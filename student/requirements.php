<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
$profile_picture = $_SESSION['user']['profile_picture'] ?? null;
$first_name = explode(' ', $student_name)[0];
$success_message = '';
$error_message = '';

// Fetch fresh student data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if($student) {
    $profile_picture = $student['profile_picture'] ?? null;
}

// Get current enrollment with student type and status
$enrollment_query = "
    SELECT e.*, g.grade_name, e.student_type, e.status as enrollment_status
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ? 
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->execute([$student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// Function to check if all required requirements are submitted
function checkAllRequirementsSubmitted($conn, $enrollment_id, $grade_name, $student_type) {
    // Get all required requirements for this grade and student type
    $req_stmt = $conn->prepare("
        SELECT requirement_name FROM enrollment_requirements 
        WHERE grade_level = ? AND student_type = ? AND is_required = 1
    ");
    $req_stmt->execute([$grade_name, $student_type]);
    $required_reqs = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrollment data
    $enroll_stmt = $conn->prepare("SELECT * FROM enrollments WHERE id = ?");
    $enroll_stmt->execute([$enrollment_id]);
    $enrollment_data = $enroll_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Map requirement names to database columns
    $field_map = [
        'Form 138' => 'form_138',
        'Certificate of Completion' => 'certificate_of_completion',
        'PSA Birth Certificate' => 'psa_birth_cert',
        '2x2 ID Pictures' => 'id_pictures',
        'Good Moral Certificate' => 'good_moral_cert',
        'Entrance Exam Result' => 'entrance_exam_result',
        'Form 137' => 'form_137'
    ];
    
    foreach($required_reqs as $req) {
        $req_name = $req['requirement_name'];
        $field_name = null;
        
        foreach($field_map as $key => $field) {
            if(strpos($req_name, $key) !== false) {
                $field_name = $field;
                break;
            }
        }
        
        if($field_name && empty($enrollment_data[$field_name])) {
            return false;
        }
    }
    
    return true;
}

// Function to send notification to all admins and registrars
function notifyAdmins($conn, $title, $message, $link, $type = 'requirement') {
    // Get all admin and registrar users
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('Admin', 'Registrar')");
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $success = true;
    foreach($admins as $admin) {
        $add_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at, is_read) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        if(!$add_notif->execute([$admin['id'], $type, $title, $message, $link])) {
            $success = false;
        }
    }
    return $success;
}

// Function to update enrollment status based on submitted requirements
function updateEnrollmentStatus($conn, $enrollment_id, $grade_name, $student_type) {
    $all_submitted = checkAllRequirementsSubmitted($conn, $enrollment_id, $grade_name, $student_type);
    
    if($all_submitted) {
        // If all requirements are submitted, set to Pending for admin review
        $update_stmt = $conn->prepare("UPDATE enrollments SET status = 'Pending' WHERE id = ?");
        $update_stmt->execute([$enrollment_id]);
        return 'Pending';
    } else {
        // If missing requirements, keep as Draft/Pending Requirements
        $current_status = $conn->prepare("SELECT status FROM enrollments WHERE id = ?");
        $current_status->execute([$enrollment_id]);
        $current = $current_status->fetch(PDO::FETCH_ASSOC);
        
        if($current['status'] != 'Rejected') {
            $update_stmt = $conn->prepare("UPDATE enrollments SET status = 'Pending_Requirements' WHERE id = ?");
            $update_stmt->execute([$enrollment_id]);
        }
        return 'Pending_Requirements';
    }
}

// Get dynamic requirements based on grade level and student type
$requirements = [];
if($enrollment) {
    $grade_name = $enrollment['grade_name'];
    $student_type = $enrollment['student_type'] ?? 'new';
    
    // Fetch requirements from database
    $req_stmt = $conn->prepare("
        SELECT * FROM enrollment_requirements 
        WHERE grade_level = ? AND student_type = ?
        ORDER BY display_order ASC
    ");
    $req_stmt->execute([$grade_name, $student_type]);
    $db_requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map database requirements to display format
    foreach($db_requirements as $req) {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $req['requirement_name']));
        
        $requirements[$key] = [
            'label' => $req['requirement_name'],
            'icon' => getIconForRequirement($req['requirement_name']),
            'required' => (bool)$req['is_required'],
            'can_be_followed' => (bool)$req['can_be_followed'],
            'description' => getDescriptionForRequirement($req['requirement_name'], $req['can_be_followed']),
            'allowed_types' => 'pdf,jpg,jpeg,png',
            'max_size' => 5
        ];
    }
}

// Helper function to get icon based on requirement name
function getIconForRequirement($name) {
    $name_lower = strtolower($name);
    if(strpos($name_lower, 'form 138') !== false) return 'fa-file-pdf';
    if(strpos($name_lower, 'form 137') !== false) return 'fa-file-pdf';
    if(strpos($name_lower, 'certificate') !== false) return 'fa-certificate';
    if(strpos($name_lower, 'psa') !== false) return 'fa-id-card';
    if(strpos($name_lower, 'birth') !== false) return 'fa-baby-carriage';
    if(strpos($name_lower, 'id picture') !== false) return 'fa-camera';
    if(strpos($name_lower, 'good moral') !== false) return 'fa-hand-peace';
    if(strpos($name_lower, 'entrance exam') !== false) return 'fa-pen';
    if(strpos($name_lower, 'interview') !== false) return 'fa-comments';
    if(strpos($name_lower, 'enrollment form') !== false) return 'fa-file-signature';
    if(strpos($name_lower, 'shs') !== false) return 'fa-graduation-cap';
    return 'fa-file-alt';
}

// Helper function to get description based on requirement name
function getDescriptionForRequirement($name, $can_be_followed = false) {
    $name_lower = strtolower($name);
    if(strpos($name_lower, 'form 138') !== false) {
        return 'Original or certified true copy of report card from previous school';
    }
    if(strpos($name_lower, 'form 137') !== false) {
        return $can_be_followed ? 'Permanent record (can be submitted later)' : 'Original permanent record from previous school';
    }
    if(strpos($name_lower, 'certificate of completion') !== false) {
        return 'Certificate showing completion of previous grade level';
    }
    if(strpos($name_lower, 'psa birth certificate') !== false || strpos($name_lower, 'psa birth') !== false) {
        return 'PSA authenticated birth certificate (original or certified copy)';
    }
    if(strpos($name_lower, '2x2 id') !== false || strpos($name_lower, 'id picture') !== false) {
        return '2x2 colored ID picture with white background';
    }
    if(strpos($name_lower, 'good moral') !== false) {
        return 'Certificate of good moral character from previous school';
    }
    if(strpos($name_lower, 'entrance exam') !== false || strpos($name_lower, 'interview') !== false) {
        return $can_be_followed ? 'Result of entrance exam/interview (can be submitted later)' : 'Result of entrance exam/interview';
    }
    if(strpos($name_lower, 'enrollment form') !== false) {
        return 'Duly accomplished enrollment form';
    }
    return 'Please submit this document as part of your enrollment requirements';
}

// Handle file upload for dynamic requirements
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_requirement'])) {
    $requirement_key = $_POST['requirement_key'];
    $requirement_label = $requirements[$requirement_key]['label'] ?? $requirement_key;
    
    if(isset($_FILES['requirement_file']) && $_FILES['requirement_file']['error'] == 0) {
        $file = $_FILES['requirement_file'];
        $filename = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if(isset($requirements[$requirement_key])) {
            $max_size = ($requirements[$requirement_key]['max_size'] ?? 5) * 1024 * 1024;
            
            if(!in_array($file_ext, $allowed)) {
                $error_message = "Invalid file type. Allowed: " . implode(', ', $allowed);
            } elseif($file_size > $max_size) {
                $error_message = "File too large. Max size: " . ($requirements[$requirement_key]['max_size'] ?? 5) . "MB";
            } else {
                $upload_dir = "../uploads/requirements/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = "req_" . $student_id . "_" . $requirement_key . "_" . time() . "." . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                $db_path = "uploads/requirements/" . $new_filename;
                
                // Find matching column in enrollments table
                $column_name = null;
                $check_columns = $conn->query("SHOW COLUMNS FROM enrollments");
                $field_map_db = [
                    'form_138' => ['Form 138', 'Report Card'],
                    'certificate_of_completion' => ['Certificate of Completion'],
                    'psa_birth_cert' => ['PSA Birth Certificate', 'PSA Birth'],
                    'id_pictures' => ['2x2 ID Pictures', 'ID Picture'],
                    'good_moral_cert' => ['Good Moral Certificate', 'Good Moral'],
                    'entrance_exam_result' => ['Entrance Exam Result'],
                    'form_137' => ['Form 137']
                ];
                
                foreach($field_map_db as $col => $keywords) {
                    foreach($keywords as $keyword) {
                        if(strpos($requirement_label, $keyword) !== false) {
                            $column_name = $col;
                            break 2;
                        }
                    }
                }
                
                if(!$column_name) {
                    $column_name = 'other_documents';
                }
                
                // Delete old file if exists
                if(!empty($enrollment[$column_name]) && file_exists("../" . $enrollment[$column_name])) {
                    unlink("../" . $enrollment[$column_name]);
                }
                
                if(move_uploaded_file($file_tmp, $upload_path)) {
                    $update_stmt = $conn->prepare("UPDATE enrollments SET $column_name = ? WHERE id = ?");
                    if($update_stmt->execute([$db_path, $enrollment['id']])) {
                        $success_message = "✅ " . htmlspecialchars($requirement_label) . " has been uploaded successfully!";
                        
                        // Get current submitted count after upload
                        $current_submitted = 0;
                        foreach($requirements as $key => $req) {
                            $col_check = null;
                            foreach($field_map_db as $col => $keywords) {
                                foreach($keywords as $keyword) {
                                    if(strpos($req['label'], $keyword) !== false) {
                                        $col_check = $col;
                                        break 2;
                                    }
                                }
                            }
                            if($col_check && !empty($enrollment[$col_check])) {
                                $current_submitted++;
                            }
                        }
                        $current_submitted++; // Add current upload
                        
                        // Check if all requirements are now submitted
                        $total_req = count($requirements);
                        $all_submitted_now = ($current_submitted >= $total_req);
                        
                        // Send notification to all admins
                        $enrollment_link = "../admin/view_enrollment.php?id=" . $enrollment['id'];
                        
                        if($all_submitted_now) {
                            // All requirements submitted - send special notification
                            $notif_title = "✅ All Requirements Completed!";
                            $notif_message = "Student " . $student_name . " has submitted ALL requirements for " . $enrollment['grade_name'] . " enrollment. Ready for review!";
                            notifyAdmins($conn, $notif_title, $notif_message, $enrollment_link, 'action');
                            $success_message .= " All requirements complete! Your enrollment is now pending review.";
                        } else {
                            // Individual requirement submitted
                            $notif_title = "📄 New Requirement Submitted";
                            $notif_message = "Student " . $student_name . " has submitted " . $requirement_label . " for " . $enrollment['grade_name'] . " enrollment.";
                            notifyAdmins($conn, $notif_title, $notif_message, $enrollment_link, 'requirement');
                        }
                        
                        // Update enrollment status based on submitted requirements
                        $new_status = updateEnrollmentStatus($conn, $enrollment['id'], $enrollment['grade_name'], $enrollment['student_type'] ?? 'new');
                        
                        if($new_status == 'Pending') {
                            $success_message .= " Your enrollment is now pending admin approval.";
                        }
                        
                        // Refresh enrollment data
                        $stmt = $conn->prepare($enrollment_query);
                        $stmt->execute([$student_id]);
                        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Failed to update database.";
                    }
                } else {
                    $error_message = "Failed to upload file.";
                }
            }
        } else {
            $error_message = "Invalid requirement type.";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Get requirement values for this enrollment
$submitted_requirements = [];
$missing_requirements = [];
$requirements_status = [];

// Get all column names from enrollments table for lookup
$check_columns = $conn->query("SHOW COLUMNS FROM enrollments");
$existing_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[$col['Field']] = true;
}

foreach($requirements as $key => $req) {
    // Find matching column
    $column_name = null;
    $field_map_check = [
        'form_138' => ['Form 138', 'Report Card'],
        'certificate_of_completion' => ['Certificate of Completion'],
        'psa_birth_cert' => ['PSA Birth Certificate', 'PSA Birth'],
        'id_pictures' => ['2x2 ID Pictures', 'ID Picture'],
        'good_moral_cert' => ['Good Moral Certificate', 'Good Moral'],
        'entrance_exam_result' => ['Entrance Exam Result'],
        'form_137' => ['Form 137']
    ];
    
    foreach($field_map_check as $col => $keywords) {
        foreach($keywords as $keyword) {
            if(strpos($req['label'], $keyword) !== false) {
                $column_name = $col;
                break 2;
            }
        }
    }
    
    $value = null;
    if($column_name && isset($enrollment[$column_name])) {
        $value = $enrollment[$column_name];
    }
    
    $is_submitted = !empty($value);
    
    $requirements_status[$key] = [
        'label' => $req['label'],
        'icon' => $req['icon'],
        'submitted' => $is_submitted,
        'required' => $req['required'],
        'can_be_followed' => $req['can_be_followed'] ?? false,
        'description' => $req['description'],
        'file' => $value,
        'allowed_types' => $req['allowed_types'],
        'max_size' => $req['max_size']
    ];
    
    if($is_submitted) {
        $submitted_requirements[$key] = ['label' => $req['label'], 'icon' => $req['icon'], 'file' => $value];
    } else {
        $missing_requirements[$key] = ['label' => $req['label'], 'icon' => $req['icon'], 'required' => $req['required']];
    }
}

$total_requirements = count($requirements);
$submitted_count = count($submitted_requirements);
$missing_count = count($missing_requirements);
$completion_percentage = $total_requirements > 0 ? round(($submitted_count / $total_requirements) * 100) : 0;

// Get enrollment status message
$enrollment_status = $enrollment['enrollment_status'] ?? 'Pending_Requirements';
$status_message = '';
$status_class = '';

switch($enrollment_status) {
    case 'Enrolled':
        $status_message = 'Your enrollment has been APPROVED! You are now officially enrolled.';
        $status_class = 'success';
        break;
    case 'Pending':
        $status_message = 'Your requirements are complete and pending admin approval. Please wait for confirmation.';
        $status_class = 'warning';
        break;
    case 'Rejected':
        $status_message = 'Your enrollment has been rejected. Please contact the registrar\'s office for assistance.';
        $status_class = 'danger';
        break;
    case 'Pending_Requirements':
    default:
        $status_message = 'Please submit all required documents to complete your enrollment.';
        $status_class = 'info';
        break;
}

// Get notifications count for badge
$notif_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$student_id]);
$notif_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements - Student Portal | PLSNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/requirements.css">
    <style>
        .status-alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        .status-alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #10b981;
        }
        .status-alert.warning {
            background: #fff3e0;
            color: #856404;
            border-left: 4px solid #f59e0b;
        }
        .status-alert.danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .status-alert.info {
            background: #e3f2fd;
            color: #0c5460;
            border-left: 4px solid #0B4F2E;
        }
        .status-alert i {
            font-size: 24px;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-upload:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .enrollment-approved-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <div class="app-container">
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

            <div class="student-profile">
                <div class="student-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo htmlspecialchars($first_name); ?></div>
                <div class="student-role"><i class="fas fa-user-graduate"></i> Student</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php"><i class="fas fa-history"></i> Enrollment History</a></li>
                        <li><a href="requirements.php" class="active"><i class="fas fa-file-alt"></i> Requirements</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Enrollment Requirements</h1>
                    <p>Track and submit your requirements for enrollment</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Status Alert -->
            <div class="status-alert <?php echo $status_class; ?>">
                <i class="fas fa-<?php echo $status_class == 'success' ? 'check-circle' : ($status_class == 'warning' ? 'clock' : ($status_class == 'danger' ? 'exclamation-triangle' : 'info-circle')); ?>"></i>
                <div>
                    <strong><?php echo $status_class == 'success' ? 'Enrollment Approved!' : ($status_class == 'warning' ? 'Pending Approval' : ($status_class == 'danger' ? 'Enrollment Rejected' : 'Requirements Incomplete')); ?></strong><br>
                    <?php echo $status_message; ?>
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

            <?php if($enrollment): ?>
                <!-- Enrollment Info Card -->
                <div class="info-card">
                    <div class="info-card-content">
                        <div class="info-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="info-details">
                            <h3><?php echo htmlspecialchars($enrollment['grade_name']); ?></h3>
                            <p>School Year: <?php echo htmlspecialchars($enrollment['school_year']); ?></p>
                            <p>Student Type: 
                                <span class="student-type-badge student-type-<?php echo strtolower($enrollment['student_type'] ?? 'new'); ?>">
                                    <?php echo ucfirst($enrollment['student_type'] ?? 'New Student'); ?>
                                </span>
                            </p>
                            <p>Enrollment Status: 
                                <span class="status-badge status-<?php echo strtolower($enrollment['enrollment_status']); ?>">
                                    <?php 
                                    $status_display = $enrollment['enrollment_status'];
                                    if($status_display == 'Pending_Requirements') $status_display = 'Pending Requirements';
                                    echo $status_display;
                                    ?>
                                </span>
                            </p>
                            <?php if(isset($enrollment['strand']) && $enrollment['strand']): ?>
                                <p>Strand: <?php echo htmlspecialchars($enrollment['strand']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="info-status">
                            <?php if($enrollment['enrollment_status'] == 'Enrolled'): ?>
                                <div class="enrollment-approved-badge">
                                    <i class="fas fa-check-circle"></i> ENROLLED
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="progress-card">
                    <div class="progress-header">
                        <h3><i class="fas fa-chart-line"></i> Requirements Progress</h3>
                        <span class="progress-percentage"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo $submitted_count; ?></span>
                            <span class="stat-label">Submitted</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $missing_count; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $total_requirements; ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                    </div>
                </div>

                <!-- Requirements List -->
                <div class="requirements-card">
                    <h3><i class="fas fa-list-check"></i> Requirements List for <?php echo htmlspecialchars($enrollment['grade_name']); ?> 
                        <span class="student-type-label">(<?php echo ucfirst($enrollment['student_type'] ?? 'New Student'); ?>)</span>
                    </h3>
                    <div class="requirements-list">
                        <?php foreach($requirements_status as $key => $req): ?>
                            <div class="requirement-item <?php echo $req['submitted'] ? 'submitted' : 'missing'; ?>" data-key="<?php echo $key; ?>">
                                <div class="requirement-icon <?php echo $req['submitted'] ? 'submitted' : 'missing'; ?>">
                                    <i class="fas <?php echo $req['icon']; ?>"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name">
                                        <?php echo htmlspecialchars($req['label']); ?>
                                        <?php if($req['required']): ?>
                                            <span class="required-badge">Required</span>
                                        <?php else: ?>
                                            <span class="optional-badge">Optional</span>
                                        <?php endif; ?>
                                        <?php if($req['can_be_followed']): ?>
                                            <span class="follow-up-badge">Can be followed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="requirement-description">
                                        <?php echo htmlspecialchars($req['description']); ?>
                                    </div>
                                    <div class="requirement-status">
                                        <?php if($req['submitted']): ?>
                                            <span class="status-badge submitted-badge">
                                                <i class="fas fa-check-circle"></i> Submitted
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge missing-badge">
                                                <i class="fas fa-clock"></i> Not Submitted
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="requirement-actions">
                                    <?php if($req['submitted'] && $req['file']): ?>
                                        <a href="../<?php echo $req['file']; ?>" target="_blank" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <?php if($enrollment['enrollment_status'] != 'Enrolled' && $enrollment['enrollment_status'] != 'Rejected'): ?>
                                            <button class="btn-upload" onclick="openUploadModal('<?php echo $key; ?>', '<?php echo addslashes($req['label']); ?>', '<?php echo $req['allowed_types']; ?>', <?php echo $req['max_size']; ?>)">
                                                <i class="fas fa-upload"></i> Upload
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-upload" disabled style="opacity:0.5; cursor:not-allowed;">
                                                <i class="fas fa-lock"></i> Upload Disabled
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Instructions Card -->
                <div class="instructions-card">
                    <h3><i class="fas fa-info-circle"></i> How to Submit Requirements</h3>
                    <div class="instructions-list">
                        <div class="instruction-item">
                            <div class="instruction-number">1</div>
                            <div class="instruction-content">
                                <h4>Prepare Your Documents</h4>
                                <p>Make sure all required documents are clear and readable (PDF, JPG, or PNG format).</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">2</div>
                            <div class="instruction-content">
                                <h4>Click Upload Button</h4>
                                <p>Click the "Upload" button next to the requirement you want to submit.</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">3</div>
                            <div class="instruction-content">
                                <h4>Select File</h4>
                                <p>Choose the file from your computer and click "Submit".</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">4</div>
                            <div class="instruction-content">
                                <h4>Wait for Verification</h4>
                                <p>After all requirements are submitted, your enrollment will be reviewed by the registrar within 3-5 business days.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Card -->
                <div class="contact-card">
                    <i class="fas fa-headset"></i>
                    <div class="contact-info">
                        <h4>Need Help?</h4>
                        <p>Contact the Registrar's Office for assistance with your requirements.</p>
                        <div class="contact-details">
                            <span><i class="fas fa-phone"></i> (032) 123-4567</span>
                            <span><i class="fas fa-envelope"></i> registrar@plsnhs.edu.ph</span>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Enrollment Found -->
                <div class="no-enrollment-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You don't have an active enrollment. Please enroll first to see your requirements.</p>
                    <a href="enrollment.php" class="btn-enroll">Enroll Now</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-upload"></i> Upload Requirement</h3>
                <button class="close-modal" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="requirement_key" id="requirement_key">
                <div class="modal-body">
                    <div class="upload-info">
                        <p>Uploading: <strong id="requirement_label"></strong></p>
                        <div class="file-info">
                            <i class="fas fa-info-circle"></i> Allowed formats: <span id="allowed_types"></span><br>
                            <i class="fas fa-weight-hanging"></i> Max size: <span id="max_size"></span>MB
                        </div>
                    </div>
                    <div class="file-input-wrapper">
                        <input type="file" name="requirement_file" id="requirement_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required>
                        <label for="requirement_file" class="file-input-label">
                            <i class="fas fa-folder-open"></i> Choose File
                        </label>
                        <div class="selected-file" id="selectedFile"></div>
                    </div>
                    <div id="uploadProgress" class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="uploadProgressFill"></div>
                        </div>
                        <p class="progress-text">Uploading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" name="upload_requirement" class="btn-primary" id="submitUpload">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Chatbot Widget -->
    <?php include('../includes/chatbot.php'); ?>

    <!-- Pass PHP data to JavaScript -->
    <script>
        const requirementsData = {
            studentId: <?php echo $student_id; ?>,
            studentName: '<?php echo htmlspecialchars($student_name); ?>',
            enrollmentId: <?php echo $enrollment['id'] ?? 0; ?>,
            gradeLevel: '<?php echo htmlspecialchars($enrollment['grade_name'] ?? ''); ?>',
            studentType: '<?php echo htmlspecialchars($enrollment['student_type'] ?? 'new'); ?>',
            enrollmentStatus: '<?php echo $enrollment['enrollment_status'] ?? 'Pending_Requirements'; ?>',
            totalRequirements: <?php echo $total_requirements; ?>,
            submittedCount: <?php echo $submitted_count; ?>,
            missingCount: <?php echo $missing_count; ?>,
            completionPercentage: <?php echo $completion_percentage; ?>
        };
    </script>

    <!-- Requirements JS -->
    <script src="js/requirements.js"></script>
</body>
</html>