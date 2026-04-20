/* ========================================
   TEACHER DASHBOARD JS
   Author: PLNHS
   Description: JavaScript for teacher dashboard
======================================== */

// DOM Elements
const gradeFilter = document.getElementById('grade_id');
const filterForm = document.querySelector('.filter-form');

// Auto-submit filter when grade changes (optional)
if (gradeFilter) {
    gradeFilter.addEventListener('change', function() {
        if (filterForm) {
            filterForm.submit();
        }
    });
}

// Add hover effect for stat cards
const statCards = document.querySelectorAll('.stat-card');
statCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = '';
    });
});

// Add hover effect for action cards
const actionCards = document.querySelectorAll('.action-card');
actionCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = '';
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + Q for QR Attendance
    if (e.ctrlKey && e.key === 'q') {
        e.preventDefault();
        window.location.href = 'attendance_qr.php';
    }
    // Ctrl + C for Classes
    if (e.ctrlKey && e.key === 'c') {
        e.preventDefault();
        window.location.href = 'classes.php';
    }
    // Ctrl + S for Schedule
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        window.location.href = 'schedule.php';
    }
    // Ctrl + G for Grades
    if (e.ctrlKey && e.key === 'g') {
        e.preventDefault();
        window.location.href = 'grades.php';
    }
    // Ctrl + P for Profile
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.location.href = 'profile.php';
    }
});

// Toggle grade section function
function toggleGrade(gradeId) {
    const content = document.getElementById('content_' + gradeId);
    const icon = document.getElementById('icon_' + gradeId);
    
    if (content) {
        if (content.classList.contains('active')) {
            content.classList.remove('active');
            if (icon) icon.classList.remove('rotated');
        } else {
            content.classList.add('active');
            if (icon) icon.classList.add('rotated');
        }
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        }
    });
}, 5000);

// Refresh statistics periodically (every 30 seconds) - only if stats elements exist
setInterval(function() {
    // Check if stats elements exist before trying to update
    const statNumbers = document.querySelectorAll('.stat-number');
    if (statNumbers.length > 0) {
        fetch('get_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.totalStudents && statNumbers[0]) {
                    statNumbers[0].textContent = data.totalStudents;
                }
                if (data.totalSections && statNumbers[1]) {
                    statNumbers[1].textContent = data.totalSections;
                }
                if (data.totalSubjects && statNumbers[2]) {
                    statNumbers[2].textContent = data.totalSubjects;
                }
                if (data.attendanceToday !== undefined && statNumbers[3]) {
                    statNumbers[3].textContent = data.attendanceToday;
                }
            })
            .catch(error => console.log('Error refreshing stats:', error));
    }
}, 30000);

// Initialize any tooltips or additional UI elements
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
    console.log('Teacher Dashboard loaded');
    
    // Set current date in date badge if exists
    const dateBadge = document.querySelector('.date-badge');
    if (dateBadge) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const currentDate = new Date().toLocaleDateString('en-US', options);
        dateBadge.innerHTML = `<i class="fas fa-calendar-alt"></i> ${currentDate}`;
    }
});