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

// Confirm delete function
function confirmDelete() {
    const studentName = studentData.name || 'this student';
    return confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone and will delete all associated records.`);
}

// Add hover effect to profile card
const profileCard = document.querySelector('.profile-card');
if (profileCard) {
    profileCard.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    profileCard.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
}

// Add hover effect to detail cards
const detailCards = document.querySelectorAll('.detail-card');
detailCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Table row click effect
const tableRows = document.querySelectorAll('.enrollments-table tbody tr');
tableRows.forEach(row => {
    row.addEventListener('click', function(e) {
        // Don't trigger if clicking on the view link
        if (e.target.closest('.view-link')) return;
        
        const viewLink = this.querySelector('.view-link');
        if (viewLink) {
            window.location.href = viewLink.getAttribute('href');
        }
    });
    
    row.addEventListener('mouseenter', function() {
        this.style.cursor = 'pointer';
    });
});

// Copy email to clipboard functionality
const emailElement = document.querySelector('.profile-meta-item .fa-envelope');
if (emailElement) {
    const emailText = emailElement.parentElement.textContent.trim();
    emailElement.parentElement.style.cursor = 'pointer';
    emailElement.parentElement.title = 'Click to copy email';
    
    emailElement.parentElement.addEventListener('click', function() {
        const email = this.textContent.replace('📧', '').trim();
        navigator.clipboard.writeText(email).then(() => {
            showToast('Email copied to clipboard!', 'success');
        });
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    // Remove existing toast
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