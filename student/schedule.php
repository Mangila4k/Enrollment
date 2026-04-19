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
$success_message = '';
$error_message = '';

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get student's enrollment information with section
$enrollment_query = "
    SELECT e.*, g.grade_name, s.section_name, s.id as section_id, 
           u.fullname as adviser_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

// Check if student has a section
$has_section = ($enrollment && isset($enrollment['section_id']) && $enrollment['section_id']);
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$section_name = $enrollment ? ($enrollment['section_name'] ?? 'Not Assigned') : 'Not Assigned';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$adviser_name = $enrollment ? ($enrollment['adviser_name'] ?? 'Not Assigned') : 'Not Assigned';
$school_year = $enrollment ? $enrollment['school_year'] : date('Y') . '-' . (date('Y') + 1);

// Get class schedule for student's section
$weekly_schedule = [];
if($has_section) {
    $schedule_query = "
        SELECT 
            cs.*,
            sub.subject_name,
            u.fullname as teacher_name,
            d.day_name,
            d.day_order,
            ts.start_time,
            ts.end_time,
            ts.id as time_slot_id
        FROM class_schedules cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN users u ON cs.teacher_id = u.id
        JOIN days_of_week d ON cs.day_id = d.id
        JOIN time_slots ts ON cs.time_slot_id = ts.id
        WHERE cs.section_id = :section_id AND cs.status = 'active'
        ORDER BY d.day_order, ts.start_time
    ";
    
    $stmt = $conn->prepare($schedule_query);
    $stmt->bindParam(':section_id', $enrollment['section_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Organize schedule by day
    while($class = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = $class['day_name'];
        $time_slot_id = $class['time_slot_id'];
        
        if(!isset($weekly_schedule[$day])) {
            $weekly_schedule[$day] = [];
        }
        
        $weekly_schedule[$day][$time_slot_id] = [
            'id' => $class['id'],
            'subject' => $class['subject_name'],
            'teacher' => $class['teacher_name'],
            'teacher_id' => $class['teacher_id'],
            'room' => $class['room'] ?? 'TBA',
            'start_time' => $class['start_time'],
            'end_time' => $class['end_time']
        ];
    }
    $stmt = null;
}

// Get subjects for student's grade level
$subjects_list = [];
if($grade_id = ($enrollment ? $enrollment['grade_id'] : null)) {
    $subjects_query = "
        SELECT * FROM subjects 
        WHERE grade_id = :grade_id 
        ORDER BY subject_name
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bindParam(':grade_id', $grade_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while($subject = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subjects_list[] = $subject;
    }
    $stmt = null;
}

// Get all time slots
$time_slots = [];
$time_slots_query = "SELECT * FROM time_slots ORDER BY start_time";
$time_slots_result = $conn->query($time_slots_query);
if($time_slots_result) {
    while($slot = $time_slots_result->fetch(PDO::FETCH_ASSOC)) {
        $time_slots[$slot['id']] = [
            'time' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])),
            'start' => $slot['start_time'],
            'end' => $slot['end_time'],
            'name' => $slot['slot_name']
        ];
    }
}

// Days of the week in order
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Get current week dates
$today = new DateTime();
$start_of_week = clone $today;
$start_of_week->modify('monday this week');
$week_dates = [];
foreach($days_order as $index => $day) {
    $date = clone $start_of_week;
    $date->modify("+" . $index . " days");
    $week_dates[$day] = $date->format('M d, Y');
}

// Get today's day name
$today_name = date('l');

// Calculate statistics
$total_classes = 0;
$unique_subjects = [];
$unique_teachers = [];
foreach($weekly_schedule as $day => $classes) {
    $total_classes += count($classes);
    foreach($classes as $class) {
        $unique_subjects[$class['subject']] = true;
        $unique_teachers[$class['teacher_id']] = true;
    }
}
$total_subjects = count($unique_subjects);
$total_teachers = count($unique_teachers);

