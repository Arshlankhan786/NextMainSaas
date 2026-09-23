<?php
session_start();
include './config/database.php'; // Required resources only

// ============================================
// PAST STUDENTS MODULE - SIMPLE VERSION
// Direct delete without modal
// ============================================


// Handle permanent delete
if (isset($_GET['delete']) && isset($_GET['type'])) {
    $id = (int)$_GET['delete'];
    $delete_type = $_GET['type'];

    if ($delete_type === 'hard') {
        $conn->query("DELETE FROM payments WHERE student_id = $id");
        $conn->query("DELETE FROM students WHERE id = $id");
        $_SESSION['success'] = "Student and all payment records permanently deleted!";
    } else {
        $conn->query("UPDATE students SET status = 'Deleted' WHERE id = $id");
        $_SESSION['success'] = "Student marked as deleted (payments preserved)!";
    }

    header('Location: past_students.php');
    exit();
}

// Restore student
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $conn->query("UPDATE students SET status = 'Active', completion_date = NULL WHERE id = $id");
    $_SESSION['success'] = "Student restored to active list!";
    header('Location: past_students.php');
    exit();
}

// Get past students
$pastStudents = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        s.email,
        s.enrollment_date,
        s.completion_date,
        s.duration_months,
        s.total_fees,
        s.status,
        c.name as course_name,
        cat.name as category_name,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    JOIN categories cat ON s.category_id = cat.id 
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status IN ('Completed', 'Dropped', 'Deleted')
    GROUP BY s.id
    ORDER BY 
        CASE 
            WHEN s.completion_date IS NOT NULL THEN s.completion_date
            ELSE s.updated_at
        END DESC
");

// Statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Completed'");
$stats['completed'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Dropped'");
$stats['dropped'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Deleted'");
$stats['deleted'] = $result->fetch_assoc()['count'];


include 'includes/header.php';

?>

<div class="page-header">
    <h2><i class="fas fa-history text-purple"></i> Past Students</h2>
    <p class="text-muted mb-0">Students who have completed, dropped, or been removed</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Completed</p>
                        <h3 class="mb-0 text-success"><?php echo $stats['completed']; ?></h3>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Dropped</p>
                        <h3 class="mb-0 text-warning"><?php echo $stats['dropped']; ?></h3>
                    </div>
                    <div class="card-icon icon-warning">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Deleted</p>
                        <h3 class="mb-0 text-danger"><?php echo $stats['deleted']; ?></h3>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-trash"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
   <div class="mb-3">
        <input type="text" class="form-control" id="searchPastStudent" placeholder="Search students by name, code, phone...">
    </div>
    
    
    <div class="table-responsive">
        <table class="table table-hover" id="pastStudentsTable">
            <thead>
                <tr>
                    <!-- <th>Code</th> -->
                    <th>Name</th>
                    <th>Course</th>
                    <th>Total Fees</th>
                    <th>Paid</th>
                    <th>Pending</th>
                    <th>Status</th>
                    <th>Completion Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($student = $pastStudents->fetch_assoc()): 
                    $status_class = 'secondary';
                    if ($student['status'] === 'Completed') $status_class = 'success';
                    if ($student['status'] === 'Dropped') $status_class = 'warning';
                    if ($student['status'] === 'Deleted') $status_class = 'danger';
                ?>
                <tr onclick="window.location.href='past_student_details.php?id=<?php echo $student['id']; ?>'">
                    <!-- <td><strong><?php // echo htmlspecialchars($student['student_code']); ?></strong></td> -->
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><small><?php echo htmlspecialchars($student['course_name']); ?></small></td>
                    <td>₹<?php echo number_format($student['total_fees'], 2); ?></td>
                    <td><span class="badge bg-success">₹<?php echo number_format($student['total_paid'], 2); ?></span></td>
                    <td>
                        <?php if ($student['pending_fees'] > 0): ?>
                            <span class="badge bg-danger">₹<?php echo number_format($student['pending_fees'], 2); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Fully Paid</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $student['status']; ?></span></td>
                    <td>
                        <?php if ($student['completion_date']): ?>
                            <?php echo date('d M Y', strtotime($student['completion_date'])); ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- <a href="past_student_details.php?id=<?php // echo $student['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a> -->
                        <?php if ($student): ?>
                        <a onclick="event.stopPropagation();" href="?restore=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Restore to Active" onclick="return confirm('Restore this student to active list?')">
                            <i class="fas fa-undo"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Delete dropdown -->
                        <div class="btn-group">
                            <button onclick="event.stopPropagation();" type="button" class="btn btn-sm btn-danger dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-trash"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="?delete=<?php echo $student['id']; ?>&type=soft" 
                                       onclick="return confirm('Soft delete this student? (Keeps payment records)')">
                                        <i class="fas fa-archive"></i> Soft Delete
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="?delete=<?php echo $student['id']; ?>&type=hard" 
                                       onclick="return confirm('PERMANENTLY delete this student AND all payment records? This CANNOT be undone!')">
                                        <i class="fas fa-exclamation-triangle"></i> Permanent Delete
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ============================
// SEARCH FUNCTIONALITY - FIXED
// Searches by Student Code (hidden) + visible fields
// ============================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPastStudent');
    const table = document.getElementById('pastStudentsTablex');
    
    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let found = false;
                
                // Check student code (hidden in data attribute)
                const studentCode = rows[i].getAttribute('data-student-code');
                if (studentCode && studentCode.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                }
                
                // If not found in code, search in visible cells
                if (!found) {
                    const cells = rows[i].getElementsByTagName('td');
                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent || cells[j].innerText;
                        if (cellText.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
});

</script>

<?php include 'includes/footer.php'; ?>



















