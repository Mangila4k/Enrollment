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
$error = '';
$success = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
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
    
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if(empty($role)) {
        $errors[] = "Role is required";
    }
    
    // Check if email already exists
    if(empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if($check->rowCount() > 0) {
            $errors[] = "Email already exists";
        }
    }
    
    // Check if ID number exists (if provided)
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
        $check_id->execute([$id_number]);
        
        if($check_id->rowCount() > 0) {
            $errors[] = "ID number already exists";
        }
    }
    
    // If no errors, insert the user
    if(empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Fixed: Removed is_approved column (doesn't exist in your table)
            $stmt = $conn->prepare("INSERT INTO users (id_number, fullname, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'approved', NOW())");
            $stmt->execute([$id_number, $fullname, $email, $hashed_password, $role]);
            
            $_SESSION['success_message'] = "Account created successfully!";
            header("Location: manage_accounts.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Error creating account: " . $e->getMessage();
        }
    }
    
    // If there are errors, store them
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Account - PLSNHS | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Add Account CSS -->
    <link rel="stylesheet" href="css/add_account.css">
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
                    <h1>Add New Account</h1>
                    <p>Create a new user account in the system</p>
                </div>
                <a href="manage_accounts.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Accounts</a>
            </div>

            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="form-container">
                <div class="form-card">
                    <h3><i class="fas fa-id-card"></i> Account Information</h3>

                    <form method="POST" action="" id="accountForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" 
                                       id="fullname" 
                                       name="fullname" 
                                       placeholder="e.g., Juan Dela Cruz" 
                                       value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       placeholder="user@plshs.edu.ph" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>ID Number</label>
                                <input type="text" 
                                       id="id_number" 
                                       name="id_number" 
                                       placeholder="e.g., EMP-2024-001" 
                                       value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Leave blank for auto-generated
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Role <span>*</span></label>
                                <select id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="Admin" <?php echo isset($_POST['role']) && $_POST['role'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="Registrar" <?php echo isset($_POST['role']) && $_POST['role'] == 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                                    <option value="Teacher" <?php echo isset($_POST['role']) && $_POST['role'] == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="Student" <?php echo isset($_POST['role']) && $_POST['role'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Password <span>*</span></label>
                                <div class="input-wrapper">
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Create a password"
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
                                    <span>Minimum 6 characters</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password <span>*</span></label>
                                <div class="input-wrapper">
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Re-enter your password"
                                           required>
                                </div>
                                <div class="strength-text" id="passwordMatch">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Re-enter your password</span>
                                </div>
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="preview-card">
                            <h4><i class="fas fa-eye"></i> Account Preview</h4>
                            <div class="preview-content">
                                <div class="preview-avatar" id="previewInitial">
                                    <?php 
                                    $initial = isset($_POST['fullname']) ? strtoupper(substr($_POST['fullname'], 0, 1)) : 'U';
                                    echo $initial;
                                    ?>
                                </div>
                                <div class="preview-info">
                                    <h5 id="previewName"><?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : 'New User'; ?></h5>
                                    <div id="previewRole" class="preview-role-badge <?php echo isset($_POST['role']) ? strtolower($_POST['role']) : 'student'; ?>">
                                        <?php echo isset($_POST['role']) ? $_POST['role'] : 'Student'; ?>
                                    </div>
                                    <div class="preview-email" id="previewEmail">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'user@plshs.edu.ph'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Create Account
                            </button>
                            <a href="manage_accounts.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleBtn.className = 'fas fa-eye';
            }
        }

        // Live preview update
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const roleSelect = document.getElementById('role');
        const previewName = document.getElementById('previewName');
        const previewEmail = document.getElementById('previewEmail');
        const previewRole = document.getElementById('previewRole');
        const previewInitial = document.getElementById('previewInitial');

        function updatePreview() {
            const fullname = fullnameInput.value.trim() || 'New User';
            previewName.textContent = fullname;
            
            const initial = fullname.charAt(0).toUpperCase() || 'U';
            previewInitial.textContent = initial;

            const email = emailInput.value.trim() || 'user@plshs.edu.ph';
            previewEmail.innerHTML = `<i class="fas fa-envelope"></i> ${email}`;

            const role = roleSelect.value || 'Student';
            previewRole.textContent = role;
            previewRole.className = 'preview-role-badge ' + role.toLowerCase();
        }

        if(fullnameInput) fullnameInput.addEventListener('input', updatePreview);
        if(emailInput) emailInput.addEventListener('input', updatePreview);
        if(roleSelect) roleSelect.addEventListener('change', updatePreview);

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');

        function checkPasswordStrength() {
            const password = passwordInput.value;
            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
                return;
            }

            if (strength <= 1) {
                strengthBar.style.width = '25%';
                strengthBar.style.backgroundColor = '#ef4444';
                strengthLabel = 'Weak';
                strengthColor = '#ef4444';
            } else if (strength <= 3) {
                strengthBar.style.width = '60%';
                strengthBar.style.backgroundColor = '#f59e0b';
                strengthLabel = 'Medium';
                strengthColor = '#f59e0b';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.style.backgroundColor = '#10b981';
                strengthLabel = 'Strong';
                strengthColor = '#10b981';
            }

            strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span style="color: ${strengthColor};">Password strength: ${strengthLabel}</span>`;
        }

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (confirm.length === 0) {
                passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>';
            } else if (password === confirm) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span style="color: #10b981;">Passwords match</span>';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> <span style="color: #ef4444;">Passwords do not match</span>';
            }
        }

        if(passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
            passwordInput.addEventListener('input', checkPasswordMatch);
        }
        
        if(confirmInput) {
            confirmInput.addEventListener('input', checkPasswordMatch);
        }

        // Form validation
        document.getElementById('accountForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });

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