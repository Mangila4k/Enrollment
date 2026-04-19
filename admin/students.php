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
$success_message = '';
$error_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        $check_enrollments = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ?");
        $check_enrollments->execute([$delete_id]);
        if($check_enrollments->rowCount() > 0) {
            $delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
            $delete_enrollments->execute([$delete_id]);
        }
        
        $check_attendance = $conn->prepare("SELECT id FROM attendance WHERE student_id = ?");
        $check_attendance->execute([$delete_id]);
        if($check_attendance->rowCount() > 0) {
            $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
            $delete_attendance->execute([$delete_id]);
        }
        
        $delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
        $delete->execute([$delete_id]);
        
        if($delete->rowCount() > 0) {
            $success_message = "Student deleted successfully!";
        } else {
            $error_message = "Error deleting student.";
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$enrollee_type = isset($_GET['type']) ? $_GET['type'] : 'all';

$current_year = date('Y');
$current_sy = $current_year . '-' . ($current_year + 1);

$query = "
    SELECT u.*, 
           e.id as enrollment_id,
           e.grade_id,
           e.status as enrollment_status,
           e.strand,
           e.school_year,
           e.created_at as enrolled_date,
           g.grade_name,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) as total_enrollments,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) as previous_enrollments,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) as current_enrollments
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id AND e.school_year = ?
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE u.role = 'Student'
";

$params = [$current_sy, $current_sy, $current_sy];

if($enrollee_type == 'new') {
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 1";
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) = 1";
    $params[] = $current_sy;
} elseif($enrollee_type == 'old') {
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) > 0";
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) = 1";
    $params[] = $current_sy;
    $params[] = $current_sy;
} elseif($enrollee_type == 'not_enrolled') {
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 0";
}

if(!empty($grade_filter)) {
    $query .= " AND e.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if(!empty($search_query)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_students_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'Student'");
$total_students_stmt->execute();
$total_students = $total_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$new_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    INNER JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' 
    AND e.school_year = ?
    AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 1
");
$new_students_stmt->execute([$current_sy]);
$new_students = $new_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$old_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    INNER JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' 
    AND e.school_year = ?
    AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) > 0
");
$old_students_stmt->execute([$current_sy, $current_sy]);
$old_students = $old_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$not_enrolled_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM users u 
    WHERE u.role = 'Student' 
    AND NOT EXISTS (SELECT 1 FROM enrollments WHERE student_id = u.id)
