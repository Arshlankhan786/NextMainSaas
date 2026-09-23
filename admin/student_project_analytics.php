<?php
/**
 * Student Project Task Manager System
 * Created for internal academy management system
 *
 * Admin view — per-student project analytics with daily logs timeline,
 * admin controls (edit/delete/complete), and past project history.
 */

include 'includes/header.php';

if (!$isAdm) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    include 'includes/footer.php';
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);
if (!$student_id) {
    echo '<div class="alert alert-warning">No student selected. <a href="student_task_manager.php">Go back</a></div>';
    include 'includes/footer.php';
    exit;
}

// ── Student info ──
$stu = $conn->query("
    SELECT s.*, c.name AS course_name
    FROM students s
    JOIN courses c ON s.course_id = c.id
    WHERE s.id = $student_id
")->fetch_assoc();

if (!$stu) {
    echo '<div class="alert alert-danger">Student not found.</div>';
    include 'includes/footer.php';
    exit;
}

// ── All projects for this student ──
$projects = $conn->query("
    SELECT sp.*,
        (SELECT COUNT(*) FROM project_daily_logs WHERE project_id = sp.id) AS total_days,
        (SELECT MAX(log_date) FROM project_daily_logs WHERE project_id = sp.id) AS last_activity
    FROM student_projects sp
    WHERE sp.student_id = $student_id
    ORDER BY sp.status = 'In Progress' DESC, sp.created_at DESC
");

$all_projects = [];
if ($projects) {
    while ($p = $projects->fetch_assoc()) {
        // Get daily logs for each project
        $logs = $conn->query("SELECT * FROM project_daily_logs WHERE project_id = {$p['id']} ORDER BY day_number ASC");
        $p['logs'] = [];
        if ($logs) {
            while ($l = $logs->fetch_assoc()) {
                $p['logs'][] = $l;
            }
        }
        $all_projects[] = $p;
    }
}

// Compute overall stats
$total_days_worked = 0;
$active_projects = [];
$completed_projects = [];
$overall_last_activity = null;

foreach ($all_projects as $p) {
    $total_days_worked += $p['total_days'];
    if ($p['status'] === 'In Progress') $active_projects[] = $p;
    else $completed_projects[] = $p;
    if ($p['last_activity'] && (!$overall_last_activity || $p['last_activity'] > $overall_last_activity)) {
        $overall_last_activity = $p['last_activity'];
    }
}

// Overall status
$overall_status = 'No Projects';
$overall_class = 'stm-badge-muted';
if (!empty($active_projects)) {
    if (!$overall_last_activity) {
        $overall_status = 'No Logs Yet';
        $overall_class = 'stm-badge-muted';
    } else {
        $days_since = (int)((strtotime(date('Y-m-d')) - strtotime($overall_last_activity)) / 86400);
        if ($days_since >= 5) { $overall_status = 'Inactive'; $overall_class = 'stm-badge-danger'; }
        elseif ($days_since >= 2) { $overall_status = 'Warning'; $overall_class = 'stm-badge-warning'; }
        else { $overall_status = 'Active'; $overall_class = 'stm-badge-success'; }
    }
}
?>

<style>
/* ═══════════════════════════════════════════
   STUDENT ANALYTICS — PREMIUM TIMELINE UI
═══════════════════════════════════════════ */
.spa-back { display:inline-flex; align-items:center; gap:6px; color:#6366f1; font-size:12px; font-weight:600; text-decoration:none; margin-bottom:12px }
.spa-back:hover { color:#4338ca }

.spa-hero {
    background: linear-gradient(120deg, #1e1b4b 0%, #312e81 50%, #1e3a5f 100%);
    border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;
    box-shadow: 0 4px 20px rgba(30,27,75,.45);
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.spa-hero-avatar {
    width: 56px; height: 56px; border-radius: 50%; overflow: hidden;
    background: linear-gradient(135deg,#4f46e5,#7c3aed);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 22px; font-weight: 800; flex-shrink: 0;
    border: 3px solid rgba(255,255,255,.25);
}
.spa-hero-avatar img { width:100%; height:100%; object-fit:cover }
.spa-hero-info h2 { font-size:16px; font-weight:800; color:#fff; margin:0 }
.spa-hero-info p { font-size:11px; color:rgba(255,255,255,.55); margin:2px 0 0 }

.spa-stat-strip {
    display: grid; grid-template-columns: repeat(5,1fr); gap:10px; margin-bottom:16px;
}
.spa-stat-item {
    background: #fff; border-radius: 10px; padding: 12px 14px;
    border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.spa-stat-item .spa-stat-label { font-size:9px; font-weight:700; text-transform:uppercase; color:#94a3b8; letter-spacing:.4px }
.spa-stat-item .spa-stat-value { font-size:16px; font-weight:800; color:#0f172a; margin-top:2px }

/* Project card */
.spa-project-card {
    background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 14px; overflow: hidden;
}
.spa-project-header {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
}
.spa-project-title { font-size:14px; font-weight:800; color:#0f172a }
.spa-project-meta { font-size:10.5px; color:#94a3b8; margin-top:2px }
.spa-project-actions { display:flex; gap:6px }
.spa-project-actions button, .spa-project-actions a {
    font-size:11px; font-weight:600; padding:5px 11px; border-radius:7px; cursor:pointer;
    border:none; display:inline-flex; align-items:center; gap:4px; transition:all .15s;
    text-decoration:none;
}
.spa-btn-complete { background:#dcfce7; color:#166534 }
.spa-btn-complete:hover { background:#166534; color:#fff }
.spa-btn-delete-proj { background:#fee2e2; color:#991b1b }
.spa-btn-delete-proj:hover { background:#991b1b; color:#fff }
.spa-btn-add-day { background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff }
.spa-btn-add-day:hover { background:linear-gradient(135deg,#4338ca,#4f46e5) }

/* Timeline */
.spa-timeline { padding: 8px 16px 16px; position: relative }
.spa-timeline::before {
    content: ''; position: absolute; left: 34px; top: 8px; bottom: 16px;
    width: 2px; background: linear-gradient(to bottom, #c7d2fe, #e2e8f0);
}
.spa-day-card {
    display: flex; gap: 12px; margin-bottom: 10px; position: relative;
    padding: 10px 14px 10px 40px;
    background: #fafbff; border-radius: 10px; border: 1px solid #eef2ff;
    transition: all .15s;
}
.spa-day-card:hover { background: #eef2ff; border-color: #c7d2fe }
.spa-day-dot {
    position: absolute; left: 10px; top: 14px;
    width: 22px; height: 22px; border-radius: 50%;
    background: linear-gradient(135deg,#4f46e5,#6366f1);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 800; color: #fff; z-index: 1;
}
.spa-day-body { flex: 1 }
.spa-day-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:4px }
.spa-day-title { font-size:12px; font-weight:700; color:#3730a3 }
.spa-day-date { font-size:10px; color:#94a3b8 }
.spa-day-text { font-size:12px; color:#374151; line-height:1.5 }
.spa-day-actions { display:flex; gap:4px; margin-top:4px }
.spa-day-actions button {
    font-size:10px; padding:3px 8px; border-radius:6px; border:none;
    cursor:pointer; font-weight:600; transition:all .15s;
}
.spa-day-edit { background:#dbeafe; color:#1e40af }
.spa-day-edit:hover { background:#1e40af; color:#fff }
.spa-day-delete { background:#fee2e2; color:#991b1b }
.spa-day-delete:hover { background:#991b1b; color:#fff }

.spa-empty-logs {
    text-align:center; padding:24px; color:#94a3b8; font-size:13px;
}

/* Past projects */
.spa-past-section { margin-top: 20px }
.spa-past-section h3 { font-size:13px; font-weight:800; color:#0f172a; margin-bottom:10px; display:flex; align-items:center; gap:6px }
.spa-past-card {
    background:#fff; border-radius:10px; border:1px solid #e2e8f0; padding:12px 14px;
    margin-bottom:8px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.spa-past-card:hover { background: #f8fafc }

/* Modal overrides */
.spa-modal .modal-content { border-radius:14px; border:none; box-shadow:0 8px 32px rgba(0,0,0,.18) }
.spa-modal .modal-header { background:linear-gradient(120deg,#1e1b4b,#312e81); border-radius:14px 14px 0 0; color:#fff }
.spa-modal .modal-header .btn-close { filter:invert(1) }

@media (max-width:767px) {
    .spa-stat-strip { grid-template-columns: repeat(2,1fr) }
    .spa-hero { padding:14px }
    .spa-timeline::before { left:26px }
    .spa-day-card { padding-left:32px }
    .spa-day-dot { left:4px }
}
</style>

<a href="student_task_manager.php" class="spa-back"><i class="fas fa-arrow-left"></i> Back to Task Manager</a>

<!-- ══ STUDENT HERO ══ -->
<div class="spa-hero">
    <div class="spa-hero-avatar">
        <?php if (!empty($stu['photo'])): ?>
            <img src="<?= htmlspecialchars($stu['photo']) ?>" alt="">
        <?php else: ?>
            <?= strtoupper(substr($stu['full_name'], 0, 1)) ?>
        <?php endif; ?>
    </div>
    <div class="spa-hero-info">
        <h2><?= htmlspecialchars($stu['full_name']) ?></h2>
        <p><?= htmlspecialchars($stu['student_code']) ?> · <?= htmlspecialchars($stu['course_name']) ?> · <?= $stu['batch'] ?? 'N/A' ?></p>
    </div>
    <div style="margin-left:auto">
        <span class="stm-badge <?= $overall_class ?>" style="font-size:11px;padding:5px 12px">
            <?= $overall_status ?>
        </span>
    </div>
</div>

<!-- ══ STAT STRIP ══ -->
<div class="spa-stat-strip">
    <div class="spa-stat-item">
        <div class="spa-stat-label">Current Project</div>
        <div class="spa-stat-value" style="font-size:13px"><?= !empty($active_projects) ? htmlspecialchars($active_projects[0]['project_name']) : '—' ?></div>
    </div>
    <div class="spa-stat-item">
        <div class="spa-stat-label">Total Days Worked</div>
        <div class="spa-stat-value"><?= $total_days_worked ?></div>
    </div>
    <div class="spa-stat-item">
        <div class="spa-stat-label">Project Start Date</div>
        <div class="spa-stat-value" style="font-size:13px"><?= !empty($active_projects) ? date('d M Y', strtotime($active_projects[0]['start_date'])) : '—' ?></div>
    </div>
    <div class="spa-stat-item">
        <div class="spa-stat-label">Last Activity</div>
        <div class="spa-stat-value" style="font-size:13px"><?= $overall_last_activity ? date('d M Y', strtotime($overall_last_activity)) : '—' ?></div>
    </div>
    <div class="spa-stat-item">
        <div class="spa-stat-label">Project Status</div>
        <div class="spa-stat-value" style="font-size:13px"><?= $overall_status ?></div>
    </div>
</div>

<!-- ══ ACTIVE PROJECTS WITH TIMELINE ══ -->
<?php foreach ($active_projects as $p): ?>
<div class="spa-project-card" id="project-<?= $p['id'] ?>">
    <div class="spa-project-header">
        <div>
            <div class="spa-project-title"><i class="fas fa-folder-open" style="color:#6366f1;margin-right:6px"></i><?= htmlspecialchars($p['project_name']) ?></div>
            <div class="spa-project-meta">
                Started <?= date('d M Y', strtotime($p['start_date'])) ?> · <?= $p['total_days'] ?> days worked
                <?php if (!empty($p['description'])): ?> · <?= htmlspecialchars(substr($p['description'], 0, 80)) ?><?php endif; ?>
            </div>
        </div>
        <div class="spa-project-actions">
            <button class="spa-btn-add-day" onclick="openAddDayModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">
                <i class="fas fa-plus"></i> Add Day
            </button>
            <button class="spa-btn-complete" onclick="completeProject(<?= $p['id'] ?>)">
                <i class="fas fa-check"></i> Complete
            </button>
            <button class="spa-btn-delete-proj" onclick="deleteProject(<?= $p['id'] ?>)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>

    <!-- Daily Logs Timeline -->
    <div class="spa-timeline">
        <?php if (empty($p['logs'])): ?>
            <div class="spa-empty-logs"><i class="fas fa-inbox"></i> No daily logs yet. Click "Add Day" to start tracking progress.</div>
        <?php else: ?>
            <?php foreach ($p['logs'] as $log): ?>
            <div class="spa-day-card" id="log-<?= $log['id'] ?>">
                <div class="spa-day-dot"><?= $log['day_number'] ?></div>
                <div class="spa-day-body">
                    <div class="spa-day-header">
                        <span class="spa-day-title">Day <?= $log['day_number'] ?></span>
                        <span class="spa-day-date"><i class="fas fa-calendar-alt"></i> <?= date('d M Y', strtotime($log['log_date'])) ?></span>
                    </div>
                    <div class="spa-day-text" id="log-text-<?= $log['id'] ?>"><?= nl2br(htmlspecialchars($log['work_description'])) ?></div>
                    <div class="spa-day-actions">
                        <button class="spa-day-edit" onclick="openEditLogModal(<?= $log['id'] ?>, <?= htmlspecialchars(json_encode($log['work_description'])) ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="spa-day-delete" onclick="deleteLog(<?= $log['id'] ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- ══ PAST PROJECTS (COMPLETED) ══ -->
<?php if (!empty($completed_projects)): ?>
<div class="spa-past-section">
    <h3><i class="fas fa-history" style="color:#6366f1"></i> Past Projects</h3>
    <?php foreach ($completed_projects as $cp): ?>
    <div class="spa-past-card">
        <div>
            <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($cp['project_name']) ?></div>
            <div style="font-size:10.5px;color:#94a3b8">
                Started <?= date('d M Y', strtotime($cp['start_date'])) ?>
                <?php if ($cp['completed_date']): ?> · Completed <?= date('d M Y', strtotime($cp['completed_date'])) ?><?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-weight:700;color:#4f46e5;font-size:13px"><?= $cp['total_days'] ?> days</span>
            <span class="stm-badge stm-badge-info"><i class="fas fa-check"></i> Completed</span>
            <button class="spa-btn-delete-proj" onclick="deleteProject(<?= $cp['id'] ?>)" style="font-size:10px;padding:3px 8px">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <!-- Expandable logs for completed projects -->
    <?php if (!empty($cp['logs'])): ?>
    <div class="spa-project-card" style="margin-left:16px;margin-bottom:14px;border-color:#e2e8f0">
        <div class="spa-timeline" style="padding-top:12px">
            <?php foreach ($cp['logs'] as $log): ?>
            <div class="spa-day-card" style="background:#f8fafc">
                <div class="spa-day-dot" style="background:#94a3b8"><?= $log['day_number'] ?></div>
                <div class="spa-day-body">
                    <div class="spa-day-header">
                        <span class="spa-day-title" style="color:#64748b">Day <?= $log['day_number'] ?></span>
                        <span class="spa-day-date"><?= date('d M Y', strtotime($log['log_date'])) ?></span>
                    </div>
                    <div class="spa-day-text"><?= nl2br(htmlspecialchars($log['work_description'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($all_projects)): ?>
<div class="spa-project-card">
    <div style="text-align:center;padding:40px 20px;color:#94a3b8">
        <i class="fas fa-folder-open" style="font-size:36px;margin-bottom:10px;display:block;color:#cbd5e1"></i>
        <div style="font-size:14px;font-weight:700;color:#64748b;margin-bottom:4px">No Projects Yet</div>
        <div style="font-size:12px;margin-bottom:14px">This student hasn't started any projects</div>
        <button class="spa-btn-add-day" onclick="openCreateProjectModal()">
            <i class="fas fa-plus"></i> Create Project
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ══ ADD DAY MODAL ══ -->
<div class="modal fade spa-modal" id="addDayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Day Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="addDayProjectId">
                <p style="font-size:12px;color:#64748b;margin-bottom:12px" id="addDayInfo"></p>
                <label class="form-label" style="font-weight:600;font-size:12px">What did you work on today?</label>
                <textarea class="form-control" id="addDayWork" rows="4" placeholder="Describe today's progress..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-purple btn-sm" onclick="submitAddDay()"><i class="fas fa-save"></i> Add Day</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ EDIT LOG MODAL ══ -->
<div class="modal fade spa-modal" id="editLogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editLogId">
                <label class="form-label" style="font-weight:600;font-size:12px">Work Description</label>
                <textarea class="form-control" id="editLogWork" rows="4"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-purple btn-sm" onclick="submitEditLog()"><i class="fas fa-save"></i> Update</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ CREATE PROJECT MODAL ══ -->
<div class="modal fade spa-modal" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-plus"></i> Create Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:12px">Project Name *</label>
                    <input type="text" class="form-control" id="newProjectName" placeholder="e.g., E-Commerce Website">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight:600;font-size:12px">Description</label>
                    <textarea class="form-control" id="newProjectDesc" rows="3" placeholder="Brief project description..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-purple btn-sm" onclick="submitCreateProject()"><i class="fas fa-plus"></i> Create</button>
            </div>
        </div>
    </div>
</div>

<script>
const STUDENT_ID = <?= $student_id ?>;

// ── Add Day Modal ──
function openAddDayModal(projectId, projectName) {
    document.getElementById('addDayProjectId').value = projectId;
    document.getElementById('addDayInfo').textContent = 'Project: ' + projectName;
    document.getElementById('addDayWork').value = '';
    new bootstrap.Modal(document.getElementById('addDayModal')).show();
}

function submitAddDay() {
    const projectId = document.getElementById('addDayProjectId').value;
    const work = document.getElementById('addDayWork').value.trim();
    if (!work) { alert('Please describe the work done'); return; }

    const fd = new FormData();
    fd.append('action', 'add_day_log');
    fd.append('project_id', projectId);
    fd.append('work_description', work);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}

// ── Edit Log Modal ──
function openEditLogModal(logId, workText) {
    document.getElementById('editLogId').value = logId;
    document.getElementById('editLogWork').value = workText;
    new bootstrap.Modal(document.getElementById('editLogModal')).show();
}

function submitEditLog() {
    const logId = document.getElementById('editLogId').value;
    const work = document.getElementById('editLogWork').value.trim();
    if (!work) { alert('Work description required'); return; }

    const fd = new FormData();
    fd.append('action', 'edit_day_log');
    fd.append('log_id', logId);
    fd.append('work_description', work);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}

// ── Delete Log ──
function deleteLog(logId) {
    if (!confirm('Delete this log entry? Day numbers will be renumbered.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_day_log');
    fd.append('log_id', logId);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}

// ── Complete Project ──
function completeProject(projectId) {
    if (!confirm('Mark this project as completed?')) return;
    const fd = new FormData();
    fd.append('action', 'complete_project');
    fd.append('project_id', projectId);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}

// ── Delete Project ──
function deleteProject(projectId) {
    if (!confirm('Delete this project and ALL daily logs? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_project');
    fd.append('project_id', projectId);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}

// ── Create Project Modal ──
function openCreateProjectModal() {
    document.getElementById('newProjectName').value = '';
    document.getElementById('newProjectDesc').value = '';
    new bootstrap.Modal(document.getElementById('createProjectModal')).show();
}

function submitCreateProject() {
    const name = document.getElementById('newProjectName').value.trim();
    const desc = document.getElementById('newProjectDesc').value.trim();
    if (!name) { alert('Project name is required'); return; }

    const fd = new FormData();
    fd.append('action', 'create_project');
    fd.append('student_id', STUDENT_ID);
    fd.append('project_name', name);
    fd.append('description', desc);

    fetch('ajax/student_project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { location.reload(); }
            else { alert(d.message); }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
