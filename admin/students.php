<?php
// ============================================
// HANDLE FORM SUBMISSION (NO OUTPUT BEFORE THIS POINT)
// ============================================

session_start();
require_once './config/database.php'; // Only include ONCE

// Do NOT reconnect in loops or functions  // include required db first, not header!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

   
    if ($_POST['action'] === 'add') {
        $student_code = 'STU' . date('Ymd') . rand(1000, 9999);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $birthdate = !empty($_POST['birthdate']) ? sanitize($_POST['birthdate']) : null;
        $category_id = (int)$_POST['category_id'];
        $course_id = (int)$_POST['course_id'];
        $duration_months = (int)$_POST['duration_months'];
        $batch = sanitize($_POST['batch']); // NEW: Batch field
        $total_fees = (float)$_POST['total_fees'];
        $enrollment_date = sanitize($_POST['enrollment_date']);

        // UPDATED SQL: Include batch + birthdate columns
        $stmt = $conn->prepare("INSERT INTO students (student_code, full_name, email, phone, address, birthdate, category_id, course_id, duration_months, batch, total_fees, enrollment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("ssssssiiisds", $student_code, $full_name, $email, $phone, $address, $birthdate, $category_id, $course_id, $duration_months, $batch, $total_fees, $enrollment_date);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Student enrolled successfully! Student Code: $student_code";
        } else {
            $_SESSION['error'] = "Failed to enroll student.";
        }
        $stmt->close();
        header('Location: students.php');
        exit();
    }

    if ($_POST['action'] === 'mark_completed') {
        $id = (int)$_POST['id'];
        $completion_date = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE students SET status = 'Completed', completion_date = ? WHERE id = ?");
        $stmt->bind_param("si", $completion_date, $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Student marked as completed!";
        } else {
            $_SESSION['error'] = "Failed to update status.";
        }
        $stmt->close();
        header('Location: students.php');
        exit();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("UPDATE students SET status = 'Deleted' WHERE id = $id");
    $_SESSION['success'] = "Student deleted successfully!";
    header('Location: students.php');
    exit();
}

// ============================================
// DATA FETCH after headers clear
// ============================================
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
        s.status,
        c.name as course_name,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees,
        EXISTS(
            SELECT 1 FROM payments p2 
            WHERE p2.student_id = s.id 
            AND YEAR(p2.payment_date) = YEAR(CURDATE())
            AND MONTH(p2.payment_date) = MONTH(CURDATE())
        ) as paid_this_month
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status IN ('Active', 'Hold')
    GROUP BY s.id
    ORDER BY s.created_at DESC
");

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");
$courses = $conn->query("SELECT * FROM courses WHERE status = 'Active' ORDER BY name");

// ============================================
// NOW include header (safe)
// ============================================
include 'includes/header.php';

?>

