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
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Teacher'";
$stmt = $conn->prepare($query);
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$teacher) {
    header("Location: teachers.php");
    exit();
}

// Get teacher's profile picture
$teacher_profile_picture = $teacher['profile_picture'] ?? null;

// Get teacher's advisory sections
$sections_query = "
    SELECT s.*, g.grade_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = s.id AND e.status = 'Enrolled') as student_count
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($sections_query);
$stmt->execute([$teacher_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects taught by teacher (from class_schedules)
$subjects_query = "
    SELECT DISTINCT sub.*, g.grade_name
    FROM subjects sub
    LEFT JOIN grade_levels g ON sub.grade_id = g.id
    INNER JOIN class_schedules cs ON cs.subject_id = sub.id
    WHERE cs.teacher_id = ?
    ORDER BY g.id, sub.subject_name
";
$stmt = $conn->prepare($subjects_query);
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// REMOVED: attendance query since table doesn't exist yet
// Get recent activities will be shown once attendance table is created
$activities = [];

// Calculate statistics
$total_sections = count($sections);
$total_students = 0;
foreach($sections as $section) {
    $total_students += $section['student_count'];
}

$account_created = $teacher['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));

// Handle delete request
if(isset($_GET['delete']) && $_GET['delete'] == $teacher_id) {
    try {
        $conn->beginTransaction();
        
        $update_sections = "UPDATE sections SET adviser_id = NULL WHERE adviser_id = ?";
        $stmt = $conn->prepare($update_sections);
        $stmt->execute([$teacher_id]);
        
        $delete_schedules = "DELETE FROM class_schedules WHERE teacher_id = ?";
        $stmt = $conn->prepare($delete_schedules);
        $stmt->execute([$teacher_id]);
        
        $delete_teacher = "DELETE FROM users WHERE id = ? AND role = 'Teacher'";
        $stmt = $conn->prepare($delete_teacher);
        $stmt->execute([$teacher_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Teacher deleted successfully!";
        header("Location: teachers.php");
        exit();
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting teacher: " . $e->getMessage();
        header("Location: view_teacher.php?id=" . $teacher_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teacher - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/view_teacher.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #0B4F2E;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-large .avatar-initial {
            font-size: 48px;
            font-weight: bold;
            color: white;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-info h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .profile-meta-item i {
            color: #0B4F2E;
            width: 18px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 15px;
        }
        
        .btn-edit {
            background: #0B4F2E;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-edit:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .detail-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            color: #333;
        }
        
        .card-header h3 i {
            color: #0B4F2E;
        }
        
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .section-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .section-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .section-card h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .section-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .section-details span {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .section-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
        }
        
        .section-stat {
            text-align: center;
        }
        
        .section-stat .value {
            font-size: 20px;
            font-weight: 700;
            color: #0B4F2E;
        }
        
        .section-stat .label {
            font-size: 11px;
            color: #666;
        }
        
        .subjects-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .subject-tag {
            background: #e8f4f8;
            color: #0c5460;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .subject-tag .subject-grade {
            color: #17a2b8;
            font-size: 11px;
        }
        
        .view-link {
            color: #0B4F2E;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-link:hover {
            text-decoration: underline;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-data i {
            font-size: 48px;
            opacity: 0.3;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        .back-btn {
            background: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-card {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .sections-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
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
                    <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-user"></i> Teachers</a></li>
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
                <h1>Teacher Profile</h1>
                <p>View complete teacher information and assignments</p>
            </div>
            <a href="teachers.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Teachers</a>
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

        <!-- Teacher Profile Card -->
        <div class="profile-card">
            <div class="profile-avatar-large">
                <?php if($teacher_profile_picture && file_exists("../" . $teacher_profile_picture)): ?>
                    <img src="../<?php echo $teacher_profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($teacher['fullname']); ?></h2>
                
                <div class="profile-meta">
                    <span class="profile-meta-item">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-id-card"></i> ID: <?php echo $teacher['id_number'] ?? 'Not assigned'; ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($teacher['created_at'])); ?>
                    </span>
                    <span class="profile-meta-item">
                        <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                    </span>
                </div>

                <div>
                    <span class="profile-badge">
                        <i class="fas fa-chalkboard-user"></i> Teacher
                    </span>
                </div>

                <div class="action-buttons">
                    <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Teacher
                    </a>
                    <a href="?delete=<?php echo $teacher_id; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this teacher? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Advisory Sections</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students Under Advisory</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo count($subjects); ?></div>
                    <div class="stat-label">Subjects Taught</div>
                </div>
            </div>
        </div>

        <!-- Advisory Sections -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-layer-group"></i> Advisory Sections</h3>
                <a href="sections.php?adviser=<?php echo $teacher_id; ?>" class="view-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if(!empty($sections)): ?>
                <div class="sections-grid">
                    <?php foreach($sections as $section): ?>
                        <div class="section-card">
                            <h4><i class="fas fa-users"></i> <?php echo htmlspecialchars($section['section_name']); ?></h4>
                            <div class="section-details">
                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                            </div>
                            <div class="section-stats">
                                <div class="section-stat">
                                    <div class="value"><?php echo $section['student_count']; ?></div>
                                    <div class="label">Students</div>
                                </div>
                            </div>
                            <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-link">
                                View Section <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Advisory Sections</h3>
                    <p>This teacher is not assigned as adviser to any section.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Subjects -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> Subjects Taught</h3>
            </div>

            <?php if(!empty($subjects)): ?>
                <div class="subjects-list">
                    <?php foreach($subjects as $subject): ?>
                        <span class="subject-tag">
                            <i class="fas fa-book-open"></i>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                            <span class="subject-grade">(<?php echo $subject['grade_name']; ?>)</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data" style="padding: 20px;">
                    <i class="fas fa-book"></i>
                    <p>No subjects assigned to this teacher.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activities (Commented out until attendance table is created) -->
        <?php if(false): // Temporarily disabled until attendance table exists ?>
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Attendance Activities</h3>
            </div>
            <div class="no-data" style="padding: 20px;">
                <i class="fas fa-calendar-times"></i>
                <p>Attendance module will be available soon.</p>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- JavaScript -->
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
        
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
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.3s';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 300);
            });
        }, 5000);
        
        // Confirm delete function
        function confirmDelete() {
            return confirm('Are you sure you want to delete this teacher? This action cannot be undone.');
        }
    </script>
</body>
</html>