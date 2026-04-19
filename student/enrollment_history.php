<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
$profile_picture = $_SESSION['user']['profile_picture'] ?? null;
$success_message = '';
$error_message = '';

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Direct database fetch for enrollment history
$enrollment_history = [];

try {
    // Get full enrollment details
    $query = "SELECT 
                e.*,
                gl.grade_name,
                s.section_name
              FROM enrollments e
              LEFT JOIN grade_levels gl ON e.grade_id = gl.id
              LEFT JOIN sections s ON e.section_id = s.id
              WHERE e.student_id = :student_id
              ORDER BY e.id DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':student_id' => $student_id]);
    $enrollment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Enrollment history error: " . $e->getMessage());
    $enrollment_history = [];
}

// Get student type based on enrollment history
$has_previous_enrollments = false;

if(count($enrollment_history) > 1) {
    $has_previous_enrollments = true;
} elseif(count($enrollment_history) == 1) {
    $first_enrollment = $enrollment_history[0];
    $current_year = date('Y');
    $enrollment_year = substr($first_enrollment['school_year'] ?? '', 0, 4);
    
    if($enrollment_year && $enrollment_year < $current_year) {
        $has_previous_enrollments = true;
    }
}

// Get current enrollment (first one in the list is the most recent)
$current_enrollment = !empty($enrollment_history) ? $enrollment_history[0] : null;

// Calculate statistics
$total_enrollments = count($enrollment_history);
$enrolled_count = 0;
$pending_count = 0;
$rejected_count = 0;
$years = [];

foreach($enrollment_history as $e) {
    $status = $e['status'] ?? '';
    if($status == 'Enrolled' || $status == 'Approved') $enrolled_count++;
    if($status == 'Pending') $pending_count++;
    if($status == 'Rejected') $rejected_count++;
    
    $school_year = $e['school_year'] ?? '';
    if(!empty($school_year)) $years[] = $school_year;
}
$unique_years = count(array_unique($years));

// Check if currently enrolled
$is_currently_enrolled = $current_enrollment && ($current_enrollment['status'] == 'Enrolled' || $current_enrollment['status'] == 'Approved');

