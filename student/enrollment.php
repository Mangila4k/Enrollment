<?php
session_start();
include("../config/database.php");

// Only students can access
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
$profile_picture = $_SESSION['user']['profile_picture'] ?? null;

// Fetch student details from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch existing enrollment
$stmt = $conn->prepare("SELECT e.*, g.grade_name 
                        FROM enrollments e 
                        LEFT JOIN grade_levels g ON e.grade_id = g.id
                        WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enroll = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch grade levels
$grades = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grades->execute();

// Define strands for Senior High (Grade 11-12)
$senior_strands = ['STEM','ABM','GAS','HUMSS','ICT','HE','Sports','Arts'];

// Handle enrollment submission
if(isset($_POST['enroll'])){
    $grade_id = $_POST['grade_id'];
    $school_year = $_POST['school_year'];
    $strand = isset($_POST['strand']) && !empty($_POST['strand']) ? $_POST['strand'] : null;
    $student_type = $_POST['student_type'] ?? '';
    
    // Get grade name
    $grade_stmt = $conn->prepare("SELECT grade_name FROM grade_levels WHERE id = ?");
    $grade_stmt->execute([$grade_id]);
    $grade_row = $grade_stmt->fetch(PDO::FETCH_ASSOC);
    $grade_name = $grade_row['grade_name'];
    
    // Check if student already enrolled for this school year
    $check = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND school_year = ?");
    $check->execute([$student_id, $school_year]);
    if($check->rowCount() > 0){
        $error = "You have already submitted an enrollment for this school year.";
    } else {
        // Create uploads directory if not exists
        if(!is_dir("../uploads/enrollment_docs")) {
            mkdir("../uploads/enrollment_docs", 0777, true);
        }
        
        $uploaded_files = [];
        $errors = [];
        
        // Get requirements based on grade and student type
        $requirements = getRequirements($grade_name, $student_type);
        
        foreach($requirements as $req) {
            $field_name = $req['field'];
            if(isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0) {
                $allowed = ['pdf','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
                if(in_array($ext, $allowed)) {
                    $filename = "uploads/enrollment_docs/".$student_id."_".$field_name."_".time().".".$ext;
                    if(move_uploaded_file($_FILES[$field_name]['tmp_name'], "../".$filename)) {
                        $uploaded_files[$field_name] = $filename;
                    } else {
                        $errors[] = "Failed to upload " . $req['name'];
                    }
                } else {
                    $errors[] = $req['name'] . " must be PDF or image file.";
                }
            } elseif($req['required']) {
                $errors[] = $req['name'] . " is required.";
            }
        }
        
        if(empty($errors)){
            // Prepare insert statement
            $sql = "INSERT INTO enrollments (student_id, grade_id, school_year, status, strand, student_type,
                    form_138, form_137, psa_birth_cert, good_moral_cert, certificate_of_completion, 
                    id_pictures, medical_cert, entrance_exam_result) 
                    VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $student_id, $grade_id, $school_year, $strand, $student_type,
                $uploaded_files['form_138'] ?? null,
                $uploaded_files['form_137'] ?? null,
                $uploaded_files['psa_birth_cert'] ?? null,
                $uploaded_files['good_moral_cert'] ?? null,
                $uploaded_files['certificate_of_completion'] ?? null,
                $uploaded_files['id_pictures'] ?? null,
                $uploaded_files['medical_cert'] ?? null,
                $uploaded_files['entrance_exam_result'] ?? null
            ]);
            
            if($result){
                $success = "Enrollment submitted successfully! Wait for approval.";
                // Refresh enrollment data
                $stmt = $conn->prepare("SELECT e.*, g.grade_name 
                                        FROM enrollments e 
                                        LEFT JOIN grade_levels g ON e.grade_id = g.id
                                        WHERE e.student_id = ?");
                $stmt->execute([$student_id]);
                $enroll = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Error submitting enrollment. Please try again.";
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Function to get requirements based on grade and student type
function getRequirements($grade_name, $student_type) {
    $requirements = [];
    
    switch($grade_name) {
        case 'Grade 7':
            if($student_type == 'new') {
                $requirements = [
                    ['name' => 'Form 137 (Permanent Record)', 'required' => true, 'field' => 'form_137'],
                    ['name' => 'Certificate of Completion (Elementary)', 'required' => true, 'field' => 'certificate_of_completion'],
                    ['name' => 'PSA Birth Certificate', 'required' => true, 'field' => 'psa_birth_cert'],
                    ['name' => '2x2 ID Pictures', 'required' => true, 'field' => 'id_pictures'],
                    ['name' => 'Good Moral Certificate', 'required' => true, 'field' => 'good_moral_cert'],
                    ['name' => 'Medical/Dental Certificate', 'required' => false, 'field' => 'medical_cert']
                ];
            }
            break;
            
        case 'Grade 8':
        case 'Grade 9':
        case 'Grade 10':
            if($student_type == 'continuing') {
                $requirements = [
                    ['name' => 'Form 138 (Report Card)', 'required' => true, 'field' => 'form_138']
                ];
            } elseif($student_type == 'transferee') {
                $requirements = [
                    ['name' => 'Form 138 (Latest Report Card)', 'required' => true, 'field' => 'form_138'],
                    ['name' => 'Form 137 (Permanent Record)', 'required' => true, 'field' => 'form_137'],
                    ['name' => 'PSA Birth Certificate', 'required' => true, 'field' => 'psa_birth_cert'],
                    ['name' => 'Good Moral Certificate', 'required' => true, 'field' => 'good_moral_cert'],
                    ['name' => '2x2 ID Pictures', 'required' => true, 'field' => 'id_pictures'],
                    ['name' => 'Entrance Exam / Interview Result', 'required' => false, 'field' => 'entrance_exam_result']
                ];
            }
            break;
            
        case 'Grade 11':
            if($student_type == 'same_school') {
                $requirements = [
                    ['name' => 'Form 138 (Grade 10 Report Card)', 'required' => true, 'field' => 'form_138'],
                    ['name' => 'Certificate of Completion (Junior High)', 'required' => true, 'field' => 'certificate_of_completion'],
                    ['name' => 'PSA Birth Certificate', 'required' => true, 'field' => 'psa_birth_cert'],
                    ['name' => 'Good Moral Certificate', 'required' => true, 'field' => 'good_moral_cert']
                ];
            } elseif($student_type == 'different_school') {
                $requirements = [
                    ['name' => 'Form 137 (Permanent Record)', 'required' => true, 'field' => 'form_137'],
                    ['name' => 'Form 138 (Grade 10 Report Card)', 'required' => true, 'field' => 'form_138'],
                    ['name' => 'Certificate of Completion (Junior High)', 'required' => true, 'field' => 'certificate_of_completion'],
                    ['name' => 'PSA Birth Certificate', 'required' => true, 'field' => 'psa_birth_cert'],
                    ['name' => 'Good Moral Certificate', 'required' => true, 'field' => 'good_moral_cert'],
                    ['name' => 'Entrance Exam / Screening Result', 'required' => false, 'field' => 'entrance_exam_result']
                ];
            }
            break;
            
        case 'Grade 12':
            if($student_type == 'continuing') {
                $requirements = [
                    ['name' => 'Form 138 (Grade 11 Report Card)', 'required' => true, 'field' => 'form_138']
                ];
            } elseif($student_type == 'transferee') {
                $requirements = [
                    ['name' => 'Form 138 (Grade 11 Report Card)', 'required' => true, 'field' => 'form_138'],
                    ['name' => 'Form 137 (Permanent Record)', 'required' => true, 'field' => 'form_137'],
                    ['name' => 'PSA Birth Certificate', 'required' => true, 'field' => 'psa_birth_cert'],
                    ['name' => 'Good Moral Certificate', 'required' => true, 'field' => 'good_moral_cert'],
                    ['name' => '2x2 ID Pictures', 'required' => true, 'field' => 'id_pictures']
                ];
            }
            break;
    }
    
    return $requirements;
}

// Get student type options based on grade level
function getStudentTypeOptions($grade_name) {
    $options = [];
    
    switch($grade_name) {
        case 'Grade 7':
            $options = ['new' => 'New Student (From Elementary)'];
            break;
        case 'Grade 8':
        case 'Grade 9':
        case 'Grade 10':
            $options = [
                'continuing' => 'Continuing Student (Moving to next grade)',
                'transferee' => 'Transferee (From another school)'
            ];
            break;
        case 'Grade 11':
            $options = [
                'same_school' => 'From the same school (Placido L. Señor SHS - Junior High)',
                'different_school' => 'From a different school (Transferee)'
            ];
            break;
        case 'Grade 12':
            $options = [
                'continuing' => 'Continuing Student (From Grade 11)',
                'transferee' => 'Transferee (From another school)'
            ];
            break;
        default:
            $options = ['new' => 'New Student', 'continuing' => 'Continuing', 'transferee' => 'Transferee'];
    }
    
    return $options;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Enrollment CSS -->
    <link rel="stylesheet" href="css/enrollment.css">
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
                        <li><a href="grades.php"><i class="fas fa-star"></i> My Grades</a></li>
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-file-alt" style="color: var(--primary);"></i> Enrollment Form</h1>
                    <p>Complete your enrollment application for the upcoming school year</p>
                </div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if($enroll && isset($enroll['id'])): ?>
                <!-- Existing Enrollment Display -->
                <div class="existing-enrollment-card">
                    <div class="enrollment-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Enrollment Already Submitted</h3>
                    <div class="enrollment-badge status-<?php echo strtolower($enroll['status'] ?? 'pending'); ?>">
                        Status: <?php echo $enroll['status'] ?? 'Pending'; ?>
                    </div>
                    <div class="enrollment-details">
                        <p><strong>Grade Level:</strong> <?php echo $enroll['grade_name'] ?? 'N/A'; ?></p>
                        <?php if(isset($enroll['strand']) && $enroll['strand']): ?>
                            <p><strong>Strand:</strong> <?php echo $enroll['strand']; ?></p>
                        <?php endif; ?>
                        <p><strong>School Year:</strong> <?php echo $enroll['school_year'] ?? 'N/A'; ?></p>
                        <p><strong>Date Submitted:</strong> <?php echo date('F d, Y', strtotime($enroll['created_at'] ?? 'now')); ?></p>
                    </div>
                    <a href="dashboard.php" class="btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Enrollment Form -->
                <div class="form-container">
                    <!-- Student Information Card -->
                    <?php if($student): ?>
                    <div class="student-info-card">
                        <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="label">Full Name</div>
                                <div class="value">
                                    <?php 
                                    if(isset($student['firstname']) && isset($student['lastname'])) {
                                        $fullname = $student['firstname'] . ' ' . ($student['middlename'] ? $student['middlename'] . ' ' : '') . $student['lastname'];
                                    } else {
                                        $fullname = $student['fullname'] ?? 'N/A';
                                    }
                                    echo htmlspecialchars($fullname);
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="label">Student ID</div>
                                <div class="value"><?php echo isset($student['id_number']) && $student['id_number'] ? htmlspecialchars($student['id_number']) : 'Not Assigned'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Birthdate</div>
                                <div class="value"><?php echo isset($student['birthdate']) && $student['birthdate'] ? date('F d, Y', strtotime($student['birthdate'])) : 'Not Provided'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Gender</div>
                                <div class="value"><?php echo isset($student['gender']) && $student['gender'] ? htmlspecialchars($student['gender']) : 'Not Provided'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="label">Email</div>
                                <div class="value"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="enrollmentForm">
                        <!-- GRADE LEVEL -->
                        <div class="form-group">
                            <label for="grade">Select Grade Level <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <select name="grade_id" id="grade" onchange="updateStudentTypeOptions()" required>
                                    <option value="">-- Select Grade Level --</option>
                                    <?php while($g = $grades->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $g['id']; ?>" data-grade="<?php echo $g['grade_name']; ?>">
                                            <?php echo $g['grade_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <i class="fas fa-chevron-down input-icon"></i>
                            </div>
                        </div>

                        <!-- STUDENT TYPE -->
                        <div class="form-group" id="studentTypeGroup" style="display: none;">
                            <label for="student_type">Student Type <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <select name="student_type" id="student_type" onchange="updateRequirements()">
                                    <option value="">-- Select Student Type --</option>
                                </select>
                                <i class="fas fa-chevron-down input-icon"></i>
                            </div>
                        </div>

                        <!-- STRAND SECTION (For Grade 11-12) -->
                        <div id="strandDiv" class="strand-section" style="display: none;">
                            <h4><i class="fas fa-tag"></i> Strand Information</h4>
                            <div class="form-group">
                                <label>Select Strand <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <select name="strand" id="strand">
                                        <option value="">-- Select Strand --</option>
                                        <?php foreach($senior_strands as $s): ?>
                                            <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-chevron-down input-icon"></i>
                                </div>
                            </div>
                        </div>

                        <!-- REQUIREMENTS SECTION -->
                        <div id="requirementsSection" class="requirements-section" style="display: none;">
                            <h4><i class="fas fa-file-alt"></i> Enrollment Requirements</h4>
                            <div id="requirementsList" class="requirements-list"></div>
                        </div>

                        <!-- SCHOOL YEAR -->
                        <div class="form-group">
                            <label for="school_year">School Year <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <input type="text" name="school_year" id="school_year" placeholder="e.g., 2026-2027" required>
                                <i class="fas fa-calendar input-icon"></i>
                            </div>
                        </div>

                        <div class="info-note">
                            <i class="fas fa-info-circle"></i>
                            <p>Please ensure all required documents are uploaded. Incomplete requirements may delay your enrollment processing.</p>
                        </div>

                        <button type="submit" name="enroll" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Enrollment
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Requirements data structure
        const requirementsData = {
            'Grade 7': {
                'new': [
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'Certificate of Completion (Elementary)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: 'Medical/Dental Certificate', required: false, can_follow: true, field: 'medical_cert' }
                ]
            },
            'Grade 8': {
                'continuing': [
                    { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 9': {
                'continuing': [
                    { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 10': {
                'continuing': [
                    { name: 'Form 138 (Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 11': {
                'same_school': [
                    { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' }
                ],
                'different_school': [
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: 'Entrance Exam / Screening Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 12': {
                'continuing': [
                    { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' }
                ]
            }
        };

        function updateStudentTypeOptions() {
            const gradeSelect = document.getElementById('grade');
            const studentTypeGroup = document.getElementById('studentTypeGroup');
            const studentTypeSelect = document.getElementById('student_type');
            const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
            const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
            
            if(gradeName) {
                const options = getStudentTypeOptions(gradeName);
                studentTypeSelect.innerHTML = '<option value="">-- Select Student Type --</option>';
                
                for(const [value, label] of Object.entries(options)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    studentTypeSelect.appendChild(option);
                }
                
                studentTypeGroup.style.display = 'block';
                
                // Update strand visibility
                updateStrandVisibility(gradeName);
            } else {
                studentTypeGroup.style.display = 'none';
            }
            
            // Reset requirements section
            document.getElementById('requirementsSection').style.display = 'none';
            document.getElementById('requirementsList').innerHTML = '';
        }

        function getStudentTypeOptions(gradeName) {
            switch(gradeName) {
                case 'Grade 7':
                    return { 'new': 'New Student (From Elementary)' };
                case 'Grade 8':
                case 'Grade 9':
                case 'Grade 10':
                    return {
                        'continuing': 'Continuing Student (Moving to next grade)',
                        'transferee': 'Transferee (From another school)'
                    };
                case 'Grade 11':
                    return {
                        'same_school': 'From the same school (Placido L. Señor SHS - Junior High)',
                        'different_school': 'From a different school (Transferee)'
                    };
                case 'Grade 12':
                    return {
                        'continuing': 'Continuing Student (From Grade 11)',
                        'transferee': 'Transferee (From another school)'
                    };
                default:
                    return { 'new': 'New Student', 'continuing': 'Continuing', 'transferee': 'Transferee' };
            }
        }

        function updateStrandVisibility(gradeName) {
            const strandDiv = document.getElementById('strandDiv');
            const strandSelect = document.getElementById('strand');
            
            if(gradeName === 'Grade 11' || gradeName === 'Grade 12') {
                strandDiv.style.display = 'block';
                strandSelect.setAttribute('required', 'required');
            } else {
                strandDiv.style.display = 'none';
                strandSelect.removeAttribute('required');
            }
        }

        function updateRequirements() {
            const gradeSelect = document.getElementById('grade');
            const studentTypeSelect = document.getElementById('student_type');
            const requirementsSection = document.getElementById('requirementsSection');
            const requirementsList = document.getElementById('requirementsList');
            
            const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
            const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
            const studentType = studentTypeSelect.value;
            
            if(gradeName && studentType && requirementsData[gradeName] && requirementsData[gradeName][studentType]) {
                requirementsSection.style.display = 'block';
                const requirements = requirementsData[gradeName][studentType];
                
                requirementsList.innerHTML = '';
                requirements.forEach(req => {
                    const reqDiv = document.createElement('div');
                    reqDiv.className = 'requirement-item';
                    
                    let badgeHtml = '';
                    if(req.required) {
                        badgeHtml = '<span class="req-badge badge-required">Required</span>';
                    } else {
                        badgeHtml = '<span class="req-badge badge-optional">Optional</span>';
                    }
                    
                    if(req.can_follow) {
                        badgeHtml += ' <span class="req-badge badge-follow">Can be followed up</span>';
                    }
                    
                    reqDiv.innerHTML = `
                        <div class="requirement-name">
                            <span><i class="fas fa-file"></i> ${req.name}</span>
                            <div>${badgeHtml}</div>
                        </div>
                        <div class="file-upload-area" onclick="document.getElementById('${req.field}').click()">
                            <i class="fas fa-cloud-upload-alt"></i> Click to upload
                            <p style="font-size: 11px; color: #666; margin-top: 5px;">PDF, JPG, JPEG, or PNG</p>
                        </div>
                        <input type="file" name="${req.field}" id="${req.field}" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" 
                               ${req.required ? 'required' : ''} onchange="updateFileName(this)">
                        <div class="file-name" id="${req.field}_name"></div>
                    `;
                    requirementsList.appendChild(reqDiv);
                });
            } else {
                requirementsSection.style.display = 'none';
            }
        }

        function updateFileName(input) {
            const fileNameDiv = document.getElementById(input.id + '_name');
            if(input.files && input.files.length > 0) {
                fileNameDiv.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> ' + input.files[0].name;
            } else {
                fileNameDiv.innerHTML = '';
            }
        }

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }

        // Auto-populate school year
        window.onload = function() {
            const today = new Date();
            const year = today.getFullYear();
            const nextYear = year + 1;
            const schoolYearInput = document.getElementById('school_year');
            if(schoolYearInput && !schoolYearInput.value) {
                schoolYearInput.value = year + '-' + nextYear;
            }
        }
    </script>
</body>
</html>