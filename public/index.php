<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RealTime Asset Converter — Purple Futurist</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo">RealTime Asset Converter</h1>
            <div class="header-controls">
                <div class="toggle-group">
                    <span class="toggle-label">Short Term</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="modeToggle" checked>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Long Term</span>
                </div>
                <select id="regionSelector" class="region-selector">
                    <option value="US">US</option>
                    <option value="IN">India</option>
                </select>
                <button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">🌙</button>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="input-section">
                <div class="input-box">
                    <select id="assetType" class="asset-select">
                        <option value="USD">USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="INR">INR (₹)</option>
                        <option value="JPY">JPY (¥)</option>
                        <option value="BTC">Bitcoin (BTC)</option>
                        <option value="ETH">Ethereum (ETH)</option>
                        <option value="GOLD">Gold (per gram)</option>
                        <option value="SILVER">Silver (per gram)</option>
                    </select>
                    <input type="number" id="amountInput" class="amount-input" placeholder="Enter amount" step="0.01" min="0">
                    <button id="convertBtn" class="convert-btn">Convert</button>
                </div>
            </div>

            <div id="assetCards" class="asset-cards">
                <!-- Cards will be dynamically generated here -->
            </div>
        </div>
    </main>

    <script src="../assets/js/converter.js?v=2.0"></script>
    <script src="../assets/js/ui.js?v=2.0"></script>
    <script src="../assets/js/charts.js?v=2.0"></script>
</body>
</html>

