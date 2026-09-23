<?php
require_once '../admin/config/database.php';
require_once './student_auth.php';
requireStudentLogin();

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$student = getCurrentStudent();
$sid = (int)$student['id'];

include 'includes/header.php';

// ── Month filter ──
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$current_month_label = date('F Y', strtotime($filter_month . '-01'));

// ── Upcoming events (from today forward, max 10) ──
$today = date('Y-m-d');
$upcoming_stmt = $conn->prepare("SELECT e.*, 
                                        ep.status as my_status
                                 FROM events e
                                 LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.student_id = ?
                                 WHERE e.event_date >= ? AND e.status = 'Upcoming'
                                 ORDER BY e.event_date ASC, e.event_time ASC
                                 LIMIT 10");
$upcoming_stmt->bind_param("is", $sid, $today);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_events = [];
while ($row = $upcoming_result->fetch_assoc()) {
    $upcoming_events[] = $row;
}
$upcoming_stmt->close();

// ── Activity history for selected month ──
$history_stmt = $conn->prepare("SELECT e.id, e.title, e.event_date, e.event_time, e.category, e.icon, e.status as event_status,
                                       ep.status as explicit_status, ep.participated_at,
                                       sa.status as attendance_status
                                FROM events e
                                LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.student_id = ?
                                LEFT JOIN student_attendance sa ON sa.attendance_date = e.event_date AND sa.student_id = ?
                                WHERE DATE_FORMAT(e.event_date, '%Y-%m') = ?
                                AND e.status != 'Cancelled'
                                ORDER BY e.event_date ASC");
$history_stmt->bind_param("iis", $sid, $sid, $filter_month);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history_events = [];
$total_month_events = 0;
$participated_month = 0;
$not_participated_month = 0;

while ($row = $history_result->fetch_assoc()) {
    $is_past = ($row['event_status'] === 'Completed' || strtotime($row['event_date']) < strtotime(date('Y-m-d')));
    $is_sunday = (date('N', strtotime($row['event_date'])) == 7);

    // ── Resolve attendance display (mirrors existing attendance page logic) ──
    if ($is_sunday) {
        $row['resolved_attendance'] = 'Sunday';
    } elseif (!empty($row['attendance_status'])) {
        // Explicit attendance record exists
        if (in_array($row['attendance_status'], ['Present', 'Holiday'])) {
            $row['resolved_attendance'] = 'Present';
        } else {
            $row['resolved_attendance'] = 'Absent';
        }
    } elseif ($is_past) {
        // No attendance record on a past non-Sunday date = Absent
        $row['resolved_attendance'] = 'Absent';
    } else {
        // Future date with no record
        $row['resolved_attendance'] = 'Not Marked';
    }

    // ── Resolve participation status ──
    if (!empty($row['explicit_status'])) {
        $row['participation_status'] = $row['explicit_status'];
    } elseif ($is_past) {
        if ($row['attendance_status'] === 'Present') {
            $row['participation_status'] = 'Participated';
        } elseif ($row['attendance_status'] === 'Absent') {
            $row['participation_status'] = 'Not Participated';
        } else {
            $row['participation_status'] = 'Not Marked';
        }
    } else {
        $row['participation_status'] = 'Not Marked';
    }
    
    $total_month_events++;
    if ($row['participation_status'] === 'Participated') $participated_month++;
    elseif ($row['participation_status'] === 'Not Participated') $not_participated_month++;
    
    $history_events[] = $row;
}
$history_stmt->close();

// ── Participation summary stats (calculated from loop above) ──
$participation_rate = $total_month_events > 0 ? round(($participated_month / $total_month_events) * 100) : 0;

// ── Months with events for navigation ──
$months_stmt = $conn->prepare("SELECT DISTINCT DATE_FORMAT(e.event_date, '%Y-%m') as ym,
                                       DATE_FORMAT(e.event_date, '%M %Y') as label
                               FROM events e
                               WHERE e.status != 'Cancelled'
                               ORDER BY ym DESC
                               LIMIT 12");
$months_stmt->execute();
$months_result = $months_stmt->get_result();
$available_months = [];
while ($m = $months_result->fetch_assoc()) {
    $available_months[] = $m;
}
$months_stmt->close();
?>

<style>
/* ── Activities Page Styles (student dark theme) ── */
.activities-section-title {
    font-size: 16px; font-weight: 800; color: var(--text);
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 16px;
}
.activities-section-title i { color: var(--purple-light); }

/* Upcoming event cards */
.upcoming-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px; margin-bottom: 32px;
}
.upcoming-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative; overflow: hidden;
}
.upcoming-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--purple), var(--indigo));
    opacity: 0.6;
}
.upcoming-card:hover {
    transform: translateY(-4px);
    border-color: rgba(124,58,237,0.3);
    box-shadow: 0 8px 30px rgba(124,58,237,0.12);
}
.upcoming-card-header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
}
.upcoming-card-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
    background: rgba(124,58,237,0.15); color: var(--purple-light);
    border: 1px solid rgba(124,58,237,0.2);
}
.upcoming-card-title { font-size: 14px; font-weight: 700; color: var(--text); }
.upcoming-card-date { font-size: 11px; color: var(--text-muted); font-weight: 600; }
.upcoming-card-desc { font-size: 12px; color: var(--text-muted); line-height: 1.6; margin-top: 6px; }
.upcoming-card-chips {
    display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px;
}
.upcoming-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 6px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
    background: rgba(124,58,237,0.1); color: var(--purple-light);
    border: 1px solid rgba(124,58,237,0.15);
}
.upcoming-chip-date {
    background: rgba(6,182,212,0.1); color: var(--cyan);
    border-color: rgba(6,182,212,0.15);
}

