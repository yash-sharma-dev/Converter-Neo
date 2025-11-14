<?php
/**
 * Data fetching script
 * Can be run via cron or manually to update cached data
 */

require_once __DIR__ . '/../includes/helpers.php';

echo "Starting data fetch...\n";

// Fetch and cache all data types
$results = [];

// Cryptocurrency
echo "Fetching cryptocurrency prices...\n";
$crypto = fetchCryptoPrices();
if ($crypto) {
    $cacheData = [
        'timestamp' => time(),
        'data' => $crypto
    ];
    file_put_contents(CACHE_DIR . md5('crypto') . '.json', json_encode($cacheData));
    $results['crypto'] = 'Success';
} else {
    $results['crypto'] = 'Failed';
}

// Fiat rates
echo "Fetching fiat exchange rates...\n";
$fiat = fetchFiatRates();
if ($fiat) {
    $cacheData = [
        'timestamp' => time(),
        'data' => $fiat
    ];
    file_put_contents(CACHE_DIR . md5('fiat') . '.json', json_encode($cacheData));
    $results['fiat'] = 'Success';
} else {
    $results['fiat'] = 'Failed';
}

// Metals
echo "Fetching metal prices...\n";
$metals = fetchMetalPrices();
if ($metals) {
    $cacheData = [
        'timestamp' => time(),
        'data' => $metals
    ];
    file_put_contents(CACHE_DIR . md5('metals') . '.json', json_encode($cacheData));
    $results['metals'] = 'Success';
} else {
    $results['metals'] = 'Failed';
}

// US Stocks
echo "Fetching US stock prices...\n";
$stocksUS = fetchUSStocks();
if ($stocksUS) {
    $cacheData = [
        'timestamp' => time(),
        'data' => $stocksUS
    ];
    file_put_contents(CACHE_DIR . md5('stocks_us') . '.json', json_encode($cacheData));
    $results['stocks_us'] = 'Success';
} else {
    $results['stocks_us'] = 'Failed';
}

// India Stocks
echo "Fetching India stock prices...\n";
$stocksIN = fetchIndiaStocks();
if ($stocksIN) {
    $cacheData = [
        'timestamp' => time(),
        'data' => $stocksIN
    ];
    file_put_contents(CACHE_DIR . md5('stocks_in') . '.json', json_encode($cacheData));
    $results['stocks_in'] = 'Success';
} else {
    $results['stocks_in'] = 'Failed';
}

echo "\nFetch complete:\n";
print_r($results);

// Log the update
logError("Data fetch completed: " . json_encode($results));

