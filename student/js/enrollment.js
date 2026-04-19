// Enrollment Page JavaScript

// Requirements data structure
const requirementsData = {
    'Grade 7': {
        'new': [
            { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
            { name: 'Certificate of Completion (Elementary)', required: true, can_follow: false, field: 'certificate_of_completion' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: 'Medical/Dental Certificate', required: false, can_follow: true, field: 'medical_cert' }
        ]
    },
    'Grade 8': {
        'continuing': [
            { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
        ],
        'transferee': [
            { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
            { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
        ]
    },
    'Grade 9': {
        'continuing': [
            { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
        ],
        'transferee': [
            { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
            { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
        ]
    },
    'Grade 10': {
        'continuing': [
            { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
        ],
        'transferee': [
            { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
            { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
        ]
    },
    'Grade 11': {
        'same_school': [
            { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' }
        ],
        'different_school': [
            { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
            { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: 'Entrance Exam / Screening Result', required: false, can_follow: true, field: 'entrance_exam_result' }
        ]
    },
    'Grade 12': {
        'continuing': [
            { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' }
        ],
        'transferee': [
            { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' },
            { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
            { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
            { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
            { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' }
        ]
    }
};

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle) {
    menuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('active');
    });
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function (event) {
    const isMobile = window.innerWidth <= 768;
    if (isMobile && sidebar && menuToggle) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    }
});

// Auto-populate school year
function setDefaultSchoolYear() {
    const today = new Date();
    const year = today.getFullYear();
    const nextYear = year + 1;
    const schoolYearInput = document.getElementById('school_year');
    if (schoolYearInput && !schoolYearInput.value) {
        schoolYearInput.value = year + '-' + nextYear;
    }
}

// Update student type options based on selected grade
function updateStudentTypeOptions() {
    const gradeSelect = document.getElementById('grade');
    const studentTypeGroup = document.getElementById('studentTypeGroup');
    const studentTypeSelect = document.getElementById('student_type');
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
    
    if (gradeName) {
        const options = getStudentTypeOptions(gradeName);
        studentTypeSelect.innerHTML = '<option value="">-- Select Student Type --</option>';
        
        for (const [value, label] of Object.entries(options)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            studentTypeSelect.appendChild(option);
        }
        
        studentTypeGroup.style.display = 'block';
        updateStrandVisibility(gradeName);
    } else {
        studentTypeGroup.style.display = 'none';
    }
    
    // Reset requirements section
    const requirementsSection = document.getElementById('requirementsSection');
    const requirementsList = document.getElementById('requirementsList');
    if (requirementsSection) requirementsSection.style.display = 'none';
    if (requirementsList) requirementsList.innerHTML = '';
}

// Get student type options based on grade level
function getStudentTypeOptions(gradeName) {
    switch(gradeName) {
        case 'Grade 7':
            return { 'new': 'New Student (From Elementary)' };
        case 'Grade 8':
        case 'Grade 9':
        case 'Grade 10':
            return {
                'continuing': 'Continuing Student (Moving to next grade)',
                'transferee': 'Transferee (From another school)'
            };
        case 'Grade 11':
            return {
                'same_school': 'From the same school (Placido L. Señor SHS - Junior High)',
                'different_school': 'From a different school (Transferee)'
            };
        case 'Grade 12':
            return {
                'continuing': 'Continuing Student (From Grade 11)',
                'transferee': 'Transferee (From another school)'
            };
        default:
            return { 'new': 'New Student', 'continuing': 'Continuing', 'transferee': 'Transferee' };
    }
}

// Update strand visibility for Senior High School
function updateStrandVisibility(gradeName) {
    const strandDiv = document.getElementById('strandDiv');
    const strandSelect = document.getElementById('strand');
    
    if (gradeName === 'Grade 11' || gradeName === 'Grade 12') {
        if (strandDiv) strandDiv.style.display = 'block';
        if (strandSelect) strandSelect.setAttribute('required', 'required');
    } else {
        if (strandDiv) strandDiv.style.display = 'none';
        if (strandSelect) {
            strandSelect.removeAttribute('required');
            strandSelect.value = '';
        }
    }
}

// Update requirements based on selected grade and student type
function updateRequirements() {
    const gradeSelect = document.getElementById('grade');
    const studentTypeSelect = document.getElementById('student_type');
    const requirementsSection = document.getElementById('requirementsSection');
    const requirementsList = document.getElementById('requirementsList');
    
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
    const studentType = studentTypeSelect ? studentTypeSelect.value : '';
    
    if (gradeName && studentType && requirementsData[gradeName] && requirementsData[gradeName][studentType]) {
        if (requirementsSection) requirementsSection.style.display = 'block';
        const requirements = requirementsData[gradeName][studentType];
        
        if (requirementsList) {
            requirementsList.innerHTML = '';
            requirements.forEach(req => {
                const reqDiv = document.createElement('div');
                reqDiv.className = 'requirement-item';
                
                let badgeHtml = '';
                if (req.required) {
                    badgeHtml = '<span class="req-badge badge-required">Required</span>';
                } else {
                    badgeHtml = '<span class="req-badge badge-optional">Optional</span>';
                }
                
                if (req.can_follow) {
                    badgeHtml += ' <span class="req-badge badge-follow">Can be followed up</span>';
                }
                
                reqDiv.innerHTML = `
                    <div class="requirement-name">
                        <span><i class="fas fa-file"></i> ${req.name}</span>
                        <div>${badgeHtml}</div>
                    </div>
                    <div class="file-upload-area" onclick="document.getElementById('${req.field}').click()">
                        <i class="fas fa-cloud-upload-alt"></i> Click to upload
                        <p style="font-size: 11px; color: #666; margin-top: 5px;">PDF, JPG, JPEG, or PNG</p>
                    </div>
                    <input type="file" name="${req.field}" id="${req.field}" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" 
                           ${req.required ? 'required' : ''} onchange="updateFileName(this)">
                    <div class="file-name" id="${req.field}_name"></div>
                `;
                requirementsList.appendChild(reqDiv);
            });
        }
    } else {
        if (requirementsSection) requirementsSection.style.display = 'none';
    }
}

// Update file name display when file is selected
function updateFileName(input) {
    const fileNameDiv = document.getElementById(input.id + '_name');
    if (input.files && input.files.length > 0) {
        const file = input.files[0];
        const fileSize = (file.size / 1024).toFixed(2);
        fileNameDiv.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> ${file.name} (${fileSize} KB)`;
        fileNameDiv.style.color = '#28a745';
    } else {
        fileNameDiv.innerHTML = '';
    }
}

// Form validation before submit
function validateForm() {
    const gradeSelect = document.getElementById('grade');
    const studentTypeSelect = document.getElementById('student_type');
    const schoolYearInput = document.getElementById('school_year');
    
    if (!gradeSelect.value) {
        showToast('Please select a grade level', 'error');
        gradeSelect.focus();
        return false;
    }
    
    if (!studentTypeSelect || !studentTypeSelect.value) {
        showToast('Please select student type', 'error');
        if (studentTypeSelect) studentTypeSelect.focus();
        return false;
    }
    
    if (!schoolYearInput.value) {
        showToast('Please enter school year', 'error');
        schoolYearInput.focus();
        return false;
    }
    
    // Validate required files
    const requiredFiles = document.querySelectorAll('input[type="file"][required]');
    for (const fileInput of requiredFiles) {
        if (!fileInput.files || fileInput.files.length === 0) {
            const requirementItem = fileInput.closest('.requirement-item');
            const requirementName = requirementItem ? requirementItem.querySelector('.requirement-name span')?.textContent : 'File';
            showToast(`${requirementName} is required`, 'error');
            return false;
        }
    }
    
    return true;
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
    toast.style.color = 'white';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '12px';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '500';
    toast.style.zIndex = '1000';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '10px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    toast.style.animation = 'slideInRight 0.3s ease';
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }, 3000);
}

// Add loading state to submit button
function setLoading(button, isLoading) {
    if (isLoading) {
        button.classList.add('loading');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner"></i> Submitting...';
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Enrollment';
    }
}

// Handle form submission
const enrollmentForm = document.getElementById('enrollmentForm');
if (enrollmentForm) {
    enrollmentForm.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        } else {
            const submitBtn = document.querySelector('.submit-btn');
            setLoading(submitBtn, true);
        }
    });
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.display = 'none';
            }
        }, 300);
    });
}, 5000);

// Add active class to current nav item
document.addEventListener('DOMContentLoaded', function() {
    setDefaultSchoolYear();
    
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-items a');
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
});

// Handle window resize
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('active');
        }
    }, 250);
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(style);