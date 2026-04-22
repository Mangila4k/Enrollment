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

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        // Check if student has enrollments
        $check_enrollments = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ?");
        $check_enrollments->execute([$delete_id]);
        if($check_enrollments->rowCount() > 0) {
            // Delete enrollments first
            $delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
            $delete_enrollments->execute([$delete_id]);
        }
        
        // Check if student has attendance records
        $check_attendance = $conn->prepare("SELECT id FROM attendance WHERE student_id = ?");
        $check_attendance->execute([$delete_id]);
        if($check_attendance->rowCount() > 0) {
            // Delete attendance records first
            $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
            $delete_attendance->execute([$delete_id]);
        }
        
        // Delete the student
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

// Get current school year
$current_year = date('Y');
$current_sy = $current_year . '-' . ($current_year + 1);

// Get statistics
$total_students_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'Student'");
$total_students_stmt->execute();
$total_students = $total_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$enrolled_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Enrolled'
");
$enrolled_students_stmt->execute();
$enrolled_students = $enrolled_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$pending_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Pending'
");
$pending_students_stmt->execute();
$pending_students = $pending_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$rejected_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Rejected'
");
$rejected_students_stmt->execute();
$rejected_students = $rejected_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$no_enrollment = $total_students - ($enrolled_students + $pending_students + $rejected_students);

// Build the query to get all students with their enrollment info
$query = "
    SELECT u.*, 
           u.profile_picture,
           e.id as enrollment_id,
           e.grade_id,
           e.status as enrollment_status,
           e.strand,
           e.school_year,
           e.created_at as enrolled_date,
           g.grade_name,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) as total_enrollments
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id AND e.school_year = ?
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE u.role = 'Student'
";

$params = [$current_sy];

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
$all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate student statistics for classification
$new_students = 0;
$old_students = 0;

foreach($all_students as $student) {
    if($student['total_enrollments'] == 1 && !empty($student['enrollment_id'])) {
        $new_students++;
    } elseif($student['total_enrollments'] > 1 && !empty($student['enrollment_id'])) {
        $old_students++;
    }
}

$student_stats = [
    'new_students' => $new_students,
    'old_students' => $old_students
];

// Get grade levels for filter
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Students CSS -->
    <link rel="stylesheet" href="css/students.css">
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
                        <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
                        <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Student Management</h1>
                    <p>Manage student records and enrollment information</p>
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
                <div class="stat-card" onclick="window.location.href='students.php'">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">All students</div>
                </div>

                <div class="stat-card" onclick="window.location.href='students.php?status=Enrolled'">
                    <div class="stat-header">
                        <h3>Enrolled</h3>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_students; ?></div>
                    <div class="stat-label">Active enrollments</div>
                </div>

                <div class="stat-card" onclick="window.location.href='students.php?status=Pending'">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $pending_students; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>

                <div class="stat-card" onclick="window.location.href='students.php?status=Rejected'">
                    <div class="stat-header">
                        <h3>Rejected</h3>
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $rejected_students; ?></div>
                    <div class="stat-label">Not enrolled</div>
                </div>

                <div class="stat-card" onclick="window.location.href='students.php?status=none'">
                    <div class="stat-header">
                        <h3>No Enrollment</h3>
                        <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $no_enrollment; ?></div>
                    <div class="stat-label">No records</div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <select name="grade" class="filter-select">
                            <option value="">All Grades</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>

                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Apply
                        </button>

                        <a href="students.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>

                <div class="export-buttons">
                    <button type="button" class="btn-export" id="exportExcelBtn">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                    <button type="button" class="btn-export" id="printBtn">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <!-- Students Table -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-user-graduate"></i> Student Records</h3>
        <div class="stats-badges">
            <span class="badge-old">Old: <?php echo $student_stats['old_students']; ?></span>
            <span class="badge-new">New: <?php echo $student_stats['new_students']; ?></span>
            <span class="badge-count">Total: <?php echo count($all_students); ?> students</span>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>ID Number</th>
                    <th>Grade & Strand</th>
                    <th>Status</th>
                    <th>Student Type</th>
                    <th>School Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($all_students) > 0): ?>
                    <?php foreach($all_students as $student): 
                        // Determine if student is old or new
                        $is_old = ($student['total_enrollments'] > 1 && !empty($student['enrollment_id']));
                        $student_badge = '';
                        $student_color = '';
                        
                        if($is_old) {
                            $student_badge = '<span class="student-badge old">Old Student</span>';
                            $student_color = '#28a745';
                        } elseif(!empty($student['enrollment_id'])) {
                            $student_badge = '<span class="student-badge new">New Student</span>';
                            $student_color = '#007bff';
                        } else {
                            $student_badge = '<span class="student-badge none">No Enrollment</span>';
                            $student_color = '#6c757d';
                        }
                        
                        $student_profile_pic = $student['profile_picture'] ?? null;
                    ?>
                        <tr class="<?php echo $is_old ? 'old-student-row' : (!empty($student['enrollment_id']) ? 'new-student-row' : ''); ?>">
                            <!-- Student Column -->
                            <td class="student-cell">
                                <div class="student-info">
                                    <?php if(!empty($student_profile_pic) && file_exists("../" . $student_profile_pic)): ?>
                                        <div class="student-avatar-img">
                                            <img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                                        </div>
                                    <?php else: ?>
                                        <div class="student-avatar" style="background: <?php echo $student_color; ?>;">
                                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="student-details">
                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                        <?php if($student['total_enrollments'] > 1): ?>
                                            <span class="enrollment-count">
                                                <i class="fas fa-history"></i> <?php echo $student['total_enrollments']; ?> enrollments
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- ID Number Column -->
                            <td class="id-cell">
                                <span class="id-badge"><?php echo $student['id_number'] ?? 'N/A'; ?></span>
                            </td>
                            
                            <!-- Grade & Strand Column -->
                            <td class="grade-cell">
                                <?php if($student['grade_name']): ?>
                                    <span class="grade-tag"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                                    <?php if($student['strand']): ?>
                                        <span class="strand-tag"><?php echo htmlspecialchars($student['strand']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge-none">Not enrolled</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Status Column -->
                            <td class="status-cell">
                                <?php if($student['enrollment_status']): ?>
                                    <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                                        <?php echo $student['enrollment_status']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-none">No record</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Student Type Column -->
                            <td class="type-cell">
                                <?php echo $student_badge; ?>
                            </td>
                            
                            <!-- School Year Column -->
                            <td class="year-cell">
                                <?php if($student['school_year']): ?>
                                    <span class="school-year"><?php echo htmlspecialchars($student['school_year']); ?></span>
                                <?php else: ?>
                                    <span class="school-year">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Actions Column -->
                            <td class="actions-cell">
                                <div class="action-btns">
                                    <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="action-btn edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $student['id']; ?>" class="action-btn delete" title="Delete" 
                                       onclick="return confirm('Are you sure you want to delete this student? This will also delete all associated records.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="no-data">
                                <i class="fas fa-user-graduate"></i>
                                <h3>No Students Found</h3>
                                <p>No student records match your search criteria.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <!-- Students JS -->
    <script src="js/students.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const studentsData = {
            totalCount: <?php echo $total_students; ?>,
            enrolledCount: <?php echo $enrolled_students; ?>,
            pendingCount: <?php echo $pending_students; ?>,
            rejectedCount: <?php echo $rejected_students; ?>,
            noEnrollmentCount: <?php echo $no_enrollment; ?>
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