<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$admin_id = $_SESSION['user']['id'];
$section_id = $_GET['section_id'] ?? 0;
$success_message = '';
$error_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Get section details
$stmt = $conn->prepare("
    SELECT s.*, g.grade_name, u.fullname as adviser_name 
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id 
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$section_id]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$section) {
    header("Location: sections.php");
    exit();
}

// Auto-detect current school year
$current_year = date('Y');
$next_year = $current_year + 1;
$current_school_year = $current_year . '-' . $next_year;

// Handle delete schedule
if(isset($_GET['delete_schedule'])) {
    $schedule_id = $_GET['delete_schedule'];
    $delete = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
    $delete->execute([$schedule_id]);
    if($delete->rowCount() > 0) {
        $success_message = "Schedule deleted successfully!";
    } else {
        $error_message = "Error deleting schedule.";
    }
}

// Handle form submission for adding schedule
if(isset($_POST['add_schedule'])) {
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    $day_id = $_POST['day_id'];
    $time_slot_id = $_POST['time_slot_id'];
    $room = trim($_POST['room']);
    $school_year = $_POST['school_year'];
    $quarter = $_POST['quarter'];

    // Check if subject already assigned to this section on the SAME DAY
    $subject_check = $conn->prepare("
        SELECT COUNT(*) FROM class_schedules 
        WHERE section_id = ? AND subject_id = ? AND day_id = ? AND school_year = ? AND quarter = ?
    ");
    $subject_check->execute([$section_id, $subject_id, $day_id, $school_year, $quarter]);
    if($subject_check->fetchColumn() > 0) {
        $error_message = "This subject is already assigned to this section on the same day!";
    }
    // Check for teacher conflict
    else {
        $teacher_conflict = $conn->prepare("
            SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, s.section_name
            FROM class_schedules cs
            JOIN days_of_week d ON cs.day_id = d.id
            JOIN time_slots ts ON cs.time_slot_id = ts.id
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN sections s ON cs.section_id = s.id
            WHERE cs.teacher_id = ? 
            AND cs.day_id = ? 
            AND cs.time_slot_id = ?
            AND cs.school_year = ?
            AND cs.quarter = ?
        ");
        $teacher_conflict->execute([$teacher_id, $day_id, $time_slot_id, $school_year, $quarter]);

        if($teacher_conflict->rowCount() > 0) {
            $conflict = $teacher_conflict->fetch(PDO::FETCH_ASSOC);
            $error_message = "Teacher conflict! This teacher is already teaching {$conflict['subject_name']} for {$conflict['section_name']} on {$conflict['day_name']} at " . date('h:i A', strtotime($conflict['start_time']));
        } else {
            // Check for room conflict
            $room_conflict = $conn->prepare("
                SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, s.section_name
                FROM class_schedules cs
                JOIN days_of_week d ON cs.day_id = d.id
                JOIN time_slots ts ON cs.time_slot_id = ts.id
                JOIN subjects sub ON cs.subject_id = sub.id
                JOIN sections s ON cs.section_id = s.id
                WHERE cs.room = ? 
                AND cs.day_id = ? 
                AND cs.time_slot_id = ?
                AND cs.school_year = ?
                AND cs.quarter = ?
                AND cs.room IS NOT NULL 
                AND cs.room != ''
            ");
            $room_conflict->execute([$room, $day_id, $time_slot_id, $school_year, $quarter]);

            if($room_conflict->rowCount() > 0 && !empty($room)) {
                $conflict = $room_conflict->fetch(PDO::FETCH_ASSOC);
                $error_message = "Room conflict! Room $room is already used for {$conflict['subject_name']} on {$conflict['day_name']} at " . date('h:i A', strtotime($conflict['start_time']));
            } else {
                // No conflicts, insert schedule
                $insert = $conn->prepare("
                    INSERT INTO class_schedules (section_id, subject_id, teacher_id, day_id, time_slot_id, room, school_year, quarter, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $insert->execute([$section_id, $subject_id, $teacher_id, $day_id, $time_slot_id, $room, $school_year, $quarter]);
                
                if($insert->rowCount() > 0) {
                    $success_message = "Schedule added successfully!";
                    header("Location: create_schedule.php?section_id=$section_id");
                    exit();
                } else {
                    $error_message = "Error adding schedule.";
                }
            }
        }
    }
}

// Get all subjects for this grade level
$subjects_stmt = $conn->prepare("
    SELECT id, subject_name 
    FROM subjects 
    WHERE grade_id = ? 
    ORDER BY subject_name
");
$subjects_stmt->execute([$section['grade_id']]);
$all_subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers
$teachers_stmt = $conn->prepare("
    SELECT id, fullname 
    FROM users 
    WHERE role = 'Teacher' 
    ORDER BY fullname
");
$teachers_stmt->execute();
$all_teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get days of week
$days_stmt = $conn->query("SELECT * FROM days_of_week ORDER BY day_order");
$days = $days_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get time slots
$time_slots_stmt = $conn->query("SELECT * FROM time_slots ORDER BY start_time");
$time_slots = $time_slots_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing schedules for validation
$existing_schedules_stmt = $conn->prepare("
    SELECT cs.*, d.day_name, ts.start_time, ts.end_time
    FROM class_schedules cs
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.section_id = ? AND cs.school_year = ? AND cs.quarter = 1
");
$existing_schedules_stmt->execute([$section_id, $current_school_year]);
$existing_schedules = $existing_schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create arrays for taken combinations
$taken_teachers_by_day_time = [];
$taken_rooms_by_day_time = [];
$taken_subjects_by_day = [];

foreach($existing_schedules as $sch) {
    $key = $sch['day_id'] . '_' . $sch['time_slot_id'];
    $taken_teachers_by_day_time[$key][] = $sch['teacher_id'];
    if(!empty($sch['room'])) {
        $taken_rooms_by_day_time[$key][] = $sch['room'];
    }
    if(!isset($taken_subjects_by_day[$sch['day_id']])) {
        $taken_subjects_by_day[$sch['day_id']] = [];
    }
    $taken_subjects_by_day[$sch['day_id']][] = $sch['subject_id'];
}

// Get current schedules for display
$schedules_stmt = $conn->prepare("
    SELECT cs.*, sub.subject_name, u.fullname as teacher_name,
           d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_name
    FROM class_schedules cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN users u ON cs.teacher_id = u.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.section_id = ?
    ORDER BY d.day_order, ts.start_time
");
$schedules_stmt->execute([$section_id]);
$schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize schedules by day for weekly view
$weekly_schedule = [];
foreach($schedules as $sch) {
    $weekly_schedule[$sch['day_name']][] = $sch;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Schedule - <?php echo htmlspecialchars($section['section_name']); ?> | PLSNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/create_schedule.css">
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

            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="admin-name"><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></div>
                <div class="admin-role"><i class="fas fa-shield-alt"></i> Administrator</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                        <li><a href="teachers.php"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
                        <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i> Sections</a></li>
                        <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
                        <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">MANAGEMENT</div>
                    <ul class="nav-items">
                        <li><a href="manage_accounts.php"><i class="fas fa-users-cog"></i> Accounts</a></li>
                        <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
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
            <div class="page-header">
                <div>
                    <h1>Class Schedule Management</h1>
                    <p>Create and manage class schedules for <?php echo htmlspecialchars($section['section_name']); ?></p>
                </div>
                <a href="sections.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Sections</a>
            </div>

            <!-- Section Info Card -->
            <div class="section-info-card">
                <div class="section-icon-large">
                    <i class="fas fa-users"></i>
                </div>
                <div class="section-details">
                    <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
                    <p>
                        <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                        <span><i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?></span>
                        <span><i class="fas fa-calendar"></i> School Year: <?php echo $current_school_year; ?></span>
                    </p>
                </div>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Add Schedule Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Schedule</h3>
                        <span class="badge-count"><i class="fas fa-info-circle"></i> Same subject allowed on different days</span>
                    </div>
                    <form method="POST" id="scheduleForm">
                        <div class="form-group">
                            <label>Subject <span>*</span></label>
                            <select name="subject_id" id="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach($all_subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Teacher <span>*</span></label>
                            <select name="teacher_id" id="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php foreach($all_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Day <span>*</span></label>
                            <select name="day_id" id="day_id" required>
                                <option value="">Select Day</option>
                                <?php foreach($days as $day): ?>
                                    <option value="<?php echo $day['id']; ?>"><?php echo $day['day_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Time Slot <span>*</span></label>
                            <select name="time_slot_id" id="time_slot_id" required>
                                <option value="">Select Time</option>
                                <?php foreach($time_slots as $slot): ?>
                                    <option value="<?php echo $slot['id']; ?>">
                                        <?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Room</label>
                            <input type="text" name="room" id="room" placeholder="e.g., Room 101">
                        </div>

                        <div class="form-group">
                            <label>School Year <span>*</span></label>
                            <select name="school_year" required>
                                <option value="<?php echo $current_school_year; ?>" selected><?php echo $current_school_year; ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Quarter</label>
                            <select name="quarter" id="quarter">
                                <option value="1">1st Quarter</option>
                                <option value="2">2nd Quarter</option>
                                <option value="3">3rd Quarter</option>
                                <option value="4">4th Quarter</option>
                            </select>
                        </div>

                        <button type="submit" name="add_schedule" class="btn-primary">
                            <i class="fas fa-save"></i> Add to Schedule
                        </button>
                    </form>

                    <div id="conflictWarning" class="dynamic-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="conflictMessage"></span>
                    </div>

                    <div class="conflict-warning">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Schedule Rules:</strong>
                            <p>✅ Same subject can be scheduled on different days (e.g., Math on Monday and Wednesday)<br>
                            ❌ Teacher cannot have two classes at the same day and time<br>
                            ❌ Room cannot be double-booked at the same day and time</p>
                        </div>
                    </div>
                </div>

                <!-- Current Schedule List - Grouped by Day -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Current Schedule</h3>
                        <span class="badge-count"><?php echo count($schedules); ?> subjects</span>
                    </div>
                    
                    <!-- Day Tabs -->
                    <div class="day-tabs">
                        <button class="day-tab active" data-day="monday">Monday</button>
                        <button class="day-tab" data-day="tuesday">Tuesday</button>
                        <button class="day-tab" data-day="wednesday">Wednesday</button>
                        <button class="day-tab" data-day="thursday">Thursday</button>
                        <button class="day-tab" data-day="friday">Friday</button>
                    </div>
                    
                    <!-- Monday Schedule -->
                    <div class="day-schedule active" id="monday-schedule">
                        <?php
                        $monday_schedules = array_filter($schedules, function($sch) {
                            return $sch['day_name'] == 'Monday';
                        });
                        ?>
                        <?php if(count($monday_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach($monday_schedules as $sch): ?>
                                    <div class="schedule-item monday">
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                            <p>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                                <?php if($sch['room']): ?>
                                                    <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                           class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-day"></i>
                                <p>No classes scheduled on Monday</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tuesday Schedule -->
                    <div class="day-schedule" id="tuesday-schedule">
                        <?php
                        $tuesday_schedules = array_filter($schedules, function($sch) {
                            return $sch['day_name'] == 'Tuesday';
                        });
                        ?>
                        <?php if(count($tuesday_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach($tuesday_schedules as $sch): ?>
                                    <div class="schedule-item tuesday">
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                            <p>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                                <?php if($sch['room']): ?>
                                                    <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                           class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-day"></i>
                                <p>No classes scheduled on Tuesday</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Wednesday Schedule -->
                    <div class="day-schedule" id="wednesday-schedule">
                        <?php
                        $wednesday_schedules = array_filter($schedules, function($sch) {
                            return $sch['day_name'] == 'Wednesday';
                        });
                        ?>
                        <?php if(count($wednesday_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach($wednesday_schedules as $sch): ?>
                                    <div class="schedule-item wednesday">
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                            <p>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                                <?php if($sch['room']): ?>
                                                    <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                           class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-day"></i>
                                <p>No classes scheduled on Wednesday</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Thursday Schedule -->
                    <div class="day-schedule" id="thursday-schedule">
                        <?php
                        $thursday_schedules = array_filter($schedules, function($sch) {
                            return $sch['day_name'] == 'Thursday';
                        });
                        ?>
                        <?php if(count($thursday_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach($thursday_schedules as $sch): ?>
                                    <div class="schedule-item thursday">
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                            <p>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                                <?php if($sch['room']): ?>
                                                    <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                           class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-day"></i>
                                <p>No classes scheduled on Thursday</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Friday Schedule -->
                    <div class="day-schedule" id="friday-schedule">
                        <?php
                        $friday_schedules = array_filter($schedules, function($sch) {
                            return $sch['day_name'] == 'Friday';
                        });
                        ?>
                        <?php if(count($friday_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach($friday_schedules as $sch): ?>
                                    <div class="schedule-item friday">
                                        <div class="schedule-info">
                                            <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                            <p>
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                                <?php if($sch['room']): ?>
                                                    <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                           class="delete-btn" onclick="return confirm('Delete this schedule?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-day"></i>
                                <p>No classes scheduled on Friday</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule View -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-week"></i> Weekly Schedule View</h3>
                    <span class="badge-count"><?php echo count($schedules); ?> classes</span>
                </div>
                <div class="weekly-schedule">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Re-fetch time slots
                            $time_slots_query = $conn->query("SELECT * FROM time_slots ORDER BY start_time");
                            $all_time_slots = $time_slots_query->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Group schedules by day and time slot
                            $schedule_by_day_time = [];
                            foreach($schedules as $sch) {
                                $day = $sch['day_name'];
                                $time_slot_id = $sch['time_slot_id'];
                                $schedule_by_day_time[$day][$time_slot_id] = $sch;
                            }
                            
                            foreach($all_time_slots as $slot):
                                $start_time = date('h:i A', strtotime($slot['start_time']));
                                $end_time = date('h:i A', strtotime($slot['end_time']));
                            ?>
                                <tr>
                                    <td class="time-cell"><?php echo $start_time; ?> - <?php echo $end_time; ?></td>
                                    
                                    <td class="<?php echo isset($schedule_by_day_time['Monday'][$slot['id']]) ? 'schedule-cell' : 'empty-cell'; ?>">
                                        <?php if(isset($schedule_by_day_time['Monday'][$slot['id']])): 
                                            $s = $schedule_by_day_time['Monday'][$slot['id']];
                                        ?>
                                            <div class="subject-name"><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></div>
                                            <div class="teacher-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                            <?php if($s['room']): ?>
                                                <div class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    
                                    <td class="<?php echo isset($schedule_by_day_time['Tuesday'][$slot['id']]) ? 'schedule-cell' : 'empty-cell'; ?>">
                                        <?php if(isset($schedule_by_day_time['Tuesday'][$slot['id']])): 
                                            $s = $schedule_by_day_time['Tuesday'][$slot['id']];
                                        ?>
                                            <div class="subject-name"><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></div>
                                            <div class="teacher-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                            <?php if($s['room']): ?>
                                                <div class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    
                                    <td class="<?php echo isset($schedule_by_day_time['Wednesday'][$slot['id']]) ? 'schedule-cell' : 'empty-cell'; ?>">
                                        <?php if(isset($schedule_by_day_time['Wednesday'][$slot['id']])): 
                                            $s = $schedule_by_day_time['Wednesday'][$slot['id']];
                                        ?>
                                            <div class="subject-name"><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></div>
                                            <div class="teacher-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                            <?php if($s['room']): ?>
                                                <div class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    
                                    <td class="<?php echo isset($schedule_by_day_time['Thursday'][$slot['id']]) ? 'schedule-cell' : 'empty-cell'; ?>">
                                        <?php if(isset($schedule_by_day_time['Thursday'][$slot['id']])): 
                                            $s = $schedule_by_day_time['Thursday'][$slot['id']];
                                        ?>
                                            <div class="subject-name"><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></div>
                                            <div class="teacher-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                            <?php if($s['room']): ?>
                                                <div class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    
                                    <td class="<?php echo isset($schedule_by_day_time['Friday'][$slot['id']]) ? 'schedule-cell' : 'empty-cell'; ?>">
                                        <?php if(isset($schedule_by_day_time['Friday'][$slot['id']])): 
                                            $s = $schedule_by_day_time['Friday'][$slot['id']];
                                        ?>
                                            <div class="subject-name"><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></div>
                                            <div class="teacher-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                            <?php if($s['room']): ?>
                                                <div class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Pass PHP data to JavaScript
        window.takenTeachersData = <?php echo json_encode($taken_teachers_by_day_time); ?>;
        window.takenRoomsData = <?php echo json_encode($taken_rooms_by_day_time); ?>;
        window.takenSubjectsByDay = <?php echo json_encode($taken_subjects_by_day); ?>;
        window.sectionId = <?php echo $section_id; ?>;
        window.currentSchoolYear = '<?php echo $current_school_year; ?>';
        window.currentQuarter = '1';
    </script>
    <script src="js/create_schedule.js"></script>
</body>
</html>