/* ========================================
   ENROLLMENTS PAGE JAVASCRIPT
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
});

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

// Make student names clickable to copy
document.addEventListener('DOMContentLoaded', function() {
    const studentNames = document.querySelectorAll('.student-details h4');
    studentNames.forEach(name => {
        name.style.cursor = 'pointer';
        name.title = 'Click to copy student name';
        name.addEventListener('click', () => {
            copyToClipboard(name.textContent, 'Student name');
        });
    });
    
    const idNumbers = document.querySelectorAll('.id-badge');
    idNumbers.forEach(id => {
        id.style.cursor = 'pointer';
        id.title = 'Click to copy ID number';
        id.addEventListener('click', () => {
            copyToClipboard(id.textContent, 'ID number');
        });
    });
});

// Add hover effects to table rows
const tableRows = document.querySelectorAll('.enrollments-table tbody tr');
tableRows.forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = 'var(--bg-light)';
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