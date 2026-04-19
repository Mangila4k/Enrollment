<?php
/**
 * Chatbot Widget for Student Dashboard
 * Fetches actual student data from database
 */

// Only proceed if chatbot is enabled
$chatbot_enabled = true;
if(!$chatbot_enabled) {
    return;
}

// Initialize student data array with default values
$student_data = [
    'name' => 'Student',
    'first_name' => 'Student',
    'grade' => 'Not Enrolled',
    'section' => 'Not Assigned',
    'strand' => 'N/A',
    'email' => '',
    'status' => 'Not Enrolled',
    'student_type' => 'Student',
    'subjects_count' => 0,
    'average_grade' => '--',
    'id_number' => 'N/A',
    'gender' => 'N/A',
    'birthdate' => 'N/A',
    'adviser' => 'Not Assigned',
    'school_year' => '',
    'enrollment_date' => '',
    'total_units' => 0,
    'completed_subjects' => 0
];

// Fetch actual student data from database
if(isset($conn) && isset($_SESSION['user']['id'])) {
    try {
        $student_id = $_SESSION['user']['id'];
        
        // Get basic user info
        $user_query = "SELECT * FROM users WHERE id = :id";
        $stmt = $conn->prepare($user_query);
        $stmt->execute([':id' => $student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user) {
            $student_data['name'] = $user['fullname'] ?? 'Student';
            $student_data['first_name'] = explode(' ', $student_data['name'])[0];
            $student_data['email'] = $user['email'] ?? '';
            $student_data['id_number'] = $user['id_number'] ?? 'N/A';
            $student_data['gender'] = $user['gender'] ?? 'N/A';
            $student_data['birthdate'] = $user['birthdate'] ? date('F d, Y', strtotime($user['birthdate'])) : 'N/A';
        }
        
        // Get current enrollment
        $enroll_query = "
            SELECT e.*, g.grade_name, s.section_name, u.fullname as adviser_name
            FROM enrollments e
            JOIN grade_levels g ON e.grade_id = g.id
            LEFT JOIN sections s ON e.section_id = s.id
            LEFT JOIN users u ON s.adviser_id = u.id
            WHERE e.student_id = :student_id AND e.status = 'Enrolled'
            ORDER BY e.created_at DESC LIMIT 1
        ";
        $stmt = $conn->prepare($enroll_query);
        $stmt->execute([':student_id' => $student_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($enrollment) {
            $student_data['grade'] = $enrollment['grade_name'] ?? 'Not Enrolled';
            $student_data['section'] = $enrollment['section_name'] ?? 'Not Assigned';
            $student_data['strand'] = $enrollment['strand'] ?? 'N/A';
            $student_data['status'] = $enrollment['status'] ?? 'Pending';
            $student_data['student_type'] = ucfirst($enrollment['student_type'] ?? 'New');
            $student_data['adviser'] = $enrollment['adviser_name'] ?? 'Not Assigned';
            $student_data['school_year'] = $enrollment['school_year'] ?? '';
            $student_data['enrollment_date'] = isset($enrollment['created_at']) ? date('F d, Y', strtotime($enrollment['created_at'])) : 'N/A';
            
            // Get subjects count for this grade
            if(isset($enrollment['grade_id'])) {
                $subj_query = "SELECT COUNT(*) as count FROM subjects WHERE grade_id = :grade_id";
                $stmt = $conn->prepare($subj_query);
                $stmt->execute([':grade_id' => $enrollment['grade_id']]);
                $subj_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $student_data['subjects_count'] = $subj_result['count'] ?? 0;
                $student_data['total_units'] = $student_data['subjects_count'] * 1.5;
            }
        }
        
        // Get average grade
        $grades_query = "SELECT AVG(grade) as avg_grade FROM grades WHERE student_id = :student_id AND grade > 0";
        $stmt = $conn->prepare($grades_query);
        $stmt->execute([':student_id' => $student_id]);
        $grades_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($grades_result && $grades_result['avg_grade'] > 0) {
            $student_data['average_grade'] = round($grades_result['avg_grade'], 2);
        }
        
        // Get completed subjects count
        $completed_query = "SELECT COUNT(DISTINCT subject_id) as completed FROM grades WHERE student_id = :student_id AND grade >= 75";
        $stmt = $conn->prepare($completed_query);
        $stmt->execute([':student_id' => $student_id]);
        $completed_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $student_data['completed_subjects'] = $completed_result['completed'] ?? 0;
        
    } catch(Exception $e) {
        error_log("Chatbot data fetch error: " . $e->getMessage());
    }
}

// Override with existing dashboard variables if they have values
if(isset($first_name) && $student_data['first_name'] == 'Student') {
    $student_data['first_name'] = $first_name;
}
if(isset($grade_display) && $student_data['grade'] == 'Not Enrolled') {
    $student_data['grade'] = $grade_display;
}
if(isset($section_display) && $student_data['section'] == 'Not Assigned') {
    $student_data['section'] = $section_display;
}
if(isset($enrollment_status) && $student_data['status'] == 'Not Enrolled') {
    $student_data['status'] = $enrollment_status;
}
if(isset($subjects_count) && $student_data['subjects_count'] == 0) {
    $student_data['subjects_count'] = $subjects_count;
}
if(isset($average_grade) && $student_data['average_grade'] == '--') {
    $student_data['average_grade'] = $average_grade;
}
if(isset($student_type_display) && $student_data['student_type'] == 'Student') {
    $student_data['student_type'] = $student_type_display;
}
?>

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
    background: #ef4444;
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
    width: 380px;
    height: 550px;
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
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
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

.chatbot-header h4 i { font-size: 20px; }

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

.close-chat:hover { background: rgba(255,255,255,0.2); }

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #f9fafb;
}

.message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.message.bot { align-items: flex-start; }
.message.user { align-items: flex-end; }

.message-bubble {
    max-width: 85%;
    padding: 10px 15px;
    border-radius: 15px;
    font-size: 13px;
    line-height: 1.5;
}

.message.bot .message-bubble {
    background: white;
    color: #1e293b;
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
    color: #94a3b8;
    margin-top: 5px;
    padding: 0 5px;
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 10px 15px;
    background: white;
    border-radius: 15px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #0B4F2E;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(1) { animation-delay: 0s; }
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-10px); opacity: 1; }
}

