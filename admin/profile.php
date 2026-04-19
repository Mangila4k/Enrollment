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

// Check if profile_picture column exists, if not, add it
try {
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
    }
} catch(PDOException $e) {
    // Column might already exist or other error
}

// Handle profile picture upload
if(isset($_POST['upload_profile_pic'])) {
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            // Create uploads directory if not exists
            $upload_dir = "../uploads/profile_pictures/";
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = "admin_" . $admin_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            // Delete old profile picture if exists
            if(!empty($admin['profile_picture']) && file_exists("../" . $admin['profile_picture'])) {
                unlink("../" . $admin['profile_picture']);
            }
            
            // Upload new file
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if($update_stmt->execute([$db_path, $admin_id])) {
                    // Update session with profile picture path
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
        // Update session
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
        // Start 2FA verification for enabling
        start2FAVerification(
            $admin_id,
            $admin['email'],
            $admin['fullname'],
            'enable_2fa',
            'profile.php'
        );
    } elseif($action == 'disable') {
        // Start 2FA verification for disabling
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
        // Verification is still valid
        // The actual enable/disable was handled in verify_2fa.php
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
        
        // Refresh admin data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
    }
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    // Validation
    $errors = [];
    
    if(empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($email)) {
        $errors[] = "Email address is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (excluding current admin)
    if(empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->execute([$email, $admin_id]);
        
        if($check_email->rowCount() > 0) {
            $errors[] = "Email address already registered to another user";
        }
    }
    
    // Check if ID number already exists (if provided and excluding current admin)
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
        $check_id->execute([$id_number, $admin_id]);
        
        if($check_id->rowCount() > 0) {
            $errors[] = "ID number already exists for another user";
        }
    }
    
    // If no errors, update the profile
    if(empty($errors)) {
        $update_query = "UPDATE users SET fullname = ?, email = ?, id_number = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([$fullname, $email, $id_number, $admin_id])) {
            // Update session
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['id_number'] = $id_number;
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Database error occurred.";
        }
    }
    
    // If there are errors, store them
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
    
    // Verify current password
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
    
    // If no errors, check if 2FA is enabled
    if(empty($errors)) {
        if($admin['two_factor_enabled'] == 1) {
            // Start 2FA verification for password change
            start2FAVerification(
                $admin_id,
                $admin['email'],
                $admin['fullname'],
                'password_change',
                'profile.php'
            );
        } else {
            // Proceed with password change without 2FA
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
    
    // If there are errors, store them
    if(!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}

$account_created = $admin['created_at'];
$two_factor_enabled = $admin['two_factor_enabled'] ?? 0;
$two_factor_last_used = $admin['two_factor_last_used'] ?? null;
$profile_picture = $admin['profile_picture'] ?? null;

// Get profile picture for sidebar
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? $profile_picture;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PLSNHS | Placido L. Señor National High School</title>
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
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['email']); ?></div>
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

                    <!-- Edit Profile Form -->
                    <div class="form-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
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
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

        // Toggle password fields
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
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;

            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');

            switch(strength) {
                case 0:
                case 1:
                    if(strengthBar) strengthBar.style.width = '20%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#ef4444';
                    strengthLabel = 'Weak';
                    strengthColor = 'strength-weak';
                    break;
                case 2:
                case 3:
                    if(strengthBar) strengthBar.style.width = '60%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#f59e0b';
                    strengthLabel = 'Medium';
                    strengthColor = 'strength-medium';
                    break;
                case 4:
                case 5:
                    if(strengthBar) strengthBar.style.width = '100%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#10b981';
                    strengthLabel = 'Strong';
                    strengthColor = 'strength-strong';
                    break;
            }

            if (password.length > 0 && strengthText) {
                strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span class="${strengthColor}">Password strength: ${strengthLabel}</span>`;
            } else if (strengthText) {
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
            }
        }

        function checkPasswordMatch() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            const matchText = document.getElementById('passwordMatchText');

            if (confirm.length > 0 && matchText) {
                if (password === confirm) {
                    matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
                } else {
                    matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
                }
            } else if (matchText) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
            }
        }

        if(newPassword) {
            newPassword.addEventListener('input', checkPasswordStrength);
            newPassword.addEventListener('input', checkPasswordMatch);
        }
        
        if(confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>