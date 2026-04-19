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

function generateStudentID($conn, $grade_level, $school_year) {
    // Grade level parameter is kept for compatibility but not used
    $year = explode('-', $school_year)[0];
    
    $pattern = "PLSNHS-{$year}-%";
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number LIKE ? ORDER BY id_number DESC LIMIT 1");
    $stmt->execute([$pattern]);
    $last_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($last_id && $last_id['id_number']) {
        $parts = explode('-', $last_id['id_number']);
        $last_seq = intval(end($parts));
        $new_seq = $last_seq + 1;
    } else {
        $new_seq = 1;
    }
    
    $formatted_seq = str_pad($new_seq, 7, '0', STR_PAD_LEFT);
    
    return "PLSNHS-{$year}-{$formatted_seq}";
}

// Handle approve
if(isset($_GET['approve'])) {
    $enrollment_id = $_GET['approve'];
    
    $stmt = $conn->prepare("
        SELECT e.*, g.grade_name, e.school_year 
        FROM enrollments e 
        JOIN grade_levels g ON e.grade_id = g.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($enrollment) {
        $student_id_number = generateStudentID($conn, $enrollment['grade_name'], $enrollment['school_year']);
        
        $update = $conn->prepare("UPDATE enrollments SET status = 'Enrolled' WHERE id = ?");
        $update->execute([$enrollment_id]);
        
        $update_user = $conn->prepare("UPDATE users SET id_number = ? WHERE id = ?");
        $update_user->execute([$student_id_number, $enrollment['student_id']]);
        
        if($update->rowCount() > 0) {
            $success_message = "Enrollment approved successfully! Student ID: " . $student_id_number;
        } else {
            $error_message = "Error approving enrollment.";
        }
    } else {
        $error_message = "Enrollment not found.";
    }
}

// Handle reject
if(isset($_GET['reject'])) {
    $enrollment_id = $_GET['reject'];
    $update = $conn->prepare("UPDATE enrollments SET status = 'Rejected' WHERE id = ?");
    $update->execute([$enrollment_id]);
    if($update->rowCount() > 0) {
        $success_message = "Enrollment rejected.";
    } else {
        $error_message = "Error rejecting enrollment.";
    }
}

// Handle pending
if(isset($_GET['pending'])) {
    $enrollment_id = $_GET['pending'];
    $update = $conn->prepare("UPDATE enrollments SET status = 'Pending' WHERE id = ?");
    $update->execute([$enrollment_id]);
    if($update->rowCount() > 0) {
        $success_message = "Enrollment status set to pending.";
    } else {
        $error_message = "Error updating enrollment.";
    }
}

// Handle delete
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $delete = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $delete->execute([$enrollment_id]);
    if($delete->rowCount() > 0) {
        $success_message = "Enrollment record deleted successfully!";
    } else {
        $error_message = "Error deleting enrollment.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$strand_filter = isset($_GET['strand']) ? $_GET['strand'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "
    SELECT e.*, 
           u.fullname, 
           u.email, 
           u.id_number,
           g.grade_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE 1=1
";
$params = [];

if(!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if(!empty($grade_filter)) {
    $query .= " AND e.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($strand_filter)) {
    $query .= " AND e.strand = ?";
    $params[] = $strand_filter;
}

if(!empty($search_query)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY e.created_at DESC";

$enrollments_stmt = $conn->prepare($query);
$enrollments_stmt->execute($params);
$enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $total_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$pending_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Pending'");
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$enrolled_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Enrolled'");
$enrolled_count = $enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$rejected_stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Rejected'");
$rejected_count = $rejected_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get grade levels for filter
$grade_levels_stmt = $conn->query("SELECT * FROM grade_levels ORDER BY id");
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Title</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS (always include) -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Page Specific CSS -->
    <link rel="stylesheet" href="css/enrollments.css"> <!-- or students.css, teachers.css, etc. -->
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
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
                        <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
                        <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
                        <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> Enrollments</a></li>
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
                    <h1>Enrollments Management</h1>
                    <p>Manage student enrollment applications</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header"><h3>Total Enrollments</h3><div class="stat-icon"><i class="fas fa-file-signature"></i></div></div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Pending</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Enrolled</h3><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><h3>Rejected</h3><div class="stat-icon"><i class="fas fa-times-circle"></i></div></div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Not approved</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <select name="grade" class="filter-select">
                            <option value="">All Grades</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>><?php echo $grade['grade_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="strand" class="filter-select">
                            <option value="">All Strands</option>
                            <option value="STEM" <?php echo $strand_filter == 'STEM' ? 'selected' : ''; ?>>STEM</option>
                            <option value="ABM" <?php echo $strand_filter == 'ABM' ? 'selected' : ''; ?>>ABM</option>
                            <option value="HUMSS" <?php echo $strand_filter == 'HUMSS' ? 'selected' : ''; ?>>HUMSS</option>
                            <option value="GAS" <?php echo $strand_filter == 'GAS' ? 'selected' : ''; ?>>GAS</option>
                            <option value="ICT" <?php echo $strand_filter == 'ICT' ? 'selected' : ''; ?>>ICT</option>
                            <option value="HE" <?php echo $strand_filter == 'HE' ? 'selected' : ''; ?>>HE</option>
                            <option value="IA" <?php echo $strand_filter == 'IA' ? 'selected' : ''; ?>>IA</option>
                        </select>
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                        <a href="enrollments.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-file-signature"></i> Enrollment Applications</h3>
                    <span class="badge-count">Total: <?php echo count($enrollments); ?> records</span>
                </div>
                <div class="table-container">
                            <table class="enrollments-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>ID Number</th>
                    <th>Grade & Strand</th>
                    <th>School Year</th>
                    <th>Form 138</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($enrollments) > 0): ?>
                    <?php foreach($enrollments as $enrollment): ?>
                        <tr>
                            <td>
                                <div class="student-info">
                                    <?php 
                                    // Get student profile picture
                                    $student_profile_pic = null;
                                    $student_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                                    $student_stmt->execute([$enrollment['student_id']]);
                                    $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);
                                    $student_profile_pic = $student_data['profile_picture'] ?? null;
                                    ?>
                                    
                                    <?php if($student_profile_pic && file_exists("../" . $student_profile_pic)): ?>
                                        <div class="student-avatar-img">
                                            <img src="../<?php echo $student_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
                                        </div>
                                    <?php else: ?>
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="student-details">
                                        <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($enrollment['id_number']): ?>
                                    <span class="id-badge"><?php echo $enrollment['id_number']; ?></span>
                                <?php else: ?>
                                    <span class="grade-tag">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="grade-tag"><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                <?php if($enrollment['strand']): ?>
                                    <span class="strand-tag"><?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="grade-tag"><?php echo htmlspecialchars($enrollment['school_year']); ?></span>
                            </td>
                            <td>
                                <?php if($enrollment['form_138']): ?>
                                    <a href="../<?php echo $enrollment['form_138']; ?>" target="_blank" class="document-link"><i class="fas fa-file-pdf"></i> View</a>
                                <?php else: ?>
                                    <span class="grade-tag">No file</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($enrollment['status']); ?>"><?php echo $enrollment['status']; ?></span>
                            </td>
                            <td>
                                <span class="grade-tag"><?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?></span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <?php if($enrollment['status'] == 'Pending'): ?>
                                        <a href="?approve=<?php echo $enrollment['id']; ?>" class="action-btn approve" onclick="return confirm('Approve this enrollment? This will generate a student ID number.')" title="Approve"><i class="fas fa-check-circle"></i></a>
                                        <a href="?reject=<?php echo $enrollment['id']; ?>" class="action-btn reject" onclick="return confirm('Reject this enrollment?')" title="Reject"><i class="fas fa-times-circle"></i></a>
                                    <?php elseif($enrollment['status'] == 'Enrolled'): ?>
                                        <a href="?pending=<?php echo $enrollment['id']; ?>" class="action-btn pending" onclick="return confirm('Change status to pending?')" title="Move to Pending"><i class="fas fa-undo-alt"></i></a>
                                    <?php elseif($enrollment['status'] == 'Rejected'): ?>
                                        <a href="?pending=<?php echo $enrollment['id']; ?>" class="action-btn pending" onclick="return confirm('Change status to pending?')" title="Move to Pending"><i class="fas fa-undo-alt"></i></a>
                                    <?php endif; ?>
                                    <a href="view_enrollment.php?id=<?php echo $enrollment['id']; ?>" class="action-btn view" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="?delete=<?php echo $enrollment['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this enrollment record?')" title="Delete"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="no-data">
                                <i class="fas fa-file-signature"></i>
                                <h3>No Enrollments Found</h3>
                                <p>Click "New Enrollment" to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-submit on filter change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search on Enter
        const searchInput = document.querySelector('.search-box input');
        if(searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        }

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>