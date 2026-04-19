/* ========================================
   DASHBOARD JAVASCRIPT
   ======================================== */

// Load data from PHP into the page
function loadDashboardData() {
    // Update stats are already populated by PHP, so we only need to load lists
    
    // Load recent enrollments if not already populated by PHP
    const enrollmentList = document.getElementById('recentEnrollments');
    if (enrollmentList && dashboardData.recentEnrollments && dashboardData.recentEnrollments.length > 0) {
        // If PHP already populated the list, we don't need to do anything
        // But if it's empty, we can show a message
        if (enrollmentList.children.length === 0 && dashboardData.recentEnrollments.length === 0) {
            enrollmentList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-file-signature"></i>
                    <p>No recent enrollments</p>
                </div>
            `;
        }
    }
    
    // Load recent activities if not already populated by PHP
    const activityList = document.getElementById('recentActivities');
    if (activityList && dashboardData.recentActivities && dashboardData.recentActivities.length === 0) {
        activityList.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>No recent activities</p>
            </div>
        `;
    }
}

// Initialize Chart
let enrollmentChart;

function initChart() {
    const ctx = document.getElementById('enrollmentChart');
    if (!ctx) return;
    
    const enrolledCount = dashboardData.enrolledCount || 100;
    
    // Generate realistic data based on actual enrolled count
    const weeklyData = [
        Math.round(enrolledCount * 0.05),
        Math.round(enrolledCount * 0.08),
        Math.round(enrolledCount * 0.12),
        Math.round(enrolledCount * 0.15),
        Math.round(enrolledCount * 0.18),
        Math.round(enrolledCount * 0.22),
        Math.round(enrolledCount * 0.2)
    ];
    
    const monthlyData = [
        Math.round(enrolledCount * 0.1),
        Math.round(enrolledCount * 0.15),
        Math.round(enrolledCount * 0.2),
        Math.round(enrolledCount * 0.25),
        Math.round(enrolledCount * 0.3),
        Math.round(enrolledCount * 0.35),
        Math.round(enrolledCount * 0.4),
        enrolledCount
    ];
    
    const yearlyData = [
        Math.round(enrolledCount * 0.3),
        Math.round(enrolledCount * 0.35),
        Math.round(enrolledCount * 0.4),
        Math.round(enrolledCount * 0.5),
        Math.round(enrolledCount * 0.6),
        Math.round(enrolledCount * 0.7),
        Math.round(enrolledCount * 0.8),
        Math.round(enrolledCount * 0.9),
        enrolledCount
    ];
    
    enrollmentChart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'],
            datasets: [{
                label: 'Enrollments',
                data: monthlyData,
                borderColor: '#0B4F2E',
                backgroundColor: 'rgba(11, 79, 46, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0B4F2E',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    backgroundColor: '#0B4F2E',
                    titleColor: '#FFD700',
                    bodyColor: '#fff',
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e2e8f0'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Chart period change
const chartPeriod = document.getElementById('chartPeriod');
if (chartPeriod) {
    chartPeriod.addEventListener('change', function() {
        let newData;
        let newLabels;
        const totalEnrollments = dashboardData.enrolledCount || 100;
        
        switch(this.value) {
            case 'weekly':
                newData = [
                    Math.round(totalEnrollments * 0.05),
                    Math.round(totalEnrollments * 0.08),
                    Math.round(totalEnrollments * 0.1),
                    Math.round(totalEnrollments * 0.12),
                    Math.round(totalEnrollments * 0.15),
                    Math.round(totalEnrollments * 0.18),
                    Math.round(totalEnrollments * 0.2)
                ];
                newLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                break;
            case 'monthly':
                newData = [
                    Math.round(totalEnrollments * 0.1),
                    Math.round(totalEnrollments * 0.15),
                    Math.round(totalEnrollments * 0.2),
                    Math.round(totalEnrollments * 0.25),
                    Math.round(totalEnrollments * 0.3),
                    Math.round(totalEnrollments * 0.35),
                    Math.round(totalEnrollments * 0.4),
                    totalEnrollments
                ];
                newLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'];
                break;
            case 'yearly':
                newData = [
                    Math.round(totalEnrollments * 0.3),
                    Math.round(totalEnrollments * 0.35),
                    Math.round(totalEnrollments * 0.4),
                    Math.round(totalEnrollments * 0.5),
                    Math.round(totalEnrollments * 0.6),
                    Math.round(totalEnrollments * 0.7),
                    Math.round(totalEnrollments * 0.8),
                    Math.round(totalEnrollments * 0.9),
                    totalEnrollments
                ];
                newLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
                break;
            default:
                newData = [
                    Math.round(totalEnrollments * 0.1),
                    Math.round(totalEnrollments * 0.15),
                    Math.round(totalEnrollments * 0.2),
                    Math.round(totalEnrollments * 0.25),
                    Math.round(totalEnrollments * 0.3),
                    Math.round(totalEnrollments * 0.35),
                    Math.round(totalEnrollments * 0.4),
                    totalEnrollments
                ];
                newLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'];
        }
        
        if (enrollmentChart) {
            enrollmentChart.data.datasets[0].data = newData;
            enrollmentChart.data.labels = newLabels;
            enrollmentChart.update();
        }
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    initChart();
});