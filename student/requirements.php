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
$first_name = explode(' ', $student_name)[0];
$success_message = '';
$error_message = '';

// Fetch fresh student data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if($student) {
    $profile_picture = $student['profile_picture'] ?? null;
}

// Get current enrollment
$enrollment_query = "
    SELECT e.*, g.grade_name 
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ? AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->execute([$student_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// If no active enrollment, get the most recent one
if(!$enrollment) {
    $enrollment_query = "
        SELECT e.*, g.grade_name 
        FROM enrollments e
        JOIN grade_levels g ON e.grade_id = g.id
        WHERE e.student_id = ?
        ORDER BY e.created_at DESC LIMIT 1
    ";
    $stmt = $conn->prepare($enrollment_query);
    $stmt->execute([$student_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Define requirements with status
$requirements = [
    'form_138' => ['label' => 'Form 138 (Report Card)', 'icon' => 'fa-file-pdf', 'required' => true, 'description' => 'Original or certified true copy of report card from previous school', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'good_moral' => ['label' => 'Good Moral Certificate', 'icon' => 'fa-file-alt', 'required' => true, 'description' => 'Certificate of good moral character from previous school', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'psa_birth_cert' => ['label' => 'PSA Birth Certificate', 'icon' => 'fa-file-pdf', 'required' => true, 'description' => 'PSA authenticated birth certificate', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'medical_cert' => ['label' => 'Medical Certificate', 'icon' => 'fa-notes-medical', 'required' => false, 'description' => 'Medical certificate from a licensed physician', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'parent_consent' => ['label' => 'Parent Consent Form', 'icon' => 'fa-file-signature', 'required' => true, 'description' => 'Signed parental consent form for enrollment', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'esc_slip' => ['label' => 'ESC Slip', 'icon' => 'fa-file-pdf', 'required' => false, 'description' => 'Educational Service Contracting slip (if applicable)', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'transfer_credentials' => ['label' => 'Transfer Credentials', 'icon' => 'fa-exchange-alt', 'required' => false, 'description' => 'Transfer credentials from previous school (for transferees)', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5],
    'report_card' => ['label' => 'Report Card (Previous Year)', 'icon' => 'fa-file-alt', 'required' => true, 'description' => 'Report card from previous grade level', 'allowed_types' => 'pdf,jpg,jpeg,png', 'max_size' => 5]
];

// Check which requirement columns exist in the enrollments table
$existing_requirements = [];
$check_columns = $conn->query("SHOW COLUMNS FROM enrollments");
$existing_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[] = $col['Field'];
}

foreach($requirements as $key => $req) {
    if(in_array($key, $existing_columns)) {
        $existing_requirements[$key] = $req;
    }
}

// ========== HANDLE FILE UPLOAD ==========
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_requirement'])) {
    $requirement_key = $_POST['requirement_key'];
    $requirement_label = $requirements[$requirement_key]['label'] ?? $requirement_key;
    
    if(isset($_FILES['requirement_file']) && $_FILES['requirement_file']['error'] == 0) {
        $file = $_FILES['requirement_file'];
        $filename = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Check if requirement exists in existing_requirements
        if(isset($existing_requirements[$requirement_key])) {
            $max_size = ($existing_requirements[$requirement_key]['max_size'] ?? 5) * 1024 * 1024; // Convert to bytes
            
            // Validate file
            if(!in_array($file_ext, $allowed)) {
                $error_message = "Invalid file type. Allowed: " . implode(', ', $allowed);
            } elseif($file_size > $max_size) {
                $error_message = "File too large. Max size: " . ($existing_requirements[$requirement_key]['max_size'] ?? 5) . "MB";
            } else {
                // Create upload directory if not exists
                $upload_dir = "../uploads/requirements/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = "req_" . $student_id . "_" . $requirement_key . "_" . time() . "." . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                $db_path = "uploads/requirements/" . $new_filename;
                
                // Delete old file if exists
                if(!empty($enrollment[$requirement_key]) && file_exists("../" . $enrollment[$requirement_key])) {
                    unlink("../" . $enrollment[$requirement_key]);
                }
                
                // Upload file
                if(move_uploaded_file($file_tmp, $upload_path)) {
                    // Update database
                    $update_stmt = $conn->prepare("UPDATE enrollments SET $requirement_key = ? WHERE id = ?");
                    if($update_stmt->execute([$db_path, $enrollment['id']])) {
                        $success_message = "✅ " . htmlspecialchars($requirement_label) . " has been uploaded successfully!";
                        
                        // Add notification for admin
                        $notif_title = "📄 New Requirement Submitted";
                        $notif_message = "Student " . $student_name . " has submitted " . $requirement_label . " for enrollment.";
                        $notif_link = "../admin/view_enrollment.php?id=" . $enrollment['id'];
                        
                        $add_notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'update', ?, ?, ?, NOW())");
                        $add_notif->execute([1, $notif_title, $notif_message, $notif_link]); // user_id 1 is admin
                        
                        // Refresh enrollment data
                        $stmt = $conn->prepare($enrollment_query);
                        $stmt->execute([$student_id]);
                        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Refresh requirements status
                        $requirements_status = [];
                        foreach($existing_requirements as $key => $req) {
                            $value = $enrollment[$key] ?? null;
                            $requirements_status[$key] = [
                                'label' => $req['label'],
                                'icon' => $req['icon'],
                                'submitted' => !empty($value),
                                'required' => $req['required'],
                                'description' => $req['description'],
                                'file' => $value
                            ];
                        }
                        
                        // Recalculate counts
                        $submitted_requirements = array_filter($requirements_status, function($req) { return $req['submitted']; });
                        $missing_requirements = array_filter($requirements_status, function($req) { return !$req['submitted']; });
                        $submitted_count = count($submitted_requirements);
                        $missing_count = count($missing_requirements);
                        $completion_percentage = $total_requirements > 0 ? round(($submitted_count / $total_requirements) * 100) : 0;
                    } else {
                        $error_message = "Failed to update database.";
                    }
                } else {
                    $error_message = "Failed to upload file.";
                }
            }
        } else {
            $error_message = "Invalid requirement type.";
        }
    } else {
        $error_message = "Please select a file to upload.";
    }
}

// Get requirement values for this enrollment
$submitted_requirements = [];
$missing_requirements = [];
$requirements_status = [];

foreach($existing_requirements as $key => $req) {
    $value = $enrollment[$key] ?? null;
    $is_submitted = !empty($value);
    $requirements_status[$key] = [
        'label' => $req['label'],
        'icon' => $req['icon'],
        'submitted' => $is_submitted,
        'required' => $req['required'],
        'description' => $req['description'],
        'file' => $value,
        'allowed_types' => $req['allowed_types'],
        'max_size' => $req['max_size']
    ];
    if($is_submitted) {
        $submitted_requirements[$key] = ['label' => $req['label'], 'icon' => $req['icon'], 'file' => $value];
    } else {
        $missing_requirements[$key] = ['label' => $req['label'], 'icon' => $req['icon'], 'required' => $req['required']];
    }
}

$total_requirements = count($existing_requirements);
$submitted_count = count($submitted_requirements);
$missing_count = count($missing_requirements);
$completion_percentage = $total_requirements > 0 ? round(($submitted_count / $total_requirements) * 100) : 0;

// Get notifications count for badge
$notif_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$student_id]);
$notif_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_notifications') {
        $notifications = getNotifications($conn, $student_id, 20);
        $unread_count = getUnreadCount($conn, $student_id);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        exit();
    }
    
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements - Student Portal | PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Requirements CSS -->
    <link rel="stylesheet" href="css/requirements.css">
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
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-initial"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="online-dot"></div>
                </div>
                <div class="student-name"><?php echo htmlspecialchars($first_name); ?></div>
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
                        <li><a href="requirements.php" class="active"><i class="fas fa-file-alt"></i> Requirements</a></li>
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
            <div class="page-header">
                <div>
                    <h1>Enrollment Requirements</h1>
                    <p>Track and submit your requirements for enrollment</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
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

            <?php if($enrollment): ?>
                <!-- Enrollment Info Card -->
                <div class="info-card">
                    <div class="info-card-content">
                        <div class="info-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="info-details">
                            <h3><?php echo htmlspecialchars($enrollment['grade_name']); ?></h3>
                            <p>School Year: <?php echo htmlspecialchars($enrollment['school_year']); ?></p>
                            <?php if(isset($enrollment['strand']) && $enrollment['strand']): ?>
                                <p>Strand: <?php echo htmlspecialchars($enrollment['strand']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="info-status">
                            <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                                <?php echo $enrollment['status']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="progress-card">
                    <div class="progress-header">
                        <h3><i class="fas fa-chart-line"></i> Requirements Progress</h3>
                        <span class="progress-percentage"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo $submitted_count; ?></span>
                            <span class="stat-label">Submitted</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $missing_count; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $total_requirements; ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                    </div>
                </div>

                <!-- Requirements List -->
                <div class="requirements-card">
                    <h3><i class="fas fa-list-check"></i> Requirements List</h3>
                    <div class="requirements-list">
                        <?php foreach($requirements_status as $key => $req): ?>
                            <div class="requirement-item <?php echo $req['submitted'] ? 'submitted' : 'missing'; ?>" data-key="<?php echo $key; ?>">
                                <div class="requirement-icon <?php echo $req['submitted'] ? 'submitted' : 'missing'; ?>">
                                    <i class="fas <?php echo $req['icon']; ?>"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name">
                                        <?php echo htmlspecialchars($req['label']); ?>
                                        <?php if($req['required']): ?>
                                            <span class="required-badge">Required</span>
                                        <?php else: ?>
                                            <span class="optional-badge">Optional</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="requirement-description">
                                        <?php echo htmlspecialchars($req['description']); ?>
                                    </div>
                                    <div class="requirement-status">
                                        <?php if($req['submitted']): ?>
                                            <span class="status-badge submitted-badge">
                                                <i class="fas fa-check-circle"></i> Submitted
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge missing-badge">
                                                <i class="fas fa-clock"></i> Not Submitted
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="requirement-actions">
                                    <?php if($req['submitted'] && $req['file']): ?>
                                        <a href="../<?php echo $req['file']; ?>" target="_blank" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-upload" onclick="openUploadModal('<?php echo $key; ?>', '<?php echo addslashes($req['label']); ?>', '<?php echo $req['allowed_types']; ?>', <?php echo $req['max_size']; ?>)">
                                            <i class="fas fa-upload"></i> Upload
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Instructions Card -->
                <div class="instructions-card">
                    <h3><i class="fas fa-info-circle"></i> How to Submit Requirements</h3>
                    <div class="instructions-list">
                        <div class="instruction-item">
                            <div class="instruction-number">1</div>
                            <div class="instruction-content">
                                <h4>Prepare Your Documents</h4>
                                <p>Make sure all required documents are clear and readable (PDF, JPG, or PNG format).</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">2</div>
                            <div class="instruction-content">
                                <h4>Click Upload Button</h4>
                                <p>Click the "Upload" button next to the requirement you want to submit.</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">3</div>
                            <div class="instruction-content">
                                <h4>Select File</h4>
                                <p>Choose the file from your computer and click "Submit".</p>
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-number">4</div>
                            <div class="instruction-content">
                                <h4>Wait for Verification</h4>
                                <p>After submission, the registrar will verify your documents within 3-5 business days.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Card -->
                <div class="contact-card">
                    <i class="fas fa-headset"></i>
                    <div class="contact-info">
                        <h4>Need Help?</h4>
                        <p>Contact the Registrar's Office for assistance with your requirements.</p>
                        <div class="contact-details">
                            <span><i class="fas fa-phone"></i> (032) 123-4567</span>
                            <span><i class="fas fa-envelope"></i> registrar@plsnhs.edu.ph</span>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Enrollment Found -->
                <div class="no-enrollment-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You don't have an active enrollment. Please enroll first to see your requirements.</p>
                    <a href="enrollment.php" class="btn-enroll">Enroll Now</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-upload"></i> Upload Requirement</h3>
                <button class="close-modal" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="requirement_key" id="requirement_key">
                <div class="modal-body">
                    <div class="upload-info">
                        <p>Uploading: <strong id="requirement_label"></strong></p>
                        <div class="file-info" id="fileInfo">
                            <i class="fas fa-info-circle"></i> Allowed formats: <span id="allowed_types"></span><br>
                            <i class="fas fa-weight-hanging"></i> Max size: <span id="max_size"></span>MB
                        </div>
                    </div>
                    <div class="file-input-wrapper">
                        <input type="file" name="requirement_file" id="requirement_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required>
                        <label for="requirement_file" class="file-input-label">
                            <i class="fas fa-folder-open"></i> Choose File
                        </label>
                        <div class="selected-file" id="selectedFile"></div>
                    </div>
                    <div id="uploadProgress" class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="uploadProgressFill"></div>
                        </div>
                        <p class="progress-text">Uploading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" name="upload_requirement" class="btn-primary" id="submitUpload">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Chatbot Widget -->
    <?php include('../includes/chatbot.php'); ?>

    <!-- Pass PHP data to JavaScript -->
    <script>
        const requirementsData = {
            studentId: <?php echo $student_id; ?>,
            studentName: '<?php echo htmlspecialchars($student_name); ?>',
            enrollmentId: <?php echo $enrollment['id'] ?? 0; ?>,
            totalRequirements: <?php echo $total_requirements; ?>,
            submittedCount: <?php echo $submitted_count; ?>,
            missingCount: <?php echo $missing_count; ?>,
            completionPercentage: <?php echo $completion_percentage; ?>
        };
    </script>

    <!-- Requirements JS -->
    <script src="js/requirements.js"></script>
</body>
</html>