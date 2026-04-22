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

// Get registrar profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$registrar_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_data['profile_picture'] ?? null;

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: enrollments.php");
    exit();
}

$enrollment_id = $_GET['id'];

// Get enrollment details with all related information including profile picture
$query = "
    SELECT 
        e.*,
        u.id as student_id,
        u.fullname,
        u.email,
        u.id_number,
        u.profile_picture as student_profile_pic,
        u.created_at as student_created_at,
        g.grade_name,
        g.id as grade_id
    FROM enrollments e
    LEFT JOIN users u ON e.student_id = u.id
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.id = ?
";

$stmt = $conn->prepare($query);
$stmt->execute([$enrollment_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$enrollment) {
    header("Location: enrollments.php");
    exit();
}

// Get ALL enrollment history (all enrollments of the same student including current)
$history_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ?
    ORDER BY e.created_at DESC
";
$stmt = $conn->prepare($history_query);
$stmt->execute([$enrollment['student_id']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get requirements based on grade level and student type from enrollment_requirements table
$requirements_query = "
    SELECT * FROM enrollment_requirements 
    WHERE grade_level = ? AND student_type = ?
    ORDER BY display_order ASC
";
$stmt = $conn->prepare($requirements_query);
$stmt->execute([$enrollment['grade_name'], $enrollment['student_type']]);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no specific requirements found, try to get generic ones for the grade level
if(empty($requirements)) {
    $stmt = $conn->prepare("
        SELECT * FROM enrollment_requirements 
        WHERE grade_level = ? AND student_type = 'new'
        ORDER BY display_order ASC
    ");
    $stmt->execute([$enrollment['grade_name']]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Map requirement names to database columns for document lookup
$requirement_column_map = [
    'Form 138' => 'form_138',
    'Form 137' => 'form_137',
    'PSA Birth Certificate' => 'psa_birth_cert',
    'PSA Birth' => 'psa_birth_cert',
    'Good Moral Certificate' => 'good_moral_cert',
    'Good Moral' => 'good_moral_cert',
    'Certificate of Completion' => 'certificate_of_completion',
    '2x2 ID Pictures' => 'id_pictures',
    'ID Pictures' => 'id_pictures',
    'Medical Certificate' => 'medical_cert',
    'Medical/Dental Certificate' => 'medical_cert',
    'Entrance Exam Result' => 'entrance_exam_result',
    'Entrance Exam' => 'entrance_exam_result',
    'Interview Result' => 'entrance_exam_result',
    'SHS Enrollment Form' => 'other_documents',
    'ESC Slip' => 'other_documents',
    'Transfer Credentials' => 'other_documents',
    'Report Card' => 'form_138'
];

// Function to check if a requirement is submitted
function isRequirementSubmitted($requirement_name, $enrollment, $column_map) {
    foreach($column_map as $key => $column) {
        if(stripos($requirement_name, $key) !== false) {
            return !empty($enrollment[$column]);
        }
    }
    return false;
}

// Function to get the submitted file path for a requirement
function getSubmittedFile($requirement_name, $enrollment, $column_map) {
    foreach($column_map as $key => $column) {
        if(stripos($requirement_name, $key) !== false) {
            return $enrollment[$column] ?? null;
        }
    }
    return null;
}

// Handle status update
if(isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
    
    if($new_status == 'Rejected' && empty($rejection_reason)) {
        $error_message = "Please provide a reason for rejection.";
    } else {
        $update_query = "UPDATE enrollments SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if($stmt->execute([$new_status, $enrollment_id])) {
            // Send notification to student
            $notif_title = "";
            $notif_message = "";
            
            if($new_status == 'Enrolled') {
                $notif_title = "✅ Enrollment Approved";
                $notif_message = "Dear {$enrollment['fullname']},\n\nCongratulations! Your enrollment for {$enrollment['grade_name']} for School Year {$enrollment['school_year']} has been APPROVED.\n\nWelcome to PLS NHS!";
            } elseif($new_status == 'Rejected') {
                $notif_title = "❌ Enrollment Rejected";
                $notif_message = "Dear {$enrollment['fullname']},\n\nWe regret to inform you that your enrollment for {$enrollment['grade_name']} for School Year {$enrollment['school_year']} has been REJECTED.\n\nReason: {$rejection_reason}\n\nPlease contact the registrar's office for more information.";
            } elseif($new_status == 'Pending') {
                $notif_title = "🔄 Enrollment Status Updated";
                $notif_message = "Dear {$enrollment['fullname']},\n\nYour enrollment status has been updated to PENDING. Please check your requirements and wait for further updates.";
            }
            
            if($notif_title && $notif_message) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'update', ?, ?, 'dashboard.php', NOW())");
                $notif_stmt->execute([$enrollment['student_id'], $notif_title, $notif_message]);
            }
            
            $_SESSION['success_message'] = "Enrollment status updated successfully!";
            header("Location: view_enrollment.php?id=" . $enrollment_id);
            exit();
        } else {
            $error_message = "Error updating status";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollment - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/view_enrollment.css">
</head>
<body data-enrollment-id="<?php echo $enrollment_id; ?>" data-student-name="<?php echo htmlspecialchars($enrollment['fullname']); ?>">
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Enrollment Details</h1>
                <p>View and manage enrollment information</p>
            </div>
            <a href="enrollments.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Enrollments
            </a>
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

        <!-- Student Information Card -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                    <?php echo $enrollment['status']; ?>
                </span>
            </div>

            <div class="student-info-card">
                <?php if(!empty($enrollment['student_profile_pic']) && file_exists("../" . $enrollment['student_profile_pic'])): ?>
                    <div class="student-avatar-large-img">
                        <img src="../<?php echo $enrollment['student_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Profile">
                    </div>
                <?php else: ?>
                    <div class="student-avatar-large">
                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="student-details">
                    <h2><?php echo htmlspecialchars($enrollment['fullname']); ?></h2>
                    <div class="student-meta">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> ID: <?php echo $enrollment['id_number'] ?? 'Not assigned'; ?></span>
                        <span><i class="fas fa-calendar-alt"></i> Registered: <?php echo date('M d, Y', strtotime($enrollment['student_created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Type</div>
                    <div class="info-value">
                        <i class="fas fa-user-tag"></i>
                        <?php echo ucfirst($enrollment['student_type'] ?? 'New'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Grade Level</div>
                    <div class="info-value">
                        <i class="fas fa-layer-group"></i>
                        <?php echo htmlspecialchars($enrollment['grade_name']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Strand</div>
                    <div class="info-value">
                        <i class="fas fa-tag"></i>
                        <?php echo $enrollment['strand'] ?: 'Not Applicable'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">School Year</div>
                    <div class="info-value">
                        <i class="fas fa-calendar"></i>
                        <?php echo htmlspecialchars($enrollment['school_year']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Application Date</div>
                    <div class="info-value">
                        <i class="fas fa-clock"></i>
                        <?php echo date('F d, Y h:i A', strtotime($enrollment['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requirements Section -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Enrollment Requirements</h3>
                <span class="badge-count"><?php echo $enrollment['grade_name']; ?> - <?php echo ucfirst($enrollment['student_type'] ?? 'New'); ?></span>
            </div>

            <div class="requirements-grid">
                <?php if(count($requirements) > 0): ?>
                    <?php foreach($requirements as $req): ?>
                        <?php 
                        $is_submitted = isRequirementSubmitted($req['requirement_name'], $enrollment, $requirement_column_map);
                        $submitted_file = getSubmittedFile($req['requirement_name'], $enrollment, $requirement_column_map);
                        ?>
                        <div class="requirement-item <?php echo $is_submitted ? 'submitted' : 'missing'; ?>">
                            <div class="requirement-info">
                                <i class="fas fa-<?php echo $is_submitted ? 'check-circle' : 'file-alt'; ?>"></i>
                                <span class="requirement-name"><?php echo htmlspecialchars($req['requirement_name']); ?></span>
                                <span class="requirement-badge badge-<?php echo $req['is_required'] ? 'required' : 'optional'; ?>">
                                    <?php echo $req['is_required'] ? 'Required' : 'Optional'; ?>
                                </span>
                                <?php if($req['can_be_followed']): ?>
                                    <span class="requirement-badge badge-follow">Can be followed</span>
                                <?php endif; ?>
                            </div>
                            <div class="requirement-status">
                                <?php if($is_submitted && $submitted_file): ?>
                                    <span class="status-submitted">
                                        <i class="fas fa-check-circle"></i> Submitted
                                    </span>
                                    <a href="../<?php echo $submitted_file; ?>" target="_blank" class="view-doc-link">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="status-missing">
                                        <i class="fas fa-times-circle"></i> Missing
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data" style="grid-column: span 2;">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No specific requirements found for <?php echo $enrollment['grade_name']; ?> - <?php echo ucfirst($enrollment['student_type'] ?? 'New'); ?> student type.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submitted Documents Summary -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-file-upload"></i> Submitted Documents</h3>
            </div>

            <div class="info-grid">
                <?php 
                $has_documents = false;
                $document_display = [
                    'form_138' => ['label' => 'Form 138 (Report Card)', 'icon' => 'fa-file-pdf'],
                    'form_137' => ['label' => 'Form 137 (Permanent Record)', 'icon' => 'fa-file-pdf'],
                    'psa_birth_cert' => ['label' => 'PSA Birth Certificate', 'icon' => 'fa-file-pdf'],
                    'good_moral_cert' => ['label' => 'Good Moral Certificate', 'icon' => 'fa-file-pdf'],
                    'certificate_of_completion' => ['label' => 'Certificate of Completion', 'icon' => 'fa-file-pdf'],
                    'id_pictures' => ['label' => '2x2 ID Pictures', 'icon' => 'fa-file-image'],
                    'medical_cert' => ['label' => 'Medical Certificate', 'icon' => 'fa-file-pdf'],
                    'entrance_exam_result' => ['label' => 'Entrance Exam Result', 'icon' => 'fa-file-pdf']
                ];
                
                foreach($document_display as $field => $info):
                    if(!empty($enrollment[$field])):
                        $has_documents = true;
                ?>
                <div class="info-item">
                    <div class="info-label"><?php echo $info['label']; ?></div>
                    <div class="info-value">
                        <a href="../<?php echo $enrollment[$field]; ?>" target="_blank" class="document-link">
                            <i class="fas <?php echo $info['icon']; ?>"></i> View Document
                        </a>
                    </div>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>
                
                <?php if(!$has_documents): ?>
                <div class="no-data" style="grid-column: span 2;">
                    <i class="fas fa-file-upload"></i>
                    <p>No documents have been submitted yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status Update Form -->
            <div class="status-update">
                <h4><i class="fas fa-edit"></i> Update Enrollment Status</h4>
                <form method="POST" class="status-form">
                    <div class="form-group">
                        <label>Change Status</label>
                        <select name="status" class="status-select" id="statusSelect">
                            <option value="Pending" <?php echo $enrollment['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Enrolled" <?php echo $enrollment['status'] == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Rejected" <?php echo $enrollment['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="rejectionReasonGroup" style="display: none;">
                        <label>Rejection Reason <span class="required">*</span></label>
                        <textarea name="rejection_reason" id="rejectionReason" class="form-control" rows="3" placeholder="Please provide a reason for rejecting this enrollment..."></textarea>
                        <small class="form-text">This reason will be sent to the student via notification.</small>
                    </div>
                    
                    <button type="submit" name="update_status" class="btn-update">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Details
                </button>
                <a href="send_requirement_notification.php?enrollment_id=<?php echo $enrollment_id; ?>" class="btn-notify">
                    <i class="fas fa-bell"></i> Notify Student
                </a>
            </div>
        </div>

        <!-- Enrollment History -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Enrollment History</h3>
                <span class="badge-count"><?php echo count($history); ?> records</span>
            </div>

            <?php if(count($history) > 0): ?>
                <div class="table-container">
                    <table class="data-table history-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>School Year</th>
                                <th>Grade Level</th>
                                <th>Strand</th>
                                <th>Student Type</th>
                                <th>Applied Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $row): ?>
                                <tr class="<?php echo ($row['id'] == $enrollment_id) ? 'current-enrollment' : ''; ?>">
                                    <td>
                                        <?php if($row['id'] == $enrollment_id): ?>
                                            <span class="current-badge">Current</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?php echo strtolower($row['status']); ?>" style="padding: 4px 12px; font-size: 11px;">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                                    <td><?php echo $row['strand'] ?: '—'; ?></td>
                                    <td>
                                        <span class="student-type-badge student-type-<?php echo strtolower($row['student_type'] ?? 'new'); ?>">
                                            <?php echo ucfirst($row['student_type'] ?? 'New'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-history"></i>
                    <p>No enrollment records found for this student.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="js/view_enrollment.js"></script>
</body>
</html>