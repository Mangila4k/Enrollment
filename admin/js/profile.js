/* ========================================
   PROFILE PAGE JAVASCRIPT
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
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        });
    }, 5000);
});

// Image modal functions
function openImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Preview image before upload
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.getElementById('imagePreview');
            if (previewDiv) {
                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" id="previewImg" style="width: 100%; height: 100%; object-fit: cover;">`;
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
        closeImageModal();
    }
}

// Toggle password fields
const changePasswordCheckbox = document.getElementById('change_password_checkbox');
const passwordFields = document.getElementById('passwordFields');
const currentPassword = document.getElementById('current_password');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const changePasswordBtn = document.getElementById('changePasswordBtn');

if (changePasswordCheckbox) {
    changePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.classList.add('show');
            if (currentPassword) currentPassword.disabled = false;
            if (newPassword) newPassword.disabled = false;
            if (confirmPassword) confirmPassword.disabled = false;
            if (changePasswordBtn) changePasswordBtn.disabled = false;
            if (newPassword) newPassword.focus();
        } else {
            passwordFields.classList.remove('show');
            if (currentPassword) currentPassword.disabled = true;
            if (newPassword) newPassword.disabled = true;
            if (confirmPassword) confirmPassword.disabled = true;
            if (changePasswordBtn) changePasswordBtn.disabled = true;
            if (currentPassword) currentPassword.value = '';
            if (newPassword) newPassword.value = '';
            if (confirmPassword) confirmPassword.value = '';
            resetPasswordStrength();
        }
    });
}

// Password strength checker
function resetPasswordStrength() {
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    const matchText = document.getElementById('passwordMatchText');
    
    if (strengthBar) strengthBar.style.width = '0';
    if (strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
    if (matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
}

function checkPasswordStrength() {
    if (!newPassword) return;
    
    const password = newPassword.value;
    let strength = 0;

    if (password.length >= 6) strength += 1;
    if (password.length >= 8) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;

    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');

    if (password.length === 0) {
        resetPasswordStrength();
        return;
    }

    if (strength <= 2) {
        if (strengthBar) strengthBar.style.width = '33%';
        if (strengthBar) strengthBar.style.backgroundColor = '#ef4444';
        if (strengthText) strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Password strength: Weak</span>`;
    } else if (strength <= 4) {
        if (strengthBar) strengthBar.style.width = '66%';
        if (strengthBar) strengthBar.style.backgroundColor = '#f59e0b';
        if (strengthText) strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Password strength: Medium</span>`;
    } else {
        if (strengthBar) strengthBar.style.width = '100%';
        if (strengthBar) strengthBar.style.backgroundColor = '#10b981';
        if (strengthText) strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Password strength: Strong</span>`;
    }
}

function checkPasswordMatch() {
    if (!newPassword || !confirmPassword) return;
    
    const password = newPassword.value;
    const confirm = confirmPassword.value;
    const matchText = document.getElementById('passwordMatchText');

    if (confirm.length === 0) {
        if (matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
    } else if (password === confirm) {
        if (matchText) matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
    } else {
        if (matchText) matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
    }
}

if (newPassword) {
    newPassword.addEventListener('input', checkPasswordStrength);
    newPassword.addEventListener('input', checkPasswordMatch);
}

if (confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

// Copy to clipboard functionality
function copyToClipboard(text, fieldName) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`${fieldName} copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
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

// Add toast styles
const toastStyles = document.createElement('style');
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
    
    .toast-info {
        border-left-color: #3b82f6;
    }
    
    .toast-info i {
        color: #3b82f6;
    }
`;
document.head.appendChild(toastStyles);

// Format phone number input
const phoneInput = document.querySelector('input[name="phone"]');
if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
        // Remove all non-numeric characters
        let value = this.value.replace(/[^0-9]/g, '');
        
        // Limit to 11 digits (Philippine mobile number)
        if (value.length > 11) {
            value = value.slice(0, 11);
        }
        
        this.value = value;
    });
}

// Add hover effect to profile card
const profileCard = document.querySelector('.profile-card');
if (profileCard) {
    profileCard.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.transition = 'transform 0.3s ease';
    });
    profileCard.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
}

// Form validation for phone number before submit
const profileForm = document.querySelector('.form-card form');
if (profileForm && !profileForm.querySelector('input[name="change_password"]')) {
    profileForm.addEventListener('submit', function(e) {
        const phone = this.querySelector('input[name="phone"]').value;
        
        if (phone && phone.length > 0) {
            if (phone.length !== 11 || !phone.startsWith('09')) {
                e.preventDefault();
                showToast('Invalid Philippine mobile number. Format: 09XXXXXXXXX (11 digits)', 'error');
                return false;
            }
        }
    });
}