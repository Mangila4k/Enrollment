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
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Get student details with all fields including profile_picture
$query = "SELECT * FROM users WHERE id = ? AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$student) {
    header("Location: students.php");
    exit();
}

// Get student's enrollment history with grade level info
$enrollments_query = "
    SELECT e.*, g.grade_name 
    FROM enrollments e 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    WHERE e.student_id = ? 
    ORDER BY e.created_at DESC
";
$stmt = $conn->prepare($enrollments_query);
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current_enrollment = !empty($enrollments) ? $enrollments[0] : null;

// Function to check if strand should be shown (only for Grade 11 and 12)
function shouldShowStrand($grade_name) {
    return in_array($grade_name, ['Grade 11', 'Grade 12']);
}

// Get account creation info
$account_created = $student['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));

// Calculate age from birthdate
$age = null;
if(!empty($student['birthdate'])) {
    $birthDate = new DateTime($student['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}

// Get student profile picture
$student_profile_picture = $student['profile_picture'] ?? null;

// Handle delete request
if(isset($_GET['delete']) && $_GET['delete'] == $student_id) {
    try {
        $conn->beginTransaction();
        
        $delete_attendance = "DELETE FROM attendance WHERE student_id = ?";
        $stmt = $conn->prepare($delete_attendance);
        $stmt->execute([$student_id]);
        
        $delete_enrollments = "DELETE FROM enrollments WHERE student_id = ?";
        $stmt = $conn->prepare($delete_enrollments);
        $stmt->execute([$student_id]);
        
        $delete_student = "DELETE FROM users WHERE id = ? AND role = 'Student'";
        $stmt = $conn->prepare($delete_student);
        $stmt->execute([$student_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Student deleted successfully!";
        header("Location: students.php");
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting student: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- View Student CSS -->
    <link rel="stylesheet" href="css/view_student.css">
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
                <h1>Student Profile</h1>
                <p>View complete student information and records</p>
            </div>
            <a href="students.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Students</a>
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

        <!-- Student Profile Card -->
        <div class="profile-card">
            <div class="profile-avatar-large">
                <?php if($student_profile_picture && file_exists("../" . $student_profile_picture)): ?>
                    <img src="../<?php echo $student_profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
                
                <div class="profile-meta">
                    <span class="profile-meta-item">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-id-card"></i> ID: <?php echo $student['id_number'] ?? 'Not assigned'; ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($student['created_at'])); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                    </span>
                </div>

                <?php if($current_enrollment): ?>
                    <div style="margin-bottom: 15px;">
                        <span class="profile-badge badge-<?php echo strtolower($current_enrollment['status']); ?>">
                            <i class="fas fa-<?php echo $current_enrollment['status'] == 'Enrolled' ? 'check-circle' : 'clock'; ?>"></i>
                            Current Status: <?php echo $current_enrollment['status']; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Student
                    </a>
                    <?php if(!$current_enrollment || $current_enrollment['status'] != 'Enrolled'): ?>
                        <a href="enroll_student.php?id=<?php echo $student_id; ?>" class="btn-enroll">
                            <i class="fas fa-user-plus"></i> Enroll Student
                        </a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $student_id; ?>" class="btn-delete" onclick="return confirmDelete()">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>

        <!-- Personal Information Section -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">First Name</div>
                    <div class="info-value">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($student['firstname'] ?? 'Not specified'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Middle Name</div>
                    <div class="info-value">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($student['middlename'] ?? 'Not specified'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Name</div>
                    <div class="info-value">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($student['lastname'] ?? 'Not specified'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Birthdate</div>
                    <div class="info-value">
                        <i class="fas fa-cake-candles"></i>
                        <?php echo !empty($student['birthdate']) ? date('F d, Y', strtotime($student['birthdate'])) : 'Not specified'; ?>
                        <?php if($age): ?>
                            <span style="font-size: 12px; color: var(--text-gray);">(Age: <?php echo $age; ?> years)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value">
                        <i class="fas fa-<?php echo $student['gender'] == 'Male' ? 'mars' : ($student['gender'] == 'Female' ? 'venus' : 'genderless'); ?>"></i>
                        <?php echo htmlspecialchars($student['gender'] ?? 'Not specified'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Student ID Number</div>
                    <div class="info-value">
                        <i class="fas fa-qrcode"></i>
                        <?php echo htmlspecialchars($student['id_number'] ?? 'Not assigned'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Enrollment Details -->
        <?php if($current_enrollment): ?>
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap"></i> Current Enrollment</h3>
                <a href="view_enrollment.php?id=<?php echo $current_enrollment['id']; ?>" class="view-link">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Grade Level</div>
                    <div class="info-value">
                        <i class="fas fa-layer-group"></i>
                        <?php echo htmlspecialchars($current_enrollment['grade_name']); ?>
                    </div>
                </div>
                <?php if(shouldShowStrand($current_enrollment['grade_name']) && !empty($current_enrollment['strand'])): ?>
                <div class="info-item">
                    <div class="info-label">Strand</div>
                    <div class="info-value">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($current_enrollment['strand']); ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">School Year</div>
                    <div class="info-value">
                        <i class="fas fa-calendar"></i>
                        <?php echo htmlspecialchars($current_enrollment['school_year']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Enrollment Date</div>
                    <div class="info-value">
                        <i class="fas fa-clock"></i>
                        <?php echo date('F d, Y', strtotime($current_enrollment['created_at'])); ?>
                    </div>
                </div>
            </div>

            <?php if(isset($current_enrollment['form_138']) && $current_enrollment['form_138']): ?>
            <div class="info-item" style="margin-top: 15px;">
                <div class="info-label">Form 138</div>
                <div class="info-value">
                    <i class="fas fa-file-pdf"></i>
                    <a href="../<?php echo $current_enrollment['form_138']; ?>" target="_blank" class="document-link">
                        View Document
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Enrollment History -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Enrollment History</h3>
            </div>

            <?php if(!empty($enrollments)): ?>
            <div class="table-container">
                <table class="enrollments-table">
                    <thead>
                        <tr>
                            <th>School Year</th>
                            <th>Grade Level</th>
                            <th>Strand</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($enrollments as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                                <td>
                                    <?php if(shouldShowStrand($row['grade_name']) && !empty($row['strand'])): ?>
                                        <span class="strand-tag"><?php echo htmlspecialchars($row['strand']); ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="view-link">
                                        View <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-file-signature"></i>
                <h3>No Enrollment Records</h3>
                <p>This student has no enrollment history.</p>
                <a href="enroll_student.php?id=<?php echo $student_id; ?>" class="enroll-now-link">
                    Enroll Student Now <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="js/view_student.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const studentData = {
            id: <?php echo $student_id; ?>,
            name: '<?php echo htmlspecialchars($student['fullname']); ?>',
            email: '<?php echo htmlspecialchars($student['email']); ?>',
            hasProfilePicture: <?php echo ($student_profile_picture && file_exists("../" . $student_profile_picture)) ? 'true' : 'false'; ?>
        };
    </script>
</body>
</html>