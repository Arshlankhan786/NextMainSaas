<?php
include 'includes/header.php';

// ============================================
// STUDENTS SORTED BY COURSE TIME REMAINING
// Students with least time remaining appear first
// ============================================

// Calculate course expiry for all active students
// Replace the students query starting at line 13:
$students = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        s.email,
        s.enrollment_date,
        s.duration_months,
        s.total_fees,
        s.total_hold_days,
        s.status,
        c.name as course_name,
        cat.name as category_name,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees,
        -- Calculate days since enrollment (EXCLUDING hold days)
        (DATEDIFF(CURDATE(), s.enrollment_date) - COALESCE(s.total_hold_days, 0)) as days_elapsed,
        -- Calculate total course days
        (s.duration_months * 30) as total_course_days,
        -- Calculate expected end date (ADDING hold days)
        DATE_ADD(DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH), INTERVAL COALESCE(s.total_hold_days, 0) DAY) as expected_end_date,
        -- Calculate days remaining (ADJUSTED for hold)
        DATEDIFF(
            DATE_ADD(DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH), INTERVAL COALESCE(s.total_hold_days, 0) DAY),
            CURDATE()
        ) as days_remaining,
        -- Calculate completion percentage (EXCLUDING hold days)
        ROUND(
            ((DATEDIFF(CURDATE(), s.enrollment_date) - COALESCE(s.total_hold_days, 0)) / (s.duration_months * 30)) * 100, 
            2
        ) as completion_percentage
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    JOIN categories cat ON s.category_id = cat.id 
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status IN ('Active', 'Hold')
    GROUP BY s.id
    ORDER BY completion_percentage DESC, days_remaining ASC
");
// Statistics
$stats = [];

// Students over 90% complete (critical)
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM students s
    WHERE s.status = 'Active'
    AND (DATEDIFF(CURDATE(), s.enrollment_date) / (s.duration_months * 30)) * 100 >= 90
");
$stats['critical'] = $result->fetch_assoc()['count'];

// Students 75-90% complete (warning)
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM students s
    WHERE s.status = 'Active'
    AND (DATEDIFF(CURDATE(), s.enrollment_date) / (s.duration_months * 30)) * 100 >= 75
    AND (DATEDIFF(CURDATE(), s.enrollment_date) / (s.duration_months * 30)) * 100 < 90
");
$stats['warning'] = $result->fetch_assoc()['count'];

// Students already expired
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM students s
    WHERE s.status = 'Active'
    AND DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH) < CURDATE()
");
$stats['expired'] = $result->fetch_assoc()['count'];
?>

<div class="page-header">
    <h2><i class="fas fa-clock text-danger"></i> Course Expiry List</h2>
    <p class="text-muted mb-0">Students sorted by time remaining in their course</p>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Course Expired</p>
                        <h3 class="mb-0 text-danger"><?php echo $stats['expired']; ?></h3>
                        <small class="text-muted">Past end date</small>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-exclamation-triangle"></i>
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
                        <p class="text-muted mb-1">Critical (90%+)</p>
                        <h3 class="mb-0 text-warning"><?php echo $stats['critical']; ?></h3>
                        <small class="text-muted">Less than 10% time left</small>
                    </div>
                    <div class="card-icon icon-warning">
                        <i class="fas fa-hourglass-end"></i>
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
                        <p class="text-muted mb-1">Warning (75-90%)</p>
                        <h3 class="mb-0" style="color: #f59e0b;"><?php echo $stats['warning']; ?></h3>
                        <small class="text-muted">10-25% time left</small>
                    </div>
                    <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="alert alert-info mb-4">
    <h6><i class="fas fa-info-circle"></i> Color Legend:</h6>
    <div class="row">
        <div class="col-md-3">
            <span class="badge bg-danger">Expired</span> - Course already ended
        </div>
        <div class="col-md-3">
            <span class="badge" style="background: #dc2626;">90%+ Complete</span> - Critical
        </div>
        <div class="col-md-3">
            <span class="badge bg-warning">75-90% Complete</span> - Warning
        </div>
        <div class="col-md-3">
            <span class="badge bg-success">&lt;75% Complete</span> - Normal
        </div>
    </div>
</div>

