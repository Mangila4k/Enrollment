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

// Function to generate teacher ID number
function generateTeacherID($conn) {
    $prefix = "PLSNHS-TCH-";
    
    // Get the last teacher ID number
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number LIKE ? AND role = 'Teacher' ORDER BY id_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($last_id && $last_id['id_number']) {
        // Extract the number part
        $parts = explode('-', $last_id['id_number']);
        $last_number = intval(end($parts));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    // Format with 6 digits (e.g., 000001)
    $formatted_number = str_pad($new_number, 6, '0', STR_PAD_LEFT);
    return $prefix . $formatted_number;
}

// Function to send notification to all admins and registrars
function notifyAdmins($conn, $title, $message, $link, $type = 'action', $exclude_user_id = null) {
    // Get all admin and registrar users
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

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $specialization = trim($_POST['specialization']);
    $phone = trim($_POST['phone']);
    
    // Generate ID number automatically
    $id_number = generateTeacherID($conn);
    
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
    
    // Strong password validation
    if(empty($password)) {
        $errors[] = "Password is required";
    } else {
        $password_errors = [];
        
        if(strlen($password) < 8) {
            $password_errors[] = "at least 8 characters";
        }
        if(!preg_match('/[A-Z]/', $password)) {
            $password_errors[] = "at least one uppercase letter";
        }
        if(!preg_match('/[a-z]/', $password)) {
            $password_errors[] = "at least one lowercase letter";
        }
        if(!preg_match('/[0-9]/', $password)) {
            $password_errors[] = "at least one number";
        }
        if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $password_errors[] = "at least one special character";
        }
        
        if(!empty($password_errors)) {
            $errors[] = "Password must contain: " . implode(", ", $password_errors);
        }
    }
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    if(empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        
        if($check_email->rowCount() > 0) {
            $errors[] = "Email address already registered";
        }
    }
    
    // If no errors, insert the teacher
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Teacher';
        $status = 'approved';
        
        $insert_query = "INSERT INTO users (id_number, fullname, email, password, role, status, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        
        if($insert_stmt->execute([$id_number, $fullname, $email, $hashed_password, $role, $status, $phone])) {
            // Send notification to all admins
            $notif_title = "👨‍🏫 New Teacher Added";
            $notif_message = "A new teacher account has been created: " . $fullname . " (ID: " . $id_number . ")";
            $notif_link = "teachers.php";
            notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'action', $admin_id);
            
            $_SESSION['success_message'] = "Teacher added successfully! ID Number: " . $id_number;
            header("Location: teachers.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Teacher - EnrollSys | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Add Teacher CSS -->
    <link rel="stylesheet" href="css/add_teacher.css">
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
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
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
                    <h1>Add New Teacher</h1>
                    <p>Create a new faculty account</p>
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

            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Auto-generated ID Number:</strong> Teacher ID will be automatically generated in format <strong>PLSNHS-TCH-XXXXXX</strong> (e.g., PLSNHS-TCH-000001)
                </div>
            </div>

            <!-- Form Card -->
            <div class="form-container">
                <div class="form-card">
                    <h3>
                        <i class="fas fa-user-plus"></i>
                        Teacher Information
                    </h3>

                    <form method="POST" action="" id="teacherForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" 
                                       name="fullname" 
                                       id="fullname"
                                       placeholder="e.g., Juan Dela Cruz" 
                                       value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       placeholder="teacher@plshs.edu.ph" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Password <span>*</span></label>
                                <div class="input-wrapper">
                                    <input type="password" 
                                           name="password" 
                                           id="password"
                                           placeholder="Create a strong password" 
                                           required>
                                    <button type="button" class="toggle-password" onclick="togglePassword()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-bar" id="strengthBar"></div>
                                </div>
                                <div class="strength-text" id="strengthText">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Minimum 8 characters with uppercase, lowercase, number & special character</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password <span>*</span></label>
                                <div class="input-wrapper">
                                    <input type="password" 
                                           name="confirm_password" 
                                           id="confirm_password"
                                           placeholder="Confirm password" 
                                           required>
                                </div>
                                <div class="strength-text" id="passwordMatch">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Re-enter your password</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" 
                                       name="phone" 
                                       id="phone"
                                       placeholder="e.g., 09123456789" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label>Specialization</label>
                                <input type="text" 
                                       name="specialization" 
                                       id="specialization"
                                       placeholder="e.g., Mathematics, Science, English" 
                                       value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Main subject area or field of expertise
                                </div>
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="preview-card">
                            <h4><i class="fas fa-eye"></i> Teacher Preview</h4>
                            <div class="preview-item">
                                <div class="preview-icon" id="previewInitial">
                                    <?php 
                                    $initial = isset($_POST['fullname']) ? strtoupper(substr($_POST['fullname'], 0, 1)) : 'T';
                                    echo $initial;
                                    ?>
                                </div>
                                <div class="preview-details">
                                    <h5 id="previewName"><?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : 'New Teacher'; ?></h5>
                                    <p id="previewEmail">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'teacher@plshs.edu.ph'; ?>
                                    </p>
                                    <p id="previewSpecialization">
                                        <i class="fas fa-book"></i>
                                        <?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : 'Specialization not set'; ?>
                                    </p>
                                    <p id="previewID">
                                        <i class="fas fa-id-card"></i>
                                        Auto-generated ID: PLSNHS-TCH-XXXXXX
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Add Teacher
                            </button>
                            <a href="teachers.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ========================================
        // ADD TEACHER PAGE JAVASCRIPT
        // ========================================

        // Live preview update
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const specializationInput = document.getElementById('specialization');
        const previewName = document.getElementById('previewName');
        const previewEmail = document.getElementById('previewEmail');
        const previewSpecialization = document.getElementById('previewSpecialization');
        const previewInitial = document.getElementById('previewInitial');

        function updatePreview() {
            const fullname = fullnameInput.value.trim() || 'New Teacher';
            previewName.textContent = fullname;
            
            const initial = fullname.charAt(0).toUpperCase() || 'T';
            previewInitial.textContent = initial;

            const email = emailInput.value.trim() || 'teacher@plshs.edu.ph';
            previewEmail.innerHTML = `<i class="fas fa-envelope"></i> ${email}`;

            const specialization = specializationInput.value.trim() || 'Specialization not set';
            previewSpecialization.innerHTML = `<i class="fas fa-book"></i> ${specialization}`;
        }

        if (fullnameInput) fullnameInput.addEventListener('input', updatePreview);
        if (emailInput) emailInput.addEventListener('input', updatePreview);
        if (specializationInput) specializationInput.addEventListener('input', updatePreview);

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if (passwordInput && toggleBtn) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleBtn.className = 'fas fa-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    toggleBtn.className = 'fas fa-eye';
                }
            }
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');

        function checkPasswordStrength() {
            if (!passwordInput) return;
            
            const password = passwordInput.value;
            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength += 1;

            switch(strength) {
                case 0:
                case 1:
                    if (strengthBar) strengthBar.style.width = '20%';
                    if (strengthBar) strengthBar.style.backgroundColor = '#ef4444';
                    strengthLabel = 'Weak';
                    strengthColor = '#ef4444';
                    break;
                case 2:
                case 3:
                    if (strengthBar) strengthBar.style.width = '60%';
                    if (strengthBar) strengthBar.style.backgroundColor = '#f59e0b';
                    strengthLabel = 'Medium';
                    strengthColor = '#f59e0b';
                    break;
                case 4:
                case 5:
                    if (strengthBar) strengthBar.style.width = '100%';
                    if (strengthBar) strengthBar.style.backgroundColor = '#10b981';
                    strengthLabel = 'Strong';
                    strengthColor = '#10b981';
                    break;
            }

            if (password.length > 0 && strengthText) {
                strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: ${strengthColor};">Password strength: ${strengthLabel}</span>`;
            } else if (strengthText) {
                strengthText.innerHTML = `<i class="fas fa-info-circle"></i> <span>Minimum 8 characters with uppercase, lowercase, number & special character</span>`;
            }
        }

        function checkPasswordMatch() {
            if (!passwordInput || !confirmInput || !passwordMatch) return;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (confirm.length > 0) {
                if (password === confirm) {
                    passwordMatch.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>`;
                } else {
                    passwordMatch.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>`;
                }
            } else {
                passwordMatch.innerHTML = `<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>`;
            }
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
            passwordInput.addEventListener('input', checkPasswordMatch);
        }
        if (confirmInput) confirmInput.addEventListener('input', checkPasswordMatch);

        // Form validation
        const teacherForm = document.getElementById('teacherForm');
        if (teacherForm) {
            teacherForm.addEventListener('submit', function(e) {
                if (!passwordInput || !confirmInput) return;
                
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                // Check password strength
                let strength = 0;
                if (password.length >= 8) strength += 1;
                if (password.match(/[a-z]+/)) strength += 1;
                if (password.match(/[A-Z]+/)) strength += 1;
                if (password.match(/[0-9]+/)) strength += 1;
                if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength += 1;

                if (strength < 4) {
                    e.preventDefault();
                    alert('⚠️ Password Requirements:\n\n• At least 8 characters\n• At least 1 uppercase letter (A-Z)\n• At least 1 lowercase letter (a-z)\n• At least 1 number (0-9)\n• At least 1 special character (!@#$%^&*)');
                    return false;
                }

                if (password !== confirm) {
                    e.preventDefault();
                    alert('❌ Passwords do not match!');
                    return false;
                }
                
                return true;
            });
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