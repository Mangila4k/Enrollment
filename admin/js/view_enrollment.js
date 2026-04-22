/* ========================================
   VIEW ENROLLMENT PAGE JAVASCRIPT
   Author: PLNHS
   Description: JavaScript for view_enrollment.php
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
    
    // Add print button if not exists
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader && !document.querySelector('.print-btn')) {
        const printBtn = document.createElement('button');
        printBtn.className = 'print-btn';
        printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
        printBtn.onclick = () => printEnrollment();
        printBtn.style.cssText = `
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
            margin-left: 10px;
        `;
        printBtn.addEventListener('mouseenter', () => {
            printBtn.style.background = '#5a6268';
            printBtn.style.transform = 'translateY(-2px)';
        });
        printBtn.addEventListener('mouseleave', () => {
            printBtn.style.background = '#6c757d';
            printBtn.style.transform = 'translateY(0)';
        });
        
        const headerActions = document.querySelector('.page-header div:first-child');
        if (headerActions) {
            headerActions.appendChild(printBtn);
        }
    }
});

// Notify requirement function
function notifyRequirement(requirementId, requirementName) {
    if (!enrollmentData) {
        console.error('enrollmentData is not defined');
        showToast('Error: Enrollment data not found', 'error');
        return;
    }
    
    const studentName = enrollmentData.studentName;
    const studentEmail = enrollmentData.studentEmail;
    const studentId = enrollmentData.studentId;
    
    // Find the button that was clicked
    const buttons = document.querySelectorAll('.btn-notify');
    let clickedButton = null;
    for (let btn of buttons) {
        if (btn.textContent.includes(requirementName) || btn.parentElement?.previousElementSibling?.querySelector('.requirement-name')?.textContent === requirementName) {
            clickedButton = btn;
            break;
        }
    }
    
    // Show confirmation dialog
    if (confirm(`Send notification to ${studentName} about missing requirement: ${requirementName}?`)) {
        // Store original button content
        const originalText = clickedButton ? clickedButton.innerHTML : '<i class="fas fa-bell"></i> Notify';
        
        // Disable button and show loading state
        if (clickedButton) {
            clickedButton.disabled = true;
            clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            clickedButton.style.opacity = '0.7';
        }
        
        // Show loading toast
        showToast('Sending notification to student...', 'info');
        
        // Send AJAX request to send notification
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_requirement_notification.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            // Restore button
            if (clickedButton) {
                clickedButton.disabled = false;
                clickedButton.innerHTML = originalText;
                clickedButton.style.opacity = '1';
            }
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast(response.message || `Notification sent to ${studentName} about ${requirementName}`, 'success');
                        // Visual feedback that notification was sent
                        if (clickedButton) {
                            clickedButton.innerHTML = '<i class="fas fa-check"></i> Sent!';
                            clickedButton.style.background = '#10b981';
                            setTimeout(() => {
                                clickedButton.innerHTML = originalText;
                                clickedButton.style.background = '#f59e0b';
                            }, 3000);
                        }
                    } else {
                        showToast(response.message || 'Failed to send notification. Please try again.', 'error');
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    showToast('Notification sent successfully!', 'success');
                }
            } else {
                showToast('Server error. Please try again.', 'error');
            }
        };
        
        xhr.onerror = function() {
            // Restore button on error
            if (clickedButton) {
                clickedButton.disabled = false;
                clickedButton.innerHTML = originalText;
                clickedButton.style.opacity = '1';
            }
            showToast('Network error. Please check your connection.', 'error');
        };
        
        // Send the request with timeout
        xhr.timeout = 30000;
        xhr.ontimeout = function() {
            if (clickedButton) {
                clickedButton.disabled = false;
                clickedButton.innerHTML = originalText;
                clickedButton.style.opacity = '1';
            }
            showToast('Request timeout. Please try again.', 'error');
        };
        
        xhr.send(`student_id=${studentId}&student_email=${encodeURIComponent(studentEmail)}&student_name=${encodeURIComponent(studentName)}&requirement=${encodeURIComponent(requirementName)}&requirement_id=${requirementId}`);
    }
}

// Print enrollment function
function printEnrollment() {
    const originalTitle = document.title;
    const studentName = enrollmentData ? enrollmentData.studentName : 'Enrollment';
    document.title = `Enrollment_${studentName.replace(/\s/g, '_')}`;
    window.print();
    document.title = originalTitle;
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
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

// Copy to clipboard function
function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

// Add copy functionality to elements
document.addEventListener('DOMContentLoaded', function() {
    // Copy student name
    const studentName = document.querySelector('.student-details h3');
    if (studentName) {
        studentName.style.cursor = 'pointer';
        studentName.title = 'Click to copy student name';
        studentName.addEventListener('click', () => {
            copyToClipboard(studentName.textContent, 'Student name');
        });
    }
    
    // Copy email functionality
    const emailSpan = document.querySelector('.student-meta span:first-child');
    if (emailSpan && emailSpan.textContent.includes('@')) {
        emailSpan.style.cursor = 'pointer';
        emailSpan.title = 'Click to copy email';
        emailSpan.addEventListener('click', () => {
            let email = emailSpan.textContent;
            // Clean up the text if it contains icons
            email = email.replace(/[^\w\s@.-]/g, '').trim();
            copyToClipboard(email, 'Email');
        });
    }
    
    // Copy ID number functionality
    const idSpan = document.querySelector('.student-meta span:last-child');
    if (idSpan && idSpan.textContent.includes('ID:')) {
        idSpan.style.cursor = 'pointer';
        idSpan.title = 'Click to copy ID number';
        idSpan.addEventListener('click', () => {
            let id = idSpan.textContent;
            id = id.replace('ID:', '').replace('ID', '').replace(':', '').trim();
            copyToClipboard(id, 'ID number');
        });
    }
});

// Add hover effects to requirement items
document.addEventListener('DOMContentLoaded', function() {
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
    const docLinks = document.querySelectorAll('.btn-view-file');
    docLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

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

// Add toast styles dynamically
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
    
    .print-btn {
        background: #6c757d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        transition: all 0.3s;
        margin-left: 10px;
    }
    
    .print-btn:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    .btn-notify:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    @media print {
        .sidebar, 
        .back-btn, 
        .print-btn, 
        .menu-toggle, 
        .page-header .back-btn, 
        .page-header .print-btn, 
        .btn-notify,
        .requirement-actions,
        .view-link {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .detail-card {
            break-inside: avoid;
            page-break-inside: avoid;
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .student-avatar-large-img,
        .student-avatar-large {
            border: 1px solid #ddd;
        }
        
        .progress-bar {
            border: 1px solid #ddd;
            background: #f0f0f0;
        }
        
        .progress-fill {
            background: #0B4F2E !important;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        
        .status-badge-submitted,
        .status-badge-missing,
        .requirement-badge {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
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

// Log that JS is loaded (for debugging)
console.log('view_enrollment.js loaded successfully');
console.log('enrollmentData:', typeof enrollmentData !== 'undefined' ? enrollmentData : 'Not defined');