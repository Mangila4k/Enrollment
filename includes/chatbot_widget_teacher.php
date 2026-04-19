<?php
/**
 * Chatbot Widget for Teacher Dashboard
 * Floating chat assistant for teacher support
 */

// Check if chatbot is enabled (you can add this to your database settings later)
$chatbot_enabled = true;
?>

<?php if($chatbot_enabled): ?>
<style>
/* Chatbot Widget Styles */
.chatbot-widget {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    font-family: 'Inter', sans-serif;
}

.chatbot-button {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #0B4F2E, #0a3d23);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
}

.chatbot-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.chatbot-button i {
    font-size: 28px;
    color: white;
}

.chatbot-button .notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f59e0b;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.chatbot-container {
    position: fixed;
    bottom: 100px;
    right: 30px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 1001;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chatbot-header {
    background: linear-gradient(135deg, #0B4F2E, #0a3d23);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-header h4 {
    margin: 0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chatbot-header h4 i {
    font-size: 20px;
}

.close-chat {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 20px;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s;
}

.close-chat:hover {
    background: rgba(255,255,255,0.2);
}

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #f5f5f5;
}

.message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.message.bot {
    align-items: flex-start;
}

.message.user {
    align-items: flex-end;
}

.message-bubble {
    max-width: 80%;
    padding: 10px 15px;
    border-radius: 15px;
    font-size: 13px;
    line-height: 1.4;
}

.message.bot .message-bubble {
    background: white;
    color: #333;
    border-bottom-left-radius: 5px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.message.user .message-bubble {
    background: #0B4F2E;
    color: white;
    border-bottom-right-radius: 5px;
}

.message-time {
    font-size: 10px;
    color: #999;
    margin-top: 5px;
    padding: 0 5px;
}

.chatbot-input {
    padding: 15px;
    background: white;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
}

.chatbot-input input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
    font-size: 13px;
}

.chatbot-input input:focus {
    border-color: #0B4F2E;
}

.chatbot-input button {
    background: #0B4F2E;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    transition: background 0.3s;
}

.chatbot-input button:hover {
    background: #0a3d23;
}

.quick-replies {
    padding: 10px 15px;
    background: white;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-reply-btn {
    background: #f0f0f0;
    border: none;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.3s;
}

.quick-reply-btn:hover {
    background: #0B4F2E;
    color: white;
}

@media (max-width: 768px) {
    .chatbot-container {
        width: 300px;
        height: 450px;
        bottom: 80px;
        right: 20px;
    }
    
    .chatbot-button {
        width: 50px;
        height: 50px;
    }
    
    .chatbot-button i {
        font-size: 24px;
    }
}
</style>

<div class="chatbot-widget">
    <div class="chatbot-button" id="chatbotToggle">
        <i class="fas fa-robot"></i>
        <div class="notification-badge" id="chatNotification" style="display: none;">1</div>
    </div>
    
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">
            <h4><i class="fas fa-robot"></i> Teacher Assistant</h4>
            <button class="close-chat" id="closeChat">×</button>
        </div>
        
        <div class="chatbot-messages" id="chatMessages">
            <div class="message bot">
                <div class="message-bubble">
                    Hello! 👋 I'm your teacher assistant. How can I help you today?
                </div>
                <div class="message-time">Just now</div>
            </div>
        </div>
        
        <div class="quick-replies">
            <button class="quick-reply-btn" data-message="How to record attendance?">📋 Attendance</button>
            <button class="quick-reply-btn" data-message="How to view my schedule?">📅 Schedule</button>
            <button class="quick-reply-btn" data-message="How to check my classes?">👥 Classes</button>
            <button class="quick-reply-btn" data-message="How to update profile?">👤 Profile</button>
        </div>
        
        <div class="chatbot-input">
            <input type="text" id="chatInput" placeholder="Type your message here..." onkeypress="handleKeyPress(event)">
            <button id="sendMessage">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Chatbot functionality
const chatbotToggle = document.getElementById('chatbotToggle');
const chatbotContainer = document.getElementById('chatbotContainer');
const closeChat = document.getElementById('closeChat');
const sendBtn = document.getElementById('sendMessage');
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');
const chatNotification = document.getElementById('chatNotification');

// Bot responses
const botResponses = {
    'attendance': 'To record attendance:\n1. Go to "QR Attendance" in the menu\n2. Generate a QR code\n3. Scan the QR code to record Time In\n4. Scan the same QR code again for Time Out\n\nNeed help? Click the generate button!',
    'schedule': 'Your schedule can be viewed in the "Schedule" section. There you can see your daily classes, time slots, and assigned sections.',
    'classes': 'Go to "My Classes" to see all sections you\'re teaching. You can view student lists, manage grades, and track attendance.',
    'profile': 'To update your profile:\n1. Click "Profile" in the ACCOUNT section\n2. Update your information\n3. Upload a profile picture\n4. Save changes',
    'qr': 'QR Code Attendance:\n• Generate a new QR code each day\n• QR code expires in 1 hour\n• First scan = Time In\n• Second scan = Time Out\n• Can\'t generate if attendance is complete',
    'grade': 'To manage grades:\n1. Go to "Grades" section\n2. Select your class\n3. Enter student grades\n4. Save and submit',
    'default': 'I\'m here to help! You can ask me about:\n• 📋 Recording attendance\n• 📅 Viewing schedule\n• 👥 Managing classes\n• 👤 Updating profile\n• 📊 Entering grades'
};

function toggleChat() {
    if (chatbotContainer.style.display === 'flex') {
        chatbotContainer.style.display = 'none';
        chatNotification.style.display = 'none';
    } else {
        chatbotContainer.style.display = 'flex';
        chatNotification.style.display = 'none';
        chatInput.focus();
    }
}

function closeChatBot() {
    chatbotContainer.style.display = 'none';
    chatNotification.style.display = 'none';
}

function addMessage(message, isUser = false) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isUser ? 'user' : 'bot'}`;
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.innerHTML = message.replace(/\n/g, '<br>');
    
    const time = document.createElement('div');
    time.className = 'message-time';
    const now = new Date();
    time.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    messageDiv.appendChild(bubble);
    messageDiv.appendChild(time);
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function getBotResponse(userMessage) {
    const msg = userMessage.toLowerCase();
    
    if (msg.includes('attendance') || msg.includes('qr') || msg.includes('time in') || msg.includes('time out')) {
        return botResponses['qr'];
    } else if (msg.includes('schedule') || msg.includes('class schedule')) {
        return botResponses['schedule'];
    } else if (msg.includes('class') || msg.includes('section')) {
        return botResponses['classes'];
    } else if (msg.includes('profile') || msg.includes('update')) {
        return botResponses['profile'];
    } else if (msg.includes('grade') || msg.includes('score')) {
        return botResponses['grade'];
    } else {
        return botResponses['default'];
    }
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    
    addMessage(message, true);
    chatInput.value = '';
    
    // Show typing indicator
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = '<div class="message-bubble">Typing<span class="dot">.</span><span class="dot">.</span><span class="dot">.</span></div>';
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    setTimeout(() => {
        document.getElementById('typingIndicator')?.remove();
        const response = getBotResponse(message);
        addMessage(response, false);
    }, 500);
}

function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Quick reply buttons
document.querySelectorAll('.quick-reply-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const message = btn.getAttribute('data-message');
        chatInput.value = message;
        sendMessage();
    });
});

// Event listeners
chatbotToggle.addEventListener('click', toggleChat);
closeChat.addEventListener('click', closeChatBot);
sendBtn.addEventListener('click', sendMessage);

// Show notification after 10 seconds if chat not opened
setTimeout(() => {
    if (chatbotContainer.style.display !== 'flex') {
        chatNotification.style.display = 'flex';
        setTimeout(() => {
            if (chatNotification.style.display === 'flex') {
                chatNotification.style.display = 'none';
            }
        }, 5000);
    }
}, 10000);
</script>
<?php endif; ?>