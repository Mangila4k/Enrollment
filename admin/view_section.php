<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$admin_id = $_SESSION['user']['id'];

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: sections.php");
    exit();
}

$section_id = $_GET['id'];

// Get section details
$section_query = "SELECT s.*, g.grade_name, u.fullname as adviser_name, u.id as adviser_id
                  FROM sections s
                  LEFT JOIN grade_levels g ON s.grade_id = g.id
                  LEFT JOIN users u ON s.adviser_id = u.id
                  WHERE s.id = ?";
$stmt = $conn->prepare($section_query);
$stmt->execute([$section_id]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$section) {
    header("Location: sections.php");
    exit();
}

// Get students in this section
$students_query = "SELECT u.*, e.status, e.school_year, e.student_type
                   FROM enrollments e
                   JOIN users u ON e.student_id = u.id
                   WHERE e.section_id = ? AND e.status = 'Enrolled'
                   ORDER BY u.lastname ASC";
$stmt = $conn->prepare($students_query);
$stmt->execute([$section_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects for this section with schedule
$subjects_query = "SELECT sub.*, cs.day_id, cs.time_slot_id, cs.room, cs.quarter, 
                          d.day_name, ts.start_time, ts.end_time, ts.slot_name,
                          u.fullname as teacher_name
                   FROM class_schedules cs
                   JOIN subjects sub ON cs.subject_id = sub.id
                   LEFT JOIN days_of_week d ON cs.day_id = d.id
                   LEFT JOIN time_slots ts ON cs.time_slot_id = ts.id
                   LEFT JOIN users u ON cs.teacher_id = u.id
                   WHERE cs.section_id = ?
                   ORDER BY d.day_order, ts.start_time";
$stmt = $conn->prepare($subjects_query);
$stmt->execute([$section_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get class schedule organized by day
$schedule_by_day = [];
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach($days_order as $day) {
    $schedule_by_day[$day] = [];
}
foreach($subjects as $subject) {
    $day = $subject['day_name'] ?? 'Not Scheduled';
    if(isset($schedule_by_day[$day])) {
        $schedule_by_day[$day][] = $subject;
    }
}

// Calculate statistics
$total_students = count($students);
$total_subjects = count($subjects);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Section - <?php echo htmlspecialchars($section['section_name']); ?> | PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/view_section.css">
</head>
<body data-section-id="<?php echo $section_id; ?>" data-section-name="<?php echo htmlspecialchars($section['section_name']); ?>">
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

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
                    <img src="../<?php echo $profile_picture; ?>" alt="Profile">
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
                <h1>Section Details</h1>
                <p>View complete section information, students, and class schedule</p>
            </div>
            <a href="sections.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Sections</a>
        </div>

        <!-- Section Header -->
        <div class="section-header">
            <div class="section-title-section">
                <h2 class="section-title"><?php echo htmlspecialchars($section['section_name']); ?></h2>
                <span class="section-badge"><?php echo htmlspecialchars($section['grade_name']); ?></span>
            </div>
            <div class="section-info">
                <div class="info-item">
                    <i class="fas fa-chalkboard-user"></i>
                    <div>
                        <span class="info-label">Adviser</span>
                        <span class="info-value"><?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <span class="info-label">Total Students</span>
                        <span class="info-value"><?php echo $total_students; ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-book"></i>
                    <div>
                        <span class="info-label">Subjects</span>
                        <span class="info-value"><?php echo $total_subjects; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects Offered</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo count(array_filter($schedule_by_day, function($day) { return !empty($day); })); ?></div>
                    <div class="stat-label">Active Days</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="students">
                <i class="fas fa-users"></i> Students
            </button>
            <button class="tab-btn" data-tab="schedule">
                <i class="fas fa-calendar-alt"></i> Class Schedule
            </button>
        </div>

        <!-- Students Tab -->
        <div class="tab-content active" id="students-tab">
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Enrolled Students List</h3>
                    <span class="student-count-badge"><?php echo $total_students; ?> Students</span>
                </div>

                <?php if(!empty($students)): ?>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Full Name</th>
                                    <th>Student Type</th>
                                    <th>School Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                        <td><span class="type-badge"><?php echo ucfirst($student['student_type'] ?? 'New'); ?></span></td>
                                        <td><?php echo htmlspecialchars($student['school_year']); ?></td>
                                        <td><span class="status-badge status-enrolled"><?php echo $student['status']; ?></span></td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn view-btn" title="View Student">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <h3>No Students Enrolled</h3>
                        <p>This section has no enrolled students yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Schedule Tab -->
        <div class="tab-content" id="schedule-tab">
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Class Schedule</h3>
                </div>

                <?php if(!empty($subjects)): ?>
                    <div class="schedule-container">
                        <?php foreach($days_order as $day): ?>
                            <?php if(!empty($schedule_by_day[$day])): ?>
                                <div class="day-schedule">
                                    <h4 class="day-title">
                                        <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                                    </h4>
                                    <div class="schedule-grid">
                                        <?php foreach($schedule_by_day[$day] as $subject): ?>
                                            <div class="schedule-card">
                                                <div class="schedule-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('g:i A', strtotime($subject['start_time'] ?? '00:00')); ?> - 
                                                    <?php echo date('g:i A', strtotime($subject['end_time'] ?? '00:00')); ?>
                                                </div>
                                                <h5 class="schedule-subject">
                                                    <i class="fas fa-book-open"></i> 
                                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                </h5>
                                                <div class="schedule-details">
                                                    <span><i class="fas fa-chalkboard-user"></i> <?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not assigned'); ?></span>
                                                    <span><i class="fas fa-door-open"></i> Room: <?php echo htmlspecialchars($subject['room'] ?? 'Not assigned'); ?></span>
                                                    <span><i class="fas fa-layer-group"></i> Quarter: <?php echo $subject['quarter']; ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Class Schedule</h3>
                        <p>No class schedule has been assigned to this section yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/view_section.js"></script>
</body>
</html>