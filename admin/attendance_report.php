<?php
include 'includes/header.php';
require_once 'includes/ranking_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php"); exit;
}

// ── Date filter ──
$filter_date = isset($_GET['date']) ? $_GET['date'] : getCurrentISTDate();
$today       = getCurrentISTDate();
$current_ist = date('h:i A');

// ── Batch / Group filters ──
$sel_batch   = isset($_GET['batch']) ? $_GET['batch'] : '';
$sel_group   = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// ── Load student groups for dropdown ──
$groupListQ = $conn->query("SELECT sg.id, sg.group_name, c.name as course_name FROM student_groups sg LEFT JOIN courses c ON sg.course_id = c.id ORDER BY sg.group_name");
$groupList = [];
while ($g = $groupListQ->fetch_assoc()) $groupList[] = $g;

// ── Year / student filter for monthly overview ──
$sel_year    = isset($_GET['year'])       ? (int)$_GET['year']       : (int)date('Y');
$sel_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// ── Sort by point type filter ──
$valid_sorts = ['total','attendance','projects','payments','manual','quiz'];
$sort_by = isset($_GET['sort']) && in_array($_GET['sort'], $valid_sorts) ? $_GET['sort'] : 'total';

// ── Ranking ──
$cm_start = date('Y-m-01');
$cm_end   = date('Y-m-t');
$ranking  = getMonthlyRanking($conn, $cm_start, $cm_end);

// ── Sort ranking by selected point type ──
$sort_key_map = [
    'total'      => 'total_points',
    'attendance' => 'attendance_points',
    'projects'   => 'project_points',
    'payments'   => 'payment_points',
    'manual'     => 'manual_points',
    'quiz'       => 'quiz_points'
];
$sort_field = $sort_key_map[$sort_by];

// Re-sort the ranking array based on selected category
uasort($ranking, function($a, $b) use ($sort_field) {
    // Primary: selected field DESC (spaceship operator handles decimals correctly)
    if ($b[$sort_field] !== $a[$sort_field]) {
        return $b[$sort_field] <=> $a[$sort_field];
    }
    // Tie-breaker 1: total_points DESC
    if ($b['total_points'] !== $a['total_points']) {
        return $b['total_points'] <=> $a['total_points'];
    }
    // Tie-breaker 2: full_name ASC
    return strcmp($a['full_name'], $b['full_name']);
});

// Re-assign ranks after sorting
$new_rank = 1;
foreach ($ranking as $sid => &$rdata) {
    $rdata['rank'] = $new_rank++;
}
unset($rdata);

// ══════════════════════════════════
// BUILD FILTER CONDITIONS
// ══════════════════════════════════
$filterWhere = "s.status='Active' AND s.login_enabled=1";
$filterJoin  = '';
if (!empty($sel_batch) && in_array($sel_batch, ['Morning','Evening'])) {
    $filterWhere .= " AND s.batch='" . $conn->real_escape_string($sel_batch) . "'";
}
if ($sel_group > 0) {
    $filterJoin  = "INNER JOIN student_group_members sgm ON s.id = sgm.student_id AND sgm.group_id=" . (int)$sel_group;
}

// ══════════════════════════════════
// STATS
// ══════════════════════════════════
$total_active = (int)$conn->query("SELECT COUNT(*) c FROM students s $filterJoin WHERE $filterWhere")->fetch_assoc()['c'];
$present_count = (int)$conn->query("SELECT COUNT(DISTINCT s.id) c FROM students s $filterJoin INNER JOIN student_attendance a ON s.id=a.student_id AND a.attendance_date='$filter_date' AND a.status='Present' WHERE $filterWhere")->fetch_assoc()['c'];
$holiday_count_stat = (int)$conn->query("SELECT COUNT(DISTINCT s.id) c FROM students s $filterJoin INNER JOIN student_attendance a ON s.id=a.student_id AND a.attendance_date='$filter_date' AND a.status='Holiday' WHERE $filterWhere")->fetch_assoc()['c'];
$absent_count  = $total_active - $present_count - $holiday_count_stat;
$att_rate      = $total_active > 0 ? round((($present_count + $holiday_count_stat) / $total_active) * 100, 1) : 0;

