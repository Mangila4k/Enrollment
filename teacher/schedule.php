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

// Get teacher's assigned schedules from class_schedules table
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

$stmt = $conn->prepare($schedules_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$assigned_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize schedules by day for easy display
$weekly_schedule = [];
$total_hours = 0;
$unique_sections = [];
$unique_subjects = [];

if(!empty($assigned_schedules)) {
    foreach($assigned_schedules as $class) {
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
        
        // Track unique sections
        $unique_sections[$class['section_id']] = $class['section_name'] . ' - ' . $class['grade_name'];
        
        // Track unique subjects
        $unique_subjects[$class['subject_id']] = $class['subject_name'];
    }
}

// Get teacher's advisory sections
$advisory_query = "
    SELECT s.*, g.grade_name
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = :teacher_id
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($advisory_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$advisory_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Calculate statistics
$total_classes = count($assigned_schedules);
$total_sections = count($unique_sections);
$total_subjects = count($unique_subjects);
$free_periods = 40 - $total_classes; // Assuming 40 total periods in a week (8 periods x 5 days)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Teacher Dashboard | PNHS</title>
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
                        <li><a href="classes.php"><i class="fas fa-users"></i> My Classes</a></li>
                        <li><a href="schedule.php" class="active"><i class="fas fa-clock"></i> Schedule</a></li>
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
                    <h1>My Schedule</h1>
                    <p>View your weekly class schedule assigned by the admin</p>
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
                        <h3>Total Classes</h3>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Classes per week</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Sections</h3>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Different sections</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects taught</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Free Periods</h3>
                        <div class="stat-icon"><i class="fas fa-coffee"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $free_periods; ?></div>
                    <div class="stat-label">Available slots</div>
                </div>
            </div>

            <!-- Week Navigation -->
            <div class="week-nav">
                <div class="week-display">
                    <h3><i class="fas fa-calendar-week"></i> Week Schedule</h3>
                    <span class="week-range">
                        <?php echo $week_dates['Monday']; ?> - <?php echo $week_dates['Friday']; ?>
                    </span>
                </div>
                <div class="nav-buttons">
                    <a href="#" class="nav-btn" onclick="changeWeek(-1)">
                        <i class="fas fa-chevron-left"></i> Previous Week
                    </a>
                    <a href="#" class="nav-btn" onclick="changeWeek(1)">
                        Next Week <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <!-- Schedule Container -->
            <div class="schedule-container">
                <div class="schedule-header">
                    <h3><i class="fas fa-table"></i> Your Assigned Schedule</h3>
                    <span class="schedule-info-badge">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo $total_classes; ?> classes scheduled
                    </span>
                </div>

                <!-- Schedule Table -->
                <div class="table-container">
                    <table class="data-table schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <?php foreach($days_order as $day): ?>
                                    <th>
                                        <?php echo $day; ?><br>
                                        <span class="date-sub"><?php echo $week_dates[$day]; ?></span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($time_slots_list)): ?>
                                <?php foreach($time_slots_list as $slot): ?>
                                    <tr>
                                        <td class="time-column">
                                            <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                         </div>
                                        <?php foreach($days_order as $day): ?>
                                            <td>
                                                <div class="schedule-cell">
                                                    <?php 
                                                    $class_found = false;
                                                    if(isset($weekly_schedule[$day][$slot['id']])): 
                                                        $class = $weekly_schedule[$day][$slot['id']];
                                                        $class_found = true;
                                                        $is_advisory = false;
                                                        foreach($advisory_sections as $advisory) {
                                                            if($advisory['id'] == $class['section_id']) {
                                                                $is_advisory = true;
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                        <div class="class-item <?php echo $is_advisory ? 'advisory' : ''; ?>">
                                                            <div class="section-name">
                                                                <i class="fas fa-users"></i> 
                                                                <?php echo htmlspecialchars($class['section_name']); ?>
                                                            </div>
                                                            <div class="subject-name">
                                                                <i class="fas fa-book-open"></i>
                                                                <?php echo htmlspecialchars($class['subject_name']); ?>
                                                            </div>
                                                            <div class="grade-name">
                                                                <i class="fas fa-graduation-cap"></i>
                                                                <?php echo htmlspecialchars($class['grade_name']); ?>
                                                            </div>
                                                            <?php if($class['room']): ?>
                                                                <div class="room-badge">
                                                                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if(!$class_found): ?>
                                                        <div class="empty-cell">
                                                            <i class="fas fa-minus-circle"></i> Free
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                             </div>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data-cell">
                                        <div class="no-data">
                                            <i class="fas fa-clock"></i>
                                            <h3>No time slots configured</h3>
                                            <p>Please contact the administrator.</p>
                                        </div>
                                     </div>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                     </div>
                </div>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color class"></div>
                        <span>Regular Class</span>
                    </div>
                    <?php if(!empty($advisory_sections)): ?>
                    <div class="legend-item">
                        <div class="legend-color advisory"></div>
                        <span>Advisory Class (You are the adviser)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-grid">
                <!-- Your Sections -->
                <div class="summary-card">
                    <h4>
                        <i class="fas fa-users"></i> Your Sections
                        <span class="badge-count"><?php echo $total_sections; ?></span>
                    </h4>
                    <div class="summary-content">
                        <?php if(!empty($unique_sections)): ?>
                            <?php foreach($unique_sections as $section): ?>
                                <span class="summary-tag">
                                    <i class="fas fa-layer-group"></i>
                                    <?php echo htmlspecialchars($section); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i> No sections assigned
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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

                <!-- Advisory Classes -->
                <div class="summary-card">
                    <h4>
                        <i class="fas fa-star" style="color: #FFD700;"></i> Advisory Classes
                        <span class="badge-count"><?php echo count($advisory_sections); ?></span>
                    </h4>
                    <div class="summary-content">
                        <?php if(!empty($advisory_sections)): ?>
                            <?php foreach($advisory_sections as $section): ?>
                                <span class="summary-tag advisory">
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['grade_name']); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-message">
                                <i class="fas fa-info-circle"></i> No advisory classes
                            </div>
                        <?php endif; ?>
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
            totalSections: <?php echo $total_sections; ?>,
            totalSubjects: <?php echo $total_subjects; ?>,
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
        
        // Week navigation function
        function changeWeek(direction) {
            alert('Week navigation feature coming soon!');
        }
    </script>
    
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>