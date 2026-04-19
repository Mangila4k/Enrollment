<?php
session_start();
require_once("../config/database.php");

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

// Handle delete action for teacher attendance
if(isset($_GET['delete_teacher'])) {
    $delete_id = $_GET['delete_teacher'];
    $delete = $conn->prepare("DELETE FROM teacher_attendance WHERE id = ?");
    $delete->execute([$delete_id]);
    if($delete->rowCount() > 0) {
        $success_message = "Teacher attendance record deleted successfully!";
    } else {
        $error_message = "Error deleting teacher attendance record.";
    }
}

// Handle edit action
if(isset($_POST['edit_teacher_attendance'])) {
    $id = $_POST['id'];
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = $_POST['time_in'] ?: null;
    $time_out = $_POST['time_out'] ?: null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $update = $conn->prepare("
        UPDATE teacher_attendance 
        SET teacher_id = ?, date = ?, time_in = ?, time_out = ?, status = ?, remarks = ?
        WHERE id = ?
    ");
    if($update->execute([$teacher_id, $date, $time_in, $time_out, $status, $remarks, $id])) {
        $_SESSION['success_message'] = "Teacher attendance record updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating teacher attendance record.";
    }
    header("Location: attendance.php");
    exit();
}

// Handle add action
if(isset($_POST['add_teacher_attendance'])) {
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = $_POST['time_in'] ?: null;
    $time_out = $_POST['time_out'] ?: null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    // Check if record already exists for this teacher on this date
    $check = $conn->prepare("SELECT id FROM teacher_attendance WHERE teacher_id = ? AND date = ?");
    $check->execute([$teacher_id, $date]);
    if($check->rowCount() > 0) {
        $_SESSION['error_message'] = "Attendance record already exists for this teacher on this date.";
    } else {
        $insert = $conn->prepare("
            INSERT INTO teacher_attendance (teacher_id, date, time_in, time_out, status, remarks, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if($insert->execute([$teacher_id, $date, $time_in, $time_out, $status, $remarks])) {
            $_SESSION['success_message'] = "Teacher attendance record added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding teacher attendance record.";
        }
    }
    header("Location: attendance.php");
    exit();
}

// Get filter parameters for teacher attendance
$teacher_date_filter = isset($_GET['teacher_date']) ? $_GET['teacher_date'] : '';
$teacher_status_filter = isset($_GET['teacher_status']) ? $_GET['teacher_status'] : '';
$teacher_filter = isset($_GET['teacher']) ? $_GET['teacher'] : '';

// Build the teacher attendance query
$teacher_query = "
    SELECT ta.*, 
           u.fullname as teacher_name,
           u.id_number as teacher_id_number,
           u.email as teacher_email,
           u.profile_picture as teacher_profile_pic
    FROM teacher_attendance ta
    INNER JOIN users u ON ta.teacher_id = u.id
    WHERE u.role = 'Teacher'
";
$teacher_params = [];

if(!empty($teacher_date_filter) && $teacher_date_filter != 'all') {
    $teacher_query .= " AND ta.date = ?";
    $teacher_params[] = $teacher_date_filter;
}

if(!empty($teacher_status_filter)) {
    $teacher_query .= " AND ta.status = ?";
    $teacher_params[] = $teacher_status_filter;
}

if(!empty($teacher_filter)) {
    $teacher_query .= " AND ta.teacher_id = ?";
    $teacher_params[] = $teacher_filter;
}

$teacher_query .= " ORDER BY ta.date DESC, ta.created_at DESC";

$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->execute($teacher_params);
$teacher_attendance_records = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);

$record_count = count($teacher_attendance_records);

// Get teacher attendance statistics for today
$today = date('Y-m-d');

$teacher_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ?");
$teacher_today_stmt->execute([$today]);
$teacher_today = $teacher_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_present_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Present'");
$teacher_present_today_stmt->execute([$today]);
$teacher_present_today = $teacher_present_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_absent_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Absent'");
$teacher_absent_today_stmt->execute([$today]);
$teacher_absent_today = $teacher_absent_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_late_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Late'");
$teacher_late_today_stmt->execute([$today]);
$teacher_late_today = $teacher_late_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Get overall statistics
$overall_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT teacher_id) as total_teachers,
        MIN(date) as earliest_date,
        MAX(date) as latest_date
    FROM teacher_attendance
");
$overall_stats->execute();
$overall = $overall_stats->fetch(PDO::FETCH_ASSOC);

// Get teachers for filter (only approved teachers)
$teachers_stmt = $conn->prepare("SELECT id, fullname, id_number, profile_picture FROM users WHERE role = 'Teacher' AND status = 'approved' ORDER BY fullname");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all distinct dates for the date filter dropdown
$dates_stmt = $conn->query("SELECT DISTINCT date FROM teacher_attendance ORDER BY date DESC LIMIT 30");
$available_dates = $dates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance Management - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Attendance CSS -->
    <link rel="stylesheet" href="css/attendance.css">
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
                    <li><a href="attendance.php" class="active"><i class="fas fa-calendar-check"></i> Attendance</a></li>
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
                <h1>Teacher Attendance Management</h1>
                <p>View and manage teacher attendance records</p>
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
                <div class="stat-header"><h3>Today's Total</h3><div class="stat-icon"><i class="fas fa-calendar-check"></i></div></div>
                <div class="stat-number"><?php echo $teacher_today; ?></div>
                <div class="stat-label">Teacher records today</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Present Today</h3><div class="stat-icon"><i class="fas fa-user-check"></i></div></div>
                <div class="stat-number"><?php echo $teacher_present_today; ?></div>
                <div class="stat-label">Teachers present</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Absent Today</h3><div class="stat-icon"><i class="fas fa-user-times"></i></div></div>
                <div class="stat-number"><?php echo $teacher_absent_today; ?></div>
                <div class="stat-label">Teachers absent</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Late Today</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                <div class="stat-number"><?php echo $teacher_late_today; ?></div>
                <div class="stat-label">Teachers late</div>
            </div>
        </div>

        <!-- Date Navigation -->
        <div class="actions-bar">
            <div class="filter-group">
                <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <select name="teacher_date" class="filter-select">
                        <option value="all">All Dates</option>
                        <?php foreach($available_dates as $date): ?>
                            <option value="<?php echo $date['date']; ?>" <?php echo $teacher_date_filter == $date['date'] ? 'selected' : ''; ?>>
                                <?php echo date('F d, Y', strtotime($date['date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                    <button type="button" class="btn-add" onclick="openAddTeacherModal()"><i class="fas fa-plus"></i> Add Record</button>
                </form>
            </div>
            <div class="badge-count">
                <i class="fas fa-chart-line"></i> Total Records: <?php echo $overall['total_records'] ?? 0; ?> | 
                Teachers: <?php echo $overall['total_teachers'] ?? 0; ?>
                <?php if($overall['earliest_date']): ?>
                    | From: <?php echo date('M d, Y', strtotime($overall['earliest_date'])); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="actions-bar">
            <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; width: 100%;">
                <input type="hidden" name="teacher_date" value="<?php echo $teacher_date_filter; ?>">
                
                <div class="filter-group">
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; color: var(--text-gray);">Teacher</label>
                    <select name="teacher" class="filter-select">
                        <option value="">All Teachers</option>
                        <?php foreach($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['fullname']); ?> (<?php echo $teacher['id_number'] ?? 'No ID'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label style="display: block; margin-bottom: 5px; font-size: 12px; color: var(--text-gray);">Status</label>
                    <select name="teacher_status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Present" <?php echo $teacher_status_filter == 'Present' ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?php echo $teacher_status_filter == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="Late" <?php echo $teacher_status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
                <a href="attendance.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
            </form>
        </div>

        <!-- Teacher Attendance Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-calendar-check"></i> Teacher Attendance Records</h3>
                <span class="badge-count">Showing: <?php echo $record_count; ?> records</span>
            </div>
            <div class="table-container">
                <?php if($record_count > 0): ?>
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Teacher</th>
                            <th>ID Number</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teacher_attendance_records as $record): ?>
                            <tr>
                                <td>
                                    <span class="grade-tag">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                    </span>
                                </div>
                                <td>
                                    <div class="teacher-info">
                                        <?php if(!empty($record['teacher_profile_pic']) && file_exists("../" . $record['teacher_profile_pic'])): ?>
                                            <div class="teacher-avatar-img">
                                                <img src="../<?php echo $record['teacher_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Teacher">
                                            </div>
                                        <?php else: ?>
                                            <div class="teacher-avatar">
                                                <?php echo strtoupper(substr($record['teacher_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="teacher-details">
                                            <h4><?php echo htmlspecialchars($record['teacher_name']); ?></h4>
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($record['teacher_email']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <td>
                                    <span class="grade-tag"><?php echo $record['teacher_id_number'] ?? 'N/A'; ?></span>
                                </div>
                                <td>
                                    <?php if($record['time_in'] && $record['time_in'] != '00:00:00'): ?>
                                        <span class="grade-tag"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($record['time_in'])); ?></span>
                                    <?php else: ?>
                                        <span class="grade-tag">—</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <?php if($record['time_out'] && $record['time_out'] != '00:00:00'): ?>
                                        <span class="grade-tag"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($record['time_out'])); ?></span>
                                    <?php else: ?>
                                        <span class="grade-tag">—</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                        <?php echo $record['status']; ?>
                                    </span>
                                 </div>
                                <td>
                                    <div class="action-btns">
                                        <a href="?delete_teacher=<?php echo $record['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this attendance record?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                 </div>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Teacher Attendance Records Found</h3>
                        <p>Teachers can generate QR codes and scan to record their attendance, or you can manually add records using the "Add Record" button above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Teacher Attendance Modal -->
    <div class="modal" id="addTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add Teacher Attendance</h3>
                <button class="close-modal" onclick="closeAddTeacherModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Teacher *</label>
                        <select name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['fullname']); ?> (<?php echo $teacher['id_number'] ?? 'No ID'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Time In</label>
                        <input type="time" name="time_in">
                    </div>
                    <div class="form-group">
                        <label>Time Out</label>
                        <input type="time" name="time_out">
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddTeacherModal()">Cancel</button>
                    <button type="submit" name="add_teacher_attendance" class="btn-save">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/attendance.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const attendanceData = {
            totalRecords: <?php echo $record_count; ?>
        };
    </script>
</body>
</html>