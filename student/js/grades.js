// Grades Page JavaScript

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

// Modal functionality
const modal = document.getElementById('gradeModal');

function formatGradeValue(grade) {
    if (grade === null || grade === undefined || grade <= 0) return null;
    return parseFloat(grade).toFixed(2);
}

function getGradeColorClass(grade) {
    if (grade === null || grade <= 0) return '';
    if (grade >= 90) return 'high';
    if (grade >= 75) return 'medium';
    return 'low';
}

function getGradeMeaning(grade) {
    if (grade === null || grade <= 0) return '';
    if (grade >= 90) return 'Excellent';
    if (grade >= 85) return 'Very Good';
    if (grade >= 80) return 'Good';
    if (grade >= 75) return 'Satisfactory';
    return 'Needs Improvement';
}

function openModal(subjectId, subjectName) {
    // Check if gradesData is defined
    if (typeof gradesData === 'undefined') {
        console.error('Grades data not loaded');
        showToast('Unable to load grade data. Please refresh the page.', 'error');
        return;
    }
    
    const subjectGrades = gradesData[subjectId];
    if (!subjectGrades) {
        showToast('No grade data available for this subject.', 'error');
        return;
    }
    
    document.getElementById('modalSubjectTitle').innerHTML = `<i class="fas fa-book"></i> ${subjectName}`;
    
    const quarters = [
        { name: '1st Quarter', grade: subjectGrades.q1 },
        { name: '2nd Quarter', grade: subjectGrades.q2 },
        { name: '3rd Quarter', grade: subjectGrades.q3 },
        { name: '4th Quarter', grade: subjectGrades.q4 }
    ];
    
    let html = '<div class="quarter-grades-list">';
    let hasAnyGrade = false;
    let quarterSum = 0;
    let quarterCount = 0;
    
    for (const q of quarters) {
        const formattedGrade = formatGradeValue(q.grade);
        let gradeDisplay = '—';
        let gradeClass = '';
        let gradeMeaning = '';
        
        if (formattedGrade !== null) {
            hasAnyGrade = true;
            gradeDisplay = formattedGrade;
            gradeClass = getGradeColorClass(q.grade);
            gradeMeaning = getGradeMeaning(q.grade);
            quarterSum += parseFloat(q.grade);
            quarterCount++;
        }
        
        html += `
            <div class="quarter-grade-item">
                <span class="quarter-name">${q.name}</span>
                <div class="quarter-grade-info">
                    <span class="quarter-grade ${gradeClass}">${gradeDisplay}</span>
                    ${gradeMeaning ? `<span class="grade-meaning">${gradeMeaning}</span>` : ''}
                </div>
            </div>
        `;
    }
    html += '</div>';
    
    // Show subject average ONLY if at least 2 quarters have grades
    if (hasAnyGrade && quarterCount >= 2 && subjectGrades.average !== null && subjectGrades.average > 0) {
        const formattedAvg = formatGradeValue(subjectGrades.average);
        const avgClass = getGradeColorClass(subjectGrades.average);
        const avgMeaning = getGradeMeaning(subjectGrades.average);
        
        html += `
            <div class="subject-average">
                <span><i class="fas fa-calculator"></i> Subject Average</span>
                <div>
                    <span class="quarter-grade ${avgClass}" style="font-size: 20px;">${formattedAvg}</span>
                    <span class="grade-meaning" style="display: block; font-size: 11px; margin-top: 4px;">${avgMeaning}</span>
                </div>
            </div>
        `;
    }
    
    if (!hasAnyGrade) {
        html = `
            <div class="no-grades-modal">
                <i class="fas fa-info-circle"></i>
                <h4>No Grades Available</h4>
                <p>No grades have been recorded yet for this subject.</p>
                <p class="small">Please check back later after your teacher submits the grades.</p>
            </div>
        `;
    }
    
    document.getElementById('modalBody').innerHTML = html;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal when clicking outside
if (modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
        closeModal();
    }
});

// Attach click handlers to view grade buttons
document.querySelectorAll('.view-grade-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const subjectId = this.getAttribute('data-subject-id');
        const subjectName = this.getAttribute('data-subject-name');
        openModal(subjectId, subjectName);
    });
});

// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `grade-toast toast-${type}`;
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
    toast.style.animation = 'fadeInOut 3s ease';
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }, 2700);
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
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(20px); }
        10% { opacity: 1; transform: translateY(0); }
        90% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(20px); }
    }
    
    .no-grades-modal {
        text-align: center;
        padding: 40px 20px;
    }
    
    .no-grades-modal i {
        font-size: 56px;
        color: #94a3b8;
        margin-bottom: 15px;
    }
    
    .no-grades-modal h4 {
        font-size: 18px;
        color: #1e293b;
        margin-bottom: 10px;
    }
    
    .no-grades-modal p {
        color: #64748b;
        margin-bottom: 5px;
    }
    
    .no-grades-modal .small {
        font-size: 12px;
    }
    
    .quarter-grade-info {
        text-align: right;
    }
    
    .grade-meaning {
        font-size: 10px;
        color: #64748b;
        display: block;
        margin-top: 2px;
    }
    
    .toast-success, .toast-error {
        animation: fadeInOut 3s ease;
    }
`;
document.head.appendChild(style);