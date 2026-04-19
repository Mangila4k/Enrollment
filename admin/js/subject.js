/* ========================================
   SUBJECTS PAGE JAVASCRIPT
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

// Toggle grade section (collapsible)
function toggleGradeSection(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}

// Modal functions
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
    document.getElementById('addSubjectForm').reset();
}

function openEditModal(id) {
    // Find subject data from the passed PHP data
    const subject = subjectData.subjects.find(s => s.id == id);
    if (subject) {
        document.getElementById('edit_subject_id').value = subject.id;
        document.getElementById('edit_subject_name').value = subject.subject_name;
        document.getElementById('edit_grade_id').value = subject.grade_id;
        document.getElementById('edit_description').value = subject.description || '';
        document.getElementById('editModal').classList.add('active');
    } else {
        // Fallback to fetch if data not available
        fetch(`get_subject.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_subject_id').value = data.id;
                document.getElementById('edit_subject_name').value = data.subject_name;
                document.getElementById('edit_grade_id').value = data.grade_id;
                document.getElementById('edit_description').value = data.description || '';
                document.getElementById('editModal').classList.add('active');
            })
            .catch(() => {
                document.getElementById('editModal').classList.add('active');
            });
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function viewSubject(id) {
    const subject = subjectData.subjects.find(s => s.id == id);
    if (subject) {
        const gradeName = subjectData.gradeLevels[subject.grade_id] || 'Unknown';
        document.getElementById('viewContent').innerHTML = `
            <div class="form-group">
                <label>Subject Name</label>
                <p style="background: var(--bg-light); padding: 10px; border-radius: 8px;">${escapeHtml(subject.subject_name)}</p>
            </div>
            <div class="form-group">
                <label>Grade Level</label>
                <p style="background: var(--bg-light); padding: 10px; border-radius: 8px;">${escapeHtml(gradeName)}</p>
            </div>
            <div class="form-group">
                <label>Description</label>
                <p style="background: var(--bg-light); padding: 10px; border-radius: 8px;">${escapeHtml(subject.description) || 'No description provided.'}</p>
            </div>
            <div class="form-group">
                <label>Attendance Records</label>
                <p style="background: var(--bg-light); padding: 10px; border-radius: 8px;">${subject.attendance_count || 0} record(s)</p>
            </div>
        `;
        document.getElementById('viewModal').classList.add('active');
    }
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-submit on filter change
const gradeSelect = document.getElementById('gradeSelect');
if (gradeSelect) {
    gradeSelect.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
}

// Search on Enter
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('filterForm').submit();
        }
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

// Grade card hover effect
const gradeCards = document.querySelectorAll('.grade-card');
gradeCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Form validation for add subject
const addForm = document.getElementById('addSubjectForm');
if (addForm) {
    addForm.addEventListener('submit', function(e) {
        const subjectName = this.querySelector('input[name="subject_name"]').value.trim();
        const gradeId = this.querySelector('select[name="grade_id"]').value;
        
        if (!subjectName) {
            e.preventDefault();
            alert('Please enter subject name');
        } else if (!gradeId) {
            e.preventDefault();
            alert('Please select grade level');
        }
    });
}

// Form validation for edit subject
const editForm = document.getElementById('editSubjectForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        const subjectName = this.querySelector('input[name="subject_name"]').value.trim();
        const gradeId = this.querySelector('select[name="grade_id"]').value;
        
        if (!subjectName) {
            e.preventDefault();
            alert('Please enter subject name');
        } else if (!gradeId) {
            e.preventDefault();
            alert('Please select grade level');
        }
    });
}