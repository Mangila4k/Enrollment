/* ========================================
   STUDENTS PAGE JAVASCRIPT
   ======================================== */

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        }, 3000);
    });
    
    // Mobile menu toggle (if needed)
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
});

// Confirm delete function
function confirmDelete(studentName) {
    return confirm(`Are you sure you want to delete student: ${studentName}? This action cannot be undone.`);
}

// Search form submission
const searchForm = document.querySelector('.filter-form');
if (searchForm) {
    searchForm.addEventListener('submit', function(e) {
        const searchInput = this.querySelector('input[name="search"]');
        if (searchInput && searchInput.value.trim() === '') {
            // Remove empty search parameter
            const searchParam = this.querySelector('input[name="search"]');
            if (searchParam) searchParam.remove();
        }
    });
}

// Filter change auto-submit (optional)
const filterSelects = document.querySelectorAll('.filter-select');
filterSelects.forEach(select => {
    select.addEventListener('change', function() {
        const form = this.closest('form');
        if (form) form.submit();
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