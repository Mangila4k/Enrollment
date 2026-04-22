<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, fullname, email_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if ($user['email_verified'] == 0) {
                    $error = "Please verify your email first. Check your inbox for the verification code.";
                } else {
                    // Generate reset code
                    $reset_code = sprintf("%06d", mt_rand(100000, 999999));
                    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $update = $conn->prepare("UPDATE users SET reset_code = ?, reset_code_expires = ? WHERE id = ?");
                    $update->execute([$reset_code, $reset_expires, $user['id']]);
                    
                    $_SESSION['reset_email'] = $email;
                    
                    // Send reset code email
                    if (sendPasswordResetCode($email, $user['fullname'], $reset_code)) {
                        header("Location: reset_password.php?step=verify");
                        exit();
                    } else {
                        $error = "Failed to send reset code. Please try again.";
                    }
                }
            } else {
                // Don't reveal if email doesn't exist for security
                $success = "If your email is registered and verified, you will receive a reset code.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PLS NHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, rgba(11, 79, 46, 0.95) 0%, rgba(26, 122, 66, 0.92) 50%, rgba(11, 79, 46, 0.95) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.98);
            max-width: 480px;
            width: 100%;
            border-radius: 32px;
            padding: 48px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            border-radius: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(11, 79, 46, 0.2);
        }

        .logo-icon i {
            font-size: 40px;
            color: white;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Alert Messages */
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin: 24px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert i {
            font-size: 18px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #ecfdf5;
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        /* Input Group */
        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
            transition: all 0.3s;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            outline: none;
            background: #ffffff;
        }

        .input-group input:focus {
            border-color: #0B4F2E;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .input-group input:focus + i {
            color: #0B4F2E;
        }

        .input-group input:hover {
            border-color: #1a7a42;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 79, 46, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .submit-btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Back Link */
        .back-link {
            text-align: center;
            margin-bottom: 28px;
        }

        .back-link a {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #0B4F2E;
            transform: translateX(-3px);
        }

        /* Features */
        .features {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .feature {
            flex: 1;
            text-align: center;
        }

        .feature i {
            font-size: 20px;
            color: #0B4F2E;
            margin-bottom: 8px;
            display: inline-block;
        }

        .feature span {
            font-size: 11px;
            color: #6b7280;
            display: block;
            font-weight: 500;
        }

        /* Field Error */
        .field-error {
            color: #dc2626;
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .container {
                padding: 32px 24px;
                border-radius: 24px;
            }

            h1 {
                font-size: 24px;
            }

            .logo-icon {
                width: 65px;
                height: 65px;
            }

            .logo-icon i {
                font-size: 32px;
            }

            .features {
                flex-direction: column;
                gap: 15px;
            }

            .feature {
                display: flex;
                align-items: center;
                gap: 12px;
                text-align: left;
            }

            .feature i {
                margin-bottom: 0;
                width: 30px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 28px 20px;
            }

            .submit-btn {
                padding: 12px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Forgot Password?</h1>
            <div class="subtitle">
                Enter your email address and we'll send you a password reset code.
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="forgotForm">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input 
                    type="email" 
                    name="email" 
                    id="emailInput"
                    placeholder="Enter your registered email address" 
                    required 
                    autofocus
                    autocomplete="email"
                >
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                <span>Send Reset Code</span>
            </button>
        </form>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>
        </div>

        <div class="features">
            <div class="feature">
                <i class="fas fa-clock"></i>
                <span>Valid for 1 hour</span>
            </div>
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <span>Secure & Encrypted</span>
            </div>
            <div class="feature">
                <i class="fas fa-envelope"></i>
                <span>Check your inbox</span>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');
        const emailInput = document.getElementById('emailInput');

        // Add loading state on form submit
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();
            const emailPattern = /^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/;
            
            if (email === '') {
                e.preventDefault();
                showFieldError(emailInput, 'Please enter your email address');
                return;
            }
            
            if (!emailPattern.test(email)) {
                e.preventDefault();
                showFieldError(emailInput, 'Please enter a valid email address');
                return;
            }
            
            submitBtn.classList.add('loading');
            submitBtn.querySelector('i').className = 'fas fa-spinner';
            submitBtn.querySelector('span').textContent = 'Sending...';
        });

        // Show field error
        function showFieldError(field, message) {
            field.style.borderColor = '#dc2626';
            field.style.backgroundColor = '#fef2f2';
            
            // Remove existing error message
            const existingError = field.parentElement.querySelector('.field-error');
            if (existingError) existingError.remove();
            
            // Add error message
            const errorMsg = document.createElement('div');
            errorMsg.className = 'field-error';
            errorMsg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
            field.parentElement.appendChild(errorMsg);
            
            setTimeout(() => {
                field.style.borderColor = '#e5e7eb';
                field.style.backgroundColor = '#ffffff';
                if (errorMsg) errorMsg.remove();
            }, 3000);
        }

        // Real-time email validation
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            const emailPattern = /^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/;
            
            if (email !== '' && emailPattern.test(email)) {
                this.style.borderColor = '#10b981';
                this.style.backgroundColor = '#f0fdf4';
            } else if (email !== '') {
                this.style.borderColor = '#dc2626';
                this.style.backgroundColor = '#fef2f2';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.backgroundColor = '#ffffff';
            }
        });

        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email !== '' && !/^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/.test(email)) {
                showFieldError(this, 'Please enter a valid email address');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.3s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 4000);
            });
        }, 1000);
    </script>
</body>
</html>