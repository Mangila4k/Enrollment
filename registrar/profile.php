<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_id = $_SESSION['user']['id'];
$registrar_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Get registrar profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$registrar_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_data['profile_picture'] ?? null;

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
    
    $check_pending_email = $conn->query("SHOW COLUMNS FROM users LIKE 'pending_email'");
    if($check_pending_email->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL");
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email_code VARCHAR(10) DEFAULT NULL");
        $conn->exec("ALTER TABLE users ADD COLUMN pending_email_expires DATETIME DEFAULT NULL");
    }
} catch(PDOException $e) {
    // Column might already exist
}

// Get registrar details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Registrar'";
$stmt = $conn->prepare($query);
$stmt->execute([$registrar_id]);
$registrar = $stmt->fetch(PDO::FETCH_ASSOC);

$email_verified = $registrar['email_verified'] ?? 0;
$current_email = $registrar['email'];

// Check for pending email change
$has_pending_email = false;
$pending_email = '';
$stmt = $conn->prepare("SELECT pending_email FROM users WHERE id = ? AND pending_email IS NOT NULL");
$stmt->execute([$registrar_id]);
if($stmt->rowCount() > 0) {
    $has_pending_email = true;
    $pending_email = $stmt->fetch(PDO::FETCH_ASSOC)['pending_email'];
}

// Update profile picture variable after possible changes
$profile_picture = $registrar['profile_picture'] ?? null;

