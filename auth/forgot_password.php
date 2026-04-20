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
    <title>Forgot Password - PLSNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background shapes */
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -150px;
            right: -150px;
            animation: float 20s infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 25s infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(30px, 30px) rotate(180deg); }
        }

        .container {
            background: rgba(255, 255, 255, 0.98);
            max-width: 480px;
            width: 100%;
            border-radius: 32px;
            padding: 48px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            font-size: 40px;
            color: white;
        }

        h2 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert i {
            font-size: 20px;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

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

        input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            outline: none;
        }

        input:focus {
            border-color: #0B4F2E;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        input:hover {
            border-color: #1a7a42;
        }

        button {
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
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button:hover::before {
            width: 300px;
            height: 300px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(11, 79, 46, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
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
            transform: translateX(-4px);
        }

        /* Loading state */
        button.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        button.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 640px) {
            .container {
                padding: 32px 24px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            
            .logo-icon i {
                font-size: 30px;
            }
        }

        /* Features section */
        .features {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .feature {
            text-align: center;
            flex: 1;
        }

        .feature i {
            font-size: 20px;
            color: #0B4F2E;
            margin-bottom: 6px;
            display: inline-block;
        }

        .feature span {
            font-size: 11px;
            color: #6b7280;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-key"></i>
            </div>
            <h2>Forgot Password?</h2>
            <div class="subtitle">
                Don't worry! It happens to the best of us.<br>
                Enter your email and we'll send you a reset code.
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
                    placeholder="Enter your registered email" 
                    required 
                    autofocus
                    autocomplete="email"
                >
            </div>
            
            <button type="submit" id="submitBtn">
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
        const emailInput = document.querySelector('input[name="email"]');

        // Add loading state on form submit
        form.addEventListener('submit', function(e) {
            if (emailInput.value.trim() === '' || !emailInput.checkValidity()) {
                e.preventDefault();
                return;
            }
            
            submitBtn.classList.add('loading');
            submitBtn.querySelector('i').className = 'fas fa-spinner';
            submitBtn.querySelector('span').textContent = 'Sending...';
        });

        // Real-time email validation
        emailInput.addEventListener('input', function() {
            if (this.value.trim() !== '' && this.checkValidity()) {
                this.style.borderColor = '#10b981';
            } else if (this.value.trim() !== '') {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });

        // Remove alert after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>