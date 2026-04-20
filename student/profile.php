<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
$profile_picture = $_SESSION['user']['profile_picture'] ?? null;
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? null;
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

// Get student details
$query = "SELECT * FROM users WHERE id = :student_id AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

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

$email_verified = $student['email_verified'] ?? 0;
$current_email = $student['email'];

// Check for pending email change
$has_pending_email = false;
$pending_email = '';
$stmt = $conn->prepare("SELECT pending_email FROM users WHERE id = ? AND pending_email IS NOT NULL");
$stmt->execute([$student_id]);
if($stmt->rowCount() > 0) {
    $has_pending_email = true;
    $pending_email = $stmt->fetch(PDO::FETCH_ASSOC)['pending_email'];
}

// Get profile picture from database
$profile_picture = $student['profile_picture'] ?? null;
$sidebar_profile_pic = $student['profile_picture'] ?? null;
$_SESSION['user']['profile_picture'] = $profile_picture;

// Handle send verification email for initial email verification
if(isset($_POST['send_verification'])) {
    require_once '../config/email_config.php';
    
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $student_id]);
    
    if(sendVerificationCode($student['email'], $student['fullname'], $verification_code)) {
        $_SESSION['temp_email'] = $student['email'];
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
        $check_email->execute([$new_email, $student_id]);
        
        if($check_email->rowCount() > 0) {
            $error_message = "Email address already registered to another user.";
        } else {
            require_once '../config/email_config.php';
            
            // Generate verification code
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store pending email and code in database
            $update = $conn->prepare("UPDATE users SET pending_email = ?, pending_email_code = ?, pending_email_expires = ? WHERE id = ?");
            $update->execute([$new_email, $verification_code, $expires, $student_id]);
            
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
                    <p>Hello <strong>" . htmlspecialchars($student['fullname']) . "</strong>,</p>
                    <p>You requested to change your email address to: <strong>$new_email</strong></p>
                    <p>Please enter the verification code below to confirm this change:</p>
                    <div class='code'>$verification_code</div>
                    <p>This code will expire in <strong>1 hour</strong>.</p>
                    <p>If you didn't request this change, please ignore this email.</p>
                </div>
            </body>
            </html>
            ";
            
            $mailSent = sendCustomEmail($new_email, $student['fullname'], $subject, $message);
            
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
    $stmt->execute([$student_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($pending['pending_email'] && $pending['pending_email_code']) {
        $expires = new DateTime($pending['pending_email_expires']);
        $now = new DateTime();
        
        if($now > $expires) {
            $error_message = "Verification code has expired. Please request a new email change.";
            // Clear pending email
            $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $clear->execute([$student_id]);
        } elseif($pending['pending_email_code'] == $verification_code) {
            // Update email to new email
            $new_email = $pending['pending_email'];
            $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $update->execute([$new_email, $student_id]);
            
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
    $clear->execute([$student_id]);
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
            
            $new_filename = "student_" . $student_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :student_id");
                $update_stmt->execute([
                    ':profile_picture' => $db_path,
                    ':student_id' => $student_id
                ]);
                
                $_SESSION['user']['profile_picture'] = $db_path;
                $profile_picture = $db_path;
                $sidebar_profile_pic = $db_path;
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
    
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = :student_id");
    $update_stmt->execute([':student_id' => $student_id]);
    
    unset($_SESSION['user']['profile_picture']);
    $profile_picture = null;
    $sidebar_profile_pic = null;
    $success_message = "Profile picture removed successfully!";
    header("Location: profile.php?success=2");
    exit();
}

// Get student's enrollment information
$enrollment_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';
$enrollment_date = $enrollment ? $enrollment['created_at'] : null;

// Get enrolled subjects count
$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $subjects_query = "SELECT COUNT(*) as count FROM subjects WHERE grade_id = :grade_id";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bindParam(':grade_id', $enrollment['grade_id'], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $subjects_count = $row['count'] ?? 0;
    $stmt = null;
}

$account_created = $student['created_at'] ?? date('Y-m-d H:i:s');
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Profile CSS -->
    <link rel="stylesheet" href="css/profile.css">
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

            <div class="student-profile">
                <div class="student-avatar">
                    <?php if($sidebar_profile_pic && file_exists("../" . $sidebar_profile_pic)): ?>
                        <img src="../<?php echo $sidebar_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo isset($student_name) ? strtoupper(substr($student_name, 0, 1)) : 'S'; ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo isset($student_name) ? htmlspecialchars(explode(' ', $student_name)[0]) : 'Student'; ?></div>
                <div class="student-role"><i class="fas fa-user-graduate"></i> Student</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php"><i class="fas fa-history"></i> Enrollment History</a></li>
                        <li><a href="requirements.php"><i class="fas fa-file-alt"></i> Requirements</a></li>
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
                        <div class="profile-picture-container">
                            <div class="profile-avatar-large" onclick="openImageModal()">
                                <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                                    <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="avatar-initial">
                                        <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="avatar-overlay">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <span class="profile-role">Student</span>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $days_active; ?></div>
                                <div class="stat-label">Days Active</div>
                            </div>
                        </div>

                        <!-- Email Verification Status -->
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
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?php echo $student['id_number'] ?? 'Not assigned'; ?></div>
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

                <!-- Right Column - Academic Info -->
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

                    <!-- Academic Information -->
                    <div class="academic-card">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                        
                        <?php if($enrollment): ?>
                            <div class="academic-grid">
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($grade_name); ?></div>
                                    <div class="academic-label">Grade Level</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($strand); ?></div>
                                    <div class="academic-label">Strand</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($school_year); ?></div>
                                    <div class="academic-label">School Year</div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; flex-wrap: wrap; gap: 10px;">
                                <span class="enrollment-badge badge-<?php echo strtolower($enrollment_status); ?>">
                                    <i class="fas fa-<?php echo $enrollment_status == 'Enrolled' ? 'check-circle' : 'clock'; ?>"></i>
                                    Status: <?php echo $enrollment_status; ?>
                                </span>
                                <?php if($enrollment_date): ?>
                                    <span style="color: var(--text-gray); font-size: 13px;">
                                        <i class="far fa-calendar"></i> Enrolled: <?php echo date('M d, Y', strtotime($enrollment_date)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="info-box">
                                <i class="fas fa-book-open"></i>
                                <p>You are currently enrolled in <?php echo $subjects_count; ?> subjects for this grade level.</p>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-graduation-cap"></i>
                                <h3>Not Enrolled</h3>
                                <p>You are not currently enrolled in any grade level.</p>
                                <p style="font-size: 13px; margin-top: 10px;">Please contact the registrar's office for assistance.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Links -->
                    <div class="academic-card">
                        <h3><i class="fas fa-link"></i> Quick Links</h3>
                        
                        <div class="quick-links-grid">
                            <a href="schedule.php" class="quick-link">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Class Schedule</span>
                            </a>
                            <a href="grades.php" class="quick-link">
                                <i class="fas fa-star"></i>
                                <span>My Grades</span>
                            </a>
                            <a href="enrollment_history.php" class="quick-link">
                                <i class="fas fa-history"></i>
                                <span>Enrollment History</span>
                            </a>
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
                                <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
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
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }

        // Image modal functions
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
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
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