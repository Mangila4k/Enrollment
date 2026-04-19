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

// Handle add section
if(isset($_POST['add_section'])) {
    $section_name = trim($_POST['section_name']);
    $grade_id = $_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : null;
    
    if(empty($section_name) || empty($grade_id)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $check = $conn->prepare("SELECT id FROM sections WHERE section_name = ? AND grade_id = ?");
            $check->execute([$section_name, $grade_id]);
            if($check->rowCount() > 0) {
                $error_message = "Section already exists for this grade level.";
            } else {
                $insert = $conn->prepare("INSERT INTO sections (section_name, grade_id, adviser_id) VALUES (?, ?, ?)");
                $insert->execute([$section_name, $grade_id, $adviser_id]);
                $success_message = "Section added successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Handle edit section
if(isset($_POST['edit_section'])) {
    $section_id = $_POST['section_id'];
    $section_name = trim($_POST['section_name']);
    $grade_id = $_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : null;
    
    try {
        $update = $conn->prepare("UPDATE sections SET section_name = ?, grade_id = ?, adviser_id = ? WHERE id = ?");
        $update->execute([$section_name, $grade_id, $adviser_id, $section_id]);
        $success_message = "Section updated successfully!";
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        $check_enrollments = $conn->prepare("SELECT id FROM enrollments WHERE section_id = ?");
        $check_enrollments->execute([$delete_id]);
        
        if($check_enrollments->rowCount() > 0) {
            $error_message = "Cannot delete section because it has enrolled students.";
        } else {
            $delete = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $delete->execute([$delete_id]);
            
            if($delete->rowCount() > 0) {
                $success_message = "Section deleted successfully!";
            } else {
                $error_message = "Error deleting section.";
            }
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all sections with details
$sections_stmt = $conn->prepare("
    SELECT s.id, s.section_name, g.grade_name, u.fullname as adviser, u.id as adviser_id
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY g.id, s.section_name
");
$sections_stmt->execute();
$sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade levels for filter
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers for adviser selection
$teachers_stmt = $conn->prepare("SELECT id, fullname FROM users WHERE role = 'Teacher' ORDER BY fullname");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== STATISTICS ==========
$total_sections = count($sections);

// Count sections with advisers
$with_adviser = 0;
$without_adviser = 0;
foreach($sections as $sec) {
    if($sec['adviser']) {
        $with_adviser++;
    } else {
        $without_adviser++;
    }
}

// Count sections per grade level
$sections_per_grade = [];
foreach($grade_levels as $grade) {
    $count = 0;
    foreach($sections as $sec) {
        if($sec['grade_name'] == $grade['grade_name']) {
            $count++;
        }
    }
    $sections_per_grade[$grade['grade_name']] = $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections Management - PLS NHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/sections.css">
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
                    <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i> Sections</a></li>
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
                <h1>Sections Management</h1>
                <p>Manage class sections and adviser assignments</p>
            </div>
        </div>

        <?php if($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Total Sections</h3>
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                </div>
                <div class="stat-number"><?php echo $total_sections; ?></div>
                <div class="stat-label">All sections</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>With Adviser</h3>
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="stat-number"><?php echo $with_adviser; ?></div>
                <div class="stat-label">Has class adviser</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>No Adviser</h3>
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                </div>
                <div class="stat-number"><?php echo $without_adviser; ?></div>
                <div class="stat-label">Needs adviser</div>
            </div>
        </div>

        <!-- Grade Level Summary -->
        <div class="grade-summary">
            <?php foreach($grade_levels as $grade): ?>
                <div class="grade-summary-item">
                    <span class="grade-name"><?php echo $grade['grade_name']; ?></span>
                    <span class="grade-count"><?php echo $sections_per_grade[$grade['grade_name']] ?? 0; ?> sections</span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="actions-bar">
            <div class="filter-group">
                <select class="filter-select" id="gradeFilter">
                    <option value="">All Grade Levels</option>
                    <?php foreach($grade_levels as $grade): ?>
                        <option value="<?php echo htmlspecialchars($grade['grade_name']); ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" id="adviserFilter">
                    <option value="">All Advisers</option>
                    <option value="assigned">With Adviser</option>
                    <option value="unassigned">No Adviser</option>
                </select>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search sections...">
            </div>
            <button class="btn-add" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Add New Section</button>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-layer-group"></i> Section List</h3>
                <span class="badge-count">Total: <?php echo count($sections); ?> sections</span>
            </div>
            <div class="table-container">
                <table class="sections-table" id="sectionsTable">
                    <thead>
                        <tr><th>Section</th><th>Grade Level</th><th>Adviser</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($sections) > 0): ?>
                            <?php foreach($sections as $sec): ?>
                                <tr>
                                    <td>
                                        <div class="section-info">
                                            <div class="section-icon"><i class="fas fa-users"></i></div>
                                            <div class="section-details">
                                                <h4><?php echo htmlspecialchars($sec['section_name']); ?></h4>
                                                <span>ID: <?php echo $sec['id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="grade-tag"><?php echo htmlspecialchars($sec['grade_name'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <?php if($sec['adviser']): ?>
                                            <div class="adviser-info">
                                                <?php 
                                                // Get adviser profile picture if exists
                                                $adviser_profile_pic = null;
                                                if($sec['adviser_id']) {
                                                    $adviser_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                                                    $adviser_stmt->execute([$sec['adviser_id']]);
                                                    $adviser_data = $adviser_stmt->fetch(PDO::FETCH_ASSOC);
                                                    $adviser_profile_pic = $adviser_data['profile_picture'] ?? null;
                                                }
                                                ?>
                                                
                                                <?php if($adviser_profile_pic && file_exists("../" . $adviser_profile_pic)): ?>
                                                    <div class="adviser-avatar-img">
                                                        <img src="../<?php echo $adviser_profile_pic; ?>?t=<?php echo time(); ?>" alt="Adviser">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="adviser-avatar">
                                                        <?php echo strtoupper(substr($sec['adviser'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="adviser-name"><?php echo htmlspecialchars($sec['adviser']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-adviser">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn schedule" onclick="openScheduleModal(<?php echo $sec['id']; ?>, '<?php echo htmlspecialchars($sec['section_name']); ?>')" title="Manage Schedule"><i class="fas fa-calendar-alt"></i></button>
                                            <button class="action-btn edit" onclick="openEditModal(<?php echo $sec['id']; ?>, '<?php echo htmlspecialchars($sec['section_name']); ?>', '<?php echo $sec['grade_name'] ?? ''; ?>', '<?php echo $sec['adviser_id'] ?? ''; ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                            <a href="?delete=<?php echo $sec['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this section?')" title="Delete"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4"><div class="no-data"><i class="fas fa-layer-group"></i><h3>No Sections Found</h3><p>Click "Add New Section" to get started.</p></div></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Section Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Section</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" placeholder="e.g., Section A - STEM" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Adviser (Optional)</label>
                        <select name="adviser_id">
                            <option value="">Select Adviser</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_section" class="btn-save">Add Section</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Section</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" id="edit_section_name" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" id="edit_grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Adviser</label>
                        <select name="adviser_id" id="edit_adviser_id">
                            <option value="">Select Adviser</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_section" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/sections.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const sectionData = {
            gradeLevels: <?php 
                $gradeMap = [];
                foreach($grade_levels as $g) {
                    $gradeMap[$g['grade_name']] = $g['id'];
                }
                echo json_encode($gradeMap);
            ?>
        };
    </script>
</body>
</html>