<?php
/**
 * Two-Factor Authentication Functions
 * Description: Handles 2FA verification for admin accounts
 * NOTE: This file does NOT start sessions - sessions should be started in the main file
 */

function start2FAVerification($user_id, $email, $fullname, $action, $redirect_url) {
    global $conn;
    
    // Generate 6-digit verification code
    $code = sprintf("%06d", mt_rand(1, 999999));
    
    // Store in session with expiration (5 minutes)
    $_SESSION['2fa_user_id'] = $user_id;
    $_SESSION['2fa_code'] = $code;
    $_SESSION['2fa_expires'] = time() + 300; // 5 minutes
    $_SESSION['2fa_email'] = $email;
    $_SESSION['2fa_name'] = $fullname;
    $_SESSION['2fa_action'] = $action;
    $_SESSION['2fa_redirect'] = $redirect_url;
    
    // Send email with verification code
    $to = $email;
    $subject = "2FA Verification Code - EnrollSys";
    
    $message = "
    <html>
    <head>
        <title>2FA Verification Code</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0B4F2E, #1a7a42); padding: 30px; text-align: center; }
            .header h1 { color: white; margin: 0; font-size: 28px; }
            .header p { color: #FFD700; margin: 5px 0 0; font-size: 16px; }
            .content { padding: 30px; }
            .code-box { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 25px 0; border: 2px dashed #0B4F2E; }
            .code { font-size: 42px; font-weight: bold; color: #0B4F2E; letter-spacing: 8px; font-family: monospace; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0; font-size: 12px; color: #999; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>EnrollSys</h1>
                <p>Two-Factor Authentication</p>
            </div>
            
            <div class='content'>
                <p style='font-size: 16px; color: #333;'>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
                <p style='font-size: 16px; color: #333;'>You have requested to perform a sensitive action on your account. Please use the following verification code:</p>
                
                <div class='code-box'>
                    <div class='code'>" . $code . "</div>
                    <p style='color: #666; margin-top: 10px;'>This code will expire in 5 minutes</p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our staff will never ask for your verification code.
                </div>
                
                <p style='color: #666; font-size: 14px; margin-top: 25px;'>If you didn't request this, please ignore this email and contact the administrator immediately.</p>
            </div>
            
            <div class='footer'>
                <p>&copy; " . date('Y') . " Placido L. Señor Senior High School. All rights reserved.</p>
                <p>Langtad, City of Naga, Cebu</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: EnrollSys 2FA <noreply@plsshs.edu.ph>" . "\r\n";
    
    if(mail($to, $subject, $message, $headers)) {
        header("Location: ../auth/verify_2fa.php");
        exit();
    } else {
        return "Failed to send verification code. Please try again.";
    }
}

function is2FAEnabled($conn, $user_id) {
    $stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['two_factor_enabled'] == 1;
}

function enable2FA($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_last_used = NOW() WHERE id = ?");
    return $stmt->execute([$user_id]);
}

function disable2FA($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0 WHERE id = ?");
    return $stmt->execute([$user_id]);
}

function verify2FACode($code) {
    if(isset($_SESSION['2fa_code']) && isset($_SESSION['2fa_expires'])) {
        if(time() > $_SESSION['2fa_expires']) {
            return false; // Code expired
        }
        
        if($_SESSION['2fa_code'] == $code) {
            return true;
        }
    }
    return false;
}

function clear2FASession() {
    unset($_SESSION['2fa_user_id']);
    unset($_SESSION['2fa_code']);
    unset($_SESSION['2fa_expires']);
    unset($_SESSION['2fa_email']);
    unset($_SESSION['2fa_name']);
    unset($_SESSION['2fa_action']);
    unset($_SESSION['2fa_redirect']);
}
?>