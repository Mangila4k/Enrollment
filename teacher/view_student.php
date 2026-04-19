<?php
session_start();

// Check if user is teacher first before including database
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Include database after session check
require_once("../config/database.php");

// Check if connection is successful
if (!$conn) {
    die("Database connection failed");
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
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

// Get student details with profile picture
$student_query = "
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.id_number,
        u.created_at,
        u.profile_picture
    FROM users u
    WHERE u.id = :student_id AND u.role = 'Student'
";

$stmt = $conn->prepare($student_query);
$stmt->execute([':student_id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$student) {
    $_SESSION['error_message'] = "Student not found.";
    header("Location: classes.php");
    exit();
}

$student_profile_pic = $student['profile_picture'] ?? null;

// Get enrollment information
$enrollment_query = "
    SELECT 
        e.*,
        g.grade_name,
        s.section_name
    FROM enrollments e
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.section_id = s.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.id DESC
    LIMIT 1
";

$stmt = $conn->prepare($enrollment_query);
$stmt->execute([':student_id' => $student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// Merge enrollment data with student data
if($enrollment) {
    foreach($enrollment as $key => $value) {
        $student[$key] = $value;
    }
}

// Get attendance statistics
$attendance_stats_query = "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count
    FROM attendance
    WHERE student_id = :student_id
";

$stmt = $conn->prepare($attendance_stats_query);
$stmt->execute([':student_id' => $student_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_days = $stats['total_days'] ?? 0;
$present_count = $stats['present_count'] ?? 0;
$absent_count = $stats['absent_count'] ?? 0;
$late_count = $stats['late_count'] ?? 0;
$attendance_rate = $total_days > 0 ? round(($present_count / $total_days) * 100, 1) : 0;

// Get recent attendance records
$attendance_query = "
    SELECT 
        a.*,
        DATE_FORMAT(a.date, '%M %d, %Y') as formatted_date,
        sub.subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = :student_id
    ORDER BY a.date DESC
    LIMIT 30
";

$stmt = $conn->prepare($attendance_query);
$stmt->execute([':student_id' => $student_id]);
$attendance_records = $stmt;
$attendance_count = $stmt->rowCount();

// Get grades
$grades_query = "
    SELECT 
        g.*,
        sub.subject_name
    FROM grades g
    LEFT JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = :student_id
    ORDER BY g.quarter, sub.subject_name
";

$stmt = $conn->prepare($grades_query);
$stmt->execute([':student_id' => $student_id]);
$grades_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$grades_count = count($grades_data);

// Calculate grade statistics
$grade_stats = [
    'q1_avg' => 0,
    'q2_avg' => 0,
    'q3_avg' => 0,
    'q4_avg' => 0,
    'final_avg' => 0,
    'total_subjects' => 0
];

$subjects_list = [];
$q1_sum = 0; $q1_count = 0;
$q2_sum = 0; $q2_count = 0;
$q3_sum = 0; $q3_count = 0;
$q4_sum = 0; $q4_count = 0;

foreach($grades_data as $grade) {
    if (!in_array($grade['subject_id'], $subjects_list)) {
        $subjects_list[] = $grade['subject_id'];
    }
    
    if ($grade['quarter'] == 1 && isset($grade['grade']) && $grade['grade'] > 0) {
        $q1_sum += $grade['grade'];
        $q1_count++;
    } elseif ($grade['quarter'] == 2 && isset($grade['grade']) && $grade['grade'] > 0) {
        $q2_sum += $grade['grade'];
        $q2_count++;
    } elseif ($grade['quarter'] == 3 && isset($grade['grade']) && $grade['grade'] > 0) {
        $q3_sum += $grade['grade'];
        $q3_count++;
    } elseif ($grade['quarter'] == 4 && isset($grade['grade']) && $grade['grade'] > 0) {
        $q4_sum += $grade['grade'];
        $q4_count++;
    }
}

$grade_stats = [
    'q1_avg' => $q1_count > 0 ? round($q1_sum / $q1_count, 2) : 0,
    'q2_avg' => $q2_count > 0 ? round($q2_sum / $q2_count, 2) : 0,
    'q3_avg' => $q3_count > 0 ? round($q3_sum / $q3_count, 2) : 0,
    'q4_avg' => $q4_count > 0 ? round($q4_sum / $q4_count, 2) : 0,
    'total_subjects' => count($subjects_list)
];

// Calculate final average
$total_quarters = 0;
$sum_quarters = 0;
if($grade_stats['q1_avg'] > 0) { $sum_quarters += $grade_stats['q1_avg']; $total_quarters++; }
if($grade_stats['q2_avg'] > 0) { $sum_quarters += $grade_stats['q2_avg']; $total_quarters++; }
if($grade_stats['q3_avg'] > 0) { $sum_quarters += $grade_stats['q3_avg']; $total_quarters++; }
if($grade_stats['q4_avg'] > 0) { $sum_quarters += $grade_stats['q4_avg']; $total_quarters++; }
$grade_stats['final_avg'] = $total_quarters > 0 ? round($sum_quarters / $total_quarters, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['fullname']); ?> - Student Profile</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- View Student CSS -->
    <link rel="stylesheet" href="css/view_student.css">
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
                    <h1>Student Profile</h1>
                    <p>View detailed information about <?php echo htmlspecialchars($student['fullname']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="javascript:history.back()" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Go Back
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?php if($student_profile_pic && file_exists("../" . $student_profile_pic)): ?>
                        <img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="avatar-initial">
                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($student['fullname']); ?></h1>
                    <div class="profile-meta">
                        <span class="meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-id-card"></i> Student ID: <?php echo $student['id_number'] ?? str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?>
                        </span>
                        <?php if(isset($student['grade_name']) && $student['grade_name']): ?>
                        <span class="meta-item">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($student['grade_name']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if(isset($student['section_name']) && $student['section_name']): ?>
                        <span class="meta-item">
                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($student['section_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($student['status']) && $student['status']): ?>
                    <div class="status-container">
                        <span class="status-badge status-<?php echo strtolower($student['status']); ?>">
                            <i class="fas fa-circle"></i> Enrollment: <?php echo $student['status']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Overall attendance</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h3>Present</h3>
                        <div class="stat-number"><?php echo $present_count; ?></div>
                        <div class="stat-label">Days present</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3>Late</h3>
                        <div class="stat-number"><?php echo $late_count; ?></div>
                        <div class="stat-label">Days late</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-content">
                        <h3>Absent</h3>
                        <div class="stat-number"><?php echo $absent_count; ?></div>
                        <div class="stat-label">Days absent</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-content">
                        <h3>Average Grade</h3>
                        <div class="stat-number"><?php echo $grade_stats['final_avg'] > 0 ? $grade_stats['final_avg'] : 'N/A'; ?></div>
                        <div class="stat-label">Overall average</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-content">
                        <h3>Subjects</h3>
                        <div class="stat-number"><?php echo $grade_stats['total_subjects']; ?></div>
                        <div class="stat-label">Enrolled subjects</div>
                    </div>
                </div>
            </div>

            <!-- Personal Information and Grade Summary -->
            <div class="info-grid">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['fullname']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-id-card"></i> Student ID</span>
                            <span class="info-value"><?php echo $student['id_number'] ?? str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <?php if(isset($student['created_at'])): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-clock"></i> Account Created</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($student['created_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                    </div>
                    <div class="info-list">
                        <?php if(isset($student['grade_name']) && $student['grade_name']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-layer-group"></i> Grade Level</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['section_name']) && $student['section_name']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-users"></i> Section</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['section_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['school_year']) && $student['school_year']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-calendar-alt"></i> School Year</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['school_year']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['strand']) && $student['strand']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-tag"></i> Strand</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['strand']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Grade Summary -->
                    <?php if($grade_stats['total_subjects'] > 0): ?>
                    <div class="grade-summary-section">
                        <h4>Quarterly Averages</h4>
                        <div class="grade-summary">
                            <div class="summary-item">
                                <div class="label">Q1</div>
                                <div class="value"><?php echo $grade_stats['q1_avg'] > 0 ? $grade_stats['q1_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q2</div>
                                <div class="value"><?php echo $grade_stats['q2_avg'] > 0 ? $grade_stats['q2_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q3</div>
                                <div class="value"><?php echo $grade_stats['q3_avg'] > 0 ? $grade_stats['q3_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q4</div>
                                <div class="value"><?php echo $grade_stats['q4_avg'] > 0 ? $grade_stats['q4_avg'] : '—'; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grades Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-star"></i> Subject Grades</h3>
                    <span class="badge"><?php echo $grades_count; ?> records</span>
                </div>

                <div class="table-container">
                    <?php if($grades_count > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Quarter</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($grades_data as $grade): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong>
                                         </div>
                                        <td>Quarter <?php echo $grade['quarter']; ?> </div>
                                        <td>
                                            <?php if(isset($grade['grade']) && $grade['grade'] > 0): ?>
                                                <span class="<?php 
                                                    echo $grade['grade'] >= 90 ? 'grade-high' : 
                                                        ($grade['grade'] >= 75 ? 'grade-medium' : 'grade-low'); 
                                                ?>">
                                                    <?php echo $grade['grade']; ?>
                                                </span>
                                            <?php else: ?>—<?php endif; ?>
                                         </div>
                                        <td>
                                            <?php if(isset($grade['remarks']) && $grade['remarks']): ?>
                                                <?php echo htmlspecialchars($grade['remarks']); ?>
                                            <?php else: ?>—<?php endif; ?>
                                         </div>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                         </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-star"></i>
                            <h3>No Grades Available</h3>
                            <p>This student doesn't have any grades recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card quick-actions-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="grades.php?student_id=<?php echo $student_id; ?>" class="action-btn warning">
                        <i class="fas fa-star"></i> Manage Grades
                    </a>
                    <?php if(isset($student['section_id']) && $student['section_id']): ?>
                    <a href="view_section.php?id=<?php echo $student['section_id']; ?>" class="action-btn secondary">
                        <i class="fas fa-users"></i> View Class
                    </a>
                    <?php endif; ?>
                    <a href="#" class="action-btn info" onclick="alert('Message feature coming soon!')">
                        <i class="fas fa-envelope"></i> Send Message
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- View Student JS -->
    <script src="js/view_student.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const studentData = {
            id: <?php echo $student_id; ?>,
            name: '<?php echo addslashes($student['fullname']); ?>',
            attendanceRate: <?php echo $attendance_rate; ?>,
            averageGrade: <?php echo $grade_stats['final_avg']; ?>
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