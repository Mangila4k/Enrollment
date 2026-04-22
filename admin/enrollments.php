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

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle AJAX request for sending missing requirements notification
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'notify_missing') {
        $student_id = $_POST['student_id'] ?? 0;
        $student_name = $_POST['student_name'] ?? '';
        $missing_requirements = $_POST['missing_requirements'] ?? '';
        $enrollment_id = $_POST['enrollment_id'] ?? 0;
        
        if(!$student_id || !$missing_requirements) {
            echo json_encode(['success' => false, 'message' => 'Missing required information.']);
            exit();
        }
        
        // Create notification message
        $title = "⚠️ Missing Requirements Notification";
        $message = "Dear {$student_name},\n\nThe school administration has notified you about the following missing requirements:\n\n";
        $message .= $missing_requirements . "\n\n";
        $message .= "Please submit these requirements as soon as possible to complete your enrollment process.\n\n";
        $message .= "Thank you for your cooperation.";
        
        $link = "requirements.php";
        
        // Insert notification
        $insert_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, created_at, is_read) 
            VALUES (?, 'alert', ?, ?, ?, NOW(), 0)
        ");
        
        if($insert_stmt->execute([$student_id, $title, $message, $link])) {
            echo json_encode(['success' => true, 'message' => 'Notification sent successfully to ' . $student_name]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send notification.']);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

function generateStudentID($conn, $grade_level, $school_year) {
    $year = explode('-', $school_year)[0];
    $pattern = "PLSNHS-{$year}-%";
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number LIKE ? ORDER BY id_number DESC LIMIT 1");
    $stmt->execute([$pattern]);
    $last_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($last_id && $last_id['id_number']) {
        $parts = explode('-', $last_id['id_number']);
        $last_seq = intval(end($parts));
        $new_seq = $last_seq + 1;
    } else {
        $new_seq = 1;
    }
    
    $formatted_seq = str_pad($new_seq, 7, '0', STR_PAD_LEFT);
    return "PLSNHS-{$year}-{$formatted_seq}";
}

// Function to check if student has all required requirements
function hasAllRequiredRequirements($conn, $enrollment_id) {
    $stmt = $conn->prepare("
        SELECT e.*, g.grade_name 
        FROM enrollments e 
        JOIN grade_levels g ON e.grade_id = g.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$enrollment) return false;
    
    $grade_level = $enrollment['grade_name'];
    $student_type = $enrollment['student_type'] ?? 'new';
    
    $req_stmt = $conn->prepare("
        SELECT requirement_name FROM enrollment_requirements 
        WHERE grade_level = ? AND student_type = ? AND is_required = 1
    ");
    $req_stmt->execute([$grade_level, $student_type]);
    $required_reqs = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $field_map = [
        'Form 138' => 'form_138',
        'Certificate of Completion' => 'certificate_of_completion',
        'PSA Birth Certificate' => 'psa_birth_cert',
        '2x2 ID Pictures' => 'id_pictures',
        'Good Moral Certificate' => 'good_moral_cert',
        'Medical Certificate' => 'medical_cert',
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
        
        if($field_name && empty($enrollment[$field_name])) {
            return false;
        }
    }
    
    return true;
}

// Handle approve
if(isset($_GET['approve'])) {
    $enrollment_id = $_GET['approve'];
    
    if(!hasAllRequiredRequirements($conn, $enrollment_id)) {
        $_SESSION['error_message'] = "Cannot approve enrollment. Student has missing required requirements.";
        header("Location: enrollments.php");
        exit();
    }
    
    $stmt = $conn->prepare("
        SELECT e.*, g.grade_name, e.school_year, u.fullname, u.email
        FROM enrollments e 
        JOIN grade_levels g ON e.grade_id = g.id 
        JOIN users u ON e.student_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($enrollment) {
        $student_id_number = generateStudentID($conn, $enrollment['grade_name'], $enrollment['school_year']);
        
        $update = $conn->prepare("UPDATE enrollments SET status = 'Enrolled' WHERE id = ?");
        $update->execute([$enrollment_id]);
        
        $update_user = $conn->prepare("UPDATE users SET id_number = ?, status = 'approved' WHERE id = ?");
        $update_user->execute([$student_id_number, $enrollment['student_id']]);
        
        // Send approval notification
        $message = "Dear {$enrollment['fullname']},\n\nCongratulations! Your enrollment application has been APPROVED. Your Student ID is: {$student_id_number}\n\nWelcome to PLS NHS!";
        $title = "✅ Enrollment Approved - PLS NHS";
        
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'update', ?, ?, 'dashboard.php', NOW())");
        $insert_notif->execute([$enrollment['student_id'], $title, $message]);
        
        $_SESSION['success_message'] = "Enrollment approved successfully! Student ID: " . $student_id_number;
    } else {
        $_SESSION['error_message'] = "Enrollment not found.";
    }
    header("Location: enrollments.php");
    exit();
}

// Handle reject with comment
if(isset($_POST['reject_enrollment'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $rejection_reason = trim($_POST['rejection_reason']);
    
    if(empty($rejection_reason)) {
        $_SESSION['error_message'] = "Please provide a reason for rejection.";
        header("Location: enrollments.php");
        exit();
    }
    
    // Get enrollment details
    $stmt = $conn->prepare("
        SELECT e.*, u.fullname, u.email, u.id as student_id
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($enrollment) {
        $update = $conn->prepare("UPDATE enrollments SET status = 'Rejected' WHERE id = ?");
        $update->execute([$enrollment_id]);
        
        // Send rejection notification to student
        $message = "Dear {$enrollment['fullname']},\n\nWe regret to inform you that your enrollment application has been REJECTED.\n\nReason: {$rejection_reason}\n\nIf you have any questions, please contact the registrar's office for assistance.\n\nThank you.";
        $title = "❌ Enrollment Rejected - PLS NHS";
        
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'alert', ?, ?, 'enrollments.php', NOW())");
        $insert_notif->execute([$enrollment['student_id'], $title, $message]);
        
        $_SESSION['success_message'] = "Enrollment rejected. Notification sent to student.";
    } else {
        $_SESSION['error_message'] = "Enrollment not found.";
    }
    header("Location: enrollments.php");
    exit();
}

// Handle pending
if(isset($_GET['pending'])) {
    $enrollment_id = $_GET['pending'];
    $update = $conn->prepare("UPDATE enrollments SET status = 'Pending' WHERE id = ?");
    $update->execute([$enrollment_id]);
    
    // Get student info for notification
    $stmt = $conn->prepare("
        SELECT e.student_id, u.fullname 
        FROM enrollments e 
        JOIN users u ON e.student_id = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($enrollment) {
        $message = "Dear {$enrollment['fullname']},\n\nYour enrollment application has been moved back to PENDING status. Please check your requirements and contact the registrar's office for more information.";
        $title = "🔄 Enrollment Status Updated to Pending";
        
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'update', ?, ?, 'enrollments.php', NOW())");
        $insert_notif->execute([$enrollment['student_id'], $title, $message]);
    }
    
    $_SESSION['success_message'] = "Enrollment status set to pending. Notification sent to student.";
    header("Location: enrollments.php");
    exit();
}

// Handle delete
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $delete->execute([$enrollment_id]);
    $_SESSION['success_message'] = "Enrollment record deleted successfully!";
    header("Location: enrollments.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$strand_filter = isset($_GET['strand']) ? $_GET['strand'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "
    SELECT e.*, 
           u.fullname, 
           u.email, 
           u.id_number,
           g.grade_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE 1=1
";
$params = [];

if(!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if(!empty($grade_filter)) {
    $query .= " AND e.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($strand_filter)) {
    $query .= " AND e.strand = ?";
    $params[] = $strand_filter;
}

if(!empty($search_query)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY e.created_at DESC";

$enrollments_stmt = $conn->prepare($query);
$enrollments_stmt->execute($params);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $total_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$pending_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Pending'");
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$enrolled_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Enrolled'");
$enrolled_count = $enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$rejected_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Rejected'");
$rejected_count = $rejected_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get grade levels for filter
$grade_levels_stmt = $conn->query("SELECT * FROM grade_levels ORDER BY id");
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get requirement status for each enrollment
$enrollment_requirements_status = [];
foreach($enrollments as $enrollment) {
    $grade_level = $enrollment['grade_name'];
    $student_type = $enrollment['student_type'] ?? 'new';
    
    $req_stmt = $conn->prepare("
        SELECT requirement_name FROM enrollment_requirements 
        WHERE grade_level = ? AND student_type = ? AND is_required = 1
        ORDER BY display_order ASC
    ");
    $req_stmt->execute([$grade_level, $student_type]);
    $required_reqs = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $field_map = [
        'Form 138' => 'form_138',
        'Certificate of Completion' => 'certificate_of_completion',
        'PSA Birth Certificate' => 'psa_birth_cert',
        '2x2 ID Pictures' => 'id_pictures',
        'Good Moral Certificate' => 'good_moral_cert',
        'Medical Certificate' => 'medical_cert',
        'Entrance Exam Result' => 'entrance_exam_result',
        'Form 137' => 'form_137'
    ];
    
    $missing_requirements = [];
    foreach($required_reqs as $req) {
        $req_name = $req['requirement_name'];
        $field_name = null;
        
        foreach($field_map as $key => $field) {
            if(strpos($req_name, $key) !== false) {
                $field_name = $field;
                break;
            }
        }
        
        if($field_name && empty($enrollment[$field_name])) {
            $missing_requirements[] = $req_name;
        }
    }
    
    $enrollment_requirements_status[$enrollment['id']] = [
        'has_missing_required' => count($missing_requirements) > 0,
        'missing_count' => count($missing_requirements),
        'missing_list' => $missing_requirements
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollments Management - PLS NHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/enrollments.css">
</head>
<body>
    <div class="app-container">
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
                        <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> Enrollments</a></li>
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

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Enrollments Management</h1>
                    <p>Manage student enrollment applications</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header"><h3>Total Enrollments</h3><div class="stat-icon"><i class="fas fa-file-signature"></i></div></div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Pending</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Enrolled</h3><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Rejected</h3><div class="stat-icon"><i class="fas fa-times-circle"></i></div></div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Not approved</div>
                </div>
            </div>

            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <select name="grade" class="filter-select">
                            <option value="">All Grades</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>><?php echo $grade['grade_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="strand" class="filter-select">
                            <option value="">All Strands</option>
                            <option value="STEM" <?php echo $strand_filter == 'STEM' ? 'selected' : ''; ?>>STEM</option>
                            <option value="ABM" <?php echo $strand_filter == 'ABM' ? 'selected' : ''; ?>>ABM</option>
                            <option value="HUMSS" <?php echo $strand_filter == 'HUMSS' ? 'selected' : ''; ?>>HUMSS</option>
                            <option value="GAS" <?php echo $strand_filter == 'GAS' ? 'selected' : ''; ?>>GAS</option>
                            <option value="ICT" <?php echo $strand_filter == 'ICT' ? 'selected' : ''; ?>>ICT</option>
                            <option value="HE" <?php echo $strand_filter == 'HE' ? 'selected' : ''; ?>>HE</option>
                            <option value="IA" <?php echo $strand_filter == 'IA' ? 'selected' : ''; ?>>IA</option>
                        </select>
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                        <a href="enrollments.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-file-signature"></i> Enrollment Applications</h3>
                    <span class="badge-count">Total: <?php echo count($enrollments); ?> records</span>
                </div>
                <div class="table-container">
                    <table class="enrollments-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID Number</th>
                                <th>Grade & Strand</th>
                                <th>School Year</th>
                                <th>Form 138</th>
                                <th>Status</th>
                                <th>Requirements</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($enrollments) > 0): ?>
                                <?php foreach($enrollments as $enrollment): ?>
                                    <?php 
                                    $req_status = $enrollment_requirements_status[$enrollment['id']];
                                    $has_missing = $req_status['has_missing_required'];
                                    $missing_count = $req_status['missing_count'];
                                    $missing_list = $req_status['missing_list'];
                                    $missing_list_json = htmlspecialchars(json_encode($missing_list), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <?php 
                                                $student_profile_pic = null;
                                                $student_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                                                $student_stmt->execute([$enrollment['student_id']]);
                                                $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);
                                                $student_profile_pic = $student_data['profile_picture'] ?? null;
                                                ?>
                                                <?php if($student_profile_pic && file_exists("../" . $student_profile_pic)): ?>
                                                    <div class="student-avatar-img"><img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                                <?php else: ?>
                                                    <div class="student-avatar"><?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?></div>
                                                <?php endif; ?>
                                                <div class="student-details">
                                                    <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <td>
                                            <?php if($enrollment['id_number']): ?>
                                                <span class="id-badge"><?php echo $enrollment['id_number']; ?></span>
                                            <?php else: ?>
                                                <span class="grade-tag">Not assigned</span>
                                            <?php endif; ?>
                                        </div>
                                        <td>
                                            <span class="grade-tag"><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                            <?php if($enrollment['strand']): ?>
                                                <span class="strand-tag"><?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <td><span class="grade-tag"><?php echo htmlspecialchars($enrollment['school_year']); ?></span></div>
                                        <td>
                                            <?php if($enrollment['form_138']): ?>
                                                <a href="../<?php echo $enrollment['form_138']; ?>" target="_blank" class="document-link"><i class="fas fa-file-pdf"></i> View</a>
                                            <?php else: ?>
                                                <span class="grade-tag">No file</span>
                                            <?php endif; ?>
                                        </div>
                                        <td><span class="badge badge-<?php echo strtolower($enrollment['status']); ?>"><?php echo $enrollment['status']; ?></span></div>
                                        <td>
                                            <?php if($has_missing && $enrollment['status'] == 'Pending'): ?>
                                                <div class="requirements-warning">
                                                    <span class="missing-badge"><i class="fas fa-exclamation-triangle"></i> <?php echo $missing_count; ?> missing</span>
                                                    <button class="btn-notify-missing" 
                                                            data-student-id="<?php echo $enrollment['student_id']; ?>"
                                                            data-student-name="<?php echo htmlspecialchars($enrollment['fullname']); ?>"
                                                            data-student-email="<?php echo htmlspecialchars($enrollment['email']); ?>"
                                                            data-missing-list='<?php echo $missing_list_json; ?>'
                                                            data-enrollment-id="<?php echo $enrollment['id']; ?>">
                                                        <i class="fas fa-bell"></i> Notify
                                                    </button>
                                                </div>
                                            <?php elseif($enrollment['status'] == 'Pending'): ?>
                                                <span class="complete-badge"><i class="fas fa-check-circle"></i> Complete</span>
                                            <?php else: ?>
                                                <span class="grade-tag">—</span>
                                            <?php endif; ?>
                                        </div>
                                        <td><span class="grade-tag"><?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?></span></div>
                                        <td>
                                            <div class="action-btns">
                                                <?php if($enrollment['status'] == 'Pending'): ?>
                                                    <?php if($has_missing): ?>
                                                        <button class="action-btn approve disabled" disabled title="Cannot approve - missing required requirements"><i class="fas fa-check-circle"></i></button>
                                                    <?php else: ?>
                                                        <a href="?approve=<?php echo $enrollment['id']; ?>" class="action-btn approve" onclick="return confirm('Approve enrollment for <?php echo htmlspecialchars($enrollment['fullname']); ?>?')"><i class="fas fa-check-circle"></i></a>
                                                    <?php endif; ?>
                                                    <button class="action-btn reject" onclick="openRejectModal(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars($enrollment['fullname']); ?>')"><i class="fas fa-times-circle"></i></button>
                                                <?php elseif($enrollment['status'] == 'Enrolled' || $enrollment['status'] == 'Rejected'): ?>
                                                    <a href="?pending=<?php echo $enrollment['id']; ?>" class="action-btn pending" onclick="return confirm('Change status to pending?')"><i class="fas fa-undo-alt"></i></a>
                                                <?php endif; ?>
                                                <a href="view_enrollment.php?id=<?php echo $enrollment['id']; ?>" class="action-btn view"><i class="fas fa-eye"></i></a>
                                                <a href="?delete=<?php echo $enrollment['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this enrollment record?')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9"><div class="no-data"><i class="fas fa-file-signature"></i><h3>No Enrollments Found</h3></div></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Reject Enrollment</h3>
                <button class="close-modal" onclick="closeRejectModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="enrollment_id" id="reject_enrollment_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Student Name</label>
                        <input type="text" id="reject_student_name" class="form-control" readonly style="background:#f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>Reason for Rejection <span class="required">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="5" placeholder="Please provide a detailed reason for rejecting this enrollment..."></textarea>
                        <small class="form-text">This reason will be sent to the student via notification.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject_enrollment" class="btn-reject">Reject Enrollment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/enrollments.js"></script>
</body>
</html>