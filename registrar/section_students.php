<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_id = $_SESSION['user']['id'];
$registrar_name = $_SESSION['user']['fullname'];
$section_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';

// Get registrar profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$registrar_id]);
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

// Get section details
$section_query = "
    SELECT s.*, g.grade_name, g.id as grade_id, u.fullname as adviser_name
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($section_query);
$stmt->execute([$section_id]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$section) {
    $_SESSION['error_message'] = "Section not found.";
    header("Location: sections.php");
    exit();
}

// Handle assign multiple students
if(isset($_POST['assign_selected'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $success_count = 0;
    $error_count = 0;
    
    foreach($student_ids as $student_id) {
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET section_id = ? 
            WHERE student_id = ? AND status = 'Enrolled'
        ");
        
        if($stmt->execute([$section_id, $student_id]) && $stmt->rowCount() > 0) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if($success_count > 0) {
        $success_message = "$success_count student(s) assigned to section successfully!";
    }
    if($error_count > 0) {
        $error_message = "$error_count student(s) could not be assigned.";
    }
}

// Handle assign single student
if(isset($_POST['assign_student'])) {
    $student_id = (int)$_POST['student_id'];
    
    $stmt = $conn->prepare("
        UPDATE enrollments 
        SET section_id = ? 
        WHERE student_id = ? AND status = 'Enrolled'
    ");
    
    if($stmt->execute([$section_id, $student_id]) && $stmt->rowCount() > 0) {
        $success_message = "Student assigned to section successfully!";
    } else {
        $error_message = "Error assigning student. Make sure the student is enrolled.";
    }
}

// Handle remove student
if(isset($_GET['remove'])) {
    $student_id = (int)$_GET['remove'];
    
    $stmt = $conn->prepare("
        UPDATE enrollments 
        SET section_id = NULL 
        WHERE student_id = ? AND section_id = ?
    ");
    
    if($stmt->execute([$student_id, $section_id]) && $stmt->rowCount() > 0) {
        $success_message = "Student removed from section successfully!";
    } else {
        $error_message = "Error removing student.";
    }
}

// Handle bulk remove
if(isset($_POST['remove_selected'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $success_count = 0;
    $error_count = 0;
    
    foreach($student_ids as $student_id) {
        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET section_id = NULL 
            WHERE student_id = ? AND section_id = ?
        ");
        
        if($stmt->execute([$student_id, $section_id]) && $stmt->rowCount() > 0) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if($success_count > 0) {
        $success_message = "$success_count student(s) removed from section successfully!";
    }
    if($error_count > 0) {
        $error_message = "$error_count student(s) could not be removed.";
    }
}

// Get students in this section with profile pictures
$stmt = $conn->prepare("
    SELECT u.id, u.fullname, u.email, u.id_number, u.profile_picture, e.status, e.school_year
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE e.section_id = ? AND u.role = 'Student'
    ORDER BY u.fullname
");
$stmt->execute([$section_id]);
$section_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available students (enrolled but no section) in the same grade level with profile pictures
$stmt = $conn->prepare("
    SELECT u.id, u.fullname, u.email, u.id_number, u.profile_picture, e.school_year
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE u.role = 'Student' 
    AND e.status = 'Enrolled'
    AND (e.section_id IS NULL OR e.section_id = 0)
    AND e.grade_id = ?
    ORDER BY u.fullname
");
$stmt->execute([$section['grade_id']]);
$available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students in this grade level (for reference)
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE u.role = 'Student' 
    AND e.status = 'Enrolled'
    AND e.grade_id = ?
");
$stmt->execute([$section['grade_id']]);
$total_grade_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$current_section_count = count($section_students);
$available_count = count($available_students);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Students - <?php echo htmlspecialchars($section['section_name']); ?></title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Section Students CSS -->
    <link rel="stylesheet" href="css/section_students.css">
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
                    <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <div class="avatar-initial"><?php echo strtoupper(substr($registrar_name, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="online-dot"></div>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></div>
            <div class="admin-role"><i class="fas fa-user-tie"></i> Registrar</div>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN MENU</div>
                <ul class="nav-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                    <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i> Sections</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                <h1>Manage Section Students</h1>
                <p>Assign and remove students from <?php echo htmlspecialchars($section['section_name']); ?></p>
            </div>
            <div class="header-actions">
                <a href="sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Sections
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

        <!-- Section Info Card -->
        <div class="section-info-card">
            <div class="section-info">
                <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
                <div class="section-meta">
                    <span class="meta-item">
                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?>
                    </span>
                </div>
            </div>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $current_section_count; ?></div>
                    <div class="stat-label">In This Section</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $available_count; ?></div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total_grade_students; ?></div>
                    <div class="stat-label">Total in Grade</div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid-2">
            <!-- Current Students in Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Current Students</h3>
                    <span class="badge"><?php echo count($section_students); ?> students</span>
                </div>

                <?php if(count($section_students) > 0): ?>
                    <form method="POST" id="removeForm">
                        <div class="bulk-actions">
                            <button type="button" class="btn-bulk remove" onclick="toggleAll('current')">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="submit" name="remove_selected" class="btn-bulk remove" onclick="return confirm('Remove selected students from this section?')">
                                <i class="fas fa-user-minus"></i> Remove Selected
                            </button>
                        </div>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchCurrent" placeholder="Search current students...">
                        </div>

                        <div class="student-list" id="currentList">
                            <div class="select-all">
                                <label>
                                    <input type="checkbox" id="selectAllCurrent"> <strong>Select All</strong> (<?php echo count($section_students); ?> students)
                                </label>
                            </div>
                            <?php foreach($section_students as $student): ?>
                                <div class="student-item" data-name="<?php echo strtolower($student['fullname']); ?>">
                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox current-checkbox">
                                    <?php if(!empty($student['profile_picture']) && file_exists("../" . $student['profile_picture'])): ?>
                                        <div class="student-avatar-img">
                                            <img src="../<?php echo $student['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile">
                                        </div>
                                    <?php else: ?>
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="student-info">
                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                        <div class="student-meta">
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                            <span><i class="fas fa-id-card"></i> ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                            <span><i class="fas fa-calendar"></i> SY: <?php echo $student['school_year']; ?></span>
                                        </div>
                                    </div>
                                    <a href="?id=<?php echo $section_id; ?>&remove=<?php echo $student['id']; ?>" 
                                       class="btn-icon remove" 
                                       onclick="return confirm('Remove this student from section?')">
                                        <i class="fas fa-times"></i> Remove
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-user-graduate"></i>
                        <p>No students in this section yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Students -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Available Students</h3>
                    <span class="badge"><?php echo count($available_students); ?> available</span>
                </div>

                <?php if(count($available_students) > 0): ?>
                    <form method="POST" id="assignForm">
                        <div class="bulk-actions">
                            <button type="button" class="btn-bulk assign" onclick="toggleAll('available')">
                                <i class="fas fa-check-double"></i> Select All
                            </button>
                            <button type="submit" name="assign_selected" class="btn-bulk assign">
                                <i class="fas fa-user-plus"></i> Assign Selected
                            </button>
                        </div>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchAvailable" placeholder="Search available students...">
                        </div>

                        <div class="student-list" id="availableList">
                            <div class="select-all">
                                <label>
                                    <input type="checkbox" id="selectAllAvailable"> <strong>Select All</strong> (<?php echo count($available_students); ?> students)
                                </label>
                            </div>
                            <?php foreach($available_students as $student): ?>
                                <div class="student-item" data-name="<?php echo strtolower($student['fullname']); ?>">
                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox available-checkbox">
                                    <?php if(!empty($student['profile_picture']) && file_exists("../" . $student['profile_picture'])): ?>
                                        <div class="student-avatar-img">
                                            <img src="../<?php echo $student['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile">
                                        </div>
                                    <?php else: ?>
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="student-info">
                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                        <div class="student-meta">
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                            <span><i class="fas fa-id-card"></i> ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                            <span><i class="fas fa-calendar"></i> SY: <?php echo $student['school_year']; ?></span>
                                        </div>
                                    </div>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="assign_student" class="btn-icon assign">
                                            <i class="fas fa-plus"></i> Assign
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-user-check"></i>
                        <p>No available students in <?php echo htmlspecialchars($section['grade_name']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="sections.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Sections
            </a>
            <a href="section_schedule.php?id=<?php echo $section_id; ?>" class="btn-primary">
                <i class="fas fa-calendar-alt"></i> View Schedule
            </a>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/section_students.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const sectionData = {
            id: <?php echo $section_id; ?>,
            name: '<?php echo addslashes($section['section_name']); ?>',
            grade: '<?php echo addslashes($section['grade_name']); ?>',
            currentCount: <?php echo $current_section_count; ?>,
            availableCount: <?php echo $available_count; ?>,
            totalGradeStudents: <?php echo $total_grade_students; ?>
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
</body>
</html>