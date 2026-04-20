<?php
session_start();
include("../config/database.php");
include("../includes/2fa_functions.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user']['id'];
$admin_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get admin details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Admin'";
$stmt = $conn->prepare($query);
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if required columns exist
try {
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
    }
    
    $check_email_verified = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
    if($check_email_verified->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
    }
    
    // Add pending email columns for email change verification
    $check_pending_email = $conn->query("SHOW COLUMNS FROM users LIKE 'pending_email'");
    if($check_pending_email->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL");
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email_code VARCHAR(10) DEFAULT NULL");
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email_expires DATETIME DEFAULT NULL");
    }
} catch(PDOException $e) {
    // Column might already exist
}

// Handle send verification email for email change
if(isset($_POST['send_email_verification'])) {
    $new_email = trim($_POST['new_email']);
    
    if(empty($new_email)) {
        $error_message = "Please enter an email address.";
    } elseif(!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email already exists for another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->execute([$new_email, $admin_id]);
        
        if($check_email->rowCount() > 0) {
            $error_message = "Email address already registered to another user.";
        } else {
            require_once '../config/email_config.php';
            
            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store pending email and code in database
            $update = $conn->prepare("UPDATE users SET pending_email = ?, pending_email_code = ?, pending_email_expires = ? WHERE id = ?");
            $update->execute([$new_email, $verification_code, $expires, $admin_id]);
            
            // Send verification email to NEW email address
            $subject = "Email Change Verification - PLSNHS";
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Email Change Verification</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 550px; margin: 0 auto; padding: 20px; }
                    .header { background: #0B4F2E; color: white; padding: 20px; text-align: center; }
                    .code { font-size: 42px; font-weight: bold; color: #0B4F2E; padding: 20px; background: #f0f0f0; text-align: center; letter-spacing: 8px; margin: 20px 0; font-family: monospace; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Email Change Request</h2>
                    </div>
                    <p>Hello <strong>" . htmlspecialchars($admin['fullname']) . "</strong>,</p>
                    <p>You requested to change your email address to: <strong>$new_email</strong></p>
                    <p>Please enter the verification code below to confirm this change:</p>
                    <div class='code'>$verification_code</div>
                    <p>This code will expire in <strong>1 hour</strong>.</p>
                    <p>If you didn't request this change, please ignore this email.</p>
                </div>
            </body>
            </html>
            ";
            
            $mailSent = sendCustomEmail($new_email, $admin['fullname'], $subject, $message);
            
            if($mailSent) {
                $_SESSION['pending_email_change'] = true;
                $_SESSION['success_message'] = "A verification code has been sent to the new email address: $new_email. Please check your inbox to confirm the change.";
                header("Location: profile.php");
                exit();
            } else {
                $error_message = "Failed to send verification email. Please try again.";
            }
        }
    }
}

// Handle verify new email code
if(isset($_POST['verify_new_email'])) {
    $verification_code = trim($_POST['verification_code']);
    
    // Get pending email info
    $stmt = $conn->prepare("SELECT pending_email, pending_email_code, pending_email_expires FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($pending['pending_email'] && $pending['pending_email_code']) {
        $expires = new DateTime($pending['pending_email_expires']);
        $now = new DateTime();
        
        if($now > $expires) {
            $error_message = "Verification code has expired. Please request a new email change.";
            // Clear pending email
            $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $clear->execute([$admin_id]);
        } elseif($pending['pending_email_code'] == $verification_code) {
            // Update email to new email
            $new_email = $pending['pending_email'];
            $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $update->execute([$new_email, $admin_id]);
            
            // Update session
            $_SESSION['user']['email'] = $new_email;
            
            $_SESSION['success_message'] = "Email changed successfully to: $new_email";
            header("Location: profile.php");
            exit();
        } else {
            $error_message = "Invalid verification code. Please try again.";
        }
    } else {
        $error_message = "No pending email change request found.";
    }
}

// Handle cancel email change
if(isset($_POST['cancel_email_change'])) {
    $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
    $clear->execute([$admin_id]);
    unset($_SESSION['pending_email_change']);
    $_SESSION['success_message'] = "Email change request cancelled.";
    header("Location: profile.php");
    exit();
}

// Handle send verification email for initial email verification
if(isset($_POST['send_verification'])) {
    require_once '../config/email_config.php';
    
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $admin_id]);
    
    if(sendVerificationCode($admin['email'], $admin['fullname'], $verification_code)) {
        $_SESSION['temp_email'] = $admin['email'];
        $_SESSION['success_message'] = "A verification code has been sent to your email. Please check your inbox.";
        header("Location: verify_email.php");
        exit();
    } else {
        $error_message = "Failed to send verification email. Please try again.";
    }
}

