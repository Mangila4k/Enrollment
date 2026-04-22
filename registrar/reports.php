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

// Get filter parameters for reports
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'enrollment_summary';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get grade levels for filter
$stmt = $conn->query("SELECT * FROM grade_levels ORDER BY id");
$grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize report data
$report_title = '';
$report_headers = [];
$report_rows = [];

// Generate report based on type
if($report_type == 'enrollment_summary') {
    $report_title = 'Enrollment Summary Report';
    $report_headers = ['Grade Level', 'Total Enrollments', 'Pending', 'Enrolled', 'Rejected', 'Enrollment Rate'];
    
    $query = "
        SELECT 
            g.grade_name,
            COUNT(e.id) as total,
            SUM(CASE WHEN e.status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN e.status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
            SUM(CASE WHEN e.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM grade_levels g
        LEFT JOIN enrollments e ON g.id = e.grade_id
        GROUP BY g.id, g.grade_name
        ORDER BY g.id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($results as $row) {
        $total = $row['total'] ?? 0;
        $enrolled = $row['enrolled'] ?? 0;
        $enrollment_rate = $total > 0 ? round(($enrolled / $total) * 100, 2) : 0;
        $report_rows[] = [
            $row['grade_name'],
            $total,
            $row['pending'] ?? 0,
            $enrolled,
            $row['rejected'] ?? 0,
            $enrollment_rate . '%'
        ];
    }
}
elseif($report_type == 'student_list') {
    $report_title = 'Student List Report';
    $report_headers = ['Student Name', 'ID Number', 'Email', 'Grade Level', 'Strand', 'Status', 'School Year'];
    
    $query = "
        SELECT 
            u.fullname,
            u.id_number,
            u.email,
            g.grade_name,
            e.strand,
            e.status,
            e.school_year
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN grade_levels g ON e.grade_id = g.id
        WHERE u.role = 'Student'
    ";
    
    $params = [];
    
    if(!empty($grade_filter)) {
        $query .= " AND e.grade_id = ?";
        $params[] = $grade_filter;
    }
    
    if(!empty($status_filter)) {
        $query .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY u.fullname";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($results as $row) {
        $report_rows[] = [
            $row['fullname'],
            $row['id_number'] ?? 'N/A',
            $row['email'],
            $row['grade_name'] ?? 'Not Enrolled',
            $row['strand'] ?? '—',
            $row['status'] ?? 'No Record',
            $row['school_year'] ?? '—'
        ];
    }
}
elseif($report_type == 'enrollment_trends') {
    $report_title = 'Enrollment Trends Report';
    $report_headers = ['Month', 'Total Enrollments', 'Pending', 'Enrolled', 'Rejected', 'Monthly Change'];
    
    $query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM enrollments
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$date_from, $date_to]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $prev_total = 0;
    foreach($results as $row) {
        $change = $prev_total > 0 ? round((($row['total'] - $prev_total) / $prev_total) * 100, 2) : 0;
        $change_text = $change > 0 ? "+$change%" : ($change < 0 ? "$change%" : "0%");
        $report_rows[] = [
            date('F Y', strtotime($row['month'] . '-01')),
            $row['total'],
            $row['pending'],
            $row['enrolled'],
            $row['rejected'],
            $change_text
        ];
        $prev_total = $row['total'];
    }
}
elseif($report_type == 'strand_distribution') {
    $report_title = 'Strand Distribution Report (Senior High)';
    $report_headers = ['Strand', 'Number of Students', 'Percentage'];
    
    $query = "
        SELECT 
            CASE 
                WHEN e.strand IS NULL OR e.strand = '' THEN 'Not Specified'
                ELSE e.strand 
            END as strand,
            COUNT(DISTINCT e.student_id) as student_count
        FROM enrollments e
        WHERE e.grade_id IN (5, 6) AND e.status = 'Enrolled'
        GROUP BY e.strand
        ORDER BY student_count DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = 0;
    foreach($results as $row) {
        $total += $row['student_count'];
    }
    
    foreach($results as $row) {
        $percentage = $total > 0 ? round(($row['student_count'] / $total) * 100, 2) : 0;
        $report_rows[] = [
            $row['strand'],
            $row['student_count'],
            $percentage . '%'
        ];
    }
}

// Get summary statistics for dashboard
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'");
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments");
$total_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'");
$enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$this_month_enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Reports CSS -->
    <link rel="stylesheet" href="css/reports.css">
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
                        <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                        <li><a href="sections.php"><i class="fas fa-layer-group"></i> Sections</a></li>
                        <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                    <h1>Reports</h1>
                    <p>Generate and export enrollment reports</p>
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
                        <h3>Total Students</h3>
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Registered</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Enrollments</h3>
                        <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Currently Enrolled</h3>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Active students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>This Month</h3>
                        <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $this_month_enrollments; ?></div>
                    <div class="stat-label">New enrollments</div>
                </div>
            </div>

            <!-- Report Controls -->
            <div class="report-controls">
                <h3><i class="fas fa-sliders-h"></i> Report Controls</h3>
                <form method="GET" class="controls-grid">
                    <div class="control-group">
                        <label>Report Type</label>
                        <select name="report_type">
                            <option value="enrollment_summary" <?php echo $report_type == 'enrollment_summary' ? 'selected' : ''; ?>>Enrollment Summary</option>
                            <option value="student_list" <?php echo $report_type == 'student_list' ? 'selected' : ''; ?>>Student List</option>
                            <option value="enrollment_trends" <?php echo $report_type == 'enrollment_trends' ? 'selected' : ''; ?>>Enrollment Trends</option>
                            <option value="strand_distribution" <?php echo $report_type == 'strand_distribution' ? 'selected' : ''; ?>>Strand Distribution</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>

                    <div class="control-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>

                    <div class="control-group">
                        <label>Grade Level</label>
                        <select name="grade">
                            <option value="">All Grades</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="control-group action-buttons">
                        <button type="submit" class="btn-generate">
                            <i class="fas fa-sync-alt"></i> Generate Report
                        </button>
                        <a href="reports.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Report Display -->
            <?php if(!empty($report_rows)): ?>
                <div class="report-card">
                    <div class="report-header">
                        <h2><i class="fas fa-chart-bar"></i> <?php echo $report_title; ?></h2>
                        <div class="report-actions">
                            <button class="btn-export" id="exportExcelBtn">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button class="btn-print" id="printBtn">
                                <i class="fas fa-print"></i> Print Report
                            </button>
                        </div>
                    </div>

                    <div class="date-range">
                        <i class="fas fa-calendar-alt"></i>
                        Report Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="reportTable">
                            <thead>
                                <tr>
                                    <?php foreach($report_headers as $header): ?>
                                        <th><?php echo $header; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($report_rows as $row): ?>
                                    <tr>
                                        <?php foreach($row as $cell): ?>
                                            <td><?php echo $cell; ?></div>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if(count($report_rows) > 0): ?>
                                <tfoot>
                                    <tr class="total-records">
                                        <td colspan="<?php echo count($report_headers); ?>">
                                            <strong>Total Records: <?php echo count($report_rows); ?></strong>
                                         </div>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="report-card">
                    <div class="no-data">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Data Available</h3>
                        <p>No records found for the selected criteria. Try adjusting your filters.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reports JS -->
    <script src="js/reports.js"></script>
</body>
</html>