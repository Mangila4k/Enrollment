<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get user data from session
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $user_id = $user['id'];
    $user_role = $user['role'];
    $user_fullname = $user['fullname'];
} else {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'Admin';
    $user_fullname = $_SESSION['fullname'] ?? 'Admin';
}

// Include database connection
require_once '../config/database.php';

// Check if connection exists
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// ========== CREATE NOTIFICATIONS TABLE IF NOT EXISTS ==========
$create_notif_table = "
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('update','action','reminder','alert','message','grade','requirement','profile','enrollment','account') NOT NULL,
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

// Fetch current admin info
$query = "SELECT id, fullname, email, id_number, role, profile_picture, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

$_SESSION['user_id'] = $admin['id'];
$_SESSION['role'] = $admin['role'];
$_SESSION['fullname'] = $admin['fullname'];

$profile_picture = $admin['profile_picture'] ?? null;
$admin_name = $admin['fullname'] ?? 'Admin';
$admin_role = $admin['role'] ?? 'Administrator';

// ========== NOTIFICATION FUNCTIONS ==========

function addNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at, is_read) VALUES (?, ?, ?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

function addAdminNotification($conn, $title, $message, $link = null, $type = 'alert') {
    $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('Admin', 'Registrar')");
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($admins as $admin) {
        addNotification($conn, $admin['id'], $type, $title, $message, $link);
    }
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

function getNotifications($conn, $user_id, $limit = 50) {
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . intval($limit);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== HANDLE AJAX REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notif_id = $_POST['notif_id'] ?? 0;
        markNotificationRead($conn, $user_id, $notif_id);
        echo json_encode(['success' => true, 'unread_count' => getUnreadCount($conn, $user_id)]);
        exit();
    }
    
    if ($action === 'mark_all_read') {
        markAllNotificationsRead($conn, $user_id);
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit();
    }
    
    if ($action === 'get_notifications') {
        $notifications = getNotifications($conn, $user_id, 50);
        $unread_count = getUnreadCount($conn, $user_id);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// ========== STATS QUERIES ==========
$student_query = "SELECT COUNT(*) as count FROM users WHERE role = 'Student' AND status IN ('approved', 'active')";
$student_result = $conn->query($student_query);
$student_count = $student_result ? $student_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

$teacher_query = "SELECT COUNT(*) as count FROM users WHERE role = 'Teacher' AND status IN ('approved', 'active')";
$teacher_result = $conn->query($teacher_query);
$teacher_count = $teacher_result ? $teacher_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

$section_query = "SELECT COUNT(*) as count FROM sections";
$section_result = $conn->query($section_query);
$section_count = $section_result ? $section_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

$subject_query = "SELECT COUNT(*) as count FROM subjects";
$subject_result = $conn->query($subject_query);
$subject_count = $subject_result ? $subject_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

$current_sy = "2026-2027";
$enrollment_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ?";
$enrollment_stmt = $conn->prepare($enrollment_query);
$enrollment_stmt->execute([$current_sy]);
$enrollment_count = $enrollment_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$enrolled_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ? AND status = 'Enrolled'";
$enrolled_stmt = $conn->prepare($enrolled_query);
$enrolled_stmt->execute([$current_sy]);
$enrolled_count = $enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ? AND status = 'Pending'";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->execute([$current_sy]);
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Get all notifications including student enrollments and profile changes
$unread_notif_count = getUnreadCount($conn, $user_id);
$notifications_list = getNotifications($conn, $user_id, 50);

// Get recent enrollments with student profile pictures
$recent_query = "SELECT e.id, e.status, e.created_at, e.student_id, e.school_year,
                        u.fullname, u.id_number, u.profile_picture as student_profile_pic, g.grade_name
                 FROM enrollments e
                 JOIN users u ON e.student_id = u.id
                 JOIN grade_levels g ON e.grade_id = g.id
                 WHERE e.school_year = ?
                 ORDER BY e.created_at DESC LIMIT 10";
$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->execute([$current_sy]);
$recent_enrollments_list = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH ALL USER PROFILE UPDATES ==========
$profile_updates_query = "
    SELECT 
        n.*,
        u.fullname,
        u.role,
        u.profile_picture,
        u.email
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type IN ('profile', 'update', 'alert')
    AND (n.title LIKE '%Profile%' OR n.title LIKE '%Email%' OR n.title LIKE '%Password%' OR n.title LIKE '%Picture%')
    ORDER BY n.created_at DESC
    LIMIT 20
";
$profile_updates_stmt = $conn->prepare($profile_updates_query);
$profile_updates_stmt->execute();
$profile_updates_list = $profile_updates_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH ADMIN AND REGISTRAR ACTIONS ==========
$admin_actions_query = "
    SELECT 
        n.*,
        u.fullname,
        u.role,
        u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type IN ('action', 'update')
    AND (n.title LIKE '%Approved%' OR n.title LIKE '%Rejected%' OR n.title LIKE '%Created%' OR n.title LIKE '%Deleted%' OR n.title LIKE '%Updated%')
    AND u.role IN ('Admin', 'Registrar')
    ORDER BY n.created_at DESC
    LIMIT 20
";
$admin_actions_stmt = $conn->prepare($admin_actions_query);
$admin_actions_stmt->execute();
$admin_actions_list = $admin_actions_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH ACCOUNT APPROVALS ==========
$account_approvals_query = "
    SELECT 
        n.*,
        u.fullname,
        u.role,
        u.profile_picture,
        (SELECT fullname FROM users WHERE id = n.user_id) as action_by
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'account' OR (n.title LIKE '%Account%' AND (n.title LIKE '%Approved%' OR n.title LIKE '%Rejected%'))
    ORDER BY n.created_at DESC
    LIMIT 15
";
$account_approvals_stmt = $conn->prepare($account_approvals_query);
$account_approvals_stmt->execute();
$account_approvals_list = $account_approvals_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH ENROLLMENT APPROVALS ==========
$enrollment_approvals_query = "
    SELECT 
        n.*,
        u.fullname,
        u.role,
        u.profile_picture,
        (SELECT fullname FROM users WHERE id = n.user_id) as action_by
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'enrollment' OR (n.title LIKE '%Enrollment%' AND (n.title LIKE '%Approved%' OR n.title LIKE '%Rejected%'))
    ORDER BY n.created_at DESC
    LIMIT 15
";
$enrollment_approvals_stmt = $conn->prepare($enrollment_approvals_query);
$enrollment_approvals_stmt->execute();
$enrollment_approvals_list = $enrollment_approvals_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== FETCH MESSAGES ==========
$messages_query = "
    SELECT 
        n.*,
        u.fullname,
        u.role,
        u.profile_picture,
        u.email
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'message'
    ORDER BY n.created_at DESC
    LIMIT 15
";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->execute();
$messages_list = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users with their latest activity
$all_users_activity_query = "
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.role,
        u.profile_picture,
        u.created_at as registered_date,
        u.status,
        (SELECT COUNT(*) FROM notifications WHERE user_id = u.id) as notification_count,
        (SELECT MAX(created_at) FROM notifications WHERE user_id = u.id) as last_activity
    FROM users u
    WHERE u.role IN ('Student', 'Teacher', 'Admin', 'Registrar')
    ORDER BY u.created_at DESC
    LIMIT 30
";
$all_users_activity = $conn->query($all_users_activity_query)->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$activity_query = "
    SELECT 'enrollment' as type, 
           CONCAT('New enrollment: ', u.fullname, ' enrolled in ', g.grade_name) as description,
           e.created_at as date,
           e.student_id,
           u.profile_picture
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.school_year = ?
    
    UNION ALL
    
    SELECT 'requirement' as type,
           CONCAT('Student ', u.fullname, ' submitted a requirement') as description,
           n.created_at as date,
           n.user_id as student_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'requirement' AND u.role = 'Student'
    
    UNION ALL
    
    SELECT 'profile' as type,
           CONCAT('Student ', u.fullname, ' updated their profile') as description,
           n.created_at as date,
           n.user_id as student_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'profile' AND u.role = 'Student'
    
    UNION ALL
    
    SELECT 'admin_action' as type,
           CONCAT(n.title, ': ', n.message) as description,
           n.created_at as date,
           n.user_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type IN ('action', 'update') AND u.role IN ('Admin', 'Registrar')
    
    UNION ALL
    
    SELECT 'account_approval' as type,
           CONCAT('Account ', n.title, ': ', n.message) as description,
           n.created_at as date,
           n.user_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'account' OR (n.title LIKE '%Account%' AND (n.title LIKE '%Approved%' OR n.title LIKE '%Rejected%'))
    
    UNION ALL
    
    SELECT 'enrollment_approval' as type,
           CONCAT('Enrollment ', n.title, ': ', n.message) as description,
           n.created_at as date,
           n.user_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'enrollment' OR (n.title LIKE '%Enrollment%' AND (n.title LIKE '%Approved%' OR n.title LIKE '%Rejected%'))
    
    UNION ALL
    
    SELECT 'message' as type,
           CONCAT('Message from ', u.fullname, ': ', SUBSTRING(n.message, 1, 100)) as description,
           n.created_at as date,
           n.user_id,
           u.profile_picture
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'message'
    
    ORDER BY date DESC LIMIT 40";
$activity_stmt = $conn->prepare($activity_query);
$activity_stmt->execute([$current_sy]);
$recent_activities_list = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== STATISTICS ==========
$total_profile_updates = count($profile_updates_list);
$total_admin_actions = count($admin_actions_list);
$total_account_approvals = count($account_approvals_list);
$total_enrollment_approvals = count($enrollment_approvals_list);
$total_messages = count($messages_list);

$student_updates = 0;
$teacher_updates = 0;
$admin_updates = 0;
$registrar_updates = 0;

foreach($profile_updates_list as $update) {
    switch($update['role']) {
        case 'Student': $student_updates++; break;
        case 'Teacher': $teacher_updates++; break;
        case 'Admin': $admin_updates++; break;
        case 'Registrar': $registrar_updates++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Placido L. Señor NHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-admin { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .role-registrar { background: rgba(253, 126, 20, 0.15); color: #fd7e14; }
        .role-teacher { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .role-student { background: rgba(0, 123, 255, 0.15); color: #007bff; }
        .update-badge, .action-badge, .approval-badge, .message-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .update-badge { background: rgba(11, 79, 46, 0.1); color: #0B4F2E; }
        .action-badge { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .approval-badge { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .message-badge { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar-img { width: 35px; height: 35px; border-radius: 50%; overflow: hidden; }
        .user-avatar-img img { width: 100%; height: 100%; object-fit: cover; }
        .user-avatar { width: 35px; height: 35px; background: linear-gradient(135deg, #0B4F2E, #1a7a42); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; }
        .user-details strong { display: block; font-size: 13px; }
        .user-details small { font-size: 10px; color: #666; }
        .notif-count-badge { background: rgba(11, 79, 46, 0.1); color: #0B4F2E; padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .data-table th { text-align: left; padding: 12px; background: #f8f9fa; font-size: 12px; font-weight: 600; color: #666; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; font-size: 13px; }
        .card { margin-bottom: 25px; }
        .stats-row-2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        @media (max-width: 992px) { .stats-row-2 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .stats-row-2 { grid-template-columns: 1fr; } }
    </style>
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
                    <img src="../<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
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
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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
        <div class="top-header">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Hello, <?php echo htmlspecialchars($admin_name); ?>! Here's what's happening with your enrollment system today.</p>
            </div>
            <div class="header-actions">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="notification-wrapper notification-badge">
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
                                                    'message' => 'fa-envelope',
                                                    'grade' => 'fa-star',
                                                    'requirement' => 'fa-file-upload',
                                                    'profile' => 'fa-user-edit',
                                                    'enrollment' => 'fa-graduation-cap',
                                                    'account' => 'fa-user-plus'
                                                ];
                                                $icon = $icons[$notif['type']] ?? 'fa-bell';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notif-content">
                                            <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                            <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div class="notif-time"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></div>
                                        </div>
                                        <?php if(!$notif['is_read']): ?>
                                            <button class="mark-read-btn" data-id="<?php echo $notif['id']; ?>"><i class="fas fa-check"></i></button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-notifications"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-header"><h3>Total Students</h3><div class="stat-icon"><i class="fas fa-user-graduate"></i></div></div><div class="stat-number"><?php echo number_format($student_count); ?></div><div class="stat-label">Enrolled students</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Total Teachers</h3><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div></div><div class="stat-number"><?php echo number_format($teacher_count); ?></div><div class="stat-label">Faculty members</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Total Sections</h3><div class="stat-icon"><i class="fas fa-layer-group"></i></div></div><div class="stat-number"><?php echo number_format($section_count); ?></div><div class="stat-label">Active sections</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Total Subjects</h3><div class="stat-icon"><i class="fas fa-book"></i></div></div><div class="stat-number"><?php echo number_format($subject_count); ?></div><div class="stat-label">Offered subjects</div></div>
        </div>

        <div class="stats-row-2">
            <div class="stat-card"><div class="stat-header"><h3>Total Enrollments</h3><div class="stat-icon"><i class="fas fa-file-signature"></i></div></div><div class="stat-number"><?php echo number_format($enrollment_count); ?></div><div class="stat-label">All time enrollments</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Enrolled</h3><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div><div class="stat-number"><?php echo number_format($enrolled_count); ?></div><div class="stat-label">Approved enrollments</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Pending</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div><div class="stat-number"><?php echo number_format($pending_count); ?></div><div class="stat-label">Awaiting approval</div></div>
        </div>

        <!-- Activity Stats -->
        <div class="stats-row-2">
            <div class="stat-card"><div class="stat-header"><h3>Profile Updates</h3><div class="stat-icon"><i class="fas fa-user-edit"></i></div></div><div class="stat-number"><?php echo $total_profile_updates; ?></div><div class="stat-label">Total changes</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Admin Actions</h3><div class="stat-icon"><i class="fas fa-shield-alt"></i></div></div><div class="stat-number"><?php echo $total_admin_actions; ?></div><div class="stat-label">Admin/Registrar actions</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Account Approvals</h3><div class="stat-icon"><i class="fas fa-user-check"></i></div></div><div class="stat-number"><?php echo $total_account_approvals; ?></div><div class="stat-label">Accounts approved/rejected</div></div>
            <div class="stat-card"><div class="stat-header"><h3>Enrollment Actions</h3><div class="stat-icon"><i class="fas fa-file-signature"></i></div></div><div class="stat-number"><?php echo $total_enrollment_approvals; ?></div><div class="stat-label">Enrollments processed</div></div>
        </div>

        <div class="quick-actions">
            <div class="section-header"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
            <div class="actions-grid">
                <a href="add_student.php" class="action-card"><div class="action-icon"><i class="fas fa-user-plus"></i></div><div class="action-info"><h4>Add Student</h4><p>Enroll new student</p></div></a>
                <a href="add_teacher.php" class="action-card"><div class="action-icon"><i class="fas fa-chalkboard-user"></i></div><div class="action-info"><h4>Add Teacher</h4><p>Hire new faculty</p></div></a>
                <a href="create_section.php" class="action-card"><div class="action-icon"><i class="fas fa-layer-group"></i></div><div class="action-info"><h4>Create Section</h4><p>Add new class</p></div></a>
                <a href="add_subject.php" class="action-card"><div class="action-icon"><i class="fas fa-book"></i></div><div class="action-info"><h4>Add Subject</h4><p>Create new course</p></div></a>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-history"></i> Recent Activities</h3><a href="#" class="view-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <?php if(count($recent_activities_list) > 0): ?>
                <ul class="activity-list">
                    <?php foreach($recent_activities_list as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <?php if($activity['type'] == 'enrollment'): ?>
                                    <i class="fas fa-user-graduate"></i>
                                <?php elseif($activity['type'] == 'requirement'): ?>
                                    <i class="fas fa-file-upload"></i>
                                <?php elseif($activity['type'] == 'profile'): ?>
                                    <i class="fas fa-user-edit"></i>
                                <?php elseif($activity['type'] == 'admin_action'): ?>
                                    <i class="fas fa-shield-alt"></i>
                                <?php elseif($activity['type'] == 'account_approval'): ?>
                                    <i class="fas fa-user-check"></i>
                                <?php elseif($activity['type'] == 'enrollment_approval'): ?>
                                    <i class="fas fa-file-signature"></i>
                                <?php elseif($activity['type'] == 'message'): ?>
                                    <i class="fas fa-envelope"></i>
                                <?php else: ?>
                                    <i class="fas fa-bell"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                <div class="activity-time"><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($activity['date'])); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-bell-slash"></i><p>No recent activities</p></div>
            <?php endif; ?>
        </div>

        <!-- Recent Enrollments -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-file-signature"></i> Recent Enrollments</h3><a href="enrollments.php" class="view-link">View All <i class="fas fa-arrow-right"></i></a></div>
            <?php if(count($recent_enrollments_list) > 0): ?>
                <ul class="enrollment-list">
                    <?php foreach($recent_enrollments_list as $enrollment): ?>
                        <li class="enrollment-item">
                            <div class="enrollment-avatar"><?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?></div>
                            <div class="enrollment-info">
                                <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                <div class="enrollment-meta"><span><?php echo htmlspecialchars($enrollment['grade_name']); ?></span><span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>"><?php echo htmlspecialchars($enrollment['status']); ?></span></div>
                                <div class="enrollment-date"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-file-signature"></i><p>No recent enrollments</p></div>
            <?php endif; ?>
        </div>

        <!-- Admin & Registrar Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt"></i> Admin & Registrar Actions</h3>
                <span class="badge-count"><?php echo $total_admin_actions; ?> actions</span>
            </div>
            <div class="table-container">
                <?php if(count($admin_actions_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Action</th><th>Message</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($admin_actions_list as $action): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($action['profile_picture']) && file_exists("../" . $action['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $action['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($action['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($action['fullname']); ?></strong><small><?php echo $action['role']; ?></small></div>
                                        </div>
                                    </div>
                                    <td><span class="action-badge"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($action['title']); ?></span></div>
                                    <td><?php echo htmlspecialchars(substr($action['message'], 0, 80)); ?>...</div>
                                    <td><?php echo date('M d, Y h:i A', strtotime($action['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-shield-alt"></i><p>No admin actions recorded</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Approvals -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-check"></i> Account Approvals & Rejections</h3>
                <span class="badge-count"><?php echo $total_account_approvals; ?> approvals</span>
            </div>
            <div class="table-container">
                <?php if(count($account_approvals_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Action</th><th>Message</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($account_approvals_list as $approval): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($approval['profile_picture']) && file_exists("../" . $approval['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $approval['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($approval['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($approval['fullname']); ?></strong><small><?php echo $approval['role']; ?></small></div>
                                        </div>
                                    </div>
                                    <td>
                                        <span class="approval-badge">
                                            <?php if(strpos($approval['title'], 'Approved') !== false): ?>
                                                <i class="fas fa-check-circle"></i> Approved
                                            <?php else: ?>
                                                <i class="fas fa-times-circle"></i> Rejected
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <td><?php echo htmlspecialchars(substr($approval['message'], 0, 80)); ?>...</div>
                                    <td><?php echo date('M d, Y h:i A', strtotime($approval['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-user-check"></i><p>No account approvals recorded</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enrollment Approvals -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-signature"></i> Enrollment Approvals & Rejections</h3>
                <span class="badge-count"><?php echo $total_enrollment_approvals; ?> actions</span>
            </div>
            <div class="table-container">
                <?php if(count($enrollment_approvals_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Action</th><th>Message</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($enrollment_approvals_list as $approval): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($approval['profile_picture']) && file_exists("../" . $approval['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $approval['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($approval['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($approval['fullname']); ?></strong><small><?php echo $approval['role']; ?></small></div>
                                        </div>
                                    </div>
                                    <td>
                                        <span class="approval-badge">
                                            <?php if(strpos($approval['title'], 'Approved') !== false): ?>
                                                <i class="fas fa-check-circle"></i> Approved
                                            <?php else: ?>
                                                <i class="fas fa-times-circle"></i> Rejected
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <td><?php echo htmlspecialchars(substr($approval['message'], 0, 80)); ?>...</div>
                                    <td><?php echo date('M d, Y h:i A', strtotime($approval['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-file-signature"></i><p>No enrollment actions recorded</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Updates -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Profile Updates</h3>
                <span class="badge-count"><?php echo $total_profile_updates; ?> updates</span>
            </div>
            <div class="table-container">
                <?php if(count($profile_updates_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Role</th><th>Update Type</th><th>Message</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($profile_updates_list as $update): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($update['profile_picture']) && file_exists("../" . $update['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $update['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($update['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($update['fullname']); ?></strong><small><?php echo htmlspecialchars($update['email']); ?></small></div>
                                        </div>
                                    </div>
                                    <td><span class="role-badge role-<?php echo strtolower($update['role']); ?>"><?php echo $update['role']; ?></span></div>
                                    <td>
                                        <span class="update-badge">
                                            <?php 
                                            if(strpos($update['title'], 'Email') !== false) echo '📧 Email Change';
                                            elseif(strpos($update['title'], 'Password') !== false) echo '🔐 Password Change';
                                            elseif(strpos($update['title'], 'Picture') !== false) echo '🖼️ Profile Picture';
                                            else echo '📝 Profile Update';
                                            ?>
                                        </span>
                                    </div>
                                    <td><?php echo htmlspecialchars(substr($update['message'], 0, 80)); ?>...</div>
                                    <td><?php echo date('M d, Y h:i A', strtotime($update['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-user-edit"></i><p>No profile updates yet</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-envelope"></i> Messages</h3>
                <span class="badge-count"><?php echo $total_messages; ?> messages</span>
            </div>
            <div class="table-container">
                <?php if(count($messages_list) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>From</th><th>Role</th><th>Message</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($messages_list as $message): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($message['profile_picture']) && file_exists("../" . $message['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $message['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($message['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($message['fullname']); ?></strong><small><?php echo htmlspecialchars($message['email']); ?></small></div>
                                        </div>
                                    </div>
                                    <td><span class="role-badge role-<?php echo strtolower($message['role']); ?>"><?php echo $message['role']; ?></span></div>
                                    <td><?php echo htmlspecialchars(substr($message['message'], 0, 100)); ?>...</div>
                                    <td><?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?></div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-envelope"></i><p>No messages yet</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Users Activity -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> All Users Activity</h3>
                <span class="badge-count"><?php echo count($all_users_activity); ?> users</span>
            </div>
            <div class="table-container">
                <?php if(count($all_users_activity) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Role</th><th>Status</th><th>Registered</th><th>Notifications</th><th>Last Activity</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($all_users_activity as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <?php if(!empty($user['profile_picture']) && file_exists("../" . $user['profile_picture'])): ?>
                                                <div class="user-avatar-img"><img src="../<?php echo $user['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile"></div>
                                            <?php else: ?>
                                                <div class="user-avatar"><?php echo strtoupper(substr($user['fullname'], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="user-details"><strong><?php echo htmlspecialchars($user['fullname']); ?></strong><small><?php echo htmlspecialchars($user['email']); ?></small></div>
                                        </div>
                                    </div>
                                    <td><span class="role-badge role-<?php echo strtolower($user['role']); ?>"><?php echo $user['role']; ?></span></div>
                                    <td><span class="status-badge status-<?php echo strtolower($user['status'] ?? 'active'); ?>"><?php echo ucfirst($user['status'] ?? 'Active'); ?></span></div>
                                    <td><?php echo date('M d, Y', strtotime($user['registered_date'])); ?></div>
                                    <td><span class="notif-count-badge"><?php echo $user['notification_count']; ?> notifications</span></div>
                                    <td>
                                        <?php if($user['last_activity']): ?>
                                            <?php echo date('M d, Y', strtotime($user['last_activity'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No activity</span>
                                        <?php endif; ?>
                                    </div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-users"></i><p>No users found</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-section">
            <div class="section-header"><h3><i class="fas fa-chart-line"></i> Enrollment Overview</h3><select id="chartPeriod" class="chart-period-select"><option value="weekly">This Week</option><option value="monthly" selected>This Month</option><option value="yearly">This Year</option></select></div>
            <div class="chart-container"><canvas id="enrollmentChart"></canvas></div>
        </div>

        <div class="system-info">
            <div class="section-header"><h3><i class="fas fa-info-circle"></i> System Information</h3></div>
            <div class="info-grid">
                <div class="info-item"><i class="fas fa-calendar-alt"></i><strong>School Year</strong><div class="info-value">2026-2027</div></div>
                <div class="info-item"><i class="fas fa-database"></i><strong>Database Status</strong><div class="info-value">Active</div></div>
                <div class="info-item"><i class="fas fa-cloud-upload-alt"></i><strong>Last Backup</strong><div class="info-value">Today</div></div>
                <div class="info-item"><i class="fas fa-users"></i><strong>Total Users</strong><div class="info-value"><?php echo number_format($student_count + $teacher_count + 1); ?></div></div>
            </div>
        </div>
    </main>

    <script>
        const dashboardData = {
            enrolledCount: <?php echo $enrolled_count; ?>,
            recentEnrollments: <?php echo json_encode($recent_enrollments_list); ?>,
            recentActivities: <?php echo json_encode($recent_activities_list); ?>
        };
    </script>
    <script src="js/dashboard.js"></script>
</body>
</html>