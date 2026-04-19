/* ========================================
   REGISTRAR DASHBOARD JS
   Author: PLNHS
   Description: JavaScript for registrar dashboard charts and interactions
======================================== */

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initTrendsChart();
    initGradeChart();
});

// Enrollment Trends Chart
function initTrendsChart() {
    const trendsCtx = document.getElementById('trendsChart');
    if (!trendsCtx) return;
    
    new Chart(trendsCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: chartData.months,
            datasets: [
                {
                    label: 'Pending',
                    data: chartData.pending,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Enrolled',
                    data: chartData.enrolled,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Rejected',
                    data: chartData.rejected,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: {
                        size: 12
                    },
                    bodyFont: {
                        size: 11
                    },
                    padding: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 10
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                }
            }
        }
    });
}

// Grade Distribution Chart
function initGradeChart() {
    const gradeCtx = document.getElementById('gradeChart');
    if (!gradeCtx) return;
    
    // Define colors for grade levels
    const colors = [
        '#0B4F2E',
        '#1a7a42',
        '#2a9d5a',
        '#3abf6e',
        '#4ad082',
        '#5ae196',
        '#6ef2aa',
        '#7effbe'
    ];
    
    new Chart(gradeCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: chartData.gradeLabels,
            datasets: [{
                data: chartData.gradeCounts,
                backgroundColor: colors.slice(0, chartData.gradeLabels.length),
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: {
                        size: 12
                    },
                    bodyFont: {
                        size: 11
                    },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} students (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%',
            layout: {
                padding: 10
            }
        }
    });
}

// Refresh data function (for future use)
function refreshDashboard() {
    location.reload();
}

// Auto-refresh every 5 minutes (optional)
// setInterval(refreshDashboard, 300000);