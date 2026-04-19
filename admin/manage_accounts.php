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

// Handle user approval
if(isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $user_id = $_GET['approve'];
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$admin_id, $user_id]);
        $success_message = "User approved successfully!";
    } catch(PDOException $e) {
        $error_message = "Error approving user: " . $e->getMessage();
    }
}

// Handle user rejection
if(isset($_POST['reject_user'])) {
    $user_id = $_POST['user_id'];
    $reason = $_POST['rejection_reason'];
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$reason, $admin_id, $user_id]);
        $success_message = "User rejected successfully!";
    } catch(PDOException $e) {
        $error_message = "Error rejecting user: " . $e->getMessage();
    }
}

// Handle user deletion
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Check if user exists and not deleting self
    if($delete_id != $_SESSION['user']['id']) {
        try {
            // Check if user has related records
            $check_enrollments = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ?");
            $check_enrollments->execute([$delete_id]);
            
            $check_attendance = $conn->prepare("SELECT id FROM attendance WHERE student_id = ?");
            $check_attendance->execute([$delete_id]);
            
            $check_sections = $conn->prepare("SELECT id FROM sections WHERE adviser_id = ?");
            $check_sections->execute([$delete_id]);
            
            $check_teacher_attendance = $conn->prepare("SELECT id FROM teacher_attendance WHERE teacher_id = ?");
            $check_teacher_attendance->execute([$delete_id]);
            
            if($check_enrollments->rowCount() > 0 || $check_attendance->rowCount() > 0 || 
               $check_sections->rowCount() > 0 || $check_teacher_attendance->rowCount() > 0) {
                $error_message = "Cannot delete user because they have related records (enrollments, attendance, or sections).";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                $success_message = "User deleted successfully!";
            }
        } catch(PDOException $e) {
            $error_message = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $error_message = "You cannot delete your own account!";
    }
}

