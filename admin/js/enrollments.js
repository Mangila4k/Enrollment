/* ========================================
   ENROLLMENTS PAGE JAVASCRIPT
   Author: PLNHS
   Description: Complete JavaScript for admin enrollments page
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
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        });
    }, 5000);
    
    // Initialize notify buttons
    initNotifyButtons();
    
    // Auto-submit on filter change
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Search on Enter
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
    
    // Make student names clickable to copy
    const studentNames = document.querySelectorAll('.student-details h4');
    studentNames.forEach(name => {
        name.style.cursor = 'pointer';
        name.title = 'Click to copy student name';
        name.addEventListener('click', () => {
            copyToClipboard(name.textContent, 'Student name');
        });
    });
    
    // Make ID numbers clickable to copy
    const idNumbers = document.querySelectorAll('.id-badge');
    idNumbers.forEach(id => {
        id.style.cursor = 'pointer';
        id.title = 'Click to copy ID number';
        id.addEventListener('click', () => {
            copyToClipboard(id.textContent, 'ID number');
        });
    });
    
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.enrollments-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Add animation to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Initialize notify buttons with event listeners
function initNotifyButtons() {
    const notifyButtons = document.querySelectorAll('.btn-notify-missing');
    notifyButtons.forEach(button => {
        // Remove existing event listeners by cloning
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const studentId = this.getAttribute('data-student-id');
            const studentName = this.getAttribute('data-student-name');
            const studentEmail = this.getAttribute('data-student-email');
            let missingList = this.getAttribute('data-missing-list');
            const enrollmentId = this.getAttribute('data-enrollment-id');
            
            // Parse missing list if it's a JSON string
            try {
                if (missingList) {
                    missingList = JSON.parse(missingList);
                }
            } catch(e) {
                console.error('Error parsing missing list:', e);
                missingList = [];
            }
            
            notifyMissingRequirements(studentId, studentName, studentEmail, missingList, enrollmentId, this);
        });
    });
}

// Open reject modal
function openRejectModal(enrollmentId, studentName) {
    document.getElementById('reject_enrollment_id').value = enrollmentId;
    document.getElementById('reject_student_name').value = studentName;
    document.getElementById('rejection_reason').value = '';
    document.getElementById('rejectModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close reject modal
function closeRejectModal() { 
    document.getElementById('rejectModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) { 
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        closeRejectModal();
    }
}

// Confirm approve function
function confirmApprove(studentName) {
    return confirm(`Approve enrollment for ${studentName}? This will generate a student ID number.`);
}

// Confirm reject function
function confirmReject(studentName) {
    return confirm(`Reject enrollment for ${studentName}?`);
}

// Confirm delete function
function confirmDelete(studentName) {
    return confirm(`Delete enrollment record for ${studentName}? This action cannot be undone.`);
}

// Copy to clipboard functionality
function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    // Create new toast
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
        <span>${escapeHtml(message)}</span>
    `;
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // Animate out and remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Notify missing requirements function (sends to current page via AJAX)
function notifyMissingRequirements(studentId, studentName, studentEmail, missingList, enrollmentId, buttonElement) {
    if (!missingList || missingList.length === 0) {
        showToast('No missing requirements to notify about.', 'info');
        return;
    }
    
    const requirementsText = missingList.join(', ');
    
    if (confirm(`Send notification to ${studentName} about missing requirements?\n\nMissing: ${requirementsText}`)) {
        // Store original button content
        const originalText = buttonElement ? buttonElement.innerHTML : '<i class="fas fa-bell"></i> Notify';
        const originalBackground = buttonElement ? buttonElement.style.background : '';
        
        // Disable button and show loading state
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            buttonElement.style.opacity = '0.7';
        }
        
        showToast('Sending notification to student...', 'info');
        
        // Send AJAX request to the same page
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            // Restore button state
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast(response.message || `Notification sent to ${studentName}`, 'success');
                        if (buttonElement) {
                            buttonElement.innerHTML = '<i class="fas fa-check"></i> Sent!';
                            buttonElement.style.background = '#10b981';
                            setTimeout(() => {
                                buttonElement.innerHTML = originalText;
                                buttonElement.style.background = originalBackground || '#f59e0b';
                            }, 3000);
                        }
                    } else {
                        showToast(response.message || 'Failed to send notification.', 'error');
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
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            showToast('Network error. Please check your connection.', 'error');
        };
        
        xhr.timeout = 30000;
        xhr.ontimeout = function() {
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            showToast('Request timeout. Please try again.', 'error');
        };
        
        // Send data to the AJAX handler
        const params = new URLSearchParams();
        params.append('action', 'notify_missing');
        params.append('student_id', studentId);
        params.append('student_name', studentName);
        params.append('student_email', studentEmail);
        params.append('missing_requirements', requirementsText);
        params.append('enrollment_id', enrollmentId);
        
        xhr.send(params.toString());
    }
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
    
    @media (max-width: 480px) {
        .toast-notification {
            bottom: 10px;
            right: 10px;
            left: 10px;
            max-width: calc(100% - 20px);
        }
    }
`;

// Add modal styles if not already present
const modalStyles = document.createElement('style');
modalStyles.textContent = `
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .modal.active { display: flex; }
    .modal-content {
        background: white;
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h3 {
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        color: #333;
    }
    .modal-header h3 i { color: #dc3545; }
    .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #999;
        transition: all 0.3s;
    }
    .close-modal:hover { color: #dc3545; transform: rotate(90deg); }
    .modal-body { padding: 25px; }
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    .form-group { margin-bottom: 20px; }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    .form-group .required { color: #dc3545; }
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s;
    }
    .form-control:focus {
        outline: none;
        border-color: #0B4F2E;
        box-shadow: 0 0 0 3px rgba(11,79,46,0.1);
    }
    textarea.form-control { resize: vertical; min-height: 100px; }
    .form-text {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
        display: block;
    }
    .btn-cancel {
        background: #f5f5f5;
        color: #666;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-cancel:hover { background: #e0e0e0; }
    .btn-reject {
        background: #dc3545;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-reject:hover { background: #c82333; transform: translateY(-2px); }
    .action-btn.reject { cursor: pointer; }
    .action-btn.approve.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #ccc;
    }
    .requirements-warning {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .missing-badge {
        background: #fee2e2;
        color: #dc2626;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        display: inline-block;
    }
    .complete-badge {
        background: #d4edda;
        color: #155724;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        display: inline-block;
    }
    .btn-notify-missing {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-notify-missing:hover { background: #d97706; }
    .btn-notify-missing:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
`;

// Append styles if they don't exist
if (!document.querySelector('#toastStyles')) {
    toastStyles.id = 'toastStyles';
    document.head.appendChild(toastStyles);
}

if (!document.querySelector('#modalStyles')) {
    modalStyles.id = 'modalStyles';
    document.head.appendChild(modalStyles);
}

// Export functions for global use
window.openRejectModal = openRejectModal;
window.closeRejectModal = closeRejectModal;
window.confirmApprove = confirmApprove;
window.confirmReject = confirmReject;
window.confirmDelete = confirmDelete;
window.copyToClipboard = copyToClipboard;
window.showToast = showToast;
window.notifyMissingRequirements = notifyMissingRequirements;