/* ========================================
   ENROLLMENTS PAGE JAVASCRIPT
   Author: PLNHS
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
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            if (sidebar && menuToggle) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        });
    }, 5000);
    
    const gradeSelect = document.getElementById('gradeSelect');
    const strandGroup = document.getElementById('strandGroup');
    
    if (gradeSelect && strandGroup) {
        gradeSelect.addEventListener('change', function() {
            const gradeName = this.options[this.selectedIndex]?.text;
            if (gradeName === 'Grade 11' || gradeName === 'Grade 12') {
                strandGroup.style.display = 'block';
            } else {
                strandGroup.style.display = 'none';
                const strandSelect = strandGroup.querySelector('select');
                if (strandSelect) strandSelect.value = '';
            }
        });
    }
    
    initNotifyButtons();
    
    const studentNames = document.querySelectorAll('.student-details h4');
    studentNames.forEach(name => {
        name.style.cursor = 'pointer';
        name.title = 'Click to copy student name';
        name.addEventListener('click', () => {
            copyToClipboard(name.textContent, 'Student name');
        });
    });
    
    const idNumbers = document.querySelectorAll('.id-badge');
    idNumbers.forEach(id => {
        id.style.cursor = 'pointer';
        id.title = 'Click to copy ID number';
        id.addEventListener('click', () => {
            copyToClipboard(id.textContent, 'ID number');
        });
    });
    
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f9fafb';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
});

const addModal = document.getElementById('addModal');
const addEnrollmentBtn = document.getElementById('addEnrollmentBtn');
const closeModalBtn = document.getElementById('closeModalBtn');
const cancelModalBtn = document.getElementById('cancelModalBtn');
const exportExcelBtn = document.getElementById('exportExcelBtn');
const printBtn = document.getElementById('printBtn');

function showAddModal() {
    if (addModal) {
        addModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function hideAddModal() {
    if (addModal) {
        addModal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

if (addEnrollmentBtn) {
    addEnrollmentBtn.addEventListener('click', showAddModal);
}

if (closeModalBtn) {
    closeModalBtn.addEventListener('click', hideAddModal);
}

if (cancelModalBtn) {
    cancelModalBtn.addEventListener('click', hideAddModal);
}

window.addEventListener('click', function(event) {
    if (addModal && event.target === addModal) {
        hideAddModal();
    }
});

function confirmApprove(studentName) {
    return confirm(`Approve enrollment for ${studentName}? This will generate a Student ID number and notify the student.`);
}

function confirmReject(studentName) {
    return confirm(`Reject enrollment for ${studentName}? The student will receive a notification about the rejection.`);
}

function confirmDelete(studentName) {
    return confirm(`Delete enrollment record for ${studentName}? This action cannot be undone.`);
}

function exportToExcel() {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tr'));
    let csv = [];
    const headers = ['Student Name', 'Email', 'ID Number', 'Grade Level', 'Strand', 'School Year', 'Student Type', 'Status'];
    csv.push(headers.map(h => '"' + h + '"').join(','));
    
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length > 0) {
            const studentInfo = cells[0];
            const studentName = studentInfo?.querySelector('.student-details h4')?.innerText || '';
            const studentEmail = studentInfo?.querySelector('.student-details span')?.innerText.replace('✉️', '').trim() || '';
            const idNumber = cells[1]?.innerText.trim() || '';
            const gradeStrand = cells[2]?.innerText.trim() || '';
            const schoolYear = cells[3]?.innerText.trim() || '';
            const studentType = cells[4]?.innerText.trim() || '';
            const status = cells[5]?.innerText.trim() || '';
            const rowData = [studentName, studentEmail, idNumber, gradeStrand, schoolYear, studentType, status];
            csv.push(rowData.map(cell => '"' + String(cell).replace(/"/g, '""') + '"').join(','));
        }
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'enrollments_export_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    showToast('Export completed successfully!', 'success');
}

function printTable() {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    const tableClone = table.cloneNode(true);
    const newWindow = window.open('', '_blank');
    newWindow.document.write(`
        <!DOCTYPE html>
        <html><head><title>Enrollment Records - PLS NHS</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', Arial, sans-serif; padding: 40px; background: white; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #0B4F2E; }
            h2 { color: #0B4F2E; margin-bottom: 5px; font-size: 24px; }
            h3 { color: #666; font-weight: 400; margin-bottom: 10px; font-size: 16px; }
            .date { color: #999; font-size: 12px; margin-top: 10px; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th { background: #f0f0f0; padding: 12px; text-align: left; font-size: 13px; font-weight: 600; border-bottom: 2px solid #ddd; }
            td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
            .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-enrolled { background: #d4edda; color: #155724; }
            .status-rejected { background: #f8d7da; color: #721c24; }
            .grade-tag { background: #e9ecef; padding: 3px 8px; border-radius: 12px; font-size: 11px; display: inline-block; margin-right: 5px; }
            .student-type-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .student-type-new { background: #cfe2ff; color: #084298; }
            .student-type-continuing { background: #d1e7dd; color: #0f5132; }
            .student-type-transferee { background: #fff3cd; color: #856404; }
            .footer { margin-top: 30px; text-align: center; color: #999; font-size: 11px; padding-top: 20px; border-top: 1px solid #eee; }
        </style></head>
        <body>
            <div class="header"><h2>Placido L. Señor National High School</h2><h3>Enrollment Records</h3><div class="date">Generated on: ${new Date().toLocaleString()}</div></div>
            ${tableClone.outerHTML}
            <div class="footer">This is a system-generated document. For official purposes only.</div>
        </body></html>
    `);
    newWindow.document.close();
    newWindow.print();
}

function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i><span>${escapeHtml(message)}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('show'); }, 100);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

function initNotifyButtons() {
    const notifyButtons = document.querySelectorAll('.btn-notify-missing');
    notifyButtons.forEach(button => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const studentId = this.getAttribute('data-student-id');
            const studentName = this.getAttribute('data-student-name');
            const studentEmail = this.getAttribute('data-student-email');
            let missingList = this.getAttribute('data-missing-list');
            const enrollmentId = this.getAttribute('data-enrollment-id');
            try {
                if (missingList) missingList = JSON.parse(missingList);
            } catch(e) { missingList = []; }
            notifyMissingRequirements(studentId, studentName, studentEmail, missingList, enrollmentId, this);
        });
    });
}

function notifyMissingRequirements(studentId, studentName, studentEmail, missingList, enrollmentId, buttonElement) {
    if (!missingList || missingList.length === 0) {
        showToast('No missing requirements to notify about.', 'info');
        return;
    }
    const requirementsText = missingList.join(', ');
    if (confirm(`Send notification to ${studentName} about missing requirements?\n\nMissing: ${requirementsText}`)) {
        const originalText = buttonElement ? buttonElement.innerHTML : '<i class="fas fa-bell"></i> Notify';
        const originalBackground = buttonElement ? buttonElement.style.background : '';
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            buttonElement.style.opacity = '0.7';
        }
        showToast('Sending notification to student...', 'info');
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast(response.message || `Notification sent to ${studentName}`, 'success');
                        if (buttonElement) {
                            buttonElement.innerHTML = '<i class="fas fa-check"></i> Sent!';
                            buttonElement.style.background = '#10b981';
                            setTimeout(() => {
                                buttonElement.innerHTML = originalText;
                                buttonElement.style.background = originalBackground || '#f59e0b';
                            }, 3000);
                        }
                    } else {
                        showToast(response.message || 'Failed to send notification.', 'error');
                    }
                } catch (e) {
                    showToast('Notification sent successfully!', 'success');
                }
            } else {
                showToast('Server error. Please try again.', 'error');
            }
        };
        xhr.onerror = function() {
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            showToast('Network error. Please check your connection.', 'error');
        };
        xhr.timeout = 30000;
        xhr.ontimeout = function() {
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalText;
                buttonElement.style.opacity = '1';
                buttonElement.style.background = originalBackground;
            }
            showToast('Request timeout. Please try again.', 'error');
        };
        const params = new URLSearchParams();
        params.append('action', 'notify_missing');
        params.append('student_id', studentId);
        params.append('student_name', studentName);
        params.append('student_email', studentEmail);
        params.append('missing_requirements', requirementsText);
        params.append('enrollment_id', enrollmentId);
        xhr.send(params.toString());
    }
}

if (exportExcelBtn) exportExcelBtn.addEventListener('click', exportToExcel);
if (printBtn) printBtn.addEventListener('click', printTable);

const addEnrollmentForm = document.getElementById('addEnrollmentForm');
if (addEnrollmentForm) {
    addEnrollmentForm.addEventListener('submit', function(e) {
        const studentId = this.querySelector('[name="student_id"]').value;
        const gradeId = this.querySelector('[name="grade_id"]').value;
        const schoolYear = this.querySelector('[name="school_year"]').value;
        if (!studentId) { e.preventDefault(); showToast('Please select a student', 'error'); return false; }
        if (!gradeId) { e.preventDefault(); showToast('Please select a grade level', 'error'); return false; }
        if (!schoolYear) { e.preventDefault(); showToast('Please enter the school year', 'error'); return false; }
        const schoolYearPattern = /^\d{4}-\d{4}$/;
        if (!schoolYearPattern.test(schoolYear)) {
            e.preventDefault();
            showToast('Please enter school year in format: YYYY-YYYY', 'error');
            return false;
        }
        return true;
    });
}

if (!document.querySelector('#toastStyles')) {
    const toastStyles = document.createElement('style');
    toastStyles.id = 'toastStyles';
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
            font-size: 14px;
            font-weight: 500;
            max-width: 350px;
        }
        .toast-notification.show { transform: translateX(0); }
        .toast-success { border-left-color: #10b981; }
        .toast-success i { color: #10b981; }
        .toast-error { border-left-color: #ef4444; }
        .toast-error i { color: #ef4444; }
        .toast-info { border-left-color: #3b82f6; }
        .toast-info i { color: #3b82f6; }
        @media (max-width: 480px) {
            .toast-notification { bottom: 10px; right: 10px; left: 10px; max-width: calc(100% - 20px); }
        }
    `;
    document.head.appendChild(toastStyles);
}

window.confirmApprove = confirmApprove;
window.confirmReject = confirmReject;
window.confirmDelete = confirmDelete;
window.showAddModal = showAddModal;
window.hideAddModal = hideAddModal;
window.exportToExcel = exportToExcel;
window.printTable = printTable;
window.copyToClipboard = copyToClipboard;
window.showToast = showToast;
window.notifyMissingRequirements = notifyMissingRequirements;