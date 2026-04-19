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
    
    // Auto-hide alerts
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
            newPassword.disabled = false;
            confirmPassword.disabled = false;
            resetPasswordBtn.disabled = false;
            newPassword.focus();
        } else {
            passwordFields.classList.remove('show');
            newPassword.disabled = true;
            confirmPassword.disabled = true;
            resetPasswordBtn.disabled = true;
            newPassword.value = '';
            confirmPassword.value = '';
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
        strengthBar.style.width = '33%';
        strengthBar.style.backgroundColor = '#ef4444';
        strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Password strength: Weak</span>`;
    } else if (strength <= 4) {
        strengthBar.style.width = '66%';
        strengthBar.style.backgroundColor = '#f59e0b';
        strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Password strength: Medium</span>`;
    } else {
        strengthBar.style.width = '100%';
        strengthBar.style.backgroundColor = '#10b981';
        strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Password strength: Strong</span>`;
    }
}

function checkPasswordMatch() {
    if (!newPassword || !confirmPassword) return;
    
    const password = newPassword.value;
    const confirm = confirmPassword.value;
    const matchText = document.getElementById('passwordMatch');

    if (confirm.length === 0) {
        matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
    } else if (password === confirm) {
        matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
    } else {
        matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
    }
}

// Add event listeners for password fields
if (newPassword) {
    newPassword.addEventListener('input', checkPasswordStrength);
    newPassword.addEventListener('input', checkPasswordMatch);
}

if (confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

// Form validation for edit student
const editForm = document.getElementById('editStudentForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        const firstname = this.querySelector('input[name="firstname"]').value.trim();
        const lastname = this.querySelector('input[name="lastname"]').value.trim();
        const email = this.querySelector('input[name="email"]').value.trim();
        const birthdate = this.querySelector('input[name="birthdate"]').value;
        const gender = this.querySelector('select[name="gender"]').value;
        
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

// Password reset form validation
const passwordForm = document.querySelector('.password-reset-section form');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        if (resetPasswordCheckbox && resetPasswordCheckbox.checked) {
            const password = newPassword.value;
            const confirm = confirmPassword.value;

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
        } else {
            e.preventDefault();
        }
    });
}

// Add hover effect to form cards
const formCards = document.querySelectorAll('.form-card');
formCards.forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.transition = 'transform 0.3s ease';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});