// ══════════════════════════════════
// ALL STUDENTS — combined (present + absent + holiday)
// ══════════════════════════════════
$allStudentsQ = $conn->query("
    SELECT s.id, s.student_code, s.full_name, s.photo, s.batch,
           a.check_in_time, a.status as att_status, a.created_at
    FROM students s
    $filterJoin
    LEFT JOIN student_attendance a ON s.id=a.student_id AND a.attendance_date='$filter_date'
    WHERE $filterWhere
    ORDER BY a.check_in_time ASC, s.full_name ASC
");
$allStudents = [];
while ($r = $allStudentsQ->fetch_assoc()) $allStudents[] = $r;

// Export-friendly combined list
$exportStudents = $allStudents;

// ══════════════════════════════════
// CONSECUTIVE ABSENTEES
// ══════════════════════════════════
$consAbsentees = [];
$studentsQ2 = $conn->query("SELECT s.id, s.full_name, s.photo, s.batch FROM students s $filterJoin WHERE $filterWhere");
while ($s = $studentsQ2->fetch_assoc()) {
    // If present or holiday today, skip
    $chk = $conn->query("SELECT id FROM student_attendance WHERE student_id={$s['id']} AND attendance_date='$filter_date' AND status IN ('Present','Holiday')");
    if ($chk->num_rows > 0) continue;

    // Walk back from yesterday counting consecutive absences
    $streak = 0;
    $check_day = date('Y-m-d', strtotime($filter_date . ' -1 day'));
    for ($i = 0; $i < 30; $i++) {
        // skip Sundays
        if (date('N', strtotime($check_day)) == 7) {
            $check_day = date('Y-m-d', strtotime($check_day . ' -1 day'));
            continue;
        }
        $dayChk = $conn->query("SELECT id FROM student_attendance WHERE student_id={$s['id']} AND attendance_date='$check_day' AND status IN ('Present','Holiday')");
        if ($dayChk->num_rows > 0) break;
        $streak++;
        $check_day = date('Y-m-d', strtotime($check_day . ' -1 day'));
    }
    if ($streak > 0) {
        $s['streak'] = $streak;
        $consAbsentees[] = $s;
    }
}
usort($consAbsentees, fn($a, $b) => $b['streak'] - $a['streak']);
$consAbsentees = array_slice($consAbsentees, 0, 10);

// ══════════════════════════════════
// MOST ABSENT DAY THIS WEEK (Mon–Sat)
// ══════════════════════════════════
$weekDays = [];
$dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat'];
// Find this week's Monday
$monday = date('Y-m-d', strtotime('monday this week'));
if (date('N', strtotime($today)) == 1) $monday = $today; // if today is Monday

for ($d = 0; $d < 6; $d++) {
    $dayDate = date('Y-m-d', strtotime($monday . " +$d days"));
    if ($dayDate > $today) break; // don't include future days
    $dayName = date('D', strtotime($dayDate));
    $pres = (int)$conn->query("SELECT COUNT(DISTINCT student_id) c FROM student_attendance WHERE attendance_date='$dayDate' AND status IN ('Present','Holiday')")->fetch_assoc()['c'];
    $pct  = $total_active > 0 ? round(($pres / $total_active) * 100, 1) : 0;
    $weekDays[] = ['date' => $dayDate, 'name' => $dayName, 'present' => $pres, 'pct' => $pct];
}
// Find lowest pct day
$worstDay = null;
if (!empty($weekDays)) {
    usort($weekDays, fn($a,$b) => $a['pct'] <=> $b['pct']);
    $worstDay = $weekDays[0]['date'];
    // re-sort chronologically
    usort($weekDays, fn($a,$b) => strcmp($a['date'], $b['date']));
}

// ══════════════════════════════════
// MONTHLY ATTENDANCE OVERVIEW
// ══════════════════════════════════
$monthlyData = [];
$yearlyTotal = 0; $yearlyDays = 0;
for ($m = 1; $m <= 12; $m++) {
    $mStart = sprintf('%04d-%02d-01', $sel_year, $m);
    $mEnd   = date('Y-m-t', strtotime($mStart));
    if ($mStart > $today) { $monthlyData[] = ['month' => $m, 'pct' => null, 'present' => 0, 'days' => 0]; continue; }

    if ($sel_student > 0) {
        // single student
        $r = $conn->query("SELECT COUNT(*) c FROM student_attendance WHERE student_id=$sel_student AND attendance_date BETWEEN '$mStart' AND '$mEnd' AND status IN ('Present','Holiday')")->fetch_assoc();
        $days_q = $conn->query("SELECT COUNT(DISTINCT attendance_date) c FROM student_attendance WHERE attendance_date BETWEEN '$mStart' AND '$mEnd'")->fetch_assoc();
        $pres = (int)$r['c'];
        $wDays = (int)$days_q['c'];
        $pct  = $wDays > 0 ? round(($pres / $wDays) * 100, 1) : 0;
    } else {
        $r = $conn->query("SELECT COUNT(*) c FROM student_attendance WHERE attendance_date BETWEEN '$mStart' AND '$mEnd' AND status IN ('Present','Holiday')")->fetch_assoc();
        $days_q = $conn->query("SELECT COUNT(DISTINCT attendance_date) c FROM student_attendance WHERE attendance_date BETWEEN '$mStart' AND '$mEnd'")->fetch_assoc();
        $pres = (int)$r['c'];
        $wDays = (int)$days_q['c'];
        $expected = $total_active * $wDays;
        $pct = $expected > 0 ? round(($pres / $expected) * 100, 1) : 0;
    }
    if ($wDays > 0) { $yearlyTotal += $pct; $yearlyDays++; }
    $monthlyData[] = ['month' => $m, 'pct' => $wDays > 0 ? $pct : null, 'present' => $pres, 'days' => $wDays];
}
$yearlyAvg = $yearlyDays > 0 ? round($yearlyTotal / $yearlyDays, 1) : 0;

// Year list
$yearList = [];
for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--) $yearList[] = $y;

// Student list for dropdown
$studentListQ = $conn->query("SELECT id, full_name, student_code FROM students WHERE status='Active' ORDER BY full_name");
$studentList = [];
while ($r = $studentListQ->fetch_assoc()) $studentList[] = $r;

$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>

<!-- Google Fonts + FA -->
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ═══════════════════════════════════════════════════════
   ATTENDANCE REPORT — DARK THEME (matches Task Manager)
═══════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }

:root {
    --purple-primary:     #2c184f;
    --purple-dark:        #260e4c;
    --purple-light:       #8e73e0;
    --purple-lighter:     #d6b8f5;
    --purple-extra-light: #f3e8ff;
    --bg-deep:   #1a0d38;
    --bg-card:   #220f43;
    --bg-input:  #2c184f;
    --bg-hover:  #341a5e;
    --border:    rgba(142,115,224,0.15);
    --border-hi: rgba(142,115,224,0.5);
    --accent:    #8e73e0;
    --accent2:   #b994f0;
    --green:     #22c97a;
    --red:       #ff5263;
    --amber:     #ffb347;
    --orange:    #ff7a35;
    --text-1:    #f0e8ff;
    --text-2:    #b09dd4;
    --text-3:    #6b5a8a;
    --glow:      0 0 28px rgba(142,115,224,0.2);
    --r-card:    14px;
    --r-pill:    50px;
    --shadow:    0 4px 28px rgba(0,0,0,0.5);
    --font-head: 'Rajdhani', sans-serif;
    --font-body: 'DM Sans', sans-serif;
}

/* Override main-content background for this page */
.main-content {
    background: radial-gradient(ellipse at top left, #2c1060 0%, #1a0d38 40%, #120830 100%) !important;
    min-height: 100vh;
    padding: 24px 26px !important;
    font-family: var(--font-body);
    position: relative;
}
.main-content::before {
    content: '';
    position: fixed; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none; z-index: 0; opacity: 0.4;
}
.main-content > * { position: relative; z-index: 1; }

/* ── TOP BAR ── */
.ar-topbar {
    display: flex; align-items: center; flex-wrap: wrap; gap: 14px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-top: 2px solid rgba(142,115,224,0.4);
    border-radius: var(--r-card);
    padding: 16px 22px;
    margin-bottom: 18px;
    box-shadow: var(--shadow);
}
.ar-topbar-title {
    font-family: var(--font-head);
    font-size: 22px; font-weight: 700;
    color: var(--text-1); letter-spacing: .5px;
    display: flex; align-items: center; gap: 10px;
}
.ar-topbar-title i { color: var(--purple-light); font-size: 18px; }
.ar-topbar-sub { font-size: 12px; color: var(--text-3); margin-top: 2px; }

.ar-date-form {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto;
}
.ar-date-input {
    height: 40px; padding: 0 14px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-1); font-family: var(--font-body); font-size: 13px;
    outline: none; cursor: pointer; transition: border-color .22s;
    color-scheme: dark;
}
.ar-date-input:focus { border-color: var(--purple-light); box-shadow: 0 0 0 3px rgba(142,115,224,0.15); }
.ar-btn {
    height: 40px; padding: 0 20px;
    background: linear-gradient(135deg, #8e73e0, #b994f0);
    color: #fff; border: none; border-radius: var(--r-pill);
    font-family: var(--font-head); font-size: 14px; font-weight: 600;
    letter-spacing: .4px; cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    white-space: nowrap; display: flex; align-items: center; gap: 7px;
}
.ar-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(142,115,224,0.45); }
.ar-btn.secondary {
    background: var(--bg-input); border: 1.5px solid var(--border);
    color: var(--text-2); font-family: var(--font-body);
}
.ar-btn.secondary:hover { border-color: var(--purple-light); color: var(--text-1); box-shadow: none; transform: none; }

