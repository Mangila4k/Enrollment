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
$success_message = '';
$error_message = '';

// Function to send notification to admins
function notifyAdmins($conn, $title, $message, $link, $type = 'alert') {
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('Admin', 'Registrar')");
    $admin_stmt->execute();
    foreach($admin_stmt->fetchAll(PDO::FETCH_ASSOC) as $admin) {
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

// Get student details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'Student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$email_verified = $student['email_verified'] ?? 0;
$current_email = $student['email'];
$profile_picture = $student['profile_picture'] ?? null;
$sidebar_profile_pic = $student['profile_picture'] ?? null;
$_SESSION['user']['profile_picture'] = $profile_picture;

// Check for pending email change
$has_pending_email = false;
$pending_email = '';
$stmt = $conn->prepare("SELECT pending_email FROM users WHERE id = ? AND pending_email IS NOT NULL");
$stmt->execute([$student_id]);
if($stmt->rowCount() > 0) {
    $has_pending_email = true;
    $pending_email = $stmt->fetch(PDO::FETCH_ASSOC)['pending_email'];
}

// Handle email verification
if(isset($_POST['send_verification'])) {
    require_once '../config/email_config.php';
    $verification_code = sprintf("%06d", mt_rand(100000, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $update = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $update->execute([$verification_code, $verification_expires, $student_id]);
    
    if(sendVerificationCode($student['email'], $student['fullname'], $verification_code)) {
        $_SESSION['temp_email'] = $student['email'];
        $_SESSION['success_message'] = "A verification code has been sent to your email.";
        header("Location: verify_email.php");
        exit();
    } else {
        $error_message = "Failed to send verification email.";
    }
}

// Handle email change request
if(isset($_POST['send_email_verification'])) {
    $new_email = trim($_POST['new_email']);
    if(empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->execute([$new_email, $student_id]);
        if($check_email->rowCount() > 0) {
            $error_message = "Email already registered.";
        } else {
            require_once '../config/email_config.php';
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $update = $conn->prepare("UPDATE users SET pending_email = ?, pending_email_code = ?, pending_email_expires = ? WHERE id = ?");
            $update->execute([$new_email, $verification_code, $expires, $student_id]);
            
            $subject = "Email Change Verification - PLSNHS";
            $message = "<div class='container'><div class='header'><h2>Email Change Request</h2></div><p>Hello <strong>{$student['fullname']}</strong>,</p><p>You requested to change your email to: <strong>$new_email</strong></p><p>Verification code: <strong>$verification_code</strong></p><p>Valid for 1 hour.</p></div>";
            
            if(sendCustomEmail($new_email, $student['fullname'], $subject, $message)) {
                $_SESSION['pending_email_change'] = true;
                $_SESSION['success_message'] = "Verification code sent to: $new_email";
                header("Location: profile.php");
                exit();
            } else {
                $error_message = "Failed to send verification email.";
            }
        }
    }
}

// Handle verify new email
if(isset($_POST['verify_new_email'])) {
    $verification_code = trim($_POST['verification_code']);
    $stmt = $conn->prepare("SELECT pending_email, pending_email_code, pending_email_expires FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($pending['pending_email'] && $pending['pending_email_code']) {
        if(strtotime($pending['pending_email_expires']) < time()) {
            $error_message = "Verification code expired.";
            $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $clear->execute([$student_id]);
        } elseif($pending['pending_email_code'] == $verification_code) {
            $new_email = $pending['pending_email'];
            $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
            $update->execute([$new_email, $student_id]);
            $_SESSION['user']['email'] = $new_email;
            notifyAdmins($conn, "📧 Email Changed", "Student $student_name changed email to: $new_email", "../admin/students.php?view=$student_id", 'alert');
            $_SESSION['success_message'] = "Email changed successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $error_message = "Invalid verification code.";
        }
    }
}

// Handle cancel email change
if(isset($_POST['cancel_email_change'])) {
    $clear = $conn->prepare("UPDATE users SET pending_email = NULL, pending_email_code = NULL, pending_email_expires = NULL WHERE id = ?");
    $clear->execute([$student_id]);
    unset($_SESSION['pending_email_change']);
    $_SESSION['success_message'] = "Email change cancelled.";
    header("Location: profile.php");
    exit();
}

// Handle profile picture upload
if(isset($_POST['upload_profile_pic']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    
    if(in_array($ext, $allowed)) {
        $upload_dir = "../uploads/profile_pictures/";
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        if($profile_picture && file_exists("../" . $profile_picture)) unlink("../" . $profile_picture);
        
        $new_filename = "student_{$student_id}_" . time() . ".$ext";
        $upload_path = $upload_dir . $new_filename;
        $db_path = "uploads/profile_pictures/" . $new_filename;
        
        if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
            $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $update_stmt->execute([$db_path, $student_id]);
            $_SESSION['user']['profile_picture'] = $db_path;
            notifyAdmins($conn, "🖼️ Profile Picture Updated", "Student $student_name updated profile picture", "../admin/students.php?view=$student_id", 'update');
            $_SESSION['success_message'] = "Profile picture updated!";
            header("Location: profile.php?success=1");
            exit();
        } else {
            $error_message = "Failed to upload image.";
        }
    } else {
        $error_message = "Invalid file type. Allowed: JPG, PNG, GIF, WEBP";
    }
}

// Handle remove profile picture
if(isset($_GET['remove_pic'])) {
    if($profile_picture && file_exists("../" . $profile_picture)) unlink("../" . $profile_picture);
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
    $update_stmt->execute([$student_id]);
    unset($_SESSION['user']['profile_picture']);
    notifyAdmins($conn, "🖼️ Profile Picture Removed", "Student $student_name removed profile picture", "../admin/students.php?view=$student_id", 'update');
    $_SESSION['success_message'] = "Profile picture removed!";
    header("Location: profile.php?success=2");
    exit();
}

// Handle password change
if(isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    $errors = [];
    
    if(empty($current) || !password_verify($current, $student['password'])) $errors[] = "Current password is incorrect";
    
    if(empty($new)) {
        $errors[] = "New password required";
    } else {
        if(strlen($new) < 8) $errors[] = "at least 8 characters";
        if(!preg_match('/[A-Z]/', $new)) $errors[] = "at least one uppercase letter";
        if(!preg_match('/[a-z]/', $new)) $errors[] = "at least one lowercase letter";
        if(!preg_match('/[0-9]/', $new)) $errors[] = "at least one number";
        if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)) $errors[] = "at least one special character";
        if(!empty($errors) && $errors[0] != "Current password is incorrect") {
            $errors = ["Password must contain: " . implode(", ", $errors)];
        }
    }
    
    if($new !== $confirm) $errors[] = "Passwords do not match";
    
    if(empty($errors)) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$hashed, $student_id]);
        notifyAdmins($conn, "🔐 Password Changed", "Student $student_name changed password", "../admin/students.php?view=$student_id", 'alert');
        $_SESSION['success_message'] = "Password changed successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get enrollment info
$stmt = $conn->prepare("SELECT e.*, g.grade_name FROM enrollments e JOIN grade_levels g ON e.grade_id = g.id WHERE e.student_id = ? AND e.status = 'Enrolled' ORDER BY e.created_at DESC LIMIT 1");
$stmt->execute([$student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';
$enrollment_date = $enrollment ? $enrollment['created_at'] : null;

$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE grade_id = ?");
    $stmt->execute([$enrollment['grade_id']]);
    $subjects_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

$account_created = $student['created_at'] ?? date('Y-m-d H:i:s');
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></div>

    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><img src="../pictures/logo sa skwelahan.jpg" alt="Logo"></div>
                    <div class="logo-text">PLS<span>NHS</span></div>
                </div>
                <div class="school-badge">Placido L. Señor NHS</div>
            </div>
            <div class="student-profile">
                <div class="student-avatar">
                    <?php if($sidebar_profile_pic && file_exists("../" . $sidebar_profile_pic)): ?>
                        <img src="../<?php echo $sidebar_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></div>
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

        <main class="main-content">
            <div class="page-header">
                <div><h1>My Profile</h1><p>View and manage your personal information</p></div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Left Column -->
                <div>
                    <div class="profile-card">
                        <div class="profile-avatar-large" onclick="openImageModal()">
                            <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                                <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-initial"><?php echo strtoupper(substr($student['fullname'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div class="avatar-overlay"><i class="fas fa-camera"></i></div>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <span class="profile-role">Student</span>
                        <div class="profile-stats">
                            <div class="stat-item"><div class="stat-value"><?php echo $days_active; ?></div><div class="stat-label">Days Active</div></div>
                        </div>
                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($current_email); ?>
                                    <?php if($email_verified): ?>
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

                <!-- Right Column -->
                <div>
                    <!-- Email Verification -->
                    <div class="academic-card">
                        <div class="verification-header"><i class="fas fa-envelope"></i><h3>Email Verification</h3></div>
                        <?php if($email_verified): ?>
                            <div class="verification-badge verified"><i class="fas fa-check-circle"></i> Verified Email</div>
                            <div class="verification-info"><p>Your email address has been verified.</p></div>
                        <?php else: ?>
                            <div class="verification-badge unverified"><i class="fas fa-exclamation-triangle"></i> Email Not Verified</div>
                            <div class="verification-info">
                                <p>Verifying your email helps secure your account.</p>
                                <form method="POST"><button type="submit" name="send_verification" class="btn-verify"><i class="fas fa-paper-plane"></i> Verify Email Now</button></form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Change Email -->
                    <div class="academic-card">
                        <div class="verification-header"><i class="fas fa-exchange-alt"></i><h3>Change Email Address</h3></div>
                        <?php if($has_pending_email): ?>
                            <div class="pending-email-alert"><i class="fas fa-clock"></i> Pending: Verification sent to <strong><?php echo htmlspecialchars($pending_email); ?></strong></div>
                            <form method="POST">
                                <div class="form-group"><input type="text" name="verification_code" class="verify-code-input" placeholder="000000" maxlength="6" required></div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" name="verify_new_email" class="btn-verify" style="background: #28a745;"><i class="fas fa-check"></i> Verify & Change</button>
                                    <button type="submit" name="cancel_email_change" class="btn-verify" style="background: #dc3545;"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST">
                                <div class="form-group"><input type="email" name="new_email" placeholder="Enter new email address" required></div>
                                <button type="submit" name="send_email_verification" class="btn-verify"><i class="fas fa-paper-plane"></i> Send Verification Code</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Change Password -->
                    <div class="academic-card">
                        <div class="verification-header"><i class="fas fa-lock"></i><h3>Change Password</h3></div>
                        <div class="password-section">
                            <div class="password-header">
                                <input type="checkbox" id="change_password_checkbox">
                                <label for="change_password_checkbox">I want to change my password</label>
                            </div>
                            <form method="POST" id="passwordForm">
                                <div class="password-fields" id="passwordFields" style="display: none;">
                                    <div class="form-group"><input type="password" name="current_password" id="current_password" placeholder="Current Password" class="password-input"></div>
                                    <div class="form-group">
                                        <input type="password" name="new_password" id="new_password" placeholder="New Password" class="password-input">
                                        <div class="password-strength"><div class="strength-bar" id="strengthBar"></div></div>
                                        <div class="strength-text" id="strengthText"><i class="fas fa-info-circle"></i> Enter new password</div>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" class="password-input">
                                        <div class="password-match" id="passwordMatch"><i class="fas fa-info-circle"></i> Re-enter new password</div>
                                    </div>
                                    <div class="password-requirements">
                                        <small><i class="fas fa-shield-alt"></i> Password must contain:</small>
                                        <ul>
                                            <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                                            <li id="req-upper"><i class="fas fa-circle"></i> At least 1 uppercase letter</li>
                                            <li id="req-lower"><i class="fas fa-circle"></i> At least 1 lowercase letter</li>
                                            <li id="req-number"><i class="fas fa-circle"></i> At least 1 number</li>
                                            <li id="req-special"><i class="fas fa-circle"></i> At least 1 special character</li>
                                        </ul>
                                    </div>
                                    <button type="submit" name="change_password" class="btn-submit" id="changePasswordBtn" disabled><i class="fas fa-key"></i> Change Password</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="academic-card">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                        <?php if($enrollment): ?>
                            <div class="academic-grid">
                                <div class="academic-item"><div class="academic-value"><?php echo htmlspecialchars($grade_name); ?></div><div class="academic-label">Grade Level</div></div>
                                <div class="academic-item"><div class="academic-value"><?php echo htmlspecialchars($strand); ?></div><div class="academic-label">Strand</div></div>
                                <div class="academic-item"><div class="academic-value"><?php echo htmlspecialchars($school_year); ?></div><div class="academic-label">School Year</div></div>
                            </div>
                            <div class="info-box"><i class="fas fa-book-open"></i><p>You are enrolled in <?php echo $subjects_count; ?> subjects.</p></div>
                        <?php else: ?>
                            <div class="no-data"><i class="fas fa-graduation-cap"></i><p>Not enrolled. Contact registrar's office.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Upload Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-camera"></i> Update Profile Picture</h3><button class="close-modal" onclick="closeImageModal()">&times;</button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="image-preview" id="imagePreview">
                        <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                            <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Preview">
                        <?php else: ?>
                            <div class="avatar-initial"><?php echo strtoupper(substr($student['fullname'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="file-input-wrapper">
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" onchange="previewImage(this)">
                        <label for="profile_picture" class="file-input-label"><i class="fas fa-upload"></i> Choose Image</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if($profile_picture): ?>
                        <a href="?remove_pic=1" class="btn-danger" onclick="return confirm('Remove profile picture?')"><i class="fas fa-trash"></i> Remove</a>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" onclick="closeImageModal()">Cancel</button>
                    <button type="submit" name="upload_profile_pic" class="btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/profile.js"></script>
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if(menuToggle) menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        
        document.addEventListener('click', (e) => {
            if(window.innerWidth <= 768 && sidebar && menuToggle && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
        
        // Image modal
        function openImageModal() { document.getElementById('imageModal').classList.add('active'); }
        function closeImageModal() { document.getElementById('imageModal').classList.remove('active'); }
        function previewImage(input) {
            if(input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => document.getElementById('imagePreview').innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                reader.readAsDataURL(input.files[0]);
            }
        }
        window.onclick = (event) => { if(event.target == document.getElementById('imageModal')) closeImageModal(); }
        
        // Auto-format code input
        document.querySelectorAll('.verify-code-input').forEach(input => {
            input.addEventListener('input', (e) => input.value = input.value.replace(/[^0-9]/g, '').slice(0, 6));
        });
        
        // Password change toggle
        const changePwdCheckbox = document.getElementById('change_password_checkbox');
        const pwdFields = document.getElementById('passwordFields');
        const currentPwd = document.getElementById('current_password');
        const newPwd = document.getElementById('new_password');
        const confirmPwd = document.getElementById('confirm_password');
        const changePwdBtn = document.getElementById('changePasswordBtn');
        
        if(changePwdCheckbox) {
            changePwdCheckbox.addEventListener('change', function() {
                if(this.checked) {
                    pwdFields.style.display = 'block';
                    if(currentPwd) currentPwd.disabled = false;
                    if(newPwd) newPwd.disabled = false;
                    if(confirmPwd) confirmPwd.disabled = false;
                    if(changePwdBtn) changePwdBtn.disabled = true;
                    if(newPwd) newPwd.focus();
                } else {
                    pwdFields.style.display = 'none';
                    if(currentPwd) { currentPwd.disabled = true; currentPwd.value = ''; }
                    if(newPwd) { newPwd.disabled = true; newPwd.value = ''; }
                    if(confirmPwd) { confirmPwd.disabled = true; confirmPwd.value = ''; }
                    if(changePwdBtn) changePwdBtn.disabled = true;
                    resetStrength();
                }
            });
        }
        
        function resetStrength() {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const matchText = document.getElementById('passwordMatch');
            if(strengthBar) strengthBar.style.width = '0';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Enter new password';
            if(matchText) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
            ['length','upper','lower','number','special'].forEach(r => {
                const el = document.getElementById(`req-${r}`);
                if(el) { el.classList.remove('valid'); el.innerHTML = '<i class="fas fa-circle"></i> ' + el.innerText.replace(/[✓✔✅]/g, '').trim(); }
            });
        }
        
        function validatePwd(pwd) {
            return {
                length: pwd.length >= 8,
                uppercase: /[A-Z]/.test(pwd),
                lowercase: /[a-z]/.test(pwd),
                number: /[0-9]/.test(pwd),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(pwd)
            };
        }
        
        function updateStrength() {
            const pwd = newPwd ? newPwd.value : '';
            const validation = validatePwd(pwd);
            
            ['length','uppercase','lowercase','number','special'].forEach(r => {
                const el = document.getElementById(`req-${r}`);
                if(el) {
                    if(validation[r]) {
                        el.classList.add('valid');
                        el.innerHTML = '<i class="fas fa-check-circle"></i> ' + el.innerText.replace(/[✓✔✅]/g, '').trim();
                    } else {
                        el.classList.remove('valid');
                        el.innerHTML = '<i class="fas fa-circle"></i> ' + el.innerText.replace(/[✓✔✅]/g, '').trim();
                    }
                }
            });
            
            const validCount = Object.values(validation).filter(v => v).length;
            const percent = (validCount / 5) * 100;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            if(strengthBar) {
                strengthBar.style.width = percent + '%';
                if(percent <= 25) { strengthBar.style.backgroundColor = '#ef4444'; if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color:#ef4444">Weak</span>'; }
                else if(percent <= 50) { strengthBar.style.backgroundColor = '#f59e0b'; if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color:#f59e0b">Fair</span>'; }
                else if(percent <= 75) { strengthBar.style.backgroundColor = '#3b82f6'; if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color:#3b82f6">Good</span>'; }
                else { strengthBar.style.backgroundColor = '#10b981'; if(strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color:#10b981">Strong</span>'; }
            }
            
            checkMatch();
            const isStrong = Object.values(validation).every(v => v);
            if(changePwdBtn) changePwdBtn.disabled = !(isStrong && confirmPwd && pwd === confirmPwd.value);
        }
        
        function checkMatch() {
            const matchText = document.getElementById('passwordMatch');
            if(newPwd && confirmPwd) {
                if(confirmPwd.value.length === 0) matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
                else if(newPwd.value === confirmPwd.value) matchText.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981"></i> <span style="color:#10b981">Passwords match</span>';
                else matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444"></i> <span style="color:#ef4444">Passwords do not match</span>';
            }
        }
        
        if(newPwd) newPwd.addEventListener('input', updateStrength);
        if(confirmPwd) confirmPwd.addEventListener('input', checkMatch);
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>