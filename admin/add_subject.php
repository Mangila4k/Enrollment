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
$error = '';
$success = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Get grade levels for dropdown
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_name = trim($_POST['subject_name']);
    $grade_id = $_POST['grade_id'];
    
    // Validation
    $errors = [];
    
    if(empty($subject_name)) {
        $errors[] = "Subject name is required";
    }
    
    if(empty($grade_id)) {
        $errors[] = "Grade level is required";
    }
    
    // Check if subject already exists for this grade level
    if(empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? AND grade_id = ?");
        $check_stmt->execute([$subject_name, $grade_id]);
        
        if($check_stmt->rowCount() > 0) {
            $errors[] = "Subject already exists for this grade level";
        }
    }
    
    // If no errors, insert the subject
    if(empty($errors)) {
        try {
            $insert_stmt = $conn->prepare("INSERT INTO subjects (subject_name, grade_id) VALUES (?, ?)");
            $insert_stmt->execute([$subject_name, $grade_id]);
            
            $_SESSION['success_message'] = "Subject added successfully!";
            header("Location: subjects.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If there are errors, store them
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Subject - PLSNHS | Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Add Subject CSS -->
    <link rel="stylesheet" href="css/add_subject.css">
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
                    <h1>Add New Subject</h1>
                    <p>Create a new subject for a grade level</p>
                </div>
                <a href="subjects.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Subjects</a>
            </div>

            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="form-container">
                <div class="form-card">
                    <h3>
                        <i class="fas fa-book-medical"></i>
                        Subject Information
                    </h3>

                    <form method="POST" action="" id="subjectForm">
                        <!-- Subject Category Quick Select -->
                        <div class="category-tags" id="categoryTags">
                            <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                            <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                        </div>

                        <div class="form-group">
                            <label>Subject Name <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="text" 
                                       id="subject_name" 
                                       name="subject_name" 
                                       placeholder="Enter Subject Name" 
                                       value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : 'Enter Subject Name'; ?>" 
                                       required>
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Click on category tags above to add a prefix. The prefix cannot be erased once added.
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Grade Level <span>*</span></label>
                            <select id="grade_id" name="grade_id" required>
                                <option value="">Select Grade Level</option>
                                <?php foreach($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" 
                                        <?php echo (isset($_POST['grade_id']) && $_POST['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['grade_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Quick Add Common Subjects -->
                        <div class="quick-add-section">
                            <h4><i class="fas fa-bolt"></i> Quick Add Common Subjects</h4>
                            <div class="quick-buttons" id="quickButtons">
                                <!-- Buttons will be populated dynamically based on grade level -->
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="preview-card">
                            <h4><i class="fas fa-eye"></i> Subject Preview</h4>
                            <div class="preview-item">
                                <div class="preview-icon" id="previewIcon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="preview-details">
                                    <h5 id="previewName"><?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : 'Enter Subject Name'; ?></h5>
                                    <div>
                                        <span class="preview-grade" id="previewGrade">
                                            <?php 
                                            if(isset($_POST['grade_id'])) {
                                                foreach($grade_levels as $g) {
                                                    if($g['id'] == $_POST['grade_id']) {
                                                        echo $g['grade_name'];
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo "Grade Level";
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Add Subject
                            </button>
                            <a href="subjects.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Create grade options object for preview
        const gradeOptions = {};
        <?php foreach($grade_levels as $grade): ?>
            gradeOptions[<?php echo $grade['id']; ?>] = "<?php echo $grade['grade_name']; ?>";
        <?php endforeach; ?>

        // Subject lists by grade level
        const juniorHighSubjects = [
            'Mathematics', 'Science', 'English', 'Filipino', 'Araling Panlipunan', 
            'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'
        ];
        
        const seniorHighSubjects = [
            'General Mathematics', 'Statistics and Probability', 'Earth Science', 'Physical Science',
            '21st Century Literature', 'Oral Communication', 'Reading and Writing Skills',
            'Personal Development', 'Understanding Culture, Society and Politics',
            'Introduction to Philosophy', 'Physical Education and Health'
        ];

        // Get DOM elements
        const subjectNameInput = document.getElementById('subject_name');
        const gradeSelect = document.getElementById('grade_id');
        const previewName = document.getElementById('previewName');
        const previewGrade = document.getElementById('previewGrade');
        const quickButtons = document.getElementById('quickButtons');

        let currentCategory = null;
        let isPrefixProtected = false;
        let currentPrefix = '';

        // Function to select a category and add protected prefix
        function selectCategory(category) {
            const gradeId = parseInt(gradeSelect.value);
            
            // Check if grade level is selected
            if (!gradeId) {
                alert('Please select a grade level first');
                return;
            }
            
            // For Senior High, only allow Major category (handled separately)
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            if (isSeniorHigh) {
                alert('For Senior High, only "Major" category is available.');
                return;
            }
            
            currentCategory = category;
            const prefix = category + ':';
            
            // Remove any existing prefix
            const prefixes = ['Core:', 'Major:', 'Elective:'];
            let currentValue = subjectNameInput.value;
            for (let p of prefixes) {
                if (currentValue.startsWith(p)) {
                    currentValue = currentValue.substring(p.length).trim();
                    break;
                }
            }
            
            // Set new value with prefix
            if (currentValue === 'Enter Subject Name') {
                subjectNameInput.value = prefix + ' Enter Subject Name';
            } else {
                subjectNameInput.value = prefix + ' ' + currentValue;
            }
            
            isPrefixProtected = true;
            currentPrefix = prefix;
            
            // Update active state on category tags
            document.querySelectorAll('.category-tag').forEach(tag => {
                tag.classList.remove('active-category');
            });
            event.target.classList.add('active-category');
            
            updatePreview();
        }

        // Function to enforce prefix protection
        function enforcePrefixProtection() {
            const currentValue = subjectNameInput.value;
            
            if (isPrefixProtected && currentPrefix) {
                if (!currentValue.startsWith(currentPrefix)) {
                    subjectNameInput.value = currentPrefix + ' ' + currentValue;
                }
            }
        }

        // Function to prevent erasing the protected prefix
        function protectPrefix(e) {
            if (!isPrefixProtected || !currentPrefix) return;
            
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const prefixLength = currentPrefix.length;
            
            // Check if user is trying to delete the prefix
            if (start < prefixLength && end > 0) {
                e.preventDefault();
                alert(`The "${currentPrefix}" prefix is protected and cannot be erased.`);
                return false;
            }
        }

        // Function to handle input while protecting prefix
        function handleInput(e) {
            if (!isPrefixProtected || !currentPrefix) return;
            
            let newValue = this.value;
            
            // Ensure prefix is always present
            if (!newValue.startsWith(currentPrefix)) {
                if (newValue === '' || newValue === 'Enter Subject Name') {
                    this.value = currentPrefix + ' Enter Subject Name';
                } else {
                    this.value = currentPrefix + ' ' + newValue;
                }
            }
            
            // Prevent deleting the prefix entirely
            if (newValue === currentPrefix) {
                this.value = currentPrefix + ' Enter Subject Name';
            }
            
            updatePreview();
        }

        // Function to update category tags based on grade level
        function updateCategoryTags() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            const categoryContainer = document.getElementById('categoryTags');
            
            if (isSeniorHigh) {
                // For Senior High, show only Major category
                categoryContainer.innerHTML = `
                    <span class="category-tag major" onclick="selectMajorCategory()">⭐ Major Subject (Required)</span>
                `;
                // Auto-select Major category
                if (!currentCategory) {
                    selectMajorCategory();
                }
            } else if (gradeId) {
                // For Junior High, show Core and Elective
                categoryContainer.innerHTML = `
                    <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                    <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                `;
                // Reset prefix protection if no category selected
                if (!currentCategory) {
                    isPrefixProtected = false;
                    currentPrefix = '';
                }
            } else {
                // No grade selected, show default
                categoryContainer.innerHTML = `
                    <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                    <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                `;
            }
        }

        // Function to select Major category (for Senior High)
        function selectMajorCategory() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (!isSeniorHigh) {
                alert('Major category is only available for Senior High (Grades 11-12)');
                return;
            }
            
            currentCategory = 'Major';
            currentPrefix = 'Major:';
            
            // Set the value with prefix
            let currentValue = subjectNameInput.value;
            if (currentValue === 'Enter Subject Name' || currentValue === '' || currentValue === 'Core:' || currentValue === 'Elective:') {
                subjectNameInput.value = currentPrefix + ' Enter Subject Name';
            } else {
                // Remove any existing prefix
                const prefixes = ['Core:', 'Major:', 'Elective:'];
                for (let p of prefixes) {
                    if (currentValue.startsWith(p)) {
                        currentValue = currentValue.substring(p.length).trim();
                        break;
                    }
                }
                subjectNameInput.value = currentPrefix + ' ' + currentValue;
            }
            
            isPrefixProtected = true;
            
            // Update active state
            document.querySelectorAll('.category-tag').forEach(tag => {
                tag.classList.remove('active-category');
            });
            const activeTag = document.querySelector('.category-tag.major');
            if (activeTag) activeTag.classList.add('active-category');
            
            updatePreview();
        }

        // Function to update quick buttons based on grade level
        function updateQuickButtons() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (!gradeId) {
                quickButtons.innerHTML = '<p style="color: var(--text-gray); font-size: 12px;">Select a grade level to see quick add options</p>';
                return;
            }
            
            let subjects = [];
            if (isSeniorHigh) {
                subjects = seniorHighSubjects;
            } else {
                subjects = juniorHighSubjects;
            }
            
            // Generate buttons
            quickButtons.innerHTML = subjects.map(subject => 
                `<button type="button" class="quick-btn" onclick="setSubjectName('${subject.replace(/'/g, "\\'")}')">${subject}</button>`
            ).join('');
        }

        // Set subject name from quick add
        function setSubjectName(name) {
            let currentValue = subjectNameInput.value;
            
            if (isPrefixProtected && currentPrefix) {
                // For protected fields, add after the prefix
                if (currentValue === currentPrefix + ' Enter Subject Name' || currentValue === currentPrefix) {
                    subjectNameInput.value = currentPrefix + ' ' + name;
                } else {
                    // Remove prefix for checking duplicates
                    let cleanValue = currentValue.substring(currentPrefix.length).trim();
                    if (!cleanValue.includes(name)) {
                        subjectNameInput.value = currentValue + ', ' + name;
                    } else {
                        alert('This subject is already in the list');
                    }
                }
            } else {
                // For unprotected fields
                if (currentValue === 'Enter Subject Name') {
                    subjectNameInput.value = name;
                } else {
                    if (!currentValue.includes(name)) {
                        subjectNameInput.value = currentValue + ', ' + name;
                    } else {
                        alert('This subject is already in the list');
                    }
                }
            }
            
            updatePreview();
            subjectNameInput.focus();
        }

        // Update preview function
        function updatePreview() {
            // Update subject name
            const subjectName = subjectNameInput.value.trim() || 'New Subject';
            previewName.textContent = subjectName;

            // Update grade
            const gradeId = gradeSelect.value;
            if (gradeId && gradeOptions[gradeId]) {
                previewGrade.textContent = gradeOptions[gradeId];
            } else {
                previewGrade.textContent = 'Grade Level';
            }
        }

        // Reset category when grade changes
        function resetCategory() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (isSeniorHigh) {
                // For Senior High, automatically set Major category
                selectMajorCategory();
            } else {
                // For Junior High, reset protection
                currentCategory = null;
                isPrefixProtected = false;
                currentPrefix = '';
                
                // Remove any existing prefix from the value
                let currentValue = subjectNameInput.value;
                const prefixes = ['Core:', 'Major:', 'Elective:'];
                for (let p of prefixes) {
                    if (currentValue.startsWith(p)) {
                        currentValue = currentValue.substring(p.length).trim();
                        subjectNameInput.value = currentValue;
                        break;
                    }
                }
                
                // Remove active class from category tags
                document.querySelectorAll('.category-tag').forEach(tag => {
                    tag.classList.remove('active-category');
                });
            }
            updatePreview();
        }

        // Add event listeners
        if (subjectNameInput) {
            subjectNameInput.addEventListener('keydown', protectPrefix);
            subjectNameInput.addEventListener('input', handleInput);
            subjectNameInput.addEventListener('blur', enforcePrefixProtection);
        }
        
        if (gradeSelect) {
            gradeSelect.addEventListener('change', function() {
                resetCategory();
                updateCategoryTags();
                updateQuickButtons();
                updatePreview();
            });
        }

        // Initialize on page load
        updateCategoryTags();
        updateQuickButtons();
        updatePreview();

        // Form validation
        document.getElementById('subjectForm').addEventListener('submit', function(e) {
            const subjectName = subjectNameInput.value.trim();
            const gradeId = gradeSelect.value;
            
            if (gradeId === '5' || gradeId === '6') {
                if (!subjectName.startsWith('Major:') || subjectName === 'Major:' || subjectName === 'Major: Enter Subject Name') {
                    e.preventDefault();
                    alert('For Senior High subjects, you must enter a subject name with the "Major:" prefix.');
                    return false;
                }
            } else if (gradeId) {
                // For Junior High, check if a category is selected
                if (!isPrefixProtected && subjectName !== 'Enter Subject Name' && subjectName !== '') {
                    // Allow if user manually entered without category
                    if (!subjectName.startsWith('Core:') && !subjectName.startsWith('Elective:')) {
                        if (subjectName === 'Enter Subject Name' || subjectName === '') {
                            e.preventDefault();
                            alert('Please select a category (Core or Elective) or enter a valid subject name.');
                            return false;
                        }
                    }
                } else if (subjectName === 'Enter Subject Name' || subjectName === 'Core: Enter Subject Name' || subjectName === 'Elective: Enter Subject Name') {
                    e.preventDefault();
                    alert('Please enter a valid subject name');
                    return false;
                }
            }
            
            if (!gradeId) {
                e.preventDefault();
                alert('Please select a grade level');
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