<?php
require_once '../admin/config/database.php';
require_once './student_auth.php';
require_once '../admin/includes/ranking_helper.php';
requireStudentLogin();

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$student = getCurrentStudent();

// ════════════════════════════════════════════════════
//  SAFE QUERY HELPERS  (never crash on bad result)
// ════════════════════════════════════════════════════
function safeRow($conn, $sql)
{
    $r = $conn->query($sql);
    return ($r && $r !== true) ? $r->fetch_assoc() : null;
}
function safeVal($conn, $sql, $col, $default = 0)
{
    $row = safeRow($conn, $sql);
    return isset($row[$col]) ? $row[$col] : $default;
}
function safeRows($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r || $r === true)
        return [];
    $out = [];
    while ($row = $r->fetch_assoc())
        $out[] = $row;
    return $out;
}

$sid = (int) $student['id'];

// ════════════════════════════════════════════════════
//  STUDENTS — real columns: full_name, student_code,
//  course_id, duration_months, total_fees,
//  enrollment_date, completion_date, status='Active'
// ════════════════════════════════════════════════════
$student_data = safeRow($conn, "SELECT * FROM students WHERE id = $sid");
if (!$student_data) {
    $student_data = [
        'full_name' => $student['name'] ?? 'Student',
        'student_code' => $student['code'] ?? '',
        'course_id' => 0,
        'duration_months' => 0,
        'total_fees' => 0,
        'enrollment_date' => null,
        'completion_date' => null,
        'status' => 'Active',
    ];
}

// ════════════════════════════════════════════════════
//  UNREAD NOTIFICATIONS
// ════════════════════════════════════════════════════
$unread_count = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS cnt FROM student_notifications
     WHERE student_id = $sid AND is_read = 0",
    'cnt',
    0
);

// ════════════════════════════════════════════════════
//  ATTENDANCE TODAY — full row for check-in/out state
// ════════════════════════════════════════════════════
$today = date('Y-m-d');
$today_attendance = safeRow(
    $conn,
    "SELECT * FROM student_attendance
     WHERE student_id = $sid AND attendance_date = '$today'
     LIMIT 1"
);
$already_checked_in = !empty($today_attendance);
$already_checked_out = !empty($today_attendance['check_out_time']);
$already_marked_today = $already_checked_in; // sidebar badge compat

// ════════════════════════════════════════════════════
//  COURSE DATA
// ════════════════════════════════════════════════════
$course_id = (int) ($student_data['course_id'] ?? 0);
$course_data = null;
if ($course_id > 0) {
    $course_data = safeRow($conn, "SELECT * FROM courses WHERE id = $course_id");
}

// ════════════════════════════════════════════════════
//  RANKING — EXACT SAME LOGIC AS ../admin/ranking.php
//
//  Formula (identical to admin ranking page):
//    payment_points  = 10 if paid this calendar month, else 0
//    project_points  = completed_projects × (15 if Web Development, else 6)
//    attendance_pts  = present days this calendar month (1 pt each)
//    manual_points   = SUM(student_manual_points.points)
//    total_points    = payment + project + attendance + manual
//
//  Conditions (identical to admin ranking page):
//    s.status = 'Active' AND s.login_enabled = 1
//
//  Sort (identical to admin ranking page):
//    ORDER BY total_points DESC, s.full_name ASC
// ════════════════════════════════════════════════════
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

// $ranking_sql = "
//     SELECT
//         s.id,
//         s.full_name,
//         s.photo,

//         -- Payment points: 10 if any payment this month
//         (CASE
//             WHEN EXISTS(
//                 SELECT 1 FROM payments p
//                 WHERE p.student_id = s.id
//                 AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
//             ) THEN 10 ELSE 0
//         END) AS payment_points,

//         -- Project points: ×15 for Web Development, ×6 for others
//         (SELECT COUNT(*)
//          FROM student_projects sp
//          WHERE sp.student_id = s.id
//          AND sp.status = 'Completed'
//         ) * (CASE WHEN cat.name = 'Web Development' THEN 15 ELSE 6 END) AS project_points,

//         -- Attendance points: 1 per present day this month
//         (SELECT COUNT(*)
//          FROM student_attendance sa
//          WHERE sa.student_id = s.id
//          AND sa.status = 'Present'
//          AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
//         ) AS attendance_points,

//         -- Manual points
//         COALESCE((
//             SELECT SUM(points)
//             FROM student_manual_points smp
//             WHERE smp.student_id = s.id
//         ), 0) AS manual_points,

//         -- TOTAL POINTS (exact same calculation as admin/ranking.php)
//         (
//             (CASE
//                 WHEN EXISTS(
//                     SELECT 1 FROM payments p
//                     WHERE p.student_id = s.id
//                     AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
//                 ) THEN 10 ELSE 0
//             END)
//             +
//             (SELECT COUNT(*)
//              FROM student_projects sp
//              WHERE sp.student_id = s.id
//              AND sp.status = 'Completed'
//             ) * (CASE WHEN cat.name = 'Web Development' THEN 15 ELSE 6 END)
//             +
//             (SELECT COUNT(*)
//              FROM student_attendance sa
//              WHERE sa.student_id = s.id
//              AND sa.status = 'Present'
//              AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
//             )
//             +
//             COALESCE((
//                 SELECT SUM(points)
//                 FROM student_manual_points smp
//                 WHERE smp.student_id = s.id
//             ), 0)
//         ) AS total_points

//     FROM students s
//     JOIN courses c   ON s.course_id   = c.id
//     JOIN categories cat ON s.category_id = cat.id
//     WHERE s.status = 'Active' AND s.login_enabled = 1
//     ORDER BY total_points DESC, s.full_name ASC
// ";

// $ranking_result = $conn->query($ranking_sql);

// ── Process ranking results ──────────────────────────
// $rank        = 1;
// $my_rank     = 0;
// $my_points   = 0;
// $top_students = [];

// if ($ranking_result && $ranking_result !== true) {
//     while ($row = $ranking_result->fetch_assoc()) {

//         // Top 10 for leaderboard
//         if (count($top_students) < 10) {
//             $top_students[] = [
//                 'id'           => $row['id'],
//                 'name'         => $row['full_name'],
//                 'photo'        => $row['photo'],   // raw path as stored in DB (same as ranking.php)
//                 'total_points' => (int)$row['total_points'],
//                 'rank'         => $rank,
//             ];
//         }

//         // Find logged-in student's rank and points
//         if ((int)$row['id'] === $sid) {
//             $my_rank   = $rank;
//             $my_points = (int)$row['total_points'];
//         }

//         $rank++;
//     }
// }

// $sotm = !empty($top_students) ? $top_students[0] : null;
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

$ranking = getMonthlyRanking($conn, $current_month_start, $current_month_end);

$my_rank = $ranking[$sid]['rank'] ?? 0;
$my_points = $ranking[$sid]['total_points'] ?? 0;

$top_students = [];

foreach ($ranking as $student_id => $data) {
    if (count($top_students) >= 10)
        break;

    $top_students[] = [
        'id' => $student_id,
        'name' => $data['full_name'],
        'photo' => $data['photo'],
        'total_points' => $data['total_points'],
        'rank' => $data['rank'],
    ];
}

$sotm = !empty($top_students) ? $top_students[0] : null;
// ════════════════════════════════════════════════════
//  PAYMENTS
// ════════════════════════════════════════════════════
$total_fees = floatval($student_data['total_fees'] ?? 0);
$paid_amount = floatval(safeVal(
    $conn,
    "SELECT COALESCE(SUM(amount_paid), 0) AS paid
     FROM payments WHERE student_id = $sid",
    'paid',
    0
));
$last_payment = safeRow(
    $conn,
    "SELECT amount_paid, payment_date, payment_method
     FROM payments WHERE student_id = $sid
     ORDER BY payment_date DESC LIMIT 1"
);

if ($total_fees <= 0 && $paid_amount > 0)
    $total_fees = $paid_amount;
$outstanding = max(0, $total_fees - $paid_amount);
$fees_percent = $total_fees > 0
    ? min(100, (int) round(($paid_amount / $total_fees) * 100))
    : 0;

$next_due_date = null;
if ($student_data['enrollment_date'] && $student_data['monthly_due_day'] ?? false) {
    $next_due_date = date('Y-m-d', strtotime(
        'first day of next month +' . ((int) ($student_data['monthly_due_day'] ?? 1) - 1) . ' days'
    ));
}

// ════════════════════════════════════════════════════
//  ATTENDANCE STATS (monthly)
// ════════════════════════════════════════════════════
$current_month = date('Y-m');
$monthly_attendance = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS cnt FROM student_attendance
     WHERE student_id = $sid
       AND status IN ('Present','Holiday')
       AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'",
    'cnt',
    0
);
$total_working_days = (int) safeVal(
    $conn,
    "SELECT COUNT(DISTINCT attendance_date) AS days
     FROM student_attendance
     WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'",
    'days',
    0
);
$attendance_percent = $total_working_days > 0
    ? (int) round(($monthly_attendance / $total_working_days) * 100)
    : 0;

