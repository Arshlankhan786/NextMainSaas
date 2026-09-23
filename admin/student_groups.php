<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $admin_id = $_SESSION['admin_id'];
    
    if ($_POST['action'] === 'create_group') {
        $group_name = sanitize($_POST['group_name']);
        $course_id = (int)$_POST['course_id'];
        
        // Check if group already exists with same name and course
        $existing = $conn->query("SELECT id FROM student_groups WHERE group_name = '$group_name' AND course_id = $course_id");
        
        if ($existing->num_rows > 0) {
            $_SESSION['error'] = "A group with this name already exists for this course!";
            header('Location: student_groups.php');
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO student_groups (group_name, course_id, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $group_name, $course_id, $admin_id);
        
        if ($stmt->execute()) {
            $group_id = $stmt->insert_id;
            
            // Add members
            if (!empty($_POST['student_ids']) && is_array($_POST['student_ids'])) {
                foreach ($_POST['student_ids'] as $student_id) {
                    $student_id = (int)$student_id;
                    if ($student_id > 0) {
                        $conn->query("INSERT INTO student_group_members (group_id, student_id) VALUES ($group_id, $student_id)");
                    }
                }
            }
            
            // Initialize topics
            $topics = $conn->query("SELECT id FROM course_topics WHERE course_id = $course_id ORDER BY order_index ASC");
            if ($topics && $topics->num_rows > 0) {
                while ($topic = $topics->fetch_assoc()) {
                    $conn->query("INSERT INTO group_topic_progress (group_id, topic_id, status) VALUES ($group_id, {$topic['id']}, 'upcoming')");
                }
            }
            
            $_SESSION['success'] = "Group created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create group. Error: " . $stmt->error;
        }
        $stmt->close();
        header('Location: student_groups.php');
        exit();
    }
}
// Get all groups
$groups = $conn->query("
    SELECT sg.*, c.name as course_name,
           (SELECT COUNT(*) FROM student_group_members WHERE group_id = sg.id) as member_count
    FROM student_groups sg
    JOIN courses c ON sg.course_id = c.id
    ORDER BY sg.created_at DESC
");

// Get courses and active students for dropdown
$courses = $conn->query("SELECT id, name FROM courses WHERE status = 'Active' ORDER BY name");
$students = $conn->query("SELECT id, student_code, full_name FROM students WHERE status = 'Active' ORDER BY full_name");

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-users text-purple"></i> Student Groups</h2>
        <p class="text-muted mb-0">Manage custom learning groups</p>
    </div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#createGroupModal">
        <i class="fas fa-plus"></i> Create Group
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
                    <th>Group Name</th>
                    <th>Course</th>
                    <th>Members</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($group = $groups->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($group['group_name']); ?></strong></td>
                    <td><small><?php echo htmlspecialchars($group['course_name']); ?></small></td>
                    <td><span class="badge bg-purple"><?php echo $group['member_count']; ?> students</span></td>
                    <td><?php echo date('d M Y', strtotime($group['created_at'])); ?></td>
                    <td>
                        <a href="group_details.php?id=<?php echo $group['id']; ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Manage
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_group">
                    
                    <div class="mb-3">
                        <label class="form-label">Group Name *</label>
                        <input type="text" class="form-control" name="group_name" placeholder="e.g., Advanced Batch 2024" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select class="form-select" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php 
                            $courses->data_seek(0);
                            while ($course = $courses->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add Students</label>
                        <select class="form-select" name="student_ids[]" multiple size="10">
                            <?php 
                            $students->data_seek(0);
                            while ($student = $students->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo $student['student_code']; ?> - <?php echo htmlspecialchars($student['full_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Prevent duplicate form submissions
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';
            }
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>