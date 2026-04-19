/* ========================================
   VIEW ENROLLMENT PAGE JAVASCRIPT
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

// Status update confirmation
const statusForm = document.getElementById('statusForm');
if (statusForm) {
    statusForm.addEventListener('submit', function(e) {
        const newStatus = this.querySelector('select[name="status"]').value;
        const currentStatus = enrollmentData.status;
        
        if (newStatus === currentStatus) {
            e.preventDefault();
            alert('Status is already set to ' + currentStatus);
        } else {
            const confirmMsg = confirm(`Are you sure you want to change status from ${currentStatus} to ${newStatus}?`);
            if (!confirmMsg) {
                e.preventDefault();
            }
        }
    });
}

// Section assignment confirmation
const assignButtons = document.querySelectorAll('.btn-assign');
assignButtons.forEach(btn => {
    btn.addEventListener('click', function(e) {
        const sectionName = this.closest('.section-card')?.querySelector('.section-info span')?.textContent || 'this section';
        if (!confirm(`Assign student to ${sectionName}?`)) {
            e.preventDefault();
        }
    });
});

// Print functionality
window.print = function() {
    const originalTitle = document.title;
    document.title = `Enrollment_${enrollmentData.id}_${enrollmentData.studentName}`;
    window.print();
    document.title = originalTitle;
};

// Copy student name functionality
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
        const email = emailSpan.textContent.replace('📧', '').trim();
        copyToClipboard(email, 'Email');
    });
}

// Copy ID number functionality
const idSpan = document.querySelector('.student-meta span:last-child');
if (idSpan && idSpan.textContent.includes('ID:')) {
    idSpan.style.cursor = 'pointer';
    idSpan.title = 'Click to copy ID number';
    idSpan.addEventListener('click', () => {
        const id = idSpan.textContent.replace('🆔', '').replace('ID:', '').trim();
        copyToClipboard(id, 'ID number');
    });
}

// Toast notification function
function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

function showToast(message, type = 'info') {
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