// ════════════════════════════════════════════════════
//  COURSE PROGRESS (via group_topic_progress)
// ════════════════════════════════════════════════════
$total_topics = 0;
$completed_topics = 0;
$course_progress = 0;
if ($course_id > 0) {
    $total_topics = (int) safeVal(
        $conn,
        "SELECT COUNT(*) AS c FROM course_topics
         WHERE course_id = $course_id AND status = 'active'",
        'c',
        0
    );

    $group_row = safeRow(
        $conn,
        "SELECT group_id FROM student_group_members WHERE student_id = $sid LIMIT 1"
    );
    if ($group_row) {
        $gid = (int) $group_row['group_id'];
        $completed_topics = (int) safeVal(
            $conn,
            "SELECT COUNT(*) AS c FROM group_topic_progress
             WHERE group_id = $gid AND status = 'completed'",
            'c',
            0
        );
    }
    $course_progress = $total_topics > 0
        ? min(100, (int) round(($completed_topics / $total_topics) * 100))
        : 0;
}

// ════════════════════════════════════════════════════
//  PROJECTS (with verification status awareness)
// ════════════════════════════════════════════════════
$completed_projects = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS c FROM student_projects
     WHERE student_id = $sid AND status = 'Completed'",
    'c',
    0
);
$verified_projects = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS c FROM student_projects
     WHERE student_id = $sid AND verification_status = 'verified'",
    'c',
    0
);
$pending_verification = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS c FROM student_projects
     WHERE student_id = $sid AND verification_status = 'pending'",
    'c',
    0
);
$total_submissions = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS c FROM student_projects WHERE student_id = $sid",
    'c',
    0
);
$month_name = date('M');

