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
$success_message = '';
$error_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if teacher ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher information
$teacher_query = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT s.id) as section_count,
           GROUP_CONCAT(DISTINCT s.section_name SEPARATOR ', ') as sections
    FROM users u
    LEFT JOIN sections s ON u.id = s.adviser_id
    WHERE u.id = ? AND u.role = 'Teacher'
    GROUP BY u.id
");

$teacher_query->execute([$teacher_id]);
$teacher_result = $teacher_query->fetchAll(PDO::FETCH_ASSOC);

if(count($teacher_result) === 0) {
    header("Location: teachers.php");
    exit();
}

$teacher = $teacher_result[0];
$teacher_profile_picture = $teacher['profile_picture'] ?? null;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    $specialization = trim($_POST['specialization']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $change_password = isset($_POST['change_password']) ? true : false;
    
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
    
    // Check if email already exists (excluding current teacher)
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->execute([$email, $teacher_id]);
    
    if($check_email->rowCount() > 0) {
        $errors[] = "Email address already registered to another user";
    }
    
    // Check if ID number already exists (if provided and excluding current teacher)
    if($id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
        $check_id->execute([$id_number, $teacher_id]);
        
        if($check_id->rowCount() > 0) {
            $errors[] = "ID number already exists for another user";
        }
    }
    
    // Password validation if changing
    $new_password = '';
    if($change_password) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } elseif(strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // If no errors, update the teacher
    if(empty($errors)) {
        try {
            if($change_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET id_number = ?, fullname = ?, email = ?, password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$id_number, $fullname, $email, $hashed_password, $teacher_id]);
            } else {
                $update_query = "UPDATE users SET id_number = ?, fullname = ?, email = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$id_number, $fullname, $email, $teacher_id]);
            }
            
            $_SESSION['success_message'] = "Teacher information updated successfully!";
            header("Location: teachers.php");
            exit();
            
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If there are errors, store them
    if(!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/edit_teacher.css">
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
                    <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
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
                <h1>Edit Teacher</h1>
                <p>Update teacher information and credentials</p>
            </div>
            <a href="teachers.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Teachers</a>
        </div>

        <!-- Alert Messages -->
        <?php if($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-container">
            <div class="form-card">
                <h3><i class="fas fa-edit"></i> Edit Teacher Information</h3>

                <form method="POST" action="" id="teacherForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" 
                                   name="fullname" 
                                   id="fullname"
                                   placeholder="e.g., Juan Dela Cruz" 
                                   value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : htmlspecialchars($teacher['fullname']); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   placeholder="teacher@plshs.edu.ph" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($teacher['email']); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
    <label>ID Number</label>
    <input type="text" 
           name="id_number" 
           id="id_number"
           class="readonly-field"
           placeholder="e.g., TCH-2024-001" 
           value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : htmlspecialchars($teacher['id_number'] ?? ''); ?>"
           readonly
           style="background-color: #f5f5f5; cursor: not-allowed;">
    <div class="form-hint">
        <i class="fas fa-info-circle"></i>
        ID number is auto-generated and cannot be changed
    </div>
</div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" 
                                   name="phone" 
                                   id="phone"
                                   placeholder="e.g., 09123456789" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" 
                               name="specialization" 
                               id="specialization"
                               placeholder="e.g., Mathematics, Science, English" 
                               value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" 
                                  id="address" 
                                  rows="3"
                                  placeholder="Complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <!-- Password Change Section -->
                    <div class="password-section">
                        <div class="password-header">
                            <input type="checkbox" id="change_password" name="change_password">
                            <label for="change_password">Change Password</label>
                        </div>
                        
                        <div class="password-fields" id="passwordFields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <div class="input-wrapper">
                                        <input type="password" 
                                               name="new_password" 
                                               id="new_password"
                                               placeholder="Enter new password"
                                               <?php echo isset($_POST['change_password']) ? '' : 'disabled'; ?>>
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
                                    <label>Confirm Password</label>
                                    <div class="input-wrapper">
                                        <input type="password" 
                                               name="confirm_password" 
                                               id="confirm_password"
                                               placeholder="Confirm new password"
                                               <?php echo isset($_POST['change_password']) ? '' : 'disabled'; ?>>
                                    </div>
                                    <div class="strength-text" id="passwordMatch">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Re-enter new password</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Update Teacher
                        </button>
                        <a href="teachers.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/edit_teacher.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const teacherData = {
            id: <?php echo $teacher_id; ?>,
            name: '<?php echo htmlspecialchars($teacher['fullname']); ?>',
            email: '<?php echo htmlspecialchars($teacher['email']); ?>'
        };
        
        // Check if change password was checked due to form error
        <?php if(isset($_POST['change_password'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const changePasswordCheckbox = document.getElementById('change_password');
                const passwordFields = document.getElementById('passwordFields');
                const newPasswordInput = document.getElementById('new_password');
                const confirmPasswordInput = document.getElementById('confirm_password');
                
                if(changePasswordCheckbox) {
                    changePasswordCheckbox.checked = true;
                    passwordFields.classList.add('show');
                    newPasswordInput.disabled = false;
                    confirmPasswordInput.disabled = false;
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>