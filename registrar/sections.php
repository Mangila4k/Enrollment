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

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // First, remove section assignment from enrollments
    $stmt = $conn->prepare("UPDATE enrollments SET section_id = NULL WHERE section_id = ?");
    $stmt->execute([$delete_id]);
    
    // Then delete schedules
    $stmt = $conn->prepare("DELETE FROM class_schedules WHERE section_id = ?");
    $stmt->execute([$delete_id]);
    
    // Delete the section
    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
    if($stmt->execute([$delete_id])) {
        $success_message = "Section deleted successfully! Students have been unassigned.";
    } else {
        $error_message = "Error deleting section: " . $conn->errorInfo()[2];
    }
}

// Handle add section
if(isset($_POST['add_section'])) {
    $section_name = $_POST['section_name'];
    $grade_id = (int)$_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
    
    $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_id, adviser_id) VALUES (?, ?, ?)");
    
    if($stmt->execute([$section_name, $grade_id, $adviser_id])) {
        $success_message = "Section added successfully!";
    } else {
        $error_message = "Error adding section: " . $conn->errorInfo()[2];
    }
}

// Handle edit section with grade change validation
if(isset($_POST['edit_section'])) {
    $section_id = (int)$_POST['section_id'];
    $section_name = $_POST['section_name'];
    $grade_id = (int)$_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : null;
    
    try {
        // Get current section info
        $current_section = $conn->prepare("SELECT grade_id, section_name FROM sections WHERE id = ?");
        $current_section->execute([$section_id]);
        $current = $current_section->fetch(PDO::FETCH_ASSOC);
        $old_grade_id = $current['grade_id'];
        $old_section_name = $current['section_name'];
        
        // Get grade names for the message
        $grade_names = [];
        $old_grade_stmt = $conn->prepare("SELECT grade_name FROM grade_levels WHERE id = ?");
        $old_grade_stmt->execute([$old_grade_id]);
        $grade_names['old'] = $old_grade_stmt->fetch(PDO::FETCH_ASSOC)['grade_name'];
        
        $new_grade_stmt = $conn->prepare("SELECT grade_name FROM grade_levels WHERE id = ?");
        $new_grade_stmt->execute([$grade_id]);
        $grade_names['new'] = $new_grade_stmt->fetch(PDO::FETCH_ASSOC)['grade_name'];
        
        // Update section
        $update = $conn->prepare("UPDATE sections SET section_name = ?, grade_id = ?, adviser_id = ? WHERE id = ?");
        $update->execute([$section_name, $grade_id, $adviser_id, $section_id]);
        
        $removed_count = 0;
        
        // If grade level changed, remove students that don't match the new grade
        if($old_grade_id != $grade_id) {
            // Get all students currently in this section
            $get_students = $conn->prepare("
                SELECT e.id, e.student_id, e.grade_id 
                FROM enrollments e 
                WHERE e.section_id = ? AND e.status = 'Enrolled'
            ");
            $get_students->execute([$section_id]);
            $students_in_section = $get_students->fetchAll(PDO::FETCH_ASSOC);
            
            // Remove students whose enrollment grade doesn't match the new section grade
            foreach($students_in_section as $student) {
                if($student['grade_id'] != $grade_id) {
                    $remove_student = $conn->prepare("
                        UPDATE enrollments 
                        SET section_id = NULL 
                        WHERE id = ? AND section_id = ?
                    ");
                    $remove_student->execute([$student['id'], $section_id]);
                    $removed_count++;
                }
            }
            
            if($removed_count > 0) {
                $success_message = "Section '<strong>$old_section_name</strong>' updated from <strong>{$grade_names['old']}</strong> to <strong>{$grade_names['new']}</strong>!<br>
                                   <i class='fas fa-user-minus'></i> <strong>$removed_count student(s)</strong> were removed because their enrollment grade doesn't match <strong>{$grade_names['new']}</strong>.<br>
                                   <i class='fas fa-info-circle'></i> Only students enrolled in <strong>{$grade_names['new']}</strong> can be assigned to this section.";
            } else {
                $success_message = "Section updated from <strong>{$grade_names['old']}</strong> to <strong>{$grade_names['new']}</strong> successfully! No students were affected.";
            }
        } else {
            $success_message = "Section updated successfully!";
        }
        
    } catch(PDOException $e) {
        $error_message = "Error updating section: " . $e->getMessage();
    }
}

// Get all sections with details
$sections_query = "
    SELECT s.*, g.grade_name, g.id as grade_id, u.fullname as adviser_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'Enrolled') as student_count
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY g.grade_name, s.section_name
";
$stmt = $conn->query($sections_query);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade levels for filter
$stmt = $conn->query("SELECT * FROM grade_levels ORDER BY grade_name");
$grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers for adviser selection
$stmt = $conn->query("SELECT id, fullname FROM users WHERE role = 'Teacher' ORDER BY fullname");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get section for editing if ID is provided
$edit_section = null;
$current_grade_name = '';
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current grade name
    if($edit_section && $edit_section['grade_id']) {
        $grade_stmt = $conn->prepare("SELECT grade_name FROM grade_levels WHERE id = ?");
        $grade_stmt->execute([$edit_section['grade_id']]);
        $current_grade = $grade_stmt->fetch(PDO::FETCH_ASSOC);
        $current_grade_name = $current_grade ? $current_grade['grade_name'] : '';
    }
}

