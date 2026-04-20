<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

$email = isset($_GET['email']) ? $_GET['email'] : (isset($_SESSION['temp_email']) ? $_SESSION['temp_email'] : '');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    try {
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ? AND email_verified = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $new_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
            $update->execute([$new_code, $expires, $user['id']]);
            
            if (sendVerificationCode($email, $user['fullname'], $new_code)) {
                $_SESSION['temp_email'] = $email;
                $message = "A new verification code has been sent to your email.";
            } else {
                $error = "Failed to send email. Please try again.";
            }
        } else {
            $error = "Email not found or already verified.";
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
    <title>Resend Code - PLSNHS</title>
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
        input, button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        button {
            background: #0B4F2E;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover { background: #1a7a42; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #0B4F2E; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><i class="fas fa-redo-alt"></i></div>
        <h2>Resend Verification Code</h2>
        
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <a href="verify_code.php?email=<?php echo urlencode($email); ?>" style="color: #0B4F2E;">Go to Verification →</a>
        <?php elseif($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
            <button type="submit">Resend Code</button>
        </form>
        
        <div class="back-link"><a href="login.php">Back to Login</a></div>
    </div>
</body>
</html>