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

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        // ID Number is NOT updated from form - it's read-only
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
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
        
        // Check if email already exists (excluding current student)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :student_id");
            $check_email->bindParam(':email', $email, PDO::PARAM_STR);
            $check_email->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $check_email->execute();
            
            if($check_email->rowCount() > 0) {
                $errors[] = "Email address already registered to another user";
            }
            $check_email = null;
        }
        
        // If no errors, update the profile (ID Number is NOT included in update)
        if(empty($errors)) {
            $update_query = "UPDATE users SET fullname = :fullname, email = :email WHERE id = :student_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
            $update_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $update_stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            
            if($update_stmt->execute()) {
                // Update session
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: settings.php");
                exit();
            } else {
                $errorInfo = $update_stmt->errorInfo();
                $errors[] = "Database error: " . ($errorInfo[2] ?? 'Unknown error');
            }
            $update_stmt = null;
        }
        
        // If there are errors, store them
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle password change
    if(isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Verify current password
        if(empty($current_password)) {
            $errors[] = "Current password is required";
        } else {
            if(!password_verify($current_password, $student['password'])) {
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
        
        // If no errors, update password
        if(empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password WHERE id = :student_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $update_stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            
            if($update_stmt->execute()) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: settings.php");
                exit();
            } else {
                $errorInfo = $update_stmt->errorInfo();
                $errors[] = "Database error: " . ($errorInfo[2] ?? 'Unknown error');
            }
            $update_stmt = null;
        }
        
        // If there are errors, store them
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle notification settings
    if(isset($_POST['save_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $grade_alerts = isset($_POST['grade_alerts']) ? 1 : 0;
        $attendance_alerts = isset($_POST['attendance_alerts']) ? 1 : 0;
        $announcements = isset($_POST['announcements']) ? 1 : 0;
        
        // In a real application, you would save these to a user_settings table
        $_SESSION['success_message'] = "Notification preferences saved successfully!";
        header("Location: settings.php");
        exit();
    }
    
    // Handle privacy settings
    if(isset($_POST['save_privacy'])) {
        $profile_visibility = $_POST['profile_visibility'];
        $show_grades = isset($_POST['show_grades']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        
        // In a real application, you would save these to a user_settings table
        $_SESSION['success_message'] = "Privacy settings saved successfully!";
        header("Location: settings.php");
        exit();
    }
}

// Get current settings (default values)
$email_notifications = true;
$sms_notifications = false;
$grade_alerts = true;
$attendance_alerts = true;
$announcements = true;
$profile_visibility = 'private';
$show_grades = false;
$show_attendance = false;

$account_created = $student['created_at'] ?? date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Settings CSS -->
    <link rel="stylesheet" href="css/settings.css">
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
                    <?php if(isset($profile_picture) && $profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
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
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <h1><i class="fas fa-cog" style="color: var(--primary);"></i> Settings</h1>
                    <p>Manage your account preferences and security</p>
                </div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($success_message) && $success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if(isset($error_message) && $error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <div class="settings-nav">
                <a href="#profile" class="settings-tab active" onclick="showTab('profile', event)">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="#account" class="settings-tab" onclick="showTab('account', event)">
                    <i class="fas fa-lock"></i> Account Security
                </a>
                <a href="#notifications" class="settings-tab" onclick="showTab('notifications', event)">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="#privacy" class="settings-tab" onclick="showTab('privacy', event)">
                    <i class="fas fa-shield-alt"></i> Privacy
                </a>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile" class="settings-tab-content">
                <div class="settings-card">
                    <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($student['fullname'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>ID Number</label>
                                <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($student['id_number'] ?? 'Not assigned'); ?>" readonly disabled>
                                <small style="color: var(--text-gray); font-size: 12px; display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> ID Number cannot be changed. Please contact the registrar's office for corrections.
                                </small>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="Enter your phone number">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter your complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>Your grade level, strand, and ID Number cannot be changed here. Please contact the registrar's office for updates.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Security Tab -->
            <div id="account" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
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
                                    <input type="password" name="current_password" id="current_password" class="form-control" disabled>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>New Password <span class="required">*</span></label>
                                        <input type="password" name="new_password" id="new_password" class="form-control" disabled>
                                        <div class="password-strength">
                                            <div class="password-strength-bar" id="passwordStrength"></div>
                                        </div>
                                        <div class="password-strength-text" id="passwordStrengthText">
                                            <i class="fas fa-info-circle"></i> Minimum 6 characters
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Confirm Password <span class="required">*</span></label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" disabled>
                                        <div class="password-strength-text" id="passwordMatch">
                                            <i class="fas fa-info-circle"></i> Re-enter new password
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn-primary" id="changePasswordBtn" disabled>
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                    <div class="activity-info">
                        <div class="activity-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <div class="activity-label">Last Login</div>
                                <div class="activity-value"><?php echo isset($student['created_at']) ? date('F d, Y h:i A', strtotime($student['created_at'])) : 'N/A'; ?></div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div>
                                <div class="activity-label">Account Created</div>
                                <div class="activity-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                    <form method="POST" action="">
                        <h4>Email Notifications</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                            <label for="email_notifications">Receive email notifications</label>
                        </div>

                        <h4>Alert Types</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="grade_alerts" name="grade_alerts" <?php echo $grade_alerts ? 'checked' : ''; ?>>
                            <label for="grade_alerts">Grade updates and alerts</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="attendance_alerts" name="attendance_alerts" <?php echo $attendance_alerts ? 'checked' : ''; ?>>
                            <label for="attendance_alerts">Attendance reminders</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="announcements" name="announcements" <?php echo $announcements ? 'checked' : ''; ?>>
                            <label for="announcements">School announcements</label>
                        </div>

                        <h4>SMS Notifications</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $sms_notifications ? 'checked' : ''; ?>>
                            <label for="sms_notifications">Receive SMS notifications</label>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-mobile-alt"></i>
                            <p>SMS notifications require a valid phone number in your profile.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_notifications" class="btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Privacy Tab -->
            <div id="privacy" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3><i class="fas fa-shield-alt"></i> Privacy Settings</h3>
                    <form method="POST" action="">
                        <h4>Profile Visibility</h4>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="public" name="profile_visibility" value="public" <?php echo $profile_visibility == 'public' ? 'checked' : ''; ?>>
                                <label for="public">Public - Anyone can view your profile</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="private" name="profile_visibility" value="private" <?php echo $profile_visibility == 'private' ? 'checked' : ''; ?>>
                                <label for="private">Private - Only you and school staff</label>
                            </div>
                        </div>

                        <h4>Data Sharing</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="show_grades" name="show_grades" <?php echo $show_grades ? 'checked' : ''; ?>>
                            <label for="show_grades">Allow parents/guardians to view grades</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="show_attendance" name="show_attendance" <?php echo $show_attendance ? 'checked' : ''; ?>>
                            <label for="show_attendance">Allow parents/guardians to view attendance</label>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-user-shield"></i>
                            <p>Your data is protected and will only be shared according to school policy.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_privacy" class="btn-primary">
                                <i class="fas fa-save"></i> Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
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
        
        // Tab switching
        function showTab(tabId, event) {
            if (event) {
                event.preventDefault();
            }
            
            // Hide all tab contents
            document.querySelectorAll('.settings-tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).style.display = 'block';
            
            // Add active class to clicked tab
            if (event && event.target) {
                event.target.classList.add('active');
            } else {
                // Find the tab with matching href
                document.querySelectorAll('.settings-tab').forEach(tab => {
                    if (tab.getAttribute('href') === '#' + tabId) {
                        tab.classList.add('active');
                    }
                });
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
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if(strengthBar) strengthBar.style.width = '0';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
            if(passwordMatch) passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> Re-enter new password';
        }

        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            let strength = 0;
            let strengthLabel = '';

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;

            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');

            switch(strength) {
                case 0:
                case 1:
                    if(strengthBar) strengthBar.style.width = '20%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#ef4444';
                    strengthLabel = 'Weak';
                    break;
                case 2:
                case 3:
                    if(strengthBar) strengthBar.style.width = '60%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#f59e0b';
                    strengthLabel = 'Medium';
                    break;
                case 4:
                case 5:
                    if(strengthBar) strengthBar.style.width = '100%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#10b981';
                    strengthLabel = 'Strong';
                    break;
            }

            if (password.length > 0 && strengthText) {
                strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> Password strength: ${strengthLabel}`;
            } else if (strengthText) {
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Minimum 6 characters';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');

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

        // Show first tab by default
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('profile').style.display = 'block';
        });
    </script>
</body>
</html>