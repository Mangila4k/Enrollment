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
});

// Notify requirement function with improved feedback
function notifyRequirement(requirementKey, requirementName) {
    const studentName = enrollmentData.studentName;
    const studentEmail = enrollmentData.studentEmail;
    const studentId = enrollmentData.studentId;
    
    // Get the button that was clicked
    const notifyBtn = event ? event.target.closest('.btn-notify') : document.querySelector(`.btn-notify[data-key="${requirementKey}"]`);
    
    // Show confirmation dialog
    if (confirm(`Send notification to ${studentName} about missing requirement: ${requirementName}?`)) {
        // Store original button content
        const originalText = notifyBtn ? notifyBtn.innerHTML : '<i class="fas fa-bell"></i> Notify';
        
        // Disable button and show loading state
        if (notifyBtn) {
            notifyBtn.disabled = true;
            notifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            notifyBtn.style.opacity = '0.7';
        }
        
        // Show loading toast
        showToast('Sending notification to student...', 'info');
        
        // Send AJAX request to send notification
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_requirement_notification.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            // Restore button
            if (notifyBtn) {
                notifyBtn.disabled = false;
                notifyBtn.innerHTML = originalText;
                notifyBtn.style.opacity = '1';
            }
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast(response.message || `Notification sent to ${studentName} about ${requirementName}`, 'success');
                        // Visual feedback that notification was sent
                        if (notifyBtn) {
                            notifyBtn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                            notifyBtn.style.background = '#10b981';
                            setTimeout(() => {
                                notifyBtn.innerHTML = originalText;
                                notifyBtn.style.background = '#f59e0b';
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
            if (notifyBtn) {
                notifyBtn.disabled = false;
                notifyBtn.innerHTML = originalText;
                notifyBtn.style.opacity = '1';
            }
            showToast('Network error. Please check your connection.', 'error');
        };
        
        // Send the request with timeout
        xhr.timeout = 30000;
        xhr.ontimeout = function() {
            if (notifyBtn) {
                notifyBtn.disabled = false;
                notifyBtn.innerHTML = originalText;
                notifyBtn.style.opacity = '1';
            }
            showToast('Request timeout. Please try again.', 'error');
        };
        
        xhr.send(`student_id=${studentId}&student_email=${encodeURIComponent(studentEmail)}&student_name=${encodeURIComponent(studentName)}&requirement=${encodeURIComponent(requirementName)}&requirement_key=${requirementKey}`);
    }
}

// Toast notification function
function showToast(message, type = 'info') {
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

// Copy functionality
function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

// Add copy functionality to student name
document.addEventListener('DOMContentLoaded', function() {
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
            id = id.replace('ID:', '').replace('🆔', '').trim();
            copyToClipboard(id, 'ID number');
        });
    }
    
    // Add data-key attribute to notify buttons for better tracking
    document.querySelectorAll('.btn-notify').forEach((btn, index) => {
        const requirementItem = btn.closest('.requirement-item');
        if (requirementItem && requirementItem.dataset.key) {
            btn.setAttribute('data-key', requirementItem.dataset.key);
        }
        // Add click handler directly to ensure it works
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const key = this.getAttribute('data-key') || 'unknown';
            const requirementName = this.closest('.requirement-item')?.querySelector('.requirement-name')?.textContent || 'requirement';
            notifyRequirement(key, requirementName);
        });
    });
});

// Print enrollment details
window.printEnrollment = function() {
    const originalTitle = document.title;
    document.title = `Enrollment_${enrollmentData.id}_${enrollmentData.studentName}`;
    window.print();
    document.title = originalTitle;
};

// Add print button to page header if needed
document.addEventListener('DOMContentLoaded', function() {
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader && !document.querySelector('.print-btn')) {
        const printBtn = document.createElement('button');
        printBtn.className = 'print-btn';
        printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
        printBtn.onclick = () => window.printEnrollment();
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
        `;
        printBtn.addEventListener('mouseenter', () => {
            printBtn.style.background = '#5a6268';
            printBtn.style.transform = 'translateY(-2px)';
        });
        printBtn.addEventListener('mouseleave', () => {
            printBtn.style.background = '#6c757d';
            printBtn.style.transform = 'translateY(0)';
        });
        pageHeader.appendChild(printBtn);
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
        .sidebar, .back-btn, .print-btn, .menu-toggle, .page-header .back-btn, .page-header .print-btn, .btn-notify {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .detail-card {
            break-inside: avoid;
            page-break-inside: avoid;
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

// Add event listener for dynamically added notify buttons
document.addEventListener('DOMContentLoaded', function() {
    // Observe for dynamically added notify buttons
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    const notifyBtns = node.querySelectorAll ? node.querySelectorAll('.btn-notify') : [];
                    notifyBtns.forEach(btn => {
                        if (!btn.hasAttribute('data-listener')) {
                            btn.setAttribute('data-listener', 'true');
                            const requirementItem = btn.closest('.requirement-item');
                            if (requirementItem && requirementItem.dataset.key) {
                                btn.setAttribute('data-key', requirementItem.dataset.key);
                            }
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                const key = this.getAttribute('data-key') || 'unknown';
                                const requirementName = this.closest('.requirement-item')?.querySelector('.requirement-name')?.textContent || 'requirement';
                                notifyRequirement(key, requirementName);
                            });
                        }
                    });
                }
            });
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
});

// Export functions for debugging (optional)
window.notifyRequirement = notifyRequirement;
window.showToast = showToast;