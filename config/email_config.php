<?php
// config/email_config.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// ========== USING YOUR WORKING GMAIL ==========
define('SMTP_USER', 'johnmicoleko@gmail.com');  // Your working Gmail
define('SMTP_PASS', 'ccczcydjccvnnwxo');  // The App Password that worked
// ===============================================

// Site URL for links in emails
define('SITE_URL', 'http://localhost/EnrollmentSystem');

function sendVerificationCode($email, $name, $code) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;
        
        // Sender & Recipient
        $mail->setFrom(SMTP_USER, 'PLSNHS Enrollment System');
        $mail->addAddress($email, $name);
        
        // Verification link
        $verify_link = SITE_URL . "/auth/verify_code.php?email=" . urlencode($email) . "&code=" . $code;
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = '🔐 Email Verification - PLSNHS Registration';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 550px; margin: 0 auto; padding: 20px; }
                .header { background: #0B4F2E; color: white; padding: 20px; text-align: center; }
                .code { font-size: 42px; font-weight: bold; color: #0B4F2E; padding: 20px; background: #f0f0f0; text-align: center; letter-spacing: 8px; margin: 20px 0; font-family: monospace; }
                .button { display: inline-block; padding: 12px 24px; background: #0B4F2E; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🏫 Placido L. Señor NHS</h2>
                    <p>Email Verification Required</p>
                </div>
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>Thank you for registering! Please verify your email using the code below:</p>
                <div class='code'>" . $code . "</div>
                <p>Or click the button below:</p>
                <div style='text-align: center;'>
                    <a href='$verify_link' class='button'>Verify Email</a>
                </div>
                <p>This code expires in <strong>24 hours</strong>.</p>
                <p>After verification, your account will be reviewed by the administrator.</p>
                <p>If you didn't create an account, please ignore this email.</p>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Placido L. Señor National High School</p>
                    <p>Langtad, City of Naga, Cebu</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nYour verification code is: $code\n\nVerify here: $verify_link\n\nThis code expires in 24 hours.\n\nBest regards,\nPLSNHS Administration";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Verification email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPasswordResetCode($email, $name, $code) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom(SMTP_USER, 'PLSNHS Enrollment System');
        $mail->addAddress($email, $name);
        
        $reset_link = SITE_URL . "/auth/reset_password.php?email=" . urlencode($email) . "&code=" . $code;
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Code - PLSNHS';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 550px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .code { font-size: 42px; font-weight: bold; color: #856404; padding: 20px; background: #fff3cd; text-align: center; letter-spacing: 8px; margin: 20px 0; font-family: monospace; }
                .button { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Password Reset Request</h2>
                </div>
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>We received a request to reset your password. Use the code below:</p>
                <div class='code'>" . $code . "</div>
                <p>Or click the button below:</p>
                <div style='text-align: center;'>
                    <a href='$reset_link' class='button'>Reset Password</a>
                </div>
                <p>This code expires in <strong>1 hour</strong>.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <p>Best regards,<br><strong>PLSNHS Administration</strong></p>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nYour password reset code is: $code\n\nReset here: $reset_link\n\nThis code expires in 1 hour.\n\nIf you didn't request this, ignore this email.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Password reset email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ========== ADD THIS FUNCTION FOR EMAIL CHANGE VERIFICATION ==========
function sendCustomEmail($to_email, $to_name, $subject, $html_content) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;
        
        // Sender & Recipient
        $mail->setFrom(SMTP_USER, 'PLSNHS Enrollment System');
        $mail->addAddress($to_email, $to_name);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_content;
        
        // Plain text alternative (optional)
        $mail->AltBody = strip_tags($html_content);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Custom email failed: " . $mail->ErrorInfo);
        return false;
    }
}
// =====================================================================
?>