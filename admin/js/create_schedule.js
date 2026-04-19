/* ========================================
   CREATE SCHEDULE PAGE JAVASCRIPT
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
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        });
    }, 5000);
});

// Data for validation (passed from PHP)
const takenTeachers = window.takenTeachersData || {};
const takenRooms = window.takenRoomsData || {};
const takenSubjects = window.takenSubjectsData || [];

// Get form elements
const teacherSelect = document.getElementById('teacher_id');
const daySelect = document.getElementById('day_id');
const timeSlotSelect = document.getElementById('time_slot_id');
const roomInput = document.getElementById('room');
const quarterSelect = document.getElementById('quarter');
const conflictWarning = document.getElementById('conflictWarning');
const conflictMessage = document.getElementById('conflictMessage');

// Function to check for conflicts
function checkConflicts() {
    if (!teacherSelect || !daySelect || !timeSlotSelect) return true;
    
    const teacherId = teacherSelect.value;
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    const room = roomInput ? roomInput.value.trim() : '';
    
    let hasConflict = false;
    let message = '';
    
    if (teacherId && dayId && timeSlotId) {
        const key = dayId + '_' + timeSlotId;
        
        // Check teacher conflict
        if (takenTeachers[key] && takenTeachers[key].includes(parseInt(teacherId))) {
            hasConflict = true;
            message = '⚠️ This teacher is already assigned to another class at this day and time!';
        }
        // Check room conflict
        else if (room && takenRooms[key] && takenRooms[key].includes(room)) {
            hasConflict = true;
            message = '⚠️ This room is already occupied at this day and time!';
        }
    }
    
    if (hasConflict && conflictWarning && conflictMessage) {
        conflictWarning.style.display = 'block';
        conflictMessage.innerHTML = message;
        return false;
    } else if (conflictWarning) {
        conflictWarning.style.display = 'none';
        return true;
    }
    return true;
}

// Function to filter teachers based on selected day/time
function filterTeachers() {
    if (!teacherSelect || !daySelect || !timeSlotSelect) return;
    
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    
    if (!dayId || !timeSlotId) return;
    
    const key = dayId + '_' + timeSlotId;
    const busyTeacherIds = takenTeachers[key] || [];
    
    // Loop through teacher options and disable busy ones
    for (let i = 0; i < teacherSelect.options.length; i++) {
        const option = teacherSelect.options[i];
        const teacherId = parseInt(option.value);
        
        if (option.value && busyTeacherIds.includes(teacherId)) {
            option.disabled = true;
            option.style.color = '#94a3b8';
            option.style.backgroundColor = '#f1f5f9';
            if (!option.getAttribute('data-original-title')) {
                option.setAttribute('data-original-title', option.text);
                option.text = option.text + ' (Busy)';
            }
        } else if (option.value && !busyTeacherIds.includes(teacherId)) {
            option.disabled = false;
            option.style.color = '';
            option.style.backgroundColor = '';
            if (option.getAttribute('data-original-title')) {
                option.text = option.getAttribute('data-original-title');
                option.removeAttribute('data-original-title');
            }
        }
    }
    
    checkConflicts();
}

// Function to filter rooms (show warning for taken rooms)
function checkRoomConflict() {
    if (!daySelect || !timeSlotSelect || !roomInput) return true;
    
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    const room = roomInput.value.trim();
    
    if (dayId && timeSlotId && room) {
        const key = dayId + '_' + timeSlotId;
        const busyRooms = takenRooms[key] || [];
        
        if (busyRooms.includes(room)) {
            roomInput.style.borderColor = '#ef4444';
            if (conflictWarning && conflictMessage) {
                conflictWarning.style.display = 'block';
                conflictMessage.innerHTML = '⚠️ This room is already occupied at this day and time!';
            }
            return false;
        } else {
            roomInput.style.borderColor = '';
            checkConflicts();
        }
    }
    return true;
}

// Add event listeners if elements exist
if (daySelect) {
    daySelect.addEventListener('change', function() {
        filterTeachers();
        checkRoomConflict();
    });
}

if (timeSlotSelect) {
    timeSlotSelect.addEventListener('change', function() {
        filterTeachers();
        checkRoomConflict();
    });
}

if (teacherSelect) {
    teacherSelect.addEventListener('change', checkConflicts);
}

if (roomInput) {
    roomInput.addEventListener('input', checkRoomConflict);
}

if (quarterSelect) {
    quarterSelect.addEventListener('change', function() {
        const sectionId = window.sectionId || '';
        if (sectionId) {
            window.location.href = 'create_schedule.php?section_id=' + sectionId + '&quarter=' + this.value;
        }
    });
}

// Initial filter
if (daySelect && daySelect.value && timeSlotSelect && timeSlotSelect.value) {
    filterTeachers();
}

// Form submission validation
const scheduleForm = document.getElementById('scheduleForm');
if (scheduleForm) {
    scheduleForm.addEventListener('submit', function(e) {
        if (!checkConflicts() || !checkRoomConflict()) {
            e.preventDefault();
            alert('Please resolve the schedule conflicts before submitting.');
        }
    });
}

// Add hover effects to schedule items
const scheduleItems = document.querySelectorAll('.schedule-item');
scheduleItems.forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.transform = 'translateX(5px)';
        this.style.transition = 'transform 0.3s ease';
    });
    item.addEventListener('mouseleave', function() {
        this.style.transform = 'translateX(0)';
    });
});

// Copy section name functionality
const sectionName = document.querySelector('.section-details h2');
if (sectionName) {
    sectionName.style.cursor = 'pointer';
    sectionName.title = 'Click to copy section name';
    sectionName.addEventListener('click', () => {
        const text = sectionName.textContent;
        navigator.clipboard.writeText(text).then(() => {
            showToast('Section name copied!', 'success');
        });
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${message}</span>
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

// Add toast styles
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        padding: 12px 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        border-left: 4px solid;
    }
    
    .toast-notification.show {
        transform: translateX(0);
    }
    
    .toast-success {
        border-left-color: #10b981;
    }
    
    .toast-success i {
        color: #10b981;
    }
    
    .toast-info {
        border-left-color: #3b82f6;
    }
    
    .toast-info i {
        color: #3b82f6;
    }
`;
document.head.appendChild(toastStyles);