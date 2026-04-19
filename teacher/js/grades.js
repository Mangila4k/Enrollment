/* ========================================
   GRADES PAGE JS
   Author: PLNHS
   Description: JavaScript for grades.php
======================================== */

// DOM Elements
const sectionSelect = document.getElementById('section_id');
const subjectSelect = document.getElementById('subject_id');
const gradesForm = document.getElementById('gradesForm');

// Validate grade input
function validateGrade(input) {
    let value = parseFloat(input.value);
    if (input.value === '') {
        input.classList.remove('passing', 'failing');
        return;
    }
    if (!isNaN(value)) {
        if (value >= 75) {
            input.classList.add('passing');
            input.classList.remove('failing');
        } else {
            input.classList.add('failing');
            input.classList.remove('passing');
        }
    }
}

// Set all grades to a specific value
function setAllGrades(grade) {
    const gradeInputs = document.querySelectorAll('.grade-input');
    gradeInputs.forEach(input => {
        input.value = grade;
        validateGrade(input);
    });
}

// Form validation before submit
if (gradesForm) {
    gradesForm.addEventListener('submit', function(e) {
        const gradeInputs = document.querySelectorAll('.grade-input');
        let hasInvalid = false;
        let hasError = false;
        
        gradeInputs.forEach(input => {
            if (input.value !== '') {
                const value = parseFloat(input.value);
                if (isNaN(value)) {
                    hasError = true;
                    input.style.borderColor = '#dc3545';
                } else if (value < 0 || value > 100) {
                    hasInvalid = true;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '';
                }
            }
        });
        
        if (hasInvalid) {
            e.preventDefault();
            alert('Please ensure all grades are between 0 and 100');
        }
        if (hasError) {
            e.preventDefault();
            alert('Please enter valid numeric grades');
        }
    });
}

// Dynamic subject filtering based on section selection using AJAX
if (sectionSelect) {
    sectionSelect.addEventListener('change', function() {
        const sectionId = this.value;
        
        if (sectionId) {
            // Show loading state
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
            subjectSelect.disabled = true;
            
            // Fetch subjects for this section
            fetch(`get_teacher_subjects.php?section_id=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                    
                    if (data.length > 0) {
                        data.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.subject_name;
                            subjectSelect.appendChild(option);
                        });
                        subjectSelect.disabled = false;
                    } else {
                        subjectSelect.innerHTML = '<option value="">No subjects assigned to this section</option>';
                        subjectSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    subjectSelect.disabled = true;
                });
        } else {
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            subjectSelect.disabled = true;
        }
    });
    
    // Trigger change on page load if section is pre-selected
    if (sectionSelect.value) {
        sectionSelect.dispatchEvent(new Event('change'));
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + S to save grades
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const saveBtn = document.querySelector('.btn-save');
        if (saveBtn) {
            saveBtn.click();
        }
    }
    // Ctrl + P to set all passing
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        setAllGrades(75);
    }
    // Ctrl + F to set all failing
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        setAllGrades(65);
    }
});

// Add input event listeners to existing grade inputs
document.querySelectorAll('.grade-input').forEach(input => {
    input.addEventListener('input', function() {
        validateGrade(this);
    });
});

// Confirm before leaving if unsaved changes
let formChanged = false;
const gradeInputs = document.querySelectorAll('.grade-input');
gradeInputs.forEach(input => {
    input.addEventListener('change', function() {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Reset form changed flag after save
if (gradesForm) {
    gradesForm.addEventListener('submit', function() {
        formChanged = false;
    });
}