// Handle send verification email for initial email verification
if(isset($_POST['send_verification'])) {
    require_once '../config/email_config.php';
    
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $registrar_id]);
    
    if(sendVerificationCode($registrar['email'], $registrar['fullname'], $verification_code)) {
        $_SESSION['temp_email'] = $registrar['email'];
        $_SESSION['success_message'] = "A verification code has been sent to your email. Please check your inbox.";
        header("Location: verify_email.php");
        exit();
    } else {
        $error_message = "Failed to send verification email. Please try again.";
    }
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
        $check_email->execute([$new_email, $registrar_id]);
        
        if($check_email->rowCount() > 0) {
            $error_message = "Email address already registered to another user.";
        } else {
            require_once '../config/email_config.php';
            
            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store pending email and code in database
            $update = $conn->prepare("UPDATE users SET pending_email = ?, pending_email_code = ?, pending_email_expires = ? WHERE id = ?");
            $update->execute([$new_email, $verification_code, $expires, $registrar_id]);
            
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
                    <p>Hello <strong>" . htmlspecialchars($registrar['fullname']) . "</strong>,</p>
                    <p>You requested to change your email address to: <strong>$new_email</strong></p>
                    <p>Please enter the verification code below to confirm this change:</p>
                    <div class='code'>$verification_code</div>
                    <p>This code will expire in <strong>1 hour</strong>.</p>
                    <p>If you didn't request this change, please ignore this email.</p>
                </div>
            </body>
            </html>
            ";
            
            $mailSent = sendCustomEmail($new_email, $registrar['fullname'], $subject, $message);
            
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
    $stmt->execute([$registrar_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($pending['pending_email'] && $pending['pending_email_code']) {
        $expires = new DateTime($pending['pending_email_expires']);
        $now = new DateTime();
        
        if($now > $expires) {
            $error_message = "Verification code has expired. Please request a new email change.";
            // Clear pending email
            $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $clear->execute([$registrar_id]);
        } elseif($pending['pending_email_code'] == $verification_code) {
            // Update email to new email
            $new_email = $pending['pending_email'];
            $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $update->execute([$new_email, $registrar_id]);
            
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
    $clear->execute([$registrar_id]);
    unset($_SESSION['pending_email_change']);
    $_SESSION['success_message'] = "Email change request cancelled.";
    header("Location: profile.php");
    exit();
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
            
            $new_filename = "registrar_" . $registrar_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            if(!empty($profile_picture) && file_exists("../" . $profile_picture)) {
                unlink("../" . $profile_picture);
            }
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if($update_stmt->execute([$db_path, $registrar_id])) {
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
    if(!empty($profile_picture) && file_exists("../" . $profile_picture)) {
        unlink("../" . $profile_picture);
    }
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
    if($update_stmt->execute([$registrar_id])) {
        $_SESSION['user']['profile_picture'] = null;
        $_SESSION['success_message'] = "Profile picture removed successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $error_message = "Failed to remove profile picture.";
    }
}

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status IN ('Enrolled', 'Rejected')");
$enrollments_processed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'");
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate processing rate
$processing_rate = $total_students > 0 ? round(($enrollments_processed / $total_students) * 100) : 0;

// Handle profile update (name and ID only, email handled separately)
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
        
        $errors = [];
        
        if(empty($fullname)) {
            $errors[] = "Full name is required";
        }
        
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->execute([$id_number, $registrar_id]);
            
            if($check_id->rowCount() > 0) {
                $errors[] = "ID number already exists for another user";
            }
        }
        
        if(empty($errors)) {
            $update_query = "UPDATE users SET fullname = ?, id_number = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$fullname, $id_number, $registrar_id]);
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['id_number'] = $id_number;
                
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Database error occurred";
            }
        }
        
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle password change with strong validation
    if(isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if(empty($current_password)) {
            $errors[] = "Current password is required";
        } else {
            if(!password_verify($current_password, $registrar['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        // Strong password validation
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } else {
            $password_errors = [];
            
            if(strlen($new_password) < 8) {
                $password_errors[] = "at least 8 characters";
            }
            if(!preg_match('/[A-Z]/', $new_password)) {
                $password_errors[] = "at least one uppercase letter (A-Z)";
            }
            if(!preg_match('/[a-z]/', $new_password)) {
                $password_errors[] = "at least one lowercase letter (a-z)";
            }
            if(!preg_match('/[0-9]/', $new_password)) {
                $password_errors[] = "at least one number (0-9)";
            }
            if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
                $password_errors[] = "at least one special character (!@#$%^&*)";
            }
            
            if(!empty($password_errors)) {
                $errors[] = "Password must contain: " . implode(", ", $password_errors);
            }
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        if(empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$hashed_password, $registrar_id]);
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Database error occurred";
            }
        }
        
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
}

$account_created = $registrar['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? $profile_picture;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Profile CSS -->
    <link rel="stylesheet" href="css/profile.css">
    <style>
        .password-requirements {
            margin-top: 8px;
            padding: 10px;
            background: #fef2f2;
            border-radius: 8px;
            border-left: 4px solid #dc2626;
            font-size: 12px;
        }
        .password-requirements ul {
            margin-left: 20px;
            margin-top: 5px;
            margin-bottom: 0;
        }
        .password-requirements li {
            color: #dc2626;
            margin: 3px 0;
        }
        .password-requirements li.valid {
            color: #10b981;
            text-decoration: line-through;
        }
        .password-requirements li i {
            margin-right: 6px;
            width: 16px;
        }
        .password-strength {
            margin-top: 8px;
        }
        .password-strength-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
            border-radius: 3px;
        }
        .password-strength-text {
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .password-match {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
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
                    <div class="avatar-initial"><?php echo strtoupper(substr($registrar_name, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="online-dot"></div>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></div>
            <div class="admin-role"><i class="fas fa-user-tie"></i> Registrar</div>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN MENU</div>
                <ul class="nav-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                    <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>My Profile</h1>
                <p>View and manage your account information</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Left Column - Profile Info -->
            <div>
                <div class="profile-card">
                    <div class="profile-avatar-large" onclick="openImageModal()">
                        <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                            <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div class="avatar-initial">
                                <?php echo strtoupper(substr($registrar['fullname'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <h2 class="profile-name"><?php echo htmlspecialchars($registrar['fullname']); ?></h2>
                    <span class="profile-role">Registrar</span>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $days_active; ?></div>
                            <div class="stat-label">Days Active</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $enrollments_processed; ?></div>
                            <div class="stat-label">Processed</div>
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Email Address</div>
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
                        <div class="info-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><?php echo $registrar['id_number'] ?? 'Not assigned'; ?></div>
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Performance & Edit Forms -->
            <div>
                <!-- Email Verification Section -->
                <div class="form-card">
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
                <div class="form-card">
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

                <!-- Performance Card -->
                <div class="performance-card">
                    <h3><i class="fas fa-chart-line"></i> Performance Overview</h3>
                    <div class="performance-stats">
                        <div class="perf-item">
                            <div class="perf-value"><?php echo $enrollments_processed; ?></div>
                            <div class="perf-label">Enrollments Processed</div>
                        </div>
                        <div class="perf-item">
                            <div class="perf-value"><?php echo $pending_count; ?></div>
                            <div class="perf-label">Pending Review</div>
                        </div>
                        <div class="perf-item">
                            <div class="perf-value"><?php echo $total_students; ?></div>
                            <div class="perf-label">Total Students</div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Processing Rate</span>
                            <span><?php echo $processing_rate; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $processing_rate; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="form-card">
                    <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($registrar['fullname']); ?>" required>
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
                                    <label>Current Password <span class="required">*</span></label>
                                    <input type="password" name="current_password" id="current_password" disabled>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>New Password <span class="required">*</span></label>
                                        <input type="password" name="new_password" id="new_password" disabled>
                                        
                                        <!-- Password strength meter -->
                                        <div class="password-strength">
                                            <div class="password-strength-bar">
                                                <div class="password-strength-fill" id="passwordStrengthFill"></div>
                                            </div>
                                            <div class="password-strength-text" id="passwordStrengthText">
                                                <i class="fas fa-info-circle"></i>
                                                <span>Enter new password</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Password requirements checklist -->
                                        <div class="password-requirements" id="passwordRequirements">
                                            <small><i class="fas fa-shield-alt"></i> Password must contain:</small>
                                            <ul>
                                                <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                                                <li id="req-upper"><i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)</li>
                                                <li id="req-lower"><i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)</li>
                                                <li id="req-number"><i class="fas fa-circle"></i> At least 1 number (0-9)</li>
                                                <li id="req-special"><i class="fas fa-circle"></i> At least 1 special character (!@#$%^&*)</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Confirm Password <span class="required">*</span></label>
                                        <input type="password" name="confirm_password" id="confirm_password" disabled>
                                        <div class="password-match" id="passwordMatch">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Re-enter new password</span>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="change_password" class="btn-submit" id="changePasswordBtn" disabled>
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
                            <div class="preview-placeholder">
                                <?php echo strtoupper(substr($registrar['fullname'], 0, 1)); ?>
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
        // Pass PHP data to JavaScript
        const profileData = {
            processingRate: <?php echo $processing_rate; ?>,
            daysActive: <?php echo $days_active; ?>,
            enrollmentsProcessed: <?php echo $enrollments_processed; ?>,
            pendingCount: <?php echo $pending_count; ?>,
            totalStudents: <?php echo $total_students; ?>
        };
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
        
        // Image modal functions
        function openImageModal() {
            document.getElementById('imageModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
            document.body.style.overflow = 'auto';
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

        // Auto-format code input
        document.querySelectorAll('.verify-code-input').forEach(input => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        });

        // Password change toggle
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
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');
            const matchText = document.getElementById('passwordMatch');
            
            if(strengthFill) strengthFill.style.width = '0%';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Enter new password</span>';
            if(matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
            
            // Reset requirement list
            const requirements = ['length', 'upper', 'lower', 'number', 'special'];
            requirements.forEach(req => {
                const element = document.getElementById(`req-${req}`);
                if(element) {
                    element.classList.remove('valid');
                    element.innerHTML = '<i class="fas fa-circle"></i> ' + element.innerText.replace(/[✓✔✅]/g, '').trim();
                }
            });
        }

        function validatePassword(password) {
            return {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
        }

        function updatePasswordStrength() {
            const password = newPassword.value;
            const validation = validatePassword(password);
            
            // Update requirement list
            const reqLength = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqLower = document.getElementById('req-lower');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');
            
            if(reqLength) {
                if(validation.length) {
                    reqLength.classList.add('valid');
                    reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
                } else {
                    reqLength.classList.remove('valid');
                    reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
                }
            }
            
            if(reqUpper) {
                if(validation.uppercase) {
                    reqUpper.classList.add('valid');
                    reqUpper.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 uppercase letter (A-Z)';
                } else {
                    reqUpper.classList.remove('valid');
                    reqUpper.innerHTML = '<i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)';
                }
            }
            
            if(reqLower) {
                if(validation.lowercase) {
                    reqLower.classList.add('valid');
                    reqLower.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 lowercase letter (a-z)';
                } else {
                    reqLower.classList.remove('valid');
                    reqLower.innerHTML = '<i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)';
                }
            }
            
            if(reqNumber) {
                if(validation.number) {
                    reqNumber.classList.add('valid');
                    reqNumber.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 number (0-9)';
                } else {
                    reqNumber.classList.remove('valid');
                    reqNumber.innerHTML = '<i class="fas fa-circle"></i> At least 1 number (0-9)';
                }
            }
            
            if(reqSpecial) {
                if(validation.special) {
                    reqSpecial.classList.add('valid');
                    reqSpecial.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 special character (!@#$%^&*)';
                } else {
                    reqSpecial.classList.remove('valid');
                    reqSpecial.innerHTML = '<i class="fas fa-circle"></i> At least 1 special character (!@#$%^&*)';
                }
            }
            
            // Calculate strength percentage
            const validCount = Object.values(validation).filter(v => v === true).length;
            const strengthPercent = (validCount / 5) * 100;
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');
            
            if(strengthFill) {
                strengthFill.style.width = strengthPercent + '%';
                if(strengthPercent <= 25) {
                    strengthFill.style.backgroundColor = '#ef4444';
                    if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Weak password</span>';
                } else if(strengthPercent <= 50) {
                    strengthFill.style.backgroundColor = '#f59e0b';
                    if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Fair password</span>';
                } else if(strengthPercent <= 75) {
                    strengthFill.style.backgroundColor = '#3b82f6';
                    if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #3b82f6;">Good password</span>';
                } else {
                    strengthFill.style.backgroundColor = '#10b981';
                    if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Strong password</span>';
                }
            }
            
            // Check password match
            checkPasswordMatch();
            
            // Enable/disable submit button based on all requirements met
            const isStrong = Object.values(validation).every(v => v === true);
            const confirm = confirmPassword.value;
            const passwordsMatch = (password === confirm);
            
            if(changePasswordBtn) {
                changePasswordBtn.disabled = !(isStrong && passwordsMatch && password.length > 0);
            }
        }

        function checkPasswordMatch() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            const matchText = document.getElementById('passwordMatch');

            if (confirm.length === 0) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
            } else {
                matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
            }
            
            // Re-check button state
            if(changePasswordBtn && newPassword.value.length > 0) {
                const validation = validatePassword(newPassword.value);
                const isStrong = Object.values(validation).every(v => v === true);
                changePasswordBtn.disabled = !(isStrong && password === confirm);
            }
        }

        if(newPassword) {
            newPassword.addEventListener('input', updatePasswordStrength);
            newPassword.addEventListener('input', checkPasswordMatch);
        }
        
        if(confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }
    </script>
</body>
</html>