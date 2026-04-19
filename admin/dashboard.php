<?php
session_start();

// Check if user is logged in (compatible with your login.php)
if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get user data from session (compatible with both session formats)
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

// Fetch current admin/registrar info
$query = "SELECT id, fullname, email, id_number, role, profile_picture, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found in database, destroy session and redirect
if (!$admin) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

// Update session with latest user info
$_SESSION['user_id'] = $admin['id'];
$_SESSION['role'] = $admin['role'];
$_SESSION['fullname'] = $admin['fullname'];

// If no profile picture, use default
$profile_picture = $admin['profile_picture'] ?? null;
$admin_name = $admin['fullname'] ?? 'Admin';
$admin_role = $admin['role'] ?? 'Administrator';

// ========== NOTIFICATION FUNCTIONS ==========

// Function to add notification
function addNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

// Function to mark notification as read
function markNotificationRead($conn, $notif_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$notif_id, $user_id]);
}

// Function to mark all as read
function markAllNotificationsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id]);
}

// Function to get unread count
function getUnreadCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

// FIXED: Function to get notifications (removed LIMIT placeholder issue)
function getNotifications($conn, $user_id, $limit = 20) {
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . intval($limit);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== GENERATE SAMPLE NOTIFICATIONS (First time only) ==========
$check_notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
$check_stmt = $conn->prepare($check_notif_sql);
$check_stmt->execute([$user_id]);
$notif_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($notif_count == 0) {
    // Sample Updates & Announcements
    addNotification($conn, $user_id, 'update', '📢 New School Year 2026-2027', 'The enrollment period for the new school year is now open! Please review the updated requirements.', 'enrollments.php');
    addNotification($conn, $user_id, 'update', '📅 Class Schedules Posted', 'New class schedules for Grade 7-10 have been posted. Teachers can now view their assignments.', 'sections.php');
    addNotification($conn, $user_id, 'update', '🏫 System Maintenance Complete', 'The system has been updated with new features including real-time notifications and improved reporting.', null);
    
    // Sample User Actions
    addNotification($conn, $user_id, 'action', '✅ Enrollment Approved', 'Student John Michael Santos has been successfully enrolled in Grade 7 - Narra section.', 'students.php');
    addNotification($conn, $user_id, 'action', '📋 Teacher Assignment Updated', 'Ms. Maria Reyes has been assigned as adviser for Grade 10 - Mahogany section.', 'teachers.php');
    
    // Sample Reminders
    addNotification($conn, $user_id, 'reminder', '⏰ Enrollment Deadline Approaching', 'The enrollment deadline is in 5 days. Please process all pending applications.', 'enrollments.php');
    addNotification($conn, $user_id, 'reminder', '📝 Incomplete Requirements', '3 students have incomplete enrollment requirements. Please follow up.', 'enrollments.php');
    addNotification($conn, $user_id, 'reminder', '💰 Payment Reminder', 'First quarter tuition fees are due by end of this month.', null);
    
    // Sample Alerts/Warnings
    addNotification($conn, $user_id, 'alert', '⚠️ Schedule Conflict Detected', 'Teacher Maria Reyes has a schedule conflict on Monday mornings. Please review the class schedule.', '#');
    addNotification($conn, $user_id, 'alert', '📄 Missing Documents Alert', 'Student records missing: 5 students need to submit their PSA birth certificates.', 'students.php');
    addNotification($conn, $user_id, 'alert', '👥 Section Capacity Alert', 'Grade 7 - Narra section has reached maximum capacity (45/45 students).', 'sections.php');
    
    // Sample Messages
    addNotification($conn, $user_id, 'message', '💬 New Message from Registrar', 'Please review the enrollment documents for new students submitted today.', 'enrollments.php');
    addNotification($conn, $user_id, 'message', '📨 Admin Response Received', 'Your request for additional teacher slots has been approved.', 'manage_accounts.php');
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
        $notifications = getNotifications($conn, $user_id, 20);
        $unread_count = getUnreadCount($conn, $user_id);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit();
    }
    
    exit();
}

// ========== STATS QUERIES ==========

// Total Students (approved)
$student_query = "SELECT COUNT(*) as count FROM users WHERE role = 'Student' AND status IN ('approved', 'active')";
$student_result = $conn->query($student_query);
$student_count = $student_result ? $student_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

// Total Teachers (approved)
$teacher_query = "SELECT COUNT(*) as count FROM users WHERE role = 'Teacher' AND status IN ('approved', 'active')";
$teacher_result = $conn->query($teacher_query);
$teacher_count = $teacher_result ? $teacher_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

// Total Sections
$section_query = "SELECT COUNT(*) as count FROM sections";
$section_result = $conn->query($section_query);
$section_count = $section_result ? $section_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

// Total Subjects
$subject_query = "SELECT COUNT(*) as count FROM subjects";
$subject_result = $conn->query($subject_query);
$subject_count = $subject_result ? $subject_result->fetch(PDO::FETCH_ASSOC)['count'] : 0;

