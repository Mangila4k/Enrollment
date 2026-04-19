// Student Dashboard JavaScript

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

// Chatbot functions
function toggleChat() {
    const chatWindow = document.getElementById('chatWindow');
    if (chatWindow) {
        if (chatWindow.style.display === 'none' || chatWindow.style.display === '') {
            chatWindow.style.display = 'flex';
            setTimeout(() => {
                const chatInput = document.getElementById('chatInput');
                if (chatInput) chatInput.focus();
            }, 100);
        } else {
            chatWindow.style.display = 'none';
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
    typingDiv.innerHTML = `
        <div class="message-content">
            <span class="typing-dots">
                <span>.</span><span>.</span><span>.</span>
            </span>
        </div>
    `;
    messagesContainer.appendChild(typingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

function getBotResponse(message) {
    const msg = message.toLowerCase();
    
    if (msg.includes('enroll') || msg.includes('enrollment') || msg.includes('how to enroll')) {
        return '📝 To enroll, please go to the Enrollment section from the sidebar or click the "Enroll Now" button on the dashboard. Make sure you have all required documents ready.';
    } else if (msg.includes('schedule') || msg.includes('class schedule')) {
        return '📅 You can view your class schedule by clicking on "Class Schedule" in the sidebar menu. Your schedule will show all your subjects, times, and rooms.';
    } else if (msg.includes('attendance') || msg.includes('absent')) {
        return '📊 Your attendance records can be found in the Attendance section. You can see your daily attendance and overall attendance rate there.';
    } else if (msg.includes('grade') || msg.includes('grades') || msg.includes('average')) {
        return '📚 Check your grades in the "My Grades" section. You can see your grades per subject and your overall average.';
    } else if (msg.includes('office hour') || msg.includes('office hours') || msg.includes('school hours')) {
        return '⏰ School office hours are Monday to Friday, 7:00 AM to 5:00 PM. The registrar\'s office is open from 8:00 AM to 4:00 PM.';
    } else if (msg.includes('profile') || msg.includes('update profile')) {
        return '👤 You can update your profile information in the "Settings" or "My Profile" section from the sidebar menu.';
    } else if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey')) {
        return '👋 Hello! How can I help you with your student needs today?';
    } else if (msg.includes('thank')) {
        return '😊 You\'re welcome! Have a great day!';
    } else if (msg.includes('help')) {
        return '🤖 I can help you with enrollment, schedule, attendance, grades, office hours, and profile updates. What would you like to know?';
    } else {
        return '🤔 I\'m here to help! You can ask me about enrollment, class schedule, attendance, grades, or office hours. What would you like to know?';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Initialize chat window hidden
document.addEventListener('DOMContentLoaded', function () {
    const chatWindow = document.getElementById('chatWindow');
    if (chatWindow) {
        chatWindow.style.display = 'none';
    }

    // Auto-hide alerts if they exist
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.display = 'none';
                }
            }, 300);
        });
    }, 5000);

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