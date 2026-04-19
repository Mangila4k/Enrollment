/* ========================================
   PROFILE PAGE JS
   Author: PLNHS
   Description: JavaScript for profile.php
======================================== */

// DOM Elements
const changePasswordCheckbox = document.getElementById('change_password_checkbox');
const passwordFields = document.getElementById('passwordFields');
const currentPassword = document.getElementById('current_password');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const changePasswordBtn = document.getElementById('changePasswordBtn');
const passwordForm = document.getElementById('passwordForm');

// Toggle password fields
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
            if (currentPassword) {
                currentPassword.disabled = true;
                currentPassword.value = '';
            }
            if (newPassword) {
                newPassword.disabled = true;
                newPassword.value = '';
            }
            if (confirmPassword) {
                confirmPassword.disabled = true;
                confirmPassword.value = '';
            }
            if (changePasswordBtn) changePasswordBtn.disabled = true;
            resetPasswordStrength();
        }
    });
}

// Reset password strength function
function resetPasswordStrength() {
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    
    if (strengthBar) strengthBar.style.width = '0';
    if (strengthText) {
        strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
        strengthText.style.color = '';
    }
    if (passwordMatch) {
        passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
        passwordMatch.style.color = '';
    }
}

// Password strength checker
function checkPasswordStrength() {
    const password = newPassword ? newPassword.value : '';
    let strength = 0;
    let strengthLabel = '';
    let strengthColor = '';
    let strengthBar = document.getElementById('passwordStrength');
    let strengthText = document.getElementById('passwordStrengthText');

    if (password.length >= 6) strength += 1;
    if (password.match(/[a-z]+/)) strength += 1;
    if (password.match(/[A-Z]+/)) strength += 1;
    if (password.match(/[0-9]+/)) strength += 1;
    if (password.match(/[$@#&!]+/)) strength += 1;

    if (strengthBar) {
        switch(strength) {
            case 0:
            case 1:
                strengthBar.style.width = '20%';
                strengthBar.style.backgroundColor = '#dc3545';
                strengthLabel = 'Weak';
                strengthColor = '#dc3545';
                break;
            case 2:
            case 3:
                strengthBar.style.width = '60%';
                strengthBar.style.backgroundColor = '#ffc107';
                strengthLabel = 'Medium';
                strengthColor = '#ffc107';
                break;
            case 4:
            case 5:
                strengthBar.style.width = '100%';
                strengthBar.style.backgroundColor = '#28a745';
                strengthLabel = 'Strong';
                strengthColor = '#28a745';
                break;
        }
    }

    if (strengthText && password.length > 0) {
        strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: ${strengthColor};">Password strength: ${strengthLabel}</span>`;
    } else if (strengthText) {
        resetPasswordStrength();
    }
}

// Check password match
function checkPasswordMatch() {
    const password = newPassword ? newPassword.value : '';
    const confirm = confirmPassword ? confirmPassword.value : '';
    const passwordMatch = document.getElementById('passwordMatch');

    if (passwordMatch && confirm.length > 0) {
        if (password === confirm) {
            passwordMatch.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>';
        } else {
            passwordMatch.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>';
        }
    } else if (passwordMatch) {
        passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
        passwordMatch.style.color = '';
    }
}

// Event listeners for password fields
if (newPassword) {
    newPassword.addEventListener('input', checkPasswordStrength);
    newPassword.addEventListener('input', checkPasswordMatch);
}

if (confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

// Form validation for password change
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        if (changePasswordCheckbox && changePasswordCheckbox.checked) {
            const current = currentPassword ? currentPassword.value : '';
            const password = newPassword ? newPassword.value : '';
            const confirm = confirmPassword ? confirmPassword.value : '';

            if (!current) {
                e.preventDefault();
                alert('Please enter your current password');
            } else if (password.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long');
            } else if (password !== confirm) {
                e.preventDefault();
                alert('New passwords do not match');
            }
        } else if (changePasswordCheckbox) {
            e.preventDefault();
        }
    });
}

// Set progress bar width after page load
document.addEventListener('DOMContentLoaded', function() {
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill && profileData && profileData.processingRate) {
        progressFill.style.width = profileData.processingRate + '%';
    }
});