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

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get teacher details
$query = "SELECT * FROM users WHERE id = :teacher_id AND role = 'Teacher'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

// Get profile picture from database
$profile_picture = $teacher['profile_picture'] ?? null;
$sidebar_profile_pic = $teacher['profile_picture'] ?? null;
$_SESSION['user']['profile_picture'] = $profile_picture;

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
    $success_message = "Profile picture removed successfully!";
    header("Location: profile.php?success=2");
    exit();
}

// Handle profile update
if(isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    $errors = [];
    
    if(empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($email)) {
        $errors[] = "Email address is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists
    if(empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :teacher_id");
        $check_email->execute([
            ':email' => $email,
            ':teacher_id' => $teacher_id
        ]);
        
        if($check_email->rowCount() > 0) {
            $errors[] = "Email address already registered to another user";
        }
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
        $update_query = "UPDATE users SET fullname = :fullname, email = :email, id_number = :id_number WHERE id = :teacher_id";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([
            ':fullname' => $fullname,
            ':email' => $email,
            ':id_number' => $id_number,
            ':teacher_id' => $teacher_id
        ])) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['id_number'] = $id_number;
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
        if(!password_verify($current_password, $teacher['password'])) {
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
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = :password WHERE id = :teacher_id";
        $update_stmt = $conn->prepare($update_query);
        
        if($update_stmt->execute([
            ':password' => $hashed_password,
            ':teacher_id' => $teacher_id
        ])) {
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
                                <div class="info-value"><?php echo htmlspecialchars($teacher['email']); ?></div>
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
                                <label>Email Address <span class="required">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
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
                                            <div class="password-strength">
                                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                            </div>
                                            <div class="password-strength-text" id="passwordStrengthText">
                                                <i class="fas fa-info-circle"></i> Minimum 6 characters
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

                    <!-- Quick Links -->
                    <div class="academic-card">
                        <h3><i class="fas fa-link"></i> Quick Links</h3>
                        
                        <div class="quick-links-grid">
                            <a href="attendance_qr.php" class="quick-link">
                                <i class="fas fa-qrcode"></i>
                                <span>QR Attendance</span>
                            </a>
                            <a href="classes.php" class="quick-link">
                                <i class="fas fa-users"></i>
                                <span>My Classes</span>
                            </a>
                            <a href="schedule.php" class="quick-link">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Schedule</span>
                            </a>
                            <a href="grades.php" class="quick-link">
                                <i class="fas fa-star"></i>
                                <span>Grades</span>
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
                    strengthColor = '#ef4444';
                    break;
                case 2:
                case 3:
                    if(strengthBar) strengthBar.style.width = '60%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#f59e0b';
                    strengthLabel = 'Medium';
                    strengthColor = '#f59e0b';
                    break;
                case 4:
                case 5:
                    if(strengthBar) strengthBar.style.width = '100%';
                    if(strengthBar) strengthBar.style.backgroundColor = '#10b981';
                    strengthLabel = 'Strong';
                    strengthColor = '#10b981';
                    break;
            }

            if (password.length > 0 && strengthText) {
                strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: ${strengthColor};">Password strength: ${strengthLabel}</span>`;
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
    </script>
</body>
</html>