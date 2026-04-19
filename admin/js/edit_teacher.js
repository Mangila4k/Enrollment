/* ========================================
   EDIT TEACHER PAGE JAVASCRIPT
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

// Toggle password fields
const changePasswordCheckbox = document.getElementById('change_password');
const passwordFields = document.getElementById('passwordFields');
const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');

if (changePasswordCheckbox) {
    changePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.classList.add('show');
            newPasswordInput.disabled = false;
            confirmPasswordInput.disabled = false;
            newPasswordInput.focus();
        } else {
            passwordFields.classList.remove('show');
            newPasswordInput.disabled = true;
            confirmPasswordInput.disabled = true;
            newPasswordInput.value = '';
            confirmPasswordInput.value = '';
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
    if (!newPasswordInput) return;
    
    const password = newPasswordInput.value;
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
    if (!newPasswordInput || !confirmPasswordInput) return;
    
    const password = newPasswordInput.value;
    const confirm = confirmPasswordInput.value;
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
if (newPasswordInput) {
    newPasswordInput.addEventListener('input', checkPasswordStrength);
    newPasswordInput.addEventListener('input', checkPasswordMatch);
}

if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
}

// Form validation
const teacherForm = document.getElementById('teacherForm');
if (teacherForm) {
    teacherForm.addEventListener('submit', function(e) {
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        
        if (!fullname) {
            e.preventDefault();
            alert('Please enter full name');
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
        
        if (changePasswordCheckbox && changePasswordCheckbox.checked) {
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;

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
        }
    });
}

// Add hover effect to form card
const formCard = document.querySelector('.form-card');
if (formCard) {
    formCard.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.transition = 'transform 0.3s ease';
    });
    formCard.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
}

// Copy email functionality
const emailSpan = document.querySelector('.info-details p:first-child span:first-child');
if (emailSpan && teacherData.email) {
    emailSpan.style.cursor = 'pointer';
    emailSpan.title = 'Click to copy email';
    emailSpan.addEventListener('click', () => {
        navigator.clipboard.writeText(teacherData.email).then(() => {
            showToast('Email copied to clipboard!', 'success');
        });
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