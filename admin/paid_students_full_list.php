<?php
include 'includes/header.php';

// ─────────────────────────────────────────────
// LOGIC PRIORITY:
//  1. Custom dates supplied via GET  → use them
//  2. ?range= quick-filter parameter → derive dates
//  3. Default                        → current month
// ─────────────────────────────────────────────

$active_range = '';   // used to highlight the active quick-filter button

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    // ── Priority 1: Custom date range ──────────
    $start_date = $_GET['start_date'];
    $end_date   = $_GET['end_date'];

} elseif (!empty($_GET['range'])) {
    // ── Priority 2: Quick range filter ─────────
    $active_range = $_GET['range'];

    switch ($active_range) {
        case 'this_month':
            $start_date = date('Y-m-01');
            $end_date   = date('Y-m-d');
            break;

        case 'last_month':
            $start_date = date('Y-m-01', strtotime('first day of last month'));
            $end_date   = date('Y-m-t', strtotime('last month'));
            break;

        case 'last_3_months':
            $start_date = date('Y-m-01', strtotime('-2 months'));
            $end_date   = date('Y-m-d');
            break;

        case 'this_year':
            $start_date = date('Y-01-01');
            $end_date   = date('Y-m-d');
            break;

        case 'all':
            $start_date = '2000-01-01';
            $end_date   = date('Y-m-d');
            break;

        default:
            $start_date = date('Y-m-01');
            $end_date   = date('Y-m-d');
            $active_range = '';
    }

} else {
    // ── Priority 3: Default (current month) ────
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-d');
}

// Sanitise dates – prevent SQL injection for bare string interpolation below
$start_date = preg_replace('/[^0-9\-]/', '', $start_date);
$end_date   = preg_replace('/[^0-9\-]/', '', $end_date);

// ─────────────────────────────────────────────
// EXISTING QUERY — unchanged
// ─────────────────────────────────────────────
$paidStudents = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        MIN(p.payment_date) as first_payment_date,
        MAX(p.payment_date) as last_payment_date,
        COUNT(p.id) as payment_count,
        c.name as course_name
    FROM students s
    JOIN payments p ON s.id = p.student_id 
        AND p.payment_date BETWEEN '$start_date' AND '$end_date'
    JOIN courses c ON s.course_id = c.id
    WHERE s.status = 'Active'
    GROUP BY s.id
    ORDER BY total_paid DESC
");

$total_count      = $paidStudents->num_rows;
$total_collection = 0;

// Helper: build a URL that preserves only start_date / end_date or range
function rangeUrl($range) {
    return '?' . http_build_query(['range' => $range]);
}
?>

<!-- ══════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════ -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-check-circle text-success"></i> Paid Students - Full List</h2>
        <p class="text-muted mb-0">
            Students who paid between
            <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> &mdash;
            <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong>
        </p>
    </div>
    <a href="index.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
       class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- ══════════════════════════════════════════
     FILTER SECTION
══════════════════════════════════════════ -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">

        <!-- ── 1. Quick Range Buttons ───────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold text-muted small text-uppercase letter-spacing-1">
                <i class="fas fa-bolt text-warning me-1"></i> Quick Filters
            </label>
            <div class="d-flex flex-wrap gap-2">

                <a href="<?php echo rangeUrl('this_month'); ?>"
                   class="btn btn-sm <?php echo $active_range === 'this_month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-day me-1"></i> This Month
                </a>

                <a href="<?php echo rangeUrl('last_month'); ?>"
                   class="btn btn-sm <?php echo $active_range === 'last_month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-minus me-1"></i> Last Month
                </a>

                <a href="<?php echo rangeUrl('last_3_months'); ?>"
                   class="btn btn-sm <?php echo $active_range === 'last_3_months' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-week me-1"></i> Last 3 Months
                </a>

                <a href="<?php echo rangeUrl('this_year'); ?>"
                   class="btn btn-sm <?php echo $active_range === 'this_year' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-calendar-alt me-1"></i> This Year
                </a>

                <a href="<?php echo rangeUrl('all'); ?>"
                   class="btn btn-sm <?php echo $active_range === 'all' ? 'btn-dark' : 'btn-outline-dark'; ?>">
                    <i class="fas fa-infinity me-1"></i> All Time
                </a>

            </div>
        </div>

        <hr class="my-3">

        <!-- ── 2. Custom Date Range Form ─────────── -->
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12">
                <label class="form-label fw-semibold text-muted small text-uppercase">
                    <i class="fas fa-calendar-range me-1"></i> Custom Date Range
                </label>
            </div>

            <div class="col-md-4 col-sm-6">
                <label for="start_date" class="form-label small mb-1">Start Date</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                    <input type="date"
                           class="form-control"
                           id="start_date"
                           name="start_date"
                           value="<?php echo htmlspecialchars($start_date); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="col-md-4 col-sm-6">
                <label for="end_date" class="form-label small mb-1">End Date</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-times"></i></span>
                    <input type="date"
                           class="form-control"
                           id="end_date"
                           name="end_date"
                           value="<?php echo htmlspecialchars($end_date); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="col-md-4 col-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Apply Filter
                    </button>
                    <a href="?" class="btn btn-outline-secondary" title="Reset to default">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Inline validation feedback -->
        <div id="dateError" class="alert alert-warning mt-3 py-2 d-none" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Start date cannot be later than end date.
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════
     STATISTICS CARDS — unchanged
