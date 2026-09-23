<?php
include 'includes/header.php';

// Get all HOLD students with details
$hold_students = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        s.photo,
        s.hold_start_date,
        s.hold_reason,
        s.total_hold_days,
        c.name as course_name,
        cat.name as category_name,
        s.batch,
        DATEDIFF(CURDATE(), s.hold_start_date) as current_hold_days
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    WHERE s.status = 'Hold'
    ORDER BY s.hold_start_date DESC
");

$total_hold = $hold_students->num_rows;
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-pause-circle text-warning"></i> Hold Students</h2>
        <p class="text-muted mb-0">Students currently on hold (paused enrollment)</p>
    </div>
    <a href="students.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Students
    </a>
</div>

<!-- Stats Card -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Hold Students</p>
                        <h3 class="mb-0 text-warning"><?php echo $total_hold; ?></h3>
                        <small class="text-muted">Enrollment paused</small>
                    </div>
                    <div class="card-icon icon-warning">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($total_hold > 0): ?>
<div class="table-card">
    <div class="mb-3">
        <input type="text" class="form-control" id="searchStudent" placeholder="Search by name, code, phone...">
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover" id="holdStudentsTable">
            <thead class="table-warning">
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Hold Date</th>
                    <th>Days on Hold</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sno = 1;
                while ($student = $hold_students->fetch_assoc()): 
                    $has_photo = !empty($student['photo']) && file_exists($student['photo']);
                ?>
                <tr data-student-code="<?php echo htmlspecialchars($student['student_code']); ?>">
                    <td><?php echo $sno++; ?></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($has_photo): ?>
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" 
                                     class="rounded-circle me-2" 
                                     style="width: 35px; height: 35px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                     style="width: 35px; height: 35px; font-size: 14px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                <!--<br><small class="text-muted"><?php // echo $student['student_code']; ?></small>-->
                            </div>
                        </div>
                    </td>
                    <td>
                        <!--<small><?php // echo htmlspecialchars($student['course_name']); ?></small>-->
                        <small class="text-muted"><?php echo htmlspecialchars($student['category_name']); ?></small>
                    </td>
                    <td>
                        <span class="badge <?php echo $student['batch'] === 'Morning' ? 'bg-warning' : 'bg-info'; ?>">
                            <?php echo $student['batch']; ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($student['hold_start_date'])); ?></td>
                    <td>
                        <span class="badge bg-warning fs-6">
                            <?php echo $student['current_hold_days']; ?> days
                        </span>
                    </td>
                    <td>
                        <?php if ($student['hold_reason']): ?>
                            <small><?php echo htmlspecialchars($student['hold_reason']); ?></small>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <!--<a href="student_details.php?id=<?php // echo $student['id']; ?>" -->
                            <!--   class="btn btn-primary" title="View Details">-->
                            <!--    <i class="fas fa-eye"></i>-->
                            <!--</a>-->
                            <button class="btn btn-success" 
                                    onclick="resumeStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')"
                                    title="Resume Student">
                                <i class="fas fa-play"></i> Resume
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> No students currently on hold.
</div>
<?php endif; ?>

<script>
document.getElementById('searchStudent')?.addEventListener('keyup', function() {
    const filter = this.value.toUpperCase();
    const rows = document.querySelectorAll('#holdStudentsTable tbody tr');
    
    rows.forEach(row => {
        let found = false;
        const studentCode = row.getAttribute('data-student-code');
        if (studentCode && studentCode.toUpperCase().indexOf(filter) > -1) {
            found = true;
        }
        
        if (!found) {
            const cells = row.getElementsByTagName('td');
            for (let j = 0; j < cells.length; j++) {
                const cellText = cells[j].textContent || cells[j].innerText;
                if (cellText.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        row.style.display = found ? '' : 'none';
    });
});

function resumeStudent(studentId, studentName) {
    if (confirm(`Resume enrollment for ${studentName}?\n\nThis will:\n• Reactivate the student\n• Resume attendance tracking\n• Resume ranking points\n• Continue course duration from where it was paused`)) {
        window.location.href = `student_hold_actions.php?action=resume&student_id=${studentId}`;
    }
}
</script>

<?php include 'includes/footer.php'; ?>