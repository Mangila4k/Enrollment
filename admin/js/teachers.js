/* ========================================
   TEACHERS PAGE JAVASCRIPT
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
});

// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        let searchValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#teachersTable tbody tr');
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });
}

// Filter by status
const statusFilter = document.getElementById('statusFilter');
if (statusFilter) {
    statusFilter.addEventListener('change', function() {
        let filterValue = this.value;
        let rows = document.querySelectorAll('#teachersTable tbody tr');
        rows.forEach(row => {
            let sectionsCell = row.cells[3]?.textContent || '';
            if (filterValue === 'with-sections') {
                row.style.display = sectionsCell.includes('No sections') ? 'none' : '';
            } else if (filterValue === 'without-sections') {
                row.style.display = sectionsCell.includes('No sections') ? '' : 'none';
            } else {
                row.style.display = '';
            }
        });
    });
}

// Confirm delete function
function confirmDelete(teacherName) {
    return confirm(`Are you sure you want to delete teacher: ${teacherName}? This action cannot be undone.`);
}

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

// Export function for use in onclick
window.confirmDelete = confirmDelete;