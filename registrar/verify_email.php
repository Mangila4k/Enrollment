<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is logged in
if(!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$user_email = $_SESSION['user']['email'];
$user_name = $_SESSION['user']['fullname'];
$user_role = $_SESSION['user']['role'];
$error = '';
$success = '';

// Check if email is already verified
$stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user['email_verified'] == 1) {
    $_SESSION['success_message'] = "Your email is already verified!";
    
    // Redirect based on role
    switch($user_role) {
        case 'Admin':
            header("Location: ../admin/profile.php");
            break;
        case 'Registrar':
            header("Location: profile.php");
            break;
        case 'Teacher':
            header("Location: ../teacher/profile.php");
            break;
        case 'Student':
            header("Location: ../student/profile.php");
            break;
        default:
            header("Location: ../auth/login.php");
            break;
    }
    exit();
}

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    
    try {
        $stmt = $conn->prepare("SELECT id, verification_code, verification_expires FROM users WHERE id = ? AND verification_code = ? AND email_verified = 0");
        $stmt->execute([$user_id, $code]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $expires = new DateTime($user_data['verification_expires']);
            $now = new DateTime();
            
            if ($now > $expires) {
                $error = "Verification code has expired. Please request a new code.";
            } else {
                $update = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                $update->execute([$user_id]);
                
                // Update session
                $_SESSION['user']['email_verified'] = 1;
                
                $_SESSION['success_message'] = "Email verified successfully!";
                
                // Redirect based on role
                switch($user_role) {
                    case 'Admin':
                        header("Location: ../admin/profile.php");
                        break;
                    case 'Registrar':
                        header("Location: profile.php");
                        break;
                    case 'Teacher':
                        header("Location: ../teacher/profile.php");
                        break;
                    case 'Student':
                        header("Location: ../student/profile.php");
                        break;
                    default:
                        header("Location: ../auth/login.php");
                        break;
                }
                exit();
            }
        } else {
            $error = "Invalid verification code.";
        }
    } catch (PDOException $e) {
        $error = "Database error occurred.";
    }
}

// Handle resend code
if (isset($_GET['resend'])) {
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $user_id]);
    
    if (sendVerificationCode($user_email, $user_name, $verification_code)) {
        $success = "A new verification code has been sent to your email.";
    } else {
        $error = "Failed to send verification email. Please try again.";
    }
}

// Get role display name
$role_display = '';
switch($user_role) {
    case 'Admin': $role_display = 'Administrator'; break;
    case 'Registrar': $role_display = 'Registrar'; break;
    case 'Teacher': $role_display = 'Teacher'; break;
    case 'Student': $role_display = 'Student'; break;
    default: $role_display = 'User';
}

// Get back URL based on role
$back_url = '';
switch($user_role) {
    case 'Admin': $back_url = "../admin/profile.php"; break;
    case 'Registrar': $back_url = "profile.php"; break;
    case 'Teacher': $back_url = "../teacher/profile.php"; break;
    case 'Student': $back_url = "../student/profile.php"; break;
    default: $back_url = "../auth/login.php";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - PLSNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            max-width: 500px;
            width: 100%;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo i {
            font-size: 70px;
            color: #0B4F2E;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .code-input {
            font-size: 36px;
            letter-spacing: 12px;
            text-align: center;
            padding: 15px;
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-weight: bold;
            font-family: monospace;
        }
        
        .code-input:focus {
            border-color: #0B4F2E;
            outline: none;
        }
        
        button {
            background: #0B4F2E;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s;
        }
        
        button:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }
        
        .resend-link {
            margin-top: 20px;
            font-size: 14px;
        }
        
        .resend-link a {
            color: #0B4F2E;
            text-decoration: none;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .email-info {
            background: #f0f7f0;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 14px;
            color: #0B4F2E;
        }
        
        .back-link {
            margin-top: 20px;
        }
        
        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            color: #0B4F2E;
        }
        
        .role-badge {
            display: inline-block;
            background: #e8f4f8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #0B4F2E;
            margin-bottom: 15px;
        }
        
        .resend-timer {
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .resend-timer a {
            color: #0B4F2E;
            text-decoration: none;
        }
        
        .resend-timer a.disabled {
            color: #999;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-envelope"></i>
        </div>
        <h2>Verify Your Email</h2>
        <div class="subtitle">Please enter the verification code sent to your email</div>
        
        <div class="role-badge">
            <i class="fas fa-user"></i> <?php echo $role_display; ?>
        </div>
        
        <div class="email-info">
            <i class="fas fa-envelope"></i> Code sent to: <strong><?php echo htmlspecialchars($user_email); ?></strong>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="verifyForm">
            <input type="text" name="code" class="code-input" id="codeInput" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button type="submit" id="verifyBtn">Verify Email</button>
        </form>
        
        <div class="resend-link" id="resendLink">
            <a href="?resend=1" id="resendBtn"><i class="fas fa-redo"></i> Resend Verification Code</a>
        </div>
        <div class="resend-timer" id="timerDisplay" style="display: none;">
            <i class="fas fa-clock"></i> You can resend code in <span id="countdown">60</span> seconds
        </div>
        
        <div class="back-link">
            <a href="<?php echo $back_url; ?>"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        </div>
    </div>
    
    <script>
        // Auto-format code input
        const codeInput = document.getElementById('codeInput');
        if(codeInput) {
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
            
            // Auto-submit when 6 digits entered
            codeInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
        }
        
        // Prevent double submission
        const verifyForm = document.getElementById('verifyForm');
        const verifyBtn = document.getElementById('verifyBtn');
        
        if(verifyForm) {
            verifyForm.addEventListener('submit', function() {
                if(verifyBtn) {
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                }
            });
        }
        
        // Resend code with cooldown timer
        const resendBtn = document.getElementById('resendBtn');
        const timerDisplay = document.getElementById('timerDisplay');
        const countdownSpan = document.getElementById('countdown');
        let canResend = true;
        let timerInterval;
        
        // Check if resend was recently done (from session storage)
        const lastResend = sessionStorage.getItem('lastResendTime');
        if(lastResend) {
            const elapsed = Math.floor((Date.now() - parseInt(lastResend)) / 1000);
            if(elapsed < 60) {
                startCooldown(60 - elapsed);
            }
        }
        
        if(resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                if(!canResend) {
                    e.preventDefault();
                    return false;
                }
                
                // Start cooldown
                startCooldown(60);
                sessionStorage.setItem('lastResendTime', Date.now().toString());
            });
        }
        
        function startCooldown(seconds) {
            canResend = false;
            if(resendBtn) {
                resendBtn.style.pointerEvents = 'none';
                resendBtn.style.opacity = '0.5';
            }
            
            if(timerDisplay) {
                timerDisplay.style.display = 'block';
            }
            
            let remaining = seconds;
            if(countdownSpan) {
                countdownSpan.textContent = remaining;
            }
            
            if(timerInterval) clearInterval(timerInterval);
            
            timerInterval = setInterval(function() {
                remaining--;
                if(countdownSpan) {
                    countdownSpan.textContent = remaining;
                }
                
                if(remaining <= 0) {
                    clearInterval(timerInterval);
                    canResend = true;
                    if(resendBtn) {
                        resendBtn.style.pointerEvents = 'auto';
                        resendBtn.style.opacity = '1';
                    }
                    if(timerDisplay) {
                        timerDisplay.style.display = 'none';
                    }
                }
            }, 1000);
        }
    </script>
</body>
</html>