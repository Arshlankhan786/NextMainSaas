<?php
include 'includes/header.php';

// Permission check
if (!$canManageEvents) {
    header('Location: index.php');
    exit;
}

// ── Get event ID ──
$event_id = (int)($_GET['id'] ?? 0);
if ($event_id <= 0) {
    header('Location: events.php');
    exit;
}

// ── Fetch event ──
$stmt = $conn->prepare("SELECT e.*, a.full_name as creator_name 
                         FROM events e 
                         LEFT JOIN admins a ON e.created_by = a.id 
                         WHERE e.id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header('Location: events.php');
    exit;
}

// ── Batch filter ──
$batch_filter = isset($_GET['batch']) ? trim($_GET['batch']) : '';

// ── Fetch students with participation status ──
$student_where = "s.status = 'Active' AND s.login_enabled = 1";
$student_params = [$event_id, $event['event_date']];
$student_types = "is";

if (!empty($batch_filter) && in_array($batch_filter, ['Morning', 'Evening'])) {
    $student_where .= " AND s.batch = ?";
    $student_params[] = $batch_filter;
    $student_types .= "s";
}

$stmt = $conn->prepare("SELECT s.id, s.full_name, s.student_code, s.batch, s.photo,
                                ep.status as explicit_status, ep.participated_at,
                                ep_admin.full_name as marked_by_name,
                                sa.status as attendance_status
                         FROM students s
                         LEFT JOIN event_participants ep ON s.id = ep.student_id AND ep.event_id = ?
                         LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.attendance_date = ?
                         LEFT JOIN admins ep_admin ON ep.marked_by = ep_admin.id
                         WHERE $student_where
                         ORDER BY s.full_name ASC");
$stmt->bind_param($student_types, ...$student_params);
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
$is_past = ($event['status'] === 'Completed' || strtotime($event['event_date']) < strtotime(date('Y-m-d')));

while ($row = $students_result->fetch_assoc()) {
    // ── Resolve participation status ──
    if (!empty($row['explicit_status'])) {
        $row['participation_status'] = $row['explicit_status'];
    } elseif ($is_past) {
        if ($row['attendance_status'] === 'Present') {
            $row['participation_status'] = 'Participated';
            $row['marked_by_name'] = 'System (Attendance)';
        } elseif ($row['attendance_status'] === 'Absent') {
            $row['participation_status'] = 'Not Participated';
            $row['marked_by_name'] = 'System (Attendance)';
        } else {
            $row['participation_status'] = 'Not Marked';
        }
    } else {
        $row['participation_status'] = 'Not Marked';
    }
    
    $students[] = $row;
}
$stmt->close();

// ── Participation stats ──
$total_students = count($students);
$participated_count = 0;
$not_participated_count = 0;
foreach ($students as $s) {
    if ($s['participation_status'] === 'Participated') $participated_count++;
    elseif ($s['participation_status'] === 'Not Participated') $not_participated_count++;
}
$not_marked_count = $total_students - $participated_count - $not_participated_count;
$participation_pct = $total_students > 0 ? round(($participated_count / $total_students) * 100) : 0;
?>

<style>
/* ── Event Details styles ── */
.event-detail-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    box-shadow: var(--sh-sm);
    margin-bottom: 24px;
}
.event-detail-header {
    display: flex; align-items: center; gap: 16px; margin-bottom: 20px;
}
.event-detail-icon {
    width: 56px; height: 56px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
    background: var(--indigo-100); color: var(--indigo-600);
}
.event-detail-title { font-size: 22px; font-weight: 800; color: var(--text); margin: 0; }
.event-detail-meta { font-size: 12px; color: var(--muted); font-weight: 500; margin-top: 2px; }
.event-detail-meta i { margin-right: 4px; width: 14px; text-align: center; }

.participation-stats {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px; margin-bottom: 24px;
}
.pstat-card {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 16px; text-align: center;
}
.pstat-value { font-size: 28px; font-weight: 800; line-height: 1; }
.pstat-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

.progress-bar-custom {
    height: 10px; background: #e9ecef; border-radius: 99px; overflow: hidden; margin-bottom: 20px;
}
.progress-bar-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, var(--indigo-600), var(--accent));
    transition: width 0.6s ease;
}

.student-participation-table { margin-top: 12px; }
.student-participation-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }

.student-row { transition: background 0.2s; }
.student-row:hover { background: rgba(99,102,241,0.03); }

.student-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--indigo-100); color: var(--indigo-600);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; flex-shrink: 0;
    overflow: hidden;
}
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }

