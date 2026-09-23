<?php
include 'includes/header.php';
requireSuperAdmin();
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $username = sanitize($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            $check = $conn->query("SELECT id FROM admins WHERE username = '$username'");
            if ($check->num_rows > 0) {
                $_SESSION['error'] = "Username already exists!";
            } else {
                $stmt = $conn->prepare("INSERT INTO admins (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $password, $full_name, $email, $role);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Admin added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add admin.";
                }
                $stmt->close();
            }
        }
        
        // ===== ADD EDIT ACTION =====
        elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            $stmt = $conn->prepare("UPDATE admins SET full_name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $role, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Admin updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update admin.";
            }
            $stmt->close();
        }
        
        // ===== ADD CHANGE PASSWORD ACTION =====
        elseif ($_POST['action'] === 'change_password') {
            $id = (int)$_POST['id'];
            $new_password = $_POST['new_password'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Password changed successfully!";
            } else {
                $_SESSION['error'] = "Failed to change password.";
            }
            $stmt->close();
        }
        
        header('Location: admins.php');
        exit();
    }
}
if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];
    $newAdminId = 5;

    if ($id == $admin['id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
    } else {

        // Transfer everywhere
        $tables = [
            "payments" => "created_by",
            "student_groups" => "created_by",
            "student_hold_history" => "created_by",
            "student_manual_points" => "awarded_by",
            "expenses" => "created_by",
            "tasks" => "created_by",
            "group_topic_progress" => "completed_by",
            "admin_task_history" => "admin_id",
            "admin_tasks" => "admin_id",
        ];

        foreach ($tables as $table => $column) {
            $stmt = $conn->prepare("UPDATE $table SET $column = ? WHERE $column = ?");
            $stmt->bind_param("ii", $newAdminId, $id);
            $stmt->execute();
            $stmt->close();
        }

        // Delete admin
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Admin deleted successfully!";
    }

    header("Location: admins.php");
    exit();
}


$admins = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-user-shield text-purple"></i> Admin Management</h2>
        <p class="text-muted mb-0">Manage system administrators</p>
    </div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addAdminModal">
        <i class="fas fa-plus"></i> Add Admin
    </button>
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

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($adm = $admins->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $adm['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($adm['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($adm['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($adm['email']); ?></td>
                   <td>
    <span class="badge <?php echo $adm['role'] === 'Super Admin' ? 'badge-purple' : ($adm['role'] === 'Administrator' ? 'bg-info' : 'bg-secondary'); ?>">
        <?php echo $adm['role']; ?>
    </span>
</td>
                    <td>
                        <?php if ($adm['last_login']): ?>
                            <small><?php echo date('d M Y, h:i A', strtotime($adm['last_login'])); ?></small>
                        <?php else: ?>
                            <small class="text-muted">Never</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick='editAdmin(<?php echo json_encode($adm); ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="changePassword(<?php echo $adm['id']; ?>, '<?php echo htmlspecialchars($adm['username']); ?>')">
                            <i class="fas fa-key"></i>
                        </button>
                        <?php if ($adm['id'] != $admin['id']): ?>
                        <a href="?delete=<?php echo $adm['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this admin?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                <div class="mb-3">
    <label class="form-label">Role *</label>
    <select class="form-select" id="edit_role" name="role" required>
        <option value="Admin">Admin</option>
        <option value="Super Admin">Super Admin</option>
        <option value="Administrator">Administrator</option>
    </select>
</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    
                 
<div class="mb-3">
    <label class="form-label">Role *</label>
    <select class="form-select" name="role" required>
        <option value="Admin">Admin</option>
        <option value="Super Admin">Super Admin</option>
        <option value="Administrator">Administrator</option>
    </select>
</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Update Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="id" id="pwd_id">
                    
                    <div class="alert alert-info">
                        Changing password for: <strong id="pwd_username"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAdmin(admin) {
    document.getElementById('edit_id').value = admin.id;
    document.getElementById('edit_username').value = admin.username;
    document.getElementById('edit_full_name').value = admin.full_name;
    document.getElementById('edit_email').value = admin.email;
    document.getElementById('edit_role').value = admin.role;
    
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
}

function changePassword(id, username) {
    document.getElementById('pwd_id').value = id;
    document.getElementById('pwd_username').textContent = username;
    
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>