// Calculate stats
$total_sections = count($sections);
$total_students_in_sections = 0;
$sections_with_adviser = 0;

foreach($sections as $sec) {
    $total_students_in_sections += $sec['student_count'];
    if($sec['adviser_name']) $sections_with_adviser++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Management - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Sections CSS -->
    <link rel="stylesheet" href="css/sections.css">
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
                        <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i> Sections</a></li>
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Section Management</h1>
                    <p>Manage class sections and assign advisers</p>
                </div>
                <div class="header-actions">
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Section
                    </button>
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

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Sections</h3>
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Active sections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Enrolled Students</h3>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $total_students_in_sections; ?></div>
                    <div class="stat-label">Total students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>With Adviser</h3>
                        <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $sections_with_adviser; ?></div>
                    <div class="stat-label">Sections with adviser</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Current Year</h3>
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                    <div class="stat-number"><?php echo date('Y'); ?></div>
                    <div class="stat-label">School year</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Filter by Grade Level</label>
                    <select id="gradeFilter" class="filter-select">
                        <option value="">All Grades</option>
                        <?php foreach($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['grade_name']; ?>"><?php echo $grade['grade_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search Section</label>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Section name...">
                    </div>
                </div>
            </div>

            <!-- Sections Grid -->
            <div class="sections-grid" id="sectionsGrid">
                <?php if(count($sections) > 0): ?>
                    <?php foreach($sections as $section): ?>
                        <div class="section-card" data-grade="<?php echo $section['grade_name']; ?>">
                            <div class="section-header">
                                <div class="section-icon"><i class="fas fa-users"></i></div>
                                <span class="section-badge"><?php echo $section['student_count']; ?> Students</span>
                            </div>
                            <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                            <div class="grade-level"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></div>
                            
                            <div class="adviser-info">
                                <div class="adviser-avatar">
                                    <?php echo $section['adviser_name'] ? strtoupper(substr($section['adviser_name'], 0, 1)) : '?'; ?>
                                </div>
                                <div class="adviser-details">
                                    <div class="adviser-label">Class Adviser</div>
                                    <div class="adviser-name"><?php echo $section['adviser_name'] ?? 'Not Assigned'; ?></div>
                                </div>
                            </div>

                            <div class="stats-row">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $section['student_count']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">-</div>
                                    <div class="stat-label">Subjects</div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <a href="section_students.php?id=<?php echo $section['id']; ?>" class="btn-action btn-students" title="View Students">
                                    <i class="fas fa-users"></i> Students
                                </a>
                                <a href="?edit=<?php echo $section['id']; ?>" class="btn-action btn-edit" title="Edit Section">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?delete=<?php echo $section['id']; ?>" class="btn-action btn-delete" title="Delete Section" 
                                   onclick="return confirmDelete(<?php echo $section['student_count']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-layer-group"></i>
                        <h3>No Sections Found</h3>
                        <p>Click "Add New Section" to create your first section.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Section</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" placeholder="e.g., Grade 7 - Section A" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo $grade['grade_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class Adviser</label>
                        <select name="adviser_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
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
    <?php if($edit_section): ?>
    <div class="modal active" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Section</h3>
                <a href="sections.php" class="close-modal">&times;</a>
            </div>
            <form method="POST">
                <input type="hidden" name="section_id" value="<?php echo $edit_section['id']; ?>">
                <div class="modal-body">
                    <?php
                    // Check if section has students and show warning
                    $check_students = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE section_id = ? AND status = 'Enrolled'");
                    $check_students->execute([$edit_section['id']]);
                    $student_count = $check_students->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if($student_count > 0):
                    ?>
                    <div class="warning-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This section has <strong><?php echo $student_count; ?> enrolled student(s)</strong>. 
                        Changing the grade level will automatically remove students whose enrollment grade doesn't match the new grade.
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" value="<?php echo htmlspecialchars($edit_section['section_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" id="edit_grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo ($grade['id'] == $edit_section['grade_id']) ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class Adviser</label>
                        <select name="adviser_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher['id'] == $edit_section['adviser_id']) ? 'selected' : ''; ?>>
                                    <?php echo $teacher['fullname']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="sections.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="edit_section" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sections JS -->
    <script src="js/sections.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const sectionsData = {
            totalSections: <?php echo $total_sections; ?>,
            totalStudents: <?php echo $total_students_in_sections; ?>,
            sectionsWithAdviser: <?php echo $sections_with_adviser; ?>
        };
        
        const currentGradeName = '<?php echo addslashes($current_grade_name); ?>';
        
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