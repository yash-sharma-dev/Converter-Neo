<?php
/**
 * Main conversion endpoint
 * Accepts POST { value, asset, mode, region } and returns JSON with converted equivalents
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/helpers.php';

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $value = floatval($input['value'] ?? 0);
    $asset = $input['asset'] ?? 'USD';
    $mode = $input['mode'] ?? 'short';
    $region = $input['region'] ?? 'US';
    
    if ($value <= 0) {
        throw new Exception('Invalid value');
    }
    
    // Convert source asset to USD
    $usdValue = convertToUSD($value, $asset, $region);
    
    if ($usdValue === null) {
        throw new Exception('Conversion to USD failed');
    }
    
    // Define all target assets
    $targetAssets = [
        'BTC', 'ETH',
        'USD', 'EUR', 'GBP', 'INR', 'JPY',
        'GOLD', 'SILVER'
    ];
    
    // Add stocks based on region
    if ($region === 'US') {
        $targetAssets = array_merge($targetAssets, ['AAPL', 'GOOGL', 'MSFT', 'TSLA']);
    } else {
        $targetAssets = array_merge($targetAssets, ['RELIANCE', 'TCS', 'INFY']);
    }
    
    // Add vehicles
    $vehicles = getVehiclePrices($region);
    foreach ($vehicles as $vehicle) {
        $targetAssets[] = $vehicle['model'];
    }
    
    // Convert to all target assets
    $results = [];
    $now = new DateTime();
    
    foreach ($targetAssets as $targetAsset) {
        // Skip if same as source
        if ($targetAsset === $asset) {
            continue;
        }
        
        $convertedValue = convertFromUSD($usdValue, $targetAsset, $region);
        
        if ($convertedValue !== null) {
            // Format equivalent string
            $sourceSymbol = getCurrencySymbol($asset) ?: $asset;
            $targetSymbol = getCurrencySymbol($targetAsset) ?: $targetAsset;
            
            // Check if target is a vehicle
            $vehicles = getVehiclePrices($region);
            $isVehicle = false;
            foreach ($vehicles as $vehicle) {
                if ($targetAsset === $vehicle['model']) {
                    $isVehicle = true;
                    break;
                }
            }
            
            if (in_array($targetAsset, ['BTC', 'ETH'])) {
                $equiv = "$sourceSymbol" . number_format($value, 2) . " ≈ " . number_format($convertedValue, 8) . " $targetAsset";
            } elseif (in_array($targetAsset, ['GOLD', 'SILVER'])) {
                $equiv = "$sourceSymbol" . number_format($value, 2) . " ≈ " . number_format($convertedValue, 2) . " grams $targetAsset";
            } elseif ($isVehicle) {
                $equiv = "$sourceSymbol" . number_format($value, 2) . " ≈ " . number_format($convertedValue, 2) . " " . $targetAsset;
            } else {
                $equiv = "$sourceSymbol" . number_format($value, 2) . " ≈ " . number_format($convertedValue, 2) . " $targetSymbol";
            }
            
            // Check if data is stale
            $stale = false;
            if (in_array($targetAsset, ['BTC', 'ETH'])) {
                $stale = isStale('crypto', CACHE_CRYPTO);
            } elseif (in_array($targetAsset, ['USD', 'EUR', 'GBP', 'INR', 'JPY'])) {
                $stale = isStale('fiat', CACHE_FX);
            } elseif (in_array($targetAsset, ['GOLD', 'SILVER'])) {
                $stale = isStale('metals', CACHE_METALS);
            } elseif (in_array($targetAsset, ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'RELIANCE', 'TCS', 'INFY'])) {
                $stale = isStale('stocks_' . strtolower($region), CACHE_STOCKS);
            }
            
            $results[$targetAsset] = [
                'value' => $convertedValue,
                'equiv' => $equiv,
                'updated_at' => $now->format('c'),
                'stale' => $stale,
                'sparkline' => generateSparkline($targetAsset)
            ];
        }
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

