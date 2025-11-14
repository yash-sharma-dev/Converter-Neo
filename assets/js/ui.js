// ui.js - Toggle modes, dropdown animations, theme switching, smooth scrolling helpers.
// Separated from converter logic to keep responsibilities focused on pure UI interactions.

document.addEventListener('DOMContentLoaded', () => {
    /**
     * Short/Long term toggle – updates all expanded cards so their
     * overview data reflects the newly selected investment horizon.
     */
    // Mode toggle (Short Term / Long Term)
    const modeToggle = document.getElementById('modeToggle');
    modeToggle.addEventListener('change', () => {
        const mode = modeToggle.checked ? 'short' : 'long';
        console.log('Mode changed to:', mode);
        
        // Refresh all expanded cards if conversion data exists
        const cards = document.querySelectorAll('.asset-card.expanded');
        cards.forEach(async (card) => {
            const assetKey = card.dataset.asset;
            await loadOverviewForCard(assetKey, card);
        });
    });

    /**
     * Region selector – automatically re-runs the latest conversion when
     * a region changes and an amount is already present.
     */
    // Region selector
    const regionSelector = document.getElementById('regionSelector');
    regionSelector.addEventListener('change', () => {
        const region = regionSelector.value;
        console.log('Region changed to:', region);
        
        // Trigger new conversion if amount is entered
        const amountInput = document.getElementById('amountInput');
        if (amountInput.value) {
            document.getElementById('convertBtn').click();
        }
    });

    /**
     * Theme toggle – switches between dark/light CSS variables and
     * persists the preference in localStorage.
     */
    const themeToggle = document.getElementById('themeToggle');
    let isDark = true;
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        isDark = false;
        document.body.classList.add('light-theme');
        themeToggle.textContent = '☀️';
    }
    
    themeToggle.addEventListener('click', () => {
        isDark = !isDark;
        document.body.classList.toggle('light-theme', !isDark);
        themeToggle.textContent = isDark ? '🌙' : '☀️';
        
        // Save theme preference
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });

    // Smooth scroll for better UX
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

/**
 * Refresh overview details for a card that is already expanded.
 * Used when switching between Short/Long modes.
 */
async function loadOverviewForCard(assetKey, cardElement) {
    const mode = document.getElementById('modeToggle').checked ? 'short' : 'long';
    
    try {
        const response = await fetch(`../api/overview.php?asset=${assetKey}&mode=${mode}`);
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
    }
}

/**
 * Shares the same overview renderer as converter.js so both paths stay in sync.
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

// Make renderOverview available globally
window.renderOverview = renderOverview;

