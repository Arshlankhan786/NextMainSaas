<?php
include 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $status = sanitize($_POST['status']);
            
            $stmt = $conn->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $description, $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Category added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add category.";
            }
            $stmt->close();
            
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $status = sanitize($_POST['status']);
            
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $description, $status, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Category updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update category.";
            }
            $stmt->close();
        }
        
        header('Location: categories.php');
        exit();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category has courses
    $check = $conn->query("SELECT COUNT(*) as count FROM courses WHERE category_id = $id");
    $result = $check->fetch_assoc();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "Cannot delete category. It has associated courses.";
    } else {
        $conn->query("DELETE FROM categories WHERE id = $id");
        $_SESSION['success'] = "Category deleted successfully!";
    }
    
    header('Location: categories.php');
    exit();
}

// Get all categories
$categories = $conn->query("SELECT c.*, 
                           (SELECT COUNT(*) FROM courses WHERE category_id = c.id) as course_count 
                           FROM categories c 
                           ORDER BY c.created_at DESC");
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-folder text-purple"></i> Course Categories</h2>
        <p class="text-muted mb-0">Manage course categories</p>
    </div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fas fa-plus"></i> Add Category
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
        <table class="table table-hover" id="categoriesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Courses</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($category = $categories->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $category['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                    <td>
                        <span class="badge bg-info"><?php echo $category['course_count']; ?> courses</span>
                    </td>
                    <td>
                        <span class="badge status-<?php echo strtolower($category['status']); ?>">
                            <?php echo $category['status']; ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($category['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?php echo $category['id']; ?>" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirm('Are you sure you want to delete this category?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description;
    document.getElementById('edit_status').value = category.status;
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>