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

// Handle add subject
if(isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $grade_id = $_POST['grade_id'];
    $description = trim($_POST['description']);
    
    if(empty($subject_name) || empty($grade_id)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $check = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? AND grade_id = ?");
            $check->execute([$subject_name, $grade_id]);
            if($check->rowCount() > 0) {
                $error_message = "Subject with this name already exists for this grade level.";
            } else {
                $insert = $conn->prepare("INSERT INTO subjects (subject_name, grade_id, description) VALUES (?, ?, ?)");
                $insert->execute([$subject_name, $grade_id, $description]);
                $success_message = "Subject added successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Handle edit subject
if(isset($_POST['edit_subject'])) {
    $subject_id = $_POST['subject_id'];
    $subject_name = trim($_POST['subject_name']);
    $grade_id = $_POST['grade_id'];
    $description = trim($_POST['description']);
    
    try {
        $update = $conn->prepare("UPDATE subjects SET subject_name = ?, grade_id = ?, description = ? WHERE id = ?");
        $update->execute([$subject_name, $grade_id, $description, $subject_id]);
        $success_message = "Subject updated successfully!";
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        $delete = $conn->prepare("DELETE FROM subjects WHERE id = ?");
        $delete->execute([$delete_id]);
        
        if($delete->rowCount() > 0) {
            $success_message = "Subject deleted successfully!";
        } else {
            $error_message = "Error deleting subject.";
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query (REMOVED attendance subquery)
$query = "
    SELECT s.*, g.grade_name, g.id as grade_id
    FROM subjects s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE 1=1
";

$params = [];

if(!empty($grade_filter)) {
    $query .= " AND s.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($search_query)) {
    $query .= " AND (s.subject_name LIKE ?)";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY g.id, s.subject_name";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by grade level
$subjects_by_grade = [];
foreach($subjects as $subject) {
    $grade_name = $subject['grade_name'];
    $grade_id = $subject['grade_id'];
    if(!isset($subjects_by_grade[$grade_id])) {
        $subjects_by_grade[$grade_id] = [
            'grade_name' => $grade_name,
            'subjects' => []
        ];
    }
    $subjects_by_grade[$grade_id]['subjects'][] = $subject;
}

// Get statistics
$total_subjects_stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects");
$total_subjects_stmt->execute();
$total_subjects = $total_subjects_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get grade levels for filter
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subject count per grade
$grade_count_query = "
    SELECT g.id, g.grade_name, COUNT(s.id) as subject_count
    FROM grade_levels g
    LEFT JOIN subjects s ON g.id = s.grade_id
    GROUP BY g.id
    ORDER BY g.id
";
$grade_counts_stmt = $conn->prepare($grade_count_query);
$grade_counts_stmt->execute();
$grade_counts = $grade_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// REMOVED attendance query - set to 0
$with_attendance = 0;

// Calculate JHS and SHS counts
$jhs_count = 0;
$shs_count = 0;
foreach($grade_counts as $gc) {
    if($gc['id'] <= 4) {
        $jhs_count += $gc['subject_count'];
    } else if($gc['id'] >= 5 && $gc['id'] <= 6) {
        $shs_count += $gc['subject_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects Management - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Subjects Page Specific CSS -->
    <link rel="stylesheet" href="css/subjects.css">
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
                    <li><a href="subjects.php" class="active"><i class="fas fa-book"></i> Subjects</a></li>
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
                <h1>Subjects Management</h1>
                <p>Manage subjects offered per grade level (Grade 7 - Grade 12)</p>
            </div>
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
                <div class="stat-header"><h3>Total Subjects</h3><div class="stat-icon"><i class="fas fa-book"></i></div></div>
                <div class="stat-number"><?php echo $total_subjects; ?></div>
                <div class="stat-label">All subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Junior High School</h3><div class="stat-icon"><i class="fas fa-users"></i></div></div>
                <div class="stat-number"><?php echo $jhs_count; ?></div>
                <div class="stat-label">Grades 7-10</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Senior High School</h3><div class="stat-icon"><i class="fas fa-user-graduate"></i></div></div>
                <div class="stat-number"><?php echo $shs_count; ?></div>
                <div class="stat-label">Grades 11-12</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><h3>Total Grades</h3><div class="stat-icon"><i class="fas fa-layer-group"></i></div></div>
                <div class="stat-number">6</div>
                <div class="stat-label">Grade levels</div>
            </div>
        </div>

        <!-- Grade Level Quick Navigation -->
        <div class="grade-cards">
            <?php 
            $grade_display_names = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
            foreach($grade_levels as $index => $grade): 
                if($grade['id'] >= 1 && $grade['id'] <= 6):
            ?>
                <a href="?grade=<?php echo $grade['id']; ?>" class="grade-card <?php echo $grade_filter == $grade['id'] ? 'active' : ''; ?>">
                    <div class="grade-number"><?php echo $grade_display_names[$index]; ?></div>
                    <div class="grade-name"><?php echo htmlspecialchars($grade['grade_name']); ?></div>
                </a>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>

        <!-- Actions -->
        <div class="actions-bar">
            <form method="GET" action="" id="filterForm" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <div class="filter-group">
                    <select name="grade" class="filter-select" id="gradeSelect">
                        <option value="">All Grades (7-12)</option>
                        <?php foreach($grade_levels as $grade): ?>
                            <?php if($grade['id'] >= 1 && $grade['id'] <= 6): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="subjects.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </form>
            <button class="btn-add" onclick="openAddModal()"><i class="fas fa-plus-circle"></i> Add New Subject</button>
        </div>

        <!-- Subjects by Grade Level -->
        <?php if(count($subjects_by_grade) > 0): ?>
            <?php 
            $grade_order = [1 => 'Grade 7', 2 => 'Grade 8', 3 => 'Grade 9', 4 => 'Grade 10', 5 => 'Grade 11', 6 => 'Grade 12'];
            foreach($grade_order as $grade_id => $grade_display):
                if(isset($subjects_by_grade[$grade_id])):
                    $grade_data = $subjects_by_grade[$grade_id];
            ?>
                <div class="grade-section">
                    <div class="grade-section-header" onclick="toggleGradeSection(this)">
                        <h2><i class="fas fa-layer-group"></i> <?php echo $grade_display; ?> - <?php echo $grade_data['grade_name']; ?></h2>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <span class="badge"><?php echo count($grade_data['subjects']); ?> Subjects</span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                    </div>
                    <div class="grade-section-content">
                        <table class="subjects-table">
                            <thead>
                                <tr><th>Subject</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($grade_data['subjects'] as $subject): ?>
                                    <tr>
                                        <td>
                                            <div class="subject-info">
                                                <div class="subject-icon"><i class="fas fa-book-open"></i></div>
                                                <div class="subject-details">
                                                    <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                                    <?php if(!empty($subject['description'])): ?>
                                                        <span><?php echo htmlspecialchars(substr($subject['description'], 0, 50)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="?delete=<?php echo $subject['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this subject?')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php 
                endif;
            endforeach; 
            ?>
        <?php else: ?>
            <div class="grade-section">
                <div class="no-data">
                    <i class="fas fa-book"></i>
                    <h3>No Subjects Found</h3>
                    <p>Click "Add New Subject" to get started.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add Subject Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Subject</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="" id="addSubjectForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Subject Name *</label>
                        <input type="text" name="subject_name" placeholder="e.g., Mathematics" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level *</label>
                        <select name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <?php if($grade['id'] >= 1 && $grade['id'] <= 6): ?>
                                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="3" placeholder="Brief description of the subject..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_subject" class="btn-save">Add Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Subject</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" id="editSubjectForm">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Subject Name *</label>
                        <input type="text" name="subject_name" id="edit_subject_name" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level *</label>
                        <select name="grade_id" id="edit_grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <?php if($grade['id'] >= 1 && $grade['id'] <= 6): ?>
                                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_subject" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Subject Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Subject Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Content loaded via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/subjects.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const subjectData = {
            subjects: <?php echo json_encode($subjects); ?>,
            gradeLevels: <?php 
                $gradeMap = [];
                foreach($grade_levels as $g) {
                    $gradeMap[$g['id']] = $g['grade_name'];
                }
                echo json_encode($gradeMap);
            ?>
        };
    </script>
</body>
</html>