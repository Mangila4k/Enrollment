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

// Function to generate student ID number
function generateStudentID($conn) {
    $prefix = "PLSNHS-STD-";
    $current_year = date('Y');
    
    // Get the last student ID number for current year
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number LIKE ? AND role = 'Student' ORDER BY id_number DESC LIMIT 1");
    $stmt->execute([$prefix . $current_year . '-%']);
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
    return $prefix . $current_year . '-' . $formatted_number;
}

// Function to send notification to all admins
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

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = trim($_POST['firstname']);
    $middlename = !empty($_POST['middlename']) ? trim($_POST['middlename']) : null;
    $lastname = trim($_POST['lastname']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Generate ID number automatically
    $id_number = generateStudentID($conn);
    
    // Combine names for fullname
    $fullname = trim($firstname . ' ' . ($middlename ? $middlename . ' ' : '') . $lastname);
    
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
        $birthdate_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
        if(!$birthdate_obj) {
            $errors[] = "Invalid birthdate format";
        } else {
            $today = new DateTime();
            $age = $today->diff($birthdate_obj)->y;
            
            if($age < 15) {
                $errors[] = "You must be at least 15 years old to register";
            } elseif($age > 30) {
                $errors[] = "Age exceeds maximum allowed (30 years)";
            }
        }
    }
    
    if(empty($gender)) {
        $errors[] = "Gender is required";
    } elseif(!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Invalid gender selection";
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
    
    if(empty($errors)) {
        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if($check->rowCount() > 0) {
                $error = "Email already exists";
            } else {
                $conn->beginTransaction();
                
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'Student';
                    $status = 'approved';
                    
                    $stmt = $conn->prepare("INSERT INTO users (id_number, firstname, middlename, lastname, fullname, birthdate, gender, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$id_number, $firstname, $middlename, $lastname, $fullname, $birthdate, $gender, $email, $hashed_password, $role, $status]);
                    
                    $conn->commit();
                    
                    // Send notification to all admins
                    $notif_title = "👨‍🎓 New Student Added";
                    $notif_message = "A new student account has been created: " . $fullname . " (ID: " . $id_number . ")";
                    $notif_link = "students.php";
                    notifyAdmins($conn, $notif_title, $notif_message, $notif_link, 'action', $admin_id);
                    
                    $success = "Student account created successfully! ID Number: " . $id_number;
                    $_POST = array();
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Error creating student: " . $e->getMessage();
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - PLSNHS | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Flatpickr for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Add Student CSS -->
    <link rel="stylesheet" href="css/add_student.css">
    <style>
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #0B4F2E;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #0c5460;
        }
        .info-box i {
            font-size: 24px;
            color: #0B4F2E;
        }
        .id-preview {
            background: #f0fdf4;
            border: 1px solid #10b981;
            color: #065f46;
            font-weight: 600;
        }
    </style>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
                    <h1>Add New Student</h1>
                    <p>Create a new student account</p>
                </div>
                <a href="students.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Students</a>
            </div>

            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Auto-generated ID Number:</strong> Student ID will be automatically generated in format 
                    <strong>PLSNHS-STD-YYYY-XXXXXX</strong> (e.g., PLSNHS-STD-2026-000001)
                </div>
            </div>

            <!-- Form -->
            <div class="form-container">
                <form method="POST" action="" id="registerForm">
                    <!-- Personal Information -->
                    <div class="form-card">
                        <h3>
                            <i class="fas fa-user"></i>
                            Student Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstname">First Name <span>*</span></label>
                                <input type="text" id="firstname" name="firstname" placeholder="First name" 
                                       value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="middlename">Middle Initial</label>
                                <input type="text" id="middlename" name="middlename" placeholder="Middle initial" maxlength="2"
                                       value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="lastname">Last Name <span>*</span></label>
                                <input type="text" id="lastname" name="lastname" placeholder="Last name" 
                                       value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="birthdate">Birthdate <span>*</span></label>
                                <input type="text" id="birthdate" name="birthdate" placeholder="Select birthdate" 
                                       value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>" required>
                                <div class="form-hint" id="ageHint">
                                    <i class="fas fa-info-circle"></i>
                                    Must be 15-30 years old
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender <span>*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span>*</span></label>
                            <input type="email" id="email" name="email" placeholder="Enter your email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="id_preview">Student ID (Auto-generated)</label>
                            <input type="text" id="id_preview" class="id-preview" readonly 
                                   value="<?php echo isset($id_number) ? $id_number : 'PLSNHS-STD-2026-XXXXXX'; ?>">
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                ID will be generated automatically when you create the student
                            </div>
                        </div>
                    </div>

                    <!-- Account Security -->
                    <div class="form-card">
                        <h3>
                            <i class="fas fa-lock"></i>
                            Account Security
                        </h3>

                        <div class="form-group">
                            <label for="password">Password <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password" placeholder="Create a strong password" required>
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
                            <label for="confirm_password">Confirm Password <span>*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                            <div class="strength-text" id="passwordMatch">
                                <i class="fas fa-info-circle"></i>
                                <span>Re-enter your password</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Create Student
                        </button>
                        <a href="students.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date picker
            flatpickr("#birthdate", {
                maxDate: "today",
                minDate: "1993-01-01",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                placeholder: "Select birthdate",
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        calculateAge(dateStr);
                    }
                }
            });
        });

        // Calculate age and validate
        function calculateAge(birthdate) {
            const birthDate = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age--;
            }
            
            const ageHint = document.getElementById('ageHint');
            
            if (age < 15) {
                ageHint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Age: ' + age + ' years - You must be at least 15 years old!';
                ageHint.style.color = '#dc3545';
                document.getElementById('birthdate').style.borderColor = '#dc3545';
                return false;
            } else if (age > 30) {
                ageHint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Age: ' + age + ' years - Age exceeds maximum allowed (30 years)!';
                ageHint.style.color = '#dc3545';
                document.getElementById('birthdate').style.borderColor = '#dc3545';
                return false;
            } else {
                ageHint.innerHTML = '<i class="fas fa-check-circle"></i> Age: ' + age + ' years - Valid age!';
                ageHint.style.color = '#28a745';
                document.getElementById('birthdate').style.borderColor = '#28a745';
                return true;
            }
        }

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

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            strengthBar.className = 'strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Minimum 8 characters with uppercase, lowercase, number & special character</span>';
                return;
            }
            
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            if (strength <= 2) {
                strengthBar.classList.add('weak');
                strengthBar.style.width = '25%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Weak password</span>';
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
                strengthBar.style.width = '60%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Medium password</span>';
            } else {
                strengthBar.classList.add('strong');
                strengthBar.style.width = '100%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Strong password</span>';
            }
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>';
            } else {
                matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const birthdate = document.getElementById('birthdate').value;
            const firstname = document.getElementById('firstname').value;
            const lastname = document.getElementById('lastname').value;
            const gender = document.getElementById('gender').value;
            const email = document.getElementById('email').value;
            
            // Check password strength
            let strength = 0;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            if (!firstname || !lastname) {
                e.preventDefault();
                alert('First name and last name are required!');
                return false;
            }
            
            if (!gender) {
                e.preventDefault();
                alert('Please select gender!');
                return false;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Email address is required!');
                return false;
            }
            
            if (!birthdate) {
                e.preventDefault();
                alert('Birthdate is required!');
                return false;
            }
            
            const birthDate = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age--;
            }
            
            if (age < 15) {
                e.preventDefault();
                alert('Student must be at least 15 years old!');
                return false;
            } else if (age > 30) {
                e.preventDefault();
                alert('Age exceeds maximum allowed (30 years)!');
                return false;
            }
            
            if (strength < 4) {
                e.preventDefault();
                alert('⚠️ Password Requirements:\n\n• At least 8 characters\n• At least 1 uppercase letter (A-Z)\n• At least 1 lowercase letter (a-z)\n• At least 1 number (0-9)\n• At least 1 special character (!@#$%^&*)');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            return true;
        });

        // Auto-uppercase middle initial
        const middleInput = document.getElementById('middlename');
        if(middleInput) {
            middleInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
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