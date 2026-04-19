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
            $new_filename = "registrar_" . $registrar_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            // Delete old profile picture if exists
            if(!empty($profile_picture) && file_exists("../" . $profile_picture)) {
                unlink("../" . $profile_picture);
            }
            
            // Upload new file
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if($update_stmt->execute([$db_path, $registrar_id])) {
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
    if(!empty($profile_picture) && file_exists("../" . $profile_picture)) {
        unlink("../" . $profile_picture);
    }
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
    if($update_stmt->execute([$registrar_id])) {
        // Update session
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

// Get registrar details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Registrar'";
$stmt = $conn->prepare($query);
$stmt->execute([$registrar_id]);
$registrar = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profile picture variable after possible changes
$profile_picture = $registrar['profile_picture'] ?? null;

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status IN ('Enrolled', 'Rejected')");
$enrollments_processed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'");
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate processing rate
$processing_rate = $total_students > 0 ? round(($enrollments_processed / $total_students) * 100) : 0;

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
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
        
        // Check if email already exists (excluding current registrar)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->execute([$email, $registrar_id]);
            
            if($check_email->rowCount() > 0) {
                $errors[] = "Email address already registered to another user";
            }
        }
        
        // Check if ID number already exists (if provided and excluding current registrar)
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->execute([$id_number, $registrar_id]);
            
            if($check_id->rowCount() > 0) {
                $errors[] = "ID number already exists for another user";
            }
        }
        
        // If no errors, update the profile
        if(empty($errors)) {
            $update_query = "UPDATE users SET fullname = ?, email = ?, id_number = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$fullname, $email, $id_number, $registrar_id]);
            
            if($update_stmt->rowCount() >= 0) {
                // Update session
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['id_number'] = $id_number;
                
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Database error occurred";
            }
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
            if(!password_verify($current_password, $registrar['password'])) {
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
        
        // If there are errors, store them
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
                            <div class="info-value"><?php echo htmlspecialchars($registrar['email']); ?></div>
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

                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($registrar['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Employee ID</label>
                            <input type="text" name="id_number" value="<?php echo htmlspecialchars($registrar['id_number'] ?? ''); ?>" placeholder="Enter your employee ID">
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
                                            <div class="password-strength-bar" id="passwordStrength"></div>
                                        </div>
                                        <div class="password-strength-text" id="passwordStrengthText">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Minimum 6 characters</span>
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

    <!-- JavaScript -->
    <script src="js/profile.js"></script>
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