<?php
session_start();

// Check if user is teacher first before including database
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Include database after session check
require_once("../config/database.php");

// Check if connection is successful (PDO)
if (!$conn) {
    die("Database connection failed");
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$section_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';

// Get teacher profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
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

// Get section details and verify teacher has access
$section_query = "
    SELECT s.*, g.grade_name, u.fullname as adviser_name,
           CASE WHEN s.adviser_id = :teacher_id THEN 1 ELSE 0 END as is_adviser
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = :section_id AND (
        s.adviser_id = :teacher_id OR 
        EXISTS (
            SELECT 1 FROM class_schedules cs 
            WHERE cs.section_id = s.id AND cs.teacher_id = :teacher_id2
        )
    )
";

$stmt = $conn->prepare($section_query);
$stmt->execute([
    ':teacher_id' => $teacher_id,
    ':section_id' => $section_id,
    ':teacher_id2' => $teacher_id
]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if(!$section) {
    $_SESSION['error_message'] = "Section not found or you don't have access to this section.";
    header("Location: classes.php");
    exit();
}

// Check what columns exist in enrollments table
$columns_check = $conn->query("SHOW COLUMNS FROM enrollments");
$enrollment_columns = [];
if($columns_check) {
    while($col = $columns_check->fetch(PDO::FETCH_ASSOC)) {
        $enrollment_columns[] = $col['Field'];
    }
}

// Get students with profile pictures
if(in_array('section_id', $enrollment_columns)) {
    $students_query = "
        SELECT 
            u.id,
            u.fullname,
            u.email,
            u.id_number,
            u.profile_picture,
            e.status as enrollment_status
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        WHERE e.section_id = :section_id AND u.role = 'Student'
        ORDER BY u.fullname
    ";
    $stmt = $conn->prepare($students_query);
    $stmt->execute([':section_id' => $section_id]);
} else {
    $students_query = "
        SELECT 
            u.id,
            u.fullname,
            u.email,
            u.id_number,
            u.profile_picture,
            e.status as enrollment_status
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        WHERE e.grade_id = :grade_id AND u.role = 'Student'
        ORDER BY u.fullname
    ";
    $stmt = $conn->prepare($students_query);
    $stmt->execute([':grade_id' => $section['grade_id']]);
}

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
$student_count = count($students);

// Get subjects with teacher info
$subjects_query = "
    SELECT 
        cs.*,
        sub.subject_name,
        u.fullname as teacher_name,
        u.profile_picture as teacher_profile_pic,
        d.day_name,
        d.day_order,
        ts.start_time,
        ts.end_time,
        ts.slot_name,
        g.grade_name
    FROM class_schedules cs
    LEFT JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    LEFT JOIN days_of_week d ON cs.day_id = d.id
    LEFT JOIN time_slots ts ON cs.time_slot_id = ts.id
    LEFT JOIN grade_levels g ON sub.grade_id = g.id
    WHERE cs.section_id = :section_id AND (cs.status = 'active' OR cs.status IS NULL)
    ORDER BY d.day_order, ts.start_time
";

try {
    $stmt = $conn->prepare($subjects_query);
    $stmt->execute([':section_id' => $section_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subject_count = count($subjects);
} catch(PDOException $e) {
    $subjects = [];
    $subject_count = 0;
}

// Organize schedule by day for display
$subjects_taught = [];
if($subjects && $subject_count > 0) {
    foreach($subjects as $class) {
        if($class['teacher_id'] == $teacher_id) {
            $subjects_taught[] = $class['subject_name'];
        }
    }
}

// Get attendance statistics
$attendance_stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0
];

$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if($table_check && $table_check->rowCount() > 0) {
    if(in_array('section_id', $enrollment_columns)) {
        $attendance_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.section_id = :section_id
        ";
        $stmt = $conn->prepare($attendance_query);
        if($stmt) {
            $stmt->execute([':section_id' => $section_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            $attendance_stats = [
                'total' => $stats['total'] ?? 0,
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'late' => $stats['late'] ?? 0
            ];
        }
    }
}

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 1) 
    : 0;

// Get current school year
$current_sy = date('Y') . '-' . (date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($section['section_name']); ?> - Section Details</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- View Section CSS -->
    <link rel="stylesheet" href="css/view_section.css">
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
                        <li><a href="attendance_qr.php"><i class="fas fa-qrcode"></i> QR Attendance</a></li>
                        <li><a href="classes.php" class="active"><i class="fas fa-users"></i> My Classes</a></li>
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
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Section Details</h1>
                    <p>View information about <?php echo htmlspecialchars($section['section_name']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="classes.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Classes
                    </a>
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

            <!-- Section Header -->
            <div class="section-header">
                <div class="section-icon-large">
                    <i class="fas fa-users"></i>
                </div>
                <div class="section-title-info">
                    <h1><?php echo htmlspecialchars($section['section_name']); ?></h1>
                    <div class="badge-container">
                        <span class="badge badge-grade">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?>
                        </span>
                        <?php if($section['is_adviser']): ?>
                            <span class="badge badge-adviser">
                                <i class="fas fa-star"></i> You are the Adviser
                            </span>
                        <?php endif; ?>
                        <span class="badge badge-info">
                            <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?>
                        </span>
                        <span class="badge badge-info">
                            <i class="fas fa-calendar"></i> SY: <?php echo $current_sy; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $student_count; ?></div>
                        <div class="stat-label">Enrolled in this section</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-content">
                        <h3>Subjects</h3>
                        <div class="stat-number"><?php echo $subject_count; ?></div>
                        <div class="stat-label">Classes scheduled</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Overall attendance</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-content">
                        <h3>Your Subjects</h3>
                        <div class="stat-number"><?php echo count($subjects_taught); ?></div>
                        <div class="stat-label">You teach in this section</div>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Students List -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-graduate"></i> Enrolled Students</h3>
                        <span class="badge"><?php echo $student_count; ?> students</span>
                    </div>

                    <div class="table-container">
                        <?php if($students && $student_count > 0): ?>
                            <table class="data-table students-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="student-info">
                                                    <?php if(!empty($student['profile_picture']) && file_exists("../" . $student['profile_picture'])): ?>
                                                        <div class="student-avatar-img">
                                                            <img src="../<?php echo $student['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="student-avatar">
                                                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="student-details">
                                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                        <div class="student-meta">
                                                            <span><i class="fas fa-id-card"></i> <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                                                    <?php echo $student['enrollment_status']; ?>
                                                </span>
                                            </div>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn view" title="View Student">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-user-graduate"></i>
                                <p>No students enrolled in this section.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <!-- Class Schedule -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Class Schedule</h3>
                    <span class="badge"><?php echo $subject_count; ?> classes</span>
                </div>

                <div class="schedule-list">
                    <?php if($subjects && $subject_count > 0): ?>
                        <?php foreach($subjects as $class): 
                            $is_my_class = ($class['teacher_id'] == $teacher_id);
                        ?>
                            <div class="schedule-item <?php echo $is_my_class ? 'taught-by-me' : ''; ?>">
                                <div class="day-time">
                                    <span class="day"><?php echo $class['day_name']; ?></span>
                                    <span class="time">
                                        <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                    </span>
                                </div>
                                <div class="subject">
                                    <?php echo htmlspecialchars($class['subject_name']); ?>
                                    <?php if($is_my_class): ?>
                                        <span class="taught-badge">You teach this</span>
                                    <?php endif; ?>
                                </div>
                                <div class="teacher">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher_name']); ?>
                                </div>
                                <?php if($class['room']): ?>
                                    <div class="room">
                                        <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Schedule Yet</h3>
                            <p>No classes have been scheduled for this section.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Section JS -->
    <script src="js/view_section.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const sectionData = {
            id: <?php echo $section_id; ?>,
            name: '<?php echo addslashes($section['section_name']); ?>',
            grade: '<?php echo addslashes($section['grade_name']); ?>',
            studentCount: <?php echo $student_count; ?>,
            scheduleCount: <?php echo $subject_count; ?>,
            attendanceRate: <?php echo $attendance_rate; ?>,
            subjectsTaught: <?php echo count($subjects_taught); ?>
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