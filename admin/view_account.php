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
    header("Location: manage_accounts.php");
    exit();
}

$account_id = $_GET['id'];

// Get account details with profile picture
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$account_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$account) {
    header("Location: manage_accounts.php");
    exit();
}

// Get account profile picture
$account_profile_pic = $account['profile_picture'] ?? null;

// Get account statistics based on role
$stats = [];

if($account['role'] == 'Student') {
    // Get enrollment count
    $enrollment_stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?");
    $enrollment_stmt->execute([$account_id]);
    $enrollment_count = $enrollment_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get current enrollment
    $current_stmt = $conn->prepare("
        SELECT e.*, g.grade_name 
        FROM enrollments e 
        LEFT JOIN grade_levels g ON e.grade_id = g.id 
        WHERE e.student_id = ? AND e.status = 'Enrolled' 
        ORDER BY e.created_at DESC LIMIT 1
    ");
    $current_stmt->execute([$account_id]);
    $current_enrollment = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // FIXED: Removed attendance query since table doesn't exist
    $attendance_count = 0;
    
    $stats = [
        'enrollments' => $enrollment_count,
        'current_enrollment' => $current_enrollment,
        'attendance' => $attendance_count
    ];
}
elseif($account['role'] == 'Teacher') {
    // Get sections advised
    $sections_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE adviser_id = ?");
    $sections_stmt->execute([$account_id]);
    $sections_advised = $sections_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get sections list
    $sections_list_stmt = $conn->prepare("
        SELECT s.*, g.grade_name 
        FROM sections s 
        LEFT JOIN grade_levels g ON s.grade_id = g.id 
        WHERE s.adviser_id = ?
        ORDER BY g.id, s.section_name
    ");
    $sections_list_stmt->execute([$account_id]);
    $sections_list = $sections_list_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'sections_count' => $sections_advised,
        'sections' => $sections_list
    ];
}
elseif($account['role'] == 'Registrar') {
    // Get processed enrollments
    $processed_stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments");
    $processed_stmt->execute();
    $processed_count = $processed_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats = [
        'processed' => $processed_count
    ];
}
elseif($account['role'] == 'Admin') {
    // Get system stats for admin
    $users_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $users_stmt->execute();
    $total_users = $users_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $enrollments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments");
    $enrollments_stmt->execute();
    $total_enrollments = $enrollments_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats = [
        'total_users' => $total_users,
        'total_enrollments' => $total_enrollments
    ];
}

$account_created = $account['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Account - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- View Account CSS -->
    <link rel="stylesheet" href="css/view_account.css">
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
                <h1>Account Details</h1>
                <p>View complete account information</p>
            </div>
            <a href="manage_accounts.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Accounts</a>
        </div>

        <!-- Account Profile Card -->
        <div class="profile-card">
            <div class="profile-avatar-large">
                <?php if($account_profile_pic && file_exists("../" . $account_profile_pic)): ?>
                    <img src="../<?php echo $account_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($account['fullname']); ?></h2>
                
                <div class="profile-meta">
                    <span class="profile-meta-item">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-id-card"></i> ID: <?php echo $account['id_number'] ?? 'Not assigned'; ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($account['created_at'])); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                    </span>
                </div>

                <div>
                    <span class="role-badge role-<?php echo strtolower($account['role']); ?>">
                        <i class="fas fa-<?php 
                            echo $account['role'] == 'Admin' ? 'user-shield' : 
                                ($account['role'] == 'Registrar' ? 'user-tie' : 
                                ($account['role'] == 'Teacher' ? 'chalkboard-user' : 'user-graduate')); 
                        ?>"></i>
                        <?php echo $account['role']; ?>
                    </span>
                </div>

                <div class="action-buttons">
                    <a href="edit_account.php?id=<?php echo $account_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Account
                    </a>
                    <?php if($account['id'] != $_SESSION['user']['id']): ?>
                        <a href="manage_accounts.php?delete=<?php echo $account['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards (Role-specific) -->
        <div class="stats-grid">
            <?php if($account['role'] == 'Student'): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['enrollments']; ?></div>
                        <div class="stat-label">Total Enrollments</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['attendance']; ?></div>
                        <div class="stat-label">Attendance Records</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['current_enrollment']['grade_name'] ?? 'N/A'; ?></div>
                        <div class="stat-label">Current Grade</div>
                    </div>
                </div>
            <?php elseif($account['role'] == 'Teacher'): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['sections_count']; ?></div>
                        <div class="stat-label">Advisory Sections</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['sections_count'] > 0 ? 'Active' : 'No Section'; ?></div>
                        <div class="stat-label">Teaching Status</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo date('Y'); ?></div>
                        <div class="stat-label">Current Year</div>
                    </div>
                </div>
            <?php elseif($account['role'] == 'Registrar'): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['processed']; ?></div>
                        <div class="stat-label">Enrollments Processed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <div class="stat-number">Active</div>
                        <div class="stat-label">Account Status</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo date('Y'); ?></div>
                        <div class="stat-label">School Year</div>
                    </div>
                </div>
            <?php elseif($account['role'] == 'Admin'): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_enrollments']; ?></div>
                        <div class="stat-label">Total Enrollments</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-number">System</div>
                        <div class="stat-label">Administrator</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Account Information -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Account ID</div>
                    <div class="info-value"></i> <?php echo $account['id']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><i class="fas fa-user"></i> <?php echo htmlspecialchars($account['fullname']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ID Number</div>
                    <div class="info-value"><i class="fas fa-id-card"></i> <?php echo $account['id_number'] ?? 'Not assigned'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value"><i class="fas fa-user-tag"></i> <?php echo $account['role']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Created</div>
                    <div class="info-value"><i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y h:i A', strtotime($account['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email Verified</div>
                    <div class="info-value">
                        <i class="fas fa-<?php echo $account['email_verified'] ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo $account['email_verified'] ? '#10b981' : '#ef4444'; ?>"></i>
                        <?php echo $account['email_verified'] ? 'Verified' : 'Not Verified'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <i class="fas fa-<?php echo $account['status'] == 'approved' ? 'check-circle' : ($account['status'] == 'pending' ? 'clock' : 'times-circle'); ?>" style="color: <?php echo $account['status'] == 'approved' ? '#10b981' : ($account['status'] == 'pending' ? '#f59e0b' : '#ef4444'); ?>"></i>
                        <?php echo ucfirst($account['status']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role-specific Details -->
        <?php if($account['role'] == 'Student' && isset($stats['current_enrollment']) && $stats['current_enrollment']): ?>
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap"></i> Current Enrollment</h3>
                <a href="view_enrollment.php?id=<?php echo $stats['current_enrollment']['id']; ?>" class="view-link">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Grade Level</div>
                    <div class="info-value"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($stats['current_enrollment']['grade_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Strand</div>
                    <div class="info-value"><i class="fas fa-tag"></i> <?php echo $stats['current_enrollment']['strand'] ?: 'Not Applicable'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">School Year</div>
                    <div class="info-value"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($stats['current_enrollment']['school_year']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><i class="fas fa-check-circle" style="color: #10b981;"></i> <?php echo $stats['current_enrollment']['status']; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($account['role'] == 'Teacher' && isset($stats['sections']) && count($stats['sections']) > 0): ?>
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-layer-group"></i> Advisory Sections</h3>
            </div>
            <div class="sections-grid">
                <?php foreach($stats['sections'] as $section): ?>
                    <div class="section-card">
                        <h4><i class="fas fa-users"></i> <?php echo htmlspecialchars($section['section_name']); ?></h4>
                        <p><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></p>
                        <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-link">View Section <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
            </div>
            <ul class="timeline">
                <li class="timeline-item">
                    <div class="timeline-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Account Created</div>
                        <div class="timeline-time"><i class="far fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($account['created_at'])); ?></div>
                    </div>
                </li>
                <?php if($account['email_verified']): ?>
                <li class="timeline-item">
                    <div class="timeline-icon"><i class="fas fa-envelope"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Email Verified</div>
                        <div class="timeline-time"><i class="far fa-clock"></i> Email has been verified</div>
                    </div>
                </li>
                <?php endif; ?>
                <?php if($account['status'] == 'approved' && $account['approved_at']): ?>
                <li class="timeline-item">
                    <div class="timeline-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Account Approved</div>
                        <div class="timeline-time"><i class="far fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($account['approved_at'])); ?></div>
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </main>

    <script src="js/view_account.js"></script>
</body>
</html>