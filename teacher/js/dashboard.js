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
});

// Add hover effect for action cards
const actionCards = document.querySelectorAll('.action-card');
actionCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
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
});

// Refresh statistics periodically (every 30 seconds)
setInterval(function() {
    fetch('get_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.totalStudents) {
                document.querySelector('.stat-number:first-child')?.textContent = data.totalStudents;
            }
            if (data.attendanceToday !== undefined) {
                const attendanceElement = document.querySelector('.stats-grid .stat-card:last-child .stat-number');
                if (attendanceElement) {
                    attendanceElement.textContent = data.attendanceToday;
                }
            }
        })
        .catch(error => console.log('Error refreshing stats:', error));
}, 30000);