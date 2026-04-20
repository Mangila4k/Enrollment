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

// ========== FETCH ALL REQUIREMENTS ==========
// Define the requirements table structure
$requirements = [
    'form_138' => ['label' => 'Form 138 (Report Card)', 'icon' => 'fa-file-pdf', 'color' => '#ef4444'],
    'good_moral' => ['label' => 'Good Moral Certificate', 'icon' => 'fa-file-alt', 'color' => '#10b981'],
    'psa_birth_cert' => ['label' => 'PSA Birth Certificate', 'icon' => 'fa-file-pdf', 'color' => '#3b82f6'],
    'medical_cert' => ['label' => 'Medical Certificate', 'icon' => 'fa-notes-medical', 'color' => '#8b5cf6'],
    'parent_consent' => ['label' => 'Parent Consent Form', 'icon' => 'fa-file-signature', 'color' => '#f59e0b'],
    'report_card' => ['label' => 'Report Card (Previous Year)', 'icon' => 'fa-file-alt', 'color' => '#ec4899'],
    'esc_slip' => ['label' => 'ESC Slip', 'icon' => 'fa-file-pdf', 'color' => '#06b6d4'],
    'transfer_credentials' => ['label' => 'Transfer Credentials', 'icon' => 'fa-exchange-alt', 'color' => '#14b8a6']
];

// Check which requirement columns exist in the enrollments table
$existing_requirements = [];
$check_columns = $conn->query("SHOW COLUMNS FROM enrollments");
$existing_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[] = $col['Field'];
}

foreach($requirements as $key => $req) {
    if(in_array($key, $existing_columns)) {
        $existing_requirements[$key] = $req;
    }
}

// Get requirement values for this enrollment
$submitted_requirements = [];
$missing_requirements = [];

foreach($existing_requirements as $key => $req) {
    $value = $enrollment[$key] ?? null;
    if(!empty($value)) {
        $submitted_requirements[$key] = [
            'label' => $req['label'],
            'icon' => $req['icon'],
            'color' => $req['color'],
            'file' => $value
        ];
    } else {
        $missing_requirements[$key] = $req;
    }
}

// Calculate completion percentage
$total_requirements = count($existing_requirements);
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
                <h3><i class="fas fa-file-alt"></i> Submitted Requirements</h3>
                <div class="completion-badge">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $submitted_count; ?>/<?php echo $total_requirements; ?> Requirements</span>
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
                        <?php foreach($submitted_requirements as $key => $req): ?>
                            <div class="requirement-item submitted" data-key="<?php echo $key; ?>">
                                <div class="requirement-icon" style="background: <?php echo $req['color']; ?>20;">
                                    <i class="fas <?php echo $req['icon']; ?>" style="color: <?php echo $req['color']; ?>;"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name"><?php echo $req['label']; ?></div>
                                    <div class="requirement-status">
                                        <span class="status-badge-submitted">
                                            <i class="fas fa-check-circle"></i> Submitted
                                        </span>
                                    </div>
                                </div>
                                <div class="requirement-actions">
                                    <a href="../<?php echo $req['file']; ?>" target="_blank" class="btn-view-file">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-requirements">
                    <i class="fas fa-file-alt"></i>
                    <p>No requirements have been submitted yet.</p>
                </div>
            <?php endif; ?>

            <!-- Missing Requirements -->
            <?php if(count($missing_requirements) > 0): ?>
                <div class="requirements-grid missing-grid">
                    <h4><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Missing Requirements</h4>
                    <div class="requirements-list">
                        <?php foreach($missing_requirements as $key => $req): ?>
                            <div class="requirement-item missing" data-key="<?php echo $key; ?>">
                                <div class="requirement-icon" style="background: #fee2e2;">
                                    <i class="fas <?php echo $req['icon']; ?>" style="color: #dc2626;"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name"><?php echo $req['label']; ?></div>
                                    <div class="requirement-status">
                                        <span class="status-badge-missing">
                                            <i class="fas fa-times-circle"></i> Not Submitted
                                        </span>
                                    </div>
                                </div>
                                <div class="requirement-actions">
                                    <button class="btn-notify" onclick="notifyRequirement('<?php echo $key; ?>', '<?php echo addslashes($req['label']); ?>')">
                                        <i class="fas fa-bell"></i> Notify
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
                    <p>No previous enrollment records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

<script>
    // Pass PHP data to JavaScript
    const enrollmentData = {
        id: <?php echo $enrollment_id; ?>,
        studentName: '<?php echo htmlspecialchars($enrollment['fullname']); ?>',
        studentEmail: '<?php echo htmlspecialchars($enrollment['email']); ?>',
        studentId: '<?php echo $enrollment['student_id']; ?>',
        status: '<?php echo $enrollment['status']; ?>',
        totalHistory: <?php echo count($history); ?>,
        submittedCount: <?php echo $submitted_count; ?>,
        missingCount: <?php echo count($missing_requirements); ?>,
        completionPercentage: <?php echo $completion_percentage; ?>
    };
</script>

<!-- JavaScript Files -->
<script src="js/view_enrollment.js"></script>
</body>
</html>