/* Stats row */
.activity-stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px; margin-bottom: 20px;
}
.activity-stat {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px; text-align: center;
}
.activity-stat-value { font-size: 24px; font-weight: 800; line-height: 1; }
.activity-stat-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

/* Month filter */
.month-filter-bar {
    display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;
}
.month-filter-bar .form-control {
    width: auto; min-width: 160px; background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); font-size: 13px; padding: 7px 12px; border-radius: 8px;
}
.month-filter-bar .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 2px rgba(124,58,237,0.2); }

.month-nav-chips {
    display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px;
}
.month-nav-chip {
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; font-weight: 600;
    background: var(--surface); border: 1px solid var(--border);
    color: var(--text-muted); text-decoration: none;
    transition: all 0.2s;
}
.month-nav-chip:hover { border-color: var(--purple); color: var(--purple-light); }
.month-nav-chip.active { background: var(--purple); color: #fff; border-color: var(--purple); }

/* History table */
.history-table {
    width: 100%; border-collapse: separate; border-spacing: 0;
}
.history-table th {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
    color: var(--text-muted); padding: 10px 14px;
    border-bottom: 1px solid var(--border);
}
.history-table td {
    padding: 12px 14px; border-bottom: 1px solid var(--border);
    font-size: 13px; color: var(--text);
}
.history-table tr:hover td { background: var(--surface); }
.history-table tr:last-child td { border-bottom: none; }

.participation-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px; border-radius: 6px;
    font-size: 11px; font-weight: 700;
}
.participation-badge.participated { background: var(--green-glow); color: var(--green); }
.participation-badge.not-participated { background: var(--red-glow); color: var(--red); }
.participation-badge.not-marked { background: var(--surface2); color: var(--text-dim); }

.attendance-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px; border-radius: 6px;
    font-size: 11px; font-weight: 700;
}
.attendance-badge.present { background: var(--green-glow); color: var(--green); }
.attendance-badge.absent { background: var(--red-glow); color: var(--red); }
.attendance-badge.att-not-marked { background: var(--surface2); color: var(--text-dim); }

.history-icon {
    width: 30px; height: 30px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
    background: rgba(124,58,237,0.12); color: var(--purple-light);
}

/* Empty state */
.empty-activities {
    text-align: center; padding: 40px 20px; color: var(--text-muted);
}
.empty-activities i { font-size: 48px; opacity: 0.2; margin-bottom: 12px; }

/* Progress bar */
.activity-progress { height: 6px; background: var(--surface2); border-radius: 99px; overflow: hidden; margin-bottom: 20px; }
.activity-progress-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--purple), var(--green)); transition: width 0.6s ease; }

