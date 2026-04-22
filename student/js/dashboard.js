// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle) {
    menuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function (event) {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && sidebar && menuToggle) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    }
});

// Auto-hide alerts
setTimeout(function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }, 300);
    });
}, 5000);

// ===== NOTIFICATION SYSTEM =====
(function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
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
        
        if (notificationList) {
            if (notifications.length > 0) {
                let html = '';
                notifications.forEach(notif => {
                    const icons = {
                        'update': 'fa-megaphone',
                        'action': 'fa-check-circle',
                        'reminder': 'fa-clock',
                        'alert': 'fa-exclamation-triangle',
                        'message': 'fa-envelope'
                    };
                    const icon = icons[notif.type] || 'fa-bell';
                    const isUnread = notif.is_read == 0;
                    
                    html += `
                        <div class="notif-item ${isUnread ? 'unread' : 'read'}" data-id="${notif.id}">
                            <div class="notif-icon notif-${notif.type}">
                                <i class="fas ${icon}"></i>
                            </div>
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
                        const notifId = this.dataset.id;
                        markAsRead(notifId, this);
                    });
                });
            } else {
                notificationList.innerHTML = `
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet</p>
                    </div>
                `;
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
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
               ' ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
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
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=mark_read&notif_id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notifItem = element.closest('.notif-item');
                notifItem.classList.remove('unread');
                notifItem.classList.add('read');
                const markBtn = notifItem.querySelector('.mark-read-btn');
                if (markBtn) markBtn.remove();
                const badge = document.querySelector('.notif-count');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                    } else {
                        badge.remove();
                    }
                }
            }
        })
        .catch(error => console.error('Error marking as read:', error));
    }
    
    function markAllAsRead() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=mark_all_read'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    const markBtn = item.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                });
                const badge = document.querySelector('.notif-count');
                if (badge) badge.remove();
            }
        })
        .catch(error => console.error('Error marking all as read:', error));
    }
    
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            markAllAsRead();
        });
    }
    
    setInterval(function() {
        if (notificationDropdown && notificationDropdown.classList.contains('show')) {
            loadNotifications();
        }
    }, 30000);
})();

// Chatbot functions (if chatbot.php is included)
function toggleChat() {
    const chatWindow = document.getElementById('chatWindow');
    if (chatWindow) {
        chatWindow.style.display = chatWindow.style.display === 'none' ? 'flex' : 'none';
        if (chatWindow.style.display === 'flex') {
            setTimeout(() => {
                const chatInput = document.getElementById('chatInput');
                if (chatInput) chatInput.focus();
            }, 100);
        }
    }
}

function setQuestion(question) {
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.value = question;
        sendMessage();
    }
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (message === '') return;

    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;

    const userMessageDiv = document.createElement('div');
    userMessageDiv.className = 'message user';
    userMessageDiv.innerHTML = `
        <div class="message-content">
            ${escapeHtml(message)}
            <span class="message-time">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
        </div>
    `;
    messagesContainer.appendChild(userMessageDiv);

    input.value = '';
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    showTypingIndicator();

    setTimeout(() => {
        removeTypingIndicator();
        const botResponse = getBotResponse(message);
        const botMessageDiv = document.createElement('div');
        botMessageDiv.className = 'message bot';
        botMessageDiv.innerHTML = `
            <div class="message-content">
                ${botResponse}
                <span class="message-time">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
            </div>
        `;
        messagesContainer.appendChild(botMessageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }, 800);
}

function showTypingIndicator() {
    const messagesContainer = document.getElementById('chatMessages');
    if (!messagesContainer) return;

    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot typing-indicator';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `<div class="message-content"><span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></div>`;
    messagesContainer.appendChild(typingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) indicator.remove();
}

function getBotResponse(message) {
    const msg = message.toLowerCase();
    
    if (msg.includes('enroll') || msg.includes('enrollment')) {
        return '📝 To enroll, please go to the Enrollment section from the sidebar or click the "Enroll Now" button on the dashboard.';
    } else if (msg.includes('schedule') || msg.includes('class schedule')) {
        return '📅 You can view your class schedule by clicking on "Class Schedule" in the sidebar menu.';
    } else if (msg.includes('grade') || msg.includes('grades')) {
        return '📚 Check your grades in the "My Grades" section. You can see your grades per subject and your overall average.';
    } else if (msg.includes('profile')) {
        return '👤 You can update your profile information in the "My Profile" section from the sidebar menu.';
    } else if (msg.includes('hello') || msg.includes('hi')) {
        return '👋 Hello! How can I help you with your student needs today?';
    } else if (msg.includes('thank')) {
        return '😊 You\'re welcome! Have a great day!';
    } else if (msg.includes('help')) {
        return '🤖 I can help you with enrollment, schedule, grades, and profile updates. What would you like to know?';
    } else {
        return '🤔 I\'m here to help! You can ask me about enrollment, class schedule, my grades, or profile updates.';
    }
}

function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Initialize chat window hidden and set active nav item
document.addEventListener('DOMContentLoaded', function () {
    const chatWindow = document.getElementById('chatWindow');
    if (chatWindow) {
        chatWindow.style.display = 'none';
    }
    
    // Add active class to current nav item
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-items a');
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        } else if (currentPage === '' || currentPage === 'dashboard.php') {
            if (linkPage === 'dashboard.php') {
                link.classList.add('active');
            }
        }
    });
});

// Handle window resize
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    }, 250);
});