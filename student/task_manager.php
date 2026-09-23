<?php
/**
 * Student Project Task Manager System
 * Created for internal academy management system
 *
 * Student portal — create projects, add daily progress logs,
 * view past projects, manage daily work tracking.
 *
 * FEATURES:
 *  - Date-based day numbers (DATEDIFF from start)
 *  - Overdue warning if no log submitted today
 *  - Project Activity Panel (last 3 logs per card)
 *  - Full timeline popup modal per project
 *  - Complete project button
 *  - Popup modals for all form actions
 */

include 'includes/header.php';

$today = date('Y-m-d');

// ── Fetch student's projects ──
$active_projects = [];
$past_projects   = [];

try {
    $result = $conn->query("
        SELECT sp.*,
            (SELECT COUNT(*) FROM project_daily_logs WHERE project_id = sp.id) AS total_days,
            (SELECT MAX(log_date) FROM project_daily_logs WHERE project_id = sp.id) AS last_activity
        FROM student_projects sp
        WHERE sp.student_id = $sid
        ORDER BY sp.status = 'In Progress' DESC, sp.created_at DESC
    ");

    if ($result) {
        while ($p = $result->fetch_assoc()) {
            // Fetch last 3 logs (for activity panel)
            $logs3 = $conn->query("SELECT * FROM project_daily_logs WHERE project_id = {$p['id']} ORDER BY log_date DESC LIMIT 3");
            $p['last3logs'] = [];
            if ($logs3) {
                while ($l = $logs3->fetch_assoc()) $p['last3logs'][] = $l;
            }
            // Check if today's log exists
            $todayLog = $conn->query("SELECT id FROM project_daily_logs WHERE project_id = {$p['id']} AND log_date = '$today'");
            $p['has_today_log'] = ($todayLog && $todayLog->num_rows > 0);

            if ($p['status'] === 'In Progress') $active_projects[] = $p;
            else $past_projects[] = $p;
        }
    }
} catch (Exception $e) {
    // Tables may not exist yet
}

// ── Overdue detection ──
$has_overdue = false;
foreach ($active_projects as $p) {
    if (!$p['has_today_log']) {
        $has_overdue = true;
        break;
    }
}
?>

<style>
/* ═══════════════════════════════════════════
   STUDENT TASK MANAGER — DARK PORTAL THEME
═══════════════════════════════════════════ */

/* Hero */
.ptm-hero {
    background: linear-gradient(120deg, rgba(99,102,241,.15), rgba(139,92,246,.1));
    border: 1px solid rgba(99,102,241,.2);
    border-radius: 14px; padding: 16px 18px; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
}
.ptm-hero h2 { font-size: 16px; font-weight: 800; margin: 0; color: var(--text-primary, #fff) }
.ptm-hero h2 i { color: var(--purple-light, #a78bfa); margin-right: 8px }
.ptm-hero p { font-size: 11px; color: var(--text-dim, #94a3b8); margin: 2px 0 0 }

/* Overdue Banner */
.ptm-overdue-banner {
    background: rgba(239,68,68,.12);
    border: 1px solid rgba(239,68,68,.3);
    border-radius: 10px; padding: 12px 16px;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px;
    animation: pulseWarn 2.5s ease-in-out infinite;
}
.ptm-overdue-banner i { color: #f87171; font-size: 15px; flex-shrink: 0; }
.ptm-overdue-banner span { font-size: 12.5px; font-weight: 700; color: #fca5a5; }
@keyframes pulseWarn {
    0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    50% { box-shadow: 0 0 0 5px rgba(239,68,68,.08); }
}

/* Create project button */
.ptm-btn-create {
    background: linear-gradient(135deg, #7c3aed, #6366f1);
    color: #fff; border: none; padding: 9px 18px; border-radius: 10px;
    font-size: 12px; font-weight: 700; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all .18s; font-family: inherit;
}
.ptm-btn-create:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(124,58,237,.4) }

/* Project card */
.ptm-project {
    background: var(--card-bg, rgba(255,255,255,.06));
    border: 1px solid var(--card-border, rgba(255,255,255,.08));
    border-radius: 14px; margin-bottom: 14px; overflow: hidden;
    transition: all .18s;
}
.ptm-project:hover { border-color: rgba(99,102,241,.3) }
.ptm-project.overdue-card {
    border-color: rgba(239,68,68,.35) !important;
    box-shadow: 0 0 0 1px rgba(239,68,68,.15), inset 0 0 20px rgba(239,68,68,.04);
}
.ptm-project-header {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    padding: 14px 16px; border-bottom: 1px solid var(--card-border, rgba(255,255,255,.06));
}
.ptm-project-title { font-size: 14px; font-weight: 800; color: var(--text-primary, #fff) }
.ptm-project-title i { color: #a78bfa; margin-right: 6px }
.ptm-project-meta { font-size: 10.5px; color: var(--text-dim, #94a3b8); margin-top: 2px }
.ptm-overdue-tag {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; color: #f87171;
    background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25);
    padding: 2px 8px; border-radius: 99px; margin-left: 6px;
}

.ptm-project-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.ptm-project-actions button {
    font-size: 11px; font-weight: 600; padding: 6px 12px; border-radius: 8px;
    cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px;
    transition: all .15s; font-family: inherit;
}
.ptm-btn-day {
    background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff;
}
.ptm-btn-day:hover { transform: translateY(-1px); box-shadow: 0 3px 12px rgba(124,58,237,.35) }
.ptm-btn-complete {
    background: rgba(16,185,129,.15); color: #34d399; border: 1px solid rgba(16,185,129,.25) !important;
}
.ptm-btn-complete:hover { background: #10b981; color: #fff; }

/* Activity Panel */
.ptm-activity-panel {
    padding: 12px 16px;
    border-bottom: 1px solid var(--card-border, rgba(255,255,255,.06));
    background: rgba(99,102,241,.04);
}
.ptm-activity-title {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    color: var(--text-dim, #94a3b8); letter-spacing: .6px; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between;
}
.ptm-activity-row {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,.04);
    font-size: 11.5px; color: var(--text-secondary, #d1d5db);
}
.ptm-activity-row:last-child { border-bottom: none; padding-bottom: 0; }
.ptm-act-date {
    font-size: 10.5px; color: #818cf8; font-weight: 600;
    white-space: nowrap; flex-shrink: 0; min-width: 82px;
}
.ptm-act-desc { flex: 1; line-height: 1.4; word-break: break-word; }
.ptm-act-day {
    font-size: 10px; font-weight: 700; color: #a78bfa;
    white-space: nowrap; flex-shrink: 0;
    background: rgba(99,102,241,.12); padding: 2px 7px; border-radius: 99px;
}
.ptm-btn-full-update {
    background: rgba(99,102,241,.12); color: #818cf8;
    border: 1px solid rgba(99,102,241,.2) !important;
    font-size: 10.5px; padding: 5px 11px; border-radius: 7px;
    cursor: pointer; font-weight: 600; transition: all .15s; font-family: inherit;
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 8px;
}
.ptm-btn-full-update:hover { background: rgba(99,102,241,.25); color: #a5b4fc; }

/* Timeline */
.ptm-timeline { padding: 10px 16px 16px; position: relative }
.ptm-timeline::before {
    content: ''; position: absolute; left: 34px; top: 10px; bottom: 16px;
    width: 2px; background: linear-gradient(to bottom, rgba(99,102,241,.4), rgba(99,102,241,.1));
}
.ptm-day {
    display: flex; gap: 12px; margin-bottom: 8px; position: relative;
    padding: 10px 14px 10px 40px;
    background: rgba(99,102,241,.06); border-radius: 10px;
    border: 1px solid rgba(99,102,241,.1);
    transition: all .15s;
}
.ptm-day:hover { background: rgba(99,102,241,.1); border-color: rgba(99,102,241,.2) }
.ptm-day-dot {
    position: absolute; left: 10px; top: 14px;
    width: 22px; height: 22px; border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #6366f1);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 800; color: #fff; z-index: 1;
}
.ptm-day-body { flex: 1 }
.ptm-day-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 3px }
.ptm-day-title { font-size: 12px; font-weight: 700; color: var(--purple-light, #a78bfa) }
.ptm-day-date { font-size: 10px; color: var(--text-dim, #94a3b8) }
.ptm-day-text { font-size: 12px; color: var(--text-secondary, #d1d5db); line-height: 1.5 }
.ptm-day-actions { display: flex; gap: 4px; margin-top: 5px }
.ptm-day-actions button {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; border: none;
    cursor: pointer; font-weight: 600; transition: all .15s; font-family: inherit;
}
.ptm-day-edit { background: rgba(59,130,246,.15); color: #60a5fa }
.ptm-day-edit:hover { background: #3b82f6; color: #fff }
.ptm-day-del { background: rgba(239,68,68,.15); color: #f87171 }
.ptm-day-del:hover { background: #ef4444; color: #fff }

.ptm-empty {
    text-align: center; padding: 24px; color: var(--text-dim, #94a3b8); font-size: 13px;
}

/* Past projects */
.ptm-past-title { font-size: 13px; font-weight: 800; color: var(--text-primary, #fff); margin: 20px 0 10px; display: flex; align-items: center; gap: 6px }
.ptm-past-card {
    background: var(--card-bg, rgba(255,255,255,.04)); border: 1px solid var(--card-border, rgba(255,255,255,.06));
    border-radius: 10px; padding: 12px 14px; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}

/* Badges */
.ptm-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 99px;
}
.ptm-badge-active { background: rgba(16,185,129,.15); color: #34d399 }
.ptm-badge-done { background: rgba(99,102,241,.15); color: #818cf8 }

/* No projects state */
.ptm-no-projects {
    text-align: center; padding: 50px 20px;
    background: var(--card-bg, rgba(255,255,255,.04));
    border: 1px dashed var(--card-border, rgba(255,255,255,.1));
    border-radius: 14px;
}
.ptm-no-projects i { font-size: 40px; color: var(--text-dim, #64748b); margin-bottom: 12px; display: block }

/* ── Modals ── */
.ptm-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,.75); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.ptm-modal-overlay.active { display: flex; }
.ptm-modal-box {
    background: #1e1533; border: 1px solid rgba(99,102,241,.25);
    border-radius: 16px; width: 90%; max-width: 500px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.6);
    animation: ptmSlideUp .22s ease;
}
.ptm-modal-box.wide { max-width: 680px; }
@keyframes ptmSlideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ptm-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid rgba(99,102,241,.15);
}
.ptm-modal-header h5 { font-size: 14px; font-weight: 800; color: #fff; margin: 0; }
.ptm-modal-header h5 i { color: #a78bfa; margin-right: 8px; }
.ptm-modal-close {
    background: none; border: none; color: #94a3b8; font-size: 18px;
    cursor: pointer; line-height: 1; padding: 2px 6px; border-radius: 6px;
    transition: all .15s;
}
.ptm-modal-close:hover { color: #fff; background: rgba(255,255,255,.1); }
.ptm-modal-body { padding: 18px 20px; }
.ptm-modal-footer { padding: 12px 20px; border-top: 1px solid rgba(99,102,241,.12); display: flex; justify-content: flex-end; gap: 8px; }
.ptm-form-group { margin-bottom: 14px; }
.ptm-form-label { font-size: 11.5px; font-weight: 700; color: #d1d5db; margin-bottom: 6px; display: block; }
.ptm-form-control {
    width: 100%; padding: 9px 13px; border-radius: 9px;
    background: rgba(255,255,255,.06); border: 1.5px solid rgba(99,102,241,.2);
    color: #fff; font-size: 13px; font-family: inherit;
    transition: border-color .18s; outline: none; resize: vertical;
}
.ptm-form-control:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.ptm-form-hint { font-size: 10.5px; color: #64748b; margin-top: 4px; }
.ptm-btn-cancel {
    background: rgba(255,255,255,.06); color: #94a3b8; border: 1px solid rgba(255,255,255,.1);
    padding: 8px 16px; border-radius: 9px; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: all .15s;
}
.ptm-btn-cancel:hover { background: rgba(255,255,255,.1); color: #fff; }
.ptm-btn-submit {
    background: linear-gradient(135deg, #7c3aed, #6366f1); color: #fff; border: none;
    padding: 8px 18px; border-radius: 9px; font-size: 12px; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: all .18s;
    display: inline-flex; align-items: center; gap: 6px;
}
.ptm-btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(124,58,237,.4); }
.ptm-btn-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

/* Full Timeline in popup */
.ptm-timeline-popup { padding: 0; }
.ptm-tl-item {
    display: grid;
    grid-template-columns: 82px 1fr auto;
    gap: 10px; align-items: start;
    padding: 10px 0;
    border-bottom: 1px solid rgba(99,102,241,.1);
}
.ptm-tl-item:last-child { border-bottom: none; }
.ptm-tl-date { font-size: 11px; font-weight: 700; color: #60a5fa; white-space: nowrap; padding-top: 2px; }
.ptm-tl-desc { font-size: 12px; color: #d1d5db; line-height: 1.5; }
.ptm-tl-day { font-size: 10.5px; font-weight: 800; color: #a78bfa; white-space: nowrap; background: rgba(99,102,241,.12); padding: 2px 8px; border-radius: 99px; }
.ptm-tl-empty { text-align: center; padding: 30px; color: #64748b; font-size: 13px; }
.ptm-tl-completion { background: rgba(16,185,129,.06); border-radius: 8px; padding: 6px 10px; }
.ptm-tl-completion .ptm-tl-desc { color: #34d399; font-weight: 600; }

@media (max-width: 767px) {
    .ptm-hero { padding: 12px 14px }
    .ptm-timeline::before { left: 26px }
    .ptm-day { padding-left: 32px }
    .ptm-day-dot { left: 4px }
    .ptm-tl-item { grid-template-columns: 72px 1fr; }
    .ptm-tl-day { grid-column: 2; margin-top: -4px; }
}
</style>

<!-- ══ PAGE HERO ══ -->
<div class="ptm-hero">
    <div>
        <h2><i class="fas fa-clipboard-list"></i> Task Manager</h2>
        <p>Track your daily project progress</p>
    </div>
    <button class="ptm-btn-create" onclick="openModal('createModal')">
        <i class="fas fa-plus"></i> New Project
    </button>
</div>

<!-- ══ OVERDUE WARNING ══ -->
<?php if ($has_overdue && !empty($active_projects)): ?>
<div class="ptm-overdue-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <span>⚠️ Overdue — You have active project(s) with no update submitted today. Please add today's progress.</span>
</div>
<?php endif; ?>

<!-- ══ ACTIVE PROJECTS ══ -->
<?php if (!empty($active_projects)): ?>
    <?php foreach ($active_projects as $p):
        $isOverdue = !$p['has_today_log'];
    ?>
    <div class="ptm-project <?= $isOverdue ? 'overdue-card' : '' ?>" id="project-<?= $p['id'] ?>">
        <div class="ptm-project-header">
            <div>
                <div class="ptm-project-title">
                    <i class="fas fa-folder-open"></i><?= htmlspecialchars($p['project_name']) ?>
                    <?php if ($isOverdue): ?>
                        <span class="ptm-overdue-tag"><i class="fas fa-clock"></i> No update today</span>
                    <?php endif; ?>
                </div>
                <div class="ptm-project-meta">
                    Started <?= date('d M Y', strtotime($p['start_date'])) ?> · <?= $p['total_days'] ?> day(s) logged
                    <span class="ptm-badge ptm-badge-active" style="margin-left:6px"><i class="fas fa-circle" style="font-size:6px"></i> Active</span>
                </div>
            </div>
            <div class="ptm-project-actions">
                <button class="ptm-btn-day" onclick="openAddDayModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>', '<?= $p['has_today_log'] ? '1' : '0' ?>')">
                    <i class="fas fa-plus"></i> <?= $p['has_today_log'] ? 'Update Today' : 'Add Today\'s Update' ?>
                </button>
                <button class="ptm-btn-complete" onclick="completeProject(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">
                    <i class="fas fa-check-circle"></i> Mark Complete
                </button>
            </div>
        </div>

        <!-- ══ ACTIVITY PANEL — last 3 logs ══ -->
        <div class="ptm-activity-panel">
            <div class="ptm-activity-title">
                <span><i class="fas fa-history" style="margin-right:5px;color:#818cf8"></i>Recent Activity</span>
            </div>
            <?php if (empty($p['last3logs'])): ?>
                <div style="font-size:11.5px;color:#64748b;padding:4px 0;">No activity yet. Add your first daily update!</div>
            <?php else: ?>
                <?php foreach ($p['last3logs'] as $log): ?>
                <div class="ptm-activity-row">
                    <span class="ptm-act-date"><?= date('d-m-Y', strtotime($log['log_date'])) ?></span>
                    <span class="ptm-act-desc"><?= htmlspecialchars(mb_strimwidth($log['work_description'], 0, 90, '…')) ?></span>
                    <span class="ptm-act-day">Day <?= $log['day_number'] ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <button class="ptm-btn-full-update" onclick="openFullTimeline(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">
                <i class="fas fa-list-ul"></i> Show Full Update
            </button>
        </div>

        <!-- ══ DETAILED LOGS (edit/delete) ══ -->
        <?php
        // Fetch all logs for edit/delete section
        $all_logs = $conn->query("SELECT * FROM project_daily_logs WHERE project_id = {$p['id']} ORDER BY log_date DESC");
        $logs_arr = [];
        if ($all_logs) while ($l = $all_logs->fetch_assoc()) $logs_arr[] = $l;
        ?>
        <div class="ptm-timeline">
            <?php if (empty($logs_arr)): ?>
                <div class="ptm-empty"><i class="fas fa-inbox"></i> No daily logs yet. Click "Add Today's Update" to start!</div>
            <?php else: ?>
                <?php foreach ($logs_arr as $log): ?>
                <div class="ptm-day" id="log-<?= $log['id'] ?>">
                    <div class="ptm-day-dot"><?= $log['day_number'] ?></div>
                    <div class="ptm-day-body">
                        <div class="ptm-day-header">
                            <span class="ptm-day-title">Day <?= $log['day_number'] ?></span>
                            <span class="ptm-day-date"><i class="fas fa-calendar-alt"></i> <?= date('d M Y', strtotime($log['log_date'])) ?></span>
                        </div>
                        <div class="ptm-day-text"><?= nl2br(htmlspecialchars($log['work_description'])) ?></div>
                        <div class="ptm-day-actions">
                            <button class="ptm-day-edit" onclick="openEditModal(<?= $log['id'] ?>, <?= htmlspecialchars(json_encode($log['work_description'])) ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="ptm-day-del" onclick="deleteDay(<?= $log['id'] ?>)">
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
<?php endif; ?>

<?php if (empty($active_projects) && empty($past_projects)): ?>
<div class="ptm-no-projects">
    <i class="fas fa-clipboard-list"></i>
    <div style="font-size:15px;font-weight:700;margin-bottom:4px;color:var(--text-primary,#fff)">No Projects Yet</div>
    <div style="font-size:12px;color:var(--text-dim,#94a3b8);margin-bottom:14px">Start tracking your daily progress by creating your first project!</div>
    <button class="ptm-btn-create" onclick="openModal('createModal')">
        <i class="fas fa-plus"></i> Create First Project
    </button>
</div>
<?php endif; ?>

<!-- ══ PAST PROJECTS ══ -->
<?php if (!empty($past_projects)): ?>
<div class="ptm-past-title"><i class="fas fa-history" style="color:#a78bfa"></i> Past Projects</div>
<?php foreach ($past_projects as $cp): ?>
<div class="ptm-past-card">
    <div>
        <div style="font-weight:700;font-size:13px;color:var(--text-primary,#fff)"><?= htmlspecialchars($cp['project_name']) ?></div>
        <div style="font-size:10.5px;color:var(--text-dim,#94a3b8)">
            <?= date('d M Y', strtotime($cp['start_date'])) ?>
            <?php if ($cp['completed_date']): ?> → <?= date('d M Y', strtotime($cp['completed_date'])) ?><?php endif; ?>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span style="font-weight:700;color:#818cf8;font-size:13px"><?= $cp['total_days'] ?> days</span>
        <span class="ptm-badge ptm-badge-done"><i class="fas fa-check"></i> Completed</span>
        <button class="ptm-btn-full-update" onclick="openFullTimeline(<?= $cp['id'] ?>, '<?= htmlspecialchars($cp['project_name'], ENT_QUOTES) ?>')">
            <i class="fas fa-list-ul"></i> View History
        </button>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>


<!-- ══════════════════════════════════════════
     MODALS
══════════════════════════════════════════ -->

<!-- CREATE PROJECT MODAL -->
<div class="ptm-modal-overlay" id="createModal">
    <div class="ptm-modal-box">
        <div class="ptm-modal-header">
            <h5><i class="fas fa-folder-plus"></i> New Project</h5>
            <button class="ptm-modal-close" onclick="closeModal('createModal')">×</button>
        </div>
        <div class="ptm-modal-body">
            <div class="ptm-form-group">
                <label class="ptm-form-label">Project Name *</label>
                <input type="text" class="ptm-form-control" id="createName" placeholder="e.g., E-Commerce Website">
            </div>
            <div class="ptm-form-group">
                <label class="ptm-form-label">Description</label>
                <textarea class="ptm-form-control" id="createDesc" rows="3" placeholder="Brief description of your project..."></textarea>
            </div>
            <div class="ptm-form-group">
                <label class="ptm-form-label">Start Date</label>
                <input type="text" class="ptm-form-control" value="<?= date('d M Y') ?>" readonly style="opacity:.65;cursor:default;">
                <div class="ptm-form-hint">Auto-set to today</div>
            </div>
        </div>
        <div class="ptm-modal-footer">
            <button class="ptm-btn-cancel" onclick="closeModal('createModal')">Cancel</button>
            <button class="ptm-btn-submit" id="btnCreateSubmit" onclick="submitCreate()"><i class="fas fa-plus"></i> Create Project</button>
        </div>
    </div>
</div>

<!-- ADD DAY MODAL -->
<div class="ptm-modal-overlay" id="addDayModal">
    <div class="ptm-modal-box">
        <div class="ptm-modal-header">
            <h5><i class="fas fa-calendar-plus"></i> <span id="addDayModalTitle">Add Today's Update</span></h5>
            <button class="ptm-modal-close" onclick="closeModal('addDayModal')">×</button>
        </div>
        <div class="ptm-modal-body">
            <input type="hidden" id="addDayProjId">
            <p style="font-size:12px;color:#94a3b8;margin-bottom:12px" id="addDayInfo"></p>
            <div class="ptm-form-group">
                <label class="ptm-form-label">What did you work on today? *</label>
                <textarea class="ptm-form-control" id="addDayWork" rows="5" placeholder="Describe your progress in detail..."></textarea>
            </div>
        </div>
        <div class="ptm-modal-footer">
            <button class="ptm-btn-cancel" onclick="closeModal('addDayModal')">Cancel</button>
            <button class="ptm-btn-submit" id="btnAddDaySubmit" onclick="submitAddDay()"><i class="fas fa-save"></i> Save Update</button>
        </div>
    </div>
</div>

<!-- EDIT DAY MODAL -->
<div class="ptm-modal-overlay" id="editDayModal">
    <div class="ptm-modal-box">
        <div class="ptm-modal-header">
            <h5><i class="fas fa-edit"></i> Edit Log</h5>
            <button class="ptm-modal-close" onclick="closeModal('editDayModal')">×</button>
        </div>
        <div class="ptm-modal-body">
            <input type="hidden" id="editDayId">
            <div class="ptm-form-group">
                <label class="ptm-form-label">Work Description</label>
                <textarea class="ptm-form-control" id="editDayWork" rows="5"></textarea>
            </div>
        </div>
        <div class="ptm-modal-footer">
            <button class="ptm-btn-cancel" onclick="closeModal('editDayModal')">Cancel</button>
            <button class="ptm-btn-submit" id="btnEditSubmit" onclick="submitEditDay()"><i class="fas fa-save"></i> Update</button>
        </div>
    </div>
</div>

<!-- FULL TIMELINE POPUP MODAL -->
<div class="ptm-modal-overlay" id="timelineModal">
    <div class="ptm-modal-box wide">
        <div class="ptm-modal-header">
            <h5><i class="fas fa-list-ul"></i> <span id="timelineModalTitle">Project Timeline</span></h5>
            <button class="ptm-modal-close" onclick="closeModal('timelineModal')">×</button>
        </div>
        <div class="ptm-modal-body">
            <div id="timelineContent">
                <div style="text-align:center;padding:30px;color:#64748b"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
        <div class="ptm-modal-footer">
            <button class="ptm-btn-cancel" onclick="closeModal('timelineModal')">Close</button>
        </div>
    </div>
</div>


<!-- COMPLETE PROJECT MODAL (requires project link) -->
<div class="ptm-modal-overlay" id="completeProjectModal">
    <div class="ptm-modal-box">
        <div class="ptm-modal-header">
            <h5><i class="fas fa-check-circle"></i> <span id="completeProjTitle">Complete Project</span></h5>
            <button class="ptm-modal-close" onclick="closeModal('completeProjectModal')">×</button>
        </div>
        <div class="ptm-modal-body">
            <input type="hidden" id="completeProjId">
            <p style="font-size:12px;color:#94a3b8;margin-bottom:14px">
                <i class="fas fa-info-circle" style="color:#818cf8;margin-right:4px"></i>
                Enter your project link below. This is <strong>required</strong> to mark the project as completed. 
                Your project will appear in your Projects portfolio page.
            </p>
            <div class="ptm-form-group">
                <label class="ptm-form-label">Project Link *</label>
                <input type="url" class="ptm-form-control" id="completeProjLink" placeholder="https://github.com/your-project" required>
                <div class="ptm-form-hint">GitHub, live URL, or CodePen link</div>
            </div>
        </div>
        <div class="ptm-modal-footer">
            <button class="ptm-btn-cancel" onclick="closeModal('completeProjectModal')">Cancel</button>
            <button class="ptm-btn-submit" id="btnCompleteSubmit" onclick="submitCompleteProject()" style="background:linear-gradient(135deg,#059669,#10b981)">
                <i class="fas fa-check-circle"></i> Complete Project
            </button>
        </div>
    </div>
</div>

<script>
// ───────────────────────────────────────────────
// Modal helpers
// ───────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}
// Close modal on overlay click
document.querySelectorAll('.ptm-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ptm-modal-overlay.active').forEach(m => closeModal(m.id));
    }
});

function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    btn.style.opacity = loading ? '.55' : '1';
}

// ───────────────────────────────────────────────
// Create Project
// ───────────────────────────────────────────────
function openCreateModal() { openModal('createModal'); }

function submitCreate() {
    const name = document.getElementById('createName').value.trim();
    const desc = document.getElementById('createDesc').value.trim();
    if (!name) { alert('Project name is required'); return; }

    setLoading('btnCreateSubmit', true);
    const fd = new FormData();
    fd.append('action', 'create_project');
    fd.append('project_name', name);
    fd.append('description', desc);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else { alert(d.message); setLoading('btnCreateSubmit', false); }
        })
        .catch(() => { alert('Network error. Please try again.'); setLoading('btnCreateSubmit', false); });
}

// ───────────────────────────────────────────────
// Add Day (Update Today Work)
// ───────────────────────────────────────────────
function openAddDayModal(projId, projName, hasToday) {
    document.getElementById('addDayProjId').value = projId;
    document.getElementById('addDayInfo').textContent = 'Project: ' + projName;
    document.getElementById('addDayWork').value = '';
    const titleEl = document.getElementById('addDayModalTitle');
    if (hasToday === '1') {
        titleEl.textContent = 'Update Today\'s Progress';
        document.getElementById('addDayInfo').textContent = 'Project: ' + projName + ' · Note: You already have an entry for today. Editing the existing log is recommended — but you can add a supplementary note.';
    } else {
        titleEl.textContent = "Add Today's Update";
        document.getElementById('addDayInfo').textContent = 'Project: ' + projName + ' · Today: <?= date('d M Y') ?>';
    }
    openModal('addDayModal');
}

function submitAddDay() {
    const projId = document.getElementById('addDayProjId').value;
    const work   = document.getElementById('addDayWork').value.trim();
    if (!work) { alert('Please describe your work'); return; }

    setLoading('btnAddDaySubmit', true);
    const fd = new FormData();
    fd.append('action', 'add_day');
    fd.append('project_id', projId);
    fd.append('work_description', work);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else { alert(d.message); setLoading('btnAddDaySubmit', false); }
        })
        .catch(() => { alert('Network error. Please try again.'); setLoading('btnAddDaySubmit', false); });
}

// ───────────────────────────────────────────────
// Edit Day
// ───────────────────────────────────────────────
function openEditModal(logId, workText) {
    document.getElementById('editDayId').value = logId;
    document.getElementById('editDayWork').value = workText;
    openModal('editDayModal');
}

function submitEditDay() {
    const logId = document.getElementById('editDayId').value;
    const work  = document.getElementById('editDayWork').value.trim();
    if (!work) { alert('Description required'); return; }

    setLoading('btnEditSubmit', true);
    const fd = new FormData();
    fd.append('action', 'edit_day');
    fd.append('log_id', logId);
    fd.append('work_description', work);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else { alert(d.message); setLoading('btnEditSubmit', false); }
        })
        .catch(() => { alert('Network error.'); setLoading('btnEditSubmit', false); });
}

// ───────────────────────────────────────────────
// Delete Day
// ───────────────────────────────────────────────
function deleteDay(logId) {
    if (!confirm('Delete this day log?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_day');
    fd.append('log_id', logId);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert(d.message);
        });
}

// ───────────────────────────────────────────────
// Complete Project — show modal with project link
// ───────────────────────────────────────────────
function completeProject(projId, projName) {
    document.getElementById('completeProjId').value = projId;
    document.getElementById('completeProjTitle').textContent = 'Complete: ' + projName;
    document.getElementById('completeProjLink').value = '';
    openModal('completeProjectModal');
}

function submitCompleteProject() {
    const projId = document.getElementById('completeProjId').value;
    const link   = document.getElementById('completeProjLink').value.trim();
    
    if (!link) {
        alert('Project link is required to complete the project');
        document.getElementById('completeProjLink').focus();
        return;
    }

    // Client-side URL validation
    try {
        new URL(link);
    } catch (_) {
        alert('Please enter a valid URL (starting with http:// or https://)');
        document.getElementById('completeProjLink').focus();
        return;
    }

    // Prevent double submit
    const btn = document.getElementById('btnCompleteSubmit');
    if (btn.disabled) return;
    setLoading('btnCompleteSubmit', true);

    const fd = new FormData();
    fd.append('action', 'complete_project');
    fd.append('project_id', projId);
    fd.append('project_link', link);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('completeProjectModal');
                location.href = 'task_manager.php?msg=completed';
            } else {
                alert(d.message || 'Failed to complete project');
                setLoading('btnCompleteSubmit', false);
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            setLoading('btnCompleteSubmit', false);
        });
}

// ───────────────────────────────────────────────
// Full Timeline Popup
// ───────────────────────────────────────────────
function openFullTimeline(projId, projName) {
    document.getElementById('timelineModalTitle').textContent = projName + ' — Full Timeline';
    document.getElementById('timelineContent').innerHTML =
        '<div style="text-align:center;padding:30px;color:#64748b"><i class="fas fa-spinner fa-spin" style="font-size:20px;margin-bottom:8px;display:block"></i>Loading...</div>';
    openModal('timelineModal');

    const fd = new FormData();
    fd.append('action', 'get_project_logs');
    fd.append('project_id', projId);

    fetch('ajax/project_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                document.getElementById('timelineContent').innerHTML =
                    '<div class="ptm-tl-empty"><i class="fas fa-exclamation-circle" style="color:#f87171"></i><br>' + (d.message || 'Failed to load') + '</div>';
                return;
            }
            if (!d.logs || d.logs.length === 0) {
                document.getElementById('timelineContent').innerHTML =
                    '<div class="ptm-tl-empty"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>No logs yet for this project.</div>';
                return;
            }

            const startFmt = formatDateStr(d.start_date);
            let html = '<div class="ptm-timeline-popup">';
            html += `<div style="font-size:11px;color:#64748b;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(99,102,241,.1)">
                <i class="fas fa-calendar" style="margin-right:5px;color:#818cf8"></i>
                Started: ${startFmt} &nbsp;·&nbsp;
                <strong style="color:#a78bfa">${d.logs.length}</strong> log entries
            </div>`;

            d.logs.forEach(log => {
                const isCompletion = log.work_description.startsWith('[COMPLETED]');
                const rowClass = isCompletion ? 'ptm-tl-item ptm-tl-completion' : 'ptm-tl-item';
                const desc = isCompletion
                    ? '<i class="fas fa-check-circle" style="margin-right:5px"></i>' + escHtml(log.work_description.replace('[COMPLETED]','').trim())
                    : escHtml(log.work_description);
                html += `<div class="${rowClass}">
                    <div class="ptm-tl-date">${log.log_date_display}</div>
                    <div class="ptm-tl-desc">${desc}</div>
                    <div class="ptm-tl-day">Day ${log.day_number}</div>
                </div>`;
            });
            html += '</div>';
            document.getElementById('timelineContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('timelineContent').innerHTML = '<div class="ptm-tl-empty" style="color:#f87171">Failed to load. Please try again.</div>';
        });
}

function formatDateStr(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
