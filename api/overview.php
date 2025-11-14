<?php
/**
 * Overview endpoint.
 * Returns short/long term overview bullets + chart data for any supported asset.
 * The copy is intentionally opinionated to make the UI feel "alive" even without
 * a dedicated research API.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/helpers.php';

try {
    $asset = $_GET['asset'] ?? '';
    $mode = $_GET['mode'] ?? 'short';
    
    if (empty($asset)) {
        throw new Exception('Asset parameter required');
    }
    
    // Generate overview based on asset type and mode
    $overview = generateOverview($asset, $mode);
    
    echo json_encode($overview, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate overview for an asset.
 * Combines narrative copy, talking points, and simulated chart data.
 */
function generateOverview($asset, $mode) {
    $isShortTerm = ($mode === 'short');
    $days = $isShortTerm ? 180 : 1825; // 6 months or 5 years
    $points = $isShortTerm ? 30 : 60;
    
    // Generate chart data
    $chartData = generateChartData($asset, $days, $points, $isShortTerm);
    
    // Generate summary and bullets based on asset type
    $summary = '';
    $bullets = [];
    $confidence = 'medium';
    
    // Cryptocurrency overviews
    if ($asset === 'BTC') {
        if ($isShortTerm) {
            $summary = "Bitcoin shows strong momentum with increasing institutional adoption and ETF inflows.";
            $bullets = [
                "ETF demand continues to support price stability and growth.",
                "Halving cycle effects expected to impact supply dynamics.",
                "Short-term volatility may persist due to macroeconomic factors.",
                "Regulatory clarity improving in major markets."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Bitcoin's long-term outlook remains positive with growing mainstream acceptance.";
            $bullets = [
                "Store of value narrative gaining traction among institutions.",
                "Limited supply and increasing adoption support long-term appreciation.",
                "Technological improvements enhance scalability and utility.",
                "Potential for significant price appreciation over 5-year horizon."
            ];
            $confidence = 'high';
        }
    } elseif ($asset === 'ETH') {
        if ($isShortTerm) {
            $summary = "Ethereum benefits from network upgrades and DeFi ecosystem growth.";
            $bullets = [
                "Layer 2 solutions improving transaction efficiency.",
                "Staking rewards attracting long-term holders.",
                "DeFi and NFT markets driving utility demand.",
                "Upcoming upgrades may impact short-term volatility."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Ethereum's transition to proof-of-stake positions it well for long-term growth.";
            $bullets = [
                "Sustainable tokenomics with deflationary mechanism.",
                "Dominant platform for smart contracts and dApps.",
                "Growing enterprise adoption and institutional interest.",
                "Strong developer community and continuous innovation."
            ];
            $confidence = 'high';
        }
    }
    // Fiat currency overviews
    elseif (in_array($asset, ['USD', 'EUR', 'GBP', 'INR', 'JPY'])) {
        if ($isShortTerm) {
            $summary = "Currency markets influenced by central bank policies and economic indicators.";
            $bullets = [
                "Interest rate decisions impact currency strength.",
                "Inflation data drives monetary policy expectations.",
                "Geopolitical events create short-term volatility.",
                "Trade balance and economic growth affect valuation."
            ];
            $confidence = 'low';
        } else {
            $summary = "Long-term currency trends depend on economic fundamentals and policy stability.";
            $bullets = [
                "Economic growth rates determine currency appreciation potential.",
                "Central bank credibility and policy consistency matter.",
                "Demographic trends and productivity affect long-term value.",
                "Currency diversification remains important for portfolios."
            ];
            $confidence = 'medium';
        }
    }
    // Precious metals overviews
    elseif ($asset === 'GOLD') {
        if ($isShortTerm) {
            $summary = "Gold prices respond to inflation expectations and dollar strength.";
            $bullets = [
                "Central bank buying supports demand.",
                "Inflation hedge characteristics attract investors.",
                "Dollar strength inversely correlates with gold prices.",
                "Geopolitical tensions increase safe-haven demand."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Gold maintains its role as a long-term store of value and portfolio diversifier.";
            $bullets = [
                "Historical preservation of purchasing power over decades.",
                "Limited supply and mining constraints support prices.",
                "Central bank reserves continue to accumulate gold.",
                "Inflation protection remains relevant long-term."
            ];
            $confidence = 'high';
        }
    } elseif ($asset === 'SILVER') {
        if ($isShortTerm) {
            $summary = "Silver prices influenced by industrial demand and gold correlation.";
            $bullets = [
                "Industrial applications drive significant demand.",
                "Solar panel and electronics manufacturing support prices.",
                "Higher volatility than gold due to smaller market.",
                "Investment demand complements industrial usage."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Silver benefits from both investment and industrial demand over long term.";
            $bullets = [
                "Green energy transition increases industrial demand.",
                "Affordable alternative to gold for investors.",
                "Supply constraints in mining sector.",
                "Dual role as precious and industrial metal."
            ];
            $confidence = 'medium';
        }
    }
    // Stock overviews
    elseif (in_array($asset, ['AAPL', 'GOOGL', 'MSFT', 'TSLA'])) {
        if ($isShortTerm) {
            $summary = "Tech stocks face market volatility but maintain strong fundamentals.";
            $bullets = [
                "Earnings growth and innovation drive performance.",
                "Market sentiment and interest rates impact valuations.",
                "Regulatory environment affects sector outlook.",
                "Product cycles and competitive dynamics matter."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Leading tech companies positioned for long-term growth with strong moats.";
            $bullets = [
                "Market leadership and competitive advantages.",
                "Continuous innovation and R&D investments.",
                "Global expansion and market penetration.",
                "Dividend growth and shareholder returns."
            ];
            $confidence = 'high';
        }
    } elseif (in_array($asset, ['RELIANCE', 'TCS', 'INFY'])) {
        if ($isShortTerm) {
            $summary = "Indian stocks reflect economic growth and sector-specific trends.";
            $bullets = [
                "Domestic consumption and infrastructure spending support growth.",
                "IT sector benefits from digital transformation.",
                "Regulatory reforms and policy stability matter.",
                "Currency fluctuations impact export-oriented companies."
            ];
            $confidence = 'medium';
        } else {
            $summary = "Indian equities offer long-term growth potential with demographic advantages.";
            $bullets = [
                "Young population and rising middle class drive consumption.",
                "Infrastructure development creates investment opportunities.",
                "Technology and services sectors show strong fundamentals.",
                "Economic reforms support sustainable growth."
            ];
            $confidence = 'high';
        }
    }
    // Default overview
    else {
        $summary = "Asset performance depends on market conditions and fundamental factors.";
        $bullets = [
            "Market trends and economic indicators influence prices.",
            "Supply and demand dynamics determine valuation.",
            "External factors create short-term volatility.",
            "Long-term outlook based on fundamental analysis."
        ];
        $confidence = 'low';
    }
    
    return [
        'summary' => $summary,
        'bullets' => $bullets,
        'confidence' => $confidence,
        'chart_data' => $chartData
    ];
}

/**
 * Generate chart data for an asset.
 * Uses lightweight pseudo-random walk to keep charts visually interesting.
 */
function generateChartData($asset, $days, $points, $isShortTerm = true) {
    $data = [];
    $basePrice = 100; // Base price for simulation
    
    // Get current price if available
    if (in_array($asset, ['BTC', 'ETH'])) {
        $cryptoPrices = getCachedData('crypto', CACHE_CRYPTO, 'fetchCryptoPrices');
        if (isset($cryptoPrices[$asset])) {
            $basePrice = $cryptoPrices[$asset];
        }
    }
    
    $interval = $days / $points;
    $currentPrice = $basePrice;
    
    for ($i = $points; $i >= 0; $i--) {
        $daysAgo = round($i * $interval);
        $date = date('Y-m-d', strtotime("-$daysAgo days"));
        
        // Simulate price movement with trend
        $trend = $isShortTerm ? 0.001 : 0.0005; // Slight upward trend
        $volatility = rand(-500, 500) / 10000;
        $currentPrice = $currentPrice * (1 + $trend + $volatility);
        
        $data[] = [
            'date' => $date,
            'value' => round($currentPrice, 2)
        ];
    }
    
    return $data;
}

