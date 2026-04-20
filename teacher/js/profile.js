/* ========================================
   PROFILE PAGE JAVASCRIPT - TEACHER
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
    document.getElementById('imageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.getElementById('imagePreview');
            previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
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

// Auto-format verification code input
document.addEventListener('DOMContentLoaded', function() {
    const codeInputs = document.querySelectorAll('.verify-code-input');
    codeInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    });
});

// Toggle password fields
const changePasswordCheckbox = document.getElementById('change_password_checkbox');
const passwordFields = document.getElementById('passwordFields');
const currentPassword = document.getElementById('current_password');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const changePasswordBtn = document.getElementById('changePasswordBtn');

if(changePasswordCheckbox) {
    changePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.classList.add('show');
            currentPassword.disabled = false;
            newPassword.disabled = false;
            confirmPassword.disabled = false;
            changePasswordBtn.disabled = false;
            newPassword.focus();
        } else {
            passwordFields.classList.remove('show');
            currentPassword.disabled = true;
            newPassword.disabled = true;
            confirmPassword.disabled = true;
            changePasswordBtn.disabled = true;
            currentPassword.value = '';
            newPassword.value = '';
            confirmPassword.value = '';
            resetPasswordStrength();
        }
    });
}

function resetPasswordStrength() {
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');
    const matchText = document.getElementById('passwordMatchText');
    
    if(strengthBar) strengthBar.style.width = '0';
    if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
    if(matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
}

function checkPasswordStrength() {
    const password = newPassword.value;
    let strength = 0;
    let strengthLabel = '';
    let strengthColor = '';

    if (password.length >= 6) strength += 1;
    if (password.match(/[a-z]+/)) strength += 1;
    if (password.match(/[A-Z]+/)) strength += 1;
    if (password.match(/[0-9]+/)) strength += 1;
    if (password.match(/[$@#&!]+/)) strength += 1;

    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');

    if (password.length === 0) {
        strengthBar.style.width = '0';
        strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
        return;
    }

    if (strength <= 1) {
        strengthBar.style.width = '20%';
        strengthBar.style.backgroundColor = '#ef4444';
        strengthLabel = 'Weak';
        strengthColor = '#ef4444';
    } else if (strength <= 3) {
        strengthBar.style.width = '60%';
        strengthBar.style.backgroundColor = '#f59e0b';
        strengthLabel = 'Medium';
        strengthColor = '#f59e0b';
    } else {
        strengthBar.style.width = '100%';
        strengthBar.style.backgroundColor = '#10b981';
        strengthLabel = 'Strong';
        strengthColor = '#10b981';
    }

    if (password.length > 0 && strengthText) {
        strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: ${strengthColor};">Password strength: ${strengthLabel}</span>`;
    }
}

function checkPasswordMatch() {
    const password = newPassword.value;
    const confirm = confirmPassword.value;
    const matchText = document.getElementById('passwordMatchText');

    if (confirm.length === 0) {
        matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
    } else if (password === confirm) {
        matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
    } else {
        matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
    }
}

if(newPassword) {
    newPassword.addEventListener('input', checkPasswordStrength);
    newPassword.addEventListener('input', checkPasswordMatch);
}

if(confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

// Toast notification function
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

// Log profile data to console
console.log('Teacher Profile Loaded:', profileData);