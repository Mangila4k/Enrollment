/* ========================================
   SECTIONS PAGE JS
   Author: PLNHS
   Description: JavaScript for sections.php
======================================== */

// DOM Elements
const gradeFilter = document.getElementById('gradeFilter');
const searchInput = document.getElementById('searchInput');
const addModal = document.getElementById('addModal');
const editModal = document.getElementById('editModal');

// Modal functions
function openAddModal() {
    if (addModal) {
        addModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeAddModal() {
    if (addModal) {
        addModal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Filter sections function
function filterSections() {
    const gradeFilterValue = gradeFilter ? gradeFilter.value.toLowerCase() : '';
    const searchText = searchInput ? searchInput.value.toLowerCase() : '';
    const cards = document.querySelectorAll('.section-card');

    cards.forEach(card => {
        const grade = card.dataset.grade ? card.dataset.grade.toLowerCase() : '';
        const name = card.querySelector('.section-name') ? 
                     card.querySelector('.section-name').textContent.toLowerCase() : '';
        
        const gradeMatch = !gradeFilterValue || grade.includes(gradeFilterValue);
        const searchMatch = !searchText || name.includes(searchText);
        
        card.style.display = (gradeMatch && searchMatch) ? 'block' : 'none';
    });
}

// Event listeners for filters
if (gradeFilter) {
    gradeFilter.addEventListener('change', filterSections);
}

if (searchInput) {
    searchInput.addEventListener('keyup', filterSections);
}

// Confirm delete function
function confirmDelete(studentCount, sectionName) {
    if (studentCount > 0) {
        return confirm(`Section "${sectionName}" has ${studentCount} student(s).\n\nDeleting this section will NOT delete the students, but they will lose their section assignment.\n\nAre you sure you want to continue?`);
    }
    return confirm(`Are you sure you want to delete section "${sectionName}"?`);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (addModal && event.target === addModal) {
        closeAddModal();
    }
    if (editModal && event.target === editModal) {
        if (editModal) {
            editModal.classList.remove('active');
            document.body.style.overflow = 'auto';
            window.location.href = 'sections.php';
        }
    }
});

// Add hover effect for section cards
const sectionCards = document.querySelectorAll('.section-card');
sectionCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.3s ease';
    });
});

// Export functionality (optional)
function exportSectionsToExcel() {
    const sections = document.querySelectorAll('.section-card');
    let csv = [['Section Name', 'Grade Level', 'Adviser', 'Number of Students']];
    
    sections.forEach(section => {
        if (section.style.display !== 'none') {
            const name = section.querySelector('.section-name')?.textContent || '';
            const grade = section.querySelector('.grade-level')?.textContent.replace('', '').trim() || '';
            const adviser = section.querySelector('.adviser-name')?.textContent || 'Not Assigned';
            const students = section.querySelector('.section-badge')?.textContent.replace('Students', '').trim() || '0';
            
            csv.push([name, grade, adviser, students]);
        }
    });
    
    const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sections_export_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}