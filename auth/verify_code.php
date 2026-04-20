<?php
session_start();
require_once '../config/database.php';

$error = '';
$success = '';

// Get email from URL or session
$email = isset($_GET['email']) ? $_GET['email'] : (isset($_SESSION['temp_email']) ? $_SESSION['temp_email'] : '');

// If code is in URL, auto-verify
if (isset($_GET['code']) && isset($_GET['email'])) {
    $email = $_GET['email'];
    $code = $_GET['code'];
    
    try {
        $stmt = $conn->prepare("SELECT id, fullname, verification_code, verification_expires FROM users WHERE email = ? AND verification_code = ? AND email_verified = 0");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $expires = new DateTime($user['verification_expires']);
            $now = new DateTime();
            
            if ($now > $expires) {
                $error = "Verification code has expired. Please request a new code.";
            } else {
                $update = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                $update->execute([$user['id']]);
                $success = "✅ Email verified successfully! Your account is now pending admin approval.";
                unset($_SESSION['temp_email']);
            }
        } else {
            $error = "Invalid verification code.";
        }
    } catch (PDOException $e) {
        $error = "Database error occurred.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $code = $_POST['code'];
    
    try {
        $stmt = $conn->prepare("SELECT id, fullname, verification_code, verification_expires FROM users WHERE email = ? AND verification_code = ? AND email_verified = 0");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $expires = new DateTime($user['verification_expires']);
            $now = new DateTime();
            
            if ($now > $expires) {
                $error = "Verification code has expired. Please request a new code.";
            } else {
                $update = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                $update->execute([$user['id']]);
                $success = "✅ Email verified successfully! Your account is now pending admin approval.";
                unset($_SESSION['temp_email']);
            }
        } else {
            $error = "Invalid verification code.";
        }
    } catch (PDOException $e) {
        $error = "Database error occurred.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email - PLSNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            max-width: 450px;
            width: 100%;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .logo i { font-size: 60px; color: #0B4F2E; margin-bottom: 20px; }
        h2 { color: #333; margin-bottom: 10px; }
        .alert { padding: 12px; border-radius: 8px; margin: 20px 0; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .code-input {
            font-size: 32px;
            letter-spacing: 10px;
            text-align: center;
            padding: 15px;
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        button {
            background: #0B4F2E;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            width: 100%;
        }
        button:hover { background: #1a7a42; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #0B4F2E; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><i class="fas fa-envelope"></i></div>
        <h2>Verify Your Email</h2>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <a href="login.php" style="display: inline-block; margin-top: 10px; color: #0B4F2E;">Go to Login →</a>
        <?php elseif($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <a href="resend_code.php?email=<?php echo urlencode($email); ?>" style="color: #0B4F2E;">Resend Code</a><br>
            <a href="login.php" style="color: #0B4F2E;">Back to Login</a>
        <?php else: ?>
            <p>Enter the 6-digit code sent to:<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="text" name="code" class="code-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                <button type="submit">Verify Email</button>
            </form>
            <div class="back-link"><a href="resend_code.php?email=<?php echo urlencode($email); ?>">Resend Code</a> | <a href="login.php">Back to Login</a></div>
        <?php endif; ?>
    </div>
</body>
</html>