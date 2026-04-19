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

// Handle enrollment approval/rejection
if(isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve') {
        $status = 'Enrolled';
        $success_message = "Enrollment approved successfully!";
    } elseif($action == 'reject') {
        $status = 'Rejected';
        $success_message = "Enrollment rejected.";
    }
    
    $stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $enrollment_id]);
}

// Handle enrollment deletion
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollment_id]);
    $success_message = "Enrollment record deleted successfully!";
}

// Handle new enrollment (manual entry by registrar)
if(isset($_POST['add_enrollment'])) {
    $student_id = $_POST['student_id'];
    $grade_id = $_POST['grade_id'];
    $strand = $_POST['strand'];
    $school_year = $_POST['school_year'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, grade_id, strand, school_year, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())");
    if($stmt->execute([$student_id, $grade_id, $strand, $school_year, $status])) {
        $success_message = "New enrollment added successfully!";
    } else {
        $error_message = "Error adding enrollment: " . $conn->errorInfo()[2];
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query based on filters
$query = "SELECT e.*, u.fullname, u.email, u.id_number, u.profile_picture as student_profile_pic, g.grade_name 
          FROM enrollments e 
          LEFT JOIN users u ON e.student_id = u.id 
          LEFT JOIN grade_levels g ON e.grade_id = g.id 
          WHERE 1=1";

$params = [];

if($search) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ? OR e.school_year LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if($status_filter) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY e.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for dashboard
$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'");
$enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'");
$rejected_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get students and grades for dropdown
$students = $conn->query("SELECT * FROM users WHERE role='Student' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Enrollments CSS -->
    <link rel="stylesheet" href="css/enrollments.css">
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
                    <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
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
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Enrollment Management</h1>
                <p>Manage student enrollments and applications</p>
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
            <div class="stat-card" onclick="window.location.href='enrollments.php'">
                <div class="stat-header">
                    <h3>Total Enrollments</h3>
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                </div>
                <div class="stat-number"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">All time</div>
            </div>

            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Pending'">
                <div class="stat-header">
                    <h3>Pending</h3>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Awaiting review</div>
            </div>

            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Enrolled'">
                <div class="stat-header">
                    <h3>Enrolled</h3>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo $enrolled_count; ?></div>
                <div class="stat-label">Approved</div>
            </div>

            <div class="stat-card" onclick="window.location.href='enrollments.php?status=Rejected'">
                <div class="stat-header">
                    <h3>Rejected</h3>
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                </div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Not approved</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name, email, ID, or school year..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>

                <a href="enrollments.php" class="btn-reset">
                    <i class="fas fa-redo-alt"></i> Reset
                </a>

                <div class="export-buttons">
                    <button type="button" class="btn-export" id="addEnrollmentBtn">
                        <i class="fas fa-plus-circle"></i> Add
                    </button>
                    <button type="button" class="btn-export" id="exportExcelBtn">
                        <i class="fas fa-file-excel"></i> Export
                    </button>
                    <button type="button" class="btn-export" id="printBtn">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-file-signature"></i> Enrollment Records</h3>
                <span class="badge-count">Total: <?php echo count($enrollments); ?> records</span>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID Number</th>
                            <th>Grade & Strand</th>
                            <th>School Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($enrollments) > 0): ?>
                            <?php foreach($enrollments as $row): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <?php if(!empty($row['student_profile_pic']) && file_exists("../" . $row['student_profile_pic'])): ?>
                                                <div class="student-avatar-img">
                                                    <img src="../<?php echo $row['student_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Profile">
                                                </div>
                                            <?php else: ?>
                                                <div class="student-avatar">
                                                    <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="student-details">
                                                <h4><?php echo htmlspecialchars($row['fullname']); ?></h4>
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <td>
                                        <span class="id-badge"><?php echo $row['id_number'] ?? 'N/A'; ?></span>
                                     </div>
                                    <td>
                                        <span class="grade-tag"><?php echo htmlspecialchars($row['grade_name']); ?></span>
                                        <?php if($row['strand']): ?>
                                            <span class="grade-tag strand"><?php echo htmlspecialchars($row['strand']); ?></span>
                                        <?php endif; ?>
                                     </div>
                                    <td>
                                        <span class="school-year"><?php echo htmlspecialchars($row['school_year']); ?></span>
                                     </div>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                     </div>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="action-btn view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if($row['status'] == 'Pending'): ?>
                                                <a href="?action=approve&id=<?php echo $row['id']; ?>" class="action-btn approve" title="Approve" onclick="return confirm('Approve this enrollment?')">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                                <a href="?action=reject&id=<?php echo $row['id']; ?>" class="action-btn reject" title="Reject" onclick="return confirm('Reject this enrollment?')">
                                                    <i class="fas fa-times-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete" title="Delete" onclick="return confirm('Delete this enrollment record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                     </div>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <i class="fas fa-file-signature"></i>
                                        <h3>No Enrollment Records Found</h3>
                                        <p>Try adjusting your filters or add a new enrollment.</p>
                                    </div>
                                 </div>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <div class="quick-action-card">
                <div class="action-icon"><i class="fas fa-clock"></i></div>
                <h3>Pending Actions</h3>
                <p>You have <strong><?php echo $pending_count; ?></strong> pending enrollments waiting for your review.</p>
                <a href="?status=Pending" class="btn-warning">Review Pending</a>
            </div>
            
            <div class="quick-action-card">
                <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Reports</h3>
                <p>Generate enrollment reports and statistics for analysis.</p>
                <a href="reports.php" class="btn-primary">Generate Reports</a>
            </div>
            
            <div class="quick-action-card">
                <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>Students</h3>
                <p>Manage student records and information.</p>
                <a href="students.php" class="btn-primary">Manage Students</a>
            </div>
        </div>
    </main>

    <!-- Add Enrollment Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Enrollment</h3>
                <button class="close-modal" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addEnrollmentForm">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="student_id" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?> (<?php echo $s['email']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">-- Select Grade --</option>
                            <?php foreach($grades as $g): ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo $g['grade_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Strand (for Grade 11-12)</label>
                        <select name="strand">
                            <option value="">-- Optional --</option>
                            <option value="STEM">STEM</option>
                            <option value="ABM">ABM</option>
                            <option value="HUMSS">HUMSS</option>
                            <option value="GAS">GAS</option>
                            <option value="ICT">ICT</option>
                            <option value="HE">HE</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" placeholder="e.g., 2024-2025" value="<?php echo date('Y') . '-' . (date('Y')+1); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Pending">Pending</option>
                            <option value="Enrolled">Enrolled</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" name="add_enrollment" class="btn-save">Add Enrollment</button>
                        <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/enrollments.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const enrollmentsData = {
            totalCount: <?php echo $total_enrollments; ?>,
            pendingCount: <?php echo $pending_count; ?>,
            enrolledCount: <?php echo $enrolled_count; ?>,
            rejectedCount: <?php echo $rejected_count; ?>
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