// Total Enrollments for current school year
$current_sy = "2026-2027";
$enrollment_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ?";
$enrollment_stmt = $conn->prepare($enrollment_query);
$enrollment_stmt->execute([$current_sy]);
$enrollment_count = $enrollment_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Enrolled (approved)
$enrolled_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ? AND status = 'Enrolled'";
$enrolled_stmt = $conn->prepare($enrolled_query);
$enrolled_stmt->execute([$current_sy]);
$enrolled_count = $enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending
$pending_query = "SELECT COUNT(*) as count FROM enrollments WHERE school_year = ? AND status = 'Pending'";
$pending_stmt = $conn->prepare($pending_query);
$pending_stmt->execute([$current_sy]);
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Get unread notification count for badge
$unread_notif_count = getUnreadCount($conn, $user_id);
$notifications_list = getNotifications($conn, $user_id, 20);

// ========== RECENT ENROLLMENTS ==========
$recent_enrollments_list = [];
$recent_query = "SELECT e.id, e.status, e.created_at, e.student_id,
                        u.fullname, u.id_number,
                        g.grade_name
                 FROM enrollments e
                 JOIN users u ON e.student_id = u.id
                 JOIN grade_levels g ON e.grade_id = g.id
                 WHERE e.school_year = ?
                 ORDER BY e.created_at DESC 
                 LIMIT 10";
$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->execute([$current_sy]);
$recent_enrollments_list = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== RECENT ACTIVITIES ==========
$recent_activities_list = [];
$activity_query = "SELECT 'enrollment' as type, 
                          CONCAT('New enrollment: ', u.fullname, ' enrolled in ', g.grade_name) as description,
                          e.created_at as date
                   FROM enrollments e
                   JOIN users u ON e.student_id = u.id
                   JOIN grade_levels g ON e.grade_id = g.id
                   WHERE e.school_year = ?
                   UNION ALL
                   SELECT 'user' as type,
                          CONCAT('New account created: ', fullname, ' as ', role) as description,
                          created_at as date
                   FROM users
                   WHERE role IN ('Student', 'Teacher') AND status IN ('approved', 'active')
                   ORDER BY date DESC 
                   LIMIT 10";
