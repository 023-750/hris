// ============================================
// Raquel Pawnshop HRIS - Chart.js Configurations
// ============================================

/**
 * Create a performance distribution pie chart
 * @param {string} canvasId - Canvas element ID
 * @param {object} data - {Excellent: n, 'Above Average': n, Average: n, 'Needs Improvement': n}
 */
function createPerformancePieChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement'],
            datasets: [{
                data: [
                    data['Outstanding'] || 0,
                    data['Exceeds Expectations'] || 0,
                    data['Meets Expectations'] || 0,
                    data['Needs Improvement'] || 0
                ],
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create a performance trends line chart
 * @param {string} canvasId - Canvas element ID
 * @param {array} labels - Month labels
 * @param {array} values - Average scores
 */
function createTrendLineChart(canvasId, labels, values) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Score',
                data: values,
                borderColor: '#ff8c00',
                backgroundColor: 'rgba(255, 140, 0, 0.1)',
                tension: 0.3,
                fill: true,
                pointRadius: 5,
                pointBackgroundColor: '#222222',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 100,
                    ticks: { callback: function(value) { return value + '%'; } }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Average: ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create a branch comparison bar chart
 * @param {string} canvasId - Canvas element ID
 * @param {array} labels - Branch names
 * @param {array} values - Average scores
 */
function createBranchBarChart(canvasId, labels, values) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const colors = ['#222222', '#ff8c00', '#ff6600', '#000000', '#6f42c1'];

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Score',
                data: values,
                backgroundColor: colors.slice(0, labels.length),
                borderRadius: 6,
                maxBarThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: function(value) { return value + '%'; } }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
}
