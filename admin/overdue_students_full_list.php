<?php
include 'includes/header.php';

// Get ALL overdue students (no payment this month + has pending)
$overdueStudents = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        s.email,
        s.total_fees,
        s.enrollment_date,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees,
        c.name as course_name,
        cat.name as category_name,
        MAX(p.payment_date) as last_payment_date
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status = 'Active'
    GROUP BY s.id
    HAVING pending_fees > 0
    AND NOT EXISTS (
        SELECT 1 FROM payments p2
        WHERE p2.student_id = s.id
        AND YEAR(p2.payment_date) = YEAR(CURDATE())
        AND MONTH(p2.payment_date) = MONTH(CURDATE())
    )
    ORDER BY pending_fees DESC
");

$total_count = $overdueStudents->num_rows;
$total_pending = 0;
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-exclamation-triangle text-danger"></i> Overdue Students - Full List</h2>
        <p class="text-muted mb-0">Students with no payment in <?php echo date('F Y'); ?></p>
    </div>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Overdue Students</p>
                        <h3 class="mb-0 text-danger"><?php echo $total_count; ?></h3>
                        <small class="text-muted">No payment this month</small>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Pending Amount</p>
                        <h3 class="mb-0 text-danger">
                            <?php 
                            $overdueStudents->data_seek(0);
                            while ($s = $overdueStudents->fetch_assoc()) {
                                $total_pending += $s['pending_fees'];
                            }
                            echo '₹' . number_format($total_pending, 2);
                            ?>
                        </h3>
                        <small class="text-muted">Across all overdue students</small>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="table-card">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <input type="text" class="form-control w-50" id="searchStudent" placeholder="Search by name, code, or phone...">
        <button class="btn btn-success" onclick="sendReminderToAll()">
            <i class="fas fa-bell"></i> Send Reminder to All
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover table-striped" id="overdueStudentsTable">
            <thead class="table-danger">
                <tr>
                    <th>#</th>
                    <th>Student Code</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Course</th>
                    <th>Total Fees</th>
                    <th>Paid</th>
                    <th>Pending</th>
                    <th>Last Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $overdueStudents->data_seek(0);
                $sno = 1;
                while ($student = $overdueStudents->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                    <td><small><?php echo htmlspecialchars($student['course_name']); ?></small></td>
                    <td>₹<?php echo number_format($student['total_fees'], 2); ?></td>
                    <td><span class="badge bg-success">₹<?php echo number_format($student['total_paid'], 2); ?></span></td>
                    <td><span class="badge bg-danger fs-6">₹<?php echo number_format($student['pending_fees'], 2); ?></span></td>
                    <td>
                        <?php if ($student['last_payment_date']): ?>
                            <small><?php echo date('d M Y', strtotime($student['last_payment_date'])); ?></small>
                        <?php else: ?>
                            <span class="badge bg-secondary">No payments</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="add_payment.php?student_id=<?php echo $student['id']; ?>" class="btn btn-success" title="Add Payment">
                                <i class="fas fa-rupee-sign"></i>
                            </a>
                            <a href="send_notification.php?student_id=<?php echo $student['id']; ?>" class="btn btn-warning" title="Send Reminder">
                                <i class="fas fa-bell"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('searchStudent').addEventListener('keyup', function() {
    const filter = this.value.toUpperCase();
    const rows = document.querySelectorAll('#overdueStudentsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent || row.innerText;
        row.style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    });
});

function sendReminderToAll() {
    if (confirm('Send payment reminder notification to all <?php echo $total_count; ?> overdue students?')) {
        window.location.href = 'send_notification.php?student_id=all&type=overdue';
    }
}
</script>

<?php include 'includes/footer.php'; ?>