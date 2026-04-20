/* ========================================
   REQUIREMENTS PAGE JAVASCRIPT
   Author: PLNHS
   Description: JavaScript for requirements.php with upload functionality
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
    
    // Animate progress bar on load
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        const targetWidth = progressFill.style.width;
        progressFill.style.width = '0%';
        setTimeout(() => {
            progressFill.style.width = targetWidth;
        }, 100);
    }
    
    // Add hover effect to requirement items
    const requirementItems = document.querySelectorAll('.requirement-item');
    requirementItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
            item.style.transform = 'translateX(5px)';
        });
        item.addEventListener('mouseleave', () => {
            item.style.transform = 'translateX(0)';
        });
    });
    
    // Add click handler for view buttons
    const viewButtons = document.querySelectorAll('.btn-view');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            console.log('Viewing requirement document');
        });
    });
    
    // Log requirements data to console for debugging
    if (typeof requirementsData !== 'undefined') {
        console.log('Requirements Data:', requirementsData);
        
        // Update page title with completion percentage
        if (requirementsData.completionPercentage !== undefined) {
            document.title = `${requirementsData.completionPercentage}% Complete - Requirements | PLSNHS`;
        }
    }
});

// ========== UPLOAD MODAL FUNCTIONS ==========
let currentRequirementKey = '';
let currentRequirementLabel = '';

function openUploadModal(key, label, allowedTypes, maxSize) {
    currentRequirementKey = key;
    currentRequirementLabel = label;
    
    document.getElementById('requirement_key').value = key;
    document.getElementById('requirement_label').textContent = label;
    document.getElementById('allowed_types').textContent = allowedTypes.toUpperCase();
    document.getElementById('max_size').textContent = maxSize;
    
    // Reset file input
    const fileInput = document.getElementById('requirement_file');
    if (fileInput) {
        fileInput.value = '';
    }
    document.getElementById('selectedFile').innerHTML = '';
    
    // Reset progress
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadProgressFill').style.width = '0%';
    
    document.getElementById('uploadModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    // Reset progress
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadProgressFill').style.width = '0%';
}

// File selection preview
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('requirement_file');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const fileName = file.name;
                const fileSize = file.size;
                const maxSize = parseInt(document.getElementById('max_size')?.textContent || 5) * 1024 * 1024;
                
                if (fileSize > maxSize) {
                    showToast(`File too large! Maximum size is ${maxSize / (1024 * 1024)}MB`, 'error');
                    this.value = '';
                    document.getElementById('selectedFile').innerHTML = '';
                    return;
                }
                
                // Check file type
                const allowedTypes = document.getElementById('allowed_types')?.textContent.toLowerCase() || '';
                const fileExt = fileName.split('.').pop().toLowerCase();
                
                if (!allowedTypes.includes(fileExt)) {
                    showToast(`Invalid file type! Allowed: ${allowedTypes.toUpperCase()}`, 'error');
                    this.value = '';
                    document.getElementById('selectedFile').innerHTML = '';
                    return;
                }
                
                document.getElementById('selectedFile').innerHTML = `
                    <i class="fas fa-file"></i> ${fileName}
                    <small>(${(fileSize / 1024 / 1024).toFixed(2)} MB)</small>
                `;
            } else {
                document.getElementById('selectedFile').innerHTML = '';
            }
        });
    }
});

// Upload form submission with progress
const uploadForm = document.getElementById('uploadForm');
if (uploadForm) {
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('requirement_file');
        if (!fileInput.files || fileInput.files.length === 0) {
            showToast('Please select a file to upload.', 'error');
            return;
        }
        
        // Show progress bar
        const progressDiv = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('uploadProgressFill');
        const submitBtn = document.getElementById('submitUpload');
        
        progressDiv.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        // Create FormData
        const formData = new FormData();
        formData.append('requirement_key', document.getElementById('requirement_key').value);
        formData.append('requirement_file', fileInput.files[0]);
        formData.append('upload_requirement', '1');
        
        // Create XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                progressFill.style.width = percent + '%';
            }
        });
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                showToast('File uploaded successfully! Refreshing page...', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Upload failed. Please try again.', 'error');
                progressDiv.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Upload';
            }
        };
        
        xhr.onerror = function() {
            showToast('Network error. Please try again.', 'error');
            progressDiv.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Upload';
        };
        
        xhr.open('POST', window.location.href, true);
        xhr.send(formData);
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target === modal) {
        closeUploadModal();
    }
}

// ========== TOAST NOTIFICATION FUNCTION ==========
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add toast styles if not already present
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
        .toast-notification.show {
            transform: translateX(0);
        }
        .toast-success {
            border-left-color: #10b981;
        }
        .toast-success i {
            color: #10b981;
        }
        .toast-error {
            border-left-color: #ef4444;
        }
        .toast-error i {
            color: #ef4444;
        }
        .toast-info {
            border-left-color: #3b82f6;
        }
        .toast-info i {
            color: #3b82f6;
        }
        @media (max-width: 480px) {
            .toast-notification {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }
        }
    `;
    document.head.appendChild(toastStyles);
}

// ========== PRINT FUNCTIONALITY ==========
function printRequirements() {
    window.print();
}

// Add print button if needed
document.addEventListener('DOMContentLoaded', function() {
    const pageHeader = document.querySelector('.page-header');
    if (pageHeader && !document.querySelector('.print-req-btn')) {
        const printBtn = document.createElement('button');
        printBtn.className = 'print-req-btn';
        printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
        printBtn.onclick = printRequirements;
        printBtn.style.cssText = `
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
            margin-left: 10px;
        `;
        printBtn.addEventListener('mouseenter', () => {
            printBtn.style.background = '#5a6268';
            printBtn.style.transform = 'translateY(-2px)';
        });
        printBtn.addEventListener('mouseleave', () => {
            printBtn.style.background = '#6c757d';
            printBtn.style.transform = 'translateY(0)';
        });
        
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            headerActions.appendChild(printBtn);
        }
    }
});

// Add CSS for print
const printStyles = document.createElement('style');
printStyles.textContent = `
    @media print {
        .sidebar, .menu-toggle, .back-btn, .print-req-btn, .contact-card, .instructions-card, .header-actions, .info-status, .btn-upload, .modal {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .requirements-card, .progress-card, .info-card {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .requirement-item {
            break-inside: avoid;
        }
        .alert {
            display: none !important;
        }
    }
`;
document.head.appendChild(printStyles);

// ========== NOTIFICATION AUTO-REFRESH ==========
// Auto-refresh notifications every 30 seconds
if (window.location.pathname.includes('requirements.php')) {
    setInterval(function() {
        fetch(window.location.origin + '/EnrollmentSystem/student/dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unread_count > 0) {
                const badge = document.querySelector('.notif-count');
                if (badge) {
                    badge.textContent = data.unread_count;
                } else {
                    const notificationBtn = document.querySelector('.notification-btn');
                    if (notificationBtn) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notif-count';
                        newBadge.textContent = data.unread_count;
                        notificationBtn.appendChild(newBadge);
                    }
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
    }, 30000);
}

// ========== KEYBOARD SHORTCUTS ==========
document.addEventListener('keydown', function(e) {
    // Escape key to close modal
    if (e.key === 'Escape') {
        const modal = document.getElementById('uploadModal');
        if (modal && modal.classList.contains('active')) {
            closeUploadModal();
        }
    }
    
    // Ctrl + P to print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printRequirements();
    }
});

// ========== ANIMATION FOR NEWLY ADDED ITEMS ==========
// Observe for newly added requirement items
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1 && node.classList && node.classList.contains('requirement-item')) {
                node.style.opacity = '0';
                node.style.transform = 'translateX(-10px)';
                setTimeout(() => {
                    node.style.transition = 'all 0.3s ease';
                    node.style.opacity = '1';
                    node.style.transform = 'translateX(0)';
                }, 10);
            }
        });
    });
});

observer.observe(document.body, { childList: true, subtree: true });