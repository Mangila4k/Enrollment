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

// FIRST: Get today's attendance record (should be only one)
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
    // Check if there's an active session for today's record
    $active_stmt = $conn->prepare("
        SELECT * FROM teacher_attendance 
        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
    ");
    $active_stmt->execute([$today_attendance['id']]);
    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle QR generation
if(isset($_GET['generate']) && $_GET['generate'] == '1') {
    // Check if attendance is already completed for today
    if($attendance_completed) {
        $error_message = "You have already completed your attendance for today (both Time In and Time Out recorded). You cannot generate a new QR code.";
    } else {
        // Check if there's an existing attendance record for today
        if(!$today_attendance) {
            // Create new attendance record
            $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999));
            $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $expires_time = clone $current_time;
            $expires_time->modify('+1 hour');
            
            $insert_stmt = $conn->prepare("
                INSERT INTO teacher_attendance (teacher_id, date, qr_token, session_status, expires_at, status)
                VALUES (?, CURDATE(), ?, 'active', ?, 'Pending')
            ");
            
            if($insert_stmt->execute([$teacher_id, $token, $expires_time->format('Y-m-d H:i:s')])) {
                $success_message = "QR Code generated successfully! Scan to record your attendance.";
                // Refresh data
                $today_stmt->execute([$teacher_id]);
                $today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);
                $active_session = $today_attendance;
            } else {
                $error_message = "Failed to generate QR code. Please try again.";
            }
        } else {
            // Update existing record with new QR code (only if time out not recorded)
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
                $expires_time->modify('+1 hour');
                
                $update_stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET qr_token = ?, session_status = 'active', expires_at = ?
                    WHERE id = ?
                ");
                if($update_stmt->execute([$token, $expires_time->format('Y-m-d H:i:s'), $today_attendance['id']])) {
                    $success_message = "New QR Code generated successfully!";
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
                $error_message = "Cannot generate QR code. Time Out already recorded.";
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
if($active_session && $active_session['qr_token'] && !$attendance_completed && !$time_out_recorded) {
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
                    <p>Generate QR code or scan to record your time in and time out</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                </div>
            </div>

            <!-- Philippine Time Display -->
            <div class="ph-time">
                <i class="fas fa-clock"></i> Philippine Time: <?php echo $ph_date_display . ' - ' . $ph_time_display; ?>
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
                            echo date('h:i A', strtotime($today_attendance['time_in']));
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
                            echo '<span class="text-warning">Waiting for Time Out scan...</span>';
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
                <?php if($time_in_recorded && !$time_out_recorded): ?>
                <div class="info-row info-highlight">
                    <span class="info-label">📌 Next Step:</span>
                    <span class="info-value">Scan the same QR code again to record Time Out</span>
                </div>
                <?php endif; ?>
                <?php if($attendance_completed): ?>
                <div class="info-row info-success">
                    <span class="info-label">✅ Complete:</span>
                    <span class="info-value">You have completed your attendance for today!</span>
                </div>
                <?php endif; ?>
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
                    <div class="qr-container">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h3>Attendance Completed for Today</h3>
                        <p>You have already recorded both Time In and Time Out.</p>
                        <p><strong>Time In:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_in'])); ?></p>
                        <p><strong>Time Out:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_out'])); ?></p>
                        <p class="info-text">You can generate a new QR code tomorrow for the next attendance day.</p>
                    </div>
                <?php elseif($active_session && $active_session['qr_token'] && $qr_url && !$time_out_recorded): ?>
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Your Active QR Code</h3>
                        <div class="qr-code">
                            <img src="../includes/generate_qr.php?data=<?php echo urlencode($qr_url); ?>" alt="QR Code">
                        </div>
                        <div class="qr-info">
                            <h4>Scan this QR Code to Record Attendance</h4>
                            <p>📱 Scan this QR code using your phone or the camera above</p>
                            <p class="expiry-text">
                                <i class="fas fa-clock"></i> Expires: <?php echo date('h:i A', strtotime($active_session['expires_at'])); ?>
                            </p>
                            <p class="info-text">
                                <i class="fas fa-info-circle"></i> 
                                <?php if(!$time_in_recorded): ?>
                                    First scan = Record Time In
                                <?php elseif($time_in_recorded && !$time_out_recorded): ?>
                                    Second scan = Record Time Out
                                <?php endif; ?>
                            </p>
                            <div class="qr-actions">
                                <a href="?generate=1" class="btn-generate regenerate" onclick="return confirm('Generate a new QR code? The current one will expire.');">
                                    <i class="fas fa-sync-alt"></i> Generate New QR Code
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif($time_in_recorded && !$time_out_recorded && !$active_session): ?>
                    <div class="qr-container">
                        <i class="fas fa-clock warning-icon"></i>
                        <h3>QR Code Expired or Not Generated</h3>
                        <p>You have recorded Time In but need to scan again for Time Out.</p>
                        <p>Please generate a new QR code to record your Time Out.</p>
                        <div class="qr-actions">
                            <a href="?generate=1" class="btn-generate">
                                <i class="fas fa-qrcode"></i> Generate QR Code for Time Out
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Generate QR Code</h3>
                        <p>Click the button below to generate a QR code for today's attendance.</p>
                        <p class="info-text">
                            <i class="fas fa-info-circle"></i> The QR code will expire in 1 hour
                        </p>
                        <div class="qr-actions">
                            <a href="?generate=1" class="btn-generate">
                                <i class="fas fa-qrcode"></i> Generate QR Code
                            </a>
                        </div>
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
            absentDays: <?php echo $stats['absent_days'] ?? 0; ?>
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
    
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>