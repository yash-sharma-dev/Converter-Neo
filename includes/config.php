<?php
/**
 * Configuration file for RealTime Asset Converter
 * Store API keys and settings here (keep outside webroot in production)
 */

// API Keys (replace with your actual keys)
define('COINGECKO_API_KEY', ''); // Optional for CoinGecko
define('ALPHAVANTAGE_API_KEY', ''); // Required for US stocks
define('METALS_API_KEY', ''); // Required for metals
define('NEWS_API_KEY', ''); // Optional for news

// Cache settings
define('CACHE_DIR', __DIR__ . '/../cache/');
define('LOG_DIR', __DIR__ . '/../logs/');

// Cache refresh intervals (in seconds)
define('CACHE_CRYPTO', 30);
define('CACHE_STOCKS', 300); // 5 minutes
define('CACHE_METALS', 900); // 15 minutes
define('CACHE_FX', 600); // 10 minutes
define('CACHE_VEHICLES', 86400); // 24 hours

// API timeout
define('API_TIMEOUT', 3); // seconds

// Base currency
define('BASE_CURRENCY', 'USD');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', LOG_DIR . 'php_errors.log');

// Ensure directories exist
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