.participation-toggle {
    display: inline-flex; border-radius: 8px; overflow: hidden; border: 1px solid var(--border);
}
.participation-toggle .pt-btn {
    padding: 5px 14px; font-size: 11px; font-weight: 700; border: none;
    cursor: pointer; transition: all 0.2s; background: var(--bg); color: var(--muted);
}
.participation-toggle .pt-btn.active-participated { background: #059669; color: #fff; }
.participation-toggle .pt-btn.active-not-participated { background: var(--crimson); color: #fff; }
.participation-toggle .pt-btn:hover:not(.active-participated):not(.active-not-participated) { background: rgba(99,102,241,0.08); }

.bulk-actions {
    display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
    padding: 12px 16px; background: rgba(99,102,241,0.04); border: 1px solid rgba(99,102,241,0.12);
    border-radius: var(--radius); flex-wrap: wrap;
}
.bulk-actions label { font-size: 12px; font-weight: 700; color: var(--muted); }

.event-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.event-badge-upcoming { background: rgba(16,185,129,0.12); color: #059669; }
.event-badge-completed { background: rgba(99,102,241,0.12); color: var(--indigo-600); }
.event-badge-cancelled { background: rgba(220,38,38,0.1); color: var(--crimson); }

@media(max-width:768px) {
    .participation-stats { grid-template-columns: repeat(2, 1fr); }
    .event-detail-header { flex-direction: column; align-items: start; }
    .bulk-actions { flex-direction: column; align-items: stretch; }
}
</style>

<!-- Breadcrumb -->
<nav style="font-size:12px;margin-bottom:20px;">
    <a href="events.php" style="color:var(--accent);text-decoration:none;font-weight:600;">
        <i class="fas fa-arrow-left"></i> Back to Events
    </a>
</nav>

<!-- Event Detail Card -->
<div class="event-detail-card">
    <div class="event-detail-header">
        <div class="event-detail-icon">
            <i class="<?= htmlspecialchars($event['icon']) ?>"></i>
        </div>
        <div style="flex:1;">
            <h4 class="event-detail-title"><?= htmlspecialchars($event['title']) ?></h4>
            <div class="event-detail-meta">
                <span><i class="fas fa-calendar"></i> <?= date('d M Y (l)', strtotime($event['event_date'])) ?></span>
                <?php if (!empty($event['event_time'])): ?>
                <span style="margin-left:12px;"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($event['event_time'])) ?></span>
                <?php endif; ?>
                <span style="margin-left:12px;"><i class="fas fa-tag"></i> <?= htmlspecialchars($event['category']) ?></span>
                <span style="margin-left:12px;">
                    <?php
                    $badge_class = 'event-badge-upcoming';
                    if ($event['status'] === 'Completed') $badge_class = 'event-badge-completed';
                    elseif ($event['status'] === 'Cancelled') $badge_class = 'event-badge-cancelled';
                    ?>
                    <span class="event-badge <?= $badge_class ?>"><?= $event['status'] ?></span>
                </span>
            </div>
        </div>
        <a href="events.php" class="btn btn-sm btn-outline-purple" style="flex-shrink:0;">
            <i class="fas fa-list"></i> All Events
        </a>
    </div>
    <?php if (!empty($event['description'])): ?>
    <p style="font-size:13px;color:var(--muted);margin:0;line-height:1.7;">
        <?= nl2br(htmlspecialchars($event['description'])) ?>
    </p>
    <?php endif; ?>
    <div style="margin-top:12px;font-size:11px;color:var(--muted);">
        Created by <?= htmlspecialchars($event['creator_name'] ?? 'Unknown') ?> on <?= date('d M Y, h:i A', strtotime($event['created_at'])) ?>
    </div>
</div>

<!-- Participation Section -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 style="font-weight:800;color:var(--text);margin:0;">
        <i class="fas fa-users" style="color:var(--accent);"></i> Student Participation
    </h5>
    <div class="d-flex gap-2">
        <a href="?id=<?= $event_id ?>" class="btn btn-sm <?= empty($batch_filter) ? 'btn-purple' : 'btn-outline-purple' ?>">All</a>
        <a href="?id=<?= $event_id ?>&batch=Morning" class="btn btn-sm <?= $batch_filter==='Morning' ? 'btn-purple' : 'btn-outline-purple' ?>">Morning</a>
        <a href="?id=<?= $event_id ?>&batch=Evening" class="btn btn-sm <?= $batch_filter==='Evening' ? 'btn-purple' : 'btn-outline-purple' ?>">Evening</a>
    </div>
</div>

<!-- Participation Stats -->
<div class="participation-stats">
    <div class="pstat-card">
        <div class="pstat-value" style="color:var(--text);"><?= $total_students ?></div>
        <div class="pstat-label">Total Students</div>
    </div>
    <div class="pstat-card">
        <div class="pstat-value" style="color:#059669;"><?= $participated_count ?></div>
        <div class="pstat-label">Participated</div>
    </div>
    <div class="pstat-card">
        <div class="pstat-value" style="color:var(--crimson);"><?= $not_participated_count ?></div>
        <div class="pstat-label">Not Participated</div>
    </div>
    <div class="pstat-card">
        <div class="pstat-value" style="color:var(--accent);"><?= $participation_pct ?>%</div>
        <div class="pstat-label">Participation Rate</div>
    </div>
</div>

<!-- Progress Bar -->
<div class="progress-bar-custom">
    <div class="progress-bar-fill" style="width: <?= $participation_pct ?>%;"></div>
</div>

<!-- Bulk Actions -->
<div class="bulk-actions">
    <label><input type="checkbox" id="selectAllStudents" onchange="toggleSelectAll(this)"> Select All</label>
    <button class="btn btn-sm btn-purple" onclick="bulkMark('Participated')">
        <i class="fas fa-check"></i> Mark Participated
    </button>
    <button class="btn btn-sm" style="color:var(--crimson);border:1px solid rgba(220,38,38,0.2);" onclick="bulkMark('Not Participated')">
        <i class="fas fa-times"></i> Mark Not Participated
    </button>
    <span id="selectedCount" style="font-size:11px;color:var(--muted);font-weight:600;">0 selected</span>
</div>

<!-- Student Table -->
<div class="table-card">
    <div class="table-responsive student-participation-table">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Student</th>
                    <th>Code</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Marked At</th>
                    <th style="width:220px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4" style="color:var(--muted);">
                        No active students found<?= !empty($batch_filter) ? " for $batch_filter batch" : '' ?>.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($students as $s): ?>
                <tr class="student-row" id="row-<?= $s['id'] ?>">
                    <td>
                        <input type="checkbox" class="student-check" value="<?= $s['id'] ?>" onchange="updateSelectedCount()">
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="student-avatar">
                                <?php if (!empty($s['profile_image'])): ?>
                                <img src="../<?= htmlspecialchars($s['profile_image']) ?>" alt="">
                                <?php else: ?>
                                <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <span style="font-weight:600;font-size:13px;"><?= htmlspecialchars($s['full_name']) ?></span>
                        </div>
                    </td>
                    <td><span style="font-size:12px;color:var(--muted);font-weight:600;"><?= htmlspecialchars($s['student_code']) ?></span></td>
                    <td><span style="font-size:12px;"><?= htmlspecialchars($s['batch'] ?? '—') ?></span></td>
                    <td id="status-<?= $s['id'] ?>">
                        <?php if ($s['participation_status'] === 'Participated'): ?>
                        <span class="event-badge event-badge-upcoming"><i class="fas fa-check"></i> Participated</span>
                        <?php elseif ($s['participation_status'] === 'Not Participated'): ?>
                        <span class="event-badge event-badge-cancelled"><i class="fas fa-times"></i> Not Participated</span>
                        <?php else: ?>
                        <span class="event-badge" style="background:rgba(0,0,0,0.05);color:var(--muted);">Not Marked</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-size:11px;color:var(--muted);">
                            <?= $s['participated_at'] ? date('d M, h:i A', strtotime($s['participated_at'])) : '—' ?>
                        </span>
                    </td>
                    <td>
                        <div class="participation-toggle">
                            <button class="pt-btn <?= $s['participation_status']==='Participated' ? 'active-participated' : '' ?>"
                                    onclick="markSingle(<?= $event_id ?>, <?= $s['id'] ?>, 'Participated', this)">
                                <i class="fas fa-check"></i> Yes
                            </button>
                            <button class="pt-btn <?= $s['participation_status']==='Not Participated' ? 'active-not-participated' : '' ?>"
                                    onclick="markSingle(<?= $event_id ?>, <?= $s['id'] ?>, 'Not Participated', this)">
                                <i class="fas fa-times"></i> No
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function markSingle(eventId, studentId, status, btn) {
    var toggle = btn.closest('.participation-toggle');
    toggle.querySelectorAll('.pt-btn').forEach(b => {
        b.classList.remove('active-participated', 'active-not-participated');
    });
    
    btn.classList.add(status === 'Participated' ? 'active-participated' : 'active-not-participated');
    
    var formData = new FormData();
    formData.append('action', 'mark_participation');
    formData.append('event_id', eventId);
    formData.append('student_id', studentId);
    formData.append('status', status);
    
    fetch('ajax/manage_events.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update status cell
                var statusCell = document.getElementById('status-' + studentId);
                if (status === 'Participated') {
                    statusCell.innerHTML = '<span class="event-badge event-badge-upcoming"><i class="fas fa-check"></i> Participated</span>';
                } else {
                    statusCell.innerHTML = '<span class="event-badge event-badge-cancelled"><i class="fas fa-times"></i> Not Participated</span>';
                }
            } else {
                alert(data.error || 'Failed to update.');
            }
        })
        .catch(() => alert('Network error.'));
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.student-check').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    var count = document.querySelectorAll('.student-check:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selected';
}

function bulkMark(status) {
    var checked = document.querySelectorAll('.student-check:checked');
    if (checked.length === 0) {
        alert('Please select at least one student.');
        return;
    }
    
    var ids = [];
    checked.forEach(cb => ids.push(parseInt(cb.value)));
    
    if (!confirm('Mark ' + ids.length + ' student(s) as "' + status + '"?')) return;
    
    var formData = new FormData();
    formData.append('action', 'bulk_mark_participation');
    formData.append('event_id', <?= $event_id ?>);
    formData.append('student_ids', JSON.stringify(ids));
    formData.append('status', status);
    
    fetch('ajax/manage_events.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to update.');
            }
        })
        .catch(() => alert('Network error.'));
}
</script>

<?php include 'includes/footer.php'; ?>