// Handle role filter and search
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get counts for stats
try {
    // Get pending accounts count
    $pending_count_stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
    $pending_count = $pending_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get approved accounts count
    $approved_count_stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'approved'");
    $approved_count = $approved_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get rejected accounts count
    $rejected_count_stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'rejected'");
    $rejected_count = $rejected_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get pending users for display
    $pending_users_stmt = $conn->prepare("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
    $pending_users_stmt->execute();
    $pending_users = $pending_users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Build query for users
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if(!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if(!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if(!empty($search)) {
    $query .= " AND (fullname LIKE ? OR email LIKE ? OR id_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY 
    CASE status 
        WHEN 'pending' THEN 1 
        WHEN 'approved' THEN 2 
        WHEN 'rejected' THEN 3 
    END, 
    created_at DESC";

// Prepare and execute
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $users = [];
}

// Get role counts
$counts = [];
$roles = ['Admin', 'Registrar', 'Teacher', 'Student'];
foreach($roles as $role) {
    try {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'approved'");
        $count_stmt->execute([$role]);
        $counts[$role] = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch(PDOException $e) {
        $counts[$role] = 0;
    }
}

$total_users = array_sum($counts);

// Function to get role badge color
function getRoleColor($role) {
    switch(strtolower($role)) {
        case 'admin': return '#dc3545';
        case 'registrar': return '#fd7e14';
        case 'teacher': return '#28a745';
        case 'student': return '#007bff';
        default: return '#6c757d';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - PLSNHS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Base CSS -->
    <link rel="stylesheet" href="css/base.css">
    <!-- Accounts Page Specific CSS -->
    <link rel="stylesheet" href="css/accounts.css">
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
                        <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
                        <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> Enrollments</a></li>
                    </ul>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">MANAGEMENT</div>
                    <ul class="nav-items">
                        <li><a href="manage_accounts.php" class="active"><i class="fas fa-users-cog"></i> Accounts</a></li>
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
                    <h1>Account Management</h1>
                    <p>Manage user accounts, approvals, and roles in the system</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header"><h3>Total Users</h3><div class="stat-icon"><i class="fas fa-users"></i></div></div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Approved accounts</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header"><h3>Pending Approval</h3><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header"><h3>Approved</h3><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Active accounts</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header"><h3>Rejected</h3><div class="stat-icon"><i class="fas fa-times-circle"></i></div></div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected accounts</div>
                </div>
            </div>

            <!-- Pending Accounts Section -->
            <?php if($pending_count > 0): ?>
            <div class="pending-section">
                <div class="pending-header">
                    <h3><i class="fas fa-clock"></i> Pending Account Approvals</h3>
                    <span class="pending-badge"><i class="fas fa-users"></i> <?php echo $pending_count; ?> pending</span>
                </div>

                <table class="pending-table">
                    <thead>
                        <tr><th>ID Number</th><th>Full Name</th><th>Email</th><th>Role</th><th>Registered On</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($pending_users) > 0): ?>
                            <?php foreach($pending_users as $pending): ?>
                            <tr>
                                <td><span class="id-badge"><?php echo htmlspecialchars($pending['id_number'] ?: 'N/A'); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($pending['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                <td>
                                    <?php $pending_role_color = getRoleColor($pending['role']); ?>
                                    <span class="role-badge <?php echo strtolower($pending['role']); ?>" style="background: <?php echo $pending_role_color; ?> !important; color: white !important; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; text-align: center; min-width: 85px;">
                                        <?php echo $pending['role']; ?>
                                    </span>
                                </td>
                                <td><i class="far fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($pending['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="?approve=<?php echo $pending['id']; ?>" class="btn-approve" onclick="return confirm('Approve this user account?')"><i class="fas fa-check"></i> Approve</a>
                                        <button class="btn-reject" onclick="openRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars($pending['fullname']); ?>')"><i class="fas fa-times"></i> Reject</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6"><div class="no-pending"><i class="fas fa-check-circle"></i><p>No pending approvals at the moment.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Section Title and Add Button -->
            <div class="section-title">
                <h2><i class="fas fa-list"></i> All Accounts</h2>
                <a href="add_account.php" class="btn-add"><i class="fas fa-plus-circle"></i> Add New Account</a>
            </div>

            <!-- Search and Filter -->
            <div class="search-bar">
                <form method="GET" class="filter-form">
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="role" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="Admin" <?php echo $role_filter == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Registrar" <?php echo $role_filter == 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                        <option value="Teacher" <?php echo $role_filter == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="Student" <?php echo $role_filter == 'Student' ? 'selected' : ''; ?>>Student</option>
                    </select>

                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Filter</button>
                    <a href="manage_accounts.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge"><?php echo htmlspecialchars($user['id_number'] ?: 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td>
                                        <?php $role_color = getRoleColor($user['role']); ?>
                                        <span class="role-badge <?php echo strtolower($user['role']); ?>" style="background: <?php echo $role_color; ?> !important; color: white !important; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; text-align: center; min-width: 85px;">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="activity-time">
                                            <i class="far fa-calendar"></i> 
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_account.php?id=<?php echo $user['id']; ?>" class="btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if($user['status'] == 'pending'): ?>
                                                <a href="?approve=<?php echo $user['id']; ?>" class="btn-approve" onclick="return confirm('Approve this user?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <button class="btn-reject" onclick="openRejectModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname']); ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            <?php if($user['role'] != 'Admin'): ?>
                                                <a href="edit_account.php?id=<?php echo $user['id']; ?>" class="btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if($user['id'] != $_SESSION['user']['id']): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user?')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if(isset($user['rejection_reason']) && $user['rejection_reason']): ?>
                                <tr class="rejection-row">
                                    <td colspan="7">
                                        <div class="rejection-reason">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($user['rejection_reason']); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="fas fa-users"></i>
                                        <h3>No Users Found</h3>
                                        <p>No user accounts match your search criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Reject User</h3>
                <button class="close-modal" onclick="closeRejectModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="reject_user_id">
                <div class="modal-body">
                    <p>You are about to reject <strong id="reject_user_name"></strong>. Please provide a reason for rejection (optional):</p>
                    <textarea name="rejection_reason" placeholder="Enter rejection reason..." style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px; font-family: inherit;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject_user" class="btn-save">Reject User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
<script src="js/accounts.js"></script>
</body>
</html>