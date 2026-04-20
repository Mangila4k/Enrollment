<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'verify';

// Step 1: Verify reset code
if ($step == 'verify' && isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
        $reset_code = trim($_POST['reset_code']);
        
        // Debug - log the attempt
        error_log("Verifying code: $reset_code for email: $email");
        
        try {
            // First, check if the user exists and has a reset code
            $stmt = $conn->prepare("SELECT id, fullname, reset_code, reset_code_expires FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Debug - log what's in database
                error_log("Database reset_code: " . $user['reset_code']);
                error_log("Database expires: " . $user['reset_code_expires']);
                error_log("Current time: " . date('Y-m-d H:i:s'));
                
                // Check if code matches and not expired
                if ($user['reset_code'] == $reset_code) {
                    $expires = new DateTime($user['reset_code_expires']);
                    $now = new DateTime();
                    
                    if ($now > $expires) {
                        $error = "Reset code has expired. Please request a new code.";
                        error_log("Code expired: " . $now->format('Y-m-d H:i:s') . " > " . $expires->format('Y-m-d H:i:s'));
                    } else {
                        $_SESSION['reset_user_id'] = $user['id'];
                        $_SESSION['reset_user_name'] = $user['fullname'];
                        header("Location: reset_password.php?step=reset");
                        exit();
                    }
                } else {
                    $error = "Invalid reset code. Please try again.";
                    error_log("Code mismatch: Input: $reset_code, DB: " . $user['reset_code']);
                }
            } else {
                $error = "User not found.";
                error_log("User not found for email: $email");
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
            error_log("Database error: " . $e->getMessage());
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Reset Code - PLSNHS</title>
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
                max-width: 500px;
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
                margin-bottom: 12px;
            }

            .email-info {
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                margin-bottom: 32px;
                padding: 12px;
                background: #f3f4f6;
                border-radius: 12px;
                display: inline-block;
                width: 100%;
            }

            .email-info i {
                color: #0B4F2E;
                margin-right: 8px;
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

            .code-input-container {
                margin: 30px 0;
            }

            .code-input {
                font-size: 36px;
                letter-spacing: 12px;
                text-align: center;
                padding: 20px;
                width: 100%;
                border: 2px solid #e5e7eb;
                border-radius: 16px;
                font-weight: 600;
                transition: all 0.3s;
                font-family: 'Courier New', monospace;
            }

            .code-input:focus {
                border-color: #0B4F2E;
                outline: none;
                box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
            }

            .code-hint {
                text-align: center;
                font-size: 13px;
                color: #6b7280;
                margin-top: 12px;
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

            .links {
                text-align: center;
                margin-top: 28px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
                display: flex;
                justify-content: center;
                gap: 20px;
            }

            .links a {
                color: #6b7280;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .links a:hover {
                color: #0B4F2E;
                transform: translateX(-2px);
            }

            .resend-timer {
                text-align: center;
                margin-top: 20px;
                font-size: 13px;
                color: #9ca3af;
            }

            @media (max-width: 640px) {
                .container {
                    padding: 32px 24px;
                }
                
                h2 {
                    font-size: 24px;
                }
                
                .code-input {
                    font-size: 28px;
                    letter-spacing: 8px;
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Verify Reset Code</h2>
                <div class="email-info">
                    <i class="fas fa-envelope"></i>
                    Code sent to: <strong><?php echo htmlspecialchars($email); ?></strong>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="code-input-container">
                    <input 
                        type="text" 
                        name="reset_code" 
                        class="code-input" 
                        placeholder="000000" 
                        maxlength="6" 
                        pattern="[0-9]{6}" 
                        required 
                        autofocus
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                    >
                    <div class="code-hint">
                        <i class="fas fa-info-circle"></i> Enter the 6-digit code sent to your email
                    </div>
                </div>
                
                <button type="submit" name="verify_code">
                    <i class="fas fa-check-circle"></i>
                    Verify Code
                </button>
            </form>
            
            <div class="links">
                <a href="forgot_password.php">
                    <i class="fas fa-redo-alt"></i>
                    Request New Code
                </a>
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>

        <script>
            // Auto-format code input
            const codeInput = document.querySelector('.code-input');
            codeInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#e5e7eb';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Step 2: Reset password
elseif ($step == 'reset' && isset($_SESSION['reset_user_id'])) {
    $user_id = $_SESSION['reset_user_id'];
    $user_name = $_SESSION['reset_user_name'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if (empty($new_password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_code_expires = NULL WHERE id = ?");
                $update->execute([$hashed_password, $user_id]);
                
                // Clear all reset sessions
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_user_name']);
                
                $success = "Password reset successful! Redirecting to login...";
                header("refresh:2;url=login.php?reset_success=1");
            } catch (PDOException $e) {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password - PLSNHS</title>
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
                max-width: 500px;
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

            .welcome-message {
                text-align: center;
                color: #6b7280;
                margin-bottom: 32px;
                font-size: 14px;
                background: #f3f4f6;
                padding: 12px;
                border-radius: 12px;
            }

            .welcome-message i {
                color: #0B4F2E;
                margin-right: 8px;
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
            }

            .input-group label {
                display: block;
                margin-bottom: 8px;
                color: #374151;
                font-weight: 600;
                font-size: 14px;
            }

            .input-group label i {
                color: #0B4F2E;
                margin-right: 8px;
            }

            .input-wrapper {
                position: relative;
            }

            .input-wrapper i {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #9ca3af;
                font-size: 18px;
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

            .password-strength {
                margin-top: 12px;
                height: 6px;
                background: #e5e7eb;
                border-radius: 3px;
                overflow: hidden;
            }

            .strength-bar {
                height: 100%;
                width: 0;
                transition: all 0.3s;
            }

            .strength-bar.weak {
                width: 33.33%;
                background-color: #ef4444;
            }

            .strength-bar.medium {
                width: 66.66%;
                background-color: #f59e0b;
            }

            .strength-bar.strong {
                width: 100%;
                background-color: #10b981;
            }

            .strength-text {
                font-size: 12px;
                margin-top: 6px;
                color: #6b7280;
            }

            .match-message {
                font-size: 12px;
                margin-top: 8px;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .match-message.success {
                color: #10b981;
            }

            .match-message.error {
                color: #ef4444;
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
                margin-top: 10px;
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

            .password-requirements {
                background: #f9fafb;
                padding: 12px;
                border-radius: 8px;
                margin-top: 12px;
                font-size: 12px;
                color: #6b7280;
            }

            .password-requirements ul {
                list-style: none;
                padding-left: 0;
            }

            .password-requirements li {
                margin: 4px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .password-requirements li i {
                font-size: 10px;
            }

            @media (max-width: 640px) {
                .container {
                    padding: 32px 24px;
                }
                
                h2 {
                    font-size: 24px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2>Create New Password</h2>
                <div class="welcome-message">
                    <i class="fas fa-user-check"></i>
                    Hello, <strong><?php echo htmlspecialchars($user_name); ?></strong>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="resetForm">
                <div class="input-group">
                    <label><i class="fas fa-key"></i> New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <div class="match-message" id="matchMessage"></div>
                </div>

                <div class="password-requirements">
                    <strong><i class="fas fa-shield-alt"></i> Password Requirements:</strong>
                    <ul>
                        <li id="reqLength"><i class="fas fa-circle"></i> At least 6 characters</li>
                        <li id="reqLower"><i class="fas fa-circle"></i> At least one lowercase letter</li>
                        <li id="reqUpper"><i class="fas fa-circle"></i> At least one uppercase letter</li>
                        <li id="reqNumber"><i class="fas fa-circle"></i> At least one number</li>
                    </ul>
                </div>
                
                <button type="submit" name="reset_password">
                    <i class="fas fa-save"></i>
                    Reset Password
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
        
        <script>
            const password = document.getElementById('new_password');
            const confirm = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const matchMessage = document.getElementById('matchMessage');
            
            // Requirements elements
            const reqLength = document.getElementById('reqLength');
            const reqLower = document.getElementById('reqLower');
            const reqUpper = document.getElementById('reqUpper');
            const reqNumber = document.getElementById('reqNumber');
            
            function checkRequirements(value) {
                const hasLength = value.length >= 6;
                const hasLower = /[a-z]/.test(value);
                const hasUpper = /[A-Z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                
                // Update requirement icons
                updateRequirement(reqLength, hasLength);
                updateRequirement(reqLower, hasLower);
                updateRequirement(reqUpper, hasUpper);
                updateRequirement(reqNumber, hasNumber);
                
                return { hasLength, hasLower, hasUpper, hasNumber };
            }
            
            function updateRequirement(element, isValid) {
                if (isValid) {
                    element.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> ' + element.textContent.trim();
                    element.style.color = '#10b981';
                } else {
                    element.innerHTML = '<i class="fas fa-circle" style="color: #9ca3af;"></i> ' + element.textContent.trim();
                    element.style.color = '#6b7280';
                }
            }
            
            function checkStrength() {
                const value = password.value;
                const requirements = checkRequirements(value);
                
                let strength = 0;
                if (requirements.hasLength) strength++;
                if (requirements.hasLower) strength++;
                if (requirements.hasUpper) strength++;
                if (requirements.hasNumber) strength++;
                
                strengthBar.classList.remove('weak', 'medium', 'strong');
                
                if (value.length === 0) {
                    strengthBar.style.width = '0';
                    strengthText.textContent = '';
                } else if (strength <= 2) {
                    strengthBar.classList.add('weak');
                    strengthText.textContent = '⚠️ Weak password';
                    strengthText.style.color = '#ef4444';
                } else if (strength === 3) {
                    strengthBar.classList.add('medium');
                    strengthText.textContent = '👍 Medium password';
                    strengthText.style.color = '#f59e0b';
                } else {
                    strengthBar.classList.add('strong');
                    strengthText.textContent = '✅ Strong password';
                    strengthText.style.color = '#10b981';
                }
                
                checkMatch();
            }
            
            function checkMatch() {
                if (confirm.value.length === 0) {
                    matchMessage.innerHTML = '';
                    matchMessage.className = 'match-message';
                } else if (password.value === confirm.value) {
                    matchMessage.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                    matchMessage.className = 'match-message success';
                } else {
                    matchMessage.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                    matchMessage.className = 'match-message error';
                }
            }
            
            password.addEventListener('input', checkStrength);
            confirm.addEventListener('input', checkMatch);
            
            // Initial check for password field
            checkStrength();
        </script>
    </body>
    </html>
    <?php
    exit();
} else {
    header("Location: forgot_password.php");
    exit();
}
?>