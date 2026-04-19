/* ========================================
   SECTION STUDENTS PAGE JS
   Author: PLNHS
   Description: JavaScript for section_students.php
======================================== */

// DOM Elements
const searchCurrent = document.getElementById('searchCurrent');
const searchAvailable = document.getElementById('searchAvailable');
const selectAllCurrent = document.getElementById('selectAllCurrent');
const selectAllAvailable = document.getElementById('selectAllAvailable');
const removeForm = document.getElementById('removeForm');
const assignForm = document.getElementById('assignForm');

// Search functionality for current students
if (searchCurrent) {
    searchCurrent.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const items = document.querySelectorAll('#currentList .student-item');
        
        items.forEach(item => {
            const name = item.getAttribute('data-name') || item.textContent.toLowerCase();
            item.style.display = name.includes(searchText) ? 'flex' : 'none';
        });
    });
}

// Search functionality for available students
if (searchAvailable) {
    searchAvailable.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const items = document.querySelectorAll('#availableList .student-item');
        
        items.forEach(item => {
            const name = item.getAttribute('data-name') || item.textContent.toLowerCase();
            item.style.display = name.includes(searchText) ? 'flex' : 'none';
        });
    });
}

// Select all for current students
if (selectAllCurrent) {
    selectAllCurrent.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.current-checkbox');
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
            cb.closest('.student-item')?.style.display !== 'none'
        );
        
        visibleCheckboxes.forEach(cb => cb.checked = this.checked);
    });
}

// Select all for available students
if (selectAllAvailable) {
    selectAllAvailable.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.available-checkbox');
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
            cb.closest('.student-item')?.style.display !== 'none'
        );
        
        visibleCheckboxes.forEach(cb => cb.checked = this.checked);
    });
}

// Toggle all function for bulk actions
function toggleAll(type) {
    const checkboxes = type === 'current' 
        ? document.querySelectorAll('.current-checkbox')
        : document.querySelectorAll('.available-checkbox');
    
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
        cb.closest('.student-item')?.style.display !== 'none'
    );
    
    const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
    visibleCheckboxes.forEach(cb => cb.checked = !allChecked);
    
    // Update select all checkbox
    if (type === 'current' && selectAllCurrent) {
        selectAllCurrent.checked = !allChecked;
    } else if (type === 'available' && selectAllAvailable) {
        selectAllAvailable.checked = !allChecked;
    }
}

// Confirm before removing students
if (removeForm) {
    removeForm.addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.current-checkbox:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one student to remove.');
        }
    });
}

// Confirm before assigning students
if (assignForm) {
    assignForm.addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.available-checkbox:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one student to assign.');
        }
    });
}

// Update select all state when individual checkboxes change
function updateSelectAllState() {
    // For current students
    if (selectAllCurrent) {
        const checkboxes = document.querySelectorAll('.current-checkbox');
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
            cb.closest('.student-item')?.style.display !== 'none'
        );
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
        selectAllCurrent.checked = allChecked;
        selectAllCurrent.indeterminate = !allChecked && visibleCheckboxes.some(cb => cb.checked);
    }
    
    // For available students
    if (selectAllAvailable) {
        const checkboxes = document.querySelectorAll('.available-checkbox');
        const visibleCheckboxes = Array.from(checkboxes).filter(cb => 
            cb.closest('.student-item')?.style.display !== 'none'
        );
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
        selectAllAvailable.checked = allChecked;
        selectAllAvailable.indeterminate = !allChecked && visibleCheckboxes.some(cb => cb.checked);
    }
}

// Add event listeners to individual checkboxes
document.querySelectorAll('.current-checkbox, .available-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectAllState);
});

// Initialize
updateSelectAllState();