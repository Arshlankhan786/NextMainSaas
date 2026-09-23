<?php
require_once 'includes/header.php';

// ── Handle assignment submission ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_test'])) {
    $test_id = intval($_POST['test_id'] ?? 0);
    $student_ids = $_POST['student_ids'] ?? [];

    if ($test_id <= 0) {
        $_SESSION['error'] = 'Please select a valid test.';
    } elseif (empty($student_ids)) {
        $_SESSION['error'] = 'Please select at least one student.';
    } else {
        $assigned = 0;
        $skipped = 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO assigned_tests (test_id, student_id) VALUES (?, ?)");
        foreach ($student_ids as $sid) {
            $sid = intval($sid);
            if ($sid <= 0) continue;
            $stmt->bind_param("ii", $test_id, $sid);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $assigned++;
            else $skipped++;
        }
        $stmt->close();

        $msg = "Assigned to {$assigned} student(s).";
        if ($skipped > 0) $msg .= " {$skipped} already assigned (skipped).";
        $_SESSION['success'] = $msg;
    }
    header('Location: assign_test.php');
    exit();
}

// ── Fetch all tests ─────────────────────────────────────────────
$tests = [];
$res = $conn->query("
    SELECT t.id, t.title, COUNT(q.id) as qcount, t.created_at
    FROM tests t LEFT JOIN questions q ON q.test_id = t.id
    GROUP BY t.id ORDER BY t.created_at DESC
");
if ($res) while ($r = $res->fetch_assoc()) $tests[] = $r;

// ── Fetch all active students ───────────────────────────────────
$students = [];
$res = $conn->query("SELECT id, full_name, student_code FROM students WHERE status = 'Active' ORDER BY full_name ASC");
if ($res) while ($r = $res->fetch_assoc()) $students[] = $r;

// ── Selected test for viewing assignments ───────────────────────
$view_test_id = intval($_GET['view'] ?? ($tests[0]['id'] ?? 0));
$assignments = [];
if ($view_test_id > 0) {
    $astmt = $conn->prepare("
        SELECT at.*, s.full_name, s.student_code,
               tr.score_percentage, tr.correct_count, tr.total_questions
        FROM assigned_tests at
        JOIN students s ON s.id = at.student_id
        LEFT JOIN test_results tr ON tr.assigned_test_id = at.id
        WHERE at.test_id = ?
        ORDER BY at.status ASC, s.full_name ASC
    ");
    $astmt->bind_param("i", $view_test_id);
    $astmt->execute();
    $ares = $astmt->get_result();
    while ($r = $ares->fetch_assoc()) $assignments[] = $r;
    $astmt->close();
}
?>

<!-- Select2 CSS + JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
.at-header { display:flex; align-items:center; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.at-header h4 { margin:0; font-weight:700; font-size:20px; }
.at-card {
    background:var(--surface); border-radius:var(--radius);
    border:1px solid var(--border); box-shadow:var(--sh-sm);
    padding:24px; margin-bottom:24px;
}
.at-card h6 {
    font-size:13px; font-weight:700; text-transform:uppercase;
    letter-spacing:.8px; color:var(--muted); margin-bottom:16px;
}

/* select2 theme override */
.select2-container--default .select2-selection--multiple {
    border:1.5px solid #e5e7eb; border-radius:9px; min-height:42px;
    padding:4px 8px; font-family:inherit;
}
.select2-container--default .select2-selection--multiple:focus-within {
    border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.15);
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background:var(--indigo-100); border:1px solid rgba(99,102,241,.25);
    border-radius:6px; font-size:12px; font-weight:600; color:var(--indigo-700);
    padding:3px 8px;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color:var(--indigo-600); margin-right:4px;
}

/* Status badges */
.st-badge {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:99px;
    font-size:11px; font-weight:700; letter-spacing:.3px;
}
.st-badge.ns { background:rgba(107,114,128,.1); color:var(--muted); }
.st-badge.ip { background:rgba(14,165,233,.1); color:var(--sky); }
.st-badge.co { background:rgba(5,150,105,.1); color:var(--emerald); }

/* Score cell */
.score-val { font-weight:800; font-size:15px; }
.score-val.high { color:var(--emerald); }
.score-val.mid  { color:var(--amber); }
.score-val.low  { color:var(--crimson); }

/* Unassign button */
.btn-unassign {
    width:28px; height:28px; border-radius:7px; border:none;
    background:rgba(220,38,38,.08); color:var(--crimson);
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:var(--ease); font-size:12px;
}
.btn-unassign:hover { background:rgba(220,38,38,.15); }

/* Test tabs */
.test-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
.test-tab {
    padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600;
    border:1.5px solid var(--border); background:transparent;
    color:var(--muted); cursor:pointer; transition:var(--ease);
    text-decoration:none;
}
.test-tab:hover { border-color:var(--indigo-400); color:var(--indigo-600); }
.test-tab.active {
    background:var(--indigo-600); color:#fff;
    border-color:var(--indigo-600);
}
</style>

<!-- ═══ PAGE CONTENT ═══ -->
<div class="at-header">
    <h4><i class="fas fa-paper-plane text-purple me-2"></i>Assign Test</h4>
    <span class="badge bg-purple"><?= count($tests) ?> Tests</span>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?= $_SESSION['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-1"></i> <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT: Assign form -->
    <div class="col-lg-5">
        <div class="at-card">
            <h6><i class="fas fa-link me-1"></i> Assign Test to Students</h6>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:12.5px">Select Test</label>
                    <select class="form-select" name="test_id" required>
                        <option value="">— Choose a test —</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?> (<?= $t['qcount'] ?> Q)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:12.5px">Select Students</label>
                    <select class="form-select" name="student_ids[]" id="studentSelect" multiple required>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['student_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="assign_test" class="btn btn-purple px-4">
                        <i class="fas fa-paper-plane me-1"></i> Assign
                    </button>
                    <button type="button" class="btn btn-outline-purple" id="selectAllBtn">
                        <i class="fas fa-check-double me-1"></i> Select All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Assignments view -->
    <div class="col-lg-7">
        <div class="at-card">
            <h6><i class="fas fa-users me-1"></i> Test Assignments</h6>

            <?php if (!empty($tests)): ?>
            <div class="test-tabs">
                <?php foreach ($tests as $t): ?>
                    <a href="?view=<?= $t['id'] ?>"
                       class="test-tab <?= $t['id'] == $view_test_id ? 'active' : '' ?>">
                        <?= htmlspecialchars($t['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="table-responsive" id="assignmentsTable">
                <?php if (empty($assignments)): ?>
                    <p class="text-muted" style="font-size:13px"><i class="fas fa-info-circle me-1"></i>No students assigned to this test yet.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Completed</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $i => $a): ?>
                            <tr id="assignRow-<?= $a['id'] ?>">
                                <td class="fw-bold text-muted"><?= $i+1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($a['full_name']) ?></strong>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['student_code']) ?></div>
                                </td>
                                <td>
                                    <?php
                                        $sc = 'ns'; $sl = $a['status'];
                                        if ($sl === 'In Progress') $sc = 'ip';
                                        if ($sl === 'Completed')   $sc = 'co';
                                    ?>
                                    <span class="st-badge <?= $sc ?>"><?= $sl ?></span>
                                </td>
                                <td>
                                    <?php if ($a['score_percentage'] !== null && $sl === 'Completed'):
                                        $pct = floatval($a['score_percentage']);
                                        $cls = $pct >= 70 ? 'high' : ($pct >= 40 ? 'mid' : 'low');
                                    ?>
                                        <span class="score-val <?= $cls ?>"><?= number_format($pct, 1) ?>%</span>
                                        <div style="font-size:11px;color:var(--muted)"><?= $a['correct_count'] ?>/<?= $a['total_questions'] ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;color:var(--muted)">
                                    <?= $a['completed_at'] ? date('d M, h:i A', strtotime($a['completed_at'])) : '—' ?>
                                </td>
                                <td>
                                    <?php if ($a['status'] === 'Not Started'): ?>
                                        <button class="btn-unassign" onclick="unassign(<?= $a['id'] ?>)" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#studentSelect').select2({
        placeholder: 'Search and select students…',
        allowClear: true,
        width: '100%'
    });
});

