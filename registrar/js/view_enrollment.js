/* ========================================
   VIEW ENROLLMENT PAGE JS
   Author: PLNHS
   Description: JavaScript for view_enrollment.php
======================================== */

// DOM Elements
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
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
    
    // Print function
    const printBtn = document.querySelector('.btn-print');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Status change - show/hide rejection reason
    const statusSelect = document.getElementById('statusSelect');
    const rejectionReasonGroup = document.getElementById('rejectionReasonGroup');
    const rejectionReason = document.getElementById('rejectionReason');
    
    if (statusSelect && rejectionReasonGroup) {
        function toggleRejectionReason() {
            if (statusSelect.value === 'Rejected') {
                rejectionReasonGroup.style.display = 'block';
                if (rejectionReason) rejectionReason.required = true;
            } else {
                rejectionReasonGroup.style.display = 'none';
                if (rejectionReason) {
                    rejectionReason.required = false;
                    rejectionReason.value = '';
                }
            }
        }
        
        statusSelect.addEventListener('change', toggleRejectionReason);
        toggleRejectionReason();
    }
    
    // Status change confirmation
    const updateForm = document.querySelector('.status-form');
    
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            if (statusSelect) {
                const newStatus = statusSelect.value;
                const currentStatus = document.querySelector('.status-badge')?.textContent.trim() || '';
                
                if (newStatus === currentStatus) {
                    e.preventDefault();
                    showToast('Please select a different status to update.', 'warning');
                } else if (newStatus === 'Rejected') {
                    const reason = rejectionReason ? rejectionReason.value.trim() : '';
                    if (!reason) {
                        e.preventDefault();
                        showToast('Please provide a reason for rejection.', 'error');
                    } else if (!confirm('Are you sure you want to reject this enrollment? The student will receive a notification with the rejection reason.')) {
                        e.preventDefault();
                    }
                } else if (newStatus === 'Enrolled') {
                    if (!confirm('Are you sure you want to mark this enrollment as Enrolled? This will officially enroll the student.')) {
                        e.preventDefault();
                    }
                } else if (newStatus === 'Pending') {
                    if (!confirm('Are you sure you want to change the status to Pending?')) {
                        e.preventDefault();
                    }
                }
            }
        });
    }
    
    // Notify student button
    const notifyBtn = document.querySelector('.btn-notify');
    if (notifyBtn) {
        notifyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const enrollmentId = document.body.getAttribute('data-enrollment-id');
            const studentName = document.body.getAttribute('data-student-name');
            
            if (confirm(`Send notification to ${studentName} about their enrollment?`)) {
                // You can implement AJAX notification here
                showToast(`Notification sent to ${studentName}`, 'success');
            }
        });
    }
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : (type === 'error' ? 'exclamation-circle' : 'info-circle'))}"></i>
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
    
    // Add toast styles dynamically
    if (!document.querySelector('#toastStyles')) {
        const toastStyles = document.createElement('style');
        toastStyles.id = 'toastStyles';
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
                font-size: 14px;
                font-weight: 500;
                max-width: 350px;
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
            
            .toast-warning {
                border-left-color: #f59e0b;
            }
            
            .toast-warning i {
                color: #f59e0b;
            }
            
            .toast-error {
                border-left-color: #ef4444;
            }
            
            .toast-error i {
                color: #ef4444;
            }
            
            .toast-info {
                border-left-color: #3b82f6;
            }
            
            .toast-info i {
                color: #3b82f6;
            }
            
            @media (max-width: 480px) {
                .toast-notification {
                    bottom: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: calc(100% - 20px);
                }
            }
        `;
        document.head.appendChild(toastStyles);
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + P for print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        // Ctrl + B for back
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            window.location.href = 'enrollments.php';
        }
    });
    
    // Add hover effects to requirement items
    const requirementItems = document.querySelectorAll('.requirement-item');
    requirementItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add hover effects to document links
    const docLinks = document.querySelectorAll('.document-link, .view-doc-link');
    docLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Copy student info to clipboard
    const studentName = document.querySelector('.student-details h2');
    if (studentName) {
        studentName.style.cursor = 'pointer';
        studentName.title = 'Click to copy student name';
        studentName.addEventListener('click', () => {
            copyToClipboard(studentName.textContent, 'Student name');
        });
    }
    
    function copyToClipboard(text, fieldName) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(`${fieldName} copied to clipboard!`, 'success');
        }).catch(() => {
            showToast('Failed to copy', 'error');
        });
    }
});