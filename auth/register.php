<?php
session_start();
include("../config/database.php");
require_once '../config/email_config.php';

// Check if already logged in
if(isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if(isset($_POST['register'])){
    // Get form data
    $firstname = trim($_POST['firstname']);
    $middlename = !empty($_POST['middlename']) ? trim($_POST['middlename']) : null;
    $lastname = trim($_POST['lastname']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    $role = "Student";
    
    $fullname = trim($firstname . ' ' . ($middlename ? $middlename . ' ' : '') . $lastname);
    
    // Validation
    $errors = [];
    
    if(empty($firstname)) $errors[] = "First name is required";
    if(empty($lastname)) $errors[] = "Last name is required";
    
    if(empty($birthdate)) {
        $errors[] = "Birthdate is required";
    } else {
        $birthdate_obj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthdate_obj)->y;
        if($age < 15) $errors[] = "You must be at least 15 years old to register";
        elseif($age > 30) $errors[] = "Age exceeds maximum allowed (30 years)";
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
    
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if($password !== $confirm_password) $errors[] = "Passwords do not match";

    // Check if email already exists
    if(empty($errors)) {
        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if($check->rowCount() > 0) $errors[] = "Email already registered!";
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If no errors, insert the user
    if(empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $status = 'pending';
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Insert user with verification code
            $stmt = $conn->prepare("INSERT INTO users (firstname, middlename, lastname, fullname, birthdate, gender, email, password, role, status, verification_code, verification_expires, email_verified) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$firstname, $middlename, $lastname, $fullname, $birthdate, $gender, $email, $hashed_password, $role, $status, $verification_code, $verification_expires]);
            
            // Send verification email
            $emailSent = sendVerificationCode($email, $fullname, $verification_code);
            
            if($emailSent) {
                $_SESSION['temp_email'] = $email;
                $success = "✅ Registration successful! A 6-digit verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong><br><br>
                            <a href='verify_code.php' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #0B4F2E; color: white; text-decoration: none; border-radius: 5px;'>Click here to verify your email →</a>";
            } else {
                $success = "⚠️ Registration successful but we couldn't send the verification email. Your verification code is: <strong>$verification_code</strong><br>
                            <a href='verify_code.php' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #0B4F2E; color: white; text-decoration: none; border-radius: 5px;'>Click here to verify manually →</a>";
            }
            
            $_POST = array();
            
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
    
    if(!empty($errors)) $error = implode("<br>", $errors);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Placido L. Señor Senior High School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .register-container {
            background: white;
            width: 100%;
            max-width: 700px;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo-section { text-align: center; margin-bottom: 30px; }
        .school-logo { width: 100px; height: 100px; margin: 0 auto 15px; }
        .school-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; }
        .school-name h1 { font-size: 24px; color: #0B4F2E; }
        h2 { font-size: 28px; color: #333; text-align: center; margin-bottom: 10px; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-success a { color: #0B4F2E; text-decoration: underline; }
        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .form-row-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 600; font-size: 14px; }
        .input-wrapper { position: relative; }
        .input-wrapper input, .input-wrapper select {
            width: 100%;
            padding: 14px 45px 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            background-color: #f8f9fa;
        }
        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11,79,46,0.1);
        }
        .input-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666; opacity: 0.5; }
        .toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; }
        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 20px 0 15px;
        }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(11,79,46,0.3); }
        .login-link { text-align: center; margin: 15px 0; }
        .login-link a { color: #0B4F2E; text-decoration: none; font-weight: 600; }
        .info-box { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 15px; border-radius: 10px; margin: 20px 0; font-size: 13px; color: #0c5460; display: flex; align-items: center; gap: 10px; }
        @media (max-width: 600px) {
            .register-container { padding: 30px 20px; }
            .form-row, .form-row-2 { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="register-container">
        <div class="logo-section">
            <div class="school-logo">
                <img src="../pictures/logo sa skwelahan.jpg" alt="School Logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%230B4F2E%22 /%3E%3Ctext x=%2250%22 y=%2265%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EPLS%3C/text%3E%3C/svg%3E';">
            </div>
            <div class="school-name">
                <h1>Placido L. Señor</h1>
                <p>National High School</p>
            </div>
        </div>
        <h2>Student Registration</h2>
        
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span>*</span></label>
                    <div class="input-wrapper">
                        <input type="text" name="firstname" placeholder="First name" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Middle Initial</label>
                    <div class="input-wrapper">
                        <input type="text" name="middlename" placeholder="Middle initial" maxlength="2" value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Last Name <span>*</span></label>
                    <div class="input-wrapper">
                        <input type="text" name="lastname" placeholder="Last name" value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" required>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Birthdate <span>*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="birthdate" name="birthdate" placeholder="Select birthdate" value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>" required>
                        <i class="fas fa-calendar input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Gender <span>*</span></label>
                    <div class="input-wrapper">
                        <select name="gender" required>
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Email Address <span>*</span></label>
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Password <span>*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span>*</span></label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div><strong>Note:</strong> After registration, you will receive a <strong>6-digit verification code</strong> via email. Please verify your email to complete registration.</div>
            </div>

            <button type="submit" name="register" class="btn-register" id="registerBtn">Create Account</button>
        </form>

        <div class="login-link">Already have an account? <a href="login.php">Sign In</a></div>
    </div>

    <script>
        flatpickr("#birthdate", { maxDate: "today", dateFormat: "Y-m-d" });
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
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
            } else if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>