/* ── STAT STRIP ── */
.ar-stats {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 18px;
}
.ar-stat {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    padding: 16px 18px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: var(--shadow);
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
}
.ar-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow), var(--glow); }
.ar-stat::before {
    content: ''; position: absolute; right: -20px; top: -20px;
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(255,255,255,.04); pointer-events: none;
}
.ar-stat-icon {
    width: 46px; height: 46px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff; flex-shrink: 0;
}
.ar-stat-num {
    font-family: var(--font-head); font-size: 34px; font-weight: 900;
    line-height: 1; color: var(--text-1); letter-spacing: -1px;
}
.ar-stat-label {
    font-size: 10px; font-weight: 600; letter-spacing: .5px;
    text-transform: uppercase; color: var(--text-3); margin-top: 2px;
}
.ar-stat.total  .ar-stat-icon { background: linear-gradient(135deg,#8e73e0,#b994f0); }
.ar-stat.present .ar-stat-icon { background: linear-gradient(135deg,#22c97a,#6bffb8); }
.ar-stat.absent  .ar-stat-icon { background: linear-gradient(135deg,#ff5263,#ff8a9b); }
.ar-stat.rate    .ar-stat-icon { background: linear-gradient(135deg,#ffb347,#ffd080); }
.ar-stat.present .ar-stat-num { color: var(--green); }
.ar-stat.absent  .ar-stat-num { color: var(--red); }
.ar-stat.rate    .ar-stat-num { color: var(--amber); }
.ar-stat.total   { border-top: 2px solid rgba(142,115,224,0.5); }
.ar-stat.present { border-top: 2px solid rgba(34,201,122,0.5); }
.ar-stat.absent  { border-top: 2px solid rgba(255,82,99,0.5); }
.ar-stat.rate    { border-top: 2px solid rgba(255,179,71,0.5); }

/* ── ACTIVE NOW ── */
.ar-active-header {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px;
}
.ar-active-counter {
    background: linear-gradient(135deg, rgba(34,201,122,0.15), rgba(34,201,122,0.05));
    border: 1px solid rgba(34,201,122,0.3);
    border-radius: var(--r-pill);
    padding: 5px 16px;
    font-family: var(--font-head); font-size: 15px; font-weight: 700;
    color: var(--green);
    display: flex; align-items: center; gap: 7px;
}
.ar-active-counter .live-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 8px var(--green);
    animation: pulse-dot 1.5s infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .5; transform: scale(.7); }
}

/* ── SECTION CARD ── */
.ar-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: var(--shadow);
}
.ar-section-head {
    font-family: var(--font-head);
    font-size: 17px; font-weight: 700;
    color: var(--text-1); letter-spacing: .5px;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.ar-section-head i { color: var(--purple-light); }

/* ── AVATAR SCROLL ── */
.ar-avatar-scroll {
    overflow-x: auto; overflow-y: hidden;
    padding: 6px 4px 12px;
    scrollbar-width: thin;
    scrollbar-color: rgba(142,115,224,0.3) transparent;
}
.ar-avatar-scroll::-webkit-scrollbar { height: 4px; }
.ar-avatar-scroll::-webkit-scrollbar-thumb { background: rgba(142,115,224,0.3); border-radius: 99px; }
.ar-avatar-row {
    display: flex; gap: 14px;
    min-width: max-content;
}
.ar-avatar-item {
    text-align: center; width: 78px; cursor: pointer;
    transition: transform .2s;
    flex-shrink: 0;
}
.ar-avatar-item:hover { transform: translateY(-4px); }
.ar-avatar-ring {
    width: 64px; height: 64px; border-radius: 50%;
    padding: 3px; margin: 0 auto 6px; position: relative;
}
.ar-avatar-ring.present {
    background: conic-gradient(#22c97a 0%, #22c97a 100%);
    box-shadow: 0 0 14px rgba(34,201,122,0.5);
}
.ar-avatar-ring.absent {
    background: conic-gradient(#ff5263 0%, #ff5263 100%);
    box-shadow: 0 0 10px rgba(255,82,99,0.35);
}
.ar-avatar-ring.late {
    background: conic-gradient(#ffb347, #ff7a35);
    box-shadow: 0 0 12px rgba(255,179,71,0.45);
}
.ar-avatar-inner {
    width: 100%; height: 100%; border-radius: 50%;
    border: 2.5px solid rgba(26,13,56,0.8);
    overflow: hidden; background: var(--bg-input);
    display: flex; align-items: center; justify-content: center;
}
.ar-avatar-inner img { width: 100%; height: 100%; object-fit: cover; }
.ar-avatar-inner i { font-size: 24px; color: var(--text-3); }
.ar-rank-badge {
    position: absolute; top: -4px; right: -4px;
    min-width: 19px; height: 19px; border-radius: 50%;
    background: #ef4444; color: #fff; font-size: 8px; font-weight: 900;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg-card); padding: 0 3px;
}
.ar-rank-badge.rank-gold { background: linear-gradient(135deg,#fbbf24,#f59e0b); }
.ar-rank-badge.rank-green { background: var(--green); }
.ar-avatar-name {
    font-size: 10px; font-weight: 600; color: var(--text-2);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 76px; margin-bottom: 2px;
}
.ar-avatar-time {
    font-size: 9px; color: var(--text-3);
    display: flex; align-items: center; justify-content: center; gap: 3px;
}
.ar-avatar-time.green { color: var(--green); }
.ar-avatar-time.red   { color: var(--red); }

/* ── CONSECUTIVE ABSENTEES ── */
.consec-list { display: flex; flex-direction: column; gap: 8px; }
.consec-item {
    display: flex; align-items: center; gap: 12px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    transition: border-color .2s, box-shadow .2s;
}
.consec-item:hover { border-color: rgba(142,115,224,0.4); box-shadow: var(--glow); }
.consec-av {
    width: 42px; height: 42px; border-radius: 50%; overflow: hidden;
    border: 2px solid rgba(255,82,99,0.5);
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-card);
}
.consec-av img { width: 100%; height: 100%; object-fit: cover; }
.consec-av i { font-size: 18px; color: var(--text-3); }
.consec-info { flex: 1; min-width: 0; }
.consec-name { font-size: 13px; font-weight: 600; color: var(--text-1); }
.consec-batch { font-size: 10px; color: var(--text-3); text-transform: uppercase; letter-spacing: .4px; }
.consec-streak {
    font-family: var(--font-head);
    font-size: 15px; font-weight: 800;
    padding: 4px 14px; border-radius: var(--r-pill);
    white-space: nowrap; flex-shrink: 0;
}
.streak-yellow { background: rgba(255,179,71,0.15); color: var(--amber); border: 1px solid rgba(255,179,71,0.35); }
.streak-orange { background: rgba(255,122,53,0.15); color: var(--orange); border: 1px solid rgba(255,122,53,0.35); }
.streak-red    { background: rgba(255,82,99,0.15);  color: var(--red);    border: 1px solid rgba(255,82,99,0.35); }

/* ── WEEK DAYS ── */
.week-day-list { display: flex; flex-direction: column; gap: 10px; }
.week-day-row {
    display: flex; align-items: center; gap: 12px;
}
.week-day-label {
    font-family: var(--font-head); font-size: 13px; font-weight: 700;
    color: var(--text-2); width: 36px; flex-shrink: 0;
    text-transform: uppercase; letter-spacing: .4px;
}
.week-day-label.worst { color: var(--red); }
.week-progress-track {
    flex: 1; height: 10px; border-radius: 99px;
    background: var(--bg-input);
    overflow: hidden; position: relative;
}
.week-progress-fill {
    height: 100%; border-radius: 99px;
    transition: width .6s ease;
}
.week-progress-fill.normal { background: linear-gradient(90deg, #8e73e0, #b994f0); }
.week-progress-fill.worst  { background: linear-gradient(90deg, #ff5263, #ff8a9b); }
.week-pct {
    font-family: var(--font-head); font-size: 14px; font-weight: 700;
    width: 46px; text-align: right; flex-shrink: 0;
    color: var(--text-2);
}
.week-pct.worst { color: var(--red); }
.week-count {
    font-size: 11px; color: var(--text-3); width: 60px; flex-shrink: 0;
}

/* ── MONTHLY GRID ── */
.monthly-grid {
    display: grid; grid-template-columns: repeat(6, 1fr);
    gap: 10px; margin-bottom: 16px;
}
.monthly-cell {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 10px;
    text-align: center;
    transition: transform .2s, border-color .2s;
    cursor: default;
}
.monthly-cell:hover { transform: translateY(-2px); border-color: rgba(142,115,224,0.4); }
.monthly-cell.future { opacity: 0.35; }
.monthly-month {
    font-size: 10px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: var(--text-3); margin-bottom: 6px;
}
.monthly-pct {
    font-family: var(--font-head); font-size: 24px; font-weight: 900;
    line-height: 1;
}
.pct-high   { color: var(--green); }
.pct-medium { color: var(--amber); }
.pct-low    { color: var(--red); }
.pct-none   { color: var(--text-3); font-size: 18px; }
.monthly-bar {
    height: 4px; border-radius: 99px;
    margin-top: 6px; background: var(--bg-card);
    overflow: hidden;
}
.monthly-bar-fill { height: 100%; border-radius: 99px; }

.monthly-filters {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 16px;
}
.monthly-filters select {
    height: 38px; padding: 0 14px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-2); font-family: var(--font-body); font-size: 13px;
    outline: none; cursor: pointer; transition: border-color .22s;
}
.monthly-filters select:focus { border-color: var(--purple-light); }
.monthly-filters select option { background: var(--bg-card); color: var(--text-1); }
.monthly-filters .ar-btn { height: 38px; font-size: 13px; padding: 0 18px; }

.yearly-avg-bar {
    display: flex; align-items: center; gap: 14px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--r-card); padding: 14px 18px;
}
.yearly-avg-label {
    font-family: var(--font-head); font-size: 14px; font-weight: 700;
    color: var(--text-2); white-space: nowrap;
}
.yearly-avg-track {
    flex: 1; height: 12px; border-radius: 99px;
    background: var(--bg-card); overflow: hidden;
}
.yearly-avg-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, #8e73e0, #22c97a);
    transition: width .8s ease;
}
.yearly-avg-val {
    font-family: var(--font-head); font-size: 22px; font-weight: 900;
    color: var(--purple-lighter); white-space: nowrap;
}

/* ── EXPORT TABLE (hidden) ── */
#exportTable { display: none; }

/* ── TWO COLUMN LAYOUT ── */
.ar-two-col {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}

/* ── EMPTY STATE ── */
.ar-empty {
    text-align: center; padding: 30px;
    color: var(--text-3); font-size: 13px;
}
.ar-empty i { font-size: 32px; display: block; margin-bottom: 8px; opacity: .35; }

/* ── SORT-BY FILTER BAR ── */
.ar-sort-bar {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    margin-bottom: 14px;
}
.ar-sort-bar label {
    font-family: var(--font-head); font-size: 14px; font-weight: 700;
    color: var(--text-2); white-space: nowrap;
    display: flex; align-items: center; gap: 7px;
}
.ar-sort-bar label i { color: var(--purple-light); font-size: 13px; }
.ar-sort-select {
    height: 40px; padding: 0 16px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-1); font-family: var(--font-body); font-size: 13px;
    outline: none; cursor: pointer; transition: border-color .22s, box-shadow .22s;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238e73e0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
    padding-right: 36px;
}
.ar-sort-select:focus { border-color: var(--purple-light); box-shadow: 0 0 0 3px rgba(142,115,224,0.15); }
.ar-sort-select option { background: var(--bg-card); color: var(--text-1); }
.ar-sort-active-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: var(--r-pill);
    font-size: 12px; font-weight: 600;
    background: linear-gradient(135deg, rgba(142,115,224,0.15), rgba(142,115,224,0.05));
    border: 1px solid rgba(142,115,224,0.3);
    color: var(--purple-lighter);
}
.ar-sort-active-pill i { font-size: 10px; }

/* ── RANKING TABLE ── */
.ar-rank-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ar-rank-table thead th {
    font-family: var(--font-head); font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--text-3); padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap; text-align: left;
}
.ar-rank-table thead th.sort-active {
    color: var(--purple-lighter);
    border-bottom: 2px solid var(--purple-light);
}
.ar-rank-table tbody tr {
    transition: background .15s, transform .15s;
    cursor: pointer;
}
.ar-rank-table tbody tr:hover {
    background: var(--bg-hover);
}
.ar-rank-table tbody td {
    padding: 10px 12px; border-bottom: 1px solid rgba(142,115,224,0.08);
    font-size: 13px; color: var(--text-2); vertical-align: middle;
}
.ar-rank-table tbody tr:last-child td { border-bottom: none; }
.ar-rank-num {
    font-family: var(--font-head); font-weight: 800; font-size: 16px;
    color: var(--text-3); width: 36px; text-align: center;
}
.ar-rank-num.top-1 { color: #fbbf24; }
.ar-rank-num.top-2 { color: #c0c0c0; }
.ar-rank-num.top-3 { color: #cd7f32; }
.ar-rank-student {
    display: flex; align-items: center; gap: 10px;
}
.ar-rank-avatar {
    width: 36px; height: 36px; border-radius: 50%; overflow: hidden;
    border: 2px solid var(--border); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-input);
}
.ar-rank-avatar img { width: 100%; height: 100%; object-fit: cover; }
.ar-rank-avatar i { font-size: 14px; color: var(--text-3); }
.ar-rank-sname { font-weight: 600; color: var(--text-1); font-size: 13px; }
.ar-points-cell {
    font-family: var(--font-head); font-size: 15px; font-weight: 700;
}
.ar-points-cell.highlight {
    color: var(--purple-lighter);
    background: rgba(142,115,224,0.08);
    border-radius: 6px;
    padding: 4px 10px;
}
.ar-points-total {
    font-family: var(--font-head); font-size: 18px; font-weight: 900;
    color: var(--text-1);
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .ar-stats { grid-template-columns: repeat(2, 1fr); }
    .ar-two-col { grid-template-columns: 1fr; }
    .monthly-grid { grid-template-columns: repeat(4, 1fr); }
    .ar-rank-table { font-size: 12px; }
}
@media (max-width: 600px) {
    .ar-stats { grid-template-columns: repeat(2, 1fr); }
    .monthly-grid { grid-template-columns: repeat(3, 1fr); }
    .ar-topbar { flex-direction: column; align-items: flex-start; }
    .ar-date-form { margin-left: 0; width: 100%; }
    .ar-rank-table thead th, .ar-rank-table tbody td { padding: 8px 6px; font-size: 11px; }
}

/* ── HOLIDAY STYLES ── */
.ar-avatar-ring.holiday {
    background: conic-gradient(#6b7280 0%, #9ca3af 100%);
    box-shadow: 0 0 10px rgba(107,114,128,0.35);
}
.ar-stat.holiday .ar-stat-icon { background: linear-gradient(135deg,#6b7280,#9ca3af); }
.ar-stat.holiday .ar-stat-num { color: #9ca3af; }
.ar-stat.holiday { border-top: 2px solid rgba(107,114,128,0.5); }

.ar-btn.holiday-btn {
    background: linear-gradient(135deg, #6b7280, #9ca3af);
    position: relative;
}
.ar-btn.holiday-btn:hover { box-shadow: 0 6px 20px rgba(107,114,128,0.45); }
.ar-btn.holiday-btn.already {
    background: var(--bg-input);
    border: 1.5px solid rgba(107,114,128,0.4);
    color: #9ca3af;
    cursor: default;
    pointer-events: none;
}

.holiday-indicator {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: var(--r-pill);
    font-size: 12px; font-weight: 600;
    background: rgba(107,114,128,0.12);
    border: 1px solid rgba(107,114,128,0.3);
    color: #9ca3af;
}

.ar-filter-select {
    height: 40px; padding: 0 14px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-2); font-family: var(--font-body); font-size: 13px;
    outline: none; cursor: pointer; transition: border-color .22s;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238e73e0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
    padding-right: 36px;
}
.ar-filter-select:focus { border-color: var(--purple-light); box-shadow: 0 0 0 3px rgba(142,115,224,0.15); }
.ar-filter-select option { background: var(--bg-card); color: var(--text-1); }

/* ── TOAST ── */
.ar-toast {
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    padding: 14px 22px; border-radius: 12px;
    font-family: var(--font-body); font-size: 14px; font-weight: 600;
    color: #fff; box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    display: flex; align-items: center; gap: 10px;
    transform: translateX(120%); transition: transform .35s ease;
    max-width: 400px;
}
.ar-toast.show { transform: translateX(0); }
.ar-toast.success { background: linear-gradient(135deg, #22c97a, #16a34a); }
.ar-toast.error   { background: linear-gradient(135deg, #ff5263, #dc2626); }
.ar-toast.info    { background: linear-gradient(135deg, #6b7280, #4b5563); }

/* ── CONFIRM MODAL ── */
.ar-modal-overlay {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
}
.ar-modal-overlay.show { display: flex; }
.ar-modal {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--r-card); padding: 28px 30px;
    max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.ar-modal h3 {
    font-family: var(--font-head); font-size: 20px; font-weight: 700;
    color: var(--text-1); margin: 0 0 12px; display: flex; align-items: center; gap: 10px;
}
.ar-modal h3 i { color: #9ca3af; }
.ar-modal p { font-size: 13px; color: var(--text-2); line-height: 1.6; margin: 0 0 8px; }
.ar-modal .warn-box {
    background: rgba(255,179,71,0.08); border: 1px solid rgba(255,179,71,0.25);
    border-radius: 8px; padding: 10px 14px; margin: 12px 0;
    font-size: 12px; color: var(--amber); display: flex; align-items: center; gap: 8px;
}
.ar-modal-actions {
    display: flex; gap: 10px; margin-top: 18px; justify-content: flex-end;
}
</style>


<!-- ═══════════════════════════════════════
     TOP BAR
═══════════════════════════════════════ -->
<div class="ar-topbar">
    <div>
        <div class="ar-topbar-title">
            <i class="fas fa-calendar-check"></i>
            Attendance Report
        </div>
        <div class="ar-topbar-sub">IST: <?= $current_ist ?> &nbsp;|&nbsp; Showing: <?= date('l, d F Y', strtotime($filter_date)) ?><?= $filter_date === $today ? ' <span style="color:var(--green);font-weight:700">— Today</span>' : '' ?></div>
    </div>
    <form method="GET" class="ar-date-form" id="dateForm">
        <input type="hidden" name="year" value="<?= $sel_year ?>">
        <input type="hidden" name="student_id" value="<?= $sel_student ?>">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
        <input type="date" name="date" class="ar-date-input" value="<?= htmlspecialchars($filter_date) ?>"
               max="<?= $today ?>" onchange="this.form.submit()">
        <select name="batch" class="ar-filter-select" onchange="this.form.submit()">
            <option value="">All Batches</option>
            <option value="Morning" <?= $sel_batch === 'Morning' ? 'selected' : '' ?>>Morning</option>
            <option value="Evening" <?= $sel_batch === 'Evening' ? 'selected' : '' ?>>Evening</option>
        </select>
        <select name="group_id" class="ar-filter-select" onchange="this.form.submit()">
            <option value="0">All Groups</option>
            <?php foreach ($groupList as $grp): ?>
            <option value="<?= $grp['id'] ?>" <?= $sel_group === (int)$grp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($grp['group_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="ar-btn holiday-btn <?= $holiday_count_stat === $total_active && $total_active > 0 ? 'already' : '' ?>"
                id="holidayBtn"
                onclick="openHolidayModal()">
            <i class="fas fa-umbrella-beach" style="font-size:12px"></i>
            <?= $holiday_count_stat === $total_active && $total_active > 0 ? 'Already Holiday' : 'Declare Holiday' ?>
        </button>
        <button type="button" class="ar-btn secondary"
                onclick="exportTableToCSV('exportTable','attendance_<?= $filter_date ?>.csv')">
            <i class="fas fa-download" style="font-size:12px"></i> CSV
        </button>
        <button type="button" class="ar-btn secondary" onclick="window.print()">
            <i class="fas fa-print" style="font-size:12px"></i>
        </button>
    </form>
</div>

<!-- ═══════════════════════════════════════
     STAT STRIP
═══════════════════════════════════════ -->
<div class="ar-stats" style="grid-template-columns: repeat(5, 1fr);">
    <div class="ar-stat total">
        <div class="ar-stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="ar-stat-num"><?= $total_active ?></div>
            <div class="ar-stat-label">Total Students</div>
        </div>
    </div>
    <div class="ar-stat present">
        <div class="ar-stat-icon"><i class="fas fa-circle-check"></i></div>
        <div>
            <div class="ar-stat-num"><?= $present_count ?></div>
            <div class="ar-stat-label">Present</div>
        </div>
    </div>
    <div class="ar-stat holiday">
        <div class="ar-stat-icon"><i class="fas fa-umbrella-beach"></i></div>
        <div>
            <div class="ar-stat-num"><?= $holiday_count_stat ?></div>
            <div class="ar-stat-label">Holiday</div>
        </div>
    </div>
    <div class="ar-stat absent">
        <div class="ar-stat-icon"><i class="fas fa-circle-xmark"></i></div>
        <div>
            <div class="ar-stat-num"><?= $absent_count ?></div>
            <div class="ar-stat-label">Absent</div>
        </div>
    </div>
    <div class="ar-stat rate">
        <div class="ar-stat-icon"><i class="fas fa-chart-pie"></i></div>
        <div>
            <div class="ar-stat-num"><?= $att_rate ?>%</div>
            <div class="ar-stat-label">Attendance Rate</div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════
     ALL STUDENTS — INSTAGRAM AVATAR ROW
═══════════════════════════════════════ -->
<div class="ar-section">
    <div class="ar-section-head">
        <i class="fas fa-users"></i>
        Today's Students
        <div class="ar-active-counter" style="margin-left:auto">
            <span class="live-dot"></span>
            Active Now: <?= $present_count ?>
        </div>
    </div>

    <?php if (!empty($allStudents)): ?>
    <div class="ar-avatar-scroll">
        <div class="ar-avatar-row">
        <?php
        // Sort: present first, then holiday, then absent
        usort($allStudents, function($a, $b) {
            $order = ['Present' => 0, 'Holiday' => 1, 'Absent' => 2, '' => 2];
            $aP = $order[$a['att_status'] ?? ''] ?? 2;
            $bP = $order[$b['att_status'] ?? ''] ?? 2;
            return $aP - $bP;
        });
        foreach ($allStudents as $st):
            $isPresent = ($st['att_status'] === 'Present');
            $isHoliday = ($st['att_status'] === 'Holiday');
            $has_photo = !empty($st['photo']) && file_exists($st['photo']);
            $rank = getStudentRank($ranking, $st['id']);
            $ringClass = $isPresent ? 'present' : ($isHoliday ? 'holiday' : 'absent');
            $rankClass = $rank === 1 ? 'rank-gold' : ($rank <= 5 ? 'rank-green' : '');
        ?>
        <div class="ar-avatar-item" onclick="window.location.href='student_details.php?id=<?= $st['id'] ?>'">
            <div class="ar-avatar-ring <?= $ringClass ?>">
                <?php if ($rank > 0): ?>
                <div class="ar-rank-badge <?= $rankClass ?>"><?= $rank <= 3 ? '★' : $rank ?></div>
                <?php endif; ?>
                <div class="ar-avatar-inner">
                    <?php if ($has_photo): ?>
                        <img src="<?= htmlspecialchars($st['photo']) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-user-graduate"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ar-avatar-name"><?= htmlspecialchars(explode(' ', $st['full_name'])[0]) ?></div>
            <?php if ($isPresent): ?>
                <div class="ar-avatar-time green">
                    <i class="fas fa-clock" style="font-size:8px"></i>
                    <?= !empty($st['check_in_time']) ? date('h:i A', strtotime($st['check_in_time'])) : '—' ?>
                </div>
            <?php elseif ($isHoliday): ?>
                <div class="ar-avatar-time" style="color:#9ca3af">
                    <i class="fas fa-umbrella-beach" style="font-size:8px"></i> Holiday
                </div>
            <?php else: ?>
                <div class="ar-avatar-time red">
                    <i class="fas fa-times" style="font-size:8px"></i> Absent
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="ar-empty"><i class="fas fa-users-slash"></i>No students found.</div>
    <?php endif; ?>

    <!-- Legend -->
    <div style="display:flex;gap:16px;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
        <span style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--green);display:inline-block;box-shadow:0 0 6px var(--green)"></span> Present
        </span>
        <span style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px">
            <span style="width:10px;height:10px;border-radius:50%;background:#9ca3af;display:inline-block"></span> Holiday
        </span>
        <span style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--red);display:inline-block"></span> Absent
        </span>
        <span style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px">
            <span style="width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,#fbbf24,#f59e0b);display:inline-block"></span> ★ Top Ranker
        </span>
    </div>
</div>

<!-- ═══════════════════════════════════════
     TWO COLUMN: Consecutive Absentees + Week Analysis
═══════════════════════════════════════ -->
<div class="ar-two-col">

    <!-- CONSECUTIVE ABSENTEES -->
    <div class="ar-section">
        <div class="ar-section-head">
            <i class="fas fa-user-clock"></i>
            Consecutive Absentees
            <span style="margin-left:auto;font-size:12px;font-weight:500;color:var(--text-3)">Max 10 shown</span>
        </div>
        <?php if (!empty($consAbsentees)): ?>
        <div class="consec-list">
        <?php foreach ($consAbsentees as $ca):
            $has_photo = !empty($ca['photo']) && file_exists($ca['photo']);
            $streakClass = $ca['streak'] >= 4 ? 'streak-red' : ($ca['streak'] >= 2 ? 'streak-orange' : 'streak-yellow');
        ?>
        <div class="consec-item" onclick="window.location.href='student_details.php?id=<?= $ca['id'] ?>'" style="cursor:pointer">
            <div class="consec-av">
                <?php if ($has_photo): ?><img src="<?= htmlspecialchars($ca['photo']) ?>" alt="">
                <?php else: ?><i class="fas fa-user-graduate"></i><?php endif; ?>
            </div>
            <div class="consec-info">
                <div class="consec-name"><?= htmlspecialchars($ca['full_name']) ?></div>
                <div class="consec-batch"><?= htmlspecialchars($ca['batch']) ?></div>
            </div>
            <div class="consec-streak <?= $streakClass ?>">
                <?= $ca['streak'] ?> Day<?= $ca['streak'] > 1 ? 's' : '' ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="ar-empty">
            <i class="fas fa-check-double"></i>
            No consecutive absentees today!
        </div>
        <?php endif; ?>
    </div>

    <!-- MOST ABSENT DAY THIS WEEK -->
    <div class="ar-section">
        <div class="ar-section-head">
            <i class="fas fa-chart-bar"></i>
            Attendance This Week
            <span style="margin-left:auto;font-size:11px;font-weight:500;color:var(--text-3)">Mon – Sat</span>
        </div>
        <?php if (!empty($weekDays)): ?>
        <div class="week-day-list">
        <?php foreach ($weekDays as $wd):
            $isWorst = ($wd['date'] === $worstDay);
        ?>
        <div class="week-day-row">
            <div class="week-day-label <?= $isWorst ? 'worst' : '' ?>">
                <?= date('D', strtotime($wd['date'])) ?>
                <?php if ($wd['date'] === $today): ?>
                    <span style="font-size:8px;color:var(--green)">●</span>
                <?php endif; ?>
            </div>
            <div class="week-progress-track">
                <div class="week-progress-fill <?= $isWorst ? 'worst' : 'normal' ?>"
                     style="width:<?= $wd['pct'] ?>%"></div>
            </div>
            <div class="week-pct <?= $isWorst ? 'worst' : '' ?>"><?= $wd['pct'] ?>%</div>
            <div class="week-count"><?= $wd['present'] ?>/<?= $total_active ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php if ($worstDay): ?>
        <div style="margin-top:12px;padding:8px 12px;background:rgba(255,82,99,0.08);border:1px solid rgba(255,82,99,0.2);border-radius:8px;font-size:12px;color:var(--red);display:flex;align-items:center;gap:7px">
            <i class="fas fa-triangle-exclamation"></i>
            Lowest attendance: <strong><?= date('l, d M', strtotime($worstDay)) ?></strong>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="ar-empty"><i class="fas fa-calendar-xmark"></i>No data for this week yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MONTHLY ATTENDANCE OVERVIEW
═══════════════════════════════════════ -->
<div class="ar-section">
    <div class="ar-section-head">
        <i class="fas fa-calendar-days"></i>
        Monthly Attendance Overview
    </div>

    <!-- Filters -->
    <form method="GET" class="monthly-filters" id="monthlyForm">
        <input type="hidden" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
        <input type="hidden" name="batch" value="<?= htmlspecialchars($sel_batch) ?>">
        <input type="hidden" name="group_id" value="<?= $sel_group ?>">
        <select name="year" onchange="this.form.submit()">
            <?php foreach ($yearList as $y): ?>
            <option value="<?= $y ?>" <?= $y === $sel_year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
        <select name="student_id" onchange="this.form.submit()">
            <option value="0">All Students</option>
            <?php foreach ($studentList as $sl): ?>
            <option value="<?= $sl['id'] ?>" <?= $sl['id'] === $sel_student ? 'selected' : '' ?>>
                <?= htmlspecialchars($sl['full_name']) ?> (<?= $sl['student_code'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($sel_student > 0): ?>
        <span style="font-size:12px;color:var(--purple-lighter);background:rgba(142,115,224,0.12);border:1px solid rgba(142,115,224,0.2);border-radius:var(--r-pill);padding:5px 12px">
            <i class="fas fa-user" style="font-size:10px"></i>
            Individual view
        </span>
        <?php endif; ?>
    </form>

    <!-- Monthly Grid -->
    <div class="monthly-grid">
    <?php foreach ($monthlyData as $md):
        $isFuture = ($md['pct'] === null);
        $pct = $md['pct'];
        $pctClass = $isFuture ? 'pct-none' : ($pct >= 75 ? 'pct-high' : ($pct >= 50 ? 'pct-medium' : 'pct-low'));
        $barColor = $isFuture ? 'var(--text-3)' : ($pct >= 75 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)'));
    ?>
    <div class="monthly-cell <?= $isFuture ? 'future' : '' ?>"
         title="<?= $monthNames[$md['month']-1] ?> <?= $sel_year ?>: <?= $isFuture ? 'No data' : $pct.'%' ?>">
        <div class="monthly-month"><?= $monthNames[$md['month']-1] ?></div>
        <div class="monthly-pct <?= $pctClass ?>">
            <?= $isFuture ? '—' : $pct.'%' ?>
        </div>
        <div class="monthly-bar">
            <div class="monthly-bar-fill"
                 style="width:<?= $isFuture ? 0 : $pct ?>%;background:<?= $barColor ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Yearly Average -->
    <div class="yearly-avg-bar">
        <div class="yearly-avg-label">
            <i class="fas fa-chart-line" style="color:var(--purple-light);margin-right:6px"></i>
            Overall Average <?= $sel_year ?>
            <?= $sel_student > 0 ? '(Student)' : '' ?>
        </div>
        <div class="yearly-avg-track">
            <div class="yearly-avg-fill" style="width:<?= min($yearlyAvg, 100) ?>%"></div>
        </div>
        <div class="yearly-avg-val"><?= $yearlyAvg ?>%</div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     STUDENT RANKING — SORTABLE BY POINT TYPE
═══════════════════════════════════════ -->
<?php
$sort_labels = [
    'total'      => 'Total Points',
    'attendance' => 'Attendance Points',
    'projects'   => 'Project Points',
    'payments'   => 'Payment Points',
    'manual'     => 'Manual Points',
    'quiz'       => 'Quiz Points'
];
?>
<div class="ar-section">
    <div class="ar-section-head">
        <i class="fas fa-trophy"></i>
        Student Ranking
        <span style="margin-left:auto;font-size:12px;font-weight:500;color:var(--text-3)">
            <?= date('F Y') ?>
        </span>
    </div>

    <!-- Sort By Filter -->
    <form method="GET" class="ar-sort-bar" id="sortForm">
        <input type="hidden" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <input type="hidden" name="year" value="<?= $sel_year ?>">
        <input type="hidden" name="student_id" value="<?= $sel_student ?>">
        <input type="hidden" name="batch" value="<?= htmlspecialchars($sel_batch) ?>">
        <input type="hidden" name="group_id" value="<?= $sel_group ?>">
        <label for="sortSelect">
            <i class="fas fa-arrow-down-wide-short"></i>
            Sort By
        </label>
        <select name="sort" id="sortSelect" class="ar-sort-select" onchange="this.form.submit()">
            <?php foreach ($sort_labels as $key => $label): ?>
            <option value="<?= $key ?>" <?= $sort_by === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($sort_by !== 'total'): ?>
        <span class="ar-sort-active-pill">
            <i class="fas fa-filter"></i>
            Sorted by <?= $sort_labels[$sort_by] ?>
        </span>
        <?php endif; ?>
    </form>

    <!-- Ranking Table -->
    <?php if (!empty($ranking)): ?>
    <div style="overflow-x:auto">
        <table class="ar-rank-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th class="<?= $sort_by === 'attendance' ? 'sort-active' : '' ?>">Attendance</th>
                    <th class="<?= $sort_by === 'projects' ? 'sort-active' : '' ?>">Projects</th>
                    <th class="<?= $sort_by === 'payments' ? 'sort-active' : '' ?>">Payment</th>
                    <th class="<?= $sort_by === 'manual' ? 'sort-active' : '' ?>">Manual</th>
                    <th class="<?= $sort_by === 'quiz' ? 'sort-active' : '' ?>">Quiz</th>
                    <th class="<?= $sort_by === 'total' ? 'sort-active' : '' ?>">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $display_rank = 1;
            foreach ($ranking as $sid => $rd):
                $has_photo = !empty($rd['photo']) && file_exists($rd['photo']);
                $topClass = $display_rank === 1 ? 'top-1' : ($display_rank === 2 ? 'top-2' : ($display_rank === 3 ? 'top-3' : ''));
            ?>
                <tr onclick="window.location.href='student_details.php?id=<?= $sid ?>'">
                    <td>
                        <div class="ar-rank-num <?= $topClass ?>">
                            <?php if ($display_rank <= 3): ?>
                                <i class="fas fa-<?= $display_rank === 1 ? 'crown' : 'medal' ?>"></i>
                            <?php else: ?>
                                <?= $display_rank ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="ar-rank-student">
                            <div class="ar-rank-avatar">
                                <?php if ($has_photo): ?>
                                    <img src="<?= htmlspecialchars($rd['photo']) ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user-graduate"></i>
                                <?php endif; ?>
                            </div>
                            <span class="ar-rank-sname"><?= htmlspecialchars($rd['full_name']) ?></span>
                        </div>
                    </td>
                    <td><span class="ar-points-cell <?= $sort_by === 'attendance' ? 'highlight' : '' ?>"><?= $rd['attendance_points'] ?></span></td>
                    <td><span class="ar-points-cell <?= $sort_by === 'projects' ? 'highlight' : '' ?>"><?= $rd['project_points'] ?></span></td>
                    <td><span class="ar-points-cell <?= $sort_by === 'payments' ? 'highlight' : '' ?>"><?= $rd['payment_points'] ?></span></td>
                    <td><span class="ar-points-cell <?= $sort_by === 'manual' ? 'highlight' : '' ?>"><?= $rd['manual_points'] ?></span></td>
                    <td><span class="ar-points-cell <?= $sort_by === 'quiz' ? 'highlight' : '' ?>"><?= $rd['quiz_points'] ?></span></td>
                    <td><span class="ar-points-total"><?= $rd['total_points'] ?></span></td>
                </tr>
            <?php
                $display_rank++;
            endforeach;
            ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="ar-empty">
        <i class="fas fa-trophy"></i>
        No ranking data available for this month.
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════
     HIDDEN EXPORT TABLE
═══════════════════════════════════════ -->
<table id="exportTable" style="display:none">
    <thead>
        <tr>
            <th>Student Code</th><th>Name</th><th>Batch</th>
            <th>Status</th><th>Check-in Time</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($exportStudents as $es): ?>
    <tr>
        <td><?= $es['student_code'] ?></td>
        <td><?= htmlspecialchars($es['full_name']) ?></td>
        <td><?= htmlspecialchars($es['batch']) ?></td>
        <td><?= $es['att_status'] ?: 'Absent' ?></td>
        <td><?= !empty($es['check_in_time']) ? date('h:i A', strtotime($es['check_in_time'])) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- ═══════════════════════════════════════
     HOLIDAY CONFIRM MODAL
═══════════════════════════════════════ -->
<div class="ar-modal-overlay" id="holidayModal">
    <div class="ar-modal">
        <h3><i class="fas fa-umbrella-beach"></i> Declare Holiday</h3>
        <p>Declare <strong><?= date('l, d F Y', strtotime($filter_date)) ?></strong> as a holiday for <span id="modalScope">all selected students</span>?</p>
        <div class="warn-box" id="modalWarn" style="display:none">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="modalWarnText"></span>
        </div>
        <p style="font-size:12px;color:var(--text-3)">Holiday gives +2 points and does not break the attendance streak. Existing attendance records for this date will be changed to Holiday.</p>
        <div class="ar-modal-actions">
            <button class="ar-btn secondary" onclick="closeHolidayModal()">Cancel</button>
            <button class="ar-btn holiday-btn" id="confirmHolidayBtn" onclick="declareHoliday()">
                <i class="fas fa-check"></i> Confirm Holiday
            </button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="ar-toast" id="arToast"></div>

<script>
// CSV export utility
function exportTableToCSV(tableId, filename) {
    var tbl = document.getElementById(tableId);
    if (!tbl) { alert('Export table not found'); return; }
    var rows = tbl.querySelectorAll('tr');
    var csv  = [];
    rows.forEach(function(row) {
        var cols = row.querySelectorAll('th, td');
        var rowData = Array.from(cols).map(function(c) {
            return '"' + c.innerText.replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(','));
    });
    var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// ── Holiday JS ──
const HOLIDAY_DATE  = '<?= htmlspecialchars($filter_date) ?>';
const HOLIDAY_BATCH = '<?= htmlspecialchars($sel_batch) ?>';
const HOLIDAY_GROUP = <?= (int)$sel_group ?>;

function showToast(msg, type) {
    const t = document.getElementById('arToast');
    t.className = 'ar-toast ' + type;
    t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle') + '"></i> ' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 4000);
}

function openHolidayModal() {
    // First check holiday status
    const params = new URLSearchParams({
        action: 'check_holiday',
        date: HOLIDAY_DATE,
        batch: HOLIDAY_BATCH,
        group_id: HOLIDAY_GROUP
    });
    fetch('ajax/declare_holiday.php?' + params)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Error checking holiday status', 'error');
                return;
            }
            if (data.status === 'already_holiday') {
                showToast('This date is already declared as a holiday for all selected students.', 'info');
                return;
            }
            if (data.status === 'no_students') {
                showToast('No students found for the selected filters.', 'error');
                return;
            }
            // Build scope description
            let scope = data.total + ' student(s)';
            if (HOLIDAY_BATCH) scope += ' in ' + HOLIDAY_BATCH + ' batch';
            if (HOLIDAY_GROUP > 0) scope += ' in selected group';
            document.getElementById('modalScope').textContent = scope;

            // Show warning if existing records
            const warn = document.getElementById('modalWarn');
            const warnText = document.getElementById('modalWarnText');
            if (data.existing_count > 0) {
                warnText.textContent = data.existing_count + ' student(s) already have attendance records (Present/Absent) for this date. These will be changed to Holiday.';
                warn.style.display = 'flex';
            } else if (data.holiday_count > 0) {
                warnText.textContent = data.holiday_count + ' of ' + data.total + ' student(s) are already marked Holiday. Only the remaining will be updated.';
                warn.style.display = 'flex';
            } else {
                warn.style.display = 'none';
            }

            document.getElementById('holidayModal').classList.add('show');
        })
        .catch(() => showToast('Network error. Please try again.', 'error'));
}

function closeHolidayModal() {
    document.getElementById('holidayModal').classList.remove('show');
}

function declareHoliday() {
    const btn = document.getElementById('confirmHolidayBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Declaring...';

    const formData = new FormData();
    formData.append('action', 'declare_holiday');
    formData.append('date', HOLIDAY_DATE);
    formData.append('batch', HOLIDAY_BATCH);
    formData.append('group_id', HOLIDAY_GROUP);

    fetch('ajax/declare_holiday.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            closeHolidayModal();
            if (data.success) {
                showToast(data.message, data.affected_count > 0 ? 'success' : 'info');
                // Refresh after short delay
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.message || 'Failed to declare holiday.', 'error');
            }
        })
        .catch(() => {
            closeHolidayModal();
            showToast('Network error. Please try again.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirm Holiday';
        });
}
</script>

<?php include 'includes/footer.php'; ?>