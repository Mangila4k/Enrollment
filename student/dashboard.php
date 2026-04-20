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

// Fetch fresh student data from database including profile picture
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if($student) {
    $student_name = $student['fullname'];
    $profile_picture = $student['profile_picture'] ?? null;
    $first_name = explode(' ', $student_name)[0];
} else {
    $first_name = 'Student';
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

// ========== NOTIFICATION FUNCTIONS ==========

function addNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
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

// ========== GENERATE SAMPLE NOTIFICATIONS FOR STUDENT ==========
$check_notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
$check_stmt = $conn->prepare($check_notif_sql);
$check_stmt->execute([$student_id]);
$notif_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($notif_count == 0) {
    // Updates & Announcements
    addNotification($conn, $student_id, 'update', '📢 New School Year 2026-2027', 'The enrollment period is now open! Please complete your enrollment requirements.', 'enrollment.php');
    addNotification($conn, $student_id, 'update', '📅 Class Schedules Available', 'Your class schedules for this semester are now available to view.', 'schedule.php');
    
    // User Actions / Status
    addNotification($conn, $student_id, 'action', '✅ Enrollment Successful', 'Your enrollment for Grade 7 has been approved. Welcome to PLSNHS!', 'dashboard.php');
    addNotification($conn, $student_id, 'action', '📋 Profile Updated', 'Your profile information has been successfully updated.', 'profile.php');
    
    // Reminders
    addNotification($conn, $student_id, 'reminder', '⏰ Enrollment Deadline', 'The enrollment deadline is approaching in 5 days. Complete your requirements.', 'enrollment.php');
    addNotification($conn, $student_id, 'reminder', '📝 Incomplete Requirements', 'Please submit your remaining requirements: PSA Birth Certificate.', 'requirements.php');
    addNotification($conn, $student_id, 'reminder', '💰 Payment Reminder', 'First quarter tuition fee is due by end of this month.', 'payments.php');
    
    // Alerts / Warnings
    addNotification($conn, $student_id, 'alert', '⚠️ Schedule Change', 'Your Math class schedule has been moved to Room 301.', 'schedule.php');
    addNotification($conn, $student_id, 'alert', '📄 Missing Document', 'Your Good Moral certificate is still pending. Please submit ASAP.', 'requirements.php');
    
    // Messages
    addNotification($conn, $student_id, 'message', '💬 Message from Registrar', 'Your enrollment documents have been received and are being processed.', 'enrollment.php');
    addNotification($conn, $student_id, 'message', '📨 Announcement from Principal', 'School will be closed on Friday for faculty development.', 'dashboard.php');
}

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notif_id = $_POST['notif_id'] ?? 0;
        markNotificationRead($conn, $notif_id, $student_id);
        echo json_encode(['success' => true, 'unread_count' => getUnreadCount($conn, $student_id)]);
        exit();
    }
    
    if ($action === 'mark_all_read') {
        markAllNotificationsRead($conn, $student_id);
        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit();
    }
    
    if ($action === 'get_notifications') {
        $notifications = getNotifications($conn, $student_id, 20);
        $unread_count = getUnreadCount($conn, $student_id);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit();
    }
    
    exit();
}

// Get notifications for display
$unread_notif_count = getUnreadCount($conn, $student_id);
$notifications_list = getNotifications($conn, $student_id, 20);

// Fetch enrollment data
$enrollment_query = "
    SELECT e.*, g.grade_name, s.section_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.section_id = s.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

// Determine enrollment status and data
$is_enrolled = ($enrollment && $enrollment['status'] == 'Enrolled');
$grade_display = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$section_display = $enrollment ? ($enrollment['section_name'] ?? 'Not Assigned') : 'Not Assigned';
$enrollment_status = $enrollment ? $enrollment['status'] : 'No Record';
$enrollment_status_color = '#6c757d';
if($enrollment) {
    if($enrollment['status'] == 'Pending') $enrollment_status_color = '#f59e0b';
    elseif($enrollment['status'] == 'Enrolled') $enrollment_status_color = '#10b981';
    else $enrollment_status_color = '#ef4444';
}

// Display grade with section if available
$enrollment_display = $grade_display;
if($is_enrolled && $section_display != 'Not Assigned') {
    $enrollment_display = $grade_display . ' - ' . $section_display;
}

