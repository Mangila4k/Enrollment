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
        // Check if teacher is assigned as adviser
        $check_adviser = $conn->prepare("SELECT id FROM sections WHERE adviser_id = ?");
        $check_adviser->execute([$delete_id]);
        
        if($check_adviser->rowCount() > 0) {
            $error_message = "Cannot delete teacher because they are assigned as adviser to a section.";
        } else {
            $delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'Teacher'");
            $delete->execute([$delete_id]);
            
            if($delete->rowCount() > 0) {
                $success_message = "Teacher deleted successfully!";
            } else {
                $error_message = "Error deleting teacher.";
            }
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all teachers with their assigned sections
$teachers_stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT s.id) as section_count,
           GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as sections
    FROM users u
    LEFT JOIN sections s ON u.id = s.adviser_id
    WHERE u.role = 'Teacher'
    GROUP BY u.id
    ORDER BY u.fullname ASC
");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_teachers = count($teachers);

// Get teachers with sections
$with_sections_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    INNER JOIN sections s ON u.id = s.adviser_id 
    WHERE u.role = 'Teacher'
");
$with_sections_stmt->execute();
$with_sections = $with_sections_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$without_sections = $total_teachers - $with_sections;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Management - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/teachers.css">
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
                <h1>Teachers Management</h1>
                <p>Manage faculty members and their section assignments</p>
            </div>
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
                <div class="stat-header"><h3>Total Teachers</h3><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div></div>
                <div class="stat-number"><?php echo $total_teachers; ?></div>
                <div class="stat-label">Faculty members</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>With Sections</h3><div class="stat-icon"><i class="fas fa-layer-group"></i></div></div>
                <div class="stat-number"><?php echo $with_sections; ?></div>
                <div class="stat-label">Class advisers</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Without Sections</h3><div class="stat-icon"><i class="fas fa-user-clock"></i></div></div>
                <div class="stat-number"><?php echo $without_sections; ?></div>
                <div class="stat-label">Available teachers</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions-bar">
            <div class="filter-group">
                <select class="filter-select" id="statusFilter">
                    <option value="">All Teachers</option>
                    <option value="with-sections">With Sections</option>
                    <option value="without-sections">Without Sections</option>
                </select>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name or email...">
            </div>
            <a href="add_teacher.php" class="btn-add"><i class="fas fa-plus-circle"></i> Add New Teacher</a>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-chalkboard-user"></i> Faculty List</h3>
                <span class="badge-count">Total: <?php echo $total_teachers; ?> teachers</span>
            </div>
            <div class="table-container">
                <table class="teachers-table" id="teachersTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Teacher</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-id-card"></i> ID Number</th>
                            <th><i class="fas fa-layer-group"></i> Sections Advised</th>
                            <th><i class="fas fa-chart-line"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($teachers) > 0): ?>
                            <?php foreach($teachers as $teacher): 
                                // Get teacher profile picture
                                $teacher_profile_pic = $teacher['profile_picture'] ?? null;
                            ?>
                                <tr>
                                    <td>
                                        <div class="teacher-info">
                                            <?php if($teacher_profile_pic && file_exists("../" . $teacher_profile_pic)): ?>
                                                <div class="teacher-avatar-img">
                                                    <img src="../<?php echo $teacher_profile_pic; ?>?t=<?php echo time(); ?>" alt="Teacher">
                                                </div>
                                            <?php else: ?>
                                                <div class="teacher-avatar">
                                                    <?php echo strtoupper(substr(trim($teacher['fullname']), 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="teacher-details">
                                                <h4><?php echo htmlspecialchars($teacher['fullname']); ?></h4>
                                                <span><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo !empty($teacher['id_number']) ? htmlspecialchars($teacher['id_number']) : 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($teacher['section_count'] > 0): ?>
                                            <div class="section-tags">
                                                <?php 
                                                $sections = !empty($teacher['sections']) ? explode(', ', $teacher['sections']) : [];
                                                foreach($sections as $section): 
                                                ?>
                                                    <span class="section-tag"><?php echo htmlspecialchars($section); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No sections assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($teacher['section_count'] > 0): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Adviser</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><i class="fas fa-clock"></i> Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_teacher.php?id=<?php echo $teacher['id']; ?>" class="action-btn view" title="View Teacher">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="action-btn edit" title="Edit Teacher">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $teacher['id']; ?>" class="action-btn delete" title="Delete Teacher" onclick="return confirm('Delete this teacher?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <i class="fas fa-chalkboard-user"></i>
                                        <h3>No Teachers Found</h3>
                                        <p>Click "Add New Teacher" to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#teachersTable tbody tr');
            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Filter by status
        document.getElementById('statusFilter').addEventListener('change', function() {
            let filterValue = this.value;
            let rows = document.querySelectorAll('#teachersTable tbody tr');
            rows.forEach(row => {
                let sectionsCell = row.cells[3]?.textContent || '';
                if (filterValue === 'with-sections') {
                    row.style.display = sectionsCell.includes('No sections') ? 'none' : '';
                } else if (filterValue === 'without-sections') {
                    row.style.display = sectionsCell.includes('No sections') ? '' : 'none';
                } else {
                    row.style.display = '';
                }
            });
        });

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