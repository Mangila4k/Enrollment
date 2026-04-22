<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_id = $_SESSION['user']['id'];
$registrar_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Function to generate student ID number
function generateStudentID($conn) {
    $prefix = "PLSNHS-";
    $current_year = date('Y');
    
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number LIKE ? AND role = 'Student' ORDER BY id_number DESC LIMIT 1");
    $stmt->execute([$prefix . $current_year . '-%']);
    $last_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($last_id && $last_id['id_number']) {
        $parts = explode('-', $last_id['id_number']);
        $last_number = intval(end($parts));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $formatted_number = str_pad($new_number, 7, '0', STR_PAD_LEFT);
    return $prefix . $current_year . '-' . $formatted_number;
}

// Get registrar profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$registrar_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_data['profile_picture'] ?? null;

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
        
        $title = "⚠️ Missing Requirements Notification";
        $message = "Dear {$student_name},\n\nThe school administration has notified you about the following missing requirements:\n\n";
        $message .= $missing_requirements . "\n\n";
        $message .= "Please submit these requirements as soon as possible to complete your enrollment process.\n\n";
        $message .= "Thank you for your cooperation.";
        
        $link = "requirements.php";
        
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

// Function to get missing requirements for a student
function getMissingRequirements($conn, $enrollment_id) {
    $stmt = $conn->prepare("
        SELECT e.*, g.grade_name, e.student_type
        FROM enrollments e
        JOIN grade_levels g ON e.grade_id = g.id
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$enrollment) return [];
    
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
        'Form 137' => 'form_137',
        'PSA Birth Certificate' => 'psa_birth_cert',
        'Good Moral Certificate' => 'good_moral_cert',
        'Certificate of Completion' => 'certificate_of_completion',
        '2x2 ID Pictures' => 'id_pictures',
        'Medical Certificate' => 'medical_cert',
        'Entrance Exam Result' => 'entrance_exam_result'
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
    
    return $missing_requirements;
}

// Handle enrollment approval/rejection with auto-generated ID
if(isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve') {
        $missing_reqs = getMissingRequirements($conn, $enrollment_id);
        if(count($missing_reqs) > 0) {
            $error_message = "Cannot approve enrollment. Missing requirements: " . implode(', ', $missing_reqs);
            header("Location: enrollments.php");
            exit();
        }
        
        $get_stmt = $conn->prepare("
            SELECT e.student_id, u.fullname, u.id_number, e.grade_id, g.grade_name, e.school_year
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            JOIN grade_levels g ON e.grade_id = g.id
            WHERE e.id = ?
        ");
        $get_stmt->execute([$enrollment_id]);
        $enrollment_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_id_number = $enrollment_data['id_number'];
        if(empty($new_id_number)) {
            $new_id_number = generateStudentID($conn);
            $update_user = $conn->prepare("UPDATE users SET id_number = ? WHERE id = ?");
            $update_user->execute([$new_id_number, $enrollment_data['student_id']]);
        }
        
        $status = 'Enrolled';
        $success_message = "Enrollment approved successfully! Student ID: " . $new_id_number;
        
        if($enrollment_data) {
            $notif_title = "✅ Enrollment Approved";
            $notif_message = "Dear {$enrollment_data['fullname']},\n\nCongratulations! Your enrollment for {$enrollment_data['grade_name']} for School Year {$enrollment_data['school_year']} has been APPROVED.\n\nYour Student ID Number is: {$new_id_number}\n\nWelcome to PLS NHS!";
            $notif_link = "dashboard.php";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'update', ?, ?, ?, NOW())");
            $notif_stmt->execute([$enrollment_data['student_id'], $notif_title, $notif_message, $notif_link]);
            
            $admin_notif_title = "✅ Student Enrollment Approved";
            $admin_notif_message = "Student " . $enrollment_data['fullname'] . " has been approved with ID: " . $new_id_number;
            $admin_notif_link = "enrollments.php";
            
            $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('Admin', 'Registrar') AND id != ?");
            $admin_stmt->execute([$registrar_id]);
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($admins as $admin) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'action', ?, ?, ?, NOW())");
                $notif_stmt->execute([$admin['id'], $admin_notif_title, $admin_notif_message, $admin_notif_link]);
            }
        }
    } elseif($action == 'reject') {
        $status = 'Rejected';
        $success_message = "Enrollment rejected.";
        
        $get_stmt = $conn->prepare("
            SELECT e.student_id, u.fullname, e.grade_id, g.grade_name, e.school_year
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            JOIN grade_levels g ON e.grade_id = g.id
            WHERE e.id = ?
        ");
        $get_stmt->execute([$enrollment_id]);
        $enrollment_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($enrollment_data) {
            $notif_title = "❌ Enrollment Rejected";
            $notif_message = "Dear {$enrollment_data['fullname']},\n\nWe regret to inform you that your enrollment for {$enrollment_data['grade_name']} for School Year {$enrollment_data['school_year']} has been REJECTED.\n\nPlease contact the registrar's office for more information.";
            $notif_link = "enrollment.php";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'alert', ?, ?, ?, NOW())");
            $notif_stmt->execute([$enrollment_data['student_id'], $notif_title, $notif_message, $notif_link]);
        }
    }
    
    $stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $enrollment_id]);
    header("Location: enrollments.php");
    exit();
}

