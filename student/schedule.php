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
$success_message = '';
$error_message = '';

// Get student profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
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

// Get student's enrollment info
$enrollment_query = "
    SELECT e.*, g.grade_name, s.section_name, s.id as section_id
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.section_id = s.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.id DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->execute([':student_id' => $student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

$section_id = $enrollment ? $enrollment['section_id'] : null;
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$section_name = $enrollment ? $enrollment['section_name'] : 'Not Assigned';
$school_year = $enrollment ? $enrollment['school_year'] : date('Y') . '-' . (date('Y') + 1);

// Get student's class schedule based on section
$schedules_query = "
    SELECT 
        cs.*,
        s.section_name,
        g.grade_name,
        sub.subject_name,
        d.day_name,
        d.day_order,
        ts.start_time,
        ts.end_time,
        ts.slot_name,
        u.fullname as teacher_name,
        u.profile_picture as teacher_profile_pic
    FROM class_schedules cs
    JOIN sections s ON cs.section_id = s.id
    JOIN grade_levels g ON s.grade_id = g.id
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    JOIN users u ON cs.teacher_id = u.id
    WHERE cs.section_id = :section_id AND cs.status = 'active'
    ORDER BY d.day_order, ts.start_time
";

$stmt = $conn->prepare($schedules_query);
$stmt->execute([':section_id' => $section_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize schedules by day for easy display
$weekly_schedule = [];
$total_hours = 0;
$unique_subjects = [];
$unique_teachers = [];

if(!empty($schedules)) {
    foreach($schedules as $class) {
        $day = $class['day_name'];
        $time_slot_id = $class['time_slot_id'];
        
        // Organize by day
        if(!isset($weekly_schedule[$day])) {
            $weekly_schedule[$day] = [];
        }
        $weekly_schedule[$day][$time_slot_id] = $class;
        
        // Calculate total hours
        $start = new DateTime($class['start_time']);
        $end = new DateTime($class['end_time']);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->i / 60);
        $total_hours += $hours;
        
        // Track unique subjects
        $unique_subjects[$class['subject_id']] = $class['subject_name'];
        
        // Track unique teachers
        $unique_teachers[$class['teacher_id']] = $class['teacher_name'];
    }
}

// Days of the week in order
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Time slots for the table header
$time_slots_query = "SELECT * FROM time_slots ORDER BY start_time";
$time_slots = $conn->query($time_slots_query);
$time_slots_list = $time_slots ? $time_slots->fetchAll(PDO::FETCH_ASSOC) : [];

// Get current week dates
$today = new DateTime();
$start_of_week = clone $today;
$start_of_week->modify('monday this week');
$week_dates = [];
foreach($days_order as $day) {
    $date = clone $start_of_week;
    $date->modify("+" . array_search($day, $days_order) . " days");
    $week_dates[$day] = $date->format('M d, Y');
}

// Check if today is a weekday
$today_name = $today->format('l');
$is_weekday = in_array($today_name, $days_order);

// Get today's classes
$today_classes = [];
if($is_weekday && isset($weekly_schedule[$today_name])) {
    $today_classes = $weekly_schedule[$today_name];
}

// Calculate statistics
$total_classes = count($schedules);
$total_subjects = count($unique_subjects);
$total_teachers = count($unique_teachers);
$total_periods = count($time_slots_list) * 5; // 5 days
$free_periods = $total_periods - $total_classes;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student Dashboard | PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Schedule CSS -->
    <link rel="stylesheet" href="css/schedule.css">
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

            <div class="student-profile">
                <div class="student-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></div>
                <div class="student-role"><i class="fas fa-user-graduate"></i> Student</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php" class="active"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>My Class Schedule</h1>
                    <p>View your weekly class schedule</p>
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

            <!-- Enrollment Info Card -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="section-title">
                        <h2><?php echo htmlspecialchars($section_name); ?> Section</h2>
                        <p><?php echo htmlspecialchars($grade_name); ?> • School Year <?php echo htmlspecialchars($school_year); ?></p>
                    </div>
                </div>
                <div class="section-details">
                    <div class="detail-item">
                        <i class="fas fa-calendar-week"></i>
                        <span>Regular Schedule</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $total_classes; ?> Classes per week</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-book"></i>
                        <span><?php echo $total_subjects; ?> Subjects</span>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Classes</h3>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Classes per week</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Enrolled subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Teachers</h3>
                        <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_teachers; ?></div>
                    <div class="stat-label">Different teachers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Free Periods</h3>
                        <div class="stat-icon"><i class="fas fa-coffee"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $free_periods; ?></div>
                    <div class="stat-label">Free time slots</div>
                </div>
            </div>

            <!-- Today's Classes Card -->
            <?php if($is_weekday && !empty($today_classes)): ?>
            <div class="today-card">
                <div class="today-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="today-info">
                    <h4><i class="fas fa-clock"></i> Today's Classes (<?php echo $today_name; ?>)</h4>
                    <div class="today-classes">
                        <?php 
                        $today_classes_sorted = $today_classes;
                        ksort($today_classes_sorted);
                        foreach($today_classes_sorted as $class): 
                        ?>
                            <div class="today-class-item">
                                <i class="fas fa-book"></i>
                                <span><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                <span class="time">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('h:i A', strtotime($class['start_time'])); ?>
                                </span>
                                <span class="room">
                                    <i class="fas fa-door-open"></i> 
                                    <?php echo htmlspecialchars($class['room']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Schedule Container -->
            <div class="schedule-container">
                <div class="schedule-header">
                    <h3><i class="fas fa-table"></i> Weekly Class Schedule</h3>
                    <span class="schedule-info-badge">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo $total_classes; ?> classes scheduled
                    </span>
                </div>

                <!-- Schedule Table -->
                <div class="table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th class="time-column">Time</th>
                                <?php foreach($days_order as $day): ?>
                                    <th class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                        <?php echo $day; ?><br>
                                        <small><?php echo $week_dates[$day]; ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($time_slots_list)): ?>
                                <?php foreach($time_slots_list as $slot): ?>
                                    <tr>
                                        <td class="time-column">
                                            <strong><?php echo date('h:i A', strtotime($slot['start_time'])); ?></strong>
                                            <small class="slot-name"><?php echo htmlspecialchars($slot['slot_name'] ?? ''); ?></small>
                                            <br>
                                            <span><?php echo date('h:i A', strtotime($slot['end_time'])); ?></span>
                                        </td>
                                        <?php foreach($days_order as $day): ?>
                                            <td class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                                <?php 
                                                $class_found = false;
                                                if(isset($weekly_schedule[$day][$slot['id']])): 
                                                    $class = $weekly_schedule[$day][$slot['id']];
                                                    $class_found = true;
                                                ?>
                                                    <div class="class-item">
                                                        <div class="subject-name">
                                                            <?php echo htmlspecialchars($class['subject_name']); ?>
                                                        </div>
                                                        <div class="teacher-name">
                                                            <i class="fas fa-chalkboard-user"></i>
                                                            <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                        </div>
                                                        <?php if($class['room']): ?>
                                                            <div class="room">
                                                                <i class="fas fa-door-open"></i>
                                                                <?php echo htmlspecialchars($class['room']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if(!$class_found): ?>
                                                    <div class="empty-cell">
                                                        <i class="fas fa-minus-circle"></i>
                                                        <span>Free</span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data-cell">
                                        <div class="no-data">
                                            <i class="fas fa-clock"></i>
                                            <h3>No Schedule Available</h3>
                                            <p>Your class schedule has not been set up yet.</p>
                                            <p>Please contact the registrar's office for assistance.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color class"></div>
                        <span>Regular Class</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color free"></div>
                        <span>Free Period / No Class</span>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <!-- Your Subjects -->
                <div class="summary-card">
                    <h4>
                        <i class="fas fa-book"></i> Your Subjects
                        <span class="badge-count"><?php echo $total_subjects; ?></span>
                    </h4>
                    <div class="summary-content">
                        <?php if(!empty($unique_subjects)): ?>
                            <?php foreach($unique_subjects as $subject): ?>
                                <span class="summary-tag">
                                    <i class="fas fa-book-open"></i>
                                    <?php echo htmlspecialchars($subject); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i> No subjects assigned
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Your Teachers -->
                <div class="summary-card">
                    <h4>
                        <i class="fas fa-chalkboard-user"></i> Your Teachers
                        <span class="badge-count"><?php echo $total_teachers; ?></span>
                    </h4>
                    <div class="summary-content">
                        <?php if(!empty($unique_teachers)): ?>
                            <?php foreach($unique_teachers as $teacher): ?>
                                <span class="summary-tag">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($teacher); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i> No teachers assigned
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section Information -->
                <div class="summary-card">
                    <h4>
                        <i class="fas fa-users"></i> Section Information
                        <span class="badge-count"><?php echo htmlspecialchars($section_name); ?></span>
                    </h4>
                    <div class="summary-content">
                        <div class="info-row">
                            <i class="fas fa-layer-group"></i>
                            <strong>Grade Level:</strong> <?php echo htmlspecialchars($grade_name); ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-calendar-alt"></i>
                            <strong>School Year:</strong> <?php echo htmlspecialchars($school_year); ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-clock"></i>
                            <strong>Total Classes:</strong> <?php echo $total_classes; ?> per week
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule JS -->
    <script src="js/schedule.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const scheduleData = {
            totalClasses: <?php echo $total_classes; ?>,
            totalSubjects: <?php echo $total_subjects; ?>,
            totalTeachers: <?php echo $total_teachers; ?>,
            freePeriods: <?php echo $free_periods; ?>
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
    
    <?php include('../includes/chatbot_widget_student.php'); ?>
</body>
</html>