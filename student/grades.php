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

// Get student's enrollment information
$enrollment_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = :student_id AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

$grade_id = $enrollment ? $enrollment['grade_id'] : null;
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';

// Get all subjects for student's grade level
$subjects_list = [];
if($grade_id) {
    $subjects_query = "
        SELECT * FROM subjects 
        WHERE grade_id = :grade_id 
        ORDER BY subject_name
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bindParam(':grade_id', $grade_id, PDO::PARAM_INT);
    $stmt->execute();
    
    while($subject = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subjects_list[] = $subject;
    }
    $stmt = null;
}

$total_subjects = count($subjects_list);

// Get grades for each subject by quarter
$grades_data = [];
$quarter_averages = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$quarter_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$final_average = 0;
$total_quarters = 0;
$has_any_grade_overall = false;

foreach($subjects_list as $subject) {
    $subject_grades = ['q1' => null, 'q2' => null, 'q3' => null, 'q4' => null];
    
    $grades_query = "
        SELECT quarter, grade 
        FROM grades 
        WHERE student_id = :student_id AND subject_id = :subject_id
    ";
    $stmt = $conn->prepare($grades_query);
    $stmt->execute([
        ':student_id' => $student_id,
        ':subject_id' => $subject['id']
    ]);
    
    while($grade = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $quarter_num = (int)$grade['quarter'];
        $subject_grades['q' . $quarter_num] = $grade['grade'];
        
        if($grade['grade'] > 0) {
            $quarter_averages[$quarter_num] += $grade['grade'];
            $quarter_counts[$quarter_num]++;
            $has_any_grade_overall = true;
        }
    }
    
    $subject_grades_values = array_filter([$subject_grades['q1'], $subject_grades['q2'], $subject_grades['q3'], $subject_grades['q4']], function($g) { return $g !== null && $g > 0; });
    $subject_average = !empty($subject_grades_values) ? round(array_sum($subject_grades_values) / count($subject_grades_values), 2) : null;
    
    $grades_data[$subject['id']] = [
        'subject_name' => $subject['subject_name'],
        'subject_id' => $subject['id'],
        'q1' => $subject_grades['q1'],
        'q2' => $subject_grades['q2'],
        'q3' => $subject_grades['q3'],
        'q4' => $subject_grades['q4'],
        'average' => $subject_average
    ];
}

// Check if any actual grades exist (more reliable check)
$actual_grades_exist = false;
foreach($grades_data as $subject_grade) {
    if($subject_grade['q1'] > 0 || $subject_grade['q2'] > 0 || $subject_grade['q3'] > 0 || $subject_grade['q4'] > 0) {
        $actual_grades_exist = true;
        break;
    }
}

$quarter_avg_display = [1 => null, 2 => null, 3 => null, 4 => null];
for($i = 1; $i <= 4; $i++) {
    if($quarter_counts[$i] > 0) {
        $quarter_avg_display[$i] = round($quarter_averages[$i] / $quarter_counts[$i], 2);
        $total_quarters++;
    }
}

if($actual_grades_exist && $total_quarters > 0) {
    $valid_quarters = array_filter($quarter_avg_display, function($avg) { return $avg !== null && $avg > 0; });
    if(!empty($valid_quarters)) {
        $sum_quarters = array_sum($valid_quarters);
        $final_average = round($sum_quarters / count($valid_quarters), 2);
    }
}

$selected_quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : 1;
$quarter_names = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];

// Encode grades data for JavaScript
$grades_data_json = json_encode($grades_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Student Dashboard | Placido L. Señor Senior High School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/grades.css">
</head>
<body>
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <div class="app-container">
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
                        <li><a href="grades.php" class="active"><i class="fas fa-star"></i> My Grades</a></li>
                        <li><a href="enrollment_history.php"><i class="fas fa-history"></i> Enrollment History</a></li>
                        <li><a href="requirements.php"><i class="fas fa-file-alt"></i> Requirements</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Grades</h1>
                    <p>View your academic performance by quarter</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?>
                </div>
            </div>

            <?php if(isset($success_message) && $success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if(isset($error_message) && $error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if($enrollment): ?>
                <div class="class-info-card">
                    <div class="class-info-details">
                        <h3><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($grade_name); ?></h3>
                        <div class="class-info-badges">
                            <?php if($strand != 'N/A'): ?>
                                <span class="info-badge"><i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($strand); ?></span>
                            <?php endif; ?>
                            <span class="info-badge"><i class="fas fa-check-circle"></i> Status: <?php echo htmlspecialchars($enrollment_status); ?></span>
                        </div>
                    </div>
                    <div class="school-year"><i class="fas fa-calendar"></i> S.Y. <?php echo htmlspecialchars($school_year); ?></div>
                </div>

                <?php if($actual_grades_exist): ?>
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Subjects</h3>
                            <div class="stat-icon"><i class="fas fa-book"></i></div>
                        </div>
                        <div class="stat-number"><?php echo $total_subjects; ?></div>
                        <div class="stat-label">Enrolled Subjects</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Grade Level</h3>
                            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                        </div>
                        <div class="stat-number"><?php echo htmlspecialchars($grade_name); ?></div>
                        <div class="stat-label">Current Grade</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>School Year</h3>
                            <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                        </div>
                        <div class="stat-number"><?php echo htmlspecialchars($school_year); ?></div>
                        <div class="stat-label">Current SY</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-grades-message">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Grades Available Yet</h3>
                    <p>Your grades have not been recorded for this school year.</p>
                    <p style="font-size: 13px; margin-top: 8px;">Once your teachers submit your grades, your overall average will appear here.</p>
                </div>
                <?php endif; ?>

                <div class="subjects-card">
                    <h3><i class="fas fa-book-open"></i> Your Subjects (<?php echo $total_subjects; ?>)</h3>
                    <div class="subjects-grid">
                        <?php if(!empty($subjects_list)): ?>
                            <?php foreach($subjects_list as $subject): 
                                $subject_grade_data = $grades_data[$subject['id']] ?? null;
                                $has_grades = ($subject_grade_data && ($subject_grade_data['q1'] || $subject_grade_data['q2'] || $subject_grade_data['q3'] || $subject_grade_data['q4']));
                            ?>
                                <div class="subject-item">
                                    <div class="subject-info">
                                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                        <?php if(!$has_grades): ?>
                                            <p style="font-size: 0.7rem; color: #f59e0b;"><i class="fas fa-clock"></i> No grades yet</p>
                                        <?php endif; ?>
                                    </div>
                                    <button class="view-grade-btn" data-subject-id="<?php echo $subject['id']; ?>" data-subject-name="<?php echo htmlspecialchars($subject['subject_name']); ?>">
                                        <i class="fas fa-eye"></i> View Grades
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data" style="grid-column: 1/-1; padding: 30px;">
                                <i class="fas fa-book"></i>
                                <p>No subjects found for your grade level.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-grades-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Not Enrolled</h3>
                    <p>You are not currently enrolled in any grade level. Please contact the registrar's office for assistance.</p>
                    <div class="info-message">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <p><strong>Registrar's Office</strong><br>
                            📞 (032) 123-4567<br>
                            📧 registrar@plshs.edu.ph<br>
                            📍 Langtad, City of Naga, Cebu</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="gradeModal" class="grade-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalSubjectTitle">Subject Grades</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin"></i> Loading grades...
                </div>
            </div>
        </div>
    </div>

    <!-- Pass PHP data to JavaScript -->
    <script>
        // Store grades data from PHP
        const gradesData = <?php echo $grades_data_json; ?>;
    </script>
    
    <!-- Grades JS -->
    <script src="js/grades.js"></script>
</body>
</html>