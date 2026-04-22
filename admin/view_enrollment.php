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

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: enrollments.php");
    exit();
}

$enrollment_id = $_GET['id'];

// Get enrollment details with all related information
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

// Get ALL enrollment history
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

// Calculate student statistics
$student_id = $enrollment['student_id'];

$total_enrollments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?");
$total_enrollments_stmt->execute([$student_id]);
$total_enrollments = $total_enrollments_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// ========== FETCH REQUIREMENTS BASED ON GRADE LEVEL AND STUDENT TYPE ==========
$grade_level = $enrollment['grade_name']; // e.g., "Grade 7", "Grade 8", etc.
$student_type = $enrollment['student_type']; // 'new', 'continuing', 'transferee'

// Get requirements from enrollment_requirements table
$requirements_query = "
    SELECT * FROM enrollment_requirements 
    WHERE grade_level = ? AND student_type = ?
    ORDER BY display_order
";
$stmt = $conn->prepare($requirements_query);
$stmt->execute([$grade_level, $student_type]);
$requirements_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define requirement field mapping for submitted documents
$requirement_field_map = [
    'Form 138' => 'form_138',
    'Form 138 (Grade 6 Report Card)' => 'form_138',
    'Form 138 (Previous Grade Report Card)' => 'form_138',
    'Form 138 (Latest Report Card)' => 'form_138',
    'Form 138 (Grade 10 Report Card)' => 'form_138',
    'Form 138 (Grade 11 Report Card)' => 'form_138',
    'Certificate of Completion' => 'certificate_of_completion',
    'Certificate of Completion (Elementary)' => 'certificate_of_completion',
    'Certificate of Completion (Junior High)' => 'certificate_of_completion',
    'PSA Birth Certificate' => 'psa_birth_cert',
    '2x2 ID Pictures' => 'id_pictures',
    'Good Moral Certificate' => 'good_moral_cert',
    'Medical/Dental Certificate' => 'medical_cert',
    'Medical Certificate' => 'medical_cert',
    'Entrance Exam Result' => 'entrance_exam_result',
    'Entrance Exam / Interview Result' => 'entrance_exam_result',
    'Entrance Exam / Screening Result' => 'entrance_exam_result',
    'Form 137' => 'form_137',
    'Form 137 (Permanent Record - to follow)' => 'form_137',
    'Form 137 (Permanent Record)' => 'form_137',
    'SHS Enrollment Form (Track/Strand Selection)' => 'strand_form'
];

// Process requirements - separate submitted and missing
$submitted_requirements = [];
$missing_requirements = [];

foreach($requirements_list as $req) {
    $req_name = $req['requirement_name'];
    $field_name = null;
    
    // Find matching field for this requirement
    foreach($requirement_field_map as $key => $field) {
        if(strpos($req_name, $key) !== false || $key == $req_name) {
            $field_name = $field;
            break;
        }
    }
    
    // Check if requirement is submitted
    $is_submitted = false;
    $file_path = null;
    
    if($field_name && !empty($enrollment[$field_name])) {
        $is_submitted = true;
        $file_path = $enrollment[$field_name];
    }
    
    $requirement_data = [
        'id' => $req['id'],
        'name' => $req_name,
        'is_required' => $req['is_required'],
        'can_be_followed' => $req['can_be_followed'],
        'display_order' => $req['display_order'],
        'field_name' => $field_name,
        'is_submitted' => $is_submitted,
        'file_path' => $file_path
    ];
    
    if($is_submitted) {
        $submitted_requirements[] = $requirement_data;
    } else {
        $missing_requirements[] = $requirement_data;
    }
}

