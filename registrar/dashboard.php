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

// ========== CREATE NOTIFICATIONS TABLE IF NOT EXISTS ==========
$create_notif_table = "
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('update','action','reminder','alert','message') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$conn->exec($create_notif_table);

// ========== NOTIFICATION FUNCTIONS ==========

function addNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

function markNotificationRead($conn, $notif_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$notif_id, $user_id]);
}

function markAllNotificationsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id]);
}

function getUnreadCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

function getNotifications($conn, $user_id, $limit = 20) {
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . intval($limit);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== GENERATE SAMPLE NOTIFICATIONS (First time only) ==========
$check_notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
$check_stmt = $conn->prepare($check_notif_sql);
$check_stmt->execute([$registrar_id]);
$notif_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($notif_count == 0) {
    // Updates & Announcements
    addNotification($conn, $registrar_id, 'update', '📢 New Enrollment Period Open', 'The enrollment period for SY 2026-2027 is now open. Please review pending applications.', 'enrollments.php');
    addNotification($conn, $registrar_id, 'update', '📅 Deadline Extended', 'Enrollment deadline has been extended to June 15, 2026.', 'enrollments.php');
    
    // User Actions / Status
    addNotification($conn, $registrar_id, 'action', '✅ Enrollment Approved', 'Student John Santos has been successfully enrolled in Grade 7.', 'enrollments.php');
    addNotification($conn, $registrar_id, 'action', '📋 New Section Created', 'Section "Mahogany" for Grade 10 has been created.', 'sections.php');
    
    // Reminders
    addNotification($conn, $registrar_id, 'reminder', '⏰ Pending Applications', 'You have 5 pending enrollment applications waiting for review.', 'enrollments.php?status=Pending');
    addNotification($conn, $registrar_id, 'reminder', '📝 Incomplete Records', '3 students have incomplete requirements. Please follow up.', 'students.php');
    
    // Alerts / Warnings
    addNotification($conn, $registrar_id, 'alert', '⚠️ Section Capacity Alert', 'Grade 7 - Narra section is almost full (42/45 students).', 'sections.php');
    addNotification($conn, $registrar_id, 'alert', '📄 Missing Documents', 'Several enrollment applications are missing PSA birth certificates.', 'enrollments.php');
    
    // Messages
    addNotification($conn, $registrar_id, 'message', '💬 Message from Admin', 'Please prepare the enrollment summary report for this week.', 'reports.php');
    addNotification($conn, $registrar_id, 'message', '📨 System Update', 'The enrollment system has been updated with new features.', 'dashboard.php');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notif_id = $_POST['notif_id'] ?? 0;
        markNotificationRead($conn, $registrar_id, $notif_id);
        echo json_encode(['success' => true, 'unread_count' => getUnreadCount($conn, $registrar_id)]);
        exit();
    }
    
    if ($action === 'mark_all_read') {
        markAllNotificationsRead($conn, $registrar_id);
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit();
    }
    
    if ($action === 'get_notifications') {
        $notifications = getNotifications($conn, $registrar_id, 20);
        $unread_count = getUnreadCount($conn, $registrar_id);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit();
    }
    
    exit();
}

// Get notifications for display
$unread_notif_count = getUnreadCount($conn, $registrar_id);
$notifications_list = getNotifications($conn, $registrar_id, 20);

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