// Determine if student is new
$is_new_student = !$has_previous_enrollments;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment History - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Enrollment History CSS -->
    <link rel="stylesheet" href="css/enrollment_history.css">
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

            <div class="student-profile">
                <div class="student-avatar">
                    <?php if(isset($profile_picture) && $profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo isset($student_name) ? strtoupper(substr($student_name, 0, 1)) : 'S'; ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo isset($student_name) ? htmlspecialchars(explode(' ', $student_name)[0]) : 'Student'; ?></div>
                <div class="student-role"><i class="fas fa-user-graduate"></i> Student</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php" class="active"><i class="fas fa-history"></i> Enrollment History</a></li>
                    </ul>
                </div>

                <div class="nav-section">
    <div class="nav-section-title">ACCOUNT</div>
    <ul class="nav-items">
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <h1><i class="fas fa-history" style="color: var(--primary);"></i> Enrollment History</h1>
                </div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($success_message) && $success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if(isset($error_message) && $error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Current Enrollment Banner (if currently enrolled) -->
            <?php if($is_currently_enrolled && $current_enrollment): ?>
                <div class="current-enrollment-banner">
                    <div class="current-enrollment-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="current-enrollment-content">
                        <h3>
                            Currently Enrolled
                            <?php if($is_new_student): ?>
                                <span class="new-student-badge">New Student</span>
                            <?php else: ?>
                                <span class="old-student-badge">Old Student</span>
                            <?php endif; ?>
                        </h3>
                        <p><strong>Grade <?php echo htmlspecialchars($current_enrollment['grade_name'] ?? 'N/A'); ?></strong>
                        <?php if(isset($current_enrollment['section_name']) && $current_enrollment['section_name']): ?>
                            - Section <?php echo htmlspecialchars($current_enrollment['section_name']); ?>
                        <?php endif; ?>
                        <?php if(isset($current_enrollment['strand']) && $current_enrollment['strand']): ?>
                            | Strand: <?php echo htmlspecialchars($current_enrollment['strand']); ?>
                        <?php endif; ?>
                        </p>
                        <p class="current-enrollment-date">
                            <i class="fas fa-calendar-alt"></i> School Year: <?php echo htmlspecialchars($current_enrollment['school_year'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div class="current-enrollment-badge">
                        <i class="fas fa-graduation-cap"></i> Active
                    </div>
                </div>
            <?php else: ?>
                <!-- No Current Enrollment Banner -->
                <div class="no-enrollment-banner">
                    <div class="current-enrollment-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="current-enrollment-content">
                        <h3>
                            Not Currently Enrolled
                            <?php if($is_new_student && $total_enrollments == 0): ?>
                                <span class="new-student-badge">New Student</span>
                            <?php elseif($is_new_student): ?>
                                <span class="new-student-badge">New Student - Pending Enrollment</span>
                            <?php endif; ?>
                        </h3>
                        <p>You are not currently enrolled for this school year.</p>
                        <p class="current-enrollment-date">
                            <i class="fas fa-info-circle"></i> Please complete your enrollment to start your journey with us.
                        </p>
                    </div>
                    <a href="enrollment.php" class="enroll-now-btn">
                        <i class="fas fa-graduation-cap"></i> Enroll Now
                    </a>
                </div>
            <?php endif; ?>

            <!-- Stats Summary -->
            <?php if(!empty($enrollment_history)): ?>
            <div class="stats-summary">
                <div class="stat-summary-card">
                    <div class="number"><?php echo $total_enrollments; ?></div>
                    <div class="label">Total Enrollments</div>
                </div>
                <div class="stat-summary-card">
                    <div class="number"><?php echo $enrolled_count; ?></div>
                    <div class="label">Approved</div>
                </div>
                <div class="stat-summary-card">
                    <div class="number"><?php echo $pending_count; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-summary-card">
                    <div class="number"><?php echo $unique_years; ?></div>
                    <div class="label">School Years</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- History List -->
            <div class="history-card">
                <div class="history-card-header">
                    <h3><i class="fas fa-list"></i> Enrollment Records</h3>
                    <span class="history-count"><?php echo $total_enrollments; ?> record(s)</span>
                </div>
                
                <?php if(empty($enrollment_history)): ?>
                    <div class="no-data">
                        <i class="fas fa-file-signature"></i>
                        <h3>No Enrollment History</h3>
                        <p>You haven't made any enrollments yet.</p>
                        <a href="enrollment.php" class="btn-primary" style="display: inline-block; margin-top: 20px;">
                            <i class="fas fa-graduation-cap"></i> Enroll Now
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($enrollment_history as $index => $enrollment): ?>
                        <div class="history-item <?php echo ($index == 0 && ($enrollment['status'] == 'Enrolled' || $enrollment['status'] == 'Approved')) ? 'current-item' : ''; ?>">
                            <div class="year-badge">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo htmlspecialchars($enrollment['school_year'] ?? 'N/A'); ?>
                            </div>
                            <div class="history-details">
                                <h3>
                                    Grade <?php echo htmlspecialchars($enrollment['grade_name'] ?? 'N/A'); ?>
                                    <?php if(isset($enrollment['section_name']) && $enrollment['section_name']): ?>
                                        - Section <?php echo htmlspecialchars($enrollment['section_name']); ?>
                                    <?php endif; ?>
                                    <?php if($index == 0 && ($enrollment['status'] == 'Enrolled' || $enrollment['status'] == 'Approved')): ?>
                                        <span class="current-badge">
                                            <i class="fas fa-check-circle"></i> Current Enrollment
                                        </span>
                                    <?php elseif($index == 0 && $enrollment['status'] == 'Pending'): ?>
                                        <span class="pending-badge">
                                            <i class="fas fa-clock"></i> Latest Application
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <p>
                                    <span><i class="fas fa-calendar-day"></i> Submitted: <?php echo date('F d, Y', strtotime($enrollment['created_at'] ?? 'now')); ?></span>
                                    <?php if(isset($enrollment['strand']) && $enrollment['strand']): ?>
                                        <span><i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="status-badge status-<?php echo strtolower($enrollment['status'] ?? 'pending'); ?>">
                                <?php
                                $status_icon = '';
                                switch(strtolower($enrollment['status'] ?? 'pending')) {
                                    case 'enrolled':
                                    case 'approved':
                                        $status_icon = '<i class="fas fa-check-circle"></i> ';
                                        break;
                                    case 'pending':
                                        $status_icon = '<i class="fas fa-hourglass-half"></i> ';
                                        break;
                                    case 'rejected':
                                        $status_icon = '<i class="fas fa-times-circle"></i> ';
                                        break;
                                }
                                echo $status_icon . ($enrollment['status'] ?? 'Pending');
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Legend / Guide -->
            <?php if(!empty($enrollment_history)): ?>
            <div class="legend-guide">
                <h4><i class="fas fa-info-circle"></i> Status Guide</h4>
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-dot enrolled"></span>
                        <span>Enrolled/Approved - Your enrollment has been approved and you are officially enrolled.</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot pending"></span>
                        <span>Pending - Your enrollment is waiting for approval from the registrar.</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot rejected"></span>
                        <span>Rejected - Your enrollment was not approved. Please contact the registrar.</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot current"></span>
                        <span>Current Enrollment - This is your active enrollment for the current school year.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
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