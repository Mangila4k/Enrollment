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
$errors = [];

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

if(isset($_SESSION['error_messages'])) {
    $errors = $_SESSION['error_messages'];
    unset($_SESSION['error_messages']);
}

// Get form data from session if there was an error
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
unset($_SESSION['form_data']);

// Get grade levels for dropdown
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers for adviser dropdown
$teachers_stmt = $conn->prepare("SELECT id, fullname FROM users WHERE role = 'Teacher' AND status = 'approved' ORDER BY fullname");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Section - PLSNHS | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Create Section CSS -->
    <link rel="stylesheet" href="css/create_section.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
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
                    <h1>Create New Section</h1>
                    <p>Add a new class section to the system</p>
                </div>
                <a href="sections.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Sections</a>
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Please fix the following errors:
                    <ul class="error-list">
                        <?php foreach($errors as $error): ?>
                            <li><i class="fas fa-times-circle"></i> <?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="form-container">
                <div class="form-card">
                    <h3>
                        <i class="fas fa-plus-circle"></i>
                        Section Information
                    </h3>

                    <form method="POST" action="sections_add.php" id="sectionForm">
                        <div class="form-group">
                            <label>Section Name <span>*</span></label>
                            <input type="text" 
                                   name="section_name" 
                                   id="section_name"
                                   placeholder="e.g., Section A, St. John, 11-STEM A" 
                                   value="<?php echo isset($form_data['section_name']) ? htmlspecialchars($form_data['section_name']) : ''; ?>"
                                   required>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Enter a descriptive name for the section
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Grade Level <span>*</span></label>
                            <select name="grade_id" id="grade_id" required>
                                <option value="">Select Grade Level</option>
                                <?php foreach($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" 
                                        <?php echo (isset($form_data['grade_id']) && $form_data['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['grade_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Class Adviser</label>
                            <select name="adviser_id" id="adviser_id">
                                <option value="">Select Adviser (Optional)</option>
                                <?php foreach($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"
                                        <?php echo (isset($form_data['adviser_id']) && $form_data['adviser_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                You can assign an adviser now or later
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="preview-card">
                            <h4><i class="fas fa-eye"></i> Section Preview</h4>
                            <div class="preview-item">
                                <div class="preview-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="preview-details">
                                    <h5 id="preview_name">
                                        <?php echo isset($form_data['section_name']) ? htmlspecialchars($form_data['section_name']) : 'Section Name'; ?>
                                    </h5>
                                    <p id="preview_details">
                                        <?php 
                                        $grade_text = 'Grade Level';
                                        if(isset($form_data['grade_id'])) {
                                            foreach($grade_levels as $grade) {
                                                if($grade['id'] == $form_data['grade_id']) {
                                                    $grade_text = $grade['grade_name'];
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        $adviser_text = 'No Adviser Assigned';
                                        if(isset($form_data['adviser_id']) && $form_data['adviser_id']) {
                                            foreach($teachers as $teacher) {
                                                if($teacher['id'] == $form_data['adviser_id']) {
                                                    $adviser_text = 'Adviser: ' . $teacher['fullname'];
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        echo $grade_text . ' · ' . $adviser_text;
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="add_section" class="btn-submit">
                                <i class="fas fa-save"></i> Create Section
                            </button>
                            <a href="sections.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Live preview update
        const sectionNameInput = document.getElementById('section_name');
        const gradeSelect = document.getElementById('grade_id');
        const adviserSelect = document.getElementById('adviser_id');
        const previewName = document.getElementById('preview_name');
        const previewDetails = document.getElementById('preview_details');

        // Get option text helper
        function getSelectedOptionText(select) {
            if (!select.value) return '';
            const option = select.options[select.selectedIndex];
            return option ? option.text : '';
        }

        function updatePreview() {
            // Update section name
            const sectionName = sectionNameInput.value.trim() || 'Section Name';
            previewName.textContent = sectionName;

            // Get grade text
            let gradeText = 'Grade Level';
            if (gradeSelect.value) {
                gradeText = getSelectedOptionText(gradeSelect);
            }

            // Get adviser text
            let adviserText = 'No Adviser Assigned';
            if (adviserSelect.value) {
                const adviserName = getSelectedOptionText(adviserSelect);
                adviserText = 'Adviser: ' + adviserName;
            }

            previewDetails.textContent = `${gradeText} · ${adviserText}`;
        }

        if(sectionNameInput) sectionNameInput.addEventListener('input', updatePreview);
        if(gradeSelect) gradeSelect.addEventListener('change', updatePreview);
        if(adviserSelect) adviserSelect.addEventListener('change', updatePreview);

        // Initial preview update
        updatePreview();

        // Form validation
        document.getElementById('sectionForm').addEventListener('submit', function(e) {
            const sectionName = sectionNameInput.value.trim();
            const gradeId = gradeSelect.value;

            if (!sectionName) {
                e.preventDefault();
                alert('Please enter a section name!');
                return false;
            }

            if (!gradeId) {
                e.preventDefault();
                alert('Please select a grade level!');
                return false;
            }

            return true;
        });

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