══════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Students Paid</p>
                        <h3 class="mb-0 text-success"><?php echo $total_count; ?></h3>
                        <small class="text-muted">During selected period</small>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-users"></i>
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
                        <p class="text-muted mb-1">Total Collection</p>
                        <h3 class="mb-0 text-purple">
                            <?php
                            $paidStudents->data_seek(0);
                            while ($s = $paidStudents->fetch_assoc()) {
                                $total_collection += $s['total_paid'];
                            }
                            echo '₹' . number_format($total_collection, 2);
                            ?>
                        </h3>
                        <small class="text-muted">From <?php echo $total_count; ?> students</small>
                    </div>
                    <div class="card-icon icon-purple">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     STUDENTS TABLE — unchanged structure
══════════════════════════════════════════ -->
<div class="table-card">

    <!-- ── 3. Search Input ──────────────────── -->
    <div class="mb-3">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text"
                   class="form-control"
                   id="searchStudent"
                   placeholder="Search by name, code, or phone...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped" id="paidStudentsTable">
            <thead class="table-success">
                <tr>
                    <th>#</th>
                    <th>Student Code</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Course</th>
                    <th>Amount Paid</th>
                    <th>Payment Period</th>
                    <th>Payments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $paidStudents->data_seek(0);
                $sno = 1;
                while ($student = $paidStudents->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['phone']); ?></td>
                    <td><small><?php echo htmlspecialchars($student['course_name']); ?></small></td>
                    <td>
                        <span class="badge bg-success fs-6">
                            ₹<?php echo number_format($student['total_paid'], 2); ?>
                        </span>
                    </td>
                    <td>
                        <small>
                            <i class="fas fa-calendar"></i>
                            <?php if ($student['first_payment_date'] === $student['last_payment_date']): ?>
                                <?php echo date('d M Y', strtotime($student['first_payment_date'])); ?>
                            <?php else: ?>
                                <?php echo date('d M', strtotime($student['first_payment_date'])); ?> &ndash;
                                <?php echo date('d M Y', strtotime($student['last_payment_date'])); ?>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-info">
                            <?php echo $student['payment_count']; ?> payment(s)
                        </span>
                    </td>
                    <td>
                        <a href="student_details.php?id=<?php echo $student['id']; ?>"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if ($total_count === 0): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fas fa-search fa-2x mb-2 d-block"></i>
                        No students found for the selected date range.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════ -->
<script>
// ── Live search (existing, unchanged) ──────
document.getElementById('searchStudent').addEventListener('keyup', function () {
    const filter = this.value.toUpperCase();
    const rows   = document.querySelectorAll('#paidStudentsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent || row.innerText;
        row.style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    });
});

// ── Date range validation ───────────────────
(function () {
    const form      = document.querySelector('form[method="GET"]');
    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');
    const errorBox   = document.getElementById('dateError');

    function validate() {
        const start = startInput.value;
        const end   = endInput.value;

        if (start && end && start > end) {
            errorBox.classList.remove('d-none');
            return false;
        }
        errorBox.classList.add('d-none');
        return true;
    }

    startInput.addEventListener('change', validate);
    endInput.addEventListener('change', validate);

    form.addEventListener('submit', function (e) {
        if (!validate()) {
            e.preventDefault();
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>