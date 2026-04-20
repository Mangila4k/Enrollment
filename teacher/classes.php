<?php
session_start();
require_once("../config/database.php");

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

// Get teacher's sections (where they are adviser)
$sections_query = "
    SELECT s.*, 
           g.grade_name,
           (SELECT COUNT(*) FROM users u 
            JOIN enrollments e ON u.id = e.student_id 
            WHERE e.grade_id = s.grade_id AND e.status = 'Enrolled') as student_count
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = :teacher_id
    ORDER BY g.id, s.section_name
";

$stmt = $conn->prepare($sections_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher's subjects from class_schedules (since teacher_subjects doesn't exist)
$subjects_query = "
    SELECT DISTINCT sub.*, 
           g.grade_name
    FROM class_schedules cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN grade_levels g ON sub.grade_id = g.id
    WHERE cs.teacher_id = :teacher_id AND cs.status = 'active'
    ORDER BY g.id, sub.subject_name
";

$subjects_stmt = $conn->prepare($subjects_query);
$subjects_stmt->execute([':teacher_id' => $teacher_id]);
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no subjects found in class_schedules, try to get from sections (adviser subjects)
if(empty($subjects)) {
    $subjects_query = "
        SELECT DISTINCT sub.*, 
               g.grade_name
        FROM sections s
        JOIN grade_levels g ON s.grade_id = g.id
        JOIN subjects sub ON sub.grade_id = g.id
        WHERE s.adviser_id = :teacher_id
        ORDER BY g.id, sub.subject_name
    ";
    $subjects_stmt = $conn->prepare($subjects_query);
    $subjects_stmt->execute([':teacher_id' => $teacher_id]);
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Group subjects by grade level
$subjects_by_grade = [];
foreach($subjects as $subject) {
    $grade_name = $subject['grade_name'];
    if(!isset($subjects_by_grade[$grade_name])) {
        $subjects_by_grade[$grade_name] = [];
    }
    $subjects_by_grade[$grade_name][] = $subject;
}

// Get teacher's class schedule from database with proper joins
$schedule_query = "
    SELECT cs.*, 
           s.section_name, 
           g.grade_name,
           sub.subject_name,
           d.day_name,
           ts.start_time,
           ts.end_time,
           ts.slot_name
    FROM class_schedules cs
    JOIN sections s ON cs.section_id = s.id
    JOIN grade_levels g ON s.grade_id = g.id
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.teacher_id = :teacher_id AND cs.status = 'active'
    ORDER BY d.day_order, ts.start_time
";

$stmt = $conn->prepare($schedule_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize schedule by day
$schedule_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
];

foreach($schedules as $schedule) {
    $day = $schedule['day_name'];
    if(isset($schedule_by_day[$day])) {
        $schedule_by_day[$day][] = $schedule;
    }
}

// Sort schedule by start time for each day
foreach($schedule_by_day as $day => $day_schedules) {
    usort($schedule_by_day[$day], function($a, $b) {
        return strtotime($a['start_time']) - strtotime($b['start_time']);
    });
}

// Get students per section (for quick view)
$section_students = [];
if(!empty($sections)) {
    foreach($sections as $section) {
        $student_query = "
            SELECT u.id, u.fullname, u.id_number, e.status
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.grade_id = :grade_id AND e.status = 'Enrolled'
            ORDER BY u.fullname
            LIMIT 5
        ";
        $stmt = $conn->prepare($student_query);
        $stmt->execute([':grade_id' => $section['grade_id']]);
        $section_students[$section['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Calculate total students from sections
$total_students = 0;
foreach($sections as $section) {
    $total_students += $section['student_count'];
}

// Get total assigned subjects count
$total_assigned_subjects = count($subjects);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Classes CSS -->
    <link rel="stylesheet" href="css/classes.css">
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>My Classes</h1>
                    <p>Manage your advisory classes and subjects</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Advisory Classes</h3>
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    </div>
                    <div class="stat-number"><?php echo count($sections); ?></div>
                    <div class="stat-label">Sections handled</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_assigned_subjects; ?></div>
                    <div class="stat-label">Subjects taught</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Under your advisory</div>
                </div>
            </div>

            <!-- Section Tabs -->
            <div class="section-tabs">
                <button class="tab-btn active" data-tab="advisory">
                    <i class="fas fa-star"></i> Advisory Classes
                </button>
                <button class="tab-btn" data-tab="subjects">
                    <i class="fas fa-book"></i> My Subjects
                </button>
                <button class="tab-btn" data-tab="schedule">
                    <i class="fas fa-clock"></i> Schedule
                </button>
            </div>

            <!-- Advisory Classes Tab -->
            <div id="advisory" class="tab-content active-tab">
                <div class="classes-grid">
                    <?php if(!empty($sections)): ?>
                        <?php foreach($sections as $section): ?>
                            <div class="class-card">
                                <div class="class-header">
                                    <h3>
                                        <i class="fas fa-users"></i>
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </h3>
                                    <span class="class-badge">Adviser</span>
                                </div>
                                <div class="class-body">
                                    <div class="class-info">
                                        <div class="class-info-item">
                                            <div class="class-info-value"><?php echo htmlspecialchars($section['grade_name']); ?></div>
                                            <div class="class-info-label">Grade Level</div>
                                        </div>
                                        <div class="class-info-item">
                                            <div class="class-info-value"><?php echo $section['student_count']; ?></div>
                                            <div class="class-info-label">Students</div>
                                        </div>
                                    </div>

                                    <div class="student-list">
                                        <h4>Recent Students</h4>
                                        <?php if(!empty($section_students[$section['id']])): ?>
                                            <?php foreach($section_students[$section['id']] as $student): ?>
                                                <div class="student-item">
                                                    <div class="student-avatar">
                                                        <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                    </div>
                                                    <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                                                    <div class="student-id"><?php echo $student['id_number'] ?? 'N/A'; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-all-link">
                                                View Subjects <i class="fas fa-arrow-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <p class="no-students">No students enrolled yet</p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="class-actions">
                                        <a href="grades.php?section=<?php echo $section['id']; ?>" class="class-action-btn btn-grades">
                                            <i class="fas fa-star"></i> Grades
                                        </a>
                                        <a href="view_section.php?id=<?php echo $section['id']; ?>" class="class-action-btn btn-students">
                                            <i class="fas fa-users"></i> Section
                                        </a>
                                        <a href="schedule.php?section=<?php echo $section['id']; ?>" class="class-action-btn btn-schedule">
                                            <i class="fas fa-clock"></i> Schedule
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-users"></i>
                            <h3>No Advisory Classes</h3>
                            <p>You are not assigned as adviser to any section yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subjects Tab - Accordion by Grade Level -->
            <div id="subjects" class="tab-content">
                <?php if(!empty($subjects_by_grade)): ?>
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
                                                    <p>
                                                        <span><i class="fas fa-layer-group"></i> Grade: <?php echo htmlspecialchars($subject['grade_name']); ?></span>
                                                    </p>
                                                </div>
                                                <div class="subject-actions">
                                                    <a href="grades.php?subject=<?php echo $subject['id']; ?>" class="subject-action-btn grades">
                                                        <i class="fas fa-star"></i> Grades
                                                    </a>
                                                </div>
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
                        <h3>No Subjects Assigned</h3>
                        <p>You are not assigned to teach any subjects yet.</p>
                        <p style="font-size: 13px; margin-top: 10px;">Subjects will appear here once you have class schedules or advisory sections.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Schedule Tab - Dynamic from Database -->
            <div id="schedule" class="tab-content">
                <div class="schedule-card">
                    <h3><i class="fas fa-calendar-alt"></i> Weekly Schedule</h3>
                    
                    <?php if(!empty($schedules)): ?>
                        <div class="schedule-grid">
                            <?php foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                                <div class="day-column">
                                    <div class="day-header"><?php echo $day; ?></div>
                                    
                                    <?php 
                                    $day_schedules = $schedule_by_day[$day] ?? [];
                                    
                                    if(!empty($day_schedules)):
                                        foreach($day_schedules as $schedule):
                                    ?>
                                        <div class="time-slot">
                                            <div class="time">
                                                <?php 
                                                echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                                     date('h:i A', strtotime($schedule['end_time']));
                                                ?>
                                            </div>
                                            <div class="class-name">
                                                <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                            </div>
                                            <div class="section-name">
                                                <i class="fas fa-users"></i> <?php echo htmlspecialchars($schedule['section_name']); ?>
                                            </div>
                                            <div class="grade-name">
                                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($schedule['grade_name']); ?>
                                            </div>
                                            <?php if(!empty($schedule['room'])): ?>
                                            <div class="room-name">
                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($schedule['room']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <div class="empty-slot">No classes scheduled</div>
                                    <?php 
                                    endif; 
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>No Schedule Found</h3>
                            <p>You don't have any class schedule assigned yet.</p>
                            <p style="font-size: 13px; margin-top: 10px;">Please contact the administrator to set up your teaching schedule.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Classes JS -->
    <script src="js/classes.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const classesData = {
            totalSections: <?php echo count($sections); ?>,
            totalSubjects: <?php echo $total_assigned_subjects; ?>,
            totalStudents: <?php echo $total_students; ?>
        };
        
        // Toggle grade section function for subjects accordion
        function toggleGrade(gradeId) {
            const content = document.getElementById('content_' + gradeId);
            const icon = document.getElementById('icon_' + gradeId);
            
            if (content && icon) {
                if (content.classList.contains('active')) {
                    content.classList.remove('active');
                    icon.classList.remove('rotated');
                } else {
                    content.classList.add('active');
                    icon.classList.add('rotated');
                }
            }
        }
        
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    tabBtns.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active-tab'));
                    
                    // Add active class to current tab
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active-tab');
                });
            });
        });
        
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