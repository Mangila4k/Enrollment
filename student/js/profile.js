/* ========================================
   STUDENT PROFILE PAGE JAVASCRIPT
   Author: PLNHS
   Description: Complete JavaScript for student profile page
======================================== */

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if(menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if(window.innerWidth <= 768) {
            if(sidebar && menuToggle) {
                if(!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        }
    });
    
    // Initialize password change functionality
    initPasswordChange();
});

// ===== PASSWORD CHANGE FUNCTIONS =====
function initPasswordChange() {
    const changePasswordCheckbox = document.getElementById('change_password_checkbox');
    const passwordFields = document.getElementById('passwordFields');
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    if(changePasswordCheckbox && passwordFields) {
        changePasswordCheckbox.addEventListener('change', function() {
            if(this.checked) {
                passwordFields.style.display = 'block';
                passwordFields.classList.add('show');
                if(currentPassword) currentPassword.disabled = false;
                if(newPassword) newPassword.disabled = false;
                if(confirmPassword) confirmPassword.disabled = false;
                if(changePasswordBtn) changePasswordBtn.disabled = true;
                if(newPassword) newPassword.focus();
            } else {
                passwordFields.style.display = 'none';
                passwordFields.classList.remove('show');
                if(currentPassword) {
                    currentPassword.disabled = true;
                    currentPassword.value = '';
                }
                if(newPassword) {
                    newPassword.disabled = true;
                    newPassword.value = '';
                }
                if(confirmPassword) {
                    confirmPassword.disabled = true;
                    confirmPassword.value = '';
                }
                if(changePasswordBtn) changePasswordBtn.disabled = true;
                resetPasswordStrength();
            }
        });
    }
    
    if(newPassword) {
        newPassword.addEventListener('input', updatePasswordStrength);
        newPassword.addEventListener('input', checkPasswordMatch);
    }
    
    if(confirmPassword) {
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
}

function resetPasswordStrength() {
    const strengthFill = document.querySelector('.strength-bar-fill');
    const strengthText = document.querySelector('.strength-text');
    const matchText = document.querySelector('.password-match');
    
    if(strengthFill) strengthFill.style.width = '0%';
    if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Enter new password';
    if(matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
    
    const requirements = ['length', 'upper', 'lower', 'number', 'special'];
    requirements.forEach(req => {
        const element = document.getElementById(`req-${req}`);
        if(element) {
            element.classList.remove('valid');
            element.innerHTML = '<i class="fas fa-circle"></i> ' + element.innerText.replace(/[✓✔✅]/g, '').trim();
        }
    });
}

function validatePassword(password) {
    return {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
}

function updatePasswordStrength() {
    const password = document.getElementById('new_password').value;
    const validation = validatePassword(password);
    
    // Update requirement list
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    
    if(reqLength) {
        if(validation.length) {
            reqLength.classList.add('valid');
            reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
        } else {
            reqLength.classList.remove('valid');
            reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
        }
    }
    
    if(reqUpper) {
        if(validation.uppercase) {
            reqUpper.classList.add('valid');
            reqUpper.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 uppercase letter (A-Z)';
        } else {
            reqUpper.classList.remove('valid');
            reqUpper.innerHTML = '<i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)';
        }
    }
    
    if(reqLower) {
        if(validation.lowercase) {
            reqLower.classList.add('valid');
            reqLower.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 lowercase letter (a-z)';
        } else {
            reqLower.classList.remove('valid');
            reqLower.innerHTML = '<i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)';
        }
    }
    
    if(reqNumber) {
        if(validation.number) {
            reqNumber.classList.add('valid');
            reqNumber.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 number (0-9)';
        } else {
            reqNumber.classList.remove('valid');
            reqNumber.innerHTML = '<i class="fas fa-circle"></i> At least 1 number (0-9)';
        }
    }
    
    if(reqSpecial) {
        if(validation.special) {
            reqSpecial.classList.add('valid');
            reqSpecial.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 special character (!@#$%^&*)';
        } else {
            reqSpecial.classList.remove('valid');
            reqSpecial.innerHTML = '<i class="fas fa-circle"></i> At least 1 special character (!@#$%^&*)';
        }
    }
    
    // Calculate strength percentage
    const validCount = Object.values(validation).filter(v => v === true).length;
    const strengthPercent = (validCount / 5) * 100;
    let strengthFill = document.querySelector('.strength-bar-fill');
    
    if(!strengthFill) {
        const strengthBar = document.querySelector('.strength-bar');
        if(strengthBar) {
            strengthFill = document.createElement('div');
            strengthFill.className = 'strength-bar-fill';
            strengthBar.appendChild(strengthFill);
        }
    }
    
    const strengthText = document.querySelector('.strength-text');
    
    if(strengthFill) {
        strengthFill.style.width = strengthPercent + '%';
        if(strengthPercent <= 25) {
            strengthFill.style.backgroundColor = '#ef4444';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Weak password</span>';
        } else if(strengthPercent <= 50) {
            strengthFill.style.backgroundColor = '#f59e0b';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Fair password</span>';
        } else if(strengthPercent <= 75) {
            strengthFill.style.backgroundColor = '#3b82f6';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #3b82f6;">Good password</span>';
        } else {
            strengthFill.style.backgroundColor = '#10b981';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Strong password</span>';
        }
    }
    
    // Check password match and enable/disable submit button
    checkPasswordMatch();
    
    const isStrong = Object.values(validation).every(v => v === true);
    const confirm = document.getElementById('confirm_password');
    const passwordsMatch = confirm && (password === confirm.value);
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    if(changePasswordBtn) {
        changePasswordBtn.disabled = !(isStrong && passwordsMatch && password.length > 0);
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const matchText = document.querySelector('.password-match');
    
    if(!password || !confirm || !matchText) return;
    
    const passwordValue = password.value;
    const confirmValue = confirm.value;
    
    if(confirmValue.length === 0) {
        matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
    } else if(passwordValue === confirmValue) {
        matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
    } else {
        matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
    }
    
    // Re-check button state
    const validation = validatePassword(passwordValue);
    const isStrong = Object.values(validation).every(v => v === true);
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    if(changePasswordBtn) {
        changePasswordBtn.disabled = !(isStrong && passwordValue === confirmValue && passwordValue.length > 0);
    }
}

// ===== IMAGE MODAL FUNCTIONS =====
function openImageModal() {
    document.getElementById('imageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function previewImage(input) {
    if(input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.getElementById('imagePreview');
            previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-format code input
document.querySelectorAll('.verify-code-input').forEach(input => {
    input.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('imageModal');
    if(event.target === modal) {
        closeImageModal();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if(alert.parentNode) alert.remove();
            }, 300);
        }, 3000);
    });
}, 1000);

// Form submission validation for password change
const passwordForm = document.getElementById('passwordForm');
if(passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const changePasswordCheckbox = document.getElementById('change_password_checkbox');
        
        if(changePasswordCheckbox && !changePasswordCheckbox.checked) {
            e.preventDefault();
            return false;
        }
        
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if(newPassword && confirmPassword) {
            const validation = validatePassword(newPassword.value);
            const isStrong = Object.values(validation).every(v => v === true);
            
            if(!isStrong) {
                e.preventDefault();
                alert('⚠️ Password Requirements:\n\n• At least 8 characters\n• At least 1 uppercase letter (A-Z)\n• At least 1 lowercase letter (a-z)\n• At least 1 number (0-9)\n• At least 1 special character (!@#$%^&*)');
                return false;
            }
            
            if(newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('❌ Passwords do not match!');
                return false;
            }
        }
        
        return true;
    });
}