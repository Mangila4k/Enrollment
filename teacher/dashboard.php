<?php
session_start();
include("../config/database.php");

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

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get sections where teacher is adviser
$sections_query = $conn->prepare("
    SELECT s.*, g.grade_name 
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
");
$sections_query->execute([$teacher_id]);
$sections = $sections_query->fetchAll(PDO::FETCH_ASSOC);

// Get all grade levels for the filter dropdown
$grade_levels_query = $conn->query("SELECT * FROM grade_levels ORDER BY id");
$grade_levels = $grade_levels_query->fetchAll(PDO::FETCH_ASSOC);

// Get subjects grouped by grade level
$subjects_by_grade = [];
foreach($grade_levels as $grade) {
    $subjects_query = $conn->prepare("
        SELECT sub.*, g.grade_name 
        FROM subjects sub
        JOIN grade_levels g ON sub.grade_id = g.id
        WHERE sub.grade_id = ?
        ORDER BY sub.subject_name
    ");
    $subjects_query->execute([$grade['id']]);
    $subjects = $subjects_query->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($subjects) > 0) {
        $subjects_by_grade[$grade['grade_name']] = $subjects;
    }
}

// Get today's attendance count
$today = date('Y-m-d');
$attendance_today_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM attendance 
    WHERE date = ?
");
$attendance_today_stmt->execute([$today]);
$attendance_today = $attendance_today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total students in teacher's sections
$total_students = 0;
if(count($sections) > 0) {
    foreach($sections as $section) {
        $student_count_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            WHERE e.grade_id = ? 
            AND e.status = 'Enrolled'
        ");
        $student_count_stmt->execute([$section['grade_id']]);
        $student_count = $student_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_students += $student_count;
    }
}

// Get total subjects count
$total_subjects = 0;
foreach($subjects_by_grade as $subjects) {
    $total_subjects += count($subjects);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Teacher Dashboard CSS -->
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
                        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="attendance_qr.php"><i class="fas fa-qrcode"></i> QR Attendance</a></li>
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
                    <h1>Teacher Dashboard</h1>
                    <p>Manage your classes, attendance, and student progress</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
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
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Enrolled students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>My Sections</h3>
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    </div>
                    <div class="stat-number"><?php echo count($sections); ?></div>
                    <div class="stat-label">Classes handling</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Total subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Today's Attendance</h3>
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $attendance_today; ?></div>
                    <div class="stat-label">Records today</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="section-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="actions-grid">
                    <a href="attendance_qr.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-qrcode"></i></div>
                        <div class="action-info">
                            <h4>QR Attendance</h4>
                            <p>Scan QR code to record attendance</p>
                        </div>
                    </a>
                    
                    <a href="classes.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-users"></i></div>
                        <div class="action-info">
                            <h4>View Classes</h4>
                            <p>See your assigned sections</p>
                        </div>
                    </a>
                    
                    <a href="schedule.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-clock"></i></div>
                        <div class="action-info">
                            <h4>Class Schedule</h4>
                            <p>Check your teaching schedule</p>
                        </div>
                    </a>
                    
                    <a href="grades.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-star"></i></div>
                        <div class="action-info">
                            <h4>Enter Grades</h4>
                            <p>Input student grades</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- My Classes and Subjects Grid -->
            <div class="dashboard-grid">
                <!-- My Sections -->
                <div class="info-card">
                    <h3><i class="fas fa-layer-group"></i> My Sections</h3>
                    <?php if(count($sections) > 0): ?>
                        <div class="section-list">
                            <?php foreach($sections as $section): ?>
                                <div class="section-item">
                                    <div class="section-info">
                                        <h4><?php echo htmlspecialchars($section['section_name']); ?></h4>
                                        <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($section['grade_name']); ?></p>
                                    </div>
                                    <span class="badge">Adviser</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-layer-group"></i>
                            <p>No sections assigned yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Subjects Organized by Grade Level -->
                <div class="info-card">
                    <h3><i class="fas fa-book"></i> Subjects by Grade Level</h3>
                    
                    <?php if(count($subjects_by_grade) > 0): ?>
                        <div class="subjects-accordion">
                            <?php 
                            $grade_order = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
                            foreach($grade_order as $grade_name):
                                if(isset($subjects_by_grade[$grade_name])): 
                            ?>
                                <div class="grade-section">
                                    <div class="grade-header" onclick="toggleGrade('<?php echo str_replace(' ', '_', $grade_name); ?>')">
                                        <div class="grade-title">
                                            <i class="fas fa-graduation-cap"></i>
                                            <span><?php echo $grade_name; ?></span>
                                            <span class="subject-count">(<?php echo count($subjects_by_grade[$grade_name]); ?> subjects)</span>
                                        </div>
                                        <i class="fas fa-chevron-down toggle-icon" id="icon_<?php echo str_replace(' ', '_', $grade_name); ?>"></i>
                                    </div>
                                    <div class="grade-content" id="content_<?php echo str_replace(' ', '_', $grade_name); ?>">
                                        <div class="subject-list">
                                            <?php foreach($subjects_by_grade[$grade_name] as $subject): ?>
                                                <div class="subject-item">
                                                    <div class="subject-info">
                                                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                                    </div>
                                                    <span class="badge subject-badge"><?php echo htmlspecialchars($grade_name); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-book"></i>
                            <p>No subjects found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Dashboard JS -->
    <script src="js/dashboard.js"></script>
    
    <script>
        // Toggle grade section function
        function toggleGrade(gradeId) {
            const content = document.getElementById('content_' + gradeId);
            const icon = document.getElementById('icon_' + gradeId);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.classList.remove('rotated');
            } else {
                content.classList.add('active');
                icon.classList.add('rotated');
            }
        }
        
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