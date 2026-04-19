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

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: enrollments.php");
    exit();
}

$enrollment_id = $_GET['id'];

// Get enrollment details with all related information including profile picture
$query = "
    SELECT 
        e.*,
        u.id as student_id,
        u.fullname,
        u.email,
        u.id_number,
        u.profile_picture as student_profile_pic,
        u.created_at as student_created_at,
        g.grade_name,
        g.id as grade_id
    FROM enrollments e
    LEFT JOIN users u ON e.student_id = u.id
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.id = ?
";

$stmt = $conn->prepare($query);
$stmt->execute([$enrollment_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$enrollment) {
    header("Location: enrollments.php");
    exit();
}

// Get ALL enrollment history (all enrollments of the same student including current)
$history_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ?
    ORDER BY e.created_at DESC
";
$stmt = $conn->prepare($history_query);
$stmt->execute([$enrollment['student_id']]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status update
if(isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $update_query = "UPDATE enrollments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    
    if($stmt->execute([$new_status, $enrollment_id])) {
        $_SESSION['success_message'] = "Enrollment status updated successfully!";
        header("Location: view_enrollment.php?id=" . $enrollment_id);
        exit();
    } else {
        $error_message = "Error updating status";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollment - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- View Enrollment CSS -->
    <link rel="stylesheet" href="css/view_enrollment.css">
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
                <h1>Enrollment Details</h1>
                <p>View and manage enrollment information</p>
            </div>
            <div class="header-actions">
                <a href="enrollments.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Enrollments
                </a>
            </div>
        </div>

        <!-- Student Information Card -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                    <?php echo $enrollment['status']; ?>
                </span>
            </div>

            <div class="student-info-card">
                <?php if(!empty($enrollment['student_profile_pic']) && file_exists("../" . $enrollment['student_profile_pic'])): ?>
                    <div class="student-avatar-large-img">
                        <img src="../<?php echo $enrollment['student_profile_pic']; ?>?t=<?php echo time(); ?>" alt="Profile">
                    </div>
                <?php else: ?>
                    <div class="student-avatar-large">
                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="student-details">
                    <h2><?php echo htmlspecialchars($enrollment['fullname']); ?></h2>
                    <div class="student-meta">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> ID: <?php echo $enrollment['id_number'] ?? 'Not assigned'; ?></span>
                        <span><i class="fas fa-calendar-alt"></i> Registered: <?php echo date('M d, Y', strtotime($enrollment['student_created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Grade Level</div>
                    <div class="info-value">
                        <i class="fas fa-layer-group"></i>
                        <?php echo htmlspecialchars($enrollment['grade_name']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Strand</div>
                    <div class="info-value">
                        <i class="fas fa-tag"></i>
                        <?php echo $enrollment['strand'] ?: 'Not Applicable'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">School Year</div>
                    <div class="info-value">
                        <i class="fas fa-calendar"></i>
                        <?php echo htmlspecialchars($enrollment['school_year']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Application Date</div>
                    <div class="info-value">
                        <i class="fas fa-clock"></i>
                        <?php echo date('F d, Y h:i A', strtotime($enrollment['created_at'])); ?>
                    </div>
                </div>
            </div>

            <?php if($enrollment['form_138']): ?>
            <div class="info-item">
                <div class="info-label">Form 138 (Report Card)</div>
                <div class="info-value">
                    <a href="../<?php echo $enrollment['form_138']; ?>" target="_blank" class="document-link">
                        <i class="fas fa-file-pdf"></i> View Document
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($enrollment['form_137']): ?>
            <div class="info-item">
                <div class="info-label">Form 137 (Permanent Record)</div>
                <div class="info-value">
                    <a href="../<?php echo $enrollment['form_137']; ?>" target="_blank" class="document-link">
                        <i class="fas fa-file-pdf"></i> View Document
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($enrollment['psa_birth_cert']): ?>
            <div class="info-item">
                <div class="info-label">PSA Birth Certificate</div>
                <div class="info-value">
                    <a href="../<?php echo $enrollment['psa_birth_cert']; ?>" target="_blank" class="document-link">
                        <i class="fas fa-file-pdf"></i> View Document
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if($enrollment['good_moral_cert']): ?>
            <div class="info-item">
                <div class="info-label">Good Moral Certificate</div>
                <div class="info-value">
                    <a href="../<?php echo $enrollment['good_moral_cert']; ?>" target="_blank" class="document-link">
                        <i class="fas fa-file-pdf"></i> View Document
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Update Form -->
            <div class="status-update">
    <div class="status-update-header">
        <i class="fas fa-edit"></i>
        <h4>Update Enrollment Status</h4>
    </div>
    <form method="POST" class="status-form">
        <div class="form-row-status">
            <div class="form-group">
                <label>Change Status</label>
                <select name="status" class="status-select">
                    <option value="Pending" <?php echo $enrollment['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Enrolled" <?php echo $enrollment['status'] == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="Rejected" <?php echo $enrollment['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" name="update_status" class="btn-update">
                <i class="fas fa-save"></i> Update Status
            </button>
        </div>
    </form>
</div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>

       <!-- Enrollment History -->
<div class="detail-card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Enrollment History</h3>
        <span class="badge-count"><?php echo count($history); ?> records</span>
    </div>

    <?php if(count($history) > 0): ?>
        <div class="table-container">
            <table class="data-table history-table">
                <thead>
                    <tr>
                        <th>Enrollment</th>
                        <th>School Year</th>
                        <th>Grade Level</th>
                        <th>Strand</th>
                        <th>Status</th>
                        <th>Applied Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history as $row): ?>
                        <tr class="<?php echo ($row['id'] == $enrollment_id) ? 'current-enrollment' : ''; ?>">
                            <td>
                                <?php if($row['id'] == $enrollment_id): ?>
                                    <span class="current-badge">Current</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                            <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                            <td><?php echo $row['strand'] ?: '—'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-history"></i>
            <p>No enrollment records found for this student.</p>
        </div>
    <?php endif; ?>
</div>
    </main>

    <!-- JavaScript -->
    <script src="js/view_enrollment.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const enrollmentData = {
            id: <?php echo $enrollment_id; ?>,
            status: '<?php echo $enrollment['status']; ?>',
            studentName: '<?php echo addslashes($enrollment['fullname']); ?>'
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