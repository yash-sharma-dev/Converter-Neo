// charts.js - Renders historical trends via Chart.js

const chartInstances = {};

function renderChart(canvasId, chartData) {
    if (!chartData || !Array.isArray(chartData) || chartData.length === 0) {
        console.warn('No chart data provided');
        return;
    }

    // Destroy existing chart if it exists
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error(`Canvas element not found: ${canvasId}`);
        return;
    }

    const ctx = canvas.getContext('2d');
    
    const labels = chartData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const values = chartData.map(item => item.value);

    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Price',
                data: values,
                borderColor: 'rgba(124, 77, 255, 0.8)',
                backgroundColor: 'rgba(124, 77, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: 'rgba(179, 136, 255, 0.8)',
                pointBorderColor: '#7C4DFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(42, 24, 73, 0.9)',
                    titleColor: '#E0B3FF',
                    bodyColor: '#FFFFFF',
                    borderColor: '#7C4DFF',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        color: '#E0B3FF',
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        color: 'rgba(179, 136, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#E0B3FF',
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        color: 'rgba(179, 136, 255, 0.1)'
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutCubic'
            }
        }
    });
}

function renderSparkline(containerId, data) {
    if (!data || !Array.isArray(data) || data.length === 0) {
        return;
    }

    const container = document.getElementById(containerId);
    if (!container) return;

    // Create a simple SVG sparkline
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '100%');
    svg.setAttribute('viewBox', `0 0 ${data.length * 10} 40`);
    svg.style.overflow = 'visible';

    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;

    const points = data.map((value, index) => {
        const x = index * 10;
        const y = 40 - ((value - min) / range) * 35;
        return `${x},${y}`;
    }).join(' ');

    const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
    polyline.setAttribute('points', points);
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', '#7C4DFF');
    polyline.setAttribute('stroke-width', '2');

    svg.appendChild(polyline);
    container.innerHTML = '';
    container.appendChild(svg);
}

// Export functions
window.renderChart = renderChart;
window.renderSparkline = renderSparkline;

