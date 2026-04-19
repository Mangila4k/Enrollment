<?php
session_start();
include("../config/database.php");

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Get teacher profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
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

// Get teacher's assigned classes (sections they teach)
$assigned_classes_query = "
    SELECT DISTINCT 
        cs.section_id,
        s.section_name,
        g.grade_name,
        g.id as grade_id
    FROM class_schedules cs
    JOIN sections s ON cs.section_id = s.id
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE cs.teacher_id = :teacher_id AND cs.status = 'active'
    ORDER BY g.id, s.section_name
";

$stmt = $conn->prepare($assigned_classes_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$assigned_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher's subjects for each section
$teacher_subjects = [];
foreach($assigned_classes as $class) {
    $subjects_query = "
        SELECT DISTINCT 
            cs.subject_id,
            sub.subject_name
        FROM class_schedules cs
        JOIN subjects sub ON cs.subject_id = sub.id
        WHERE cs.teacher_id = :teacher_id 
        AND cs.section_id = :section_id
        AND cs.status = 'active'
        ORDER BY sub.subject_name
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':section_id' => $class['section_id']
    ]);
    $teacher_subjects[$class['section_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected filters
$selected_section = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selected_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selected_quarter = isset($_GET['quarter']) ? $_GET['quarter'] : '1st Quarter';

// Get selected section details
$selected_section_name = '';
$selected_grade_name = '';
$selected_grade_id = null;

foreach($assigned_classes as $class) {
    if($class['section_id'] == $selected_section) {
        $selected_section_name = $class['section_name'];
        $selected_grade_name = $class['grade_name'];
        $selected_grade_id = $class['grade_id'];
        break;
    }
}

// Get students for selected section
$students_list = [];
if($selected_section && $selected_subject && $selected_grade_id) {
    // Check if teacher is authorized to grade this subject
    $is_authorized = false;
    foreach($teacher_subjects[$selected_section] ?? [] as $subject) {
        if($subject['subject_id'] == $selected_subject) {
            $is_authorized = true;
            break;
        }
    }
    
    if($is_authorized) {
        // Get enrolled students in this grade level with profile picture
        $students_stmt = $conn->prepare("
            SELECT u.*, e.id as enrollment_id
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE u.role = 'Student' 
            AND e.grade_id = :grade_id
            AND e.status = 'Enrolled'
            ORDER BY u.fullname ASC
        ");
        $students_stmt->execute([':grade_id' => $selected_grade_id]);
        $students_query_result = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if grades table exists
        $grades_table_exists = false;
        try {
            $table_check = $conn->query("SHOW TABLES LIKE 'grades'");
            $grades_table_exists = $table_check->rowCount() > 0;
        } catch(PDOException $e) {
            $grades_table_exists = false;
        }
        
        foreach($students_query_result as $student) {
            $student['grade_recorded'] = false;
            $student['grade'] = null;
            $student['grade_id'] = null;
            $student['profile_picture'] = $student['profile_picture'] ?? null; // Add profile picture
            
            if($grades_table_exists) {
                $grade_check_stmt = $conn->prepare("
                    SELECT * FROM grades 
                    WHERE student_id = :student_id 
                    AND subject_id = :subject_id
                    AND quarter = :quarter
                ");
                $grade_check_stmt->execute([
                    ':student_id' => $student['id'],
                    ':subject_id' => $selected_subject,
                    ':quarter' => $selected_quarter
                ]);
                $grade_check = $grade_check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if($grade_check) {
                    $student['grade_recorded'] = true;
                    $student['grade'] = $grade_check['grade'];
                    $student['grade_id'] = $grade_check['id'];
                }
            }
            
            $students_list[] = $student;
        }
    } else {
        $error_message = "You are not authorized to enter grades for this subject and section combination.";
    }
}

// Handle grade submission (same as before)
if(isset($_POST['save_grades'])) {
    $subject_id = (int)$_POST['subject_id'];
    $section_id = (int)$_POST['section_id'];
    $quarter = $_POST['quarter'];
    $student_ids = $_POST['student_ids'] ?? [];
    $grades = $_POST['grades'] ?? [];
    $grade_ids = $_POST['grade_ids'] ?? [];
    
    // Verify teacher is authorized
    $is_authorized = false;
    foreach($teacher_subjects[$section_id] ?? [] as $subject) {
        if($subject['subject_id'] == $subject_id) {
            $is_authorized = true;
            break;
        }
    }
    
    if(!$is_authorized) {
        $error_message = "You are not authorized to enter grades for this subject and section.";
    } else {
        try {
            $conn->beginTransaction();
            
            // Check if grades table exists, if not create it
            $table_check = $conn->query("SHOW TABLES LIKE 'grades'");
            if($table_check->rowCount() == 0) {
                $create_table = "
                    CREATE TABLE IF NOT EXISTS `grades` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `student_id` int(11) NOT NULL,
                        `subject_id` int(11) NOT NULL,
                        `quarter` varchar(20) NOT NULL,
                        `grade` decimal(5,2) DEFAULT NULL,
                        `teacher_id` int(11) DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (`id`),
                        KEY `student_id` (`student_id`),
                        KEY `subject_id` (`subject_id`),
                        KEY `teacher_id` (`teacher_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ";
                $conn->exec($create_table);
            }
            
            foreach($student_ids as $index => $student_id) {
                $grade_value = $grades[$index] ?? null;
                $grade_id = $grade_ids[$index] ?? null;
                
                // Skip if grade is empty
                if($grade_value === '' || $grade_value === null) {
                    continue;
                }
                
                // Validate grade range (0-100)
                $grade_value = floatval($grade_value);
                if($grade_value < 0 || $grade_value > 100) {
                    throw new Exception("Grade must be between 0 and 100");
                }
                
                if($grade_id && $grade_id > 0) {
                    // Update existing grade
                    $update_stmt = $conn->prepare("
                        UPDATE grades 
                        SET grade = :grade, teacher_id = :teacher_id
                        WHERE id = :id
                    ");
                    $update_stmt->execute([
                        ':grade' => $grade_value,
                        ':teacher_id' => $teacher_id,
                        ':id' => $grade_id
                    ]);
                } else {
                    // Insert new grade
                    $insert_stmt = $conn->prepare("
                        INSERT INTO grades (student_id, subject_id, quarter, grade, teacher_id)
                        VALUES (:student_id, :subject_id, :quarter, :grade, :teacher_id)
                    ");
                    $insert_stmt->execute([
                        ':student_id' => $student_id,
                        ':subject_id' => $subject_id,
                        ':quarter' => $quarter,
                        ':grade' => $grade_value,
                        ':teacher_id' => $teacher_id
                    ]);
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Grades saved successfully!";
            header("Location: grades.php?section_id=$section_id&subject_id=$subject_id&quarter=$quarter");
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Error saving grades: " . $e->getMessage();
        }
    }
}

// Get grade statistics (same as before)
$statistics = [];
if($selected_subject && $selected_section && !empty($students_list)) {
    $grade_values = [];
    foreach($students_list as $student) {
        if($student['grade'] !== null) {
            $grade_values[] = floatval($student['grade']);
        }
    }
    
    if(!empty($grade_values)) {
        $statistics['average'] = array_sum($grade_values) / count($grade_values);
        $statistics['highest'] = max($grade_values);
        $statistics['lowest'] = min($grade_values);
        $statistics['passed'] = count(array_filter($grade_values, function($g) { return $g >= 75; }));
        $statistics['failed'] = count(array_filter($grade_values, function($g) { return $g < 75; }));
        $statistics['total'] = count($grade_values);
    }
}

$quarters = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Management - Teacher Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Grades CSS -->
    <link rel="stylesheet" href="css/grades.css">
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

            <div class="teacher-profile">
                <div class="teacher-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($teacher_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="teacher-name"><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></div>
                <div class="teacher-role"><i class="fas fa-chalkboard-user"></i> Teacher</div>
            </div>

            <div class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN MENU</div>
                    <ul class="nav-items">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="attendance_qr.php"><i class="fas fa-qrcode"></i> QR Attendance</a></li>
                        <li><a href="classes.php"><i class="fas fa-users"></i> My Classes</a></li>
                        <li><a href="schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                        <li><a href="grades.php" class="active"><i class="fas fa-star"></i> Grades</a></li>
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
                    <h1>Grade Management</h1>
                    <p>Record and manage student grades for your classes</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
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
                        <h3>My Classes</h3>
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    </div>
                    <div class="stat-number"><?php echo count($assigned_classes); ?></div>
                    <div class="stat-label">Sections assigned</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $total_subjects = 0;
                        foreach($teacher_subjects as $subjects) {
                            $total_subjects += count($subjects);
                        }
                        echo $total_subjects;
                        ?>
                    </div>
                    <div class="stat-label">Total subjects taught</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Students</h3>
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    </div>
                    <div class="stat-number"><?php echo count($students_list); ?></div>
                    <div class="stat-label">In selected class</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>With Grades</h3>
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $graded = 0;
                        foreach($students_list as $s) {
                            if($s['grade_recorded']) $graded++;
                        }
                        echo $graded;
                        ?>
                    </div>
                    <div class="stat-label">Grades recorded</div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Select Class and Subject</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Section / Class</label>
                            <select name="section_id" id="section_id" required>
                                <option value="">Select Section</option>
                                <?php foreach($assigned_classes as $class): ?>
                                    <option value="<?php echo $class['section_id']; ?>" 
                                        data-grade-id="<?php echo $class['grade_id']; ?>"
                                        <?php echo ($selected_section == $class['section_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['section_name'] . ' - ' . $class['grade_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-book"></i> Subject</label>
                            <select name="subject_id" id="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php if($selected_section && isset($teacher_subjects[$selected_section])): ?>
                                    <?php foreach($teacher_subjects[$selected_section] as $subject): ?>
                                        <option value="<?php echo $subject['subject_id']; ?>" 
                                            <?php echo ($selected_subject == $subject['subject_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-chart-line"></i> Quarter</label>
                            <select name="quarter" required>
                                <?php foreach($quarters as $quarter): ?>
                                    <option value="<?php echo $quarter; ?>" 
                                        <?php echo ($selected_quarter == $quarter) ? 'selected' : ''; ?>>
                                        <?php echo $quarter; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search"></i> Load Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Statistics Panel -->
            <?php if(!empty($statistics)): ?>
                <div class="stats-panel">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($statistics['average'], 2); ?></div>
                        <div class="stat-label">Class Average</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['highest']; ?></div>
                        <div class="stat-label">Highest Grade</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['lowest']; ?></div>
                        <div class="stat-label">Lowest Grade</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['passed']; ?></div>
                        <div class="stat-label">Passed (≥75)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['failed']; ?></div>
                        <div class="stat-label">Failed (<75)</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grade Entry Form -->
            <?php if(!empty($students_list)): ?>
                <div class="grade-card">
                    <div class="grade-header">
                        <h3>
                            <i class="fas fa-star"></i>
                            Grade Entry - <?php echo htmlspecialchars($selected_section_name); ?> - <?php echo htmlspecialchars($selected_grade_name); ?>
                            <span class="quarter-badge"><?php echo $selected_quarter; ?></span>
                        </h3>
                        <div class="batch-actions">
                            <button type="button" class="btn-batch btn-pass" onclick="setAllGrades(75)">
                                <i class="fas fa-check-circle"></i> All Passing (75)
                            </button>
                            <button type="button" class="btn-batch btn-fail" onclick="setAllGrades(65)">
                                <i class="fas fa-times-circle"></i> All Failing (65)
                            </button>
                        </div>
                    </div>

                    <form method="POST" action="" id="gradesForm">
                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        <input type="hidden" name="quarter" value="<?php echo $selected_quarter; ?>">
                        
                        <div class="table-container">
                            <table class="data-table grades-table">
                                <thead>
                                    <tr>
                                        <th>Student Information</th>
                                        <th>Grade (0-100)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students_list as $index => $student): ?>
                                        <tr>
                                            <td>
                                                <div class="student-info">
                                                    <?php if(!empty($student['profile_picture']) && file_exists("../" . $student['profile_picture'])): ?>
                                                        <div class="student-avatar-img">
                                                            <img src="../<?php echo $student['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="student-avatar">
                                                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="student-details">
                                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                        <div class="student-meta">
                                                            <span><i class="fas fa-id-card"></i> ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <td>
                                                <input type="hidden" name="student_ids[]" value="<?php echo $student['id']; ?>">
                                                <input type="hidden" name="grade_ids[]" value="<?php echo $student['grade_id'] ?? ''; ?>">
                                                <input type="number" 
                                                       name="grades[]" 
                                                       class="grade-input <?php echo ($student['grade'] !== null) ? ($student['grade'] >= 75 ? 'passing' : 'failing') : ''; ?>" 
                                                       value="<?php echo $student['grade'] !== null ? $student['grade'] : ''; ?>"
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01"
                                                       placeholder="Enter grade"
                                                       oninput="validateGrade(this)">
                                            </div>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" name="save_grades" class="btn-save">
                            <i class="fas fa-save"></i> Save Grades
                        </button>
                    </form>
                </div>
            <?php elseif($selected_section && $selected_subject): ?>
                <div class="grade-card">
                    <div class="no-data">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Students Found</h3>
                        <p>There are no enrolled students in this section.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grade-card">
                    <div class="no-data">
                        <i class="fas fa-hand-pointer"></i>
                        <h3>Select a Class and Subject</h3>
                        <p>Please select a section, subject, and quarter to enter grades.</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Grades JS -->
    <script src="js/grades.js"></script>
    
    <script>
        // Pass PHP data to JavaScript
        const gradesData = {
            totalClasses: <?php echo count($assigned_classes); ?>,
            totalSubjects: <?php echo $total_subjects; ?>,
            totalStudents: <?php echo count($students_list); ?>,
            gradedCount: <?php 
                $graded = 0;
                foreach($students_list as $s) {
                    if($s['grade_recorded']) $graded++;
                }
                echo $graded;
            ?>
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
        
        // Function to set all grades to a specific value
        function setAllGrades(value) {
            const inputs = document.querySelectorAll('.grade-input');
            inputs.forEach(input => {
                input.value = value;
                validateGrade(input);
            });
        }
        
        // Function to validate grade input
        function validateGrade(input) {
            let value = parseFloat(input.value);
            if (isNaN(value)) {
                input.classList.remove('passing', 'failing');
                return;
            }
            if (value >= 75) {
                input.classList.add('passing');
                input.classList.remove('failing');
            } else {
                input.classList.add('failing');
                input.classList.remove('passing');
            }
        }
    </script>
    
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>