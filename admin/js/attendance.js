/* ========================================
   ATTENDANCE PAGE JAVASCRIPT
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

// Add Teacher Modal Functions
function openAddTeacherModal() {
    const modal = document.getElementById('addTeacherModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeAddTeacherModal() {
    const modal = document.getElementById('addTeacherModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Edit Modal Functions (if needed)
function openEditModal(id, teacherId, date, timeIn, timeOut, status, remarks) {
    const modal = document.getElementById('editModal');
    if (modal) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_teacher_id').value = teacherId;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_time_in').value = timeIn;
        document.getElementById('edit_time_out').value = timeOut;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_remarks').value = remarks;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Confirm delete function
function confirmDelete(recordId, teacherName) {
    return confirm(`Delete attendance record for ${teacherName}? This action cannot be undone.`);
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

// Add hover effects to stat cards
const statCards = document.querySelectorAll('.stat-card');
statCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Filter form auto-submit
const filterSelects = document.querySelectorAll('.filter-select');
filterSelects.forEach(select => {
    select.addEventListener('change', function() {
        if (this.form) {
            this.form.submit();
        }
    });
});

// Make teacher names clickable to copy
const teacherNames = document.querySelectorAll('.teacher-details h4');
teacherNames.forEach(name => {
    name.style.cursor = 'pointer';
    name.title = 'Click to copy teacher name';
    name.addEventListener('click', () => {
        copyToClipboard(name.textContent, 'Teacher name');
    });
});

// Make emails clickable to copy
const emailCells = document.querySelectorAll('.teacher-details span');
emailCells.forEach(cell => {
    const email = cell.textContent.replace(/📧/g, '').trim();
    if (email && email.includes('@')) {
        cell.style.cursor = 'pointer';
        cell.title = 'Click to copy email';
        cell.addEventListener('click', () => {
            copyToClipboard(email, 'Email');
        });
    }
});