// Get subjects count
$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $subjects_query = "SELECT COUNT(*) as count FROM subjects WHERE grade_id = :grade_id";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bindParam(':grade_id', $enrollment['grade_id'], PDO::PARAM_INT);
    $stmt->execute();
    $subjects_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $subjects_count = $subjects_result['count'] ?? 0;
    $stmt = null;
}

// Get average grade
$average_grade = '--';
$grades_query = "SELECT AVG(grade) as avg_grade FROM grades WHERE student_id = :student_id AND grade > 0";
$stmt = $conn->prepare($grades_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$grades_result = $stmt->fetch(PDO::FETCH_ASSOC);
if($grades_result && $grades_result['avg_grade'] > 0) {
    $average_grade = round($grades_result['avg_grade'], 2);
}
$stmt = null;

// Student Type Determination
$db_student_type = $enrollment ? ($enrollment['student_type'] ?? null) : null;

$student_type_map = [
    'new' => ['display' => 'New Student', 'icon' => 'fa-star', 'color' => '#f59e0b', 'description' => 'First time enrollee'],
    'continuing' => ['display' => 'Continuing', 'icon' => 'fa-undo-alt', 'color' => '#10b981', 'description' => 'Continuing student'],
    'transferee' => ['display' => 'Transferee', 'icon' => 'fa-exchange-alt', 'color' => '#8b5cf6', 'description' => 'Transferred from another school'],
    'old' => ['display' => 'Old Student', 'icon' => 'fa-user-check', 'color' => '#3b82f6', 'description' => 'Previously enrolled student'],
    'same_school' => ['display' => 'Same School', 'icon' => 'fa-school', 'color' => '#06b6d4', 'description' => 'From the same school'],
    'different_school' => ['display' => 'Different School', 'icon' => 'fa-building', 'color' => '#ec4899', 'description' => 'From a different school']
];

$student_type_key = 'new';
$since_year = date('Y');

if($db_student_type && isset($student_type_map[$db_student_type])) {
    $student_type_key = $db_student_type;
} else {
    $history_check = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?");
    $history_check->execute([$student_id]);
    $history_count = $history_check->fetch(PDO::FETCH_ASSOC);
    
    if($history_count['count'] > 1) {
        $student_type_key = 'continuing';
    } elseif($history_count['count'] == 1) {
        $student_type_key = 'new';
    }
}

$first_year_query = "SELECT school_year FROM enrollments WHERE student_id = ? ORDER BY created_at ASC LIMIT 1";
$stmt = $conn->prepare($first_year_query);
$stmt->execute([$student_id]);
$first_enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
if($first_enrollment && isset($first_enrollment['school_year'])) {
    $since_year = explode('-', $first_enrollment['school_year'])[0];
}

$student_type_display = $student_type_map[$student_type_key]['display'];
$student_type_icon = $student_type_map[$student_type_key]['icon'];
$student_type_color = $student_type_map[$student_type_key]['color'];
$student_type_description = $student_type_map[$student_type_key]['description'];

if($student_type_key == 'new') {
    if($grade_display == 'Grade 7') {
        $student_type_description = 'New student from elementary school';
    } elseif($grade_display == 'Grade 11') {
        $student_type_description = 'New senior high school student';
    } else {
        $student_type_description = 'First time enrollee';
    }
} elseif($student_type_key == 'continuing') {
    $student_type_description = 'Continuing student - progressing to next grade level';
} elseif($student_type_key == 'transferee') {
    $student_type_description = 'Transferred from another school - requirements may vary';
}

// Get enrollment history
$history_query = "
    SELECT e.*, g.grade_name 
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = :student_id
    ORDER BY e.created_at DESC
";
$stmt = $conn->prepare($history_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$enrollment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = null;

$recent_activities = array_slice($enrollment_history, 0, 5);

// Success/Error messages
$success_message = '';
$error_message = '';
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - PLSNHS | Placido L. Señor National High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* Additional Notification Styles */
        .notification-wrapper {
            position: relative;
        }
        
        .notification-btn {
            position: relative;
            background: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        
        .notification-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .notif-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            overflow: hidden;
            flex-direction: column;
        }
        
        .notification-dropdown.show {
            display: flex;
        }
        
        .dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        
        .dropdown-header h4 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
        
        .mark-all-read {
            background: none;
            border: none;
            color: #0B4F2E;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .mark-all-read:hover {
            text-decoration: underline;
        }
        
        .dropdown-body {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }
        
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
            position: relative;
        }
        
        .notif-item.unread {
            background: #f0f7ff;
        }
        
        .notif-item:hover {
            background: #f8f9fa;
        }
        
        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notif-icon.notif-update {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .notif-icon.notif-action {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .notif-icon.notif-reminder {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .notif-icon.notif-alert {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .notif-icon.notif-message {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .notif-content {
            flex: 1;
        }
        
        .notif-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .notif-message {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .notif-time {
            font-size: 11px;
            color: #999;
        }
        
        .mark-read-btn {
            background: none;
            border: none;
            color: #10b981;
            cursor: pointer;
            padding: 5px;
            opacity: 0.6;
            transition: opacity 0.3s;
            flex-shrink: 0;
        }
        
        .mark-read-btn:hover {
            opacity: 1;
        }
        
        .empty-notifications {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-notifications i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @media (max-width: 480px) {
            .notification-dropdown {
                width: calc(100vw - 20px);
                right: -10px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <div class="app-container">
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

            <div class="student-profile">
                <div class="student-avatar">
                    <?php if(isset($profile_picture) && $profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo isset($first_name) ? strtoupper(substr($first_name, 0, 1)) : 'S'; ?></div>
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
                        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php"><i class="fas fa-history"></i> Enrollment History</a></li>
                        <li><a href="requirements.php"><i class="fas fa-file-alt"></i> Requirements</a></li>
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header with Notifications -->
            <div class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($first_name); ?>!</p>
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
                </div>
            </div>

            <!-- Student Type Badge -->
            <div class="student-type-badge" style="background: <?php echo $student_type_color; ?>; margin-bottom: 20px; display: inline-flex;">
                <i class="fas <?php echo $student_type_icon; ?>"></i>
                <?php echo $student_type_display; ?> Student
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <!-- Student Type Information Card -->
            <div class="student-type-card" style="border-left: 4px solid <?php echo $student_type_color; ?>;">
                <div class="student-type-icon" style="background: <?php echo $student_type_color; ?>;">
                    <i class="fas <?php echo $student_type_icon; ?>"></i>
                </div>
                <div class="student-type-info">
                    <h3><?php echo $student_type_display; ?> Student</h3>
                    <p><?php echo $student_type_description; ?></p>
                    <?php if($student_type_key == 'continuing'): ?>
                        <small><i class="fas fa-arrow-up"></i> Progressing to next grade level</small>
                    <?php elseif($student_type_key == 'transferee'): ?>
                        <small><i class="fas fa-school"></i> Additional requirements may apply</small>
                    <?php elseif($student_type_key == 'new'): ?>
                        <small><i class="fas fa-graduation-cap"></i> First time enrollment</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='enrollment.php'">
                    <div class="stat-header"><h3>Enrollment Status</h3><div class="stat-icon"><i class="fas fa-graduation-cap"></i></div></div>
                    <div class="stat-number"><?php echo htmlspecialchars($enrollment_display); ?></div>
                    <div class="stat-label" style="color: <?php echo $enrollment_status_color; ?>;">
                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i>
                        <?php echo htmlspecialchars($enrollment_status); ?>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='subjects.php'">
                    <div class="stat-header"><h3>Subjects</h3><div class="stat-icon"><i class="fas fa-book"></i></div></div>
                    <div class="stat-number"><?php echo $subjects_count; ?></div>
                    <div class="stat-label">Current Subjects</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='grades.php'">
                    <div class="stat-header"><h3>Average Grade</h3><div class="stat-icon"><i class="fas fa-star"></i></div></div>
                    <div class="stat-number"><?php echo $average_grade; ?></div>
                    <div class="stat-label">Overall Average</div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollment_history.php'">
                    <div class="stat-header"><h3>Total Enrollments</h3>
                        <div class="stat-icon" style="background: <?php echo $student_type_color; ?>;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-number" style="font-size: 24px;"><?php echo count($enrollment_history); ?></div>
                    <div class="stat-label">
                        <i class="fas fa-history"></i> 
                        Since <?php echo $since_year; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section-title">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>

            <div class="actions-grid">
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>Enrollment</h3>
                    <p><?php echo $is_enrolled ? 'Update your enrollment information' : 'Enroll now for the current school year'; ?></p>
                    <a href="enrollment.php" class="action-btn"><?php echo $is_enrolled ? 'Update' : 'Enroll Now'; ?></a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Class Schedule</h3>
                    <p>View your weekly class schedule and subjects</p>
                    <a href="schedule.php" class="action-btn">View Schedule</a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-star"></i></div>
                    <h3>My Grades</h3>
                    <p>Check your grades and academic performance</p>
                    <a href="grades.php" class="action-btn">View Grades</a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-user-circle"></i></div>
                    <h3>My Profile</h3>
                    <p>Update your personal information and password</p>
                    <a href="profile.php" class="action-btn">View Profile</a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="section-title">
                <h2><i class="fas fa-history"></i> Recent Enrollment Activity</h2>
            </div>

            <div class="activity-card">
                <div class="activity-header">
                    <h3><i class="fas fa-file-signature"></i> Enrollment History</h3>
                    <span class="stat-label">Last <?php echo count($recent_activities); ?> records</span>
                </div>
                <div class="activity-list">
                    <?php if(!empty($recent_activities)): ?>
                        <?php foreach($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-dot 
                                    <?php 
                                        if($activity['status'] == 'Pending') echo 'dot-pending';
                                        else if($activity['status'] == 'Enrolled') echo 'dot-approved';
                                        else echo 'dot-completed';
                                    ?>">
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        Enrollment Request - SY <?php echo htmlspecialchars($activity['school_year']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i>
                                        School Year: <?php echo htmlspecialchars($activity['school_year']); ?>
                                        <?php if(isset($activity['student_type']) && $activity['student_type']): ?>
                                            <br><i class="fas fa-tag"></i> Type: <?php echo ucfirst(str_replace('_', ' ', $activity['student_type'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="activity-status 
                                    <?php 
                                        if($activity['status'] == 'Pending') echo 'status-pending';
                                        else if($activity['status'] == 'Enrolled') echo 'status-approved';
                                        else echo 'status-rejected';
                                    ?>">
                                    <?php echo htmlspecialchars($activity['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-content" style="text-align: center; padding: 30px;">
                                <i class="fas fa-file-signature" style="font-size: 40px; color: #999; opacity: 0.3; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No enrollment history found.</p>
                                <a href="enrollment.php" style="color: #0B4F2E; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 10px;">
                                    Enroll Now <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complete Enrollment History Section -->
            <?php if(!empty($enrollment_history)): ?>
            <div class="section-title">
                <h2><i class="fas fa-history"></i> Complete Enrollment History</h2>
            </div>

            <div class="activity-card">
                <div class="activity-header">
                    <h3><i class="fas fa-file-signature"></i> All Enrollments</h3>
                    <span class="stat-label">Total: <?php echo count($enrollment_history); ?> enrollments</span>
                </div>
                <div class="activity-list">
                    <?php foreach($enrollment_history as $index => $enrollment_item): ?>
                        <div class="activity-item">
                            <div class="activity-dot 
                                <?php 
                                    if($enrollment_item['status'] == 'Pending') echo 'dot-pending';
                                    else if($enrollment_item['status'] == 'Enrolled') echo 'dot-approved';
                                    else echo 'dot-completed';
                                ?>">
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <strong>School Year <?php echo htmlspecialchars($enrollment_item['school_year']); ?></strong>
                                    <?php if($index == 0): ?>
                                        <span style="margin-left: 10px; font-size: 11px; background: #10b981; color: white; padding: 2px 8px; border-radius: 12px;">Current</span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <i class="fas fa-layer-group"></i> Grade: <?php echo htmlspecialchars($enrollment_item['grade_name']); ?>
                                    <?php if(isset($enrollment_item['student_type']) && $enrollment_item['student_type']): ?>
                                        <br><i class="fas fa-tag"></i> Type: <?php echo ucfirst(str_replace('_', ' ', $enrollment_item['student_type'])); ?>
                                    <?php endif; ?>
                                    <?php if(isset($enrollment_item['section_name']) && $enrollment_item['section_name']): ?>
                                        - Section: <?php echo htmlspecialchars($enrollment_item['section_name']); ?>
                                    <?php endif; ?>
                                    <?php if(isset($enrollment_item['strand']) && $enrollment_item['strand']): ?>
                                        <br><i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($enrollment_item['strand']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-status 
                                <?php 
                                    if($enrollment_item['status'] == 'Pending') echo 'status-pending';
                                    else if($enrollment_item['status'] == 'Enrolled') echo 'status-approved';
                                    else echo 'status-rejected';
                                ?>">
                                <?php echo htmlspecialchars($enrollment_item['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- School Information -->
            <div class="school-info">
                <p>
                    <i class="fas fa-map-marker-alt"></i> PLACIDO L. SEÑOR SENIOR HIGH SCHOOL<br>
                    Langtad, City of Naga, Cebu 6037<br>
                    <i class="fas fa-phone"></i> (032) 123-4567 · <i class="fas fa-envelope"></i> info@plsshs.edu.ph
                </p>
            </div>
        </main>
    </div>

    <!-- Include Chatbot Widget -->
    <?php include('../includes/chatbot.php'); ?>

    <!-- Student Dashboard JS with Notifications -->
    <script>
        // Notification System JavaScript
        (function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            // Function to load notifications via AJAX
            function loadNotifications() {
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
                    if (data.success) {
                        updateNotificationUI(data.notifications, data.unread_count);
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
            }
            
            // Function to update notification UI
            function updateNotificationUI(notifications, unreadCount) {
                const notificationList = document.getElementById('notificationList');
                const badge = document.querySelector('.notif-count');
                
                // Update badge count
                if (unreadCount > 0) {
                    if (badge) {
                        badge.textContent = unreadCount;
                    } else if (notificationBtn) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notif-count';
                        newBadge.textContent = unreadCount;
                        notificationBtn.appendChild(newBadge);
                    }
                } else if (badge) {
                    badge.remove();
                }
                
                // Update notification list
                if (notificationList) {
                    if (notifications.length > 0) {
                        let html = '';
                        notifications.forEach(notif => {
                            const icons = {
                                'update': 'fa-megaphone',
                                'action': 'fa-check-circle',
                                'reminder': 'fa-clock',
                                'alert': 'fa-exclamation-triangle',
                                'message': 'fa-envelope'
                            };
                            const icon = icons[notif.type] || 'fa-bell';
                            const isUnread = notif.is_read == 0;
                            
                            html += `
                                <div class="notif-item ${isUnread ? 'unread' : 'read'}" data-id="${notif.id}">
                                    <div class="notif-icon notif-${notif.type}">
                                        <i class="fas ${icon}"></i>
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-title">${escapeHtml(notif.title)}</div>
                                        <div class="notif-message">${escapeHtml(notif.message)}</div>
                                        <div class="notif-time">${formatDate(notif.created_at)}</div>
                                    </div>
                                    ${isUnread ? `<button class="mark-read-btn" data-id="${notif.id}"><i class="fas fa-check"></i></button>` : ''}
                                </div>
                            `;
                        });
                        notificationList.innerHTML = html;
                        
                        // Re-attach event listeners
                        document.querySelectorAll('.mark-read-btn').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                const notifId = this.dataset.id;
                                markAsRead(notifId, this);
                            });
                        });
                    } else {
                        notificationList.innerHTML = `
                            <div class="empty-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                        `;
                    }
                }
            }
            
            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Helper function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
                       ' ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            }
            
            // Toggle dropdown
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    // Load fresh notifications when opening dropdown
                    if (notificationDropdown.classList.contains('show')) {
                        loadNotifications();
                    }
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
                })
                .catch(error => console.error('Error marking as read:', error));
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
                })
                .catch(error => console.error('Error marking all as read:', error));
            }
            
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function() {
                    markAllAsRead();
                });
            }
            
            // Auto-refresh notifications every 30 seconds
            setInterval(function() {
                loadNotifications();
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
    </script>
</body>
</html>