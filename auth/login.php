<?php
session_start();
include("../config/database.php");

// Check if connection exists
if (!$conn) {
    die("Database connection failed. Please check your configuration.");
}

$error = '';
$success = '';

// Check if user is already logged in
if(isset($_SESSION['user'])) {
    // Redirect based on role
    switch($_SESSION['user']['role']){
        case 'Admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'Registrar':
            header("Location: ../registrar/dashboard.php");
            break;
        case 'Teacher':
            header("Location: ../teacher/dashboard.php");
            break;
        case 'Student':
            header("Location: ../student/dashboard.php");
            break;
        default:
            header("Location: ../student/dashboard.php");
            break;
    }
    exit();
}

// Check for success messages from URL parameters
if(isset($_GET['registered'])) {
    $success = "Registration successful! Please wait for admin approval.";
}
if(isset($_GET['pending'])) {
    $success = "Your account is pending approval. You will be notified once approved.";
}
if(isset($_GET['2fa_verified'])) {
    $success = "Two-factor authentication successful! Welcome back.";
}
if(isset($_GET['reset_success'])) {
    $success = "Password reset successful! You can now login with your new password.";
}

// Check for remember me cookie
$remember_email = isset($_COOKIE['user_email']) ? $_COOKIE['user_email'] : '';
$remember_checked = isset($_COOKIE['user_remember']) ? 'checked' : '';