@media(max-width:576px) {
    .upcoming-grid { grid-template-columns: 1fr; }
    .activity-stats-row { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- Upcoming Events -->
<?php if (!empty($upcoming_events)): ?>
<div style="margin-bottom:32px;">
    <div class="activities-section-title">
        <i class="fas fa-bolt"></i> Upcoming Activities
    </div>
    <div class="upcoming-grid">
        <?php foreach ($upcoming_events as $evt): ?>
        <div class="upcoming-card">
            <div class="upcoming-card-header">
                <div class="upcoming-card-icon">
                    <i class="<?= htmlspecialchars($evt['icon']) ?>"></i>
                </div>
                <div>
                    <div class="upcoming-card-title"><?= htmlspecialchars($evt['title']) ?></div>
                    <div class="upcoming-card-date"><?= date('d M Y (l)', strtotime($evt['event_date'])) ?></div>
                </div>
            </div>
            <?php if (!empty($evt['description'])): ?>
            <div class="upcoming-card-desc"><?= htmlspecialchars(mb_strimwidth($evt['description'], 0, 120, '...')) ?></div>
            <?php endif; ?>
            <div class="upcoming-card-chips">
                <span class="upcoming-chip"><?= htmlspecialchars($evt['category']) ?></span>
                <?php if (!empty($evt['event_time'])): ?>
                <span class="upcoming-chip upcoming-chip-date"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($evt['event_time'])) ?></span>
                <?php endif; ?>
                <span class="upcoming-chip upcoming-chip-date">
                    <?php
                    $days_until = (strtotime($evt['event_date']) - strtotime($today)) / 86400;
                    if ($days_until == 0) echo 'Today';
                    elseif ($days_until == 1) echo 'Tomorrow';
                    else echo (int)$days_until . ' days left';
                    ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Activity History Section -->
<div class="activities-section-title">
    <i class="fas fa-history"></i> Activity History
</div>

<!-- Month Navigation Chips -->
<?php if (!empty($available_months)): ?>
<div class="month-nav-chips">
    <?php foreach ($available_months as $am): ?>
    <a href="?month=<?= htmlspecialchars($am['ym']) ?>" 
       class="month-nav-chip <?= ($filter_month === $am['ym']) ? 'active' : '' ?>">
        <?= htmlspecialchars($am['label']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Month Filter -->
<div class="month-filter-bar">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
        <button type="submit" class="btn btn-sm" style="background:var(--purple);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:700;">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<!-- Stats for selected month -->
<div class="activity-stats-row">
    <div class="activity-stat">
        <div class="activity-stat-value" style="color:var(--text);"><?= $total_month_events ?></div>
        <div class="activity-stat-label">Total Events</div>
    </div>
    <div class="activity-stat">
        <div class="activity-stat-value" style="color:var(--green);"><?= $participated_month ?></div>
        <div class="activity-stat-label">Participated</div>
    </div>
    <div class="activity-stat">
        <div class="activity-stat-value" style="color:var(--red);"><?= $not_participated_month ?></div>
        <div class="activity-stat-label">Missed</div>
    </div>
    <div class="activity-stat">
        <div class="activity-stat-value" style="color:var(--purple-light);"><?= $participation_rate ?>%</div>
        <div class="activity-stat-label">Rate</div>
    </div>
</div>

<!-- Participation Progress Bar -->
<div class="activity-progress">
    <div class="activity-progress-fill" style="width: <?= $participation_rate ?>%;"></div>
</div>

<!-- History Table -->
<div class="glass-card" style="padding:0;overflow:hidden;">
    <?php if (empty($history_events)): ?>
    <div class="empty-activities">
        <i class="fas fa-calendar-xmark"></i>
        <p style="font-weight:600;margin-bottom:4px;">No activities for <?= $current_month_label ?></p>
        <p style="font-size:12px;">Activities will appear here once they are created by the admin.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Participation</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history_events as $h): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="history-icon">
                                <i class="<?= htmlspecialchars($h['icon']) ?>"></i>
                            </div>
                            <span style="font-weight:600;"><?= htmlspecialchars($h['title']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:12px;"><?= date('d M Y', strtotime($h['event_date'])) ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?= date('l', strtotime($h['event_date'])) ?>
                            <?php if (!empty($h['event_time'])): ?>
                            · <?= date('h:i A', strtotime($h['event_time'])) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span style="font-size:12px;"><?= htmlspecialchars($h['category']) ?></span></td>
                    <td>
                        <?php if ($h['participation_status'] === 'Participated'): ?>
                        <span class="participation-badge participated"><i class="fas fa-check-circle"></i> Participated</span>
                        <?php elseif ($h['participation_status'] === 'Not Participated'): ?>
                        <span class="participation-badge not-participated"><i class="fas fa-times-circle"></i> Not Participated</span>
                        <?php else: ?>
                        <span class="participation-badge not-marked"><i class="fas fa-minus-circle"></i> Not Marked</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($h['resolved_attendance'] === 'Present'): ?>
                        <span class="attendance-badge present"><i class="fas fa-check-circle"></i> Present</span>
                        <?php elseif ($h['resolved_attendance'] === 'Absent'): ?>
                        <span class="attendance-badge absent"><i class="fas fa-times-circle"></i> Absent</span>
                        <?php elseif ($h['resolved_attendance'] === 'Sunday'): ?>
                        <span class="attendance-badge att-not-marked"><i class="fas fa-sun"></i> Sunday</span>
                        <?php else: ?>
                        <span class="attendance-badge att-not-marked"><i class="fas fa-minus-circle"></i> Not Marked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
