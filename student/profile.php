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
$sidebar_profile_pic = $_SESSION['user']['profile_picture'] ?? null;
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

// Get student details
$query = "SELECT * FROM users WHERE id = :student_id AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = null;

// Get profile picture from database
$profile_picture = $student['profile_picture'] ?? null;
$sidebar_profile_pic = $student['profile_picture'] ?? null;
$_SESSION['user']['profile_picture'] = $profile_picture;

// Handle profile picture upload
if(isset($_POST['upload_profile_pic'])) {
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if(in_array($ext, $allowed)) {
            $upload_dir = "../uploads/profile_pictures/";
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old profile picture if exists
            if($profile_picture && file_exists("../" . $profile_picture)) {
                unlink("../" . $profile_picture);
            }
            
            $new_filename = "student_" . $student_id . "_" . time() . "." . $ext;
            $upload_path = $upload_dir . $new_filename;
            $db_path = "uploads/profile_pictures/" . $new_filename;
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :student_id");
                $update_stmt->execute([
                    ':profile_picture' => $db_path,
                    ':student_id' => $student_id
                ]);
                
                $_SESSION['user']['profile_picture'] = $db_path;
                $profile_picture = $db_path;
                $sidebar_profile_pic = $db_path;
                $success_message = "Profile picture updated successfully!";
                header("Location: profile.php?success=1");
                exit();
            } else {
                $error_message = "Failed to upload image. Please try again.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else {
        $error_message = "Please select an image to upload.";
    }
}

// Handle remove profile picture
if(isset($_GET['remove_pic'])) {
    if($profile_picture && file_exists("../" . $profile_picture)) {
        unlink("../" . $profile_picture);
    }
    
    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = :student_id");
    $update_stmt->execute([':student_id' => $student_id]);
    
    unset($_SESSION['user']['profile_picture']);
    $profile_picture = null;
    $sidebar_profile_pic = null;
    $success_message = "Profile picture removed successfully!";
    header("Location: profile.php?success=2");
    exit();
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

$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';
$enrollment_date = $enrollment ? $enrollment['created_at'] : null;

// Get attendance statistics
$attendance_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0
];

$attendance_query = "
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = :student_id
    GROUP BY status
";
$stmt = $conn->prepare($attendance_query);
$stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
$stmt->execute();

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $attendance_stats[strtolower($row['status'])] = $row['count'];
    $attendance_stats['total'] += $row['count'];
}
$stmt = null;

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 2) 
    : 0;

// Get enrolled subjects count
$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $subjects_query = "SELECT COUNT(*) as count FROM subjects WHERE grade_id = :grade_id";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bindParam(':grade_id', $enrollment['grade_id'], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $subjects_count = $row['count'] ?? 0;
    $stmt = null;
}

$account_created = $student['created_at'] ?? date('Y-m-d H:i:s');
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Profile CSS -->
    <link rel="stylesheet" href="css/profile.css">
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
                    <?php if($sidebar_profile_pic && file_exists("../" . $sidebar_profile_pic)): ?>
                        <img src="../<?php echo $sidebar_profile_pic; ?>?t=<?php echo time(); ?>" alt="Profile">
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
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">ACCOUNT</div>
                    <ul class="nav-items">
                        <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Profile</h1>
                    <p>View and manage your personal information</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Left Column - Profile Info -->
                <div>
                    <div class="profile-card">
                        <div class="profile-avatar-large" onclick="openImageModal()">
                            <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                                <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-initial">
                                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <span class="profile-role">Student</span>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $days_active; ?></div>
                                <div class="stat-label">Days Active</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $attendance_stats['total']; ?></div>
                                <div class="stat-label">Total Attendance</div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-id-card"></i></div>
                            <div class="info-content">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?php echo $student['id_number'] ?? 'Not assigned'; ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="info-content">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Academic Info -->
                <div>
                    <!-- Academic Information -->
                    <div class="academic-card">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                        
                        <?php if($enrollment): ?>
                            <div class="academic-grid">
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($grade_name); ?></div>
                                    <div class="academic-label">Grade Level</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($strand); ?></div>
                                    <div class="academic-label">Strand</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($school_year); ?></div>
                                    <div class="academic-label">School Year</div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; flex-wrap: wrap; gap: 10px;">
                                <span class="enrollment-badge badge-<?php echo strtolower($enrollment_status); ?>">
                                    <i class="fas fa-<?php echo $enrollment_status == 'Enrolled' ? 'check-circle' : 'clock'; ?>"></i>
                                    Status: <?php echo $enrollment_status; ?>
                                </span>
                                <?php if($enrollment_date): ?>
                                    <span style="color: var(--text-gray); font-size: 13px;">
                                        <i class="far fa-calendar"></i> Enrolled: <?php echo date('M d, Y', strtotime($enrollment_date)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="info-box">
                                <i class="fas fa-book-open"></i>
                                <p>You are currently enrolled in <?php echo $subjects_count; ?> subjects for this grade level.</p>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-graduation-cap"></i>
                                <h3>Not Enrolled</h3>
                                <p>You are not currently enrolled in any grade level.</p>
                                <p style="font-size: 13px; margin-top: 10px;">Please contact the registrar's office for assistance.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Links -->
                    <div class="academic-card">
                        <h3><i class="fas fa-link"></i> Quick Links</h3>
                        
                        <div class="quick-links-grid">
                            <a href="schedule.php" class="quick-link">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Class Schedule</span>
                            </a>
                            <a href="grades.php" class="quick-link">
                                <i class="fas fa-star"></i>
                                <span>My Grades</span>
                            </a>
                            <a href="enrollment_history.php" class="quick-link">
                                <i class="fas fa-history"></i>
                                <span>Enrollment History</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Upload Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Update Profile Picture</h3>
                <button class="close-modal" onclick="closeImageModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="image-preview" id="imagePreview">
                        <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                            <img src="../<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" alt="Profile Preview">
                        <?php else: ?>
                            <div class="avatar-initial">
                                <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-input-wrapper">
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                        <label for="profile_picture" class="file-input-label">
                            <i class="fas fa-upload"></i> Choose Image
                        </label>
                    </div>
                    <p style="font-size: 12px; color: var(--text-gray); margin-top: 10px;">
                        <i class="fas fa-info-circle"></i> Allowed formats: JPG, PNG, GIF, WEBP. Max size: 5MB
                    </p>
                </div>
                <div class="modal-footer">
                    <?php if($profile_picture): ?>
                        <a href="?remove_pic=1" class="btn-danger" onclick="return confirm('Remove your profile picture?')">
                            <i class="fas fa-trash"></i> Remove
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" onclick="closeImageModal()">Cancel</button>
                    <button type="submit" name="upload_profile_pic" class="btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }

        // Image modal functions
        function openImageModal() {
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.getElementById('imagePreview');
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

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