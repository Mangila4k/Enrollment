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

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher details - PDO version
$query = "SELECT * FROM users WHERE id = ? AND role = 'Teacher'";
$stmt = $conn->prepare($query);
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$teacher) {
    header("Location: teachers.php");
    exit();
}

// Get teacher's profile picture
$teacher_profile_picture = $teacher['profile_picture'] ?? null;

// Get teacher's advisory sections
$sections_query = "
    SELECT s.*, g.grade_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = s.id AND e.status = 'Enrolled') as student_count
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($sections_query);
$stmt->execute([$teacher_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects taught by teacher (from class_schedules)
$subjects_query = "
    SELECT DISTINCT sub.*, g.grade_name
    FROM subjects sub
    LEFT JOIN grade_levels g ON sub.grade_id = g.id
    INNER JOIN class_schedules cs ON cs.subject_id = sub.id
    WHERE cs.teacher_id = ?
    ORDER BY g.id, sub.subject_name
";
$stmt = $conn->prepare($subjects_query);
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities (attendance records from teacher's subjects)
$activities_query = "
    SELECT a.*, u.fullname as student_name, sub.subject_name
    FROM attendance a
    LEFT JOIN users u ON a.student_id = u.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE sub.id IN (SELECT DISTINCT cs.subject_id FROM class_schedules cs WHERE cs.teacher_id = ?)
    ORDER BY a.date DESC
    LIMIT 10
";
$stmt = $conn->prepare($activities_query);
$stmt->execute([$teacher_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_sections = count($sections);
$total_students = 0;
foreach($sections as $section) {
    $total_students += $section['student_count'];
}

$account_created = $teacher['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));

// Handle delete request
if(isset($_GET['delete']) && $_GET['delete'] == $teacher_id) {
    try {
        $conn->beginTransaction();
        
        $update_sections = "UPDATE sections SET adviser_id = NULL WHERE adviser_id = ?";
        $stmt = $conn->prepare($update_sections);
        $stmt->execute([$teacher_id]);
        
        $delete_schedules = "DELETE FROM class_schedules WHERE teacher_id = ?";
        $stmt = $conn->prepare($delete_schedules);
        $stmt->execute([$teacher_id]);
        
        $delete_teacher = "DELETE FROM users WHERE id = ? AND role = 'Teacher'";
        $stmt = $conn->prepare($delete_teacher);
        $stmt->execute([$teacher_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Teacher deleted successfully!";
        header("Location: teachers.php");
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting teacher: " . $e->getMessage();
        header("Location: view_teacher.php?id=" . $teacher_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teacher - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/view_teacher.css">
</head>
<body>
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
                    <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
                    <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
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
                <h1>Teacher Profile</h1>
                <p>View complete teacher information and assignments</p>
            </div>
            <a href="teachers.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Teachers</a>
        </div>

        <!-- Alert Messages -->
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Teacher Profile Card -->
        <div class="profile-card">
            <div class="profile-avatar-large">
                <?php if($teacher_profile_picture && file_exists("../" . $teacher_profile_picture)): ?>
                    <img src="../<?php echo $teacher_profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($teacher['fullname']); ?></h2>
                
                <div class="profile-meta">
                    <span class="profile-meta-item">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-id-card"></i> ID: <?php echo $teacher['id_number'] ?? 'Not assigned'; ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($teacher['created_at'])); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                    </span>
                </div>

                <div>
                    <span class="profile-badge">
                        <i class="fas fa-chalkboard-user"></i> Teacher
                    </span>
                </div>

                <div class="action-buttons">
                    <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Teacher
                    </a>
                    <a href="?delete=<?php echo $teacher_id; ?>" class="btn-delete" onclick="return confirmDelete()">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Advisory Sections</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students Under Advisory</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo count($subjects); ?></div>
                    <div class="stat-label">Subjects Taught</div>
                </div>
            </div>
        </div>

        <!-- Advisory Sections -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-layer-group"></i> Advisory Sections</h3>
                <a href="sections.php?adviser=<?php echo $teacher_id; ?>" class="view-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if(!empty($sections)): ?>
                <div class="sections-grid">
                    <?php foreach($sections as $section): ?>
                        <div class="section-card">
                            <h4><i class="fas fa-users"></i> <?php echo htmlspecialchars($section['section_name']); ?></h4>
                            <div class="section-details">
                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                            </div>
                            <div class="section-stats">
                                <div class="section-stat">
                                    <div class="value"><?php echo $section['student_count']; ?></div>
                                    <div class="label">Students</div>
                                </div>
                            </div>
                            <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-link">
                                View Section <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Advisory Sections</h3>
                    <p>This teacher is not assigned as adviser to any section.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Subjects -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Subjects Taught</h3>
            </div>

            <?php if(!empty($subjects)): ?>
                <div class="subjects-list">
                    <?php foreach($subjects as $subject): ?>
                        <span class="subject-tag">
                            <i class="fas fa-book-open"></i>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                            <span class="subject-grade">(<?php echo $subject['grade_name']; ?>)</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data" style="padding: 20px;">
                    <i class="fas fa-book"></i>
                    <p>No subjects assigned to this teacher.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activities -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Attendance Activities</h3>
            </div>

            <?php if(!empty($activities)): ?>
                <div class="table-container">
                    <table class="activities-table">
                        <thead>
                            <tr><th>Date</th><th>Student</th><th>Subject</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            foreach($activities as $row): 
                                if($count++ >= 5) break;
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                     </div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data" style="padding: 20px;">
                    <i class="fas fa-calendar-times"></i>
                    <p>No recent activities found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/view_teacher.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const teacherData = {
            id: <?php echo $teacher_id; ?>,
            name: '<?php echo htmlspecialchars($teacher['fullname']); ?>',
            email: '<?php echo htmlspecialchars($teacher['email']); ?>',
            hasProfilePicture: <?php echo ($teacher_profile_picture && file_exists("../" . $teacher_profile_picture)) ? 'true' : 'false'; ?>
        };
    </script>
</body>
</html>