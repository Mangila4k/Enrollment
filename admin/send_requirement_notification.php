<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are present
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
$student_email = isset($_POST['student_email']) ? $_POST['student_email'] : '';
$student_name = isset($_POST['student_name']) ? $_POST['student_name'] : '';
$requirement = isset($_POST['requirement']) ? $_POST['requirement'] : '';
$requirement_key = isset($_POST['requirement_key']) ? $_POST['requirement_key'] : '';

if(empty($student_id) || empty($student_email) || empty($requirement)){
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Include database connection
include("../config/database.php");

// Create notifications table if not exists (for student dashboard)
$create_notif_table = "
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('update','action','reminder','alert','message') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$conn->exec($create_notif_table);

// Function to add notification to database
function addNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

// Get admin name for signature
$admin_name = $_SESSION['user']['fullname'];

// ========== ADD NOTIFICATION TO STUDENT'S DASHBOARD ==========
$notification_title = "⚠️ Missing Requirement: " . $requirement;
$notification_message = "The school administration has notified you about the missing requirement: " . $requirement . ". Please submit this requirement as soon as possible to complete your enrollment process.";
$notification_link = "requirements.php";

$notif_added = addNotification($conn, $student_id, 'alert', $notification_title, $notification_message, $notification_link);

// ========== SEND EMAIL NOTIFICATION ==========
require_once '../config/email_config.php';

// Email subject
$subject = "Missing Requirement Notification - PLSNHS Enrollment";

// Email content
$email_message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Missing Requirement Notification</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 550px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .requirement-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .requirement-name {
            font-size: 18px;
            font-weight: bold;
            color: #856404;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #0B4F2E;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 15px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
        .warning {
            background: #fee2e2;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            color: #dc2626;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📋 Missing Requirement Notice</h1>
            <p>Placido L. Señor National High School</p>
        </div>
        <div class='content'>
            <p>Dear <strong>" . htmlspecialchars($student_name) . "</strong>,</p>
            <p>We noticed that the following requirement is still missing from your enrollment application:</p>
            
            <div class='requirement-box'>
                <div class='requirement-name'>
                    📄 " . htmlspecialchars($requirement) . "
                </div>
                <p style='margin-top: 10px; margin-bottom: 0;'>Please submit this requirement as soon as possible to complete your enrollment process.</p>
            </div>
            
            <p>You can submit your requirements by:</p>
            <ul>
                <li>Uploading them through your student portal</li>
                <li>Submitting physical copies to the Registrar's Office</li>
                <li>Emailing them to <strong>registrar@plsnhs.edu.ph</strong></li>
            </ul>
            
            <div style='text-align: center;'>
                <a href='http://localhost/EnrollmentSystem/student/requirements.php' class='button'>View Requirements</a>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Note:</strong> Your enrollment may be delayed if requirements are not completed on time.
            </div>
            
            <p>Best regards,<br>
            <strong>" . htmlspecialchars($admin_name) . "</strong><br>
            Administrator<br>
            PLSNHS Enrollment System</p>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " Placido L. Señor National High School</p>
            <p>Langtad, City of Naga, Cebu</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
";

// Send email
$emailSent = false;
if(function_exists('sendCustomEmail')) {
    $emailSent = sendCustomEmail($student_email, $student_name, $subject, $email_message);
}

// Log the notification
$log_file = __DIR__ . '/logs/notification_log.txt';
$log_dir = __DIR__ . '/logs';

if(!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

$log_entry = date('Y-m-d H:i:s') . " - Admin: {$admin_name} sent notification to Student ID: {$student_id} - Requirement: {$requirement}\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

if($notif_added) {
    echo json_encode(['success' => true, 'message' => 'Notification sent successfully to student dashboard and email']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save notification to database']);
}
?>