// Get enrollment statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'");
$enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'");
$rejected_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get student statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'");
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent enrollments
$stmt = $conn->prepare("
    SELECT e.*, u.fullname, u.email, u.id_number, g.grade_name 
    FROM enrollments e 
    LEFT JOIN users u ON e.student_id = u.id 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    ORDER BY e.id DESC 
    LIMIT 5
");
$stmt->execute();
$recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get enrollment trends by month (last 6 months)
$trends_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM enrollments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
";
$stmt = $conn->query($trends_query);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade level distribution
$stmt = $conn->query("
    SELECT g.grade_name, COUNT(e.id) as count
    FROM grade_levels g
    LEFT JOIN enrollments e ON g.id = e.grade_id AND e.status = 'Enrolled'
    GROUP BY g.id
    ORDER BY g.id
");
$grade_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$activities_query = "
    (SELECT 'enrollment' as type, e.created_at, CONCAT('New enrollment from ', u.fullname) as description
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     ORDER BY e.created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'status_change' as type, e.created_at, CONCAT('Enrollment ', e.status, ' for ', u.fullname) as description
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     WHERE e.status IN ('Enrolled', 'Rejected')
     ORDER BY e.created_at DESC LIMIT 3)
    ORDER BY created_at DESC LIMIT 5
";
$stmt = $conn->query($activities_query);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate approval rate
$approval_rate = $total_enrollments > 0 ? round(($enrolled_count / $total_enrollments) * 100, 2) : 0;

// Prepare chart data
$months = [];
$pending_data = [];
$enrolled_data = [];
$rejected_data = [];

if(count($trends) > 0) {
    foreach($trends as $row) {
        $months[] = date('M Y', strtotime($row['month'] . '-01'));
        $pending_data[] = (int)$row['pending'];
        $enrolled_data[] = (int)$row['enrolled'];
        $rejected_data[] = (int)$row['rejected'];
    }
} else {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $pending_data = [0,0,0,0,0,0];
    $enrolled_data = [0,0,0,0,0,0];
    $rejected_data = [0,0,0,0,0,0];
}

$grade_labels = [];
$grade_counts = [];
if(count($grade_distribution) > 0) {
    foreach($grade_distribution as $row) {
        $grade_labels[] = $row['grade_name'];
        $grade_counts[] = (int)$row['count'];
    }
} else {
    $grade_labels = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    $grade_counts = [0,0,0,0,0,0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Dashboard - Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Registrar Dashboard CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
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
                        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header with Notification Bell -->
            <div class="page-header">
                <div>
                    <h1>Registrar Dashboard</h1>
                    <p>Manage enrollments and student records</p>
                </div>
                <div class="header-actions">
                    <div class="notification-wrapper">
                        <a href="#" class="notification-btn" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <?php if($unread_notif_count > 0): ?>
                                <span class="notif-count"><?php echo $unread_notif_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="dropdown-header">
                                <h4>Notifications</h4>
                                <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                            </div>
                            <div class="dropdown-body" id="notificationList">
                                <?php if(count($notifications_list) > 0): ?>
                                    <?php foreach($notifications_list as $notif): ?>
                                        <div class="notif-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo $notif['id']; ?>">
                                            <div class="notif-icon notif-<?php echo $notif['type']; ?>">
                                                <?php
                                                    $icons = [
                                                        'update' => 'fa-megaphone',
                                                        'action' => 'fa-check-circle',
                                                        'reminder' => 'fa-clock',
                                                        'alert' => 'fa-exclamation-triangle',
                                                        'message' => 'fa-envelope'
                                                    ];
                                                    $icon = $icons[$notif['type']] ?? 'fa-bell';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="notif-content">
                                                <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <div class="notif-time">
                                                    <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                                </div>
                                            </div>
                                            <?php if(!$notif['is_read']): ?>
                                                <button class="mark-read-btn" data-id="<?php echo $notif['id']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-notifications">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No notifications yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="date-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                    </div>
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='enrollments.php'">
                    <div class="stat-header">
                        <h3>Total Enrollments</h3>
                        <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Pending'">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting review</div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Enrolled'">
                    <div class="stat-header">
                        <h3>Enrolled</h3>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved (<?php echo $approval_rate; ?>%)</div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Rejected'">
                    <div class="stat-header">
                        <h3>Rejected</h3>
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Not approved</div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="dashboard-grid">
                <!-- Enrollment Trends Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Enrollment Trends</h3>
                        <span class="badge">Last 6 months</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>

                <!-- Grade Distribution -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-pie-chart"></i> Enrollment by Grade Level</h3>
                        <span class="badge success">Current enrolled</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Enrollments -->
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Recent Enrollments</h3>
                    <?php if(count($recent_enrollments) > 0): ?>
                        <div class="enrollment-list">
                            <?php foreach($recent_enrollments as $enrollment): ?>
                                <div class="enrollment-item">
                                    <div class="enrollment-avatar">
                                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                                    </div>
                                    <div class="enrollment-info">
                                        <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                        <p>
                                            <span><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                            <?php if($enrollment['strand']): ?>
                                                <span>• <?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                            <?php endif; ?>
                                            <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                                                <?php echo $enrollment['status']; ?>
                                            </span>
                                        </p>
                                        <div class="activity-time">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="enrollments.php" class="view-link">
                            View All Enrollments <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-file-signature"></i>
                            <p>No recent enrollments</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="info-card">
                    <h3><i class="fas fa-bell"></i> Recent Activities</h3>
                    <?php if(count($recent_activities) > 0): ?>
                        <div class="activity-list">
                            <?php foreach($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo $activity['type'] == 'enrollment' ? 'user-plus' : 'sync-alt'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-bell-slash"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="section-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="actions-grid">
                    <a href="enrollments.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-file-signature"></i></div>
                        <div class="action-info">
                            <h4>Manage Enrollments</h4>
                            <p>View and process applications</p>
                        </div>
                    </a>
                    
                    <a href="students.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="action-info">
                            <h4>Student Records</h4>
                            <p>Manage student information</p>
                        </div>
                    </a>
                    
                    <a href="sections.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="action-info">
                            <h4>Manage Sections</h4>
                            <p>Create and assign sections</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="action-info">
                            <h4>Generate Reports</h4>
                            <p>Create enrollment reports</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification System JavaScript -->
    <script>
        // Notification System
        (function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (notificationDropdown && notificationBtn) {
                    if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                }
            });
            
            function markAsRead(notifId, element) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=mark_read&notif_id=' + notifId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notifItem = element.closest('.notif-item');
                        notifItem.classList.remove('unread');
                        notifItem.classList.add('read');
                        const markBtn = notifItem.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                        const badge = document.querySelector('.notif-count');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.textContent = data.unread_count;
                            } else {
                                badge.remove();
                            }
                        }
                    }
                });
            }
            
            function markAllAsRead() {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notif-item').forEach(item => {
                            item.classList.remove('unread');
                            item.classList.add('read');
                            const markBtn = item.querySelector('.mark-read-btn');
                            if (markBtn) markBtn.remove();
                        });
                        const badge = document.querySelector('.notif-count');
                        if (badge) badge.remove();
                    }
                });
            }
            
            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const notifId = this.dataset.id;
                    markAsRead(notifId, this);
                });
            });
            
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function() {
                    markAllAsRead();
                });
            }
            
            setInterval(function() {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=get_notifications'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && notificationBtn) {
                        const existingBadge = document.querySelector('.notif-count');
                        if (data.unread_count > 0) {
                            if (existingBadge) {
                                existingBadge.textContent = data.unread_count;
                            } else {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notif-count';
                                newBadge.textContent = data.unread_count;
                                notificationBtn.appendChild(newBadge);
                            }
                        } else if (existingBadge) {
                            existingBadge.remove();
                        }
                    }
                });
            }, 30000);
        })();
    </script>
    
    <!-- Registrar Dashboard JS -->
    <script src="js/dashboard.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const chartData = {
            months: <?php echo json_encode(array_reverse($months)); ?>,
            pending: <?php echo json_encode(array_reverse($pending_data)); ?>,
            enrolled: <?php echo json_encode(array_reverse($enrolled_data)); ?>,
            rejected: <?php echo json_encode(array_reverse($rejected_data)); ?>,
            gradeLabels: <?php echo json_encode($grade_labels); ?>,
            gradeCounts: <?php echo json_encode($grade_counts); ?>
        };
        
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