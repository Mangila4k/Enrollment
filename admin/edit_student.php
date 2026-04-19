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
$success_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Get student details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$student) {
    header("Location: students.php");
    exit();
}

// Get student's current enrollment
$enrollment_query = "
    SELECT e.*, g.grade_name 
    FROM enrollments e 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    WHERE e.student_id = ? 
    ORDER BY e.id DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->execute([$student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_student'])) {
        $firstname = trim($_POST['firstname']);
        $middlename = trim($_POST['middlename']);
        $lastname = trim($_POST['lastname']);
        $fullname = $firstname . (!empty($middlename) ? ' ' . $middlename . ' ' : ' ') . $lastname;
        $email = trim($_POST['email']);
        $birthdate = trim($_POST['birthdate']);
        $gender = trim($_POST['gender']);
        $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
        
        // Validation
        $errors = [];
        
        if(empty($firstname)) {
            $errors[] = "First name is required";
        }
        
        if(empty($lastname)) {
            $errors[] = "Last name is required";
        }
        
        if(empty($birthdate)) {
            $errors[] = "Birthdate is required";
        } else {
            // Calculate age
            $birthDateObj = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            
            if($age < 15 || $age > 30) {
                $errors[] = "Student must be between 15-30 years old";
            }
        }
        
        if(empty($gender)) {
            $errors[] = "Gender is required";
        }
        
        if(empty($email)) {
            $errors[] = "Email address is required";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email already exists (excluding current student)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->execute([$email, $student_id]);
            
            if($check_email->rowCount() > 0) {
                $errors[] = "Email address already registered to another user";
            }
        }
        
        // Check if ID number already exists
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->execute([$id_number, $student_id]);
            
            if($check_id->rowCount() > 0) {
                $errors[] = "ID number already exists for another user";
            }
        }
        
        // If no errors, update the student
        if(empty($errors)) {
            $update_query = "UPDATE users SET 
                firstname = ?, 
                middlename = ?, 
                lastname = ?, 
                fullname = ?, 
                email = ?, 
                birthdate = ?, 
                gender = ?, 
                id_number = ? 
                WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([
                $firstname, 
                $middlename, 
                $lastname, 
                $fullname, 
                $email, 
                $birthdate, 
                $gender, 
                $id_number, 
                $student_id
            ]);
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['success_message'] = "Student information updated successfully!";
                header("Location: view_student.php?id=" . $student_id);
                exit();
            } else {
                $errors[] = "Database error occurred.";
            }
        }
        
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
            $update_stmt->execute([$hashed_password, $student_id]);
            
            if($update_stmt->rowCount() >= 0) {
                $_SESSION['success_message'] = "Password reset successfully!";
                header("Location: view_student.php?id=" . $student_id);
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
    <title>Edit Student - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/edit_student.css">
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
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
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
                <h1>Edit Student</h1>
                <p>Update student information and account details</p>
            </div>
            <a href="students.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Students</a>
        </div>

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

        <!-- Edit Student Form -->
        <div class="form-card">
            <h3><i class="fas fa-user-edit"></i> Student Information</h3>
            
            <form method="POST" action="" id="editStudentForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span>*</span></label>
                        <input type="text" name="firstname" value="<?php echo htmlspecialchars($student['firstname'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Middle Initial</label>
                        <input type="text" name="middlename" maxlength="2" value="<?php echo htmlspecialchars($student['middlename'] ?? ''); ?>" placeholder="M.I.">
                    </div>
                </div>

                <div class="form-group">
                    <label>Last Name <span>*</span></label>
                    <input type="text" name="lastname" value="<?php echo htmlspecialchars($student['lastname'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Birthdate <span>*</span></label>
                        <input type="date" name="birthdate" value="<?php echo htmlspecialchars($student['birthdate'] ?? ''); ?>" required>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i> Must be 15-30 years old
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Gender <span>*</span></label>
                        <select name="gender" required>
                            <option value="">Select gender</option>
                            <option value="Male" <?php echo ($student['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($student['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($student['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address <span>*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($student['id_number'] ?? ''); ?>" readonly disabled>
                    <div class="form-hint">
                        <i class="fas fa-lock"></i> Student ID cannot be edited
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_student" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_student.php?id=<?php echo $student_id; ?>" class="btn-secondary">
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
                    <label for="reset_password_checkbox">Reset student password</label>
                </div>

                <div id="passwordFields" class="password-fields">
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
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/edit_student.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const studentData = {
            id: <?php echo $student_id; ?>,
            name: '<?php echo htmlspecialchars($student['fullname']); ?>'
        };
    </script>
</body>
</html>