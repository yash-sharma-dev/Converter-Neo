<?php
/**
 * Helper functions for conversions, caching, and API calls.
 * These utilities are shared by every API endpoint and even CLI scripts.
 * The goal is to keep transport logic (convert.php/overview.php) extremely thin.
 */

require_once __DIR__ . '/config.php';

/**
 * Generic cache wrapper. Accepts a callback that knows how to
 * fetch fresh data and persists the result alongside a timestamp.
 *
 * @param string   $key      Cache namespace (e.g. 'crypto', 'stocks_us')
 * @param int      $ttl      Time-to-live in seconds
 * @param callable $callback Function name/string to invoke on cache miss
 *
 * @return mixed|null        Cached payload or null when both network/cache fail
 */
function getCachedData($key, $ttl, $callback) {
    $cacheFile = CACHE_DIR . md5($key) . '.json';
    
    // 1. Serve fresh cache if available
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data['timestamp'])) {
            $age = time() - $data['timestamp'];
            if ($age < $ttl) {
                return $data['data'];
            }
        }
    }
    
    // 2. Attempt to fetch new data and persist it for future calls
    try {
        $newData = $callback();
        if ($newData !== null) {
            $cacheData = [
                'timestamp' => time(),
                'data' => $newData
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
            return $newData;
        }
    } catch (Exception $e) {
        logError("Error fetching data for $key: " . $e->getMessage());
    }
    
    // 3. Fallback to stale cache so the UI still renders something useful
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data['data'])) {
            return $data['data'];
        }
    }
    
    return null;
}

/**
 * Check if data is stale
 */
function isStale($key, $ttl) {
    $cacheFile = CACHE_DIR . md5($key) . '.json';
    if (!file_exists($cacheFile)) {
        return true;
    }
    
    $data = json_decode(file_get_contents($cacheFile), true);
    if (!$data || !isset($data['timestamp'])) {
        return true;
    }
    
    $age = time() - $data['timestamp'];
    return $age >= $ttl;
}

/**
 * Fetch cryptocurrency prices (USD quotes for BTC/ETH).
 * CoinGecko is used because it does not require an API key for simple spot data.
 */
function fetchCryptoPrices() {
    $cryptos = ['bitcoin', 'ethereum'];
    $ids = implode(',', $cryptos);
    $url = "https://api.coingecko.com/api/v3/simple/price?ids=$ids&vs_currencies=usd";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return [
            'BTC' => $data['bitcoin']['usd'] ?? null,
            'ETH' => $data['ethereum']['usd'] ?? null
        ];
    }
    
    return null;
}

/**
 * Fetch fiat exchange rates (base USD) via exchangerate.host.
 * Used for both USD normalization and displaying direct fiat conversions.
 */
function fetchFiatRates() {
    $url = "https://api.exchangerate.host/latest?base=USD";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return $data['rates'] ?? null;
    }
    
    return null;
}

/**
 * Fetch metal prices (Gold and Silver per gram).
 * The metals.live endpoint returns per-ounce data, so we normalize to grams.
 * Includes a fallback constant to keep the UI running when the API fails.
 */
function fetchMetalPrices() {
    // Using a free API endpoint (metals-api.com requires API key)
    // Fallback to approximate values if API fails
    $url = "https://api.metals.live/v1/spot";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        // Convert from per ounce to per gram (1 oz = 31.1035 grams)
        if (isset($data['gold']) && isset($data['silver'])) {
            return [
                'GOLD' => $data['gold'] / 31.1035,
                'SILVER' => $data['silver'] / 31.1035
            ];
        }
    }
    
    // Fallback approximate prices (as of 2024)
    return [
        'GOLD' => 65.0, // USD per gram
        'SILVER' => 0.85 // USD per gram
    ];
}

/**
 * Fetch US stock prices from Yahoo Finance's public chart endpoint.
 * The API is polled one symbol at a time to keep the implementation simple.
 */
