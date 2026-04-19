/* ========================================
   SECTIONS PAGE JAVASCRIPT
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
    
    // Auto-hide alerts
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

// Modal functions
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(id, name, gradeName, adviserId) {
    document.getElementById('edit_section_id').value = id;
    document.getElementById('edit_section_name').value = name;
    
    // Set grade level
    const gradeSelect = document.getElementById('edit_grade_id');
    if (sectionData && sectionData.gradeLevels) {
        const gradeId = sectionData.gradeLevels[gradeName];
        if (gradeId) {
            gradeSelect.value = gradeId;
        }
    }
    
    document.getElementById('edit_adviser_id').value = adviserId;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function openScheduleModal(sectionId, sectionName) {
    window.location.href = 'create_schedule.php?section_id=' + sectionId;
}

// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        let searchValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#sectionsTable tbody tr');
        rows.forEach(row => {
            if (row.querySelector('.no-data')) return;
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });
}

// Filter by grade and adviser
const gradeFilter = document.getElementById('gradeFilter');
const adviserFilter = document.getElementById('adviserFilter');

if (gradeFilter) {
    gradeFilter.addEventListener('change', filterTable);
}
if (adviserFilter) {
    adviserFilter.addEventListener('change', filterTable);
}

function filterTable() {
    let gradeFilterValue = gradeFilter ? gradeFilter.value.toLowerCase() : '';
    let adviserFilterValue = adviserFilter ? adviserFilter.value : '';
    let rows = document.querySelectorAll('#sectionsTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.no-data')) return;
        let gradeCell = row.cells[1]?.textContent.toLowerCase() || '';
        let adviserCell = row.cells[2]?.textContent.toLowerCase() || '';
        let hasAdviser = !adviserCell.includes('not assigned');
        
        let gradeMatch = !gradeFilterValue || gradeCell.includes(gradeFilterValue);
        let adviserMatch = true;
        
        if (adviserFilterValue === 'assigned') {
            adviserMatch = hasAdviser;
        } else if (adviserFilterValue === 'unassigned') {
            adviserMatch = !hasAdviser;
        }
        
        row.style.display = (gradeMatch && adviserMatch) ? '' : 'none';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
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