// Handle enrollment deletion
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollment_id]);
    $success_message = "Enrollment record deleted successfully!";
    header("Location: enrollments.php");
    exit();
}

// Handle new enrollment (manual entry by registrar)
if(isset($_POST['add_enrollment'])) {
    $student_id = $_POST['student_id'];
    $grade_id = $_POST['grade_id'];
    $strand = $_POST['strand'] ?? null;
    $school_year = $_POST['school_year'];
    $status = $_POST['status'];
    $student_type = $_POST['student_type'] ?? 'new';
    
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, grade_id, strand, school_year, status, student_type, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if($stmt->execute([$student_id, $grade_id, $strand, $school_year, $status, $student_type])) {
        $enrollment_id = $conn->lastInsertId();
        $success_message = "New enrollment added successfully!";
        
        $get_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
        $get_stmt->execute([$student_id]);
        $student_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($student_data) {
            $notif_title = "📋 New Enrollment Record";
            $notif_message = "Dear {$student_data['fullname']},\n\nA new enrollment record has been created for you. Please check your requirements page to submit the necessary documents.";
            $notif_link = "requirements.php";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'action', ?, ?, ?, NOW())");
            $notif_stmt->execute([$student_id, $notif_title, $notif_message, $notif_link]);
        }
    } else {
        $error_message = "Error adding enrollment: " . $conn->errorInfo()[2];
    }
    header("Location: enrollments.php");
    exit();
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT e.*, u.fullname, u.email, u.id_number, u.profile_picture as student_profile_pic, g.grade_name 
          FROM enrollments e 
          LEFT JOIN users u ON e.student_id = u.id 
          LEFT JOIN grade_levels g ON e.grade_id = g.id 
          WHERE 1=1";

$params = [];