function fetchUSStocks() {
    $stocks = ['AAPL', 'GOOGL', 'MSFT', 'TSLA'];
    $prices = [];
    
    foreach ($stocks as $symbol) {
        // Using Yahoo Finance API (free, no key required)
        $url = "https://query2.finance.yahoo.com/v8/finance/chart/$symbol";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                $prices[$symbol] = $data['chart']['result'][0]['meta']['regularMarketPrice'];
            }
        }
    }
    
    return $prices;
}

/**
 * Fetch India stock prices (NSE tickers) from Yahoo Finance.
 * Symbols include the `.NS` suffix and are trimmed before returning.
 */
function fetchIndiaStocks() {
    $stocks = ['RELIANCE.NS', 'TCS.NS', 'INFY.NS']; // Yahoo Finance format
    $prices = [];
    
    foreach ($stocks as $symbol) {
        $url = "https://query2.finance.yahoo.com/v8/finance/chart/$symbol";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                $symbolClean = str_replace('.NS', '', $symbol);
                $prices[$symbolClean] = $data['chart']['result'][0]['meta']['regularMarketPrice'];
            }
        }
    }
    
    return $prices;
}

/**
 * Get vehicle prices (static data). This is an example of a "synthetic" asset
 * that does not require external APIs but still benefits from the same UI flow.
 */
function getVehiclePrices($region = 'US') {
    $vehicles = [
        'US' => [
            ['model' => 'Tesla Model 3', 'price' => 38000],
            ['model' => 'Toyota Camry', 'price' => 26000],
            ['model' => 'Honda Accord', 'price' => 27000],
            ['model' => 'Ford F-150', 'price' => 35000]
        ],
        'IN' => [
            ['model' => 'Maruti Swift', 'price' => 850000],
            ['model' => 'Hyundai Creta', 'price' => 1200000],
            ['model' => 'Mahindra XUV700', 'price' => 1500000],
            ['model' => 'Tata Nexon', 'price' => 800000]
        ]
    ];
    
    return $vehicles[$region] ?? $vehicles['US'];
}

/**
 * Convert a raw value (entered by the user) into USD.
 * All downstream conversions start from this neutral currency to simplify math.
 *
 * @param float  $value  User-entered quantity
 * @param string $asset  Symbol or model identifier
 * @param string $region Currently selected region (affects stocks/vehicles)
 *
 * @return float|null
 */
function convertToUSD($value, $asset, $region = 'US') {
    $cryptoPrices = getCachedData('crypto', CACHE_CRYPTO, 'fetchCryptoPrices');
    $fiatRates = getCachedData('fiat', CACHE_FX, 'fetchFiatRates');
    $metalPrices = getCachedData('metals', CACHE_METALS, 'fetchMetalPrices');
    
    // Cryptocurrencies
    if ($asset === 'BTC' && isset($cryptoPrices['BTC'])) {
        return $value * $cryptoPrices['BTC'];
    }
    if ($asset === 'ETH' && isset($cryptoPrices['ETH'])) {
        return $value * $cryptoPrices['ETH'];
    }
    
    // Fiat currencies
    if (isset($fiatRates[$asset])) {
        return $value / $fiatRates[$asset];
    }
    
    // Metals (per gram)
    if ($asset === 'GOLD' && isset($metalPrices['GOLD'])) {
        return $value * $metalPrices['GOLD'];
    }
    if ($asset === 'SILVER' && isset($metalPrices['SILVER'])) {
        return $value * $metalPrices['SILVER'];
    }
    
    // Stocks (region aware so we only fetch what we need)
    if ($region === 'US') {
        $stocks = getCachedData('stocks_us', CACHE_STOCKS, 'fetchUSStocks');
        if (isset($stocks[$asset])) {
            return $value * $stocks[$asset];
        }
    } else {
        $stocks = getCachedData('stocks_in', CACHE_STOCKS, 'fetchIndiaStocks');
        if (isset($stocks[$asset])) {
            return $value * $stocks[$asset];
        }
    }
    
    // Vehicles - check if asset matches a vehicle model
    $vehicles = getVehiclePrices($region);
    foreach ($vehicles as $vehicle) {
        if ($asset === $vehicle['model']) {
            $price = $vehicle['price'];
            // Indian vehicle prices are in INR, convert to USD
            if ($region === 'IN') {
                if (isset($fiatRates['INR'])) {
                    return ($value * $price) / $fiatRates['INR'];
                }
            } else {
                // US vehicles already in USD
                return $value * $price;
            }
        }
    }
    
    // Default: assume the incoming value is already USD denominated
    return $value;
}

