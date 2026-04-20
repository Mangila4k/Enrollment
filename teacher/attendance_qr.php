<?php
// Set Philippine Timezone
date_default_timezone_set('Asia/Manila');

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database - use require_once to prevent redeclaration
require_once '../config/database.php';

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Get teacher profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $user_data['profile_picture'] ?? null;

// Get sections where teacher is adviser
$sections_query = $conn->prepare("
    SELECT s.*, g.grade_name 
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
");
$sections_query->execute([$teacher_id]);
$sections = $sections_query->fetchAll(PDO::FETCH_ASSOC);

// Get subjects taught by this teacher
$subjects_query = $conn->prepare("
    SELECT DISTINCT sub.*, g.grade_name 
    FROM subjects sub
    JOIN grade_levels g ON sub.grade_id = g.id
    ORDER BY sub.subject_name
");
$subjects_query->execute();
$subjects = $subjects_query->fetchAll(PDO::FETCH_ASSOC);

// Get current time in Philippine Time
$ph_time_now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$current_hour = (int)$ph_time_now->format('H');
$current_minute = (int)$ph_time_now->format('i');
$current_time_display = $ph_time_now->format('h:i A');

// Define cutoff time for being on time (8:00 AM)
$cutoff_hour = 8;
$cutoff_minute = 0;
$is_late = ($current_hour > $cutoff_hour) || ($current_hour == $cutoff_hour && $current_minute > $cutoff_minute);

// FIRST: Get today's attendance record
$today_stmt = $conn->prepare("
    SELECT * FROM teacher_attendance 
    WHERE teacher_id = ? AND date = CURDATE()
    ORDER BY id DESC LIMIT 1
");
$today_stmt->execute([$teacher_id]);
$today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Determine attendance status
$time_in_recorded = false;
$time_out_recorded = false;
$attendance_completed = false;

if($today_attendance) {
    $time_in_recorded = ($today_attendance['time_in'] !== null && $today_attendance['time_in'] != '00:00:00');
    $time_out_recorded = ($today_attendance['time_out'] !== null && $today_attendance['time_out'] != '00:00:00');
    $attendance_completed = ($time_in_recorded && $time_out_recorded);
}

// Check for existing active session (only if attendance not completed)
$active_session = null;
if(!$attendance_completed && $today_attendance) {
    $active_stmt = $conn->prepare("
        SELECT * FROM teacher_attendance 
        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
    ");
    $active_stmt->execute([$today_attendance['id']]);
    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle QR generation - Different for Time In and Time Out
if(isset($_GET['generate']) && $_GET['generate'] == '1') {
    $type = isset($_GET['type']) ? $_GET['type'] : 'time_in';
    
    // Check if attendance is already completed for today
    if($attendance_completed) {
        $error_message = "You have already completed your attendance for today (both Time In and Time Out recorded).";
    } 
    // For Time In QR generation
    elseif($type == 'time_in') {
        // Check if Time In already recorded
        if($time_in_recorded) {
            $error_message = "Time In already recorded for today. You cannot generate a new Time In QR code.";
        } else {
            // Create new attendance record or update existing
            if(!$today_attendance) {
                // Create new attendance record
                $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999));
                $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $expires_time = clone $current_time;
                $expires_time->modify('+30 minutes');
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO teacher_attendance (teacher_id, date, qr_token, session_status, expires_at, session_type, status)
                    VALUES (?, CURDATE(), ?, 'active', ?, 'time_in', 'Pending')
                ");
                
                if($insert_stmt->execute([$teacher_id, $token, $expires_time->format('Y-m-d H:i:s')])) {
                    $success_message = "Time In QR Code generated successfully! Scan to record your Time In.";
                    // Refresh data
                    $today_stmt->execute([$teacher_id]);
                    $today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Refresh active session
                    $active_stmt = $conn->prepare("
                        SELECT * FROM teacher_attendance 
                        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
                    ");
                    $active_stmt->execute([$today_attendance['id']]);
                    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to generate QR code. Please try again.";
                }
            } else {
                // Update existing record with new QR code (if Time Out not recorded)
                if(!$time_out_recorded) {
                    // First, expire any existing active session
                    $expire_stmt = $conn->prepare("
                        UPDATE teacher_attendance 
                        SET session_status = 'expired'
                        WHERE id = ? AND session_status = 'active'
                    ");
                    $expire_stmt->execute([$today_attendance['id']]);
                    
                    // Generate new token
                    $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999));
                    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                    $expires_time = clone $current_time;
                    $expires_time->modify('+30 minutes');
                    
                    $update_stmt = $conn->prepare("
                        UPDATE teacher_attendance 
                        SET qr_token = ?, session_status = 'active', expires_at = ?, session_type = 'time_in'
                        WHERE id = ?
                    ");
                    if($update_stmt->execute([$token, $expires_time->format('Y-m-d H:i:s'), $today_attendance['id']])) {
                        $success_message = "Time In QR Code generated successfully!";
                        // Refresh active session
                        $active_stmt = $conn->prepare("
                            SELECT * FROM teacher_attendance 
                            WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
                        ");
                        $active_stmt->execute([$today_attendance['id']]);
                        $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Failed to generate QR code. Please try again.";
                    }
                } else {
                    $error_message = "Cannot generate Time In QR code. Time Out already recorded.";
                }
            }
        }
    }
    // For Time Out QR generation
    elseif($type == 'time_out') {
        // Check if Time In has been recorded
        if(!$time_in_recorded) {
            $error_message = "You must record Time In first before generating Time Out QR code.";
        } elseif($time_out_recorded) {
            $error_message = "Time Out already recorded for today.";
        } else {
            // Generate Time Out QR code
            if(!$today_attendance) {
                $error_message = "No attendance record found. Please record Time In first.";
            } else {
                // First, expire any existing active session
                $expire_stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET session_status = 'expired'
                    WHERE id = ? AND session_status = 'active'
                ");
                $expire_stmt->execute([$today_attendance['id']]);
                
                // Generate new token for Time Out
                $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999) . 'timeout');
                $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $expires_time = clone $current_time;
                $expires_time->modify('+30 minutes');
                
                $update_stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET qr_token = ?, session_status = 'active', expires_at = ?, session_type = 'time_out'
                    WHERE id = ?
                ");
                if($update_stmt->execute([$token, $expires_time->format('Y-m-d H:i:s'), $today_attendance['id']])) {
                    $success_message = "Time Out QR Code generated successfully! Scan to record your Time Out.";
                    // Refresh active session
                    $active_stmt = $conn->prepare("
                        SELECT * FROM teacher_attendance 
                        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
                    ");
                    $active_stmt->execute([$today_attendance['id']]);
                    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to generate Time Out QR code. Please try again.";
                }
            }
        }
    }
}

// Get today's attendance again after any changes
$today_stmt->execute([$teacher_id]);
$today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);

if($today_attendance) {
    $time_in_recorded = ($today_attendance['time_in'] !== null && $today_attendance['time_in'] != '00:00:00');
    $time_out_recorded = ($today_attendance['time_out'] !== null && $today_attendance['time_out'] != '00:00:00');
    $attendance_completed = ($time_in_recorded && $time_out_recorded);
}

// Get attendance history
$history_stmt = $conn->prepare("
    SELECT * FROM teacher_attendance 
    WHERE teacher_id = ? 
    ORDER BY date DESC, created_at DESC
    LIMIT 10
");
$history_stmt->execute([$teacher_id]);
$attendance_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
    FROM teacher_attendance 
    WHERE teacher_id = ?
");
$stats_stmt->execute([$teacher_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate the QR URL if active session exists
$qr_url = '';
if($active_session && $active_session['qr_token'] && !$attendance_completed) {
    $base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
    $base_url = str_replace('/teacher', '', $base_url);
    $qr_url = $base_url . "/EnrollmentSystem/teacher/process_attendance.php?token=" . $active_session['qr_token'];
}

// Get current Philippine time
$ph_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
$ph_time_display = $ph_time->format('h:i A');
$ph_date_display = $ph_time->format('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Attendance - Teacher Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- QR Attendance CSS -->
    <link rel="stylesheet" href="css/attendance_qr.css">
    <style>
        .qr-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .btn-generate-timein {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-generate-timeout {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-generate-timein:hover, .btn-generate-timeout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .info-text-late {
            color: #dc3545;
            background: #fee2e2;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .info-text-ontime {
            color: #10b981;
            background: #d4edda;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .cutoff-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }
        .cutoff-info i {
            margin-right: 8px;
        }
        .text-warning {
            color: #f59e0b;
        }
        .status-badge.status-Late {
            background: #fee2e2;
            color: #dc3545;
        }
        .status-badge.status-Present {
            background: #d4edda;
            color: #10b981;
        }
    </style>
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

            <div class="teacher-profile">
                <div class="teacher-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($teacher_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="teacher-name"><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></div>
                <div class="teacher-role"><i class="fas fa-chalkboard-user"></i> Teacher</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="attendance_qr.php" class="active"><i class="fas fa-qrcode"></i> QR Attendance</a></li>
                        <li><a href="classes.php"><i class="fas fa-users"></i> My Classes</a></li>
                        <li><a href="schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> Grades</a></li>
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>QR Code Attendance System</h1>
                    <p>Generate QR code to record your Time In and Time Out</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                </div>
            </div>

            <!-- Philippine Time Display -->
            <div class="ph-time">
                <i class="fas fa-clock"></i> Philippine Time: <?php echo $ph_date_display . ' - ' . $ph_time_display; ?>
            </div>

            <!-- Cutoff Time Info -->
            <div class="cutoff-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Time In Cutoff: 8:00 AM</strong> - Time in after 8:00 AM will be marked as "Late"
                <?php if(!$time_in_recorded && $is_late): ?>
                    <span class="info-text-late" style="display: inline-block; margin-left: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> Current time is past 8:00 AM, Time In will be marked as LATE
                    </span>
                <?php endif; ?>
            </div>

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

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['total_days'] ?? 0; ?></div>
                    <div class="stat-label">Total Days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['present_days'] ?? 0; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['late_days'] ?? 0; ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['absent_days'] ?? 0; ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>

            <!-- Today's Attendance Info -->
            <?php if($today_attendance): ?>
            <div class="attendance-info">
                <h3><i class="fas fa-calendar-day"></i> Today's Attendance (<?php echo $ph_date_display; ?>)</h3>
                <div class="info-row">
                    <span class="info-label">⏰ Time In:</span>
                    <span class="info-value">
                        <?php 
                        if($time_in_recorded) {
                            $time_in_val = date('h:i A', strtotime($today_attendance['time_in']));
                            $time_in_obj = new DateTime($today_attendance['time_in']);
                            $time_in_hour = (int)$time_in_obj->format('H');
                            $is_time_in_late = ($time_in_hour > 8 || ($time_in_hour == 8 && (int)$time_in_obj->format('i') > 0));
                            echo $time_in_val;
                            if($is_time_in_late) {
                                echo ' <span class="status-badge status-Late" style="font-size: 11px; margin-left: 8px;">Late</span>';
                            } else {
                                echo ' <span class="status-badge status-Present" style="font-size: 11px; margin-left: 8px;">On Time</span>';
                            }
                        } else {
                            echo '<span class="text-warning">Not yet recorded</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">⏰ Time Out:</span>
                    <span class="info-value">
                        <?php 
                        if($time_out_recorded) {
                            echo date('h:i A', strtotime($today_attendance['time_out']));
                        } elseif($time_in_recorded && !$time_out_recorded) {
                            echo '<span class="text-warning">Waiting for Time Out...</span>';
                        } else {
                            echo '<span class="text-warning">Not yet recorded</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">📊 Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo $today_attendance['status']; ?>">
                            <?php echo $today_attendance['status']; ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- QR Code Scanner Section (Hidden if attendance completed) -->
            <?php if(!$attendance_completed): ?>
            <div class="scanner-container">
                <div class="scanner-header">
                    <h3><i class="fas fa-camera"></i> Scan QR Code to Record Attendance</h3>
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="switchTab('camera')">📷 Camera</button>
                        <button class="tab-btn" onclick="switchTab('upload')">📁 Upload Photo</button>
                    </div>
                </div>
                
                <!-- Camera Tab -->
                <div id="cameraScannerTab" class="scanner-tab active-tab">
                    <div class="video-container">
                        <video id="video" playsinline autoplay></video>
                        <canvas id="canvas"></canvas>
                        <div class="scan-line" id="scanLine"></div>
                    </div>
                    <div class="scanner-controls">
                        <button class="btn-scanner" id="startCameraBtn" onclick="startCamera()">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>
                        <button class="btn-scanner danger" id="stopCameraBtn" onclick="stopCamera()" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Camera
                        </button>
                    </div>
                </div>
                
                <!-- Upload Tab -->
                <div id="uploadScannerTab" class="scanner-tab">
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload QR code image</p>
                        <p class="upload-hint">Supports: JPG, PNG, GIF</p>
                    </div>
                    <input type="file" id="fileInput" accept="image/*" onchange="uploadImage(this)">
                    <div class="preview-image" id="previewImage">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>
                
                <div class="scan-result" id="scanResult">
                    <div id="scanResultText"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- QR Code Section -->
            <div class="qr-card">
                <?php if($attendance_completed): ?>
                    <!-- Case 1: Both Time In and Time Out completed -->
                    <div class="qr-container">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h3>Attendance Completed for Today</h3>
                        <p>You have already recorded both Time In and Time Out.</p>
                        <p><strong>Time In:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_in'])); ?></p>
                        <p><strong>Time Out:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_out'])); ?></p>
                        <p class="info-text">You can generate a new QR code tomorrow for the next attendance day.</p>
                    </div>
                    
                <?php elseif($time_in_recorded && !$time_out_recorded): ?>
                    <!-- Case 2: Time In recorded, Time Out NOT recorded - Show Time Out QR section -->
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Time Out QR Code</h3>
                        <p>Your Time In has been recorded. Generate a QR code for Time Out.</p>
                        
                        <?php if($active_session && $active_session['qr_token'] && $qr_url && isset($active_session['session_type']) && $active_session['session_type'] == 'time_out'): ?>
                            <!-- Active Time Out QR code exists -->
                            <div class="qr-code">
                                <img src="../includes/generate_qr.php?data=<?php echo urlencode($qr_url); ?>" alt="Time Out QR Code">
                            </div>
                            <div class="qr-info">
                                <h4>Scan this QR Code to Record Time Out</h4>
                                <p>📱 Scan this QR code using your phone or the camera above</p>
                                <p class="expiry-text">
                                    <i class="fas fa-clock"></i> Expires: <?php echo date('h:i A', strtotime($active_session['expires_at'])); ?>
                                </p>
                                <div class="qr-actions">
                                    <a href="?generate=1&type=time_out" class="btn-generate-timeout" onclick="return confirm('Generate a new Time Out QR code? The current one will expire.');">
                                        <i class="fas fa-sync-alt"></i> Generate New Time Out QR Code
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- No active Time Out QR code - Show generate button -->
                            <div class="qr-actions" style="text-align: center; padding: 30px;">
                                <a href="?generate=1&type=time_out" class="btn-generate-timeout">
                                    <i class="fas fa-qrcode"></i> Generate Time Out QR Code
                                </a>
                                <p class="info-text" style="margin-top: 15px;">
                                    <i class="fas fa-info-circle"></i> Generate a QR code to record your Time Out. This will expire in 30 minutes.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif(!$time_in_recorded): ?>
                    <!-- Case 3: No Time In recorded yet - Show Time In QR section -->
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Time In QR Code</h3>
                        <p>Generate a QR code to record your Time In</p>
                        
                        <?php if($active_session && $active_session['qr_token'] && $qr_url && (!isset($active_session['session_type']) || $active_session['session_type'] != 'time_out')): ?>
                            <!-- Active Time In QR code exists -->
                            <div class="qr-code">
                                <img src="../includes/generate_qr.php?data=<?php echo urlencode($qr_url); ?>" alt="Time In QR Code">
                            </div>
                            <div class="qr-info">
                                <h4>Scan this QR Code to Record Time In</h4>
                                <p>📱 Scan this QR code using your phone or the camera above</p>
                                <p class="expiry-text">
                                    <i class="fas fa-clock"></i> Expires: <?php echo date('h:i A', strtotime($active_session['expires_at'])); ?>
                                </p>
                                <?php if($is_late): ?>
                                    <div class="info-text-late">
                                        <i class="fas fa-exclamation-triangle"></i> Note: Time In after 8:00 AM will be marked as LATE
                                    </div>
                                <?php else: ?>
                                    <div class="info-text-ontime">
                                        <i class="fas fa-check-circle"></i> Time In before 8:00 AM will be marked as ON TIME
                                    </div>
                                <?php endif; ?>
                                <div class="qr-actions">
                                    <a href="?generate=1&type=time_in" class="btn-generate-timein" onclick="return confirm('Generate a new Time In QR code? The current one will expire.');">
                                        <i class="fas fa-sync-alt"></i> Generate New Time In QR Code
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- No active Time In QR code - Show generate button -->
                            <div class="qr-actions" style="text-align: center; padding: 30px;">
                                <a href="?generate=1&type=time_in" class="btn-generate-timein">
                                    <i class="fas fa-qrcode"></i> Generate Time In QR Code
                                </a>
                                <p class="info-text" style="margin-top: 15px;">
                                    <i class="fas fa-info-circle"></i> Generate a QR code to record your Time In. This will expire in 30 minutes.
                                    <?php if($is_late): ?>
                                        <br><strong class="text-warning">⚠️ Note: Time In after 8:00 AM will be marked as LATE</strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attendance History -->
            <div class="history-section">
                <h3><i class="fas fa-history"></i> Attendance History (Last 10 Records)</h3>
                <?php if(count($attendance_history) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In (PH Time)</th>
                                <th>Time Out (PH Time)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($attendance_history as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td>
                                    <?php 
                                    if($record['time_in'] && $record['time_in'] != '00:00:00') {
                                        $time_in = new DateTime($record['time_in'], new DateTimeZone('Asia/Manila'));
                                        echo $time_in->format('h:i A');
                                    } else {
                                        echo '--:--';
                                    }
                                    ?>
                                 </div>
                                <td>
                                    <?php 
                                    if($record['time_out'] && $record['time_out'] != '00:00:00') {
                                        $time_out = new DateTime($record['time_out'], new DateTimeZone('Asia/Manila'));
                                        echo $time_out->format('h:i A');
                                    } else {
                                        echo '--:--';
                                    }
                                    ?>
                                 </div>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo $record['status']; ?>
                                    </span>
                                 </div>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                     </div>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No attendance records found</p>
                    <p class="info-text">Generate a QR code to start recording your attendance</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- jsQR Library for QR code scanning -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <!-- QR Attendance JS -->
    <script src="js/attendance_qr.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const attendanceData = {
            teacherId: <?php echo $teacher_id; ?>,
            teacherName: '<?php echo addslashes($teacher_name); ?>',
            totalDays: <?php echo $stats['total_days'] ?? 0; ?>,
            presentDays: <?php echo $stats['present_days'] ?? 0; ?>,
            lateDays: <?php echo $stats['late_days'] ?? 0; ?>,
            absentDays: <?php echo $stats['absent_days'] ?? 0; ?>,
            isLate: <?php echo $is_late ? 'true' : 'false'; ?>,
            timeInRecorded: <?php echo $time_in_recorded ? 'true' : 'false'; ?>,
            timeOutRecorded: <?php echo $time_out_recorded ? 'true' : 'false'; ?>
        };
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
    
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>