// ========== NORMAL LOGIN ==========
if(isset($_POST['login'])){
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    if(empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Use prepared statement to prevent SQL injection
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        if($stmt->rowCount() > 0){
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password'])){
                
                // Check user status first
                if($user['status'] == 'pending') {
                    $error = "Your account is pending approval from the administrator. Please wait for approval.";
                } elseif($user['status'] == 'rejected') {
                    $reason = isset($user['rejection_reason']) ? " Reason: " . $user['rejection_reason'] : "";
                    $error = "Your account has been rejected." . $reason . " Please contact the administrator for more information.";
                } elseif($user['status'] == 'approved' || $user['status'] == 'active') {
                    
                    // ========== EMAIL VERIFICATION CHECK ==========
                    // Only Students need email verification. Admin, Registrar, and Teacher can bypass.
                    $needs_verification = false;
                    
                    if(isset($user['email_verified']) && $user['email_verified'] == 0) {
                        // Check if user role requires email verification
                        if($user['role'] == 'Student') {
                            $needs_verification = true;
                            $error = "Please verify your email address first. Check your inbox for the verification code. <a href='resend_code.php?email=" . urlencode($email) . "' style='color:#0B4F2E;'>Resend code</a>";
                        }
                        // Admin, Registrar, Teacher can log in without email verification
                    }
                    
                    // If no verification error, proceed to login
                    if(!$needs_verification) {
                        // Check if 2FA is enabled for this user (only for Admin and Registrar)
                        if(($user['role'] == 'Admin' || $user['role'] == 'Registrar') && isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                            // Generate 6-digit code
                            $code = sprintf("%06d", mt_rand(1, 999999));
                            
                            // Store in session with expiration (5 minutes)
                            $_SESSION['2fa_user_id'] = $user['id'];
                            $_SESSION['2fa_code'] = $code;
                            $_SESSION['2fa_expires'] = time() + 300; // 5 minutes
                            $_SESSION['2fa_email'] = $user['email'];
                            $_SESSION['2fa_name'] = $user['fullname'];
                            
                            // Send email with code
                            $to = $user['email'];
                            $subject = "Your 2FA Verification Code - PLSNHS";
                            
                            $message = "
                            <html>
                            <head>
                                <title>2FA Verification Code</title>
                            </head>
                            <body style='font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;'>
                                    <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>
                                        <div style='background: linear-gradient(135deg, #0B4F2E, #1a7a42); padding: 30px; text-align: center;'>
                                            <h1 style='color: white; margin: 0; font-size: 28px;'>PLSNHS</h1>
                                            <p style='color: #FFD700; margin: 5px 0 0; font-size: 16px;'>Two-Factor Authentication</p>
                                        </div>
                                        
                                        <div style='padding: 30px;'>
                                            <p style='font-size: 16px; color: #333;'>Hello <strong>" . htmlspecialchars($user['fullname']) . "</strong>,</p>
                                            <p style='font-size: 16px; color: #333;'>You have requested to log in to your account. Please use the following verification code:</p>
                                            
                                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 25px 0; border: 2px dashed #0B4F2E;'>
                                                <div style='font-size: 42px; font-weight: bold; color: #0B4F2E; letter-spacing: 8px; font-family: monospace;'>
                                                    " . $code . "
                                                </div>
                                                <p style='color: #666; margin-top: 10px;'>This code will expire in 5 minutes</p>
                                            </div>
                                            
                                            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                                                <p style='color: #856404; margin: 0; font-size: 14px;'>
                                                    <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our staff will never ask for your verification code.
                                                </p>
                                            </div>
                                            
                                            <p style='color: #666; font-size: 14px; margin-top: 25px;'>If you didn't attempt to log in, please ignore this email and contact the administrator immediately.</p>
                                        </div>
                                        
                                        <div style='background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                            <p style='color: #999; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " Placido L. Señor Senior High School. All rights reserved.</p>
                                            <p style='color: #999; font-size: 12px; margin: 5px 0 0;'>Langtad, City of Naga, Cebu</p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                                ";
                            
                            $headers = "MIME-Version: 1.0" . "\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                            $headers .= "From: PLSNHS 2FA <noreply@plsshs.edu.ph>" . "\r\n";
                            
                            if(mail($to, $subject, $message, $headers)) {
                                header("Location: verify_2fa.php");
                                exit();
                            } else {
                                $error = "Failed to send verification code. Please try again.";
                            }
                        } else {
                            // Normal login without 2FA
                            $_SESSION['user'] = $user;
                            
                            // Set remember me cookie (30 days)
                            if($remember) {
                                setcookie('user_email', $email, time() + (86400 * 30), "/");
                                setcookie('user_remember', 'true', time() + (86400 * 30), "/");
                            } else {
                                // Clear remember me cookies if not checked
                                if(isset($_COOKIE['user_email'])) {
                                    setcookie('user_email', '', time() - 3600, "/");
                                }
                                if(isset($_COOKIE['user_remember'])) {
                                    setcookie('user_remember', '', time() - 3600, "/");
                                }
                            }
                            
                            // Redirect based on role
                            switch($user['role']){
                                case 'Admin':
                                    header("Location: ../admin/dashboard.php");
                                    break;
                                case 'Registrar':
                                    header("Location: ../registrar/dashboard.php");
                                    break;
                                case 'Teacher':
                                    header("Location: ../teacher/dashboard.php");
                                    break;
                                case 'Student':
                                    header("Location: ../student/dashboard.php");
                                    break;
                                default:
                                    header("Location: ../student/dashboard.php");
                                    break;
                            }
                            exit();
                        }
                    }
                } else {
                    $error = "Account status unknown. Please contact administrator.";
                }
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "Email not registered!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EnrollSys | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, rgba(11, 79, 46, 0.6) 0%, rgba(26, 122, 66, 0.5) 100%);
            backdrop-filter: blur(5px);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Main Container */
        .login-wrapper {
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-panel {
            flex: 1;
            padding: 50px 40px;
            background: white;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .school-name h1 {
            font-size: 20px;
            color: #0B4F2E;
            margin-bottom: 5px;
        }

        .school-name p {
            font-size: 12px;
            color: #666;
        }

        h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .highlight {
            color: #0B4F2E;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            text-align: center;
            font-size: 14px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert i {
            font-size: 18px;
        }

        .alert a {
            color: #0B4F2E;
            text-decoration: underline;
        }

        /* Form Styles */
        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        .input-group label span {
            color: #dc3545;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 45px 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .input-wrapper input:focus {
            border-color: #0B4F2E;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: #0B4F2E;
        }

        /* Forgot Password Link */
        .forgot-password {
            text-align: right;
            margin: -15px 0 20px;
        }

        .forgot-password a {
            color: #666;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }

        .forgot-password a:hover {
            color: #0B4F2E;
            text-decoration: underline;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0B4F2E;
            cursor: pointer;
        }

        .remember-me label {
            color: #555;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0 15px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .signup-link {
            text-align: center;
            margin: 20px 0;
            color: #666;
            font-size: 14px;
        }

        .signup-link a {
            color: #0B4F2E;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-home:hover {
            color: #0B4F2E;
        }

        .form-footer {
            text-align: center;
            margin-top: 15px;
        }

        .info-panel {
            flex: 1;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            padding: 50px 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .info-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(45deg);
        }

        .motto {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        .motto h3 {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 4px;
            text-transform: uppercase;
            line-height: 1.4;
        }

        .motto h3 span {
            display: block;
            font-size: 22px;
            letter-spacing: 2px;
        }

        .school-level {
            text-align: center;
            margin: 30px 0 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .school-level h4 {
            font-size: 20px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .school-level p {
            font-size: 13px;
            opacity: 0.9;
        }

        .programs-list {
            list-style: none;
            margin: 25px 0;
            position: relative;
            z-index: 1;
        }

        .programs-list li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .programs-list li::before {
            content: "✓";
            font-size: 14px;
            opacity: 0.8;
            color: #FFD700;
        }

        .address {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            opacity: 0.8;
            line-height: 1.6;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            font-style: italic;
        }

        .address i {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-panel,
            .info-panel {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            .motto h3 {
                font-size: 24px;
            }
            
            .school-logo {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Left Panel - Login Form -->
            <div class="login-panel">
                <div class="logo-section">
                    <div class="school-logo">
                        <img src="../pictures/logo sa skwelahan.jpg" alt="School Logo" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%230B4F2E%22 /%3E%3Ctext x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EPLS%3C/text%3E%3C/svg%3E';">
                    </div>
                    <div class="school-name">
                        <h1>Placido L. Señor</h1>
                        <p>National High School</p>
                    </div>
                </div>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Normal Login Form -->
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="email">Email Address <span>*</span></label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($remember_email); ?>" required autofocus>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Password <span>*</span></label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- FORGOT PASSWORD LINK -->
                    <div class="forgot-password">
                        <a href="forgot_password.php">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember" <?php echo $remember_checked; ?>>
                        <label for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login">
                        <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> LOGIN
                    </button>
                </form>
                
                <div class="signup-link">
                    Don't have an account? <a href="register.php">Create Account</a>
                </div>

                <div class="form-footer">
                    <a href="../index.php" class="back-home">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
            
            <!-- Right Panel - Information -->
            <div class="info-panel">
                <div class="motto">
                    <h3>VIRTUS<br><span>EXCELLENTIA</span><br>SERVITIUM</h3>
                </div>
                
                <div class="school-level">
                    <h4>SENIOR HIGH SCHOOL</h4>
                    <p>Grades 11-12 · Academic & Technical-Vocational Tracks</p>
                </div>
                
                <ul class="programs-list">
                    <li><strong>STEM</strong> - Science, Technology, Engineering, Mathematics</li>
                    <li><strong>ABM</strong> - Accountancy, Business, Management</li>
                    <li><strong>HUMSS</strong> - Humanities and Social Sciences</li>
                    <li><strong>GAS</strong> - General Academic Strand</li>
                    <li><strong>ICT</strong> - Information and Communications Technology</li>
                    <li><strong>HE</strong> - Home Economics</li>
                    <li><strong>IA</strong> - Industrial Arts</li>
                </ul>
                
                <div class="address">
                    <i class="fas fa-map-marker-alt"></i> Langtad, City of Naga, Cebu<br>
                    <i class="fas fa-phone"></i> (032) 123-4567<br>
                    <i class="fas fa-envelope"></i> info@plsnhs.edu.ph
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility for login
        function togglePassword() {
            const passwordInput = document.getElementById('password');
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if(alert.parentNode) alert.remove();
                    }, 300);
                }, 3000);
            });
        }, 1000);
    </script>
</body>
</html>