// converter.js - Handles user input and fetches from PHP backend

/**
 * Handles network calls to the PHP backend and manages cached responses.
 * Acts as a super lightweight data layer for the UI.
 */
class AssetConverter {
    constructor() {
        this.currentMode = 'short';
        this.currentRegion = 'US';
        this.apiBase = '../api';
        this.conversionData = null;
    }

    /**
     * Convert a value for a given asset/mode/region via the backend.
     * Returns the formatted JSON payload used by the card renderer.
     */
    async convert(value, asset, mode = 'short', region = 'US') {
        if (!value || value <= 0) {
            console.error('Invalid input value');
            return null;
        }

        try {
            const response = await fetch(`${this.apiBase}/convert.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    value: parseFloat(value),
                    asset: asset,
                    mode: mode,
                    region: region
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.conversionData = data;
            return data;
        } catch (error) {
            console.error('Conversion error:', error);
            // Try to use cached data if available
            return this.getCachedData();
        }
    }

    getCachedData() {
        // Return cached data if available
        const cached = localStorage.getItem('lastConversion');
        if (cached) {
            try {
                return JSON.parse(cached);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    setCache(data) {
        localStorage.setItem('lastConversion', JSON.stringify(data));
    }
}

// Initialize converter instance
const converter = new AssetConverter();

// Handle convert button click and Enter key
document.addEventListener('DOMContentLoaded', () => {
    const convertBtn = document.getElementById('convertBtn');
    const amountInput = document.getElementById('amountInput');
    const assetType = document.getElementById('assetType');

    const performConversion = async () => {
        const value = amountInput.value;
        const asset = assetType.value;
        const mode = document.getElementById('modeToggle').checked ? 'short' : 'long';
        const region = document.getElementById('regionSelector').value;

        if (!value) {
            alert('Please enter an amount');
            return;
        }

        // Show loading state
        const cardsContainer = document.getElementById('assetCards');
        cardsContainer.innerHTML = '<div class="loading">Converting assets</div>';

        const data = await converter.convert(value, asset, mode, region);
        
        if (data) {
            converter.setCache(data);
            updateAssetCards(data);
        } else {
            cardsContainer.innerHTML = '<div class="loading">Error: Could not fetch conversion data. Please try again.</div>';
        }
    };

    convertBtn.addEventListener('click', performConversion);
    
    amountInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            performConversion();
        }
    });
});

/**
 * Render the full list/grid of asset cards with fresh conversion data.
 * Clears previous nodes to keep DOM in sync with the latest request.
 */
function updateAssetCards(data) {
    const cardsContainer = document.getElementById('assetCards');
    cardsContainer.innerHTML = '';

    if (!data || Object.keys(data).length === 0) {
        cardsContainer.innerHTML = '<div class="loading">No conversion data available</div>';
        return;
    }

    // Define asset icons
    const assetIcons = {
        'BTC': '₿',
        'ETH': 'Ξ',
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'INR': '₹',
        'JPY': '¥',
        'GOLD': '🥇',
        'SILVER': '🥈',
        'AAPL': '📱',
        'GOOGL': '🔍',
        'MSFT': '💻',
        'TSLA': '🚗',
        'RELIANCE': '🏭',
        'TCS': '💼',
        'INFY': '💻'
    };

    Object.keys(data).forEach(assetKey => {
        const asset = data[assetKey];
        const card = createAssetCard(assetKey, asset, assetIcons[assetKey] || '💎');
        cardsContainer.appendChild(card);
    });
}

/**
 * Builds a single asset card DOM node with overview, sparkline, badges, etc.
 */
function createAssetCard(assetKey, assetData, icon) {
    const card = document.createElement('div');
    card.className = 'asset-card';
    card.dataset.asset = assetKey;

    const isStale = assetData.stale || false;
    const staleBadge = isStale ? '<span class="stale-badge">Stale</span>' : '';

    card.innerHTML = `
        <div class="asset-card-header">
            <div>
                <span class="asset-icon">${icon}</span>
                <span class="asset-name">${assetKey}</span>
            </div>
            <span class="dropdown-arrow">▼</span>
        </div>
        <div class="asset-value">${formatValue(assetData.value, assetKey)}</div>
        <div class="asset-equiv">${assetData.equiv || ''}${staleBadge}</div>
        <div class="sparkline-container" id="sparkline-${assetKey}"></div>
        ${assetData.updated_at ? `<div class="update-time">Updated: ${formatTime(assetData.updated_at)}</div>` : ''}
        <div class="asset-card-overview">
            ${assetData.overview ? renderOverview(assetData.overview) : '<div class="loading">Loading overview...</div>'}
        </div>
    `;

    // Add click handler for dropdown / expansion state
    const cardHeader = card.querySelector('.asset-card-header');
    if (cardHeader) {
        cardHeader.addEventListener('click', async (e) => {
            e.stopPropagation();
            
            const isExpanded = card.classList.contains('expanded');
            card.classList.toggle('expanded');

            // Load overview if not already loaded and card is expanding
            if (!isExpanded && !assetData.overview) {
                await loadOverview(assetKey, card);
            } else if (!isExpanded && assetData.overview) {
                // Render chart if overview exists
                setTimeout(() => {
                    const chartCanvas = card.querySelector('canvas');
                    if (chartCanvas && assetData.overview.chart_data) {
                        renderChart(chartCanvas.id, assetData.overview.chart_data);
                    }
                }, 100);
            }
        });
    }

    // Render sparkline
    if (assetData.sparkline) {
        renderSparkline(`sparkline-${assetKey}`, assetData.sparkline);
    }

    return card;
}

/**
 * Generates the markup for the rich overview section inside a card.
 */
function renderOverview(overview) {
    if (!overview) return '';

    const confidenceClass = `confidence-${overview.confidence || 'medium'}`;
    const bullets = overview.bullets ? overview.bullets.map(b => `<li>${b}</li>`).join('') : '';

    return `
        <div class="overview-summary">${overview.summary || ''}</div>
        <ul class="overview-bullets">${bullets}</ul>
        <div class="overview-chart">
            <canvas id="chart-${Date.now()}"></canvas>
        </div>
        <div class="confidence-badge ${confidenceClass}">Confidence: ${overview.confidence || 'medium'}</div>
    `;
}

/**
 * Fetches on-demand overview data for a specific asset card when expanded.
 */
async function loadOverview(assetKey, cardElement) {
    const mode = document.getElementById('modeToggle').checked ? 'short' : 'long';
    
    try {
        const response = await fetch(`${converter.apiBase}/overview.php?asset=${assetKey}&mode=${mode}`);
        const overview = await response.json();
        
        const overviewDiv = cardElement.querySelector('.asset-card-overview');
        if (overviewDiv) {
            overviewDiv.innerHTML = renderOverview(overview);
            
            // Render chart after a short delay to ensure canvas is in DOM
            setTimeout(() => {
                const chartCanvas = overviewDiv.querySelector('canvas');
                if (chartCanvas && overview.chart_data) {
                    renderChart(chartCanvas.id, overview.chart_data);
                }
            }, 100);
        }
    } catch (error) {
        console.error('Error loading overview:', error);
        const overviewDiv = cardElement.querySelector('.asset-card-overview');
        if (overviewDiv) {
            overviewDiv.innerHTML = '<div class="loading">Error loading overview</div>';
        }
    }
}

/**
 * Formats numeric values based on asset category for readability.
 */
function formatValue(value, assetKey) {
    if (value === null || value === undefined) return 'N/A';
    
    // Determine decimal places based on asset type
    const cryptoAssets = ['BTC', 'ETH'];
    const fiatAssets = ['USD', 'EUR', 'GBP', 'INR', 'JPY'];
    const metalAssets = ['GOLD', 'SILVER'];
    
    let decimals = 2;
    if (cryptoAssets.includes(assetKey)) {
        decimals = 8;
    } else if (metalAssets.includes(assetKey)) {
        decimals = 2;
    } else if (fiatAssets.includes(assetKey)) {
        decimals = 2;
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(value);
}

/**
 * Converts ISO timestamps into a localised string for the UI.
 */
function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleString();
}

// Export for use in other modules
window.converter = converter;
window.updateAssetCards = updateAssetCards;