");
$not_enrolled_stmt->execute();
$not_enrolled = $not_enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$enrolled_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Enrolled' AND e.school_year = ?
");
$enrolled_students_stmt->execute([$current_sy]);
$enrolled_students = $enrolled_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$pending_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Pending' AND e.school_year = ?
");
$pending_students_stmt->execute([$current_sy]);
$pending_students = $pending_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$rejected_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Rejected' AND e.school_year = ?
");
$rejected_students_stmt->execute([$current_sy]);
$rejected_students = $rejected_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/students.css">
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
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
                    <li><a href="teachers.php"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
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
                <h1>Students Management</h1>
                <p>Manage student accounts and enrollment records</p>
            </div>
            <a href="add_student.php" class="btn-add"><i class="fas fa-plus-circle"></i> Add New Student</a>
        </div>

        <?php if($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><h3>Total Students</h3><div class="stat-icon"><i class="fas fa-user-graduate"></i></div></div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">All registered students</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>New Enrollees</h3><div class="stat-icon"><i class="fas fa-star-of-life"></i></div></div>
                <div class="stat-number"><?php echo $new_students; ?></div>
                <div class="stat-label">First-time enrollees</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Old Enrollees</h3><div class="stat-icon"><i class="fas fa-history"></i></div></div>
                <div class="stat-number"><?php echo $old_students; ?></div>
                <div class="stat-label">Returning students</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Not Enrolled</h3><div class="stat-icon"><i class="fas fa-user-slash"></i></div></div>
                <div class="stat-number"><?php echo $not_enrolled; ?></div>
                <div class="stat-label">No enrollment record</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?type=all" class="tab-btn <?php echo $enrollee_type == 'all' ? 'active' : ''; ?>"><i class="fas fa-users"></i> All Students</a>
            <a href="?type=new" class="tab-btn <?php echo $enrollee_type == 'new' ? 'active' : ''; ?>"><i class="fas fa-star-of-life"></i> New Enrollees</a>
            <a href="?type=old" class="tab-btn <?php echo $enrollee_type == 'old' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Old Enrollees</a>
            <a href="?type=not_enrolled" class="tab-btn <?php echo $enrollee_type == 'not_enrolled' ? 'active' : ''; ?>"><i class="fas fa-user-slash"></i> Not Enrolled</a>
        </div>

        <!-- Filters -->
        <div class="actions-bar">
            <form method="GET" class="filter-form" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%;">
                <input type="hidden" name="type" value="<?php echo $enrollee_type; ?>">
                <div class="filter-group" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <select name="grade" class="filter-select">
                        <option value="">All Grades</option>
                        <?php foreach($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>><?php echo $grade['grade_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="students.php?type=<?php echo $enrollee_type; ?>" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                </div>
                <div class="search-box" style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name, email or ID..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn-search">Search</button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-user-graduate"></i> Student List</h3>
                <span class="badge-count">Total: <?php echo count($students); ?> students</span>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr><th>Student</th><th>ID Number</th><th>Type</th><th>Grade & Strand</th><th>Status</th><th>Enrolled Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($students) > 0): ?>
                            <?php foreach($students as $student): 
                                if($student['total_enrollments'] == 0) {
                                    $student_type = 'not-enrolled';
                                    $student_type_label = 'Not Enrolled';
                                    $student_type_icon = 'user-slash';
                                } elseif($student['total_enrollments'] == 1 && $student['current_enrollments'] == 1) {
                                    $student_type = 'new';
                                    $student_type_label = 'New Enrollee';
                                    $student_type_icon = 'star-of-life';
                                } elseif($student['previous_enrollments'] > 0 && $student['current_enrollments'] == 1) {
                                    $student_type = 'old';
                                    $student_type_label = 'Old Enrollee';
                                    $student_type_icon = 'history';
                                } else {
                                    $student_type = 'not-enrolled';
                                    $student_type_label = 'Not Enrolled';
                                    $student_type_icon = 'user-slash';
                                }
                                
                                // Get student profile picture
                                $student_profile_pic = $student['profile_picture'] ?? null;
                            ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <?php if($student_profile_pic && file_exists("../" . $student_profile_pic)): ?>
                                                <div class="student-avatar-img">
                                                    <img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                                                </div>
                                            <?php else: ?>
                                                <div class="student-avatar <?php echo $student_type; ?>">
                                                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="student-details">
                                                <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="id-tag"><?php echo $student['id_number'] ?? 'N/A'; ?></span></td>
                                    <td><span class="type-badge <?php echo $student_type; ?>"><i class="fas fa-<?php echo $student_type_icon; ?>"></i> <?php echo $student_type_label; ?></span></td>
                                    <td>
                                        <?php if(!empty($student['grade_name'])): ?>
                                            <span class="grade-tag"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                                            <?php if(!empty($student['strand'])): ?>
                                                <span class="strand-tag"><?php echo htmlspecialchars($student['strand']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge none">Not enrolled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(!empty($student['enrollment_status'])): ?>
                                            <span class="status-badge <?php echo strtolower($student['enrollment_status']); ?>"><?php echo $student['enrollment_status']; ?></span>
                                        <?php else: ?>
                                            <span class="status-badge none">No record</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($student['enrolled_date']) ? date('M d, Y', strtotime($student['enrolled_date'])) : '—'; ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn view" title="View"><i class="fas fa-eye"></i></a>
                                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="action-btn edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?php echo $student['id']; ?>&type=<?php echo $enrollee_type; ?>" class="action-btn delete" title="Delete" onclick="return confirm('Delete this student?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"><div class="no-data"><i class="fas fa-user-graduate"></i><h3>No Students Found</h3><p>Click "Add New Student" to get started.</p></div></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (sidebar && menuToggle) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s';
                    setTimeout(() => {
                        if (alert.parentNode) alert.remove();
                    }, 300);
                }, 3000);
            });
        }, 1000);
    </script>
</body>
</html>