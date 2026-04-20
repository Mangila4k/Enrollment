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
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Auto-hide alerts
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
    
    // ===== DAY TAB SWITCHING FUNCTIONALITY =====
    initDayTabs();
});

// Function to initialize day tabs
function initDayTabs() {
    const dayTabs = document.querySelectorAll('.day-tab');
    const daySchedules = document.querySelectorAll('.day-schedule');
    
    if (dayTabs.length === 0) {
        return;
    }
    
    // Remove any existing active classes
    dayTabs.forEach(tab => {
        tab.classList.remove('active');
    });
    daySchedules.forEach(schedule => {
        schedule.classList.remove('active');
    });
    
    // Set first tab as active by default
    if (dayTabs.length > 0) {
        dayTabs[0].classList.add('active');
    }
    if (daySchedules.length > 0) {
        daySchedules[0].classList.add('active');
    }
    
    // Add click event listeners to each tab
    dayTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            const day = this.getAttribute('data-day');
            
            // Remove active class from all tabs
            dayTabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all day schedules
            daySchedules.forEach(schedule => {
                schedule.classList.remove('active');
            });
            
            // Show the selected day schedule
            const selectedSchedule = document.getElementById(`${day}-schedule`);
            if (selectedSchedule) {
                selectedSchedule.classList.add('active');
            }
        });
    });
}

// Data from PHP
const takenTeachers = window.takenTeachersData || {};
const takenRooms = window.takenRoomsData || {};
const takenSubjectsByDay = window.takenSubjectsByDay || {};
const sectionId = window.sectionId || 0;
const currentSchoolYear = window.currentSchoolYear || '';

// DOM elements
const subjectSelect = document.getElementById('subject_id');
const teacherSelect = document.getElementById('teacher_id');
const daySelect = document.getElementById('day_id');
const timeSlotSelect = document.getElementById('time_slot_id');
const roomInput = document.getElementById('room');
const quarterSelect = document.getElementById('quarter');
const conflictWarning = document.getElementById('conflictWarning');
const conflictMessage = document.getElementById('conflictMessage');

// Function to filter subjects based on selected day
function filterSubjects() {
    const dayId = daySelect.value;
    
    if (!dayId) return;
    
    const subjectsOnThisDay = takenSubjectsByDay[dayId] || [];
    
    for (let i = 0; i < subjectSelect.options.length; i++) {
        const option = subjectSelect.options[i];
        const subjectId = parseInt(option.value);
        
        if (option.value && subjectsOnThisDay.includes(subjectId)) {
            option.disabled = true;
            option.style.color = '#94a3b8';
            option.style.backgroundColor = '#f1f5f9';
            if (!option.textContent.includes('(Already on this day)')) {
                option.textContent = option.textContent + ' (Already on this day)';
            }
        } else if (option.value) {
            option.disabled = false;
            option.style.color = '';
            option.style.backgroundColor = '';
            option.textContent = option.textContent.replace(' (Already on this day)', '');
        }
    }
}

// Function to filter teachers based on selected day/time
function filterTeachers() {
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    
    if (!dayId || !timeSlotId) return;
    
    const key = dayId + '_' + timeSlotId;
    const busyTeacherIds = takenTeachers[key] || [];
    
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
}

// Function to check teacher conflict
function checkTeacherConflict() {
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    const teacherId = teacherSelect.value;
    
    if (dayId && timeSlotId && teacherId) {
        const key = dayId + '_' + timeSlotId;
        const busyTeachers = takenTeachers[key] || [];
        
        if (busyTeachers.includes(parseInt(teacherId))) {
            conflictWarning.style.display = 'block';
            conflictMessage.innerHTML = '⚠️ This teacher is already assigned to another class at this day and time!';
            return false;
        }
    }
    return true;
}

// Function to check room conflict
function checkRoomConflict() {
    const dayId = daySelect.value;
    const timeSlotId = timeSlotSelect.value;
    const room = roomInput ? roomInput.value.trim() : '';
    
    if (dayId && timeSlotId && room) {
        const key = dayId + '_' + timeSlotId;
        const busyRooms = takenRooms[key] || [];
        
        if (busyRooms.includes(room)) {
            roomInput.style.borderColor = '#ef4444';
            conflictWarning.style.display = 'block';
            conflictMessage.innerHTML = '⚠️ This room is already occupied at this day and time!';
            return false;
        }
    }
    roomInput.style.borderColor = '';
    return true;
}

// Function to check all conflicts
function checkConflicts() {
    const teacherOk = checkTeacherConflict();
    const roomOk = checkRoomConflict();
    
    if (teacherOk && roomOk) {
        conflictWarning.style.display = 'none';
    }
    return teacherOk && roomOk;
}

// Event listeners
if (daySelect) {
    daySelect.addEventListener('change', function() {
        filterSubjects();
        filterTeachers();
        checkRoomConflict();
        checkConflicts();
    });
}

if (timeSlotSelect) {
    timeSlotSelect.addEventListener('change', function() {
        filterTeachers();
        checkRoomConflict();
        checkConflicts();
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
        filterSubjects();
        filterTeachers();
    });
}

// Initialize filters
if (daySelect && daySelect.value) {
    filterSubjects();
    filterTeachers();
}

// Form submission validation
const scheduleForm = document.getElementById('scheduleForm');
if (scheduleForm) {
    scheduleForm.addEventListener('submit', function(e) {
        if (!checkConflicts()) {
            e.preventDefault();
            alert('Please resolve the schedule conflicts before submitting.\n\nNote: Same subject can be scheduled on different days, but teachers and rooms cannot be double-booked.');
        }
    });
}

// Log loaded
console.log('Schedule Management JS Loaded');
console.log('Rule: Same subject can be scheduled on different days');
console.log('Section ID:', sectionId);