// ════════════════════════════════════════════════════
//  TASK MANAGER PROJECTS (student_projects table — unified)
//  OPTIMIZED: Pre-fetch logs in bulk to avoid N+1 queries
// ════════════════════════════════════════════════════
$task_projects = [];
$task_overdue = false;
try {
    $tp_res = $conn->query("
        SELECT sp.*,
            (SELECT COUNT(*) FROM project_daily_logs WHERE project_id = sp.id) AS total_days,
            (SELECT MAX(log_date) FROM project_daily_logs WHERE project_id = sp.id) AS last_log_date
        FROM student_projects sp
        WHERE sp.student_id = $sid AND sp.status = 'In Progress'
        ORDER BY sp.created_at DESC
    ");
    if ($tp_res && $tp_res->num_rows > 0) {
        // Collect all project IDs first
        $tmp_projects = [];
        while ($tp = $tp_res->fetch_assoc()) {
            $tmp_projects[$tp['id']] = $tp;
        }
        $pids = array_keys($tmp_projects);
        $pids_str = implode(',', array_map('intval', $pids));

        // Bulk fetch: today's logs for all projects (replaces N per-project queries)
        $today_logs = [];
        $tl_res = $conn->query("SELECT project_id, id FROM project_daily_logs WHERE project_id IN ($pids_str) AND log_date='$today'");
        if ($tl_res) while ($tl = $tl_res->fetch_assoc()) $today_logs[(int)$tl['project_id']] = true;

        // Bulk fetch: last 3 logs per project using a ranked approach
        $last3_by_project = [];
        $l3_res = $conn->query("
            SELECT dl.* FROM project_daily_logs dl
            WHERE dl.project_id IN ($pids_str)
            AND (
                SELECT COUNT(*) FROM project_daily_logs dl2
                WHERE dl2.project_id = dl.project_id AND dl2.log_date > dl.log_date
            ) < 3
            ORDER BY dl.project_id, dl.log_date DESC
        ");
        if ($l3_res) while ($lr = $l3_res->fetch_assoc()) $last3_by_project[(int)$lr['project_id']][] = $lr;

        // Assemble results using pre-fetched data (no more per-project queries)
        foreach ($tmp_projects as $pid => $tp) {
            $tp['current_day'] = max(1, (int) ((strtotime($today) - strtotime($tp['start_date'])) / 86400) + 1);
            $tp['has_today'] = isset($today_logs[$pid]);
            if (!$tp['has_today'])
                $task_overdue = true;
            $tp['last3'] = $last3_by_project[$pid] ?? [];
            $task_projects[] = $tp;
        }
    }
} catch (Exception $e) { /* table may not exist */ }

// ════════════════════════════════════════════════════
//  COURSE DURATION
// ════════════════════════════════════════════════════
$start_date = $student_data['enrollment_date'] ?? null;
$duration_months = (int) ($student_data['duration_months'] ?? 0);
$end_date = null;
if ($start_date && $duration_months > 0) {
    try {
        $end_date = (new DateTime($start_date))
            ->modify("+$duration_months months")
            ->format('Y-m-d');
    } catch (Exception $e) {
        $end_date = null;
    }
}

if ($start_date && $end_date) {
    try {
        $d1 = new DateTime($start_date);
        $d2 = new DateTime($end_date);
        $diff = $d1->diff($d2);
        $duration_months = $diff->m + ($diff->y * 12);
    } catch (Exception $e) {
        $duration_months = '';
    }
}

$current_month_check = date('Y-m');

$paid_this_month = (int) safeVal(
    $conn,
    "SELECT COUNT(*) AS cnt
     FROM payments
     WHERE student_id = $sid
     AND DATE_FORMAT(payment_date, '%Y-%m') = '$current_month_check'",
    'cnt',
    0
);

$emi_pending_this_month = ($paid_this_month == 0 && $outstanding > 0);

// ════════════════════════════════════════════════════
//  PHOTO HELPER
//  ranking.php uses file_exists($photo) directly,
//  meaning photos are stored as paths relative to the
//  admin/ folder root (e.g. "uploads/photos/xyz.jpg").
//  From dashboard.php (student/), the admin root is ../admin/
// ════════════════════════════════════════════════════
function resolvePhotoSrc($photo)
{
    if (empty($photo))
        return null;
    // Check as stored path relative to admin root
    $admin_relative = __DIR__ . '/../admin/' . ltrim($photo, '/');
    if (file_exists($admin_relative)) {
        return '../admin/' . ltrim($photo, '/');
    }
    // Check as path relative to project root (some installs store absolute-like paths)
    if (file_exists($photo)) {
        return $photo;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Student Portal</title>
    <link rel="icon" type="image/png" href="../skill-development.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg: #0d0b1e;
            --bg2: #120f2d;
            --surface: rgba(255, 255, 255, 0.04);
            --surface2: rgba(255, 255, 255, 0.07);
            --border: rgba(255, 255, 255, 0.08);
            --border2: rgba(255, 255, 255, 0.14);
            --purple: #7c3aed;
            --purple-light: #a78bfa;
            --indigo: #4f46e5;
            --green: #10b981;
            --green-glow: rgba(16, 185, 129, 0.25);
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.25);
            --yellow: #f59e0b;
            --yellow-glow: rgba(245, 158, 11, 0.2);
            --cyan: #06b6d4;
            --text: #f1f0ff;
            --text-muted: rgba(241, 240, 255, 0.45);
            --sidebar-w: 68px;
            --right-w: 270px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 15% 20%, rgba(124, 58, 237, 0.18) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 85% 80%, rgba(79, 70, 229, 0.15) 0%, transparent 60%),
                radial-gradient(ellipse 35% 30% at 50% 50%, rgba(6, 182, 212, 0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-w);
            background: rgba(13, 11, 30, 0.9);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0;
            z-index: 100;
            gap: 4px;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--purple), var(--indigo));
            b//  OPTIMIZED: Pre-fetch logs in bulk to avoid N+1 queriesorder-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 20px;
            box-shadow: 0 0 20px rgba(124, 58, 237, 0.4);
        }

        .sidebar-item {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 17px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .sidebar-item:hover,
        .sidebar-item.active {
            background: rgba(124, 58, 237, 0.2);
            color: var(--purple-light);
            box-shadow: 0 0 12px rgba(124, 58, 237, 0.2);
        }

        .sidebar-item.active {
            background: rgba(124, 58, 237, 0.25);
            color: #fff;
        }

        .sidebar-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--bg);
        }

        .sidebar-tooltip {
            position: absolute;
            left: calc(100% + 14px);
            background: rgba(30, 27, 60, 0.95);
            border: 1px solid var(--border2);
            color: var(--text);
            font-size: 12px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 8px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transform: translateX(-6px);
            transition: all 0.2s;
            z-index: 200;
        }

        .sidebar-item:hover .sidebar-tooltip {
            opacity: 1;
            transform: translateX(0);
        }

        .sidebar-divider {
            width: 28px;
            height: 1px;
            background: var(--border);
            margin: 8px 0;
        }

        .sidebar-spacer {
            flex: 1;
        }

        /* ─── LAYOUT ─── */
        .app-shell {
            position: relative;
            z-index: 1;
            margin-left: var(--sidebar-w);
            margin-right: var(--right-w);
            min-height: 100vh;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* ─── RIGHT PANEL ─── */
        .right-panel {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: var(--right-w);
            background: rgba(13, 11, 30, 0.92);
            border-left: 1px solid var(--border);
            backdrop-filter: blur(20px);
            padding: 18px 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 90;
            overflow-y: auto;
        }

        /* ─── GLASS CARD ─── */
        .card-glass {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            backdrop-filter: blur(16px);
        }

        .card-glass:hover {
            border-color: var(--border2);
        }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .greeting-text {
            font-family: 'Poppins', sans-serif;
        }

        .greeting-text h1 {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            background: linear-gradient(120deg, #fff 30%, var(--purple-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .greeting-text p {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .top-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-check-in,
        .btn-check-out {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            font-family: 'Poppins', sans-serif;
            position: relative;
            white-space: nowrap;
        }

        .btn-check-in {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            box-shadow: 0 4px 16px var(--green-glow);
        }

        .btn-check-in:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 22px rgba(16, 185, 129, 0.4);
        }

        .btn-check-out {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
            box-shadow: 0 4px 16px var(--red-glow);
        }

        .btn-check-out:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 22px rgba(239, 68, 68, 0.4);
        }

        .btn-check-in:disabled,
        .btn-check-out:disabled {
            opacity: 0.38;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ── time labels under buttons ── */
        .att-btn-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .att-time-label {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 500;
            min-height: 13px;
            transition: opacity 0.3s;
        }

        /* ── toast ── */
        #att-toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(20, 18, 48, 0.97);
            border: 1px solid var(--border2);
            color: var(--text);
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            padding: 11px 22px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s, transform 0.3s;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        #att-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        #att-toast.toast-success {
            border-color: rgba(16, 185, 129, 0.55);
        }

        #att-toast.toast-error {
            border-color: rgba(239, 68, 68, 0.55);
        }

        .notif-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--surface2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            position: relative;
            font-size: 15px;
            transition: all 0.2s;
        }

        .notif-btn:hover {
            color: var(--purple-light);
            border-color: var(--purple);
        }

        .notif-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            background: var(--red);
            border-radius: 50%;
            border: 1.5px solid var(--bg);
        }

        /* ─── MAIN GRID ─── */
        .main-grid {
            display: grid;
            grid-template-columns: 230px 1fr;
            gap: 14px;
            flex: 1;
        }

        /* ─── PROFILE CARD ─── */
        .profile-card {
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: linear-gradient(160deg, rgba(124, 58, 237, 0.12) 0%, rgba(79, 70, 229, 0.06) 100%);
            border-color: rgba(124, 58, 237, 0.25);
        }

        .avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .avatar-ring {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--indigo));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.3), 0 0 30px rgba(124, 58, 237, 0.25);
            position: relative;
        }

        .avatar-status {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 14px;
            height: 14px;
            background: var(--green);
            border-radius: 50%;
            border: 2.5px solid var(--bg);
            box-shadow: 0 0 8px var(--green);
        }

        .student-name {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            line-height: 1.2;
        }

        .student-course {
            font-size: 11.5px;
            color: var(--purple-light);
            text-align: center;
            font-weight: 500;
        }

        .rank-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(124, 58, 237, 0.12);
            border: 1px solid rgba(124, 58, 237, 0.25);
            border-radius: 12px;
            padding: 10px 14px;
        }

        .rank-num {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--yellow);
            line-height: 1;
        }

        .rank-label {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .rank-pts {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--purple-light);
        }

        .rank-pts-label {
            font-size: 10px;
            color: var(--text-muted);
            text-align: right;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        /* ─── INFO BOXES ─── */
        .info-boxes {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .info-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 12px;
        }

        .info-box-label {
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-box-label i {
            font-size: 11px;
            width: 14px;
        }

        .info-box-val {
            font-weight: 700;
            font-size: 12.5px;
            color: var(--text);
        }

        .info-box-val.green {
            color: var(--green);
        }

        .info-box-val.red {
            color: #f87171;
        }

        .info-box-val.yellow {
            color: var(--yellow);
        }

        /* ─── CENTER COLUMN ─── */
        .center-col {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* ─── STAT ROW ─── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .stat-mini {
            padding: 14px 14px 12px;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .stat-mini::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 14px;
            opacity: 0.08;
        }

        .stat-mini.s-purple {
            background: rgba(124, 58, 237, 0.1);
            border: 1px solid rgba(124, 58, 237, 0.2);
        }

        .stat-mini.s-purple::before {
            background: var(--purple);
        }

        .stat-mini.s-green {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.18);
        }

        .stat-mini.s-cyan {
            background: rgba(6, 182, 212, 0.08);
            border: 1px solid rgba(6, 182, 212, 0.18);
        }

        .stat-mini.s-yellow {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .stat-mini-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .s-purple .stat-mini-icon {
            background: rgba(124, 58, 237, 0.2);
            color: var(--purple-light);
        }

        .s-green .stat-mini-icon {
            background: rgba(16, 185, 129, 0.15);
            color: var(--green);
        }

        .s-cyan .stat-mini-icon {
            background: rgba(6, 182, 212, 0.15);
            color: var(--cyan);
        }

        .s-yellow .stat-mini-icon {
            background: rgba(245, 158, 11, 0.15);
            color: var(--yellow);
        }

        .stat-mini-val {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .s-purple .stat-mini-val {
            color: var(--purple-light);
        }

        .s-green .stat-mini-val {
            color: var(--green);
        }

        .s-cyan .stat-mini-val {
            color: var(--cyan);
        }

        .s-yellow .stat-mini-val {
            color: var(--yellow);
        }

        .stat-mini-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-mini-sub {
            font-size: 10px;
            color: var(--text-muted);
            opacity: 0.7;
        }

        /* ─── ATTENDANCE % HERO ─── */
        .attendance-hero {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.18);
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .att-big-pct {
            font-family: 'Poppins', sans-serif;
            font-size: 44px;
            font-weight: 700;
            color: var(--green);
            line-height: 1;
        }

        .att-big-pct span {
            font-size: 22px;
            letter-spacing: 0;
        }

        .att-meta {
            font-size: 12px;
            color: var(--text-muted);
        }

        .att-meta strong {
            color: var(--text);
            font-size: 14px;
        }

        .att-bar-wrap {
            flex: 1;
            margin: 0 20px;
        }

        .att-bar-track {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.07);
            border-radius: 99px;
            overflow: hidden;
            margin-top: 6px;
        }

        .att-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, var(--green), #34d399);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
            transition: width 1.2s ease;
        }

        /* ─── PROGRESS CIRCLES ROW ─── */
        .circles-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .circle-card {
            padding: 18px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .circle-card.c-purple {
            background: rgba(124, 58, 237, 0.08);
            border: 1px solid rgba(124, 58, 237, 0.2);
        }

        .circle-card.c-blue {
            background: rgba(6, 182, 212, 0.08);
            border: 1px solid rgba(6, 182, 212, 0.18);
        }

        .circle-wrap {
            flex-shrink: 0;
            position: relative;
            width: 72px;
            height: 72px;
        }

        .circle-svg {
            transform: rotate(-90deg);
        }

        .circle-track {
            stroke: rgba(255, 255, 255, 0.07);
        }

        .circle-fill {
            stroke-dashoffset: 226;
            stroke-linecap: round;
            transition: stroke-dashoffset 1.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .c-purple .circle-fill {
            stroke: url(#gradPurple);
        }

        .c-blue .circle-fill {
            stroke: url(#gradCyan);
        }

        .circle-label {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .circle-pct {
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            font-weight: 800;
            line-height: 1;
        }

        .c-purple .circle-pct {
            color: var(--purple-light);
        }

        .c-blue .circle-pct {
            color: var(--cyan);
        }

        .circle-pct-sym {
            font-size: 10px;
            font-weight: 500;
        }

        .circle-info h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .circle-info p {
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* ─── EMI ALERT ─── */
        .emi-card {
            background: rgba(239, 68, 68, 0.07);
            border: 1px solid rgba(239, 68, 68, 0.22);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .emi-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--red);
            flex-shrink: 0;
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }
        }

        .emi-info h5 {
            font-size: 13px;
            font-weight: 700;
            color: #f87171;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .emi-info p {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .emi-info strong {
            color: var(--text);
        }

        .emi-badge {
            margin-left: auto;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        /* ─── RIGHT PANEL CARDS ─── */
        .sotm-card {
            background: linear-gradient(140deg, rgba(245, 158, 11, 0.12) 0%, rgba(124, 58, 237, 0.1) 100%);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sotm-card::after {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), transparent, rgba(124, 58, 237, 0.15));
            pointer-events: none;
        }

        .sotm-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--yellow);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .sotm-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--yellow), #f97316);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            margin: 0 auto 8px;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25), 0 0 20px rgba(245, 158, 11, 0.2);
            overflow: hidden;
        }

        .sotm-name {
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 800;
        }

        .sotm-pts {
            font-size: 11px;
            color: var(--yellow);
            font-weight: 600;
            margin-top: 3px;
        }

        .sotm-rank-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: var(--yellow);
            margin-top: 6px;
        }

        .leaderboard-header {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .lb-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .lb-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 10px;
            border-radius: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        /* ── Highlight for the logged-in student in Top 10 ── */
        .lb-item.is-me {
            background: rgba(124, 58, 237, 0.15);
            border-color: rgba(124, 58, 237, 0.45);
            box-shadow: 0 0 16px rgba(124, 58, 237, 0.18), inset 0 0 12px rgba(124, 58, 237, 0.06);
        }

        .lb-rank {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 700;
            width: 20px;
            text-align: center;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .lb-rank.gold {
            color: #fbbf24;
        }

        .lb-rank.silver {
            color: #94a3b8;
        }

        .lb-rank.bronze {
            color: #d97706;
        }

        .lb-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--indigo));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            overflow: hidden;
        }

        .lb-item.is-me .lb-avatar {
            background: linear-gradient(135deg, #7c3aed, #06b6d4);
        }

        .lb-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .lb-name {
            font-size: 12px;
            font-weight: 600;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lb-pts {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: var(--purple-light);
        }

        .lb-item.is-me .lb-pts {
            color: var(--cyan);
        }

        /* ─── SCROLLBAR ─── */
        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(124, 58, 237, 0.3);
            border-radius: 99px;
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp 0.5s ease both;
        }

        .delay-1 {
            animation-delay: 0.07s;
        }

        .delay-2 {
            animation-delay: 0.14s;
        }

        .delay-3 {
            animation-delay: 0.21s;
        }

        .delay-4 {
            animation-delay: 0.28s;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1100px) {
            :root {
                --right-w: 0px;
            }

            .right-panel {
                display: none;
            }
        }

        @media (max-width: 780px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .stat-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .circles-row {
                grid-template-columns: 1fr;
            }
        }

        /* ─── ATTENDANCE HISTORY SECTION ─── */
        .att-history-card {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 16px;
            overflow: hidden;
        }

        .att-history-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            flex-wrap: wrap;
            gap: 8px;
        }

        .att-history-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .att-history-title i {
            color: var(--green);
            font-size: 12px;
        }

        .att-hist-filter-grp {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .afhf-btn {
            padding: 5px 13px;
            font-size: 11.5px;
            font-weight: 600;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'Poppins', sans-serif;
        }

        .afhf-btn.active,
        .afhf-btn:hover {
            background: var(--green);
            color: #fff;
        }

        .att-history-body {
            padding: 14px 16px;
        }

        .att-hist-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .att-hist-stat-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            transition: all 0.18s;
        }

        .att-hist-stat-box:hover {
            border-color: rgba(255, 255, 255, 0.16);
            transform: translateY(-1px);
        }

        .att-hist-val {
            font-family: 'Poppins', sans-serif;
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .att-hist-lbl {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-top: 5px;
        }

        .att-hist-period {
            font-size: 10.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .att-hist-period i {
            color: var(--green);
        }

        .att-hist-loading {
            text-align: center;
            padding: 12px;
            display: none;
        }

        .att-hist-chart-wrap {
            position: relative;
            height: 145px;
            transition: opacity 0.3s;
        }

        @media (max-width: 600px) {
            .att-hist-stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <!-- ═══ SIDEBAR ═══ -->
    <nav class="sidebar">
        <div class="sidebar-logo"><i class="fas fa-graduation-cap"></i></div>

        <a href="dashboard.php" class="sidebar-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span class="sidebar-tooltip">Dashboard</span>
        </a>
        <a href="attendance.php" class="sidebar-item <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <?php if (!$already_marked_today): ?><span class="sidebar-badge"></span><?php endif; ?>
            <span class="sidebar-tooltip">Attendance</span>
        </a>
        <a href="activities.php" class="sidebar-item <?php echo $current_page === 'activities' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-day"></i>
            <span class="sidebar-tooltip">Activities</span>
        </a>
        <a href="course_progress.php" class="sidebar-item <?php echo $current_page === 'course_progress' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span class="sidebar-tooltip">Course Progress</span>
        </a>
        <a href="projects.php" class="sidebar-item <?php echo $current_page === 'projects' ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span class="sidebar-tooltip">Projects</span>
        </a>
        <a href="task_manager.php" class="sidebar-item <?php echo $current_page === 'task_manager' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span class="sidebar-tooltip">Task Manager</span>
        </a>
        <a href="my_tests.php"
            class="sidebar-item <?php echo in_array($current_page, ['my_tests', 'take_quiz', 'quiz_result']) ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span class="sidebar-tooltip">My Tests</span>
        </a>
        <a href="typing_competition.php"
            class="sidebar-item <?php echo $current_page === 'typing_competition' ? 'active' : ''; ?>">
            <i class="fas fa-keyboard"></i>
            <span class="sidebar-tooltip">Typing Competition</span>
        </a>

        <div class="sidebar-divider"></div>

        <a href="payments.php" class="sidebar-item <?php echo $current_page === 'payments' ? 'active' : ''; ?>">
            <i class="fas fa-indian-rupee-sign"></i>
            <span class="sidebar-tooltip">Payments</span>
        </a>
        <a href="receipts.php" class="sidebar-item <?php echo $current_page === 'receipts' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <span class="sidebar-tooltip">Receipts</span>
        </a>

        <div class="sidebar-spacer"></div>

        <a href="notifications.php" class="sidebar-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0): ?><span class="sidebar-badge"></span><?php endif; ?>
            <span class="sidebar-tooltip">Notifications (<?php echo $unread_count; ?>)</span>
        </a>
        <a href="profile.php" class="sidebar-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span class="sidebar-tooltip">Profile</span>
        </a>
        <a href="logout.php" class="sidebar-item">
            <i class="fas fa-sign-out-alt"></i>
            <span class="sidebar-tooltip">Logout</span>
        </a>
    </nav>

    <!-- ═══ MAIN APP SHELL ═══ -->
    <div class="app-shell">

        <!-- TOP BAR -->
        <div class="top-bar fade-up">
            <div class="greeting-text">
                <h1>Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 18) ? 'Afternoon' : 'Evening'); ?>,
                    <?php echo htmlspecialchars(explode(' ', $student_data['full_name'])[0]); ?> 👋</h1>
                <p><?php echo date('l, d F Y'); ?> &nbsp;·&nbsp;
                    <?php echo htmlspecialchars($student_data['student_code'] ?? ''); ?></p>
            </div>
            <div class="top-actions">
                <!-- CHECK IN -->
                <div class="att-btn-group">
                    <button id="btnCheckIn" class="btn-check-in" onclick="doCheckIn()" <?php echo $already_checked_in ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i>
                        <?php echo $already_checked_in ? 'Checked In' : 'Check In'; ?>
                    </button>
                    <span class="att-time-label" id="checkInTime">
                        <?php echo ($already_checked_in && $today_attendance['check_in_time'])
                            ? date('h:i A', strtotime($today_attendance['check_in_time']))
                            : ''; ?>
                    </span>
                </div>
                <!-- OVERDUE PILL -->
                <?php if ($task_overdue && !empty($task_projects)): ?>
                    <div id="overdueWarning" style="
                display:inline-flex;align-items:center;gap:6px;
                background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);
                border-radius:10px;padding:7px 14px;
                font-size:12px;font-weight:700;color:#fca5a5;
                animation:pulseOverdue 2.5s ease-in-out infinite;
                white-space:nowrap;
            ">
                        <i class="fas fa-exclamation-triangle" style="color:#f87171"></i>
                        ⚠ Overdue — No update submitted today
                    </div>
                    <style>
                        @keyframes pulseOverdue {

                            0%,
                            100% {
                                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                            }

                            50% {
                                box-shadow: 0 0 0 5px rgba(239, 68, 68, .1);
                            }
                        }
                    </style>
                <?php endif; ?>
                <!-- CHECK OUT -->
                <div class="att-btn-group">
                    <button id="btnCheckOut" class="btn-check-out" onclick="doCheckOut()" <?php echo ($already_checked_out || !$already_checked_in) ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-out-alt"></i>
                        <?php echo $already_checked_out ? 'Checked Out' : 'Check Out'; ?>
                    </button>
                    <span class="att-time-label" id="checkOutTime">
                        <?php echo ($already_checked_out && $today_attendance['check_out_time'])
                            ? date('h:i A', strtotime($today_attendance['check_out_time']))
                            : ''; ?>
                    </span>
                </div>
                <a href="notifications.php" class="notif-btn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
                </a>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="main-grid">

            <!-- ── LEFT: PROFILE CARD ── -->
            <div class="card-glass profile-card fade-up delay-1">

                <!-- Avatar + name -->
                <div class="avatar-wrap">
                    <div class="avatar-ring" style="overflow:hidden;">
                        <?php
                        $my_photo_src = resolvePhotoSrc($student_data['photo'] ?? '');
                        if ($my_photo_src): ?>
                            <img src="<?php echo htmlspecialchars($my_photo_src); ?>"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <i class="fas fa-user-graduate"></i>
                        <?php endif; ?>
                        <div class="avatar-status"></div>
                    </div>
                    <div>
                        <div class="student-name"><?php echo htmlspecialchars($student_data['full_name']); ?></div>
                        <div class="student-course">
                            <?php echo htmlspecialchars($course_data['name'] ?? 'No Course Assigned'); ?></div>
                    </div>
                </div>

                <!-- Rank badge -->
                <div class="rank-badge">
                    <div>
                        <div class="rank-label">Rank</div>
                        <div class="rank-num">#<?php echo $my_rank > 0 ? str_pad($my_rank, 2, '0', STR_PAD_LEFT) : '--'; ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="rank-pts-label">Points</div>
                        <div class="rank-pts"><?php echo number_format($my_points); ?></div>
                    </div>
                </div>

                <!-- Info boxes -->
                <div class="info-boxes">
                    <div class="info-box">
                        <span class="info-box-label"><i class="fas fa-calendar-alt"></i> Duration</span>
                        <span class="info-box-val">
                            <?php
                            if ($start_date && $end_date) {
                                echo date('d M y', strtotime($start_date)) . ' – ' . date('d M y', strtotime($end_date));
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($duration_months): ?>
                        <div class="info-box">
                            <span class="info-box-label"><i class="fas fa-clock"></i> Period</span>
                            <span class="info-box-val"><?php echo $duration_months; ?> Months</span>
                        </div>
                    <?php endif; ?>
                    <div class="info-box">
                        <span class="info-box-label"><i class="fas fa-file-invoice"></i> Total Fees</span>
                        <span class="info-box-val">₹<?php echo number_format($total_fees, 0); ?></span>
                    </div>
                    <div class="info-box">
                        <span class="info-box-label"><i class="fas fa-check-circle"></i> Paid</span>
                        <span class="info-box-val green">₹<?php echo number_format($paid_amount, 0); ?></span>
                    </div>
                    <div class="info-box">
                        <span class="info-box-label"><i class="fas fa-exclamation-circle"></i> Outstanding</span>
                        <span
                            class="info-box-val <?php echo $outstanding > 0 ? 'red' : 'green'; ?>">₹<?php echo number_format($outstanding, 0); ?></span>
                    </div>
                </div>
            </div>

            <!-- ── CENTER COLUMN ── -->
            <div class="center-col">

                <!-- STAT MINI CARDS -->
                <div class="stat-row fade-up delay-2">
                    <div class="stat-mini s-purple">
                        <div class="stat-mini-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-mini-val"><?php echo $completed_projects; ?></div>
                        <div class="stat-mini-label">Projects Done</div>
                        <div class="stat-mini-sub"><?php echo $month_name; ?></div>
                    </div>
                    <div class="stat-mini s-green">
                        <div class="stat-mini-icon"><i class="fas fa-paper-plane"></i></div>
                        <div class="stat-mini-val"><?php echo $total_submissions; ?></div>
                        <div class="stat-mini-label">Submissions</div>
                        <div class="stat-mini-sub">Overall</div>
                    </div>
                    <div class="stat-mini s-cyan">
                        <div class="stat-mini-icon"><i class="fas fa-user-check"></i></div>
                        <div class="stat-mini-val"><?php echo $monthly_attendance; ?></div>
                        <div class="stat-mini-label">Present Days</div>
                        <div class="stat-mini-sub"><?php echo date('F'); ?></div>
                    </div>
                    <div class="stat-mini s-yellow">
                        <div class="stat-mini-icon"><i class="fas fa-trophy"></i></div>
                        <div class="stat-mini-val"><?php echo number_format($my_points); ?></div>
                        <div class="stat-mini-label">Total Points</div>
                        <div class="stat-mini-sub">Rank #<?php echo $my_rank > 0 ? $my_rank : '—'; ?></div>
                    </div>
                </div>

                <!-- ATTENDANCE HERO BAR -->
                <div class="attendance-hero fade-up delay-2">
                    <div>
                        <div
                            style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:4px;">
                            Attendance Rate</div>
                        <div class="att-big-pct"><?php echo $attendance_percent; ?><span>%</span></div>
                    </div>
                    <div class="att-bar-wrap">
                        <div style="font-size:12px;color:var(--text-muted);"><?php echo $monthly_attendance; ?> /
                            <?php echo $total_working_days; ?> days &nbsp;·&nbsp; <?php echo date('F Y'); ?></div>
                        <div class="att-bar-track">
                            <div class="att-bar-fill" id="attBarFill" style="width:0%"></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div
                            style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;">
                            Status</div>
                        <div
                            style="font-size:13px;font-weight:700;color:<?php echo $attendance_percent >= 75 ? 'var(--green)' : 'var(--red)'; ?>;margin-top:2px;">
                            <?php echo $attendance_percent >= 75 ? '✓ Good' : '⚠ Low'; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ ATTENDANCE HISTORY SECTION ═══ -->
                <div class="att-history-card fade-up delay-4">
                    <div class="att-history-header">
                        <div class="att-history-title">
                            <i class="fas fa-chart-bar"></i> Attendance History
                        </div>
                        <div class="att-hist-filter-grp">
                            <button class="afhf-btn" id="ahf7" onclick="loadStudentAtt(7,this)">7 Days</button>
                            <button class="afhf-btn" id="ahf15" onclick="loadStudentAtt(15,this)">15 Days</button>
                            <button class="afhf-btn active" id="ahf30" onclick="loadStudentAtt(30,this)">30
                                Days</button>
                        </div>
                    </div>
                    <div class="att-history-body">
                        <!-- Summary stats -->
                        <!-- <div class="att-hist-stat-grid">
                        <div class="att-hist-stat-box">
                            <div class="att-hist-val" id="ahPresent" style="color:var(--green);">—</div>
                            <div class="att-hist-lbl">Present</div>
                        </div>
                        <div class="att-hist-stat-box">
                            <div class="att-hist-val" id="ahAbsent" style="color:var(--red);">—</div>
                            <div class="att-hist-lbl">Absent</div>
                        </div>
                        <div class="att-hist-stat-box">
                            <div class="att-hist-val" id="ahRate" style="color:var(--purple-light);">—</div>
                            <div class="att-hist-lbl">Attendance %</div>
                        </div>
                        <div class="att-hist-stat-box">
                            <div class="att-hist-val" id="ahStreak" style="color:var(--yellow);">—</div>
                            <div class="att-hist-lbl">Current Streak</div>
                        </div>
                    </div> -->
                        <!-- Period info -->
                        <div class="att-hist-period" id="ahPeriodInfo">&nbsp;</div>
                        <!-- Loading indicator -->
                        <div class="att-hist-loading" id="ahLoading">
                            <div
                                style="display:inline-block;width:1.3rem;height:1.3rem;border:2px solid rgba(16,185,129,0.3);border-top-color:var(--green);border-radius:50%;animation:spin .7s linear infinite;">
                            </div>
                        </div>
                        <!-- Chart canvas -->
                        <div class="att-hist-chart-wrap" id="ahChartWrap">
                            <canvas id="studentAttChart"></canvas>
                        </div>
                        <style>
                            @keyframes spin {
                                to {
                                    transform: rotate(360deg)
                                }
                            }
                        </style>
                    </div>
                </div><!-- /att-history-card -->

              



                <!-- EMI PENDING ALERT -->
                <?php if ($emi_pending_this_month): ?>
                    <div class="emi-card fade-up delay-4">
                        <div class="emi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="emi-info">
                            <?php if ($emi_pending_this_month): ?>
                                <div style="margin-top:6px;font-size:11px;color:#f87171;font-weight:600;">
                                    ⚠ EMI PENDING THIS MONTH
                                </div>
                            <?php endif; ?>
                            <p>
                                <?php if ($last_payment): ?>
                                    Last paid <strong>₹<?php echo number_format($last_payment['amount_paid'], 0); ?></strong>
                                    on <?php echo date('d M Y', strtotime($last_payment['payment_date'])); ?>
                                    <?php if ($next_due_date): ?>
                                        &nbsp;·&nbsp; Next due:
                                        <strong><?php echo date('d M Y', strtotime($next_due_date)); ?></strong>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Outstanding balance: <strong>₹<?php echo number_format($outstanding, 0); ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="emi-badge">₹<?php echo number_format($outstanding, 0); ?> Due</span>
                    </div>
                <?php endif; ?>
  <!-- CIRCULAR PROGRESS CARDS -->
                <div class="circles-row fade-up delay-3">
                    <!-- Course Progress -->
                    <div class="circle-card c-purple card-glass">
                        <svg style="display:none" width="0" height="0">
                            <defs>
                                <linearGradient id="gradPurple" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#a78bfa" />
                                    <stop offset="100%" style="stop-color:#7c3aed" />
                                </linearGradient>
                                <linearGradient id="gradCyan" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#38bdf8" />
                                    <stop offset="100%" style="stop-color:#06b6d4" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="circle-wrap">
                            <svg class="circle-svg" width="72" height="72" viewBox="0 0 72 72">
                                <circle class="circle-track" cx="36" cy="36" r="30" fill="none" stroke-width="6" />
                                <circle class="circle-fill" id="courseCircle" cx="36" cy="36" r="30" fill="none"
                                    stroke-width="6" stroke-dasharray="188.5" stroke-dashoffset="188.5" />
                            </svg>
                            <div class="circle-label">
                                <span class="circle-pct" id="courseVal">0<span class="circle-pct-sym">%</span></span>
                            </div>
                        </div>
                        <div class="circle-info">
                            <h4>Course Progress</h4>
                            <p><?php echo $completed_topics; ?> of <?php echo $total_topics; ?> topics<br>completed</p>
                        </div>
                    </div>

                    <!-- Fees Progress -->
                    <div class="circle-card c-blue card-glass">
                        <div class="circle-wrap">
                            <svg class="circle-svg" width="72" height="72" viewBox="0 0 72 72">
                                <circle class="circle-track" cx="36" cy="36" r="30" fill="none" stroke-width="6" />
                                <circle class="circle-fill" id="feesCircle" cx="36" cy="36" r="30" fill="none"
                                    stroke-width="6" stroke-dasharray="188.5" stroke-dashoffset="188.5" />
                            </svg>
                            <div class="circle-label">
                                <span class="circle-pct" id="feesVal">0<span class="circle-pct-sym">%</span></span>
                            </div>
                        </div>
                        <div class="circle-info">
                            <h4>Fees Paid</h4>
                            <p>₹<?php echo number_format($paid_amount, 0); ?>
                                of<br>₹<?php echo number_format($total_fees, 0); ?> cleared</p>
                        </div>
                    </div>
                </div>
                <!-- ══ PROJECT TRACKER SECTION ══ -->
                <?php if (!empty($task_projects)): ?>
                    <div class="fade-up delay-4" style="
                background:rgba(99,102,241,.07);
                border:1px solid rgba(99,102,241,.2);
                border-radius:16px;
                overflow:hidden;
                margin-top:2px;
            ">


                        <!-- Header -->
                        <div
                            style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid rgba(99,102,241,.15);">
                            <div
                                style="font-size:13px;font-weight:800;color:#e0e7ff;display:flex;align-items:center;gap:8px;">
                                <i class="fas fa-clipboard-list" style="color:#a78bfa;"></i> Live Project Tracker
                            </div>
                            <a href="task_manager.php"
                                style="font-size:11px;font-weight:700;color:#818cf8;text-decoration:none;">
                                <i class="fas fa-external-link-alt"></i> Full Task Manager
                            </a>
                        </div>

                        <?php foreach ($task_projects as $tp): ?>
                            <!-- Project Row -->
                            <div
                                style="padding:12px 16px;border-bottom:1px solid rgba(99,102,241,.1);<?= (!$tp['has_today']) ? 'background:rgba(239,68,68,.04);' : '' ?>">
                                <!-- Project Info Row -->
                                <div
                                    style="display:grid;grid-template-columns:1fr auto auto auto auto;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
                                    <!-- Project name + overdue badge -->
                                    <div>
                                        <div style="font-size:13px;font-weight:800;color:#e0e7ff;">
                                            <i class="fas fa-folder-open" style="color:#a78bfa;margin-right:5px;"></i>
                                            <?= htmlspecialchars($tp['project_name']) ?>
                                            <?php if (!$tp['has_today']): ?>
                                                <span
                                                    style="font-size:9px;font-weight:700;background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);padding:2px 7px;border-radius:99px;margin-left:6px;">
                                                    <i class="fas fa-clock"></i> No update today
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:10px;color:rgba(255,255,255,.5);margin-top:2px;">
                                            Started <?= date('d M Y', strtotime($tp['start_date'])) ?>
                                        </div>
                                    </div>
                                    <!-- Start Date -->
                                    <div style="text-align:center;">
                                        <div
                                            style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;">
                                            Start Date</div>
                                        <div style="font-size:12px;font-weight:700;color:#c7d2fe;">
                                            <?= date('d-m-Y', strtotime($tp['start_date'])) ?></div>
                                    </div>
                                    <!-- Current Day -->
                                    <div style="text-align:center;">
                                        <div
                                            style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;">
                                            Day</div>
                                        <div style="font-size:18px;font-weight:900;color:#a78bfa;line-height:1;">
                                            <?= $tp['current_day'] ?></div>
                                    </div>
                                    <!-- Update Today Work -->
                                    <div>
                                        <button
                                            onclick="openUpdateModal(<?= $tp['id'] ?>, '<?= htmlspecialchars($tp['project_name'], ENT_QUOTES) ?>')"
                                            style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;
                                    padding:7px 13px;border-radius:8px;font-size:11px;font-weight:700;
                                    cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:5px;
                                    transition:all .18s;white-space:nowrap;"
                                            onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(124,58,237,.5)'"
                                            onmouseout="this.style.transform='';this.style.boxShadow=''">
                                            <i class="fas fa-plus"></i>
                                            <?= $tp['has_today'] ? 'Update Today' : 'Update Today Work' ?>
                                        </button>
                                    </div>
                                    <!-- Close / Final -->
                                    <div>
                                        <a href="task_manager.php" style="background:rgba(16,185,129,.1);color:#34d399;border:1px solid rgba(16,185,129,.25);
                                    padding:7px 11px;border-radius:8px;font-size:11px;font-weight:700;
                                    text-decoration:none;display:inline-flex;align-items:center;gap:4px;
                                    transition:all .18s;white-space:nowrap;"
                                            onmouseover="this.style.background='#10b981';this.style.color='#fff'"
                                            onmouseout="this.style.background='rgba(16,185,129,.1)';this.style.color='#34d399'">
                                            <i class="fas fa-check-circle"></i> Close / Final
                                        </a>
                                    </div>
                                </div>

                                <!-- Last 3 Activity -->
                                <div style="background:rgba(0,0,0,.15);border-radius:10px;padding:10px 12px;">
                                    <div
                                        style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.4);margin-bottom:8px;">
                                        <i class="fas fa-history" style="margin-right:4px;color:#818cf8;"></i>Last 3 Activities
                                    </div>
                                    <?php if (empty($tp['last3'])): ?>
                                        <div style="font-size:11.5px;color:rgba(255,255,255,.3);text-align:center;padding:6px 0;">No
                                            project updates yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($tp['last3'] as $log): ?>
                                            <div
                                                style="display:grid;grid-template-columns:85px 1fr 55px;gap:8px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:11.5px;align-items:start;">
                                                <span
                                                    style="color:#60a5fa;font-weight:700;white-space:nowrap;"><?= date('d-m-Y', strtotime($log['log_date'])) ?></span>
                                                <span
                                                    style="color:#d1d5db;line-height:1.4;word-break:break-word;"><?= htmlspecialchars(mb_strimwidth($log['work_description'], 0, 85, '…')) ?></span>
                                                <span style="text-align:right;">
                                                    <span
                                                        style="font-size:10px;font-weight:800;color:#a78bfa;background:rgba(99,102,241,.15);padding:2px 7px;border-radius:99px;">Day
                                                        <?= $log['day_number'] ?></span>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <!-- Show Full Update button -->
                                    <button
                                        onclick="openDashTimeline(<?= $tp['id'] ?>, '<?= htmlspecialchars($tp['project_name'], ENT_QUOTES) ?>')"
                                        style="margin-top:8px;background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.2);
                                padding:5px 12px;border-radius:7px;font-size:10.5px;font-weight:700;
                                cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:4px;
                                transition:all .15s;" onmouseover="this.style.background='rgba(99,102,241,.25)'"
                                        onmouseout="this.style.background='rgba(99,102,241,.12)'">
                                        <i class="fas fa-list-ul"></i> Show Full Update of this Project
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($task_projects)): ?>
                            <div style="text-align:center;padding:24px;color:rgba(255,255,255,.3);font-size:12px;">
                                <i class="fas fa-clipboard-list"
                                    style="display:block;font-size:28px;margin-bottom:8px;opacity:.4;"></i>
                                No active projects. <a href="task_manager.php" style="color:#818cf8;">Create one</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div><!-- /center-col -->
        </div><!-- /main-grid -->

    </div><!-- /app-shell -->

    <!-- ═══ RIGHT PANEL ═══ -->
    <aside class="right-panel">

        <!-- Student of the Month -->
        <?php if ($sotm): ?>
            <div class="sotm-card">
                <div class="sotm-label">🏅 Student of the Month</div>
                <?php $sotm_photo = resolvePhotoSrc($sotm['photo'] ?? ''); ?>
                <div class="sotm-avatar">
                    <?php if ($sotm_photo): ?>
                        <img src="<?php echo htmlspecialchars($sotm_photo); ?>"
                            style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="sotm-name"><?php echo htmlspecialchars($sotm['name']); ?></div>
                <div class="sotm-pts"><?php echo $sotm['total_points'] + 0; ?> pts</div>
                <div class="sotm-rank-pill"><i class="fas fa-crown" style="font-size:9px;"></i> Rank #01</div>
            </div>
        <?php endif; ?>

        <!-- Leaderboard -->
        <div class="leaderboard-header">🏆 Top 10 Students</div>
        <div class="lb-list">
            <?php foreach ($top_students as $s):
                $is_me = ((int) $s['id'] === $sid);
                $r = $s['rank'];
                $rank_class = $r == 1 ? 'gold' : ($r == 2 ? 'silver' : ($r == 3 ? 'bronze' : ''));
                $initials = strtoupper(substr($s['name'], 0, 1));
                if (strpos($s['name'], ' ') !== false) {
                    $initials .= strtoupper(substr(strrchr($s['name'], ' '), 1, 1));
                }
                $lb_photo_src = resolvePhotoSrc($s['photo'] ?? '');
                ?>
                <div class="lb-item <?php echo $is_me ? 'is-me' : ''; ?>">
                    <div class="lb-rank <?php echo $rank_class; ?>"><?php
                       if ($r == 1)
                           echo '🥇';
                       elseif ($r == 2)
                           echo '🥈';
                       elseif ($r == 3)
                           echo '🥉';
                       else
                           echo '#' . $r;
                       ?></div>
                    <div class="lb-avatar">
                        <?php if ($lb_photo_src): ?>
                            <img src="<?php echo htmlspecialchars($lb_photo_src); ?>"
                                alt="<?php echo htmlspecialchars($s['name']); ?>">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials); ?>
                        <?php endif; ?>
                    </div>
                    <div class="lb-name">
                        <?php echo htmlspecialchars($s['name']); ?>
                        <?php if ($is_me): ?> <span style="font-size:9px;color:var(--cyan);">(You)</span><?php endif; ?>
                    </div>
                    <div class="lb-pts"><?php echo $s['total_points'] + 0; ?></div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($top_students)): ?>
                <div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px 0;">No ranking data yet
                </div>
            <?php endif; ?>
        </div>

    </aside>

    <!-- Attendance Toast -->
    <div id="att-toast">
        <span id="att-toast-icon" class="fas fa-check-circle"></span>
        <span id="att-toast-msg"></span>
    </div>

    <!-- ══ UPDATE TODAY WORK MODAL ══ -->
    <div id="dashUpdateModal"
        style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
        <div style="background:#1e1533;border:1px solid rgba(99,102,241,.25);border-radius:16px;width:90%;max-width:480px;
        box-shadow:0 20px 60px rgba(0,0,0,.6);animation:dashModalIn .22s ease;">
            <style>
                @keyframes dashModalIn {
                    from {
                        transform: translateY(30px);
                        opacity: 0
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1
                    }
                }
            </style>
            <div
                style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid rgba(99,102,241,.15);">
                <div style="font-size:13px;font-weight:800;color:#fff;">
                    <i class="fas fa-calendar-plus" style="color:#a78bfa;margin-right:7px;"></i>
                    <span id="dashUpdateTitle">Update Today's Work</span>
                </div>
                <button onclick="closeDashUpdateModal()"
                    style="background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;line-height:1;padding:2px 6px;border-radius:6px;"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">×</button>
            </div>
            <div style="padding:16px 18px;">
                <input type="hidden" id="dashUpdateProjId">
                <p id="dashUpdateInfo" style="font-size:11px;color:#94a3b8;margin-bottom:10px;"></p>
                <label style="font-size:11.5px;font-weight:700;color:#d1d5db;margin-bottom:6px;display:block;">What did
                    you work on today? *</label>
                <textarea id="dashUpdateWork" rows="5" placeholder="Describe your progress in detail..."
                    style="width:100%;padding:9px 13px;border-radius:9px;background:rgba(255,255,255,.06);border:1.5px solid rgba(99,102,241,.2);color:#fff;font-size:13px;font-family:inherit;outline:none;resize:vertical;"
                    onfocus="this.style.borderColor='#818cf8'"
                    onblur="this.style.borderColor='rgba(99,102,241,.2)'"></textarea>
            </div>
            <div
                style="padding:10px 18px;border-top:1px solid rgba(99,102,241,.12);display:flex;justify-content:flex-end;gap:8px;">
                <button onclick="closeDashUpdateModal()"
                    style="background:rgba(255,255,255,.06);color:#94a3b8;border:1px solid rgba(255,255,255,.1);padding:8px 16px;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">Cancel</button>
                <button id="dashUpdateSubmitBtn" onclick="submitDashUpdate()"
                    style="background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;padding:8px 18px;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-save"></i> Save Update
                </button>
            </div>
        </div>
    </div>

    <!-- ══ FULL TIMELINE MODAL ══ -->
    <div id="dashTimelineModal"
        style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
        <div style="background:#1e1533;border:1px solid rgba(99,102,241,.25);border-radius:16px;width:90%;max-width:620px;max-height:88vh;display:flex;flex-direction:column;
        box-shadow:0 20px 60px rgba(0,0,0,.6);animation:dashModalIn .22s ease;">
            <div
                style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid rgba(99,102,241,.15);flex-shrink:0;">
                <div style="font-size:13px;font-weight:800;color:#fff;">
                    <i class="fas fa-list-ul" style="color:#a78bfa;margin-right:7px;"></i>
                    <span id="dashTimelineTitle">Project Timeline</span>
                </div>
                <button onclick="closeDashTimeline()"
                    style="background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;line-height:1;padding:2px 6px;border-radius:6px;"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">×</button>
            </div>
            <div id="dashTimelineContent" style="padding:16px 18px;overflow-y:auto;flex:1;"></div>
            <div
                style="padding:10px 18px;border-top:1px solid rgba(99,102,241,.12);display:flex;justify-content:flex-end;flex-shrink:0;">
                <button onclick="closeDashTimeline()"
                    style="background:rgba(255,255,255,.06);color:#94a3b8;border:1px solid rgba(255,255,255,.1);padding:7px 16px;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">Close</button>
            </div>
        </div>
    </div>

    <!-- Progress animation JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Attendance bar
            setTimeout(() => {
                const bar = document.getElementById('attBarFill');
                if (bar) bar.style.width = '<?php echo $attendance_percent; ?>%';
            }, 300);

            // Circular progress helper
            function animateCircle(id, valId, targetPct) {
                const circ = document.getElementById(id);
                const val = document.getElementById(valId);
                if (!circ || !val) return;
                const circumference = 2 * Math.PI * 30; // ~188.5

                let current = 0;
                const duration = 1400;
                const startTime = performance.now();

                function step(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    current = Math.round(targetPct * eased);
                    val.childNodes[0].textContent = current;
                    const currentOffset = circumference - (current / 100) * circumference;
                    circ.style.strokeDashoffset = currentOffset;
                    if (progress < 1) requestAnimationFrame(step);
                }
                setTimeout(() => requestAnimationFrame(step), 500);
            }

            animateCircle('courseCircle', 'courseVal', <?php echo (int) $course_progress; ?>);
            animateCircle('feesCircle', 'feesVal', <?php echo (int) $fees_percent; ?>);
        });

        // ════════════════════════════════════════════════════
        //  ATTENDANCE — Check In / Check Out
        // ════════════════════════════════════════════════════
        function showToast(msg, type) {
            const toast = document.getElementById('att-toast');
            const icon = document.getElementById('att-toast-icon');
            const text = document.getElementById('att-toast-msg');

            toast.classList.remove('toast-success', 'toast-error', 'show');
            icon.className = type === 'success'
                ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            icon.style.color = type === 'success' ? '#10b981' : '#ef4444';
            text.textContent = msg;
            toast.classList.add(type === 'success' ? 'toast-success' : 'toast-error');

            // Force reflow then show
            void toast.offsetWidth;
            toast.classList.add('show');

            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => toast.classList.remove('show'), 3200);
        }

        function doCheckIn() {
            const btn = document.getElementById('btnCheckIn');
            if (btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking in…';

            const fd = new FormData();
            fd.append('action', 'check_in');

            fetch('attendance_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Disable check-in permanently for today
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Checked In';
                        btn.disabled = true;

                        // Show time label
                        document.getElementById('checkInTime').textContent = data.check_in_time;

                        // Enable check-out
                        const btnOut = document.getElementById('btnCheckOut');
                        btnOut.disabled = false;
                        btnOut.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';

                        showToast(data.message, 'success');
                    } else {
                        // Revert button if something went wrong
                        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
                        btn.disabled = false;
                        showToast(data.message, 'error');
                    }
                })
                .catch(() => {
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check In';
                    btn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function doCheckOut() {
            const btn = document.getElementById('btnCheckOut');
            if (btn.disabled) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking out…';

            const fd = new FormData();
            fd.append('action', 'check_out');

            fetch('attendance_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Checked Out';
                        btn.disabled = true;
                        document.getElementById('checkOutTime').textContent = data.check_out_time;
                        showToast(data.message, 'success');
                    } else {
                        btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
                        const keepDisabled = data.message.includes('check in');
                        btn.disabled = keepDisabled;
                        showToast(data.message, 'error');
                    }
                })
                .catch(() => {
                    btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
                    btn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        // ════════════════════════════════════════════════════
        //  DASHBOARD — Update Today Work Modal
        // ════════════════════════════════════════════════════
        function openUpdateModal(projId, projName) {
            document.getElementById('dashUpdateProjId').value = projId;
            document.getElementById('dashUpdateTitle').textContent = 'Update Today\'s Work — ' + projName;
            document.getElementById('dashUpdateInfo').textContent = 'Project: ' + projName;
            document.getElementById('dashUpdateWork').value = '';
            const modal = document.getElementById('dashUpdateModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('dashUpdateWork').focus(), 120);
        }

        function closeDashUpdateModal() {
            document.getElementById('dashUpdateModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function submitDashUpdate() {
            const projId = document.getElementById('dashUpdateProjId').value;
            const work = document.getElementById('dashUpdateWork').value.trim();
            if (!work) { alert('Please describe the work done today.'); return; }

            const btn = document.getElementById('dashUpdateSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

            const fd = new FormData();
            fd.append('action', 'add_day');
            fd.append('project_id', projId);
            fd.append('work_description', work);

            fetch('ajax/project_actions.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        // Hide overdue warning since update was submitted
                        const ow = document.getElementById('overdueWarning');
                        if (ow) ow.style.display = 'none';
                        closeDashUpdateModal();
                        showToast('Update saved — Day ' + (d.day_number || ''), 'success');
                        // Reload after short delay so activity history updates
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save"></i> Save Update';
                        alert(d.message || 'Failed to save update.');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Update';
                    alert('Network error. Please try again.');
                });
        }

        // ════════════════════════════════════════════════════
        //  DASHBOARD — Full Timeline Modal
        // ════════════════════════════════════════════════════
        function openDashTimeline(projId, projName) {
            document.getElementById('dashTimelineTitle').textContent = projName + ' — Full Timeline';
            document.getElementById('dashTimelineContent').innerHTML =
                '<div style="text-align:center;padding:30px;color:#64748b"><i class="fas fa-spinner fa-spin" style="font-size:22px;display:block;margin-bottom:8px"></i>Loading…</div>';
            const modal = document.getElementById('dashTimelineModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            const fd = new FormData();
            fd.append('action', 'get_project_logs');
            fd.append('project_id', projId);

            fetch('ajax/project_actions.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        document.getElementById('dashTimelineContent').innerHTML =
                            '<div style="text-align:center;padding:24px;color:#f87171"><i class="fas fa-exclamation-circle" style="display:block;font-size:24px;margin-bottom:8px"></i>' + (d.message || 'Failed to load') + '</div>';
                        return;
                    }
                    if (!d.logs || d.logs.length === 0) {
                        document.getElementById('dashTimelineContent').innerHTML =
                            '<div style="text-align:center;padding:24px;color:rgba(255,255,255,.35)"><i class="fas fa-inbox" style="display:block;font-size:28px;margin-bottom:8px"></i>No logs yet for this project.</div>';
                        return;
                    }
                    let html = '';
                    d.logs.forEach((log, i) => {
                        const isComp = log.work_description.startsWith('[COMPLETED]');
                        const desc = isComp
                            ? '<i class="fas fa-check-circle" style="color:#34d399;margin-right:5px"></i>' + _escHtml(log.work_description.replace('[COMPLETED]', '').trim())
                            : _escHtml(log.work_description);
                        html += `<div style="display:grid;grid-template-columns:90px 1fr 60px;gap:10px;align-items:start;
                    padding:9px 12px;${i < d.logs.length - 1 ? 'border-bottom:1px solid rgba(255,255,255,.05)' : ''}
                    ${isComp ? 'background:rgba(16,185,129,.04);border-radius:8px;' : ''};font-size:12px;">
                    <span style="color:#60a5fa;font-weight:700;">${log.log_date_display}</span>
                    <span style="color:#d1d5db;line-height:1.5;word-break:break-word;">${desc}</span>
                    <span style="text-align:right;"><span style="font-size:10px;font-weight:800;color:#a78bfa;background:rgba(99,102,241,.15);padding:2px 8px;border-radius:99px;">Day ${log.day_number}</span></span>
                </div>`;
                    });
                    document.getElementById('dashTimelineContent').innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('dashTimelineContent').innerHTML =
                        '<div style="text-align:center;padding:24px;color:#f87171">Network error. Please try again.</div>';
                });
        }

        function closeDashTimeline() {
            document.getElementById('dashTimelineModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function _escHtml(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        // Close modals on backdrop click
        document.addEventListener('click', function (e) {
            const upd = document.getElementById('dashUpdateModal');
            const tl = document.getElementById('dashTimelineModal');
            if (e.target === upd) closeDashUpdateModal();
            if (e.target === tl) closeDashTimeline();
        });

    </script>

    <!-- Chart.js for Attendance History -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // ════════════════════════════════════════════════════
        //  STUDENT PORTAL — Attendance History Chart
        // ════════════════════════════════════════════════════
        let studentAttChart = null;

        function loadStudentAtt(days, btn) {
            document.querySelectorAll('.afhf-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            fetchStudentAtt(days);
        }

        function fetchStudentAtt(days) {
            const loading = document.getElementById('ahLoading');
            const chartWrap = document.getElementById('ahChartWrap');
            if (loading) loading.style.display = 'block';
            if (chartWrap) chartWrap.style.opacity = '0.3';

            fetch(`ajax/get_attendance.php?days=${days}`)
                .then(r => r.json())
                .then(data => {
                    if (loading) loading.style.display = 'none';
                    if (chartWrap) chartWrap.style.opacity = '1';
                    if (data.success) {
                        renderStudentAttChart(data);
                        computeStudentAttStats(data, days);
                    }
                })
                .catch(() => {
                    if (loading) loading.style.display = 'none';
                    if (chartWrap) chartWrap.style.opacity = '1';
                });
        }

        function renderStudentAttChart(data) {
            const ctx = document.getElementById('studentAttChart');
            const rawDates = data.rawDates || [];
            const labels = data.dates;
            const present = data.present;
            const absent = data.absent;
            const checkIn = data.checkIn || [];
            const checkOut = data.checkOut || [];

            const sunC = 'rgba(245,158,11,0.80)';
            const preC = 'rgba(16,185,129,0.88)';
            const absC = 'rgba(239,68,68,0.82)';
            const bgPre = [], bgAbs = [];
            for (let i = 0; i < labels.length; i++) {
                const isSun = rawDates[i] ? (new Date(rawDates[i]).getDay() === 0) : false;
                bgPre.push(isSun ? sunC : preC);
                bgAbs.push(isSun ? sunC : absC);
            }

            if (studentAttChart) studentAttChart.destroy();

            Chart.defaults.color = 'rgba(241,240,255,0.5)';

            studentAttChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Present', data: present, backgroundColor: bgPre, borderRadius: 4, barThickness: 9 },
                        { label: 'Absent', data: absent, backgroundColor: bgAbs, borderRadius: 4, barThickness: 9 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 10, font: { size: 11 }, color: 'rgba(241,240,255,0.6)' }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(13,11,30,0.95)',
                            borderColor: 'rgba(255,255,255,0.12)',
                            borderWidth: 1,
                            titleColor: '#f1f0ff',
                            bodyColor: 'rgba(241,240,255,0.75)',
                            padding: 10,
                            callbacks: {
                                title(items) {
                                    const idx = items[0]?.dataIndex;
                                    if (idx === undefined || !rawDates[idx]) return labels[idx] || '';
                                    const d = new Date(rawDates[idx]);
                                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                                },
                                label(ctx) {
                                    const idx = ctx.dataIndex;
                                    const isSun = rawDates[idx] && new Date(rawDates[idx]).getDay() === 0;
                                    if (isSun) {
                                        return [
                                            'Status    : Sunday',
                                            'Check In  : --',
                                            'Check Out : --'
                                        ];
                                    }
                                    if (present[idx] === 1) {
                                        return [
                                            'Status    : Present',
                                            'Check In  : ' + (checkIn[idx] || '--'),
                                            'Check Out : ' + (checkOut[idx] || '--')
                                        ];
                                    }
                                    return [
                                        'Status    : Absent',
                                        'Check In  : --',
                                        'Check Out : --'
                                    ];
                                },
                                afterLabel() { return null; }
                            },
                            filter(item) {
                                return item.datasetIndex === 0;
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 1.15,
                            ticks: {
                                stepSize: 1,
                                callback: v => v === 1 ? '✓' : '',
                                font: { size: 10 },
                                color: 'rgba(241,240,255,0.4)'
                            },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        x: {
                            ticks: { font: { size: 9.5 }, color: 'rgba(241,240,255,0.4)' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function computeStudentAttStats(data, days) {
            const rawDates = data.rawDates || [];
            const present = data.present || [];

            let sundays = 0;
            rawDates.forEach(d => { if (new Date(d).getDay() === 0) sundays++; });

            const workingDays = rawDates.length - sundays;
            let presentCount = 0;
            rawDates.forEach((d, i) => {
                if (new Date(d).getDay() !== 0 && present[i] === 1) presentCount++;
            });
            const absentCount = Math.max(0, workingDays - presentCount);
            const rate = workingDays > 0 ? Math.round((presentCount / workingDays) * 100) : 0;

            // Current streak (skip Sundays)
            let curStreak = 0;
            for (let i = present.length - 1; i >= 0; i--) {
                if (rawDates[i] && new Date(rawDates[i]).getDay() === 0) continue;
                if (present[i] === 1) curStreak++; else break;
            }

            const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
            el('ahPresent', presentCount);
            el('ahAbsent', absentCount);
            el('ahRate', rate + '%');
            el('ahStreak', curStreak + 'd');

            // Period label
            const fmtDate = d => d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            const start = new Date(); start.setDate(start.getDate() - (days - 1));
            const pi = document.getElementById('ahPeriodInfo');
            if (pi) pi.innerHTML = `<i class="fas fa-calendar-alt"></i> <strong style="color:var(--text);">${fmtDate(start)}</strong> → <strong style="color:var(--text);">${fmtDate(new Date())}</strong> &nbsp;·&nbsp; ${days} days &nbsp;<span style="color:var(--yellow);font-size:10px;"><i class="fas fa-sun"></i> Sunday = amber, not counted</span>`;
        }

        // Load default (30 days) on page ready
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => loadStudentAtt(30, document.getElementById('ahf30')), 500);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>