<?php
include 'includes/header.php';

// ============================================
// SYSTEM SETTINGS PAGE
// ============================================

// Get system stats
$stats = [];

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$stats['active_students'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'Active'");
$stats['active_courses'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM admins");
$stats['total_admins'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM payments");
$stats['total_payments'] = $result->fetch_assoc()['count'];

// Database size
$result = $conn->query("SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES 
    WHERE table_schema = '" . DB_NAME . "'");
$stats['db_size'] = $result->fetch_assoc()['size_mb'];
?>

<div class="page-header">
    <h2><i class="fas fa-cog text-purple"></i> System Settings</h2>
    <p class="text-muted mb-0">System information and configuration</p>
</div>

<div class="row g-4">
    <!-- System Information -->
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-info-circle"></i> System Information</h5>
            
            <table class="table table-sm">
                <tr>
                    <td><i class="fas fa-desktop text-muted"></i> System Name</td>
                    <td><strong>Academy Fees Management</strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-code-branch text-muted"></i> Version</td>
                    <td><strong>1.0.0</strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-server text-muted"></i> PHP Version</td>
                    <td><strong><?php echo phpversion(); ?></strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-database text-muted"></i> Database</td>
                    <td><strong>MySQL <?php echo $conn->server_info; ?></strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-hdd text-muted"></i> Database Size</td>
                    <td><strong><?php echo $stats['db_size']; ?> MB</strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-calendar text-muted"></i> Current Date</td>
                    <td><strong><?php echo date('l, d F Y'); ?></strong></td>
                </tr>
                <tr>
                    <td><i class="fas fa-clock text-muted"></i> Server Time</td>
                    <td><strong><?php echo date('h:i:s A'); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Database Statistics -->
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-chart-pie"></i> Database Statistics</h5>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-user-graduate text-success"></i> Active Students</span>
                    <strong class="badge bg-success"><?php echo $stats['active_students']; ?></strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-book text-primary"></i> Active Courses</span>
                    <strong class="badge bg-primary"><?php echo $stats['active_courses']; ?></strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" style="width: <?php echo min(100, $stats['active_courses'] * 10); ?>%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-user-shield text-warning"></i> Total Admins</span>
                    <strong class="badge bg-warning"><?php echo $stats['total_admins']; ?></strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-warning" style="width: <?php echo $stats['total_admins'] * 20; ?>%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-receipt text-info"></i> Total Payments</span>
                    <strong class="badge bg-info"><?php echo $stats['total_payments']; ?></strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-info" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-12">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-bolt"></i> Quick Actions</h5>
            
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="students.php" class="btn btn-outline-purple w-100">
                        <i class="fas fa-user-plus"></i><br>
                        Enroll Student
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="add_payment.php" class="btn btn-outline-success w-100">
                        <i class="fa-solid fa-indian-rupee-sign"></i><br>
                        Add Payment
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="courses.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-book-medical"></i><br>
                        Add Course
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="reports.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-chart-line"></i><br>
                        View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Features -->
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-list-check"></i> System Features</h5>
            
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Student Management</span>
                    <span class="badge bg-success">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Course Management</span>
                    <span class="badge bg-success">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Payment Tracking</span>
                    <span class="badge bg-success">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Reports & Analytics</span>
                    <span class="badge bg-success">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Overdue Tracking</span>
                    <span class="badge bg-success">Active</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-check-circle text-success"></i> Multi-Admin Support</span>
                    <span class="badge bg-success">Active</span>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Important Links -->
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-link"></i> Important Links</h5>
            
            <div class="list-group list-group-flush">
                <a href="index.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-home text-purple"></i> Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user text-primary"></i> My Profile
                </a>
                <?php if (isSuperAdmin()): ?>
                <a href="admins.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users-cog text-warning"></i> Manage Admins
                </a>
                <?php endif; ?>
                <a href="past_students.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-history text-info"></i> Past Students
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- About System -->
    <div class="col-lg-12">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-info"></i> About This System</h5>
            
            <div class="row">
                <div class="col-md-8">
                    <p><strong>Academy Fees Management System</strong> is a comprehensive solution for managing student enrollments, course fees, and payment tracking.</p>
                    
                    <h6 class="text-purple mt-3">Key Features:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul>
                                <li>Student Enrollment & Management</li>
                                <li>Flexible Payment System</li>
                                <li>Course & Fee Management</li>
                                <li>Automatic Pending Calculation</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li>Monthly Overdue Tracking</li>
                                <li>Detailed Reports & Charts</li>
                                <li>Multi-User Support</li>
                                <li>Role-Based Access Control</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="alert alert-purple">
                        <h6 class="text-purple"><i class="fas fa-shield-alt"></i> System Security</h6>
                        <ul class="mb-0">
                            <li>Password Encryption</li>
                            <li>SQL Injection Protection</li>
                            <li>Session Management</li>
                            <li>Role-Based Permissions</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.alert-purple {
    background-color: #f3e8ff;
    border-color: #e9d5ff;
    color: #5b21b6;
}
</style>

<?php include 'includes/footer.php'; ?>