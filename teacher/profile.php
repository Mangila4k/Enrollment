<?php
session_start();
include("../config/database.php");

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$profile_picture = $_SESSION['user']['profile_picture'] ?? null;
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? null;
$success_message = '';
$error_message = '';

// Function to send notification to all admins
function notifyAdmins($conn, $title, $message, $link, $type = 'alert', $exclude_user_id = null) {
    $sql = "SELECT id FROM users WHERE role IN ('Admin', 'Registrar')";
    $params = [];
    
    if($exclude_user_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
    }
    
    $admin_stmt = $conn->prepare($sql);
    $admin_stmt->execute($params);
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($admins as $admin) {
        $add_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at, is_read) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        $add_notif->execute([$admin['id'], $type, $title, $message, $link]);
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

// Check if required columns exist
try {
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

// Get teacher details
$query = "SELECT * FROM users WHERE id = :teacher_id AND role = 'Teacher'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

$email_verified = $teacher['email_verified'] ?? 0;
$current_email = $teacher['email'];

// Check for pending email change
$has_pending_email = false;
$pending_email = '';
$stmt = $conn->prepare("SELECT pending_email FROM users WHERE id = ? AND pending_email IS NOT NULL");
$stmt->execute([$teacher_id]);
if($stmt->rowCount() > 0) {
    $has_pending_email = true;
    $pending_email = $stmt->fetch(PDO::FETCH_ASSOC)['pending_email'];
}

// Get profile picture from database
$profile_picture = $teacher['profile_picture'] ?? null;
$sidebar_profile_pic = $teacher['profile_picture'] ?? null;
$_SESSION['user']['profile_picture'] = $profile_picture;

// Handle send verification email for initial email verification
if(isset($_POST['send_verification'])) {
    require_once '../config/email_config.php';
    
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $teacher_id]);
    
    if(sendVerificationCode($teacher['email'], $teacher['fullname'], $verification_code)) {
        $_SESSION['temp_email'] = $teacher['email'];
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
        $check_email->execute([$new_email, $teacher_id]);
        
        if($check_email->rowCount() > 0) {
            $error_message = "Email address already registered to another user.";
        } else {
            require_once '../config/email_config.php';
            
            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store pending email and code in database
            $update = $conn->prepare("UPDATE users SET pending_email = ?, pending_email_code = ?, pending_email_expires = ? WHERE id = ?");
            $update->execute([$new_email, $verification_code, $expires, $teacher_id]);
            
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
                    <p>Hello <strong>" . htmlspecialchars($teacher['fullname']) . "</strong>,</p>
                    <p>You requested to change your email address to: <strong>$new_email</strong></p>
                    <p>Please enter the verification code below to confirm this change:</p>
                    <div class='code'>$verification_code</div>
                    <p>This code will expire in <strong>1 hour</strong>.</p>
                    <p>If you didn't request this change, please ignore this email.</p>
                </div>
            </body>
            </html>
            ";
            
            $mailSent = sendCustomEmail($new_email, $teacher['fullname'], $subject, $message);
            
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
    $stmt->execute([$teacher_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($pending['pending_email'] && $pending['pending_email_code']) {
        $expires = new DateTime($pending['pending_email_expires']);
        $now = new DateTime();
        
        if($now > $expires) {
            $error_message = "Verification code has expired. Please request a new email change.";
            // Clear pending email
            $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $clear->execute([$teacher_id]);
        } elseif($pending['pending_email_code'] == $verification_code) {
            // Update email to new email
            $new_email = $pending['pending_email'];
            $old_email = $teacher['email'];
            $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $update->execute([$new_email, $teacher_id]);
            
            // Update session
            $_SESSION['user']['email'] = $new_email;
            
            // NOTIFY ADMINS ABOUT EMAIL CHANGE
            $notif_title = "📧 Teacher Email Changed";
            $notif_message = "Teacher " . $teacher['fullname'] . " has changed their email address from " . $old_email . " to " . $new_email;
            $notif_link = "../admin/teachers.php?view=" . $teacher_id;
            notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'alert');
            
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
    $clear->execute([$teacher_id]);
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
            
            // Delete old profile picture if exists
            if($profile_picture && file_exists("../" . $profile_picture)) {
                unlink("../" . $profile_picture);
            }
            
            $new_filename = "teacher_" . $teacher_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :teacher_id");
                $update_stmt->execute([
                    ':profile_picture' => $db_path,
                    ':teacher_id' => $teacher_id
                ]);
                
                $_SESSION['user']['profile_picture'] = $db_path;
                $profile_picture = $db_path;
                $sidebar_profile_pic = $db_path;
                
                // NOTIFY ADMINS ABOUT PROFILE PICTURE UPDATE
                $notif_title = "🖼️ Teacher Profile Picture Updated";
                $notif_message = "Teacher " . $teacher['fullname'] . " has updated their profile picture.";
                $notif_link = "../admin/teachers.php?view=" . $teacher_id;
                notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'update');
                
                $success_message = "Profile picture updated successfully!";
                header("Location: profile.php?success=1");
                exit();
            } else {
                $error_message = "Failed to upload image. Please try again.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else {
        $error_message = "Please select an image to upload.";
    }
}

// Handle remove profile picture
if(isset($_GET['remove_pic'])) {
    if($profile_picture && file_exists("../" . $profile_picture)) {
        unlink("../" . $profile_picture);
    }
    
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = :teacher_id");
    $update_stmt->execute([':teacher_id' => $teacher_id]);
    
    unset($_SESSION['user']['profile_picture']);
    $profile_picture = null;
    $sidebar_profile_pic = null;
    
    // NOTIFY ADMINS ABOUT PROFILE PICTURE REMOVAL
    $notif_title = "🖼️ Teacher Profile Picture Removed";
    $notif_message = "Teacher " . $teacher['fullname'] . " has removed their profile picture.";
    $notif_link = "../admin/teachers.php?view=" . $teacher_id;
    notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'update');
    
    $success_message = "Profile picture removed successfully!";
    header("Location: profile.php?success=2");
    exit();
}

// Handle profile update (name and ID only, email handled separately)
if(isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    $errors = [];
    
    if(empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    // Check if ID number already exists
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = :id_number AND id != :teacher_id");
        $check_id->execute([
            ':id_number' => $id_number,
            ':teacher_id' => $teacher_id
        ]);
        
        if($check_id->rowCount() > 0) {
            $errors[] = "ID number already exists for another user";
        }
    }
    
    if(empty($errors)) {
        $update_query = "UPDATE users SET fullname = :fullname, id_number = :id_number WHERE id = :teacher_id";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([
            ':fullname' => $fullname,
            ':id_number' => $id_number,
            ':teacher_id' => $teacher_id
        ])) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['id_number'] = $id_number;
            
            // NOTIFY ADMINS ABOUT PROFILE UPDATE
            $notif_title = "📝 Teacher Profile Updated";
            $notif_message = "Teacher " . $teacher['fullname'] . " has updated their profile name to " . $fullname;
            $notif_link = "../admin/teachers.php?view=" . $teacher_id;
            notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'update');
            
            $success_message = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $error_message = "Failed to update profile.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle password change with strong validation
if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Verify current password
    if(empty($current_password)) {
        $errors[] = "Current password is required";
    } else {
        if(!password_verify($current_password, $teacher['password'])) {
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
        $update_query = "UPDATE users SET password = :password WHERE id = :teacher_id";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([
            ':password' => $hashed_password,
            ':teacher_id' => $teacher_id
        ])) {
            // NOTIFY ADMINS ABOUT PASSWORD CHANGE
            $notif_title = "🔐 Teacher Password Changed";
            $notif_message = "Teacher " . $teacher['fullname'] . " has changed their account password.";
            $notif_link = "../admin/teachers.php?view=" . $teacher_id;
            notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'alert');
            
            $success_message = "Password changed successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $error_message = "Failed to change password.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get teacher's statistics
$sections_count = 0;
$subjects_count = 0;
$students_count = 0;
$total_attendance = 0;

// Get sections count
$sections_query = "SELECT COUNT(*) as count FROM sections WHERE adviser_id = :teacher_id";
$stmt = $conn->prepare($sections_query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$sections_count = $row['count'] ?? 0;
$stmt = null;

// Get subjects count
$subjects_query = "
    SELECT COUNT(DISTINCT subject_id) as count 
    FROM class_schedules 
    WHERE teacher_id = :teacher_id AND status = 'active'
";
$stmt = $conn->prepare($subjects_query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$subjects_count = $row['count'] ?? 0;
$stmt = null;

// Get total students count
$students_query = "
    SELECT COUNT(DISTINCT e.student_id) as count
    FROM enrollments e
    JOIN sections s ON e.grade_id = s.grade_id
    JOIN class_schedules cs ON s.id = cs.section_id
    WHERE cs.teacher_id = :teacher_id 
    AND e.status = 'Enrolled' 
    AND cs.status = 'active'
";
$stmt = $conn->prepare($students_query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$students_count = $row['count'] ?? 0;
$stmt = null;

// Get total attendance records
$attendance_query = "
    SELECT COUNT(*) as count FROM teacher_attendance WHERE teacher_id = :teacher_id
";
$stmt = $conn->prepare($attendance_query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_attendance = $row['count'] ?? 0;
$stmt = null;

$account_created = $teacher['created_at'] ?? date('Y-m-d H:i:s');
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

    <div class="app-container">
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

            <div class="teacher-profile">
                <div class="teacher-avatar">
                    <?php if($sidebar_profile_pic && file_exists("../" . $sidebar_profile_pic)): ?>
                        <img src="../<?php echo $sidebar_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo isset($teacher_name) ? strtoupper(substr($teacher_name, 0, 1)) : 'T'; ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="teacher-name"><?php echo isset($teacher_name) ? htmlspecialchars(explode(' ', $teacher_name)[0]) : 'Teacher'; ?></div>
                <div class="teacher-role"><i class="fas fa-chalkboard-user"></i> Teacher</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="attendance_qr.php"><i class="fas fa-qrcode"></i> QR Attendance</a></li>
                        <li><a href="classes.php"><i class="fas fa-users"></i> My Classes</a></li>
                        <li><a href="schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> Grades</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
                    <p>View and manage your personal information</p>
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
                    <div class="profile-card">
                        <div class="profile-avatar-large" onclick="openImageModal()">
                            <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                                <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-initial">
                                    <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($teacher['fullname']); ?></h2>
                        <span class="profile-role">Teacher</span>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $days_active; ?></div>
                                <div class="stat-label">Days Active</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $total_attendance; ?></div>
                                <div class="stat-label">Attendance Records</div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
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
                            <div class="info-icon"><i class="fas fa-id-card"></i></div>
                            <div class="info-content">
                                <div class="info-label">Employee ID</div>
                                <div class="info-value"><?php echo $teacher['id_number'] ?? 'Not assigned'; ?></div>
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

                <!-- Right Column - Professional Info -->
                <div>
                    <!-- Email Verification Section -->
                    <div class="academic-card">
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
                    <div class="academic-card">
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

                    <!-- Professional Information -->
                    <div class="academic-card">
                        <h3><i class="fas fa-chalkboard-user"></i> Professional Information</h3>
                        
                        <div class="academic-grid">
                            <div class="academic-item">
                                <div class="academic-value"><?php echo $sections_count; ?></div>
                                <div class="academic-label">Sections Handled</div>
                            </div>
                            <div class="academic-item">
                                <div class="academic-value"><?php echo $subjects_count; ?></div>
                                <div class="academic-label">Subjects Taught</div>
                            </div>
                            <div class="academic-item">
                                <div class="academic-value"><?php echo $students_count; ?></div>
                                <div class="academic-label">Total Students</div>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>You are currently teaching <?php echo $subjects_count; ?> subject(s) to <?php echo $students_count; ?> student(s) across <?php echo $sections_count; ?> section(s).</p>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="form-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($teacher['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Employee ID</label>
                                <input type="text" name="id_number" value="<?php echo htmlspecialchars($teacher['id_number'] ?? ''); ?>" placeholder="Enter your employee ID">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i> Employee ID is optional
                                </div>
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
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="image-preview" id="imagePreview">
                        <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                            <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Preview">
                        <?php else: ?>
                            <div class="avatar-initial">
                                <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
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
            teacherId: <?php echo $teacher_id; ?>,
            teacherName: '<?php echo addslashes($teacher_name); ?>',
            emailVerified: <?php echo $email_verified; ?>,
            currentEmail: '<?php echo addslashes($current_email); ?>'
        };
        
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
            const matchText = document.getElementById('passwordMatchText');
            
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
            if(!newPassword) return;
            
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
            const confirm = confirmPassword ? confirmPassword.value : '';
            const passwordsMatch = (password === confirm);
            
            if(changePasswordBtn) {
                changePasswordBtn.disabled = !(isStrong && passwordsMatch && password.length > 0);
            }
        }

        function checkPasswordMatch() {
            if(!newPassword || !confirmPassword) return;
            
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            const matchText = document.getElementById('passwordMatchText');

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
    </script>

    <!-- Profile JS -->
    <script src="js/profile.js"></script>
</body>
</html>