/* ========================================
   VIEW ENROLLMENT PAGE JS
   Author: PLNHS
   Description: JavaScript for view_enrollment.php
======================================== */

// DOM Elements
const printBtn = document.querySelector('.btn-print');
const statusSelect = document.querySelector('select[name="status"]');
const updateForm = document.querySelector('.status-form');

// Print function
function printEnrollment() {
    window.print();
}

// Print button event
if (printBtn) {
    printBtn.addEventListener('click', printEnrollment);
}

// Status change confirmation
if (statusSelect && updateForm) {
    updateForm.addEventListener('submit', function(e) {
        const newStatus = statusSelect.value;
        const currentStatus = enrollmentData.status;
        
        if (newStatus === currentStatus) {
            e.preventDefault();
            alert('Please select a different status to update.');
        } else if (newStatus === 'Rejected') {
            if (!confirm('Are you sure you want to reject this enrollment? This action can be changed later.')) {
                e.preventDefault();
            }
        } else if (newStatus === 'Enrolled') {
            if (!confirm('Are you sure you want to mark this enrollment as Enrolled? This will officially enroll the student.')) {
                e.preventDefault();
            }
        }
    });
}

// Auto-refresh status badge when status changes (optional)
if (statusSelect) {
    statusSelect.addEventListener('change', function() {
        const newStatus = this.value;
        const statusBadge = document.querySelector('.status-badge');
        
        if (statusBadge) {
            // Remove existing status classes
            statusBadge.classList.remove('status-pending', 'status-enrolled', 'status-rejected');
            // Add new status class
            statusBadge.classList.add(`status-${newStatus.toLowerCase()}`);
            // Update text
            statusBadge.textContent = newStatus;
        }
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + P for print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printEnrollment();
    }
    // Ctrl + B for back
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        window.location.href = 'enrollments.php';
    }
});