// Calculate completion percentage
$total_requirements = count($requirements_list);
$submitted_count = count($submitted_requirements);
$completion_percentage = $total_requirements > 0 ? round(($submitted_count / $total_requirements) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollment - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/view_enrollment.css">
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Enrollment Details</h1>
                <p>View enrollment information</p>
            </div>
            <a href="enrollments.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Enrollments
            </a>
        </div>

        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Student Information -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                <a href="view_student.php?id=<?php echo $enrollment['student_id']; ?>" class="view-link">
                    View Full Profile <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="student-info">
                <?php if($enrollment['student_profile_pic'] && file_exists("../" . $enrollment['student_profile_pic'])): ?>
                    <div class="student-avatar-large-img">
                        <img src="../<?php echo $enrollment['student_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Profile">
                    </div>
                <?php else: ?>
                    <div class="student-avatar-large">
                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="student-details">
                    <h3><?php echo htmlspecialchars($enrollment['fullname']); ?></h3>
                    <div class="student-meta">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> ID: <?php echo $enrollment['id_number'] ?? 'Not assigned'; ?></span>
                    </div>
                </div>
            </div>

            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-mini-label">Total Enrollments</div>
                </div>
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?php echo date('Y', strtotime($enrollment['student_created_at'])); ?></div>
                    <div class="stat-mini-label">Since Year</div>
                </div>
            </div>
        </div>

        <!-- Enrollment Information -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Enrollment Information</h3>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Type</div>
                    <div class="info-value">
                        <i class="fas fa-user-tag"></i>
                        <?php echo ucfirst($enrollment['student_type']); ?>
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
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo htmlspecialchars($enrollment['school_year']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Application Date</div>
                    <div class="info-value">
                        <i class="fas fa-clock"></i>
                        <?php echo date('F d, Y', strtotime($enrollment['created_at'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge-small status-<?php echo strtolower($enrollment['status']); ?>">
                            <?php echo $enrollment['status']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== REQUIREMENTS SECTION ========== -->
        <div class="detail-card requirements-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Enrollment Requirements</h3>
                <div class="completion-badge">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $submitted_count; ?>/<?php echo $total_requirements; ?> Requirements</span>
                </div>
            </div>

            <!-- Grade & Student Type Info -->
            <div class="requirements-info-banner">
                <div class="info-banner-item">
                    <i class="fas fa-layer-group"></i>
                    <span>Grade Level: <strong><?php echo htmlspecialchars($grade_level); ?></strong></span>
                </div>
                <div class="info-banner-item">
                    <i class="fas fa-user-tag"></i>
                    <span>Student Type: <strong><?php echo ucfirst($student_type); ?></strong></span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-label">
                    <span>Completion Progress</span>
                    <span class="progress-percentage"><?php echo $completion_percentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                </div>
            </div>

            <!-- Submitted Requirements Grid -->
            <?php if(count($submitted_requirements) > 0): ?>
                <div class="requirements-grid submitted-grid">
                    <h4><i class="fas fa-check-circle" style="color: #10b981;"></i> Submitted Requirements</h4>
                    <div class="requirements-list">
                        <?php foreach($submitted_requirements as $req): ?>
                            <div class="requirement-item submitted" data-key="<?php echo $req['id']; ?>">
                                <div class="requirement-icon" style="background: #10b98120;">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name"><?php echo htmlspecialchars($req['name']); ?></div>
                                    <div class="requirement-status">
                                        <span class="status-badge-submitted">
                                            <i class="fas fa-check-circle"></i> Submitted
                                        </span>
                                        <?php if($req['is_required']): ?>
                                            <span class="requirement-badge badge-required">Required</span>
                                        <?php else: ?>
                                            <span class="requirement-badge badge-optional">Optional</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if($req['file_path']): ?>
                                    <div class="requirement-actions">
                                        <a href="../<?php echo $req['file_path']; ?>" target="_blank" class="btn-view-file">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Missing Requirements -->
            <?php if(count($missing_requirements) > 0): ?>
                <div class="requirements-grid missing-grid">
                    <h4><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Missing Requirements</h4>
                    <div class="requirements-list">
                        <?php foreach($missing_requirements as $req): ?>
                            <div class="requirement-item missing" data-key="<?php echo $req['id']; ?>">
                                <div class="requirement-icon" style="background: #fee2e2;">
                                    <i class="fas fa-times-circle" style="color: #dc2626;"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name"><?php echo htmlspecialchars($req['name']); ?></div>
                                    <div class="requirement-status">
                                        <span class="status-badge-missing">
                                            <i class="fas fa-times-circle"></i> Not Submitted
                                        </span>
                                        <?php if($req['is_required']): ?>
                                            <span class="requirement-badge badge-required">Required</span>
                                        <?php else: ?>
                                            <span class="requirement-badge badge-optional">Optional</span>
                                        <?php endif; ?>
                                        <?php if($req['can_be_followed']): ?>
                                            <span class="requirement-badge badge-follow">Can be followed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="requirement-actions">
                                    <button class="btn-notify" onclick="notifyRequirement('<?php echo $req['id']; ?>', '<?php echo addslashes($req['name']); ?>')">
                                        <i class="fas fa-bell"></i> Notify
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- If no requirements found -->
            <?php if($total_requirements == 0): ?>
                <div class="no-requirements">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No specific requirements found for <?php echo htmlspecialchars($grade_level); ?> - <?php echo ucfirst($student_type); ?></p>
                </div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div class="requirements-summary">
                <div class="summary-item">
                    <div class="summary-value"><?php echo $submitted_count; ?></div>
                    <div class="summary-label">Submitted</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo count($missing_requirements); ?></div>
                    <div class="summary-label">Missing</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $completion_percentage; ?>%</div>
                    <div class="summary-label">Complete</div>
                </div>
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
                                <th>School Year</th>
                                <th>Grade Level</th>
                                <th>Strand</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                                    <td><?php echo $row['strand'] ?: '—'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </div>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-history"></i>
                    <p>No previous enrollment records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Pass PHP data to JavaScript
        const enrollmentData = {
            id: <?php echo $enrollment_id; ?>,
            studentName: '<?php echo htmlspecialchars($enrollment['fullname']); ?>',
            studentEmail: '<?php echo htmlspecialchars($enrollment['email']); ?>',
            studentId: '<?php echo $enrollment['student_id']; ?>',
            gradeLevel: '<?php echo htmlspecialchars($grade_level); ?>',
            studentType: '<?php echo $student_type; ?>',
            status: '<?php echo $enrollment['status']; ?>',
            totalHistory: <?php echo count($history); ?>,
            submittedCount: <?php echo $submitted_count; ?>,
            missingCount: <?php echo count($missing_requirements); ?>,
            completionPercentage: <?php echo $completion_percentage; ?>,
            totalRequirements: <?php echo $total_requirements; ?>
        };
    </script>

    <!-- JavaScript Files -->
    <script src="js/view_enrollment.js"></script>
</body>
</html>