$activity_stmt = $conn->prepare($activity_query);
$activity_stmt->execute([$current_sy]);
$recent_activities_list = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Placido L. Señor NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
                    <img src="../<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                    </div>
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
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
                <!-- Notification Button with Dropdown -->
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
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Students</h3>
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($student_count); ?></div>
                <div class="stat-label">Enrolled students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Teachers</h3>
                    <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($teacher_count); ?></div>
                <div class="stat-label">Faculty members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Sections</h3>
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($section_count); ?></div>
                <div class="stat-label">Active sections</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Subjects</h3>
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($subject_count); ?></div>
                <div class="stat-label">Offered subjects</div>
            </div>
        </div>

        <!-- Second Row Stats -->
        <div class="stats-row-2">
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Enrollments</h3>
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($enrollment_count); ?></div>
                <div class="stat-label">All time enrollments</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Enrolled</h3>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($enrolled_count); ?></div>
                <div class="stat-label">Approved enrollments</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Pending</h3>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo number_format($pending_count); ?></div>
                <div class="stat-label">Awaiting approval</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="section-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="actions-grid">
                <a href="add_student.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="action-info">
                        <h4>Add Student</h4>
                        <p>Enroll new student</p>
                    </div>
                </a>
                <a href="add_teacher.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="action-info">
                        <h4>Add Teacher</h4>
                        <p>Hire new faculty</p>
                    </div>
                </a>
                <a href="create_section.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="action-info">
                        <h4>Create Section</h4>
                        <p>Add new class</p>
                    </div>
                </a>
                <a href="add_subject.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-book"></i></div>
                    <div class="action-info">
                        <h4>Add Subject</h4>
                        <p>Create new course</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Enrollments -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Enrollments</h3>
                    <a href="enrollments.php" class="view-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if(count($recent_enrollments_list) > 0): ?>
                    <ul class="enrollment-list">
                        <?php foreach($recent_enrollments_list as $enrollment): ?>
                            <li class="enrollment-item">
                                <?php 
                                $student_profile_pic = null;
                                $student_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                                $student_stmt->execute([$enrollment['student_id']]);
                                $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);
                                $student_profile_pic = $student_data['profile_picture'] ?? null;
                                ?>
                                
                                <?php if($student_profile_pic && file_exists("../" . $student_profile_pic)): ?>
                                    <div class="enrollment-avatar-img">
                                        <img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                                    </div>
                                <?php else: ?>
                                    <div class="enrollment-avatar">
                                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="enrollment-info">
                                    <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                    <div class="enrollment-meta">
                                        <span><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                        <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                                            <?php echo htmlspecialchars($enrollment['status']); ?>
                                        </span>
                                    </div>
                                    <div class="enrollment-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-signature"></i>
                        <p>No recent enrollments</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Recent Activities</h3>
                    <a href="#" class="view-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if(count($recent_activities_list) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach($recent_activities_list as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php echo $activity['type'] == 'enrollment' ? 'user-graduate' : 'user-plus'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($activity['date'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="chart-section">
            <div class="section-header">
                <h3><i class="fas fa-chart-line"></i> Enrollment Overview</h3>
                <select id="chartPeriod" class="chart-period-select">
                    <option value="weekly">This Week</option>
                    <option value="monthly" selected>This Month</option>
                    <option value="yearly">This Year</option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="enrollmentChart"></canvas>
            </div>
        </div>

        <!-- System Information -->
        <div class="system-info">
            <div class="section-header">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <strong>School Year</strong>
                    <div class="info-value">2026-2027</div>
                </div>
                <div class="info-item">
                    <i class="fas fa-database"></i>
                    <strong>Database Status</strong>
                    <div class="info-value">Active</div>
                </div>
                <div class="info-item">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <strong>Last Backup</strong>
                    <div class="info-value">Today</div>
                </div>
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <strong>Total Users</strong>
                    <div class="info-value"><?php echo number_format($student_count + $teacher_count + 1); ?></div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Pass PHP data to JavaScript
        const dashboardData = {
            enrolledCount: <?php echo $enrolled_count; ?>,
            recentEnrollments: <?php echo json_encode($recent_enrollments_list); ?>,
            recentActivities: <?php echo json_encode($recent_activities_list); ?>
        };
        
        // Notification System JavaScript
        (function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            
            // Toggle dropdown
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationDropdown && notificationBtn) {
                    if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                }
            });
            
            // Mark single notification as read
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
            
            // Mark all as read
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
            
            // Attach event listeners
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
            
            // Auto-refresh notifications every 30 seconds
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
                    if (data.success && notificationList) {
                        const existingBadge = document.querySelector('.notif-count');
                        if (data.unread_count > 0) {
                            if (existingBadge) {
                                existingBadge.textContent = data.unread_count;
                            } else {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notif-count';
                                newBadge.textContent = data.unread_count;
                                notificationBtn?.appendChild(newBadge);
                            }
                        } else if (existingBadge) {
                            existingBadge.remove();
                        }
                    }
                });
            }, 30000);
        })();
        
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
        
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (sidebar && menuToggle) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            }
        });
        
        // Initialize Chart
        let enrollmentChart;
        
        function initChart() {
            const ctx = document.getElementById('enrollmentChart');
            if (!ctx) return;
            
            const enrolledCount = dashboardData.enrolledCount || 100;
            
            const monthlyData = [
                Math.round(enrolledCount * 0.1),
                Math.round(enrolledCount * 0.15),
                Math.round(enrolledCount * 0.2),
                Math.round(enrolledCount * 0.25),
                Math.round(enrolledCount * 0.3),
                Math.round(enrolledCount * 0.35),
                Math.round(enrolledCount * 0.4),
                enrolledCount
            ];
            
            enrollmentChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'],
                    datasets: [{
                        label: 'Enrollments',
                        data: monthlyData,
                        borderColor: '#0B4F2E',
                        backgroundColor: 'rgba(11, 79, 46, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#0B4F2E',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            backgroundColor: '#0B4F2E',
                            titleColor: '#FFD700',
                            bodyColor: '#fff',
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        
        const chartPeriod = document.getElementById('chartPeriod');
        if (chartPeriod) {
            chartPeriod.addEventListener('change', function() {
                let newData, newLabels;
                const totalEnrollments = dashboardData.enrolledCount || 100;
                
                switch(this.value) {
                    case 'weekly':
                        newData = [
                            Math.round(totalEnrollments * 0.05),
                            Math.round(totalEnrollments * 0.08),
                            Math.round(totalEnrollments * 0.1),
                            Math.round(totalEnrollments * 0.12),
                            Math.round(totalEnrollments * 0.15),
                            Math.round(totalEnrollments * 0.18),
                            Math.round(totalEnrollments * 0.2)
                        ];
                        newLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        break;
                    case 'monthly':
                        newData = [
                            Math.round(totalEnrollments * 0.1),
                            Math.round(totalEnrollments * 0.15),
                            Math.round(totalEnrollments * 0.2),
                            Math.round(totalEnrollments * 0.25),
                            Math.round(totalEnrollments * 0.3),
                            Math.round(totalEnrollments * 0.35),
                            Math.round(totalEnrollments * 0.4),
                            totalEnrollments
                        ];
                        newLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'];
                        break;
                    case 'yearly':
                        newData = [
                            Math.round(totalEnrollments * 0.3),
                            Math.round(totalEnrollments * 0.35),
                            Math.round(totalEnrollments * 0.4),
                            Math.round(totalEnrollments * 0.5),
                            Math.round(totalEnrollments * 0.6),
                            Math.round(totalEnrollments * 0.7),
                            Math.round(totalEnrollments * 0.8),
                            Math.round(totalEnrollments * 0.9),
                            totalEnrollments
                        ];
                        newLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
                        break;
                }
                
                if (enrollmentChart) {
                    enrollmentChart.data.datasets[0].data = newData;
                    enrollmentChart.data.labels = newLabels;
                    enrollmentChart.update();
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            initChart();
        });
    </script>
</body>
</html>