// Handle profile picture upload
if(isset($_POST['upload_profile_pic'])) {
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            $upload_dir = "../uploads/profile_pictures/";
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = "admin_" . $admin_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            if(!empty($admin['profile_picture']) && file_exists("../" . $admin['profile_picture'])) {
                unlink("../" . $admin['profile_picture']);
            }
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if($update_stmt->execute([$db_path, $admin_id])) {
                    $_SESSION['user']['profile_picture'] = $db_path;
                    $_SESSION['success_message'] = "Profile picture updated successfully!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $error_message = "Failed to update database.";
                }
            } else {
                $error_message = "Failed to upload image.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else {
        $error_message = "Please select an image file.";
    }
}

// Handle remove profile picture
if(isset($_GET['remove_pic'])) {
    if(!empty($admin['profile_picture']) && file_exists("../" . $admin['profile_picture'])) {
        unlink("../" . $admin['profile_picture']);
    }
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
    if($update_stmt->execute([$admin_id])) {
        $_SESSION['user']['profile_picture'] = null;
        $_SESSION['success_message'] = "Profile picture removed successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $error_message = "Failed to remove profile picture.";
    }
}

// Handle 2FA toggle
if(isset($_POST['toggle_2fa'])) {
    $action = $_POST['action'];
    
    if($action == 'enable') {
        start2FAVerification(
            $admin_id,
            $admin['email'],
            $admin['fullname'],
            'enable_2fa',
            'profile.php'
        );
    } elseif($action == 'disable') {
        start2FAVerification(
            $admin_id,
            $admin['email'],
            $admin['fullname'],
            'disable_2fa',
            'profile.php'
        );
    }
}

// Handle 2FA verification completion
if(isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true) {
    if(time() - $_SESSION['2fa_verified_time'] < 600) {
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
    }
}

// Handle profile update (name and ID only, email handled separately)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    $errors = [];
    
    if(empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
        $check_id->execute([$id_number, $admin_id]);
        
        if($check_id->rowCount() > 0) {
            $errors[] = "ID number already exists for another user";
        }
    }
    
    if(empty($errors)) {
        $update_query = "UPDATE users SET fullname = ?, id_number = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([$fullname, $id_number, $admin_id])) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['id_number'] = $id_number;
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Database error occurred.";
        }
    }
    
    if(!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}