<div class="table-card">
      <div class="mb-3">
        <input type="text" class="form-control" id="searchStudent" placeholder="Search students by name, code, phone...">
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover" id="expiryTable">
            <thead>
                <tr>
                    <!-- <th>Code</th> -->
                    <th>Name</th>
                    <th>Course</th>
                    <th>Duration</th>
                    <th>Enrolled</th>
                    <th>Expected End</th>
                    <th>Days Left</th>
                    <th>Progress</th>
                    <!--<th>Status</th>-->
                    <th>Pending Fees</th>
                    <!-- <th>Actions</th> -->
                </tr>
            </thead>
            <tbody>
                <?php while ($student = $students->fetch_assoc()): 
                    // Determine status color
                    $row_class = '';
                    $badge_class = 'bg-success';
                    $status_text = 'Normal';
                    
                    if ($student['days_remaining'] < 0) {
                        $row_class = 'table-danger';
                        $badge_class = 'bg-danger';
                        $status_text = 'EXPIRED';
                    } elseif ($student['completion_percentage'] >= 90) {
                        $row_class = 'table-warning';
                        $badge_class = 'bg-danger';
                        $status_text = 'CRITICAL';
                    } elseif ($student['completion_percentage'] >= 75) {
                        $badge_class = 'bg-warning';
                        $status_text = 'WARNING';
                    }
                    
                    // Progress bar color
                    $progress_color = 'success';
                    if ($student['completion_percentage'] >= 90) $progress_color = 'danger';
                    elseif ($student['completion_percentage'] >= 75) $progress_color = 'warning';
                    
                    // Days remaining text
                    $days_left_text = $student['days_remaining'];
                    if ($student['days_remaining'] < 0) {
                        $days_left_text = abs($student['days_remaining']) . ' days overdue';
                    } else {
                        $days_left_text .= ' days';
                    }
                ?>
                <tr  onclick="window.location.href='student_details.php?id=<?php echo $student['id']; ?>'" class="<?php echo $row_class; ?>">
                    <!-- <td ><strong><?php // echo htmlspecialchars($student['student_code']); ?></strong></td> -->
                    <td>
                        <?php echo htmlspecialchars($student['full_name']); ?>
                        <br>
                        <!-- <small class="text-muted"><?php // echo htmlspecialchars($student['phone']); ?></small> -->
                    </td>
                    <td>
                        <small><strong><?php echo htmlspecialchars($student['course_name']); ?></strong></small>
                        <br>
                        <!-- <small class="text-muted"><?php // echo htmlspecialchars($student['category_name']); ?></small> -->
                    </td>
                    <td><?php echo $student['duration_months']; ?> Mn</td>
                    <td><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></td>
                    <td>
                        <strong><?php echo date('d M Y', strtotime($student['expected_end_date'])); ?></strong>
                    </td>
                    <td>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo $days_left_text; ?>
                        </span>
                    </td>
                    <td>
                        <div class="progress text-white" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $progress_color; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo min(100, $student['completion_percentage']); ?>%"
                                 aria-valuenow="<?php echo $student['completion_percentage']; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo number_format($student['completion_percentage'], 1); ?>%
                            </div>
                        </div>
                      
                    </td>
                    <!--<td>-->
                    <!--    <span class="badge <?php // echo $badge_class; ?> fs-6">-->
                    <!--        <?php // echo $status_text; ?>-->
                    <!--    </span>-->
                    <!--</td>-->
                    <!-- <td>
                        <a href="student_details.php?id=<?php // echo $student['id']; ?>" 
                           class="btn btn-sm btn-info" 
                           title="View Details">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td> -->
                    <td>
                        <?php if ($student['pending_fees'] > 0): ?>
                            <span class="badge bg-danger fs-6">₹<?php echo number_format($student['pending_fees'], 2); ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Paid</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
    <?php if ($student['status'] === 'Hold'): ?>
        <span class="badge bg-warning mb-1">
            <i class="fas fa-pause-circle"></i> ON HOLD
        </span>
        <br>
    <?php endif; ?>
    
    <?php if ($student['total_hold_days'] > 0): ?>
        <small class="text-muted">
            (<?php echo $student['total_hold_days']; ?> hold days excluded)
        </small>
    <?php endif; ?>
    
    <!-- Rest of existing code -->
</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Help Section -->
<div class="table-card mt-4">
    <h5 class="text-purple mb-3"><i class="fas fa-question-circle"></i> How This Works</h5>
    <div class="row">
        <div class="col-md-6">
            <h6>Calculation Method:</h6>
            <ul>
                <li><strong>Total Course Days</strong> = Duration (months) × 30</li>
                <li><strong>Days Elapsed</strong> = Current Date - Enrollment Date</li>
                <li><strong>Completion %</strong> = (Days Elapsed ÷ Total Days) × 100</li>
                <li><strong>Days Remaining</strong> = Expected End Date - Current Date</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h6>Status Categories:</h6>
            <ul>
                <li><span class="badge bg-danger">EXPIRED</span> - Course end date has passed</li>
                <li><span class="badge bg-danger">CRITICAL</span> - 90%+ complete (less than 10% time left)</li>
                <li><span class="badge bg-warning">WARNING</span> - 75-90% complete (10-25% time left)</li>
                <li><span class="badge bg-success">NORMAL</span> - Less than 75% complete</li>
            </ul>
        </div>
    </div>
    <div class="alert alert-warning mt-3">
        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Students at the top of the list need immediate attention. 
        Consider marking them as completed or extending their enrollment if needed.
    </div>
</div>

<script>
// ============================
// SEARCH FUNCTIONALITY - FIXED
// Searches by Student Code (hidden) + visible fields
// ============================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchStudent');
    const table = document.getElementById('expiryTable');
    
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

    
// Search functionality
// searchTable('searchStudent', 'expiryTable');
</script>

<?php include 'includes/footer.php'; ?>