<style>
/* ── Students Page — dashboard design system ── */
.st-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px }
.st-header h2 { font-size:18px; font-weight:800; color:var(--text); margin:0; display:flex; align-items:center; gap:8px }
.st-header h2 i { color:var(--indigo-600); font-size:16px }
.st-header p { font-size:12px; color:var(--muted); margin:2px 0 0 }
.st-btn-add {
    background:linear-gradient(135deg,var(--indigo-600),var(--accent)); color:#fff;
    border:none; border-radius:10px; padding:8px 18px; font-size:12px; font-weight:700;
    cursor:pointer; transition:all .18s; display:inline-flex; align-items:center; gap:6px;
    font-family:inherit; text-decoration:none
}
.st-btn-add:hover { box-shadow:0 4px 16px rgba(79,70,229,.3); transform:translateY(-1px); color:#fff }

/* Table card */
.st-table-card {
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden
}
.st-search-wrap { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px }
.st-search-wrap i { color:var(--muted); font-size:12px }
.st-search-wrap input { border:none; outline:none; font-size:12px; font-family:inherit; flex:1; color:var(--text); background:transparent }

.st-table { width:100%; border-collapse:collapse; font-size:12px }
.st-table th {
    padding:10px 14px; text-align:left; font-size:10px; font-weight:700;
    color:var(--muted); text-transform:uppercase; letter-spacing:.4px;
    background:#fafbfc; border-bottom:1px solid var(--border)
}
.st-table td { padding:10px 14px; border-bottom:1px solid rgba(0,0,0,.04); vertical-align:middle }
.st-table tbody tr { cursor:pointer; transition:background .12s }
.st-table tbody tr:hover td { background:rgba(99,102,241,.03) }
.st-table tbody tr:last-child td { border-bottom:none }
.st-table tbody tr.st-row-overdue td { background:rgba(220,38,38,.03) }
.st-table tbody tr.st-row-overdue:hover td { background:rgba(220,38,38,.06) }

.st-name-cell { font-weight:700; color:var(--text) }
.st-badge-overdue { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:99px; font-size:9px; font-weight:700; background:#fef2f2; color:#dc2626; border:1px solid rgba(220,38,38,.15); margin-top:3px }
.st-badge-hold { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:99px; font-size:9px; font-weight:700; background:#fffbeb; color:#d97706; border:1px solid rgba(217,119,6,.15); margin-top:3px }

.st-fee-paid { color:#059669; font-weight:700 }
.st-fee-pending { color:#d97706; font-weight:700 }

.st-actions { display:flex; gap:4px }
.st-act-btn {
    width:28px; height:28px; border-radius:8px; border:1px solid var(--border);
    background:#fff; display:flex; align-items:center; justify-content:center;
    font-size:11px; cursor:pointer; transition:all .15s; color:var(--muted); text-decoration:none
}
.st-act-btn:hover { background:var(--indigo-100); color:var(--indigo-700); border-color:var(--indigo-400) }
.st-act-btn.success:hover { background:#f0fdf4; color:#059669; border-color:rgba(5,150,105,.3) }
.st-act-btn.danger:hover { background:#fef2f2; color:#dc2626; border-color:rgba(220,38,38,.3) }

.st-empty { text-align:center; padding:40px 20px; color:var(--muted) }
.st-empty i { font-size:28px; opacity:.25; display:block; margin-bottom:8px }

/* Modal */
.st-modal .modal-content { border-radius:14px; border:1px solid var(--border); font-family:inherit }
.st-modal .modal-header { border-bottom:1px solid var(--border); padding:14px 20px; background:linear-gradient(135deg,var(--indigo-600),var(--accent)); border-radius:14px 14px 0 0 }
.st-modal .modal-title { font-size:14px; font-weight:800; color:#fff; display:flex; align-items:center; gap:8px }
.st-modal .modal-body { padding:18px 20px }
.st-modal .modal-footer { border-top:1px solid var(--border); padding:12px 20px }
.st-modal .form-label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px }
.st-modal .form-control, .st-modal .form-select { border-radius:8px; border:1px solid var(--border); font-size:12px; font-family:inherit; padding:8px 12px }
.st-modal .form-control:focus, .st-modal .form-select:focus { border-color:var(--indigo-400); box-shadow:0 0 0 3px rgba(99,102,241,.1) }
.st-section-title { font-size:11px; font-weight:800; color:var(--indigo-600); text-transform:uppercase; letter-spacing:.5px; margin:14px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border) }

@media (max-width:768px) {
    .st-table th:nth-child(2), .st-table td:nth-child(2),
    .st-table th:nth-child(4), .st-table td:nth-child(4) { display:none }
}
</style>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(5,150,105,.2)">
    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(220,38,38,.2)">
    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="st-header">
    <div>
        <h2><i class="fas fa-user-graduate"></i> Active Students</h2>
        <p>Manage currently enrolled students</p>
    </div>
    <button class="st-btn-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="fas fa-plus"></i> Enroll Student
    </button>
</div>

<!-- Table -->
<div class="st-table-card">
    <div class="st-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="searchStudent" placeholder="Search students by name, code, phone...">
    </div>
    <div style="overflow-x:auto">
        <table class="st-table" id="studentsTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Phone</th>
                    <th>Course</th>
                    <th>Duration</th>
                    <th>Total Fees</th>
                    <th>Paid</th>
                    <th>Pending</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($student = $students->fetch_assoc()): 
                    $payment_status = 'pending';
                    if ($student['pending_fees'] <= 0) $payment_status = 'paid';
                    elseif ($student['total_paid'] > 0) $payment_status = 'partial';
                    $is_overdue = (!$student['paid_this_month'] && $student['pending_fees'] > 0);
                ?>
                <tr onclick="window.location.href='student_details.php?id=<?= $student['id'] ?>'" class="<?= $is_overdue ? 'st-row-overdue' : '' ?>" data-student-code="<?= htmlspecialchars($student['student_code']) ?>">
                    <td>
                        <div class="st-name-cell">
                            <?= htmlspecialchars($student['full_name']) ?>
                            <?php if ($is_overdue): ?><br><span class="st-badge-overdue"><i class="fas fa-exclamation-triangle"></i> OVERDUE</span><?php endif; ?>
                            <?php if ($student['status'] === 'Hold'): ?><br><span class="st-badge-hold"><i class="fas fa-pause-circle"></i> HOLD</span><?php endif; ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($student['phone']) ?></td>
                    <td style="font-size:11px"><?= htmlspecialchars($student['course_name']) ?></td>
                    <td><?= $student['duration_months'] ?> M</td>
                    <td>₹<?= number_format($student['total_fees'], 2) ?></td>
                    <td><span class="st-fee-paid">₹<?= number_format($student['total_paid'], 2) ?></span></td>
                    <td><span class="st-fee-pending">₹<?= number_format($student['pending_fees'], 2) ?></span></td>
                    <td>
                        <div class="st-actions" onclick="event.stopPropagation()">
                            <?php if ($student['pending_fees'] <= 0 && $student['status'] !== 'Hold'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this student as completed?');">
                                <input type="hidden" name="action" value="mark_completed">
                                <input type="hidden" name="id" value="<?= $student['id'] ?>">
                                <button type="submit" class="st-act-btn success" title="Mark Completed"><i class="fas fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            <a href="?delete=<?= $student['id'] ?>" class="st-act-btn danger" title="Delete" onclick="return confirm('Delete this student? This will move them to Past Students.')"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($students->num_rows === 0): ?>
    <div class="st-empty"><i class="fas fa-user-graduate"></i><p>No active students found</p></div>
    <?php endif; ?>
</div>

<!-- Add Student Modal -->
<div class="modal fade st-modal" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Enroll New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="studentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="st-section-title">Personal Information</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="birthdate">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Enrollment Date *</label>
                            <input type="date" class="form-control" name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="st-section-title">Course & Fees</div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select class="form-select" name="course_id" id="course_select" required>
                            <option value="">Select Course</option>
                            <?php $courses->data_seek(0); while ($course = $courses->fetch_assoc()): ?>
                            <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted" id="course_help">Choose a course to see available durations</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duration *</label>
                            <select class="form-select" name="duration_months" id="duration_select" required>
                                <option value="">Select Duration</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Batch *</label>
                            <select class="form-select" name="batch" required>
                                <option value="Morning">Morning</option>
                                <option value="Evening">Evening</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Fees *</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:8px 0 0 8px;font-size:12px">₹</span>
                                <input type="number" class="form-control" name="total_fees" id="total_fees" step="0.01" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:12px;font-weight:600">Cancel</button>
                    <button type="submit" class="st-btn-add">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================
// SEARCH FUNCTIONALITY - FIXED
// Searches by Student Code (hidden) + visible fields
// ============================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchStudent');
    const table = document.getElementById('studentsTable');
    
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

// ============================
// MODAL EVENT - COURSE SELECTION
// ============================
document.getElementById('addStudentModal').addEventListener('shown.bs.modal', function () {
    const courseSelect = document.getElementById('course_select');
    const durationSelect = document.getElementById('duration_select');
    const feesInput = document.getElementById('total_fees');
    const courseHelp = document.getElementById('course_help');

    // Reset on modal open
    durationSelect.innerHTML = '<option value="">Select Duration</option>';
    feesInput.value = '';

    // ============================
    // COURSE CHANGE
    // ============================
    courseSelect.onchange = function () {
        const courseId = this.value;

        // Reset fields
        durationSelect.innerHTML = '<option value="">Select Duration</option>';
        feesInput.value = '';

        if (!courseId) return;

        fetch(`ajax/get_course_fees.php?course_id=${courseId}`)
            .then(res => res.json())
            .then(data => {
                // If preset fees exist
                if (data.success && Array.isArray(data.fees) && data.fees.length > 0) {
                    data.fees.forEach(fee => {
                        const opt = document.createElement('option');
                        opt.value = fee.duration_months;
                        opt.textContent = `${fee.duration_months} Months`;
                        opt.dataset.fee = fee.fee_amount;
                        durationSelect.appendChild(opt);
                    });

                    courseHelp.textContent = 'Durations loaded successfully';
                    courseHelp.className = 'text-success';
                } else {
                    // No preset fees → default durations
                    [3, 6, 9, 12, 18, 24].forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m;
                        opt.textContent = `${m} Months`;
                        durationSelect.appendChild(opt);
                    });

                    courseHelp.textContent = 'No preset fees. Please enter custom amount.';
                    courseHelp.className = 'text-warning';
                }
            })
            .catch(err => {
                console.error('AJAX error:', err);

                // Fallback to default durations
                [3, 6, 9, 12, 18, 24].forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = `${m} Months`;
                    durationSelect.appendChild(opt);
                });

                courseHelp.textContent = 'Error loading durations';
                courseHelp.className = 'text-danger';
            });
    };

    // ============================
    // DURATION CHANGE
    // ============================
    durationSelect.onchange = function () {
        const selected = this.options[this.selectedIndex];
        if (!selected) return;

        const fee = selected.dataset.fee;

        if (fee) {
            feesInput.value = fee;
            courseHelp.textContent = 'Fee auto-filled. You can adjust it.';
            courseHelp.className = 'text-success';
        } else {
            feesInput.value = '';
            feesInput.focus();
            courseHelp.textContent = 'Please enter total fees manually.';
            courseHelp.className = 'text-info';
        }
    };
});
</script>

<?php include 'includes/footer.php'; ?>