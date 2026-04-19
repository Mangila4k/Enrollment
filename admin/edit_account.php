<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$admin_id = $_SESSION['user']['id'];
$error_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_accounts.php");
    exit();
}

$account_id = $_GET['id'];

// Get account details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$account_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$account) {
    header("Location: manage_accounts.php");
    exit();
}

// Get account profile picture
$account_profile_pic = $account['profile_picture'] ?? null;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_account'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
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
        
        if(empty($role)) {
            $errors[] = "Role is required";
        }
        
        // Check if email already exists (excluding current account)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->execute([$email, $account_id]);
            
            if($check_email->rowCount() > 0) {
                $errors[] = "Email address already registered to another user";
            }
        }
        
        // Check if ID number already exists (if provided and excluding current account)
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->execute([$id_number, $account_id]);
            
            if($check_id->rowCount() > 0) {
                $errors[] = "ID number already exists for another user";
            }
        }
        
        // If no errors, update the account
        if(empty($errors)) {
            if($id_number) {
                $update_query = "UPDATE users SET fullname = ?, email = ?, role = ?, id_number = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$fullname, $email, $role, $id_number, $account_id]);
            } else {
                $update_query = "UPDATE users SET fullname = ?, email = ?, role = ?, id_number = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$fullname, $email, $role, $account_id]);
            }
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['success_message'] = "Account updated successfully!";
                header("Location: view_account.php?id=" . $account_id);
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
    
    // Handle password reset
    if(isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } elseif(strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if(empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$hashed_password, $account_id]);
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['success_message'] = "Password reset successfully!";
                header("Location: view_account.php?id=" . $account_id);
                exit();
            } else {
                $errors[] = "Database error occurred.";
            }
        }
        
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Edit Account CSS -->
    <link rel="stylesheet" href="css/edit_account.css">
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
                <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                    <img src="../<?php echo $profile_picture; ?>" alt="Profile">
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
                    <li><a href="manage_accounts.php" class="active"><i class="fas fa-users-cog"></i> Accounts</a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">ACCOUNT</div>
                <ul class="nav-items">
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Edit Account</h1>
                <p>Update user account information</p>
            </div>
            <a href="manage_accounts.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Accounts</a>
        </div>

        <!-- Alert Messages -->
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Edit Account Form -->
        <div class="form-container">
            <div class="form-card">
                <h3><i class="fas fa-user-edit"></i> Edit Account Information</h3>
                
                <form method="POST" action="" id="editAccountForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($account['fullname']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($account['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span>*</span></label>
                            <select name="role" id="role" required>
                                <option value="Admin" <?php echo $account['role'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Registrar" <?php echo $account['role'] == 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                                <option value="Teacher" <?php echo $account['role'] == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="Student" <?php echo $account['role'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>ID Number</label>
                            <input type="text" name="id_number" id="id_number" value="<?php echo htmlspecialchars($account['id_number'] ?? ''); ?>" placeholder="Enter ID number">
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Leave blank if not applicable
                            </div>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-card">
                        <h4><i class="fas fa-eye"></i> Live Preview</h4>
                        <div class="preview-item">
                            <?php if($account_profile_pic && file_exists("../" . $account_profile_pic)): ?>
                                <div class="preview-avatar-img">
                                    <img src="../<?php echo $account_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                                </div>
                            <?php else: ?>
                                <div class="preview-avatar" id="previewInitial">
                                    <?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="preview-details">
                                <h5 id="previewName"><?php echo htmlspecialchars($account['fullname']); ?></h5>
                                <div>
                                    <span class="preview-role role-<?php echo strtolower($account['role']); ?>" id="previewRole">
                                        <?php echo $account['role']; ?>
                                    </span>
                                </div>
                                <div class="preview-email" id="previewEmail">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_account" class="btn-primary">
                            <i class="fas fa-save"></i> Update Account
                        </button>
                        <a href="view_account.php?id=<?php echo $account_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Password Reset Form -->
            <div class="form-card">
                <h3><i class="fas fa-key"></i> Password Management</h3>
                
                <div class="password-reset-section">
                    <div class="password-header">
                        <input type="checkbox" id="reset_password_checkbox">
                        <label for="reset_password_checkbox">Reset user password</label>
                    </div>

                    <form method="POST" action="" id="passwordForm">
                        <div class="password-fields" id="passwordFields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password <span>*</span></label>
                                    <div class="input-wrapper">
                                        <input type="password" name="new_password" id="new_password" disabled>
                                        <button type="button" class="toggle-password" onclick="togglePassword()">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-bar" id="strengthBar"></div>
                                    </div>
                                    <div class="strength-text" id="strengthText">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Minimum 6 characters</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Confirm Password <span>*</span></label>
                                    <div class="input-wrapper">
                                        <input type="password" name="confirm_password" id="confirm_password" disabled>
                                    </div>
                                    <div class="strength-text" id="passwordMatch">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Re-enter new password</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions" style="margin-top: 0; border-top: none; padding-top: 0;">
                                <button type="submit" name="reset_password" class="btn-warning" id="resetPasswordBtn" disabled>
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/edit_account.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const accountData = {
            id: <?php echo $account_id; ?>,
            name: '<?php echo htmlspecialchars($account['fullname']); ?>',
            email: '<?php echo htmlspecialchars($account['email']); ?>',
            role: '<?php echo $account['role']; ?>',
            initial: '<?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>'
        };
    </script>
</body>
</html>