// Handle password change
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if(empty($current_password)) {
        $errors[] = "Current password is required";
    } else {
        if(!password_verify($current_password, $admin['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    if(empty($new_password)) {
        $errors[] = "New password is required";
    } elseif(strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    
    if($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if(empty($errors)) {
        if($admin['two_factor_enabled'] == 1) {
            start2FAVerification(
                $admin_id,
                $admin['email'],
                $admin['fullname'],
                'password_change',
                'profile.php'
            );
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            
            if($update_stmt->execute([$hashed_password, $admin_id])) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Database error occurred.";
            }
        }
    }
    
    if(!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}

// Check for pending email change
$has_pending_email = false;
$pending_email = '';
$stmt = $conn->prepare("SELECT pending_email FROM users WHERE id = ? AND pending_email IS NOT NULL");
$stmt->execute([$admin_id]);
if($stmt->rowCount() > 0) {
    $has_pending_email = true;
    $pending_email = $stmt->fetch(PDO::FETCH_ASSOC)['pending_email'];
}

$account_created = $admin['created_at'];
$two_factor_enabled = $admin['two_factor_enabled'] ?? 0;
$two_factor_last_used = $admin['two_factor_last_used'] ?? null;
$profile_picture = $admin['profile_picture'] ?? null;
$email_verified = $admin['email_verified'] ?? 0;
$current_email = $admin['email'];

// Get profile picture for sidebar
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? $profile_picture;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PLSNHS | Placido L. Señor National High School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/profile.css">
    <style>
        .verification-status {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .verification-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .verification-header i {
            font-size: 24px;
        }
        
        .verification-header h3 {
            font-size: 18px;
            margin: 0;
            color: var(--text-dark);
        }
        
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.unverified {
            background: #fff3cd;
            color: #856404;
        }
        
        .verification-info {
            color: var(--text-gray);
            font-size: 14px;
            line-height: 1.6;
        }
        
        .btn-verify {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .btn-verify:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-verify i {
            margin-right: 8px;
        }
        
        .profile-info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-gray);
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .verified-badge {
            color: #28a745;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .unverified-badge {
            color: #dc3545;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .email-change-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .email-change-form {
            margin-top: 15px;
        }
        
        .email-change-form .form-group {
            margin-bottom: 15px;
        }
        
        .pending-email-alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .pending-email-alert i {
            color: #856404;
            margin-right: 10px;
        }
        
        .verify-code-input {
            font-size: 24px;
            letter-spacing: 8px;
            text-align: center;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <img src="../pictures/logo sa skwelahan.jpg" alt="School Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%230B4F2E%22 /%3E%3Ctext x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EPLS%3C/text%3E%3C/svg%3E'">
                    </div>
                    <div class="logo-text">PLS<span>NHS</span></div>
                </div>
                <div class="school-badge">Placido L. Señor NHS</div>
            </div>

            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php if($sidebar_profile_pic && file_exists("../" . $sidebar_profile_pic)): ?>
                        <img src="../<?php echo $sidebar_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="admin-name"><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></div>
                <div class="admin-role"><i class="fas fa-shield-alt"></i> Administrator</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                        <li><a href="teachers.php"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
                        <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
                        <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
                        <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">MANAGEMENT</div>
                    <ul class="nav-items">
                        <li><a href="manage_accounts.php"><i class="fas fa-users-cog"></i> Accounts</a></li>
                        <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Profile</h1>
                    <p>View and manage your account information</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Left Column - Profile Info -->
                <div>
                    <div class="form-card">
                        <div class="profile-avatar-large" onclick="openImageModal()">
                            <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                                <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-initial">
                                    <?php echo strtoupper(substr($admin['fullname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($admin['fullname']); ?></h2>
                        <div style="text-align: center;">
                            <span class="profile-role">Administrator</span>
                        </div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    $days = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
                                    echo $days;
                                    ?>
                                </div>
                                <div class="stat-label">Days Active</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">Admin</div>
                                <div class="stat-label">Account Type</div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-content">
                                <div class="info-label">Current Email Address</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($current_email); ?>
                                    <?php if($email_verified == 1): ?>
                                        <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="unverified-badge"><i class="fas fa-times-circle"></i> Unverified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-id-card"></i></div>
                            <div class="info-content">
                                <div class="info-label">ID Number</div>
                                <div class="info-value"><?php echo $admin['id_number'] ?? 'Not set'; ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="info-content">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Edit Forms -->
                <div>
                    <!-- Email Verification Section -->
                    <div class="verification-status">
                        <div class="verification-header">
                            <i class="fas fa-envelope"></i>
                            <h3>Email Verification</h3>
                        </div>
                        
                        <?php if($email_verified == 1): ?>
                            <div class="verification-badge verified">
                                <i class="fas fa-check-circle"></i> Verified Email
                            </div>
                            <div class="verification-info">
                                <p><i class="fas fa-check-circle" style="color: #28a745;"></i> Your email address has been verified.</p>
                                <p style="margin-top: 10px;">This adds an extra layer of security to your account.</p>
                            </div>
                        <?php else: ?>
                            <div class="verification-badge unverified">
                                <i class="fas fa-exclamation-triangle"></i> Email Not Verified
                            </div>
                            <div class="verification-info">
                                <p><i class="fas fa-info-circle"></i> Your email address has not been verified yet.</p>
                                <p style="margin-top: 10px;">Verifying your email helps secure your account and ensures you receive important notifications.</p>
                                <form method="POST" style="margin-top: 15px;">
                                    <button type="submit" name="send_verification" class="btn-verify">
                                        <i class="fas fa-paper-plane"></i> Verify Email Now
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Email Change Section -->
                    <div class="verification-status">
                        <div class="verification-header">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>Change Email Address</h3>
                        </div>
                        
                        <?php if($has_pending_email): ?>
                            <div class="pending-email-alert">
                                <i class="fas fa-clock"></i> 
                                <strong>Pending Email Change:</strong> Verification sent to <strong><?php echo htmlspecialchars($pending_email); ?></strong>
                                <p style="margin-top: 10px; font-size: 13px;">Please check your inbox and enter the verification code below to complete the email change.</p>
                            </div>
                            
                            <form method="POST" class="email-change-form">
                                <div class="form-group">
                                    <label>Verification Code</label>
                                    <input type="text" name="verification_code" class="verify-code-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" name="verify_new_email" class="btn-verify" style="background: #28a745;">
                                        <i class="fas fa-check"></i> Verify & Change Email
                                    </button>
                                    <button type="submit" name="cancel_email_change" class="btn-verify" style="background: #dc3545;">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="email-change-form">
                                <div class="form-group">
                                    <label>New Email Address</label>
                                    <input type="email" name="new_email" placeholder="Enter your new email address" required>
                                    <small style="color: #666; display: block; margin-top: 5px;">
                                        <i class="fas fa-info-circle"></i> A verification code will be sent to the new email address for confirmation.
                                    </small>
                                </div>
                                <button type="submit" name="send_email_verification" class="btn-verify">
                                    <i class="fas fa-paper-plane"></i> Send Verification Code
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- 2FA Section -->
                    <div class="twofa-section">
                        <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
                        
                        <div class="twofa-status">
                            <div class="status-badge">
                                <span class="<?php echo $two_factor_enabled ? 'enabled' : 'disabled'; ?>">
                                    <i class="fas fa-<?php echo $two_factor_enabled ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <?php if($two_factor_last_used): ?>
                                <div class="last-used">
                                    <i class="fas fa-clock"></i> Last used: <?php echo date('M d, Y h:i A', strtotime($two_factor_last_used)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($two_factor_enabled): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="disable">
                                <button type="submit" name="toggle_2fa" class="btn-toggle disable" onclick="return confirm('Are you sure you want to disable Two-Factor Authentication?')">
                                    <i class="fas fa-times-circle"></i> Disable 2FA
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="enable">
                                <button type="submit" name="toggle_2fa" class="btn-toggle enable">
                                    <i class="fas fa-check-circle"></i> Enable 2FA
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>What is Two-Factor Authentication?</strong>
                                <p style="margin-top: 5px;">2FA adds an extra layer of security to your account. When enabled, you'll need to enter a verification code sent to your email after logging in with your password.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form (Name and ID only) -->
                    <div class="form-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>ID Number</label>
                                <input type="text" name="id_number" value="<?php echo htmlspecialchars($admin['id_number'] ?? ''); ?>" placeholder="Enter your ID number">
                            </div>

                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="Administrator" disabled>
                            </div>

                            <button type="submit" name="update_profile" class="btn-submit">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="form-card">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        
                        <div class="password-section">
                            <div class="password-header">
                                <input type="checkbox" id="change_password_checkbox">
                                <label for="change_password_checkbox">I want to change my password</label>
                            </div>

                            <form method="POST" action="" id="passwordForm">
                                <div class="password-fields" id="passwordFields">
                                    <div class="form-group">
                                        <label>Current Password <span>*</span></label>
                                        <input type="password" name="current_password" id="current_password" disabled>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>New Password <span>*</span></label>
                                            <input type="password" name="new_password" id="new_password" disabled>
                                            <div class="password-strength">
                                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                            </div>
                                            <div class="password-strength-text" id="passwordStrengthText">
                                                <i class="fas fa-info-circle"></i> Minimum 6 characters
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Confirm Password <span>*</span></label>
                                            <input type="password" name="confirm_password" id="confirm_password" disabled>
                                            <div class="password-strength-text" id="passwordMatchText">
                                                <i class="fas fa-info-circle"></i> Re-enter new password
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="change_password" class="btn-submit" id="changePasswordBtn" disabled>
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if($two_factor_enabled): ?>
                            <div class="info-box" style="margin-top: 15px; background: rgba(245, 158, 11, 0.1); border-left-color: var(--warning);">
                                <i class="fas fa-shield-alt" style="color: var(--warning);"></i>
                                <div>
                                    <strong>2FA Protection:</strong> Since 2FA is enabled, you'll need to verify your identity via email when changing your password.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Upload Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Update Profile Picture</h3>
                <button class="close-modal" onclick="closeImageModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-body">
                    <div class="image-preview" id="imagePreview">
                        <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                            <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Preview" id="previewImg">
                        <?php else: ?>
                            <div style="width: 150px; height: 150px; background: linear-gradient(135deg, #0B4F2E, #1a7a42); display: flex; align-items: center; justify-content: center; color: white; font-size: 56px; font-weight: bold; border-radius: 50%;">
                                <?php echo strtoupper(substr($admin['fullname'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-input-wrapper">
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                        <label for="profile_picture" class="file-input-label">
                            <i class="fas fa-upload"></i> Choose Image
                        </label>
                    </div>
                    <p style="font-size: 12px; color: var(--text-gray); margin-top: 10px;">
                        <i class="fas fa-info-circle"></i> Allowed formats: JPG, PNG, GIF, WEBP. Max size: 5MB
                    </p>
                </div>
                <div class="modal-footer">
                    <?php if($profile_picture): ?>
                        <a href="?remove_pic=1" class="btn-danger" onclick="return confirm('Remove your profile picture?')">
                            <i class="fas fa-trash"></i> Remove
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" onclick="closeImageModal()">Cancel</button>
                    <button type="submit" name="upload_profile_pic" class="btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImageModal() {
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.getElementById('imagePreview');
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

        // Auto-format code input
        document.querySelectorAll('.verify-code-input').forEach(input => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        });

        const changePasswordCheckbox = document.getElementById('change_password_checkbox');
        const passwordFields = document.getElementById('passwordFields');
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const changePasswordBtn = document.getElementById('changePasswordBtn');

        if(changePasswordCheckbox) {
            changePasswordCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    passwordFields.classList.add('show');
                    currentPassword.disabled = false;
                    newPassword.disabled = false;
                    confirmPassword.disabled = false;
                    changePasswordBtn.disabled = false;
                    newPassword.focus();
                } else {
                    passwordFields.classList.remove('show');
                    currentPassword.disabled = true;
                    newPassword.disabled = true;
                    confirmPassword.disabled = true;
                    changePasswordBtn.disabled = true;
                    currentPassword.value = '';
                    newPassword.value = '';
                    confirmPassword.value = '';
                    resetPasswordStrength();
                }
            });
        }

        function resetPasswordStrength() {
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            const matchText = document.getElementById('passwordMatchText');
            if(strengthBar) strengthBar.style.width = '0';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
            if(matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
        }

        function checkPasswordStrength() {
            const password = newPassword.value;
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;

            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
                return;
            }

            if (strength <= 2) {
                strengthBar.style.width = '33%';
                strengthBar.style.backgroundColor = '#ef4444';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> Weak password';
            } else if (strength <= 4) {
                strengthBar.style.width = '66%';
                strengthBar.style.backgroundColor = '#f59e0b';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> Medium password';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.style.backgroundColor = '#10b981';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> Strong password';
            }
        }

        function checkPasswordMatch() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            const matchText = document.getElementById('passwordMatchText');

            if (confirm.length === 0) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
            } else {
                matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
            }
        }

        if(newPassword) {
            newPassword.addEventListener('input', checkPasswordStrength);
            newPassword.addEventListener('input', checkPasswordMatch);
        }
        
        if(confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }

        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
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