if($search) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ? OR e.school_year LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if($status_filter) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY e.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for dashboard
$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'");
$enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'");
$rejected_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get students and grades for dropdown
$students = $conn->query("SELECT * FROM users WHERE role='Student' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Get missing requirements for each enrollment
$enrollment_missing_reqs = [];
foreach($enrollments as $enrollment) {
    if($enrollment['status'] == 'Pending') {
        $missing = getMissingRequirements($conn, $enrollment['id']);
        $enrollment_missing_reqs[$enrollment['id']] = [
            'has_missing' => count($missing) > 0,
            'missing_count' => count($missing),
            'missing_list' => $missing
        ];
    } else {
        $enrollment_missing_reqs[$enrollment['id']] = [
            'has_missing' => false,
            'missing_count' => 0,
            'missing_list' => []
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Registrar Dashboard | PLS NHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/enrollments.css">
</head>
<body>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

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
                    <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <div class="avatar-initial"><?php echo strtoupper(substr($registrar_name, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="online-dot"></div>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></div>
            <div class="admin-role"><i class="fas fa-user-tie"></i> Registrar</div>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN MENU</div>
                <ul class="nav-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                    <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                <h1>Enrollment Management</h1>
                <p>Manage student enrollments and applications</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
            </div>
        </div>

        <?php if($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='enrollments.php'">
                <div class="stat-header"><h3>Total Enrollments</h3><div class="stat-icon"><i class="fas fa-file-signature"></i></div></div>
                <div class="stat-number"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">All time</div>
            </div>
            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Pending'">
                <div class="stat-header"><h3>Pending</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Awaiting review</div>
            </div>
            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Enrolled'">
                <div class="stat-header"><h3>Enrolled</h3><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                <div class="stat-number"><?php echo $enrolled_count; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Rejected'">
                <div class="stat-header"><h3>Rejected</h3><div class="stat-icon"><i class="fas fa-times-circle"></i></div></div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Not approved</div>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name, email, ID, or school year..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                <a href="enrollments.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                <div class="export-buttons">
                    <button type="button" class="btn-export" id="addEnrollmentBtn"><i class="fas fa-plus-circle"></i> Add</button>
                    <button type="button" class="btn-export" id="exportExcelBtn"><i class="fas fa-file-excel"></i> Export</button>
                    <button type="button" class="btn-export" id="printBtn"><i class="fas fa-print"></i> Print</button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-file-signature"></i> Enrollment Records</h3>
                <span class="badge-count">Total: <?php echo count($enrollments); ?> records</span>
            </div>
            <div class="table-container">
                <table class="data-table" id="enrollmentsTable">
                    <thead>
                        <tr><th>Student</th><th>ID Number</th><th>Grade & Strand</th><th>School Year</th><th>Student Type</th><th>Status</th><th>Requirements</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($enrollments) > 0): ?>
                            <?php foreach($enrollments as $row): ?>
                                <?php 
                                $missing_data = $enrollment_missing_reqs[$row['id']];
                                $has_missing = $missing_data['has_missing'];
                                $missing_count = $missing_data['missing_count'];
                                $missing_list = $missing_data['missing_list'];
                                $missing_list_json = htmlspecialchars(json_encode($missing_list), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><div class="student-info"><?php if(!empty($row['student_profile_pic']) && file_exists("../" . $row['student_profile_pic'])): ?><div class="student-avatar-img"><img src="../<?php echo $row['student_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Profile"></div><?php else: ?><div class="student-avatar"><?php echo strtoupper(substr($row['fullname'], 0, 1)); ?></div><?php endif; ?><div class="student-details"><h4><?php echo htmlspecialchars($row['fullname']); ?></h4><span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></span></div></div></td>
                                    <td><span class="id-badge"><?php echo $row['id_number'] ?? 'N/A'; ?></span></td>
                                    <td><span class="grade-tag"><?php echo htmlspecialchars($row['grade_name']); ?></span><?php if($row['strand']): ?><span class="grade-tag strand"><?php echo htmlspecialchars($row['strand']); ?></span><?php endif; ?></td>
                                    <td><span class="school-year"><?php echo htmlspecialchars($row['school_year']); ?></span></td>
                                    <td><span class="student-type-badge student-type-<?php echo strtolower($row['student_type'] ?? 'new'); ?>"><?php echo ucfirst($row['student_type'] ?? 'New'); ?></span></td>
                                    <td><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                                    <td><?php if($row['status'] == 'Pending'): ?><?php if($has_missing): ?><div class="missing-reqs-warning"><span class="missing-badge-sm"><i class="fas fa-exclamation-triangle"></i> <?php echo $missing_count; ?> missing</span><button class="btn-notify-missing" data-student-id="<?php echo $row['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($row['fullname']); ?>" data-student-email="<?php echo htmlspecialchars($row['email']); ?>" data-missing-list='<?php echo $missing_list_json; ?>' data-enrollment-id="<?php echo $row['id']; ?>"><i class="fas fa-bell"></i> Notify</button></div><?php else: ?><span class="complete-badge-sm"><i class="fas fa-check-circle"></i> Complete</span><?php endif; ?><?php else: ?><span class="grade-tag">—</span><?php endif; ?></td>
                                    <td><div class="action-btns"><a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="action-btn view" title="View"><i class="fas fa-eye"></i></a><?php if($row['status'] == 'Pending'): ?><?php if($has_missing): ?><button class="action-btn approve disabled" disabled title="Cannot approve - missing required requirements"><i class="fas fa-check-circle"></i></button><?php else: ?><a href="?action=approve&id=<?php echo $row['id']; ?>" class="action-btn approve" title="Approve" onclick="return confirmApprove('<?php echo htmlspecialchars($row['fullname']); ?>')"><i class="fas fa-check-circle"></i></a><?php endif; ?><a href="?action=reject&id=<?php echo $row['id']; ?>" class="action-btn reject" title="Reject" onclick="return confirmReject('<?php echo htmlspecialchars($row['fullname']); ?>')"><i class="fas fa-times-circle"></i></a><?php endif; ?><a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete" title="Delete" onclick="return confirmDelete('<?php echo htmlspecialchars($row['fullname']); ?>')"><i class="fas fa-trash"></i></a></div></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8"><div class="no-data"><i class="fas fa-file-signature"></i><h3>No Enrollment Records Found</h3><p>Try adjusting your filters or add a new enrollment.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="quick-actions-grid">
            <div class="quick-action-card"><div class="action-icon"><i class="fas fa-clock"></i></div><h3>Pending Actions</h3><p>You have <strong><?php echo $pending_count; ?></strong> pending enrollments waiting for your review.</p><a href="?status=Pending" class="btn-warning">Review Pending</a></div>
            <div class="quick-action-card"><div class="action-icon"><i class="fas fa-chart-bar"></i></div><h3>Reports</h3><p>Generate enrollment reports and statistics for analysis.</p><a href="reports.php" class="btn-primary">Generate Reports</a></div>
            <div class="quick-action-card"><div class="action-icon"><i class="fas fa-user-graduate"></i></div><h3>Students</h3><p>Manage student records and information.</p><a href="students.php" class="btn-primary">Manage Students</a></div>
        </div>
    </main>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Add New Enrollment</h3><button class="close-modal" id="closeModalBtn">&times;</button></div>
            <div class="modal-body">
                <form method="POST" id="addEnrollmentForm">
                    <div class="form-group"><label>Select Student <span class="required">*</span></label><select name="student_id" required><option value="">-- Choose Student --</option><?php foreach($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?> (<?php echo $s['email']; ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Grade Level <span class="required">*</span></label><select name="grade_id" id="gradeSelect" required><option value="">-- Select Grade --</option><?php foreach($grades as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo $g['grade_name']; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group" id="strandGroup" style="display: none;"><label>Strand (for Grade 11-12)</label><select name="strand"><option value="">-- Select Strand --</option><option value="STEM">STEM</option><option value="ABM">ABM</option><option value="HUMSS">HUMSS</option><option value="GAS">GAS</option><option value="ICT">ICT</option><option value="HE">HE</option><option value="IA">IA</option></select></div>
                    <div class="form-group"><label>Student Type <span class="required">*</span></label><select name="student_type" required><option value="new">New Student</option><option value="continuing">Continuing Student</option><option value="transferee">Transferee</option></select></div>
                    <div class="form-group"><label>School Year <span class="required">*</span></label><input type="text" name="school_year" placeholder="e.g., 2024-2025" value="<?php echo date('Y') . '-' . (date('Y')+1); ?>" required></div>
                    <div class="form-group"><label>Status <span class="required">*</span></label><select name="status" required><option value="Pending">Pending</option><option value="Enrolled">Enrolled</option><option value="Rejected">Rejected</option></select></div>
                    <div class="modal-footer"><button type="submit" name="add_enrollment" class="btn-save">Add Enrollment</button><button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/enrollments.js"></script>
</body>
</html>