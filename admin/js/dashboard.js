/* ========================================
   ADMIN DASHBOARD JAVASCRIPT
   Author: PLNHS
   Description: Complete JavaScript for admin dashboard with real-time notifications
======================================== */

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Initialize chart
    initChart();
    
    // Request notification permission
    if ('Notification' in window) {
        Notification.requestPermission();
    }
});

// ===== NOTIFICATION SYSTEM =====
(function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    let lastNotificationCount = parseInt(document.querySelector('.notif-count')?.textContent || '0');
    
    // Play notification sound
    function playNotificationSound() {
        try {
            const audio = new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3');
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch(e) {
            console.log('Audio not supported');
        }
    }
    
    // Show browser notification
    function showBrowserNotification(title, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: message,
                icon: '../pictures/logo sa skwelahan.jpg',
                silent: false
            });
            
            notification.onclick = function() {
                window.focus();
                if (notificationDropdown) {
                    notificationDropdown.classList.add('show');
                    loadNotifications();
                }
                notification.close();
            };
            
            setTimeout(() => notification.close(), 5000);
        }
    }
    
    function loadNotifications() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newCount = data.unread_count;
                
                if (newCount > lastNotificationCount) {
                    const newNotificationsCount = newCount - lastNotificationCount;
                    playNotificationSound();
                    showBrowserNotification(
                        '🔔 New Notification', 
                        `You have ${newNotificationsCount} new notification${newNotificationsCount > 1 ? 's' : ''}`
                    );
                }
                
                lastNotificationCount = newCount;
                updateNotificationUI(data.notifications, data.unread_count);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
    }
    
    function updateNotificationUI(notifications, unreadCount) {
        const notificationList = document.getElementById('notificationList');
        const badge = document.querySelector('.notif-count');
        
        if (unreadCount > 0) {
            if (badge) {
                badge.textContent = unreadCount;
            } else if (notificationBtn) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notif-count';
                newBadge.textContent = unreadCount;
                notificationBtn.appendChild(newBadge);
            }
        } else if (badge) {
            badge.remove();
        }
        
        if (notificationList && notificationDropdown && notificationDropdown.classList.contains('show')) {
            if (notifications.length > 0) {
                let html = '';
                const icons = {
                    'update': 'fa-megaphone', 'action': 'fa-check-circle',
                    'reminder': 'fa-clock', 'alert': 'fa-exclamation-triangle',
                    'message': 'fa-envelope', 'grade': 'fa-star',
                    'requirement': 'fa-file-upload', 'profile': 'fa-user-edit',
                    'enrollment': 'fa-graduation-cap'
                };
                
                notifications.forEach(notif => {
                    const icon = icons[notif.type] || 'fa-bell';
                    const isUnread = notif.is_read == 0;
                    
                    html += `
                        <div class="notif-item ${isUnread ? 'unread' : 'read'}" data-id="${notif.id}">
                            <div class="notif-icon notif-${notif.type}"><i class="fas ${icon}"></i></div>
                            <div class="notif-content">
                                <div class="notif-title">${escapeHtml(notif.title)}</div>
                                <div class="notif-message">${escapeHtml(notif.message)}</div>
                                <div class="notif-time">${formatDate(notif.created_at)}</div>
                            </div>
                            ${isUnread ? `<button class="mark-read-btn" data-id="${notif.id}"><i class="fas fa-check"></i></button>` : ''}
                        </div>
                    `;
                });
                notificationList.innerHTML = html;
                
                document.querySelectorAll('.mark-read-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        markAsRead(this.dataset.id, this);
                    });
                });
            } else {
                notificationList.innerHTML = '<div class="empty-notifications"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>';
            }
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else if (diffDays < 7) {
            return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }
    
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            if (notificationDropdown.classList.contains('show')) {
                loadNotifications();
            }
        });
    }
    
    document.addEventListener('click', function(e) {
        if (notificationDropdown && notificationBtn) {
            if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        }
    });
    
    function markAsRead(notifId, element) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=mark_read&notif_id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notifItem = element.closest('.notif-item');
                notifItem.classList.remove('unread');
                const markBtn = notifItem.querySelector('.mark-read-btn');
                if (markBtn) markBtn.remove();
                
                lastNotificationCount = data.unread_count;
                const badge = document.querySelector('.notif-count');
                if (badge && data.unread_count > 0) badge.textContent = data.unread_count;
                else if (badge) badge.remove();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }
    
    function markAllAsRead() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=mark_all_read'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.classList.remove('unread');
                    const markBtn = item.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                });
                const badge = document.querySelector('.notif-count');
                if (badge) badge.remove();
                lastNotificationCount = 0;
                showToast('All notifications marked as read', 'success');
            }
        })
        .catch(error => console.error('Error marking all notifications as read:', error));
    }
    
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) markAllBtn.addEventListener('click', () => markAllAsRead());
    
    // Auto-check for new notifications every 10 seconds
    setInterval(() => {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const newCount = data.unread_count;
                
                if (newCount > lastNotificationCount) {
                    const newNotificationsCount = newCount - lastNotificationCount;
                    
                    const badge = document.querySelector('.notif-count');
                    if (newCount > 0) {
                        if (badge) {
                            badge.textContent = newCount;
                        } else if (notificationBtn) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notif-count';
                            newBadge.textContent = newCount;
                            notificationBtn.appendChild(newBadge);
                        }
                    }
                    
                    playNotificationSound();
                    showBrowserNotification(
                        `📬 ${newNotificationsCount} New Notification${newNotificationsCount > 1 ? 's' : ''}`,
                        `You have ${newNotificationsCount} unread notification${newNotificationsCount > 1 ? 's' : ''}. Click to view.`
                    );
                    
                    lastNotificationCount = newCount;
                }
                
                if (notificationDropdown && notificationDropdown.classList.contains('show')) {
                    updateNotificationUI(data.notifications, data.unread_count);
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
    }, 10000);
})();

