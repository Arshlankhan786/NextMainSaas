<?php
/**
 * Student Project Task Manager System
 * Created for internal academy management system
 *
 * Admin view — lists all students with project status, activity badges,
 * and dashboard-style stat cards at the top.
 */

include 'includes/header.php';

// Guard — only Admin+ can access
if (!$isAdm) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    include 'includes/footer.php';
    exit;
}

// ══════════════════════════════════════
// STAT QUERIES
// ══════════════════════════════════════
$today = date('Y-m-d');

// Total projects in progress
$projects_active = 0;
$projects_completed = 0;
$students_active_count = 0;
$students_inactive_count = 0;
$students_warning_count = 0;

try {
    $projects_active    = (int)$conn->query("SELECT COUNT(*) AS c FROM student_projects WHERE status='In Progress'")->fetch_assoc()['c'];
    $projects_completed = (int)$conn->query("SELECT COUNT(*) AS c FROM student_projects WHERE status='Completed'")->fetch_assoc()['c'];

    // Students with update in last 2 days
    $students_active_count = (int)$conn->query("
        SELECT COUNT(DISTINCT sp.student_id) AS c
        FROM student_projects sp
        JOIN project_daily_logs dl ON sp.id = dl.project_id
        WHERE sp.status = 'In Progress'
          AND dl.log_date >= DATE_SUB('$today', INTERVAL 2 DAY)
    ")->fetch_assoc()['c'];

    // Students with no update for 5+ days (Inactive)
    $students_inactive_count = (int)$conn->query("
        SELECT COUNT(DISTINCT sp.student_id) AS c
        FROM student_projects sp
        WHERE sp.status = 'In Progress'
          AND sp.student_id NOT IN (
              SELECT DISTINCT sp2.student_id
              FROM student_projects sp2
              JOIN project_daily_logs dl2 ON sp2.id = dl2.project_id
              WHERE dl2.log_date >= DATE_SUB('$today', INTERVAL 5 DAY)
          )
    ")->fetch_assoc()['c'];

    // Warning — no update 2-4 days
    $students_warning_count = (int)$conn->query("
        SELECT COUNT(DISTINCT sp.student_id) AS c
        FROM student_projects sp
        WHERE sp.status = 'In Progress'
          AND sp.student_id NOT IN (
              SELECT DISTINCT sp2.student_id
              FROM student_projects sp2
              JOIN project_daily_logs dl2 ON sp2.id = dl2.project_id
              WHERE dl2.log_date >= DATE_SUB('$today', INTERVAL 2 DAY)
          )
          AND sp.student_id IN (
              SELECT DISTINCT sp3.student_id
              FROM student_projects sp3
              JOIN project_daily_logs dl3 ON sp3.id = dl3.project_id
              WHERE dl3.log_date >= DATE_SUB('$today', INTERVAL 5 DAY)
          )
    ")->fetch_assoc()['c'];
} catch (Exception $e) {
    // Tables may not exist yet
}

// ══════════════════════════════════════
// ALL ACTIVE STUDENTS WITH PROJECT INFO
// ══════════════════════════════════════
$students_data = [];
try {
    $result = $conn->query("
        SELECT
            s.id,
            s.student_code,
            s.full_name,
            s.photo,
            s.batch,
            c.name AS course_name,
            sp.id AS project_id,
            sp.project_name,
            sp.start_date AS project_start,
            sp.status AS project_status,
            (SELECT COUNT(*) FROM project_daily_logs WHERE project_id = sp.id) AS total_days,
            (SELECT MAX(log_date) FROM project_daily_logs WHERE project_id = sp.id) AS last_activity
        FROM students s
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN student_projects sp ON s.id = sp.student_id AND sp.status = 'In Progress'
        WHERE s.status = 'Active'
        ORDER BY last_activity DESC, s.full_name ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students_data[] = $row;
        }
    }
} catch (Exception $e) {
    // fallback: just get students
    $result = $conn->query("SELECT s.id, s.student_code, s.full_name, s.photo, s.batch, c.name AS course_name FROM students s JOIN courses c ON s.course_id = c.id WHERE s.status='Active' ORDER BY s.full_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['project_id'] = null;
            $row['project_name'] = null;
            $row['project_start'] = null;
            $row['project_status'] = null;
            $row['total_days'] = 0;
            $row['last_activity'] = null;
            $students_data[] = $row;
        }
    }
}

/**
 * Determine student activity status based on last log date
 */
function getActivityStatus($last_activity, $project_status) {
    if (!$project_status || $project_status === 'Completed') return ['label' => 'No Active Project', 'class' => 'stm-badge-muted', 'icon' => 'fa-minus-circle'];
    if (!$last_activity) return ['label' => 'No Logs Yet', 'class' => 'stm-badge-muted', 'icon' => 'fa-clock'];

    $days_since = (int)((strtotime(date('Y-m-d')) - strtotime($last_activity)) / 86400);

    if ($days_since >= 5) return ['label' => 'Inactive', 'class' => 'stm-badge-danger', 'icon' => 'fa-exclamation-circle'];
    if ($days_since >= 2) return ['label' => 'Warning', 'class' => 'stm-badge-warning', 'icon' => 'fa-exclamation-triangle'];
    return ['label' => 'Active', 'class' => 'stm-badge-success', 'icon' => 'fa-check-circle'];
}
?>

<style>
/* ═══════════════════════════════════════════
   STUDENT TASK MANAGER — PREMIUM UI
═══════════════════════════════════════════ */
.stm-hero {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 18px; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;
    background: linear-gradient(120deg, #1e1b4b 0%, #312e81 50%, #1e3a5f 100%);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(30,27,75,.45);
}
.stm-hero h2 { font-size: 16px; font-weight: 800; color: #fff; margin: 0 }
.stm-hero h2 i { margin-right: 8px; color: #a5b4fc }
.stm-hero p { font-size: 11px; color: rgba(255,255,255,.5); margin: 2px 0 0 }

/* Stat cards row */
.stm-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
.stm-stat-card {
    border-radius: 11px; padding: 14px 13px;
    display: flex; align-items: center; gap: 11px;
    transition: transform .18s ease, box-shadow .18s ease;
    position: relative; overflow: hidden; border: none;
}
.stm-stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.18) }
.stm-stat-card::after {
    content: ''; position: absolute; right: -16px; top: -16px;
    width: 65px; height: 65px; border-radius: 50%;
    background: rgba(255,255,255,.1); pointer-events: none;
}
.stm-stat-icon {
    width: 44px; height: 44px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 19px; color: #fff;
    background: rgba(255,255,255,.2);
    border: 1.5px solid rgba(255,255,255,.3);
    flex-shrink: 0; z-index: 1;
}
.stm-stat-num { font-size: 26px; font-weight: 900; color: #fff; line-height: 1; z-index: 1 }
.stm-stat-label { font-size: 9px; font-weight: 700; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: .5px; z-index: 1 }
.stm-sc-green  { background: #065f46; box-shadow: 0 4px 14px rgba(6,95,70,.45) }
.stm-sc-red    { background: #9f1239; box-shadow: 0 4px 14px rgba(159,18,57,.4) }
.stm-sc-blue   { background: #1d4ed8; box-shadow: 0 4px 14px rgba(29,78,216,.4) }
.stm-sc-purple { background: #5b21b6; box-shadow: 0 4px 14px rgba(91,33,182,.4) }

/* Student list table */
.stm-table-card {
    background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow: hidden;
}
.stm-table-card .table { margin: 0; font-size: 12.5px }
.stm-table-card .table thead th {
    background: #eef2ff; color: #3730a3; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; border: none; padding: 10px 12px;
}
.stm-table-card .table tbody td { padding: 10px 12px; vertical-align: middle; border-color: #f1f5f9 }
.stm-table-card .table tbody tr { cursor: pointer; transition: background .15s }
.stm-table-card .table tbody tr:hover { background: #f5f3ff }

/* Badges */
.stm-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; padding: 3px 9px;
    border-radius: 99px; letter-spacing: .2px;
}
.stm-badge-success { background: #dcfce7; color: #166534 }
.stm-badge-warning { background: #fef3c7; color: #92400e }
.stm-badge-danger  { background: #fee2e2; color: #991b1b }
.stm-badge-muted   { background: #f1f5f9; color: #64748b }
.stm-badge-info    { background: #dbeafe; color: #1e40af }

/* Student avatar */
.stm-avatar {
    width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0;
}
.stm-avatar img { width: 100%; height: 100%; object-fit: cover }

/* Search */
.stm-search {
    padding: 12px 14px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 10px;
}
.stm-search input {
    flex: 1; border: 1.5px solid #e2e8f0; border-radius: 9px;
    padding: 8px 12px; font-size: 13px; font-family: inherit;
    transition: border-color .18s;
}
.stm-search input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12) }

/* Create project button */
.stm-btn-create {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff; border: none; padding: 8px 16px; border-radius: 9px;
    font-size: 12px; font-weight: 700; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all .18s;
}
.stm-btn-create:hover { background: linear-gradient(135deg, #4338ca, #4f46e5); transform: translateY(-1px) }

@media (max-width: 767px) {
    .stm-stats { grid-template-columns: repeat(2, 1fr) }
    .stm-hero { padding: 10px 14px }
    .stm-hero h2 { font-size: 14px }
    .stm-table-card .table thead th { font-size: 9px; padding: 8px 6px }
    .stm-table-card .table tbody td { padding: 8px 6px; font-size: 11.5px }
    .stm-search { flex-wrap: wrap }
    .stm-search input { min-width: 0 }
}
@media (max-width: 480px) {
    .stm-stats { grid-template-columns: 1fr 1fr; gap: 6px }
    .stm-stat-num { font-size: 20px }
    .stm-stat-icon { width: 36px; height: 36px; font-size: 15px }
}
</style>

<!-- ══ PAGE HERO ══ -->
<div class="stm-hero">
    <div>
        <h2><i class="fas fa-clipboard-list"></i> Student Task Manager</h2>
        <p>Monitor student project progress & daily activity</p>
    </div>
    <div style="font-size:11px;color:rgba(255,255,255,.7);font-weight:600;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:99px;padding:4px 12px;">
        <i class="fas fa-calendar"></i> <?= date('d M Y') ?>
    </div>
</div>

<!-- ══ STAT CARDS ══ -->
<div class="stm-stats">
    <div class="stm-stat-card stm-sc-green">
        <div class="stm-stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stm-stat-num"><?= $students_active_count ?></div>
            <div class="stm-stat-label">Active Students</div>
        </div>
    </div>
    <div class="stm-stat-card stm-sc-red">
        <div class="stm-stat-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <div class="stm-stat-num"><?= $students_inactive_count ?></div>
            <div class="stm-stat-label">Inactive Students</div>
        </div>
    </div>
    <div class="stm-stat-card stm-sc-blue">
        <div class="stm-stat-icon"><i class="fas fa-spinner"></i></div>
        <div>
            <div class="stm-stat-num"><?= $projects_active ?></div>
            <div class="stm-stat-label">Projects In Progress</div>
        </div>
    </div>
    <div class="stm-stat-card stm-sc-purple">
        <div class="stm-stat-icon"><i class="fas fa-check-double"></i></div>
        <div>
            <div class="stm-stat-num"><?= $projects_completed ?></div>
            <div class="stm-stat-label">Completed Projects</div>
        </div>
    </div>
</div>

<!-- ══ STUDENT LIST ══ -->
<div class="stm-table-card">
    <div class="stm-search">
        <i class="fas fa-search" style="color:#94a3b8;font-size:14px"></i>
        <input type="text" id="stmSearch" placeholder="Search students by name, project, status...">
        <button class="stm-btn-create" onclick="document.getElementById('createProjectModal') && new bootstrap.Modal(document.getElementById('createProjectModal')).show()" type="button">
            <i class="fas fa-plus"></i> Create Project
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="stmTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Current Project</th>
                    <th>Days Worked</th>
                    <th>Last Activity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students_data)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No students found</td></tr>
                <?php else: ?>
                <?php foreach ($students_data as $s):
                    $status = getActivityStatus($s['last_activity'], $s['project_status']);
                ?>
                <tr onclick="window.location.href='student_project_analytics.php?student_id=<?= $s['id'] ?>'">
                    <td>
                        <div style="display:flex;align-items:center;gap:9px">
                            <div class="stm-avatar">
                                <?php if (!empty($s['photo'])): ?>
                                    <img src="<?= htmlspecialchars($s['photo']) ?>" alt="">
                                <?php else: ?>
                                    <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:12.5px"><?= htmlspecialchars($s['full_name']) ?></div>
                                <div style="font-size:10px;color:#94a3b8"><?= htmlspecialchars($s['student_code']) ?> · <?= $s['batch'] ?? '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span style="font-size:11.5px"><?= htmlspecialchars($s['course_name'] ?? '') ?></span></td>
                    <td>
                        <?php if ($s['project_name']): ?>
                            <span style="font-weight:600"><?= htmlspecialchars($s['project_name']) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-style:italic">No project</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['total_days']): ?>
                            <span style="font-weight:700;color:#4f46e5"><?= $s['total_days'] ?></span> days
                        <?php else: ?>
                            <span style="color:#94a3b8">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['last_activity']): ?>
                            <?= date('d M Y', strtotime($s['last_activity'])) ?>
                        <?php else: ?>
                            <span style="color:#94a3b8">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="stm-badge <?= $status['class'] ?>">
                            <i class="fas <?= $status['icon'] ?>"></i> <?= $status['label'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search filter
document.getElementById('stmSearch').addEventListener('keyup', function() {
    const f = this.value.toUpperCase();
    const rows = document.querySelectorAll('#stmTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toUpperCase().includes(f) ? '' : 'none';
    });
});
</script>

<!-- ═══ CREATE PROJECT MODAL (Part 5) ═══ -->
<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-plus" style="margin-right:8px;"></i>Create Student Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProjectForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student <span style="color:red;">*</span></label>
                        <select id="cp-student-id" class="form-select" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students_data as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['student_code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project Name <span style="color:red;">*</span></label>
                        <input type="text" id="cp-project-name" class="form-control" placeholder="e.g., E-Commerce Website" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="cp-description" class="form-control" rows="3" placeholder="Brief project description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple" id="cp-submit-btn">
                        <i class="fas fa-plus" style="margin-right:6px;"></i>Create Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Create Project Modal AJAX
document.getElementById('createProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('cp-submit-btn');
    const studentId = document.getElementById('cp-student-id').value;
    const projectName = document.getElementById('cp-project-name').value.trim();
    const description = document.getElementById('cp-description').value.trim();

    if (!studentId || !projectName) {
        alert('Please select a student and enter a project name');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

    const fd = new FormData();
    fd.append('action', 'create_project');
    fd.append('student_id', studentId);
    fd.append('project_name', projectName);
    fd.append('description', description);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('createProjectModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus" style="margin-right:6px;"></i>Create Project';
        });
});
</script>

<?php include 'includes/footer.php'; ?>