// Get today's classes
$today_classes = isset($weekly_schedule[$today_name]) ? $weekly_schedule[$today_name] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Schedule CSS -->
    <link rel="stylesheet" href="css/schedule.css">
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
                        <div class="avatar-initial"><?php echo isset($student_name) ? strtoupper(substr($student_name, 0, 1)) : 'S'; ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo isset($student_name) ? htmlspecialchars(explode(' ', $student_name)[0]) : 'Student'; ?></div>
                <div class="student-role"><i class="fas fa-user-graduate"></i> Student</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php" class="active"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php"><i class="fas fa-history"></i> Enrollment History</a></li>
                    </ul>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <h1>My Class Schedule</h1>
                    <p><i class="fas fa-info-circle"></i> View your weekly class schedule and subjects</p>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($success_message) && $success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if(isset($error_message) && $error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if(!$enrollment): ?>
                <!-- No Enrollment Message -->
                <div class="no-data">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You are not currently enrolled. Please contact the registrar's office.</p>
                    <a href="enrollment.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">Enroll Now</a>
                </div>
            <?php elseif(!$has_section): ?>
                <!-- No Section Assigned -->
                <div class="no-data">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Section Assigned</h3>
                    <p>You are enrolled but not yet assigned to a section. Please contact the registrar's office.</p>
                    <div class="info-badge">
                        <strong>Grade Level:</strong> <?php echo htmlspecialchars($grade_name); ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Section Information Card -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="section-title">
                            <h2><?php echo htmlspecialchars($section_name); ?></h2>
                            <p><?php echo htmlspecialchars($grade_name); ?></p>
                        </div>
                    </div>
                    <div class="section-details">
                        <span class="detail-item">
                            <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($adviser_name); ?>
                        </span>
                        <span class="detail-item">
                            <i class="fas fa-calendar"></i> School Year: <?php echo htmlspecialchars($school_year); ?>
                        </span>
                        <?php if($strand != 'N/A'): ?>
                        <span class="detail-item">
                            <i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($strand); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-content">
                            <h3>Total Subjects</h3>
                            <div class="stat-number"><?php echo $total_subjects; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <h3>Class Hours/Week</h3>
                            <div class="stat-number"><?php echo $total_classes; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <h3>Teachers</h3>
                            <div class="stat-number"><?php echo $total_teachers; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="week-nav">
                    <div class="week-display">
                        <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
                        <span class="week-range">
                            <?php echo $week_dates['Monday']; ?> - <?php echo $week_dates['Friday']; ?>
                        </span>
                    </div>
                    <span class="today-badge">
                        <i class="fas fa-sun"></i> Today is <?php echo $today_name; ?>
                    </span>
                </div>

                <!-- Today's Classes -->
                <div class="today-card">
                    <div class="today-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="today-info">
                        <h4>Today's Classes (<?php echo $today_name; ?>)</h4>
                        <div class="today-classes">
                            <?php 
                            if(!empty($today_classes)) {
                                foreach($today_classes as $slot_id => $class) {
                                    echo '<div class="today-class-item">';
                                    echo '<i class="fas fa-book-open"></i> ' . htmlspecialchars($class['subject']);
                                    echo '<span class="time">' . (isset($time_slots[$slot_id]) ? $time_slots[$slot_id]['time'] : '') . '</span>';
                                    echo '<span class="room">Room: ' . htmlspecialchars($class['room']) . '</span>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="today-class-item">No classes today 🎉</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Schedule Table -->
                <div class="schedule-container">
                    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-table" style="color: var(--primary);"></i> 
                        Class Schedule - <?php echo htmlspecialchars($section_name); ?>
                    </h3>

                    <?php if(empty($weekly_schedule)): ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Schedule Yet</h3>
                            <p>No classes have been scheduled for your section.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <?php foreach($days_order as $day): ?>
                                            <th class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                                <?php echo $day; ?>
                                                <small><?php echo $week_dates[$day]; ?></small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($time_slots as $slot_id => $slot): ?>
                                        <tr>
                                            <td class="time-column">
                                                <strong><?php echo $slot['time']; ?></strong>
                                                <?php if($slot['name']): ?>
                                                    <br><span class="slot-name"><?php echo $slot['name']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach($days_order as $day): ?>
                                                <td class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                                    <?php if(isset($weekly_schedule[$day][$slot_id])): ?>
                                                        <?php $class = $weekly_schedule[$day][$slot_id]; ?>
                                                        <div class="class-item">
                                                            <div class="subject-name"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                            <div class="teacher-name">
                                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher']); ?>
                                                            </div>
                                                            <div class="room">
                                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="empty-cell">—</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Subjects -->
                <div class="subjects-card">
                    <h4><i class="fas fa-book-open"></i> My Subjects (<?php echo count($subjects_list); ?>)</h4>
                    <div class="subject-tags">
                        <?php foreach($subjects_list as $subject): ?>
                            <span class="subject-tag">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Schedule JS -->
    <script src="js/schedule.js"></script>
</body>
</html>