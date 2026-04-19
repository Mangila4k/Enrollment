/* ========================================
   EDIT STUDENT PAGE JAVASCRIPT
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

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('new_password');
    const toggleBtn = document.querySelector('.toggle-password i');
    
    if (passwordInput && toggleBtn) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleBtn.className = 'fas fa-eye';
        }
    }
}

// Live preview update
const firstnameInput = document.getElementById('firstname');
const lastnameInput = document.getElementById('lastname');
const middlenameInput = document.getElementById('middlename');
const emailInput = document.getElementById('email');
const previewName = document.getElementById('previewName');
const previewEmail = document.getElementById('previewEmail');
const previewInitial = document.getElementById('previewInitial');

function updatePreview() {
    const firstname = firstnameInput ? firstnameInput.value.trim() : '';
    const lastname = lastnameInput ? lastnameInput.value.trim() : '';
    const middlename = middlenameInput ? middlenameInput.value.trim() : '';
    
    let fullname = firstname;
    if (middlename) {
        fullname += ' ' + middlename;
    }
    if (lastname) {
        fullname += ' ' + lastname;
    }
    
    if (fullname.trim() === '') {
        fullname = 'Student Name';
    }
    
    if (previewName) previewName.textContent = fullname;
    
    const initial = fullname.charAt(0).toUpperCase() || 'S';
    if (previewInitial) previewInitial.textContent = initial;

    const email = emailInput ? emailInput.value.trim() : '';
    if (previewEmail) {
        previewEmail.innerHTML = `<i class="fas fa-envelope"></i> ${email || 'student@email.com'}`;
    }
}

if (firstnameInput) firstnameInput.addEventListener('input', updatePreview);
if (lastnameInput) lastnameInput.addEventListener('input', updatePreview);
if (middlenameInput) middlenameInput.addEventListener('input', updatePreview);
if (emailInput) emailInput.addEventListener('input', updatePreview);

// Toggle password fields
const resetPasswordCheckbox = document.getElementById('reset_password_checkbox');
const passwordFields = document.getElementById('passwordFields');
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const resetPasswordBtn = document.getElementById('resetPasswordBtn');

if (resetPasswordCheckbox) {
    resetPasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.classList.add('show');
            if (newPassword) newPassword.disabled = false;
            if (confirmPassword) confirmPassword.disabled = false;
            if (resetPasswordBtn) resetPasswordBtn.disabled = false;
            if (newPassword) newPassword.focus();
        } else {
            passwordFields.classList.remove('show');
            if (newPassword) newPassword.disabled = true;
            if (confirmPassword) confirmPassword.disabled = true;
            if (resetPasswordBtn) resetPasswordBtn.disabled = true;
            if (newPassword) newPassword.value = '';
            if (confirmPassword) confirmPassword.value = '';
            resetPasswordStrength();
        }
    });
}

// Password strength checker
function resetPasswordStrength() {
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    
    if (strengthBar) strengthBar.style.width = '0';
    if (strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
    if (passwordMatch) passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
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

    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

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
    const matchText = document.getElementById('passwordMatch');

    if (confirm.length === 0) {
        if (matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
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

// Form validation for password reset
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        if (resetPasswordCheckbox && resetPasswordCheckbox.checked) {
            const password = newPassword ? newPassword.value : '';
            const confirm = confirmPassword ? confirmPassword.value : '';

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
            } else if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        } else {
            e.preventDefault();
        }
    });
}

// Form validation for edit student
const editForm = document.getElementById('editStudentForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        const firstname = document.getElementById('firstname')?.value.trim();
        const lastname = document.getElementById('lastname')?.value.trim();
        const email = document.getElementById('email')?.value.trim();
        const birthdate = document.getElementById('birthdate')?.value;
        const gender = document.getElementById('gender')?.value;

        if (!firstname) {
            e.preventDefault();
            alert('Please enter first name');
            return false;
        }

        if (!lastname) {
            e.preventDefault();
            alert('Please enter last name');
            return false;
        }

        if (!birthdate) {
            e.preventDefault();
            alert('Please select birthdate');
            return false;
        }

        // Validate age (15-30 years old)
        const birthDate = new Date(birthdate);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        if (age < 15 || age > 30) {
            e.preventDefault();
            alert('Student must be between 15-30 years old');
            return false;
        }

        if (!gender) {
            e.preventDefault();
            alert('Please select gender');
            return false;
        }

        if (!email) {
            e.preventDefault();
            alert('Please enter email address');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
    });
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