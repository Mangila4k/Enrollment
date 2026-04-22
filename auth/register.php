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
    
    // Strong password validation
    if(empty($password)) {
        $errors[] = "Password is required";
    } else {
        $password_errors = [];
        
        // Check minimum length
        if(strlen($password) < 8) {
            $password_errors[] = "at least 8 characters long";
        }
        
        // Check for uppercase letter
        if(!preg_match('/[A-Z]/', $password)) {
            $password_errors[] = "at least one uppercase letter";
        }
        
        // Check for lowercase letter
        if(!preg_match('/[a-z]/', $password)) {
            $password_errors[] = "at least one lowercase letter";
        }
        
        // Check for number
        if(!preg_match('/[0-9]/', $password)) {
            $password_errors[] = "at least one number";
        }
        
        // Check for special character
        if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $password_errors[] = "at least one special character (!@#$%^&* etc.)";
        }
        
        if(!empty($password_errors)) {
            $errors[] = "Password must contain: " . implode(", ", $password_errors);
        }
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
            max-width: 750px;
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
            transition: all 0.3s;
        }
        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11,79,46,0.1);
        }
        .input-wrapper input.valid { border-color: #10b981; background-color: #f0fdf4; }
        .input-wrapper input.invalid { border-color: #ef4444; background-color: #fef2f2; }
        .input-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666; opacity: 0.5; }
        .toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; }
        .password-strength {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            background: #f8f9fa;
        }
        .strength-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
        }
        .strength-item i { font-size: 10px; }
        .strength-item.valid i { color: #10b981; }
        .strength-item.invalid i { color: #ef4444; }
        .strength-meter {
            margin-top: 8px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
            border-radius: 3px;
        }
        .strength-meter-fill.weak { background: #ef4444; width: 25%; }
        .strength-meter-fill.fair { background: #f59e0b; width: 50%; }
        .strength-meter-fill.good { background: #3b82f6; width: 75%; }
        .strength-meter-fill.strong { background: #10b981; width: 100%; }
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
            transition: all 0.3s;
        }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(11,79,46,0.3); }
        .btn-register:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .login-link { text-align: center; margin: 15px 0; }
        .login-link a { color: #0B4F2E; text-decoration: none; font-weight: 600; }
        .info-box { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 15px; border-radius: 10px; margin: 20px 0; font-size: 13px; color: #0c5460; display: flex; align-items: center; gap: 10px; }
        .password-requirements {
            background: #fef2f2;
            border-left: 4px solid #1eb319ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 12px;
        }
        .password-requirements ul { margin-left: 20px; margin-top: 5px; }
        .password-requirements li { color: #d63434ff; margin: 3px 0; }
        .password-requirements li.valid { color: #10b981; text-decoration: line-through; }
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

            <div class="form-group">
                <label>Password <span>*</span></label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
                </div>
                
                <!-- Password strength meter -->
                <div class="strength-meter">
                    <div class="strength-meter-fill" id="strengthFill"></div>
                </div>
                
                <!-- Password requirements checklist -->
                <div class="password-requirements" id="passwordRequirements">
                    <small><i class="fas fa-shield-alt"></i> Password must contain:</small>
                    <ul id="requirementsList">
                        <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                        <li id="req-upper"><i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)</li>
                        <li id="req-lower"><i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)</li>
                        <li id="req-number"><i class="fas fa-circle"></i> At least 1 number (0-9)</li>
                        <li id="req-special"><i class="fas fa-circle"></i> At least 1 special character (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password <span>*</span></label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>
                <div id="confirmMatch" style="font-size: 12px; margin-top: 5px;"></div>
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
    
    // Password validation functions
    function validatePassword(password) {
        return {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
    }
    
    function updatePasswordStrength() {
        const password = document.getElementById('password').value;
        const validation = validatePassword(password);
        
        // Update requirement list
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        
        // Update length requirement
        if (reqLength) {
            if (validation.length) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
        }
        
        // Update uppercase requirement
        if (reqUpper) {
            if (validation.uppercase) {
                reqUpper.classList.add('valid');
                reqUpper.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 uppercase letter (A-Z)';
            } else {
                reqUpper.classList.remove('valid');
                reqUpper.innerHTML = '<i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)';
            }
        }
        
        // Update lowercase requirement
        if (reqLower) {
            if (validation.lowercase) {
                reqLower.classList.add('valid');
                reqLower.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 lowercase letter (a-z)';
            } else {
                reqLower.classList.remove('valid');
                reqLower.innerHTML = '<i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)';
            }
        }
        
        // Update number requirement
        if (reqNumber) {
            if (validation.number) {
                reqNumber.classList.add('valid');
                reqNumber.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 number (0-9)';
            } else {
                reqNumber.classList.remove('valid');
                reqNumber.innerHTML = '<i class="fas fa-circle"></i> At least 1 number (0-9)';
            }
        }
        
        // Update special character requirement
        if (reqSpecial) {
            if (validation.special) {
                reqSpecial.classList.add('valid');
                reqSpecial.innerHTML = '<i class="fas fa-check-circle"></i> At least 1 special character (!@#$%^&*)';
            } else {
                reqSpecial.classList.remove('valid');
                reqSpecial.innerHTML = '<i class="fas fa-circle"></i> At least 1 special character (!@#$%^&*)';
            }
        }
        
        // Calculate strength percentage
        const validCount = Object.values(validation).filter(v => v === true).length;
        const strengthPercent = (validCount / 5) * 100;
        const strengthFill = document.getElementById('strengthFill');
        
        if (strengthFill) {
            strengthFill.style.width = strengthPercent + '%';
            if (strengthPercent <= 25) {
                strengthFill.className = 'strength-meter-fill weak';
            } else if (strengthPercent <= 50) {
                strengthFill.className = 'strength-meter-fill fair';
            } else if (strengthPercent <= 75) {
                strengthFill.className = 'strength-meter-fill good';
            } else {
                strengthFill.className = 'strength-meter-fill strong';
            }
        }
        
        // Check if password meets all requirements
        const isStrong = Object.values(validation).every(v => v === true);
        
        // Validate confirm password
        validateConfirmPassword();
        
        return isStrong;
    }
    
    function validateConfirmPassword() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const confirmMatchDiv = document.getElementById('confirmMatch');
        const registerBtn = document.getElementById('registerBtn');
        
        // Get current password validation
        const validation = validatePassword(password);
        const allValid = Object.values(validation).every(v => v === true);
        
        if (confirm === '') {
            confirmMatchDiv.innerHTML = '';
            confirmMatchDiv.style.color = '';
            registerBtn.disabled = !allValid;
        } else if (password === confirm) {
            confirmMatchDiv.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Passwords match!';
            confirmMatchDiv.style.color = '#10b981';
            registerBtn.disabled = !allValid;
        } else {
            confirmMatchDiv.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc2626;"></i> Passwords do not match!';
            confirmMatchDiv.style.color = '#dc2626';
            registerBtn.disabled = true;
        }
    }
    
    // Real-time password validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordStrength);
        // Initial check
        updatePasswordStrength();
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', validateConfirmPassword);
    }
    
    // Form submission validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const validation = validatePassword(password);
        const isStrong = Object.values(validation).every(v => v === true);
        
        if (!isStrong) {
            e.preventDefault();
            alert('Please make sure your password meets all the requirements:\n\n✓ At least 8 characters\n✓ At least 1 uppercase letter (A-Z)\n✓ At least 1 lowercase letter (a-z)\n✓ At least 1 number (0-9)\n✓ At least 1 special character (!@#$%^&*)');
            return false;
        }
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        return true;
    });
    
    // Test function to debug
    function testPassword() {
        const testPass = document.getElementById('password').value;
        console.log('Testing password:', testPass);
        console.log('Has uppercase:', /[A-Z]/.test(testPass));
        console.log('Has lowercase:', /[a-z]/.test(testPass));
        console.log('Has number:', /[0-9]/.test(testPass));
        console.log('Length:', testPass.length >= 8);
    }
</script>
</body>
</html>