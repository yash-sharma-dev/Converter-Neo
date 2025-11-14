<?php
/**
 * Helper functions for conversions, caching, and API calls
 */

require_once __DIR__ . '/config.php';

/**
 * Get cached data or fetch from API
 */
function getCachedData($key, $ttl, $callback) {
    $cacheFile = CACHE_DIR . md5($key) . '.json';
    
    // Check if cache exists and is fresh
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && isset($data['timestamp'])) {
            $age = time() - $data['timestamp'];
            if ($age < $ttl) {
                return $data['data'];
            }
        }
    }
    
    // Fetch new data
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
    
    // Fallback to cached data even if stale
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
 * Fetch cryptocurrency prices
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
 * Fetch fiat exchange rates
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
 * Fetch metal prices (Gold and Silver per gram)
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
 * Fetch US stock prices
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
 * Fetch India stock prices
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
 * Get vehicle prices (static data)
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
 * Convert value from source asset to USD
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
    
    // Stocks
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
    
    // Default: assume already in USD
    return $value;
}

/**
 * Convert USD to target asset
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
 * Format currency symbol
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
 * Log errors
 */
function logError($message) {
    $logFile = LOG_DIR . 'errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Generate sparkline data (simple price history)
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