.chatbot-input {
    padding: 15px;
    background: white;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
}

.chatbot-input input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #cbd5e1;
    border-radius: 25px;
    outline: none;
    font-size: 13px;
    transition: all 0.3s;
}

.chatbot-input input:focus {
    border-color: #0B4F2E;
    box-shadow: 0 0 0 2px rgba(11, 79, 46, 0.1);
}

.chatbot-input button {
    background: #0B4F2E;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-input button:hover {
    background: #0a3d23;
    transform: scale(1.05);
}

.quick-replies {
    padding: 10px 15px;
    background: white;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-reply-btn {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.3s;
    color: #334155;
}

.quick-reply-btn:hover {
    background: #0B4F2E;
    border-color: #0B4F2E;
    color: white;
}

.chatbot-messages::-webkit-scrollbar { width: 5px; }
.chatbot-messages::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
.chatbot-messages::-webkit-scrollbar-thumb { background: #0B4F2E; border-radius: 10px; }

@media (max-width: 768px) {
    .chatbot-container { width: 320px; height: 480px; bottom: 80px; right: 20px; }
    .chatbot-button { width: 50px; height: 50px; }
    .chatbot-button i { font-size: 24px; }
}
</style>

<div class="chatbot-widget">
    <div class="chatbot-button" id="chatbotToggle">
        <i class="fas fa-robot"></i>
        <div class="notification-badge" id="chatNotification" style="display: none;">!</div>
    </div>
    
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">
            <h4><i class="fas fa-robot"></i> Student Assistant</h4>
            <button class="close-chat" id="closeChat">×</button>
        </div>
        
        <div class="chatbot-messages" id="chatMessages">
            <div class="message bot">
                <div class="message-bubble">
                    👋 Hi <strong><?php echo htmlspecialchars($student_data['first_name']); ?></strong>! I'm your personal student assistant.<br><br>
                    I can help you with:<br>
                    • 📝 Enrollment process<br>
                    • 📅 Class schedule<br>
                    • 📚 My Grades<br>
                    • 👤 Profile updates<br>
                    • 📋 Requirements<br>
                    • 📊 Your personal information
                </div>
                <div class="message-time">Just now</div>
            </div>
        </div>
        
        <div class="quick-replies">
            <button class="quick-reply-btn" data-message="Show my info">👤 My Info</button>
            <button class="quick-reply-btn" data-message="How to enroll?">📝 Enrollment</button>
            <button class="quick-reply-btn" data-message="My class schedule">📅 Schedule</button>
            <button class="quick-reply-btn" data-message="My grades">📚 Grades</button>
            <button class="quick-reply-btn" data-message="Office hours">⏰ Office Hours</button>
        </div>
        
        <div class="chatbot-input">
            <input type="text" id="chatInput" placeholder="Type your question here..." onkeypress="handleKeyPress(event)">
            <button id="sendMessage">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Student data fetched from database
const studentData = {
    name: '<?php echo addslashes($student_data['name']); ?>',
    first_name: '<?php echo addslashes($student_data['first_name']); ?>',
    grade: '<?php echo addslashes($student_data['grade']); ?>',
    section: '<?php echo addslashes($student_data['section']); ?>',
    strand: '<?php echo addslashes($student_data['strand']); ?>',
    email: '<?php echo addslashes($student_data['email']); ?>',
    status: '<?php echo addslashes($student_data['status']); ?>',
    student_type: '<?php echo addslashes($student_data['student_type']); ?>',
    id_number: '<?php echo addslashes($student_data['id_number']); ?>',
    gender: '<?php echo addslashes($student_data['gender']); ?>',
    birthdate: '<?php echo addslashes($student_data['birthdate']); ?>',
    adviser: '<?php echo addslashes($student_data['adviser']); ?>',
    school_year: '<?php echo addslashes($student_data['school_year']); ?>',
    enrollment_date: '<?php echo addslashes($student_data['enrollment_date']); ?>',
    subjects_count: <?php echo $student_data['subjects_count']; ?>,
    average_grade: '<?php echo $student_data['average_grade']; ?>',
    total_units: <?php echo $student_data['total_units']; ?>,
    completed_subjects: <?php echo $student_data['completed_subjects']; ?>
};

const chatbotToggle = document.getElementById('chatbotToggle');
const chatbotContainer = document.getElementById('chatbotContainer');
const closeChat = document.getElementById('closeChat');
const sendBtn = document.getElementById('sendMessage');
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');
const chatNotification = document.getElementById('chatNotification');

function toggleChat() {
    if (chatbotContainer.style.display === 'flex') {
        chatbotContainer.style.display = 'none';
        if (chatNotification) chatNotification.style.display = 'none';
    } else {
        chatbotContainer.style.display = 'flex';
        if (chatNotification) chatNotification.style.display = 'none';
        setTimeout(() => chatInput?.focus(), 300);
    }
}

function closeChatBot() {
    chatbotContainer.style.display = 'none';
    if (chatNotification) chatNotification.style.display = 'none';
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

function showTypingIndicator() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `<div class="typing-indicator"><span></span><span></span><span></span></div>`;
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) indicator.remove();
}

function getStudentInfoResponse() {
    return `👤 **YOUR STUDENT INFORMATION**\n\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `Full Name: ${studentData.name}\n` +
           `Student ID: ${studentData.id_number}\n` +
           `Birthdate: ${studentData.birthdate}\n` +
           `Gender: ${studentData.gender}\n` +
           `Email: ${studentData.email}\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `** ACADEMIC INFORMATION**\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `Grade Level: ${studentData.grade}\n` +
           `Section: ${studentData.section}\n` +
           `Strand: ${studentData.strand}\n` +
           `Adviser: ${studentData.adviser}\n` +
           `School Year: ${studentData.school_year}\n` +
           `Enrollment Date: ${studentData.enrollment_date}\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `**📊 ACADEMIC PERFORMANCE**\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `📖 Total Subjects: ${studentData.subjects_count}\n` +
           `✅ Completed Subjects: ${studentData.completed_subjects}\n` +
           `📊 Average Grade: ${studentData.average_grade}\n` +
           `🎯 Total Units: ${studentData.total_units}\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `**✅ ENROLLMENT STATUS**\n` +
           `━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
           `📌 Status: ${studentData.status}\n` +
           `🎓 Student Type: ${studentData.student_type}\n\n` +
           `Need help with anything specific? Just ask! 😊`;
}

function getBotResponse(userMessage) {
    const msg = userMessage.toLowerCase();
    
    // Personal info related
    if (msg.includes('my info') || msg.includes('my information') || msg.includes('who am i') || 
        msg.includes('show my') || msg.includes('my details') || msg.includes('tell me about myself')) {
        return getStudentInfoResponse();
    }
    
    // Grade level related
    if (msg.includes('my grade') || msg.includes('what grade') || msg.includes('which grade') || 
        msg.includes('what year') || msg.includes('grade level')) {
        return `You are currently in ${studentData.grade}.\n\nYour section is ${studentData.section} and your adviser is ${studentData.adviser}.`;
    }
    
    // Average grade related
    if (msg.includes('my average') || msg.includes('my gpa') || msg.includes('average grade') || 
        msg.includes('what is my average') || msg.includes('my grades average')) {
        return `Your current overall average is ${studentData.average_grade}.\n\nYou have completed ${studentData.completed_subjects} out of ${studentData.subjects_count} subjects.`;
    }
    
    // Adviser related
    if (msg.includes('my adviser') || msg.includes('who is my adviser') || msg.includes('adviser name') || 
        msg.includes('my teacher') || msg.includes('class adviser')) {
        return `Your adviser is ${studentData.adviser}.\n\nYou can contact them for academic concerns and guidance.`;
    }
    
    // Schedule related
    if (msg.includes('my schedule') || msg.includes('class schedule') || msg.includes('time table') || 
        msg.includes('timetable') || msg.includes('what is my schedule') || msg.includes('when is my class')) {
        return `Your class schedule can be found in the "Class Schedule" section.\n\nYour Details:\n• Grade: ${studentData.grade}\n• Section: ${studentData.section}\n• Strand: ${studentData.strand}\n• Adviser: ${studentData.adviser}`;
    }
    
    // Subjects related
    if (msg.includes('my subjects') || msg.includes('what subjects') || msg.includes('subjects i have') || 
        msg.includes('what are my subjects') || msg.includes('subject list')) {
        return `You are currently enrolled in ${studentData.subjects_count} subjects for ${studentData.grade}.\n\nYou have completed ${studentData.completed_subjects} subjects with passing grades.`;
    }
    
    // Enrollment related
    if (msg.includes('enroll') || msg.includes('enrollment') || msg.includes('enrollment status') || 
        msg.includes('am i enrolled') || msg.includes('enrollment process') || msg.includes('how to enroll')) {
        return `**Enrollment Information**\n\nYour current enrollment status: ${studentData.status}\nStudent Type: ${studentData.student_type}\nSchool Year: ${studentData.school_year}\nEnrollment Date: ${studentData.enrollment_date}`;
    }
    
    // Office hours
    if (msg.includes('office hour') || msg.includes('school hour') || msg.includes('school hours') || 
        msg.includes('office hours') || msg.includes('contact') || msg.includes('school contact') || 
        msg.includes('phone') || msg.includes('telephone') || msg.includes('call')) {
        return `School Office Hours\n\n📅 Monday to Friday: 7:00 AM - 5:00 PM\n\nRegistrar's Office: 8:00 AM - 4:00 PM\nPrincipal's Office: 8:00 AM - 5:00 PM\n\n📍 Langtad, City of Naga, Cebu 6037\n📞 (032) 123-4567\n📧 info@plsshs.edu.ph`;
    }
    
    // Greetings
    if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey') || msg.includes('good morning') || 
        msg.includes('good afternoon') || msg.includes('good evening') || msg.includes('greetings') || 
        msg.includes('howdy') || msg.includes('sup') || msg.includes('yo')) {
        return `Hello ${studentData.first_name}! Welcome back to PLS-NHS.\n\n📊 Quick Overview:\n• Grade: ${studentData.grade} | Section: ${studentData.section}\n• Average Grade: ${studentData.average_grade}\n• Status: ${studentData.status}\n\nHow can I assist you today?`;
    }
    
    // Help
    if (msg.includes('help') || msg.includes('what can you do') || msg.includes('capabilities') || 
        msg.includes('what do you do') || msg.includes('how can you help') || msg.includes('features')) {
        return `❓ I can help you with:\n\n📊 My Info - View your complete student profile\n📝 Enrollment - Check enrollment status\n📅 Schedule - View class schedule\n📚 Grades - Check your grades\n⏰ Office Hours - School contact information\n\nJust type what you want to know! For example:\n• "Show my info"\n• "What is my grade level?"\n• "Who is my adviser?"\n• "How to enroll?"`;
    }
    
    // Thank you
    if (msg.includes('thank') || msg.includes('thanks') || msg.includes('appreciate') || 
        msg.includes('grateful') || msg.includes('ty') || msg.includes('thx')) {
        return `😊 You're very welcome, ${studentData.first_name}! I'm happy to help. Have a great day!`;
    }
    
    // Farewell
    if (msg.includes('bye') || msg.includes('goodbye') || msg.includes('see you') || 
        msg.includes('farewell') || msg.includes('cya') || msg.includes('take care')) {
        return `👋 Goodbye ${studentData.first_name}! Have a wonderful day. Come back if you need any help!`;
    }
    
    // How are you
    if (msg.includes('how are you') || msg.includes('how are you doing') || msg.includes('how\'s it going') || 
        msg.includes('how do you do') || msg.includes('what\'s up')) {
        return `I'm doing great, ${studentData.first_name}! Thanks for asking. I'm here and ready to help you with anything you need about your studies. How can I assist you today? 😊`;
    }
    
    // Name
    if (msg.includes('your name') || msg.includes('who are you') || msg.includes('what are you') || 
        msg.includes('what is your name')) {
        return `I'm your personal PLS-NHS Student Assistant! 🤖\n\nI'm here to help you with enrollment, schedules, grades, and any questions about your student life. You can call me "NHS Assistant" or just "Assistant"!`;
    }
    
    // Default response
    return `I'm here to help, ${studentData.first_name}! 😊\n\nYou can ask me about:\n• 📊 My Info - Your personal information\n• 📝 Enrollment - Enrollment status\n• 📅 Schedule - Class schedule\n• 📚 Grades - Your grades\n• ⏰ Office Hours - School contact\n\nWhat would you like to know?`;
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    
    addMessage(message, true);
    chatInput.value = '';
    
    showTypingIndicator();
    
    setTimeout(() => {
        removeTypingIndicator();
        const response = getBotResponse(message);
        addMessage(response, false);
    }, 600);
}

function handleKeyPress(event) {
    if (event.key === 'Enter') sendMessage();
}

document.querySelectorAll('.quick-reply-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        chatInput.value = btn.getAttribute('data-message');
        sendMessage();
    });
});

if (chatbotToggle) chatbotToggle.addEventListener('click', toggleChat);
if (closeChat) closeChat.addEventListener('click', closeChatBot);
if (sendBtn) sendBtn.addEventListener('click', sendMessage);

setTimeout(() => {
    if (chatbotContainer && chatbotContainer.style.display !== 'flex' && chatNotification) {
        chatNotification.style.display = 'flex';
        setTimeout(() => { if (chatNotification) chatNotification.style.display = 'none'; }, 8000);
    }
}, 10000);

if (chatbotToggle) {
    chatbotToggle.addEventListener('click', () => {
        if (chatNotification) chatNotification.style.display = 'none';
    });
}
</script>