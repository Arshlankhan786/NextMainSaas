<?php
include 'includes/header.php';

// ============================================
// ADMIN PROFILE PAGE
// ============================================

$admin_id = $admin['id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_profile') {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        
        $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $admin_id);
        
        if ($stmt->execute()) {
            $_SESSION['admin_name'] = $full_name;
            $_SESSION['admin_email'] = $email;
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update profile.";
        }
        $stmt->close();
        header('Location: profile.php');
        exit();
    }
    
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password hash
        $result = $conn->query("SELECT password FROM admins WHERE id = $admin_id");
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed, $admin_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Password changed successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to change password.";
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Password must be at least 6 characters.";
                }
            } else {
                $_SESSION['error'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
        
        header('Location: profile.php');
        exit();
    }
}

// Get admin details
$admin_details = $conn->query("SELECT * FROM admins WHERE id = $admin_id")->fetch_assoc();

// Get activity stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE created_by = $admin_id");
$stats['payments_recorded'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['recent_enrollments'] = $result->fetch_assoc()['count'];
?>

<div class="page-header">
    <h2><i class="fas fa-user text-purple"></i> My Profile</h2>
    <p class="text-muted mb-0">Manage your account settings</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Info Card -->
    <div class="col-lg-4">
        <div class="table-card">
            <div class="text-center mb-4">
                <div class="bg-purple text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px; font-size: 50px;">
                    <i class="fas fa-user"></i>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($admin_details['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($admin_details['username']); ?></p>
                <span class="badge bg-purple"><?php echo $admin_details['role']; ?></span>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-envelope"></i> Email</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($admin_details['email']); ?></strong></p>
            </div>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-calendar"></i> Member Since</label>
                <p class="mb-0"><?php echo date('d M Y', strtotime($admin_details['created_at'])); ?></p>
            </div>
            
            <?php if ($admin_details['last_login']): ?>
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-clock"></i> Last Login</label>
                <p class="mb-0"><?php echo date('d M Y, h:i A', strtotime($admin_details['last_login'])); ?></p>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <h6 class="text-purple mb-3"><i class="fas fa-chart-line"></i> Your Activity</h6>
            <div class="mb-2">
                <small class="text-muted">Payments Recorded</small>
                <div class="d-flex justify-content-between align-items-center">
                    <strong><?php echo $stats['payments_recorded']; ?></strong>
                    <span class="badge bg-success">Total</span>
                </div>
            </div>
            <div class="mb-2">
                <small class="text-muted">Recent Enrollments (30 days)</small>
                <div class="d-flex justify-content-between align-items-center">
                    <strong><?php echo $stats['recent_enrollments']; ?></strong>
                    <span class="badge bg-info">Students</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Forms -->
    <div class="col-lg-8">
        <!-- Update Profile Info -->
        <div class="table-card mb-4">
            <h5 class="text-purple mb-3"><i class="fas fa-user-edit"></i> Update Profile Information</h5>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($admin_details['full_name']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin_details['email']); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_details['username']); ?>" disabled>
                    <small class="text-muted">Username cannot be changed</small>
                </div>
                
                <button type="submit" class="btn btn-purple">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-key"></i> Change Password</h5>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-3">
                    <label class="form-label">Current Password *</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" minlength="6" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" minlength="6" required>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Password Requirements:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Minimum 6 characters</li>
                        <li>Both passwords must match</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Password match validation
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>