/**
 * Convert a USD amount into a specific target asset.
 * Essentially the inverse of convertToUSD(), sharing the same cache buckets.
 */
function convertFromUSD($usdValue, $targetAsset, $region = 'US') {
    $cryptoPrices = getCachedData('crypto', CACHE_CRYPTO, 'fetchCryptoPrices');
    $fiatRates = getCachedData('fiat', CACHE_FX, 'fetchFiatRates');
    $metalPrices = getCachedData('metals', CACHE_METALS, 'fetchMetalPrices');
    
    // Cryptocurrencies
    if ($targetAsset === 'BTC' && isset($cryptoPrices['BTC'])) {
        return $usdValue / $cryptoPrices['BTC'];
    }
    if ($targetAsset === 'ETH' && isset($cryptoPrices['ETH'])) {
        return $usdValue / $cryptoPrices['ETH'];
    }
    
    // Fiat currencies
    if (isset($fiatRates[$targetAsset])) {
        return $usdValue * $fiatRates[$targetAsset];
    }
    
    // Metals (per gram)
    if ($targetAsset === 'GOLD' && isset($metalPrices['GOLD'])) {
        return $usdValue / $metalPrices['GOLD'];
    }
    if ($targetAsset === 'SILVER' && isset($metalPrices['SILVER'])) {
        return $usdValue / $metalPrices['SILVER'];
    }
    
    // Stocks
    if ($region === 'US') {
        $stocks = getCachedData('stocks_us', CACHE_STOCKS, 'fetchUSStocks');
        if (isset($stocks[$targetAsset])) {
            return $usdValue / $stocks[$targetAsset];
        }
    } else {
        $stocks = getCachedData('stocks_in', CACHE_STOCKS, 'fetchIndiaStocks');
        if (isset($stocks[$targetAsset])) {
            return $usdValue / $stocks[$targetAsset];
        }
    }
    
    // Vehicles
    $vehicles = getVehiclePrices($region);
    foreach ($vehicles as $vehicle) {
        if ($targetAsset === $vehicle['model']) {
            $price = $vehicle['price'];
            // Indian vehicle prices are in INR, need to convert
            if ($region === 'IN') {
                if (isset($fiatRates['INR'])) {
                    $priceInUSD = $price / $fiatRates['INR'];
                    return $usdValue / $priceInUSD;
                }
            } else {
                // US vehicles already in USD
                return $usdValue / $price;
            }
        }
    }
    
    return null;
}

/**
 * Helper for mapping ISO codes to friendly currency symbols.
 */
function getCurrencySymbol($asset) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'JPY' => '¥'
    ];
    return $symbols[$asset] ?? '';
}

/**
 * Log errors in a consistent timestamped format.
 * Keeps history around issues such as API rate limits or malformed payloads.
 */
function logError($message) {
    $logFile = LOG_DIR . 'errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Generate sparkline data (simple price history).
 * In production you could replace this with historical quotes from an API,
 * but for now synthetic data keeps the UI engaging during demos.
 */
function generateSparkline($asset, $days = 30) {
    // Generate mock sparkline data (in production, fetch from historical API)
    $data = [];
    $basePrice = 100;
    
    for ($i = $days; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $variation = (rand(-100, 100) / 1000); // Small random variation
        $price = $basePrice * (1 + $variation);
        $data[] = round($price, 2);
        $basePrice = $price;
    }
    
    return $data;
}