// Select all students
document.getElementById('selectAllBtn').addEventListener('click', function() {
    const sel = $('#studentSelect');
    const allVals = [];
    sel.find('option').each(function() { allVals.push(this.value); });
    sel.val(allVals).trigger('change');
});

// Unassign student
function unassign(assignmentId) {
    if (!confirm('Remove this assignment?')) return;
    fetch('ajax/unassign_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'assignment_id=' + assignmentId
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const row = document.getElementById('assignRow-' + assignmentId);
            if (row) {
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                row.style.transition = '.3s ease';
                setTimeout(() => row.remove(), 350);
            }
        } else alert(d.error || 'Failed.');
    })
    .catch(() => alert('Network error.'));
}

// Auto-refresh assignments (visibility-aware, saves DB connections)
<?php if ($view_test_id > 0): ?>
(function(){
    var _assignPoll = null;
    var _assignInFlight = false;
    function _pollAssignments() {
        if (_assignInFlight) return;
        _assignInFlight = true;
        fetch('ajax/fetch_test_assignments.php?test_id=<?= $view_test_id ?>')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                data.assignments.forEach(a => {
                    const row = document.getElementById('assignRow-' + a.id);
                    if (!row) return;
                    // Update status badge
                    const badgeCell = row.children[2];
                    let sc = 'ns', sl = a.status;
                    if (sl === 'In Progress') sc = 'ip';
                    if (sl === 'Completed') sc = 'co';
                    badgeCell.innerHTML = '<span class="st-badge ' + sc + '">' + sl + '</span>';

                    // Update score
                    const scoreCell = row.children[3];
                    if (a.score_percentage !== null && sl === 'Completed') {
                        let pct = parseFloat(a.score_percentage);
                        let cls = pct >= 70 ? 'high' : (pct >= 40 ? 'mid' : 'low');
                        scoreCell.innerHTML = '<span class="score-val ' + cls + '">' + pct.toFixed(1) + '%</span>' +
                            '<div style="font-size:11px;color:var(--muted)">' + a.correct_count + '/' + a.total_questions + '</div>';
                    }
                });
            })
            .catch(function(){})
            .finally(function(){ _assignInFlight = false; });
    }
    function _startAssignPoll() { if (!_assignPoll) _assignPoll = setInterval(function(){ if(!document.hidden) _pollAssignments(); }, 300000); }
    function _stopAssignPoll() { if (_assignPoll) { clearInterval(_assignPoll); _assignPoll = null; } }
    document.addEventListener('visibilitychange', function(){ if(document.hidden){ _stopAssignPoll(); } else { _pollAssignments(); _startAssignPoll(); } });
    _startAssignPoll();
})();
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
