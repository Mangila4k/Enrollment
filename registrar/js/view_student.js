/* ========================================
   VIEW STUDENT PAGE JAVASCRIPT
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

// Make email clickable to copy
document.addEventListener('DOMContentLoaded', function() {
    const emailItem = document.querySelector('.profile-meta-item:first-child');
    if (emailItem && studentData.email) {
        emailItem.style.cursor = 'pointer';
        emailItem.title = 'Click to copy email';
        emailItem.addEventListener('click', () => {
            copyToClipboard(studentData.email, 'Email');
        });
    }
    
    // Make ID number clickable to copy
    const idItem = document.querySelectorAll('.profile-meta-item')[1];
    if (idItem && studentData.id) {
        idItem.style.cursor = 'pointer';
        idItem.title = 'Click to copy ID';
        idItem.addEventListener('click', () => {
            copyToClipboard(studentData.id.toString(), 'Student ID');
        });
    }
    
    // Make name clickable to copy
    const nameElement = document.querySelector('.profile-info h2');
    if (nameElement) {
        nameElement.style.cursor = 'pointer';
        nameElement.title = 'Click to copy name';
        nameElement.addEventListener('click', () => {
            copyToClipboard(nameElement.textContent, 'Student name');
        });
    }
});

// Add hover effects to stat cards
const statCards = document.querySelectorAll('.stat-mini-card');
statCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Add hover effects to profile avatar
const profileAvatar = document.querySelector('.profile-avatar-large');
if (profileAvatar) {
    profileAvatar.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.02)';
    });
    profileAvatar.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
}

// Print functionality
const printBtn = document.createElement('button');
printBtn.className = 'btn-print';
printBtn.innerHTML = '<i class="fas fa-print"></i> Print Profile';
printBtn.style.cssText = `
    background: var(--bg-white);
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    margin-left: 10px;
    transition: all 0.3s;
    margin-top: 15px;
`;
printBtn.onclick = () => {
    window.print();
};

// Add print button to page header
const pageHeader = document.querySelector('.page-header');
if (pageHeader && !document.querySelector('.btn-print')) {
    const headerDiv = pageHeader.querySelector('div:first-child');
    if (headerDiv) {
        headerDiv.appendChild(printBtn);
    }
}

// Print styles
const printStyles = document.createElement('style');
printStyles.textContent = `
    @media print {
        .sidebar, .menu-toggle, .back-btn, .action-buttons, .btn-print, .view-link {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 20px !important;
            margin-top: 20px;
        }
        
        .profile-card, .detail-card {
            break-inside: avoid;
            box-shadow: none;
            border: 1px solid #ddd;
        }
        
        body {
            background: white;
        }
    }
`;
document.head.appendChild(printStyles);