// ===== TOAST NOTIFICATION FUNCTION =====
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
        <span>${escapeHtml(message)}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== CHART INITIALIZATION =====
let enrollmentChart;

function initChart() {
    const ctx = document.getElementById('enrollmentChart');
    if (!ctx) return;
    
    const enrolledCount = typeof dashboardData !== 'undefined' && dashboardData.enrolledCount ? dashboardData.enrolledCount : 100;
    const monthlyData = [
        Math.round(enrolledCount * 0.1), Math.round(enrolledCount * 0.15),
        Math.round(enrolledCount * 0.2), Math.round(enrolledCount * 0.25),
        Math.round(enrolledCount * 0.3), Math.round(enrolledCount * 0.35),
        Math.round(enrolledCount * 0.4), enrolledCount
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
                    labels: { font: { size: 12, weight: 'bold' } }
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
                    grid: { color: '#e2e8f0' },
                    title: { display: true, text: 'Number of Enrollments', font: { size: 12 } }
                }, 
                x: { 
                    grid: { display: false },
                    title: { display: true, text: 'Time Period', font: { size: 12 } }
                } 
            }
        }
    });
}

const chartPeriod = document.getElementById('chartPeriod');
if (chartPeriod) {
    chartPeriod.addEventListener('change', function() {
        let newData, newLabels;
        const total = typeof dashboardData !== 'undefined' && dashboardData.enrolledCount ? dashboardData.enrolledCount : 100;
        
        switch(this.value) {
            case 'weekly':
                newData = [Math.round(total * 0.05), Math.round(total * 0.08), Math.round(total * 0.1), Math.round(total * 0.12), Math.round(total * 0.15), Math.round(total * 0.18), Math.round(total * 0.2)];
                newLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                break;
            case 'yearly':
                newData = [Math.round(total * 0.3), Math.round(total * 0.35), Math.round(total * 0.4), Math.round(total * 0.5), Math.round(total * 0.6), Math.round(total * 0.7), Math.round(total * 0.8), Math.round(total * 0.9), total];
                newLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
                break;
            default:
                newData = [Math.round(total * 0.1), Math.round(total * 0.15), Math.round(total * 0.2), Math.round(total * 0.25), Math.round(total * 0.3), Math.round(total * 0.35), Math.round(total * 0.4), total];
                newLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'];
        }
        
        if (enrollmentChart) {
            enrollmentChart.data.datasets[0].data = newData;
            enrollmentChart.data.labels = newLabels;
            enrollmentChart.update();
        }
    });
}

// ===== SEARCH FUNCTIONALITY =====
const searchInput = document.querySelector('.search-bar input');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        }
    });
}

// ===== HOVER EFFECTS =====
const statCards = document.querySelectorAll('.stat-card');
statCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});