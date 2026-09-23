<?php
include 'includes/header.php';
require_once 'includes/ranking_helper.php';

// ── session guard ──
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
if (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === 'administrator') {
    header("Location: task_manager.php");
    exit;
}

// ── date range ──
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$today = getCurrentISTDate();
$cm_start = date('Y-m-01');
$cm_end = date('Y-m-t');

// ── ranking ──
$ranking = getMonthlyRanking($conn, $cm_start, $cm_end);

// ══════════════════════════════════════
// STAT CARD QUERIES
// ══════════════════════════════════════
$total_active = (int) $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'")->fetch_assoc()['c'];
$new_this_month = (int) $conn->query("SELECT COUNT(*) as c FROM students WHERE MONTH(enrollment_date)=MONTH(CURDATE()) AND YEAR(enrollment_date)=YEAR(CURDATE())")->fetch_assoc()['c'];
$morning_active = (int) $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active' AND batch='Morning'")->fetch_assoc()['c'];
$evening_active = (int) $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active' AND batch='Evening'")->fetch_assoc()['c'];
$active_today = (int) $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM student_attendance WHERE attendance_date='$today' AND status='Present'")->fetch_assoc()['c'];

// pending tasks (kept for internal use)
$task_stat = 0;
try {
    $tr2 = $conn->query("SELECT COUNT(*) as c FROM admin_tasks WHERE status='Pending' AND assigned_to={$_SESSION['admin_id']}");
    if ($tr2)
        $task_stat = (int) $tr2->fetch_assoc()['c'];
} catch (Exception $e) {
    $task_stat = 0;
}

// ── SMART MONTHLY PENDING FEES CALCULATION ────────────────────────────────
// Priority 1: Use total_fees / duration_months from student record (the "dedicated" EMI)
// Priority 2: Fallback to median payment history, excluding outliers (advance/multi-month)
$expected_monthly = 0.0;
try {
    $emiRes = $conn->query("
        SELECT s.id, s.total_fees, s.duration_months,
               GROUP_CONCAT(p.amount_paid ORDER BY p.amount_paid ASC SEPARATOR ',') AS all_payments
        FROM students s
        LEFT JOIN payments p ON p.student_id = s.id
        WHERE s.status = 'Active'
        GROUP BY s.id
    ");
    if ($emiRes) {
        while ($row = $emiRes->fetch_assoc()) {
            $tf = (float) $row['total_fees'];
            $dm = (int) $row['duration_months'];
            if ($dm > 0 && $tf > 0) {
                // PRIMARY: student record has dedicated installment info
                $expected_monthly += $tf / $dm;
            } elseif (!empty($row['all_payments'])) {
                // FALLBACK: infer from payment history, ignore advance/multi-month outliers
                $amounts = array_map('floatval', explode(',', $row['all_payments']));
                sort($amounts);
                $cnt = count($amounts);
                if ($cnt > 0) {
                    $median = ($cnt % 2 === 0)
                        ? ($amounts[$cnt / 2 - 1] + $amounts[$cnt / 2]) / 2
                        : $amounts[(int) floor($cnt / 2)];
                    // Exclude payments > 2× median (advance payments / multi-month lumps)
                    $normal = array_values(array_filter($amounts, fn($a) => $median > 0 ? $a <= $median * 2.0 : true));
                    if (!empty($normal)) {
                        $expected_monthly += array_sum($normal) / count($normal);
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $expected_monthly = 0.0;
}

// Current calendar month collected
$cm_collected_fees = 0.0;
try {
    $cmR = $conn->query("
        SELECT COALESCE(SUM(amount_paid), 0) AS total FROM payments
        WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
    ");
    if ($cmR)
        $cm_collected_fees = (float) $cmR->fetch_assoc()['total'];
} catch (Exception $e) {
}

// Pending = Expected Collection − Already Collected (never negative)
$pending_monthly_fees = max(0.0, $expected_monthly - $cm_collected_fees);
// ─────────────────────────────────────────────────────────────────────────

// ── Student Project Tracker stats (for dashboard cards) ──
$spt_active = 0;
$spt_inactive = 0;
$spt_in_progress = 0;
$spt_completed = 0;
try {
    $spt_in_progress = (int) $conn->query("SELECT COUNT(*) AS c FROM student_projects WHERE status='In Progress'")->fetch_assoc()['c'];
    $spt_completed = (int) $conn->query("SELECT COUNT(*) AS c FROM student_projects WHERE status='Completed'")->fetch_assoc()['c'];
    $spt_active = (int) $conn->query("
        SELECT COUNT(DISTINCT sp.student_id) AS c
        FROM student_projects sp
        JOIN project_daily_logs dl ON sp.id = dl.project_id
        WHERE sp.status='In Progress' AND dl.log_date >= DATE_SUB('$today', INTERVAL 2 DAY)
    ")->fetch_assoc()['c'];
    $spt_inactive = (int) $conn->query("
        SELECT COUNT(DISTINCT sp.student_id) AS c
        FROM student_projects sp
        WHERE sp.status='In Progress'
          AND sp.student_id NOT IN (
              SELECT DISTINCT sp2.student_id FROM student_projects sp2
              JOIN project_daily_logs dl2 ON sp2.id = dl2.project_id
              WHERE dl2.log_date >= DATE_SUB('$today', INTERVAL 5 DAY)
          )
    ")->fetch_assoc()['c'];
} catch (Exception $e) { /* tables may not exist */
}

// ── Morning / Evening present & absent counts ──
$morning_present_stat = (int) $conn->query("
    SELECT COUNT(DISTINCT a.student_id) as c
    FROM student_attendance a
    JOIN students s ON s.id=a.student_id
    WHERE a.attendance_date='$today' AND a.status='Present' AND s.batch='Morning' AND s.status='Active'
")->fetch_assoc()['c'];

$morning_absent_stat = (int) $conn->query("
    SELECT COUNT(*) as c FROM students
    WHERE status='Active' AND login_enabled=1 AND batch='Morning'
    AND NOT EXISTS (SELECT 1 FROM student_attendance a WHERE a.student_id=students.id AND a.attendance_date='$today')
")->fetch_assoc()['c'];

$evening_present_stat = (int) $conn->query("
    SELECT COUNT(DISTINCT a.student_id) as c
    FROM student_attendance a
    JOIN students s ON s.id=a.student_id
    WHERE a.attendance_date='$today' AND a.status='Present' AND s.batch='Evening' AND s.status='Active'
")->fetch_assoc()['c'];

$evening_absent_stat = (int) $conn->query("
    SELECT COUNT(*) as c FROM students
    WHERE status='Active' AND login_enabled=1 AND batch='Evening'
    AND NOT EXISTS (SELECT 1 FROM student_attendance a WHERE a.student_id=students.id AND a.attendance_date='$today')
")->fetch_assoc()['c'];

// ══════════════════════════════════════
// OVERDUE
// ══════════════════════════════════════
$res_od = $conn->query("
    SELECT COUNT(DISTINCT s.id) as count
    FROM students s
    LEFT JOIN (SELECT student_id, SUM(amount_paid) as paid FROM payments GROUP BY student_id) p ON s.id=p.student_id
    WHERE s.status='Active'
    AND (s.total_fees - COALESCE(p.paid,0)) > 0
    AND NOT EXISTS (SELECT 1 FROM payments p2 WHERE p2.student_id=s.id AND YEAR(p2.payment_date)=YEAR(CURDATE()) AND MONTH(p2.payment_date)=MONTH(CURDATE()))
");
$overdue_count = (int) $res_od->fetch_assoc()['count'];

// $overdueStudents = $conn->query("
//     SELECT s.id, s.student_code, s.full_name, s.phone, s.total_fees,
//           COALESCE(SUM(p.amount_paid),0) as total_paid,
//           (s.total_fees - COALESCE(SUM(p.amount_paid),0)) as pending_fees,
//           MAX(p.payment_date) as last_payment_date,
//           DATEDIFF(CURDATE(),MAX(p.payment_date)) as days_since_last_payment
//     FROM students s LEFT JOIN payments p ON s.id=p.student_id
//     WHERE s.status='Active'
//     GROUP BY s.id
//     HAVING pending_fees>0 AND (last_payment_date IS NULL OR days_since_last_payment>=30)
//     ORDER BY days_since_last_payment DESC
// ");

// $activePayingStudents = $conn->query("
//     SELECT s.id,s.student_code,s.full_name,
//           COALESCE(SUM(p.amount_paid),0) as month_paid,
//           MIN(p.payment_date) as first_payment_date,
//           MAX(p.payment_date) as last_payment_date,
//           COUNT(p.id) as payment_count
//     FROM students s
//     JOIN payments p ON s.id=p.student_id AND p.payment_date BETWEEN '$start_date' AND '$end_date'
//     WHERE s.status='Active'
//     GROUP BY s.id ORDER BY last_payment_date DESC
// ");

// ══════════════════════════════════════
// ALL STUDENTS FEES — with monthly paid flag + last payment amount
// ══════════════════════════════════════
$allStudentsFees = $conn->query("
    SELECT
        s.id,
        s.student_code,
        s.full_name,
        s.photo,
        s.total_fees,
        COALESCE(SUM(p.amount_paid), 0)                          AS total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0))         AS pending_fees,
        MAX(p.payment_date)                                       AS last_payment_date,

        /* Last single payment amount ever made by this student */
        (SELECT lp.amount_paid
         FROM payments lp
         WHERE lp.student_id = s.id
         ORDER BY lp.payment_date DESC, lp.id DESC
         LIMIT 1)                                                 AS last_payment_amount,

        /* Total paid specifically within the selected date range */
        COALESCE((
            SELECT SUM(mp.amount_paid)
            FROM payments mp
            WHERE mp.student_id = s.id
              AND mp.payment_date BETWEEN '$start_date' AND '$end_date'
        ), 0)                                                     AS month_paid

    FROM students s
    LEFT JOIN payments p ON p.student_id = s.id
    WHERE s.status = 'Active'
    GROUP BY s.id
    ORDER BY pending_fees DESC
");

$course_revenue = $conn->query("
    SELECT c.name, SUM(p.amount_paid) as total_revenue, COUNT(DISTINCT s.id) as student_count
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN courses c ON s.course_id=c.id
    WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY c.id ORDER BY total_revenue DESC LIMIT 10
");

$total_collection = (float) $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE payment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
$total_paid_students = (int) $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM payments WHERE payment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['c'];

// ══════════════════════════════════════
// ATTENDANCE
// ══════════════════════════════════════
$morningPresent = $conn->query("
    SELECT s.id,s.student_code,s.full_name,s.photo,a.check_in_time,a.created_at
    FROM students s JOIN student_attendance a ON s.id=a.student_id
    WHERE s.status='Active' AND s.batch='Morning' AND a.attendance_date='$today' AND a.status='Present'
    ORDER BY a.check_in_time DESC
");
$morningAbsent = $conn->query("
    SELECT s.id,s.student_code,s.full_name,s.photo,s.phone
    FROM students s
    WHERE s.status='Active' AND s.login_enabled=1 AND s.batch='Morning'
    AND NOT EXISTS (SELECT 1 FROM student_attendance a WHERE a.student_id=s.id AND a.attendance_date='$today')
    ORDER BY s.full_name ASC
");
$eveningPresent = $conn->query("
    SELECT s.id,s.student_code,s.full_name,s.photo,a.check_in_time,a.created_at
    FROM students s JOIN student_attendance a ON s.id=a.student_id
    WHERE s.status='Active' AND s.batch='Evening' AND a.attendance_date='$today' AND a.status='Present'
    ORDER BY a.check_in_time DESC
");
$eveningAbsent = $conn->query("
    SELECT s.id,s.student_code,s.full_name,s.photo,s.phone
    FROM students s
    WHERE s.status='Active' AND s.login_enabled=1 AND s.batch='Evening'
    AND NOT EXISTS (SELECT 1 FROM student_attendance a WHERE a.student_id=s.id AND a.attendance_date='$today')
    ORDER BY s.full_name ASC
");

$morning_present_count = $morningPresent->num_rows;
$morning_absent_count = $morningAbsent->num_rows;
$evening_present_count = $eveningPresent->num_rows;
$evening_absent_count = $eveningAbsent->num_rows;

// ══════════════════════════════════════
// CONVERT ATTENDANCE RESULT SETS TO PHP ARRAYS (for reuse in batch cards + drawer)
// ══════════════════════════════════════
$morningPresentArr = [];
while ($r = $morningPresent->fetch_assoc())
    $morningPresentArr[] = $r;
$morningAbsentArr = [];
while ($r = $morningAbsent->fetch_assoc())
    $morningAbsentArr[] = $r;
$eveningPresentArr = [];
while ($r = $eveningPresent->fetch_assoc())
    $eveningPresentArr[] = $r;
$eveningAbsentArr = [];
while ($r = $eveningAbsent->fetch_assoc())
    $eveningAbsentArr[] = $r;

// ── Groups with batch derivation + per-group attendance ──
$morningGroups = [];
$eveningGroups = [];
$groupStudents = [];
try {
    $grpR = $conn->query("
        SELECT sg.id, sg.group_name, c.name as course_name
        FROM student_groups sg
        JOIN courses c ON sg.course_id = c.id
        ORDER BY sg.group_name
    ");
    if ($grpR) {
        while ($g = $grpR->fetch_assoc()) {
            $gid = (int) $g['id'];
            $bi = $conn->query("
                SELECT 
                    SUM(CASE WHEN s.batch='Morning' THEN 1 ELSE 0 END) as mc,
                    SUM(CASE WHEN s.batch='Evening' THEN 1 ELSE 0 END) as ec,
                    COUNT(s.id) as active_m,
                    SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) as pc
                FROM student_group_members sgm
                JOIN students s ON sgm.student_id=s.id AND s.status='Active' AND s.login_enabled=1
                LEFT JOIN student_attendance a ON a.student_id=s.id AND a.attendance_date='$today'
                WHERE sgm.group_id=$gid
            ")->fetch_assoc();
            $g['present_count'] = (int) ($bi['pc'] ?? 0);
            $g['active_members'] = (int) ($bi['active_m'] ?? 0);
            $g['absent_count'] = max(0, $g['active_members'] - $g['present_count']);
            $g['att_pct'] = $g['active_members'] > 0 ? round(($g['present_count'] / $g['active_members']) * 100) : 0;
            if ((int) ($bi['mc'] ?? 0) >= (int) ($bi['ec'] ?? 0)) {
                $morningGroups[] = $g;
            } else {
                $eveningGroups[] = $g;
            }
        }
    }
    $gsR = $conn->query("
        SELECT sgm.group_id, s.id, s.student_code, s.full_name, s.photo, s.phone, s.batch,
               a.status as att_status, a.check_in_time
        FROM student_group_members sgm
        JOIN students s ON sgm.student_id=s.id AND s.status='Active' AND s.login_enabled=1
        LEFT JOIN student_attendance a ON a.student_id=s.id AND a.attendance_date='$today'
        ORDER BY sgm.group_id, CASE WHEN a.status='Present' THEN 0 ELSE 1 END, s.full_name
    ");
    if ($gsR)
        while ($r = $gsR->fetch_assoc())
            $groupStudents[(int) $r['group_id']][] = $r;
} catch (Exception $e) {
}

// ── Consecutive absent days for today's absent students ──
$consecutiveAbsent = [];
$allAbsentIds = array_merge(array_column($morningAbsentArr, 'id'), array_column($eveningAbsentArr, 'id'));
if (!empty($allAbsentIds)) {
    $ids_ca = implode(',', array_map('intval', $allAbsentIds));
    try {
        $caR = $conn->query("SELECT student_id, MAX(attendance_date) as last_present_date, DATEDIFF('$today', MAX(attendance_date)) as days_absent FROM student_attendance WHERE student_id IN ($ids_ca) AND status='Present' GROUP BY student_id");
        if ($caR)
            while ($r = $caR->fetch_assoc())
                $consecutiveAbsent[(int) $r['student_id']] = $r;
    } catch (Exception $e) {
    }
}

// ── Today's Attention counts ──
$absent3plus = 0;
try {
    $a3r = $conn->query("SELECT COUNT(DISTINCT s.id) as c FROM students s WHERE s.status='Active' AND s.login_enabled=1 AND NOT EXISTS (SELECT 1 FROM student_attendance a WHERE a.student_id=s.id AND a.status='Present' AND a.attendance_date >= DATE_SUB('$today', INTERVAL 2 DAY)) AND EXISTS (SELECT 1 FROM student_attendance a2 WHERE a2.student_id=s.id)");
    if ($a3r)
        $absent3plus = (int) $a3r->fetch_assoc()['c'];
} catch (Exception $e) {
}
$pendingInquiries = 0;
try {
    $piR = $conn->query("SELECT COUNT(*) as c FROM inquiries WHERE status IN ('new','followup')");
    if ($piR)
        $pendingInquiries = (int) $piR->fetch_assoc()['c'];
} catch (Exception $e) {
}

// ── Upcoming Leads for sidebar widget ──
$upcomingLeads = [];
try {
    $ulR = $conn->query("
        SELECT id, name, course_interested, photo, followup_at, status
        FROM inquiries
        WHERE status NOT IN ('converted','closed')
          AND followup_at IS NOT NULL
        ORDER BY
          CASE WHEN followup_at < NOW() THEN 0 ELSE 1 END,
          followup_at ASC
        LIMIT 6
    ");
    if ($ulR) while ($r = $ulR->fetch_assoc()) $upcomingLeads[] = $r;
} catch (Exception $e) {}

$overdueProjects = 0;
try {
    $opR = $conn->query("SELECT COUNT(*) as c FROM student_projects WHERE status='In Progress' AND end_date < '$today'");
    if ($opR)
        $overdueProjects = (int) $opR->fetch_assoc()['c'];
} catch (Exception $e) {
}

// ── Recent Activity feed ──
$recentActivityArr = [];
try {
    $raR = $conn->query("
        (SELECT 'fee_paid' as type, s.full_name as who, CONCAT('paid ₹',FORMAT(p.amount_paid,0)) as detail, p.created_at as ts FROM payments p JOIN students s ON p.student_id=s.id ORDER BY p.created_at DESC LIMIT 3)
        UNION ALL (SELECT 'attendance' as type, s.full_name as who, 'marked attendance' as detail, a.created_at as ts FROM student_attendance a JOIN students s ON a.student_id=s.id WHERE a.attendance_date='$today' AND a.status='Present' ORDER BY a.created_at DESC LIMIT 2)
        UNION ALL (SELECT 'admission' as type, s.full_name as who, 'enrolled' as detail, s.created_at as ts FROM students s WHERE s.status='Active' ORDER BY s.created_at DESC LIMIT 2)
        UNION ALL (SELECT 'inquiry' as type, i.name as who, 'new inquiry' as detail, i.created_at as ts FROM inquiries i ORDER BY i.created_at DESC LIMIT 2)
        ORDER BY ts DESC LIMIT 8
    ");
    if ($raR)
        while ($r = $raR->fetch_assoc())
            $recentActivityArr[] = $r;
} catch (Exception $e) {
}

// ── Batch attendance percentages ──
$morning_total = $morning_present_count + $morning_absent_count;
$evening_total = $evening_present_count + $evening_absent_count;
$morning_pct = $morning_total > 0 ? round(($morning_present_count / $morning_total) * 100) : 0;
$evening_pct = $evening_total > 0 ? round(($evening_present_count / $evening_total) * 100) : 0;

// ══════════════════════════════════════
// RECENT ADMISSIONS
// ══════════════════════════════════════
$recentAdmissions = $conn->query("
    SELECT id,student_code,full_name,photo,enrollment_date
    FROM students WHERE status='Active'
    ORDER BY enrollment_date DESC LIMIT 8
");

// ══════════════════════════════════════
// TOP 5 RANKING
// ══════════════════════════════════════
$top_students_data = array_slice($ranking, 0, 5, true);
$top_student_ids = array_keys($top_students_data);
$top_students = [];
if (!empty($top_student_ids)) {
    $ids_str = implode(',', $top_student_ids);
    $tsd = $conn->query("
        SELECT s.id,s.student_code,s.full_name,s.photo,s.batch,c.name as course_name
        FROM students s JOIN courses c ON s.course_id=c.id
        WHERE s.id IN ($ids_str)
        ORDER BY FIELD(s.id,$ids_str)
    ");
    while ($r = $tsd->fetch_assoc()) {
        $top_students[$r['id']] = array_merge($r, $top_students_data[$r['id']]);
    }
}

// ══════════════════════════════════════
// WORST 5
// ══════════════════════════════════════
$worst_students = [];
if (!empty($ranking)) {
    $ranking_reversed = array_reverse($ranking, true);
    $worst_data = array_slice($ranking_reversed, 0, 5, true);
    $worst_ids = array_keys($worst_data);
    if (!empty($worst_ids)) {
        $wids_str = implode(',', $worst_ids);
        $wq = $conn->query("
            SELECT s.id, s.student_code, s.full_name, s.photo, s.batch, c.name as course_name
            FROM students s JOIN courses c ON s.course_id=c.id
            WHERE s.id IN ($wids_str)
            ORDER BY FIELD(s.id,$wids_str)
        ");
        if ($wq) {
            while ($r = $wq->fetch_assoc()) {
                $worst_students[$r['id']] = array_merge($r, $worst_data[$r['id']]);
            }
        }
    }
}
?>


<style>
    /* ══════════════════════════════════════════════════════════
       PREMIUM FLAT ERP DASHBOARD — CLEAN REFERENCE MATCH
    ══════════════════════════════════════════════════════════ */
    :root {
        --blue: #2563eb;
        --blue-lt: #eff6ff;
        --blue-bd: #bfdbfe;
        --orange: #f97316;
        --orange-lt: #fff7ed;
        --orange-bd: #fed7aa;
        --green: #16a34a;
        --green-lt: #f0fdf4;
        --green-bd: #bbf7d0;
        --red: #dc2626;
        --red-lt: #fef2f2;
        --red-bd: #fecaca;
        --purple: #7c3aed;
        --purple-lt: #f5f3ff;
        --purple-bd: #ddd6fe;
        --amber: #d97706;
        --amber-lt: #fffbeb;
        --amber-bd: #fde68a;
        --surface: #ffffff;
        --bg: #f1f5f9;
        --border: #e2e8f0;
        --text: #0f172a;
        --muted: #64748b;
        --radius: 10px;
        --sh-sm: 0 1px 4px rgba(0, 0, 0, .08), 0 1px 2px rgba(0, 0, 0, .05);
        --sh-md: 0 4px 16px rgba(0, 0, 0, .10);
        --indigo-600: #4f46e5;
        --indigo-400: #818cf8;
        --indigo-700: #4338ca;
        --indigo-100: #e0e7ff;
    }

    /* ── BODY BACKGROUND — clean flat gray ── */
    body {
        background: var(--bg) !important;
    }

    /* ══════════════════════════════════════
   PAGE HERO — compact dark banner
══════════════════════════════════════ */
    .page-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 6px;
        padding: 9px 16px;
        background: linear-gradient(120deg, #1e1b4b 0%, #2e1065 50%, #0f2044 100%);
        border-radius: 11px;
        box-shadow: 0 4px 18px rgba(30, 27, 75, .4);
    }

    .page-hero-title {
        font-size: 15px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -.2px;
    }

    .page-hero-title span {
        color: #a5b4fc
    }

    .page-hero-sub {
        font-size: 10px;
        color: rgba(255, 255, 255, .5);
        margin-top: 1px
    }

    .page-hero-date {
        font-size: 10px;
        color: rgba(255, 255, 255, .75);
        font-weight: 600;
        background: rgba(255, 255, 255, .1);
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 99px;
        padding: 3px 11px;
    }

    /* ═══ STAT CARD BASE ═══ */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        margin-bottom: 12px;
    }

    .stat-card {
        background: var(--surface);
        border-radius: var(--radius);
        padding: 14px 13px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 12px;
        min-height: 88px;
        position: relative;
        /* overflow: hidden; */
        cursor: default;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--sh-md);
    }

    /* Colored left-accent strip */
    .stat-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        border-radius: 4px 0 0 4px;
    }

    .stat-card::after {
        display: none;
    }

    .stat-card>* {
        position: relative;
        z-index: 1;
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .stat-right {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        flex: 1;
        min-width: 0;
    }

    .stat-mini-label {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .5px;
        color: var(--muted);
        text-transform: uppercase;
        margin-bottom: 1px;
        line-height: 1;
    }

    .stat-num {
        font-size: 28px;
        font-weight: 900;
        line-height: 1;
        color: var(--text);
        letter-spacing: -1px;
        font-variant-numeric: tabular-nums;
        display: block;
        width: 100%;
    }

    .stat-label {
        font-size: 9px;
        font-weight: 700;
        color: var(--muted);
        letter-spacing: .4px;
        text-transform: uppercase;
        margin-top: 3px;
        line-height: 1.2;
    }

    /* Badge — top right corner */
    .stat-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--red);
        color: #fff;
        font-size: 9px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        padding: 0 3px;
        box-shadow: 0 2px 6px rgba(220, 38, 38, .5);
        z-index: 10;
        line-height: 1;
    }

    .stat-badge.amber-badge {
        background: var(--amber);
        box-shadow: 0 2px 6px rgba(217, 119, 6, .5);
    }

    /* ═══ FLAT ACCENT COLOURS PER CARD ═══ */
    .sc-indigo::before {
        background: #4f46e5;
    }

    .sc-indigo .stat-icon {
        background: #eff1fe;
        color: #4f46e5;
    }

    .sc-purple::before {
        background: var(--purple);
    }

    .sc-purple .stat-icon {
        background: var(--purple-lt);
        color: var(--purple);
    }

    .sc-morning::before {
        background: var(--orange);
    }

    .sc-morning .stat-icon {
        background: var(--orange-lt);
        color: var(--orange);
    }

    .sc-navy::before {
        background: #1e3a5f;
    }

    .sc-navy .stat-icon {
        background: #e1eef8;
        color: #1e3a5f;
    }

    .sc-royal::before {
        background: var(--blue);
    }

    .sc-royal .stat-icon {
        background: var(--blue-lt);
        color: var(--blue);
    }

    .sc-emerald::before {
        background: var(--green);
    }

    .sc-emerald .stat-icon {
        background: var(--green-lt);
        color: var(--green);
    }

    .sc-rose::before {
        background: var(--red);
    }

    .sc-rose .stat-icon {
        background: var(--red-lt);
        color: var(--red);
    }

    /* pending fees card */
    .sc-fees::before {
        background: var(--amber);
    }

    .sc-fees .stat-icon {
        background: var(--amber-lt);
        color: var(--amber);
    }

    /* ══════════════════════════════════════
   SECTION HEADERS
══════════════════════════════════════ */
    .sec-head {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 8px;
    }

    .sec-head-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #fff;
        flex-shrink: 0;
    }

    .sec-head h2 {
        font-size: 13px;
        font-weight: 800;
        margin: 0;
        color: var(--text)
    }

    .sec-head p {
        font-size: 9.5px;
        color: var(--muted);
        margin: 0
    }

    /* ═══ PERFORMER PANELS — sidebar vertical list ═══ */
    .perf-panel {
        background: var(--surface);
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        overflow: hidden;
        margin-bottom: 10px;
    }

    .perf-panel-head {
        padding: 9px 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .perf-panel-head.best {
        background: #166534;
    }

    .perf-panel-head.worst {
        background: #7f1d1d;
    }

    .perf-panel-icon {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #fff;
        background: rgba(255, 255, 255, .2);
    }

    .perf-panel-title {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .8px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .92);
        margin: 0;
    }

    .perf-list {
        padding: 4px 0;
    }

    .perf-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 12px;
        transition: background .15s ease;
        cursor: pointer;
        border-bottom: 1px solid #f8fafc;
        text-decoration: none;
        color: var(--text);
    }

    .perf-row:last-child {
        border-bottom: none;
    }

    .perf-row:hover {
        background: #f8fafc;
    }

    .perf-row-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e5e7eb;
        position: relative;
    }

    .perf-row-avatar.best-av {
        border: 2px solid #86efac;
    }

    .perf-row-avatar.worst-av {
        border: 2px solid #fca5a5;
    }

    .perf-row-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .perf-row-avatar i {
        font-size: 14px;
        color: #9ca3af;
    }

    .perf-rank-dot {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #ef4444;
        color: #fff;
        font-size: 7px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .25);
    }

    .rank-1-dot {
        background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
    }

    .perf-row-info {
        flex: 1;
        min-width: 0;
    }

    .perf-row-name {
        font-size: 11.5px;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .perf-row-meta {
        font-size: 9.5px;
        color: var(--muted);
        font-weight: 500;
    }

    .perf-row-pct {
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .pct-best {
        color: var(--green);
    }

    .pct-worst {
        color: var(--red);
    }

    /* legacy perf-strip classes kept for any other pages */
    .perf-strip {
        border-radius: 10px;
        padding: 14px 13px 12px;
        overflow: visible;
        position: relative;
        height: 100%;
    }

    .perf-strip.best {
        background: #166534;
        box-shadow: 0 4px 18px rgba(22, 101, 52, .4);
        border: 1px solid rgba(134, 239, 172, .2);
    }

    .perf-strip.worst {
        background: #7f1d1d;
        box-shadow: 0 4px 18px rgba(127, 29, 29, .4);
        border: 1px solid rgba(252, 165, 165, .15);
    }

    .perf-strip-title {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .75);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .perf-avatars {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 8px 4px 4px 4px;
        scrollbar-width: none;
    }

    .perf-avatars::-webkit-scrollbar {
        display: none;
    }

    .perf-av-item {
        flex: 0 0 auto;
        text-align: center;
        cursor: pointer;
        transition: transform .18s ease;
        margin-top: 6px;
    }

    .perf-av-item:hover {
        transform: translateY(-4px);
    }

    .perf-av-ring {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        padding: 3px;
        margin: 0 auto 5px;
        position: relative;
        overflow: visible;
    }

    .best .perf-av-ring {
        background: rgba(255, 255, 255, .9);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, .5), 0 0 14px rgba(74, 222, 128, .5);
    }

    .worst .perf-av-ring {
        background: rgba(255, 255, 255, .85);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, .45), 0 0 14px rgba(252, 165, 165, .5);
    }

    .perf-av-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        overflow: hidden;
        background: #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255, 255, 255, .6);
    }

    .perf-av-inner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .perf-av-inner i {
        font-size: 28px;
        color: #9ca3af;
    }

    .perf-rank-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 21px;
        height: 21px;
        border-radius: 50%;
        background: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 900;
        color: #fff;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(239, 68, 68, .6);
        padding: 0 4px;
    }

    .rank-1 .perf-rank-badge {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        box-shadow: 0 2px 8px rgba(251, 191, 36, .7);
    }

    .perf-name {
        font-size: 10px;
        font-weight: 700;
        color: rgba(255, 255, 255, .92);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 72px;
        margin-top: 2px;
    }

    /* ══════════════════════════════════════
   ATTENDANCE PANELS — white card, colored header bar
══════════════════════════════════════ */
    .att-panel {
        background: var(--surface);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        overflow: hidden;
    }

    .att-panel-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
    }

    /* Morning header — amber/gold */
    .att-panel.morning-panel .att-panel-head {
        background: linear-gradient(120deg, #78350f, #b45309, #d97706);
    }

    /* Evening header — navy/blue */
    .att-panel.evening-panel .att-panel-head {
        background: linear-gradient(120deg, #1e3a5f, #1e40af, #2563eb);
    }

    .att-batch-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: #fff;
        flex-shrink: 0;
        background: rgba(255, 255, 255, .2);
        border: 2px solid rgba(255, 255, 255, .35);
    }

    .att-panel-head h5 {
        font-size: 13px;
        font-weight: 800;
        margin: 0;
        color: #fff
    }

    .att-panel-head .att-sub {
        font-size: 10.5px;
        font-weight: 700;
        color: rgba(255, 255, 255, .85);
        margin-top: 1px;
    }

    .att-panel-head .att-sub .present-num {
        color: #86efac;
        font-size: 12px;
        font-weight: 900
    }

    .att-panel-head .att-sub .absent-num {
        color: #fca5a5;
        font-size: 12px;
        font-weight: 900
    }

    .att-scroll-area {
        padding: 10px 13px;
        overflow-x: auto;
        max-height: 220px;
        overflow-y: auto
    }

    /* Story cards inside attendance */
    .story-container {
        display: flex;
        flex-wrap: wrap;
        gap: 12px
    }

    .story-card {
        text-align: center;
        cursor: pointer;
        transition: transform .18s ease
    }

    .story-card:hover {
        transform: translateY(-3px)
    }

    .story-avatar {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        margin: 0 auto 5px;
        position: relative;
        padding: 3px;
    }

    .story-avatar.present {
        background: #16a34a;
        box-shadow: 0 0 0 2.5px rgba(22, 163, 74, .35)
    }

    .story-avatar.absent {
        background: #dc2626;
        box-shadow: 0 0 0 2.5px rgba(220, 38, 38, .35)
    }

    .story-avatar-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2.5px solid rgba(255, 255, 255, .85);
        overflow: hidden;
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .story-avatar-inner img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .story-avatar-inner i {
        font-size: 24px;
        color: #9ca3af
    }

    .story-rank-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        width: 19px;
        height: 19px;
        border-radius: 50%;
        background: #f59e0b;
        color: #fff;
        font-size: 8px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        box-shadow: 0 1px 5px rgba(0, 0, 0, .3);
    }

    .story-rank-badge.top-3 {
        background: #d97706
    }

    .story-rank-badge.top-10 {
        background: #059669
    }

    .story-name {
        font-size: 9px;
        font-weight: 600;
        color: #374151;
        max-width: 72px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0 auto;
    }

    .story-time {
        font-size: 8px;
        color: #9ca3af
    }

    /* ══════════════════════════════════════
   FEES STORY SCROLLING CARDS
══════════════════════════════════════ */
    .fees-story-wrapper {
        overflow: hidden;
        position: relative;
        width: 100%;
        padding: 8px 0
    }

    .fees-story-track {
        display: flex;
        gap: 12px;
        width: max-content;
        animation: feesScroll 35s linear infinite;
    }

    .fees-story-wrapper:hover .fees-story-track {
        animation-play-state: paused
    }

    @keyframes feesScroll {
        from {
            transform: translateX(0)
        }

        to {
            transform: translateX(-50%)
        }
    }

    .fees-story-card {
        flex: 0 0 auto;
        width: 78px;
        text-align: center;
        cursor: pointer;
        transition: transform .2s ease;
    }

    .fees-story-card:hover {
        transform: translateY(-4px) scale(1.04)
    }

    .fees-avatar {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        padding: 3px;
        margin: auto;
        background: linear-gradient(135deg, #059669, #34d399);
        box-shadow: 0 0 12px rgba(16, 185, 129, .45);
        position: relative;
    }

    .fees-avatar-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid white;
        overflow: hidden;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fees-avatar-inner img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .fees-avatar-inner i {
        font-size: 20px;
        color: #9ca3af
    }

    .fees-rank {
        position: absolute;
        top: -4px;
        right: -4px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: white;
        font-size: 8px;
        font-weight: 900;
        width: 19px;
        height: 19px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(251, 191, 36, .5);
    }

    .fees-name {
        font-size: 9.5px;
        font-weight: 600;
        margin-top: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .fees-amount {
        font-size: 10px;
        color: #059669;
        font-weight: 800
    }

    .fees-date {
        font-size: 7.5px;
        color: #9ca3af
    }

    /* ══════════════════════════════════════
   RANKING CARDS
══════════════════════════════════════ */
    .rank-card {
        background: var(--surface);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        padding: 11px;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .rank-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--sh-md)
    }

    .rank-medal {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #fff;
        flex-shrink: 0;
    }

    .medal-1 {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        box-shadow: 0 3px 8px rgba(251, 191, 36, .5)
    }

    .medal-2 {
        background: linear-gradient(135deg, #94a3b8, #64748b)
    }

    .medal-3 {
        background: linear-gradient(135deg, #cd7f32, #b8722c)
    }

    .medal-4,
    .medal-5 {
        background: linear-gradient(135deg, #34d399, #059669)
    }

    /* ══════════════════════════════════════
   COLLECTION CARD
══════════════════════════════════════ */
    .collection-card {
        background: linear-gradient(135deg, #3730a3 0%, #4f46e5 55%, #6d28d9 100%);
        border-radius: var(--radius);
        padding: 16px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 28px rgba(67, 56, 202, .45);
    }

    .collection-card::after {
        content: '';
        position: absolute;
        right: -28px;
        top: -28px;
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .09);
    }

    .collection-label {
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .8px;
        text-transform: uppercase;
        opacity: .7;
        margin-bottom: 5px
    }

    .collection-amt {
        font-size: 26px;
        font-weight: 900;
        letter-spacing: -.5px;
        margin-bottom: 2px;
        text-shadow: 0 2px 8px rgba(0, 0, 0, .2)
    }

    .collection-period {
        font-size: 10px;
        opacity: .6
    }

    /* ══════════════════════════════════════
   TABLE CARDS
══════════════════════════════════════ */
    .table-card {
        background: white;
        border-radius: var(--radius);
        padding: 13px;
        box-shadow: var(--sh-sm);
        border: 1px solid var(--border);
    }

    .table thead th {
        background: #eef2ff;
        color: #3730a3;
        font-weight: 700;
        border: none;
        padding: 7px 10px;
        font-size: 10.5px;
    }

    .table tbody td {
        padding: 6px 10px;
        vertical-align: middle;
        font-size: 11.5px
    }

    .table-hover tbody tr:hover {
        background: #f5f3ff
    }

    /* ══════════════════════════════════════
   CARD ICONS & BADGES
══════════════════════════════════════ */
    .card-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: white;
    }

    .icon-purple {
        background: linear-gradient(135deg, #4f46e5, #7c3aed)
    }

    .icon-success {
        background: linear-gradient(135deg, #059669, #10b981)
    }

    .icon-danger {
        background: linear-gradient(135deg, #dc2626, #ef4444)
    }

    /* ══════════════════════════════════════
   RECENT ADMISSIONS
══════════════════════════════════════ */
    .recent-admissions-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin
    }

    .admission-story-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 75px;
        text-align: center;
        transition: transform .25s ease;
    }

    .admission-story-card:hover {
        transform: translateY(-4px)
    }

    .story-photo-wrapper {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: 2px solid #86efac;
        padding: 2px;
        background: white;
        margin-bottom: 5px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(16, 185, 129, .25);
    }

    .story-photo-wrapper img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover
    }

    .story-placeholder {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 22px;
    }

    .admission-story-card .story-name {
        font-size: .74rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
        max-width: 72px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .admission-story-card .story-date {
        font-size: .64rem;
        color: #9ca3af
    }

    .admission-story-card:hover .story-name {
        color: var(--indigo-600)
    }

    .d-flex.gap-3 {
        gap: 9px !important
    }

    /* ══════════════════════════════════════
   ROW / SPACING
══════════════════════════════════════ */
    .row.g-4 {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 10px
    }

    .row.g-3 {
        --bs-gutter-x: 9px;
        --bs-gutter-y: 9px
    }

    .col-12.mt-2,
    .mt-2 {
        margin-top: 6px !important
    }

    .mb-3 {
        margin-bottom: 8px !important
    }

    .mb-1 {
        margin-bottom: 2px !important
    }

    /* ══════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════ */
    @media (max-width: 1399px) {
        .stat-num {
            font-size: 26px
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            font-size: 19px;
            min-width: 42px;
            min-height: 42px
        }

        .stat-card {
            gap: 9px;
            padding: 12px 10px
        }
    }

    @media (max-width: 1199px) {
        .stat-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 8px
        }

        .stat-num {
            font-size: 28px
        }
    }

    @media (max-width: 767px) {
        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px
        }

        .stat-num {
            font-size: 24px
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            font-size: 17px;
            min-width: 38px;
            min-height: 38px
        }

        .stat-card {
            gap: 8px;
            padding: 11px 9px;
            min-height: 78px
        }

        .perf-strip {
            padding: 10px 11px
        }

        .collection-amt {
            font-size: 22px
        }

        body {
            background: #eef0f5 !important
        }
    }

    /* ══════════════════════════════════════
       DASHBOARD LAYOUT — Main + Sidebar
    ══════════════════════════════════════ */
    .dash-sidebar {
        position: sticky;
        top: calc(var(--nb-h) + 16px);
        align-self: flex-start
    }

    /* ── BATCH SECTION ── */
    .batch-section {
        background: var(--surface);
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        overflow: hidden;
        margin-bottom: 10px
    }

    .batch-section-head {
        padding: 10px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px
    }

    .batch-section-head.morning {
        background: #b45309;
    }

    .batch-section-head.evening {
        background: #1e3a5f;
    }

    .batch-section-head h3 {
        font-size: 15px;
        font-weight: 800;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .batch-summary-stats {
        display: flex;
        gap: 16px;
        flex-wrap: wrap
    }

    .batch-summary-stat {
        text-align: center;
        color: rgba(255, 255, 255, .9)
    }

    .batch-summary-stat .bss-val {
        font-size: 18px;
        font-weight: 800;
        display: block;
        line-height: 1.1
    }

    .batch-summary-stat .bss-lbl {
        font-size: 9px;
        font-weight: 600;
        letter-spacing: .5px;
        text-transform: uppercase;
        opacity: .7
    }

    .batch-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(50%, 1fr));
        gap: 12px;
        padding: 16px
    }

    /* ── INDIVIDUAL BATCH CARD ── */
    .batch-card {
        background: var(--surface);
        border-radius: 12px;
        border: 1.5px solid var(--border);
        padding: 16px;
        cursor: pointer;
        transition: all .2s ease;
        position: relative;
        overflow: hidden
    }

    .batch-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--sh-md);
        border-color: var(--indigo-400)
    }

    .batch-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 10px
    }

    .batch-card-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--text);
        margin: 0
    }

    .batch-card-course {
        font-size: 10px;
        color: var(--muted);
        font-weight: 500;
        margin-top: 2px
    }

    .batch-card-badge {
        font-size: 18px;
        font-weight: 900;
        color: var(--indigo-600);
        background: var(--indigo-100);
        border-radius: 8px;
        padding: 2px 10px;
        line-height: 1.3
    }

    .batch-card-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 10px
    }

    .batch-card-stat {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600
    }

    .batch-card-stat i {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px
    }

    .batch-card-stat.s-present i {
        background: #dcfce7;
        color: #16a34a
    }

    .batch-card-stat.s-absent i {
        background: #fef2f2;
        color: #dc2626
    }

    .batch-card-stat.s-total i {
        background: #eef2ff;
        color: #4f46e5
    }

    .batch-card-stat.s-seats i {
        background: #fef3c7;
        color: #d97706
    }

    .batch-progress {
        height: 6px;
        background: #f1f5f9;
        border-radius: 99px;
        margin-top: 12px;
        overflow: hidden
    }

    .batch-progress-fill {
        height: 100%;
        border-radius: 99px;
        transition: width .6s ease;
        background: var(--green);
    }

    .batch-progress-fill.low {
        background: var(--red);
    }

    .batch-progress-fill.medium {
        background: var(--amber);
    }

    /* ── BATCH CARD STUDENT AVATARS ── */
    .batch-card-avatars {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 10px;
        flex-wrap: wrap     ;
        overflow: hidden;
    }

    .bca-av {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        flex-shrink: 0;
        padding: 2px;
        position: relative;
    }

    .bca-av.av-present {
        background: var(--green);
        box-shadow: 0 0 0 1.5px rgba(22, 163, 74, .3);
    }

    .bca-av.av-absent {
        background: var(--red);
        box-shadow: 0 0 0 1.5px rgba(220, 38, 38, .3);
    }

    .bca-av-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        overflow: hidden;
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid rgba(255, 255, 255, .85);
    }

    .bca-av-inner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .bca-av-inner i {
        font-size: 11px;
        color: #9ca3af;
    }

    .bca-more {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 1.5px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8px;
        font-weight: 800;
        color: var(--muted);
        flex-shrink: 0;
    }

    /* ── BATCH SECTION REDESIGN OVERRIDES ── */
    .batch-section {
        background: #fff;
        border-radius: 10px;
        border-color: #e8eaed;
        box-shadow: 0 1px 2px rgba(0,0,0,.04)
    }
    .batch-section-head {
        padding: 10px 16px;
        gap: 8px;
        background: #fff;
        border-bottom: 1px solid #f1f5f9
    }
    .batch-section-head.morning {
        background: #fff;
        border-left: 3px solid #ea580c
    }
    .batch-section-head.evening {
        background: #fff;
        border-left: 3px solid #2563eb
    }
    .batch-section-head h3 {
        font-size: 13px;
        color: #1e293b
    }
    .batch-section-head.morning h3 i { color: #ea580c }
    .batch-section-head.evening h3 i { color: #2563eb }
    .batch-summary-stat {
        color: #1e293b;
        padding: 3px 10px;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #f1f5f9
    }
    .batch-summary-stat .bss-val {
        font-size: 14px;
        color: #1e293b
    }
    .batch-summary-stat .bss-lbl { color: #94a3b8 }
    .batch-card-grid { gap: 10px; padding: 12px }
    .batch-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e8eaed;
        padding: 12px
    }
    .batch-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,.08);
        border-color: #cbd5e1
    }
    .batch-morning .batch-card:hover { border-color: #fed7aa }
    .batch-evening .batch-card:hover { border-color: #bfdbfe }
    .batch-card-header { margin-bottom: 6px }
    .batch-card-title { font-size: 12px }
    .batch-card-course { color: #94a3b8 }
    .batch-card-right { text-align: right; flex-shrink: 0 ;display: flex;gap: 10px;}
    .batch-card-right .batch-card-stat { margin-top: 1px }
    .batch-card-badge {
        font-size: 16px;
        padding: 1px 8px;
        border-radius: 6px;
        display: inline-block
    }
    .batch-morning .batch-card-badge { color: #ea580c; background: #fff7ed }
    .batch-evening .batch-card-badge { color: #2563eb; background: #eff6ff }
    .batch-card-stat { font-size: 11px; gap: 4px; color: #64748b }
    .batch-card-stat i { width: 20px; height: 20px; border-radius: 5px; font-size: 9px }
    .batch-card-stats { gap: 6px; margin-top: 8px }
    .batch-progress { height: 4px; margin-top: 8px }
    .batch-card-avatars { gap: 3px; margin-top: 8px }
    .bca-av { width: 60px; height: 60px; padding: 1.5px }
    .bca-av.av-present { box-shadow: 0 0 0 1px rgba(22,163,74,.25) }
    .bca-av.av-absent { box-shadow: 0 0 0 1px rgba(220,38,38,.25) }
    .bca-av-inner { background: #f1f5f9; border-width: 1.5px; border-color: #fff }
    .bca-av-inner i { font-size: 10px; color: #94a3b8 }
    .bca-more { width: 26px; height: 26px; border: 1px solid #e8eaed; color: #94a3b8 }

    /* ── UPCOMING LEADS ── */
    .qa-panel {
        background: var(--surface);
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        padding: 14px;
        margin-bottom: 10px
    }

    .qa-panel-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .qa-panel-title .qa-view-all {
        margin-left: auto;
        font-size: 10px;
        font-weight: 600;
        color: var(--indigo-600);
        text-decoration: none;
        transition: var(--ease)
    }
    .qa-panel-title .qa-view-all:hover { color: var(--indigo-700); text-decoration: underline }

    .lead-list { display: flex; flex-direction: column; gap: 4px }

    .lead-row {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 6px 8px;
        border-radius: 10px;
        text-decoration: none;
        color: var(--text);
        transition: all .18s ease;
        background: #f8fafc;
        border: 1px solid transparent
    }
    .lead-row:hover {
        background: var(--indigo-100);
        border-color: var(--indigo-400);
        color: var(--indigo-700);
        transform: translateX(3px);
        box-shadow: 0 3px 10px rgba(79,70,229,.08)
    }

    .lead-av {
        width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700; color: #fff; overflow: hidden;
        background: linear-gradient(135deg, var(--indigo-600), var(--accent))
    }
    .lead-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50% }

    .lead-info { flex: 1; min-width: 0 }
    .lead-name { font-size: 11px; font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
    .lead-course { font-size: 9px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis }

    .lead-meta { text-align: right; flex-shrink: 0 }
    .lead-time { font-size: 9px; font-weight: 600; color: var(--muted); white-space: nowrap }
    .lead-badge {
        display: inline-block; font-size: 8px; font-weight: 700;
        padding: 1px 6px; border-radius: 99px; margin-top: 2px;
        text-transform: uppercase; letter-spacing: .3px
    }
    .lead-badge-overdue { background: #fef2f2; color: #dc2626; border: 1px solid rgba(220,38,38,.2) }
    .lead-badge-today { background: #fffbeb; color: #d97706; border: 1px solid rgba(217,119,6,.2) }
    .lead-badge-upcoming { background: #f0fdf4; color: #059669; border: 1px solid rgba(5,150,105,.2) }
    .lead-badge-new { background: #eff6ff; color: #2563eb; border: 1px solid rgba(37,99,235,.2) }

    .lead-empty { text-align: center; color: var(--muted); font-size: 10px; padding: 14px 0 }
    .lead-empty i { display: block; font-size: 16px; opacity: .3; margin-bottom: 5px }

    /* ── ATTENTION PANEL ── */
    .attn-panel {
        background: var(--surface);
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        padding: 10px;
        margin-bottom: 10px
    }

    .attn-panel-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .attn-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 8px;
        border-radius: 10px;
        margin-bottom: 4px;
        text-decoration: none;
        color: var(--text);
        transition: all .18s ease;
        background: #f8fafc;
        border: 1px solid transparent
    }

    .attn-row:hover {
        background: #fff;
        border-color: var(--border);
        transform: translateX(3px);
        color: var(--text)
    }

    .attn-icon {
        width: 24px;
        height: 24px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        flex-shrink: 0
    }

    .attn-count {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
        min-width: 24px
    }

    .attn-desc {
        flex: 1;
        font-size: 9px;
        font-weight: 600;
        color: #64748b
    }

    .attn-arrow {
        color: #cbd5e1;
        font-size: 11px
    }

    /* ── ACTIVITY TIMELINE ── */
    .activity-panel {
        background: var(--surface);
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--sh-sm);
        padding: 16px;
        margin-bottom: 10px
    }

    .activity-panel-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        position: relative
    }

    .activity-item:last-child {
        border-bottom: none
    }

    .activity-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #fff;
        flex-shrink: 0;
        margin-top: 2px
    }

    .activity-content {
        flex: 1;
        min-width: 0
    }

    .activity-text {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .activity-time {
        font-size: 9.5px;
        color: #94a3b8;
        font-weight: 500
    }

    /* ── BATCH DETAIL OFFCANVAS ── */
    #batchDrawer .offcanvas-header {
        padding: 16px 20px;
        background: var(--indigo-900);
        color: #fff
    }

    #batchDrawer .offcanvas-header .btn-close {
        filter: invert(1) grayscale(1) brightness(2)
    }

    #batchDrawer .offcanvas-body {
        padding: 16px 20px;
        background: #f8fafc
    }

    .batch-drawer-overview {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-bottom: 16px
    }

    .batch-drawer-stat {
        background: #fff;
        border-radius: 10px;
        padding: 10px 8px;
        text-align: center;
        border: 1px solid var(--border)
    }

    .batch-drawer-stat .bd-val {
        font-size: 20px;
        font-weight: 800;
        color: var(--text);
        display: block
    }

    .batch-drawer-stat .bd-lbl {
        font-size: 9px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .5px
    }

    .drawer-section-title {
        font-size: 11px;
        font-weight: 800;
        color: var(--text);
        margin: 16px 0 8px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: .5px
    }

    /* ── STUDENT MINI CARD (drawer) ── */
    .student-mini-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: #fff;
        border-radius: 10px;
        border: 1px solid var(--border);
        margin-bottom: 6px;
        transition: all .18s ease;
        cursor: pointer
    }

    .student-mini-card:hover {
        border-color: var(--indigo-400);
        box-shadow: 0 4px 14px rgba(99, 102, 241, .13);
        transform: translateY(-1px);
        background: #f8f9ff
    }

    .smc-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .smc-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover
    }

    .smc-avatar i {
        font-size: 16px;
        color: #9ca3af
    }

    .smc-avatar.present-ring {
        border: 2.5px solid #86efac
    }

    .smc-avatar.absent-ring {
        border: 2.5px solid #fca5a5
    }

    .smc-info {
        flex: 1;
        min-width: 0
    }

    .smc-name {
        font-size: 12px;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .smc-meta {
        font-size: 9.5px;
        color: var(--muted);
        font-weight: 500
    }

    .smc-badge {
        font-size: 9px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 99px;
        letter-spacing: .3px;
        white-space: nowrap
    }

    .smc-badge.badge-present {
        background: #dcfce7;
        color: #15803d
    }

    .smc-badge.badge-absent {
        background: #fef2f2;
        color: #b91c1c
    }

    .smc-actions {
        display: flex;
        gap: 4px
    }

    .smc-actions a {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        text-decoration: none;
        transition: all .15s ease;
        border: 1px solid var(--border);
        background: #f8fafc;
        color: var(--muted)
    }

    .smc-actions a:hover {
        transform: scale(1.1)
    }

    .smc-actions .act-call:hover {
        background: #dcfce7;
        color: #16a34a;
        border-color: #86efac
    }

    .smc-actions .act-wa:hover {
        background: #dcfce7;
        color: #25d366;
        border-color: #86efac
    }

    .smc-actions .act-profile:hover {
        background: var(--indigo-100);
        color: var(--indigo-600);
        border-color: var(--indigo-400)
    }

    .smc-actions .act-remind:hover {
        background: #fef3c7;
        color: #d97706;
        border-color: #fcd34d
    }

    /* ── RESPONSIVE — new layout ── */
    @media(max-width:991px) {
        .dash-sidebar {
            position: static
        }

        .batch-card-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))
        }
    }

    @media(max-width:575px) {
        .batch-card-grid {
            grid-template-columns: 1fr
        }

        .batch-drawer-overview {
            grid-template-columns: repeat(2, 1fr)
        }

        .batch-summary-stats {
            gap: 10px
        }
    }
</style>

<!-- ══════════════════════════════════
     PAGE HERO
══════════════════════════════════ -->


<!-- ══════════════════════════════════
     STAT CARDS
══════════════════════════════════ -->
<div class="stat-grid">

    <!-- Total Students — Deep Purple -->
    <a href="students.php" style="text-decoration:none">
        <div class="stat-card sc-indigo">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= number_format($total_active) ?></div>
                <div class="stat-label">Total<br>Students</div>
            </div>
        </div>
    </a>

    <!-- New This Month — Violet -->
    <div class="stat-card sc-purple">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div class="stat-right">
            <div class="stat-mini-label">This Month</div>
            <div class="stat-num"><?= number_format($new_this_month) ?></div>
            <div class="stat-label">New Enrolled</div>
        </div>
    </div>

    <!-- Morning Present — Amber, badge = absent -->
    <a href="students.php?batch=Morning" style="text-decoration:none">
        <div class="stat-card sc-morning" style="position:relative">
            <span class="stat-badge"><?= $morning_absent_stat ?></span>
            <div class="stat-icon"><i class="fas fa-sun"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= number_format($morning_present_stat) ?></div>
                <div class="stat-label">Morning</div>
            </div>
        </div>
    </a>

    <!-- Evening Present — Dark Navy, badge = absent -->
    <a href="students.php?batch=Evening" style="text-decoration:none">
        <div class="stat-card sc-navy" style="position:relative">
            <span class="stat-badge"><?= $evening_absent_stat ?></span>
            <div class="stat-icon"><i class="fas fa-moon"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= number_format($evening_present_stat) ?></div>
                <div class="stat-label">Evening</div>
            </div>
        </div>
    </a>

    <!-- Active Today — Royal Blue, badge = inactive -->
    <?php $inactive_today = max(0, $total_active - $active_today); ?>
    <div class="stat-card sc-royal" style="position:relative">
        <span class="stat-badge"><?= $inactive_today ?></span>
        <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
        <div class="stat-right">
            <div class="stat-num"><?= number_format($active_today) ?></div>
            <div class="stat-label">Active<br>Today</div>
        </div>
    </div>

    <!-- Fees Paid — Emerald, badge = overdue -->
    <a href="payments.php" style="text-decoration:none">
        <div class="stat-card sc-emerald" style="position:relative">
            <span class="stat-badge amber-badge"><?= $overdue_count ?></span>
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-right">
                <div class="stat-mini-label">Fees</div>
                <div class="stat-num"><?= number_format($total_paid_students) ?></div>
                <div class="stat-label">Paid</div>
            </div>
        </div>
    </a>

    <!-- Pending Monthly Fees — Amber -->
    <a href="payments.php" style="text-decoration:none">
        <div class="stat-card sc-fees" style="position:relative">
            <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-right">
                <div class="stat-mini-label">Pending</div>
                <div class="stat-num" style="font-size:18px;letter-spacing:-.5px">
                    ₹<?= number_format($pending_monthly_fees, 0) ?></div>
                <div class="stat-label">Monthly Fees</div>
            </div>
        </div>
    </a>

</div><!-- /stat-grid -->

<!-- ══ STUDENT PROJECT TRACKER CARDS ══ -->
<!-- <div class="sec-head" style="margin-top:4px">
    <div class="sec-head-icon" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)"><i
            class="fas fa-clipboard-list"></i></div>
    <div>
        <h2>Student Project Tracker</h2>
    </div>
</div>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px">
    <a href="student_task_manager.php" style="text-decoration:none">
        <div class="stat-card sc-emerald">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= $spt_active ?></div>
                <div class="stat-label">Active<br>Students</div>
            </div>
        </div>
    </a>
    <a href="student_task_manager.php" style="text-decoration:none">
        <div class="stat-card sc-rose">
            <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= $spt_inactive ?></div>
                <div class="stat-label">Inactive<br>Students</div>
            </div>
        </div>
    </a>
    <a href="student_task_manager.php" style="text-decoration:none">
        <div class="stat-card sc-royal">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= $spt_in_progress ?></div>
                <div class="stat-label">Projects<br>In Progress</div>
            </div>
        </div>
    </a>
    <a href="student_task_manager.php" style="text-decoration:none">
        <div class="stat-card sc-purple">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-right">
                <div class="stat-num"><?= $spt_completed ?></div>
                <div class="stat-label">Completed<br>Projects</div>
            </div>
        </div>
    </a>
</div> -->

<div class="row g-3">

    <!-- ═══════════════════════════════════════════════════════
     MAIN CONTENT AREA
═══════════════════════════════════════════════════════ -->
    <div class="col-lg-9 dash-main">


        <!-- Attendance section label -->
        <!-- <div class="sec-head" style="margin-bottom:8px">
            <div class="sec-head-icon" style="background:#059669"><i class="fas fa-calendar-check"></i></div>
            <div>
                <h2>Today's Attendance</h2>
                <p><//?= date('l, d F Y') ?></p>
            </div>
        </div> -->


<?php
// ── Auto-switch Morning/Evening render order based on India time ──
$ist_now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$isEveningFirst = (int) $ist_now->format('H') >= 14;
ob_start();
?>
        <!-- ══════════════════════════════════════
         🌞 MORNING BATCHES SECTION
    ══════════════════════════════════════ -->
        <div class="batch-section batch-morning mb-3">
            <div class="batch-section-head morning">
                <h3><i class="fas fa-sun"></i> Morning Batches</h3>
                <div class="batch-summary-stats">
                    <div class="batch-summary-stat"><span class="bss-val"><?= count($morningGroups) ?: 1 ?></span><span
                            class="bss-lbl">Groups</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"><?= $morning_total ?></span><span
                            class="bss-lbl">Students</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"
                            style="color:#16a34a"><?= $morning_present_count ?></span><span
                            class="bss-lbl">Present</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"
                            style="color:#dc2626"><?= $morning_absent_count ?></span><span class="bss-lbl">Absent</span>
                    </div>
                    <div class="batch-summary-stat"><span class="bss-val"><?= $morning_pct ?>%</span><span
                            class="bss-lbl">Rate</span></div>
                </div>
            </div>
            <div class="batch-card-grid">
                <?php if (!empty($morningGroups)): ?>
                    <?php foreach ($morningGroups as $mg):
                        $gid = (int) $mg['id'];
                        $pct = (int) $mg['att_pct'];
                        $pctClass = $pct >= 75 ? '' : ($pct >= 50 ? 'medium' : 'low');
                        ?>
                        <div class="batch-card" onclick="openBatchDrawer('group',<?= $gid ?>,'morning')"
                            data-group-id="<?= $gid ?>">
                            <div class="batch-card-header">
                                <div>
                                    <h4 class="batch-card-title"><i class="fas fa-users-rectangle"
                                            style="color:var(--indigo-400);margin-right:4px"></i>
                                        <?= htmlspecialchars($mg['group_name']) ?></h4>
                                    <!-- <div class="batch-card-course"><//?= // htmlspecialchars($mg['course_name']) ?></div> -->
                                </div>
                                <div class="batch-card-right">
                                    <div class="batch-card-stat">
                                        <span><?= $mg['present_count'] ?> / <?= $mg['active_members'] ?></span>
                                    </div>
                                    <div class="batch-card-badge"><?= $pct ?>%</div>

                                </div>
                            </div>
                            <!-- <div class="batch-progress">
                                <div class="batch-progress-fill <//?= $pctClass ?>" style="width:<//?= $pct ?>%"></div>
                            </div> -->
                            <!-- <div class="batch-card-stats">
                                        <div class="batch-card-stat s-present">
                                            <//?= $mg['present_count'] ?>
                                        </div> -->
                            <!-- <div class="batch-card-stat s-absent"><i class="fas fa-xmark"></i> <//?= $mg['absent_count'] ?>
                                    Absent</div> -->
                            <!-- <div class="batch-card-stat s-total">
                                            <//?= $mg['active_members'] ?>
                                        </div> -->
                            <!-- <div class="batch-card-stat s-seats"><i class="fas fa-chair"></i> <//?= $mg['active_members'] ?>
                                    Seats</div> -->
                            <!-- </div> -->
                            <?php
                            /* Avatar preview strip for this group */
                            $grpStudents = $groupStudents[$gid] ?? [];
                            if (!empty($grpStudents)):
                                $maxAv = 20;
                                $shown = 0;
                                $total_g = count($grpStudents);
                                ?>
                                <div class="batch-card-avatars">
                                    <?php foreach ($grpStudents as $gs):
                                        if ($shown >= $maxAv)
                                            break;
                                        $isP = ($gs['att_status'] === 'Present');
                                        $hasP = !empty($gs['photo']) && file_exists($gs['photo']);
                                        ?>
                                        <div class="bca-av <?= $isP ? 'av-present' : 'av-absent' ?>"
                                            title="<?= htmlspecialchars($gs['full_name']) ?> — <?= $isP ? 'Present' : 'Absent' ?>">
                                            <div class="bca-av-inner">
                                                <?php if ($hasP): ?><img src="<?= htmlspecialchars($gs['photo']) ?>" alt="">
                                                <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php $shown++; endforeach; ?>
                                    <?php if ($total_g > $maxAv): ?>
                                        <div class="bca-more">+<?= $total_g - $maxAv ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <!-- Always show an "All Morning" card -->
                <div class="batch-card" onclick="openBatchDrawer('batch',0,'morning')"
                    style="<?= !empty($morningGroups) ? 'border-style:dashed' : '' ?>">
                    <div class="batch-card-header">
                        <div>
                            <h4 class="batch-card-title"><i class="fas fa-sun"
                                    style="color:#d97706;margin-right:4px"></i>
                                <?= !empty($morningGroups) ? 'All Morning Students' : 'Morning Batch' ?></h4>
                            <div class="batch-card-course">View all morning attendance</div>
                        </div>
                        <div class="batch-card-right">
                            <div class="batch-card-stat">
                                <span><?= $morning_present_count ?> / <?= $morning_active ?></span>
                            </div>
                            <div class="batch-card-badge"><?= $morning_pct ?>%</div>

                        </div>
                    </div>
                    <!-- <div class="batch-progress">
                        <div class="batch-progress-fill <?= $morning_pct >= 75 ? '' : ($morning_pct >= 50 ? 'medium' : 'low') ?>"
                            style="width:<?= $morning_pct ?>%"></div>
                    </div> -->
                    <!-- <div class="batch-card-stats">
                        <div class="batch-card-stat s-present"><i class="fas fa-check"></i>
                            <?= $morning_present_count ?> Present</div>
                        <div class="batch-card-stat s-absent"><i class="fas fa-xmark"></i>
                            <?= $morning_absent_count ?>
                            Absent</div>
                        <div class="batch-card-stat s-total"><i class="fas fa-users"></i> <?= $morning_total ?>
                            Total
                        </div>
                        <div class="batch-card-stat s-seats"><i class="fas fa-chair"></i> <?= $morning_active ?>
                            Seats
                        </div>
                    </div> -->
                    <?php
                    /* Avatar strip — morning present + absent combined */
                    $allMornAv = array_merge(
                        array_map(fn($s) => array_merge($s, ['_present' => true]), $morningPresentArr),
                        array_map(fn($s) => array_merge($s, ['_present' => false]), $morningAbsentArr)
                    );
                    if (!empty($allMornAv)):
                        $maxAv = 20;
                        $shown = 0;
                        $totalAv = count($allMornAv);
                        ?>
                        <div class="batch-card-avatars">
                            <?php foreach ($allMornAv as $ma):
                                if ($shown >= $maxAv)
                                    break;
                                $hasP = !empty($ma['photo']) && file_exists($ma['photo']);
                                ?>
                                <div class="bca-av <?= $ma['_present'] ? 'av-present' : 'av-absent' ?>"
                                    title="<?= htmlspecialchars($ma['full_name']) ?>">
                                    <div class="bca-av-inner">
                                        <?php if ($hasP): ?><img src="<?= htmlspecialchars($ma['photo']) ?>" alt="">
                                        <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                                    </div>
                                </div>
                                <?php $shown++; endforeach; ?>
                            <?php if ($totalAv > $maxAv): ?>
                                <div class="bca-more">+<?= $totalAv - $maxAv ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php $morningHTML = ob_get_clean(); ob_start(); ?>
        <!-- ══════════════════════════════════════
         🌙 EVENING BATCHES SECTION
    ══════════════════════════════════════ -->
        <div class="batch-section batch-evening mb-3">
            <div class="batch-section-head evening">
                <h3><i class="fas fa-moon"></i> Evening Batches</h3>
                <div class="batch-summary-stats">
                    <div class="batch-summary-stat"><span class="bss-val"><?= count($eveningGroups) ?: 1 ?></span><span
                            class="bss-lbl">Groups</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"><?= $evening_total ?></span><span
                            class="bss-lbl">Students</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"
                            style="color:#16a34a"><?= $evening_present_count ?></span><span
                            class="bss-lbl">Present</span></div>
                    <div class="batch-summary-stat"><span class="bss-val"
                            style="color:#dc2626"><?= $evening_absent_count ?></span><span class="bss-lbl">Absent</span>
                    </div>
                    <div class="batch-summary-stat"><span class="bss-val"><?= $evening_pct ?>%</span><span
                            class="bss-lbl">Rate</span></div>
                </div>
            </div>
            <div class="batch-card-grid">
                <?php if (!empty($eveningGroups)): ?>
                    <?php foreach ($eveningGroups as $eg):
                        $gid = (int) $eg['id'];
                        $pct = (int) $eg['att_pct'];
                        $pctClass = $pct >= 75 ? '' : ($pct >= 50 ? 'medium' : 'low');
                        ?>
                        <div class="batch-card" onclick="openBatchDrawer('group',<?= $gid ?>,'evening')"
                            data-group-id="<?= $gid ?>">
                            <div class="batch-card-header">
                                <div>
                                    <h4 class="batch-card-title"><i class="fas fa-users-rectangle"
                                            style="color:var(--indigo-400);margin-right:4px"></i>
                                        <?= htmlspecialchars($eg['group_name']) ?></h4>
                                    <!-- <div class="batch-card-course"><   ?=// htmlspecialchars($eg['course_name']) ?></div> -->
                                </div>
                                <div class="batch-card-right">
                                    <div class="batch-card-stat">
                                        <span><?= $eg['present_count'] ?> / <?= $eg['active_members'] ?></span>
                                    </div>
                                    <div class="batch-card-badge"><?= $pct ?>%</div>

                                </div>
                            </div>
                            <?php
                            $grpStudentsE = $groupStudents[$gid] ?? [];
                            if (!empty($grpStudentsE)):
                                $maxAvE = 20;
                                $shownE = 0;
                                $totalGE = count($grpStudentsE);
                                ?>
                                <div class="batch-card-avatars">
                                    <?php foreach ($grpStudentsE as $ge):
                                        if ($shownE >= $maxAvE)
                                            break;
                                        $isP2 = ($ge['att_status'] === 'Present');
                                        $hasP2 = !empty($ge['photo']) && file_exists($ge['photo']);
                                        ?>
                                        <div class="bca-av <?= $isP2 ? 'av-present' : 'av-absent' ?>"
                                            title="<?= htmlspecialchars($ge['full_name']) ?>">
                                            <div class="bca-av-inner">
                                                <?php if ($hasP2): ?><img src="<?= htmlspecialchars($ge['photo']) ?>" alt="">
                                                <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php $shownE++; endforeach; ?>
                                    <?php if ($totalGE > $maxAvE): ?>
                                        <div class="bca-more">+<?= $totalGE - $maxAvE ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="batch-card" onclick="openBatchDrawer('batch',0,'evening')"
                    style="<?= !empty($eveningGroups) ? 'border-style:dashed' : '' ?>">
                    <div class="batch-card-header">
                        <div>
                            <h4 class="batch-card-title"><i class="fas fa-moon"
                                    style="color:#2563eb;margin-right:4px"></i>
                                <?= !empty($eveningGroups) ? 'All Evening Students' : 'Evening Batch' ?></h4>
                            <div class="batch-card-course">View all evening attendance</div>
                        </div>
                        <div class="batch-card-right">
                            <div class="batch-card-stat">
                                <span><?= $evening_present_count ?> / <?= $evening_active ?></span>
                            </div>
                            <div class="batch-card-badge"><?= $evening_pct ?>%</div>
                        </div>
                    </div>
                    <?php
                    $allEveAv = array_merge(
                        array_map(fn($s) => array_merge($s, ['_present' => true]), $eveningPresentArr),
                        array_map(fn($s) => array_merge($s, ['_present' => false]), $eveningAbsentArr)
                    );
                    if (!empty($allEveAv)):
                        $maxAvE2 = 20;
                        $shownE2 = 0;
                        $totalAvE2 = count($allEveAv);
                        ?>
                        <div class="batch-card-avatars">
                            <?php foreach ($allEveAv as $ea):
                                if ($shownE2 >= $maxAvE2)
                                    break;
                                $hasPE = !empty($ea['photo']) && file_exists($ea['photo']);
                                ?>
                                <div class="bca-av <?= $ea['_present'] ? 'av-present' : 'av-absent' ?>"
                                    title="<?= htmlspecialchars($ea['full_name']) ?>">
                                    <div class="bca-av-inner">
                                        <?php if ($hasPE): ?><img src="<?= htmlspecialchars($ea['photo']) ?>" alt="">
                                        <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                                    </div>
                                </div>
                                <?php $shownE2++; endforeach; ?>
                            <?php if ($totalAvE2 > $maxAvE2): ?>
                                <div class="bca-more">+<?= $totalAvE2 - $maxAvE2 ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php
$eveningHTML = ob_get_clean();
// Render sections in order based on India time
echo $isEveningFirst ? $eveningHTML . $morningHTML : $morningHTML . $eveningHTML;
?>

        <!-- ══════════════════════════════════════
         STUDENT FEES STATUS (preserved)
    ══════════════════════════════════════ -->
        <style>
            .fees-tick-badge {
                position: absolute;
                bottom: -2px;
                right: -2px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #16a34a;
                border: 2px solid #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 8px;
                color: #fff;
                font-weight: 900;
                box-shadow: 0 2px 6px rgba(22, 163, 74, .55);
                z-index: 5
            }

            .fees-partial-badge {
                position: absolute;
                bottom: -2px;
                right: -2px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #f59e0b;
                border: 2px solid #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 8px;
                color: #fff;
                font-weight: 900;
                box-shadow: 0 2px 6px rgba(245, 158, 11, .55);
                z-index: 5
            }

            .fees-group-divider {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                margin: 4px 0 6px
            }

            .fees-group-divider span {
                font-size: 9px;
                font-weight: 800;
                letter-spacing: .7px;
                text-transform: uppercase;
                white-space: nowrap;
                padding: 2px 10px;
                border-radius: 99px
            }

            .fees-group-divider span.unpaid-label {
                background: #fef2f2;
                color: #b91c1c;
                border: 1px solid #fecaca
            }

            .fees-group-divider span.paid-label {
                background: #f0fdf4;
                color: #15803d;
                border: 1px solid #bbf7d0
            }

            .fees-group-divider hr {
                flex: 1;
                border: none;
                border-top: 1px dashed #e2e8f0;
                margin: 0
            }

            .fees-overdue-badge {
                position: absolute;
                top: -3px;
                left: -3px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                color: #fff;
                font-size: 8px;
                font-weight: 900;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #fff;
                box-shadow: 0 2px 6px rgba(0, 0, 0, .28);
                z-index: 6;
                line-height: 1
            }

            .overdue-1 {
                background: #facc15;
                color: #713f12
            }

            .overdue-2 {
                background: #fb923c
            }

            .overdue-3 {
                background: #ef4444
            }

            .overdue-critical {
                background: #b91c1c;
                animation: overdueBlink 1s ease-in-out infinite
            }

            @keyframes overdueBlink {
                0% {
                    transform: scale(1)
                }

                50% {
                    transform: scale(1.18)
                }

                100% {
                    transform: scale(1)
                }
            }
        </style>

        <div class="table-card position-relative mb-3">
            <?php
            $fees_unpaid = [];
            $fees_paid = [];
            while ($row = $allStudentsFees->fetch_assoc()) {
                $row['photo'] = (!empty($row['photo']) && file_exists($row['photo'])) ? $row['photo'] : null;
                $pending = (float) $row['pending_fees'];
                $month_paid = (float) $row['month_paid'];
                $row['is_fully_paid'] = ($pending <= 0);
                $row['paid_this_month'] = ($month_paid > 0);
                if (!$row['is_fully_paid'] && !$row['paid_this_month']) {
                    $fees_unpaid[] = $row;
                } else {
                    $fees_paid[] = $row;
                }
            }
            $cnt_unpaid = count($fees_unpaid);
            $cnt_paid = count($fees_paid);
            $visible_paid = array_filter($fees_paid, function ($student) {
                return !$student['is_fully_paid'];
            });
            $cnt_paid_visible = count($visible_paid);
            $total_shown = $cnt_unpaid + $cnt_paid_visible;
            ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="card-icon icon-purple" style="width:30px;height:30px;font-size:13px;border-radius:9px">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold" style="font-size:13px;color:#1e1b4b">Student Fees Status</h5>
                    <small class="text-muted" style="font-size:10px">
                        <?= date('M Y', strtotime($start_date)) ?>&nbsp;&bull;&nbsp;
                        <span class="text-danger fw-bold"><?= $cnt_unpaid ?> Unpaid</span>&nbsp;&bull;&nbsp;
                        <span class="text-success fw-bold"><?= $cnt_paid_visible ?>
                            Paid</span>&nbsp;&bull;&nbsp;<?= $total_shown ?> Total Active
                    </small>
                </div>
                <div class="ms-auto d-flex gap-2 flex-wrap">
                    <a href="overdue_students_full_list.php" class="btn btn-sm btn-outline-danger"
                        style="font-size:10px;padding:3px 10px">
                        <i class="fas fa-exclamation-triangle"></i> Overdue (<?= $overdue_count ?>)
                    </a>
                    <a href="paid_students_full_list.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"
                        class="btn btn-sm btn-outline-success" style="font-size:10px;padding:3px 10px">
                        <i class="fas fa-check-circle"></i> Paid (<?= $total_paid_students ?>)
                    </a>
                </div>
            </div>
            <hr style="margin:8px 0;border-color:#e2e8f0">
            <?php if ($total_shown > 0): ?>
                <div style="overflow-y:auto;max-height:340px;padding:2px 2px 8px;scrollbar-width:thin">
                    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start">
                        <?php foreach ($fees_unpaid as $student):
                            $last_amt = (float) ($student['last_payment_amount'] ?? 0);
                            if (!empty($student['last_payment_date'])) {
                                $months_overdue = max(1, (int) floor((strtotime(date('Y-m-d')) - strtotime($student['last_payment_date'])) / (60 * 60 * 24 * 30)));
                            } else {
                                $months_overdue = 1;
                            }
                            if ($months_overdue >= 5) {
                                $badge_class = 'overdue-critical';
                            } elseif ($months_overdue >= 3) {
                                $badge_class = 'overdue-3';
                            } elseif ($months_overdue == 2) {
                                $badge_class = 'overdue-2';
                            } else {
                                $badge_class = 'overdue-1';
                            }
                            ?>
                            <div class="fees-story-card"
                                onclick="window.location.href='student_details.php?id=<?= (int) $student['id'] ?>'">
                                <div class="fees-avatar"
                                    style="background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 0 12px rgba(220,38,38,.45)">
                                    <div class="fees-overdue-badge <?= $badge_class ?>"
                                        title="<?= $months_overdue ?> month<?= $months_overdue > 1 ? 's' : '' ?> overdue">
                                        <?= $months_overdue ?>
                                    </div>
                                    <div class="fees-avatar-inner">
                                        <?php if ($student['photo']): ?><img src="<?= htmlspecialchars($student['photo']) ?>"
                                                alt="">
                                        <?php else: ?><i class="fas fa-user-graduate"></i><?php endif; ?>
                                    </div>
                                    <?php if ($last_amt > 0): ?>
                                        <div class="fees-partial-badge" title="Partially paid"><i class="fas fa-clock"
                                                style="font-size:7px"></i></div><?php endif; ?>
                                </div>
                                <div class="fees-name"><?= htmlspecialchars(explode(' ', trim($student['full_name']))[0]) ?>
                                </div>
                                <div class="fees-amount" style="color:#ef4444">
                                    <?= $last_amt > 0 ? '₹' . number_format($last_amt) : '—' ?>
                                </div>
                                <div class="fees-date" style="color:#ef4444;font-weight:800;font-size:8px">Unpaid</div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($fees_paid as $student):
                            if ($student['is_fully_paid'])
                                continue;
                            $last_amt = (float) ($student['last_payment_amount'] ?? 0);
                            $is_full = $student['is_fully_paid'];
                            $month_amt = (float) $student['month_paid'];
                            $display_amt = $month_amt > 0 ? $month_amt : $last_amt;
                            ?>
                            <div class="fees-story-card"
                                onclick="window.location.href='student_details.php?id=<?= (int) $student['id'] ?>'">
                                <div class="fees-avatar"
                                    style="background:linear-gradient(135deg,#059669,#34d399);box-shadow:0 0 12px rgba(16,185,129,.45)">
                                    <div class="fees-avatar-inner">
                                        <?php if ($student['photo']): ?><img src="<?= htmlspecialchars($student['photo']) ?>"
                                                alt="">
                                        <?php else: ?><i class="fas fa-user-graduate"></i><?php endif; ?>
                                    </div>
                                    <?php if ($is_full): ?>
                                        <div class="fees-tick-badge" title="Fees fully paid"><i class="fas fa-check"
                                                style="font-size:7px"></i></div><?php endif; ?>
                                </div>
                                <div class="fees-name"><?= htmlspecialchars(explode(' ', trim($student['full_name']))[0]) ?>
                                </div>
                                <div class="fees-amount" style="color:#059669">
                                    <?= $display_amt > 0 ? '₹' . number_format($display_amt) : '—' ?>
                                </div>
                                <div class="fees-date" style="color:#059669;font-weight:800;font-size:8px">
                                    <?= $is_full ? 'Complete ✔' : 'Paid' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> No student
                    fees
                    data available.</div>
            <?php endif; ?>
        </div>

        <!-- ══ RECENT ADMISSIONS (preserved) ══ -->
        <div class="sec-head" style="margin-bottom:6px">
            <div class="sec-head-icon" style="background:linear-gradient(135deg,#059669,#10b981)"><i
                    class="fas fa-user-check"></i></div>
            <div>
                <h2>Recent Admissions</h2>
                <p>Latest students enrolled</p>
            </div>
        </div>
        <div class="table-card mb-3">
            <?php if ($recentAdmissions->num_rows > 0): ?>
                <div class="recent-admissions-scroll">
                    <div class="d-flex gap-3" style="overflow-x:auto;padding:5px 0">
                        <?php while ($admission = $recentAdmissions->fetch_assoc()):
                            $has_photo = !empty($admission['photo']) && file_exists($admission['photo']);
                            ?>
                            <a href="student_details.php?id=<?= $admission['id'] ?>"
                                class="admission-story-card text-decoration-none">
                                <div class="story-photo-wrapper">
                                    <?php if ($has_photo): ?><img src="<?= htmlspecialchars($admission['photo']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="story-placeholder"><i class="fas fa-user-graduate"></i></div><?php endif; ?>
                                </div>
                                <p class="story-name"><?= htmlspecialchars($admission['full_name']) ?></p>
                                <small class="story-date"><?= date('d M', strtotime($admission['enrollment_date'])) ?></small>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> No recent
                    admissions found.</div>
            <?php endif; ?>
        </div>

        <!-- ══ COLLECTION + TOP COURSES (preserved) ══ -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="collection-card">
                    <div class="collection-label"><i class="fas fa-indian-rupee-sign"></i>
                        <?= date('M Y', strtotime($start_date)) ?> Collection</div>
                    <div class="collection-amt">₹<?= number_format($total_collection, 2) ?></div>
                    <div class="collection-period"><?= date('d M Y', strtotime($start_date)) ?> &mdash;
                        <?= date('d M Y', strtotime($end_date)) ?>
                    </div>
                    <div style="margin-top:10px;font-size:11px;opacity:.75"><i class="fas fa-users"></i>
                        <?= $total_paid_students ?> students paid</div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="table-card h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="card-icon icon-purple"
                            style="width:28px;height:28px;font-size:12px;border-radius:8px"><i
                                class="fas fa-trophy"></i></div>
                        <h6 class="mb-0 fw-bold" style="color:#3730a3;font-size:12.5px">Top Revenue Courses</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Students</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $course_revenue->data_seek(0);
                                while ($course = $course_revenue->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight:600;font-size:11px">
                                            <?= htmlspecialchars($course['name']) ?>
                                        </td>
                                        <td><span class="badge bg-info"
                                                style="font-size:9px"><?= $course['student_count'] ?></span></td>
                                        <td><strong
                                                style="font-size:11px;color:#3730a3">₹<?= number_format($course['total_revenue'], 2) ?></strong>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /dash-main -->

    <!-- ═══════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════ -->
    <div class="col-lg-3 dash-sidebar">



        <div class="row px-md-3">
            <!-- ═══ TOP 5 PERFORMERS ═══ -->
            <div class="col-md-6 px-3">
                <div class="row perf-panel">
                    <div class="perf-panel-head best">
                        <div class="perf-panel-icon"><i class="fas fa-trophy"></i></div>
                        <div class="perf-panel-title">Top Performers</div>
                    </div>
                    <div class="perf-list">
                        <?php if (!empty($top_students)):
                            $rn = 1;
                            foreach ($top_students as $sid => $st):
                                $hp = !empty($st['photo']) && file_exists($st['photo']);
                                $pct_label = isset($st['att_pct']) ? number_format($st['att_pct'], 1) . '%' : '--';
                                $batchLabel = $st['batch'] ?? '';
                                ?>
                                <a class="perf-row" href="student_details.php?id=<?= $sid ?>">
                                    <div class="perf-row-avatar best-av">
                                        <?php if ($hp): ?><img src="<?= htmlspecialchars($st['photo']) ?>" alt="">
                                        <?php else: ?><i class="fas fa-user-graduate"></i><?php endif; ?>
                                        <div class="perf-rank-dot <?= $rn === 1 ? 'rank-1-dot' : '' ?>">
                                            <?= $rn === 1 ? '<i class="fas fa-crown" style="font-size:6px"></i>' : $rn ?>
                                        </div>
                                    </div>
                                    <div class="perf-row-info">
                                        <div class="perf-row-name">
                                            <?= htmlspecialchars(explode(' ', $st['full_name'])[0]) ?>
                                        </div>
                                        <!-- <div class="perf-row-meta"><//?= htmlspecialchars($batchLabel) ?> Batch</div> -->
                                    </div>
                                    <!-- <div class="perf-row-pct pct-best"><//?= $pct_label ?></div> -->
                                </a>
                                <?php $rn++; endforeach;
                        else: ?>
                            <div style="text-align:center;color:var(--muted);font-size:11px;padding:14px 0">
                                <i class="fas fa-trophy"
                                    style="opacity:.25;font-size:18px;display:block;margin-bottom:6px"></i>No data yet
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══ WORST 5 PERFORMERS ═══ -->
            <div class=" col-md-6 px-3">
                <div class="row perf-panel">
                    <div class="perf-panel-head worst">
                        <div class="perf-panel-icon"><i class="fas fa-arrow-trend-down"></i></div>
                        <div class="perf-panel-title">Needs Attention</div>
                    </div>
                    <div class="perf-list">
                        <?php if (!empty($worst_students)):
                            foreach ($worst_students as $wsid => $wst):
                                $hp = !empty($wst['photo']) && file_exists($wst['photo']);
                                $pct_w = isset($wst['att_pct']) ? number_format($wst['att_pct'], 1) . '%' : '--';
                                $batchW = $wst['batch'] ?? '';
                                ?>
                                <a class="perf-row" href="student_details.php?id=<?= $wsid ?>">
                                    <div class="perf-row-avatar worst-av">
                                        <?php if ($hp): ?><img src="<?= htmlspecialchars($wst['photo']) ?>" alt="">
                                        <?php else: ?><i class="fas fa-user-graduate"></i><?php endif; ?>
                                        <div class="perf-rank-dot" style="background:#ef4444"><?= $wst['rank'] ?? '' ?>
                                        </div>
                                    </div>
                                    <div class="perf-row-info">
                                        <div class="perf-row-name">
                                            <?= htmlspecialchars(explode(' ', $wst['full_name'])[0]) ?>
                                        </div>
                                        <!-- <div class="perf-row-meta"><//?= htmlspecialchars($batchW) ?> Batch</div> -->
                                    </div>
                                    <!-- <div class="perf-row-pct pct-worst"><//?= $pct_w ?></div> -->
                                </a>
                            <?php endforeach;
                        else: ?>
                            <div style="text-align:center;color:var(--muted);font-size:11px;padding:14px 0">
                                <i class="fas fa-chart-line"
                                    style="opacity:.25;font-size:18px;display:block;margin-bottom:6px"></i>No data yet
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- ═══ UPCOMING LEADS ═══ -->
        <div class="qa-panel">
            <div class="qa-panel-title"><i class="fas fa-user-clock" style="color:#6366f1"></i> Upcoming Leads <a href="inquiries.php" class="qa-view-all">View All →</a></div>
            <?php if (!empty($upcomingLeads)): ?>
            <div class="lead-list">
                <?php foreach ($upcomingLeads as $lead):
                    $fu = strtotime($lead['followup_at']);
                    $now = time();
                    $isOverdue = $fu < $now;
                    $isToday = date('Y-m-d', $fu) === date('Y-m-d', $now);
                    if ($isOverdue) { $badgeCls = 'lead-badge-overdue'; $badgeTxt = 'Overdue'; }
                    elseif ($isToday) { $badgeCls = 'lead-badge-today'; $badgeTxt = 'Today'; }
                    else { $badgeCls = 'lead-badge-upcoming'; $badgeTxt = date('d M', $fu); }
                    $initials = strtoupper(substr($lead['name'], 0, 1));
                    $hasPhoto = !empty($lead['photo']) && file_exists($lead['photo']);
                ?>
                <a href="inquiry_details.php?id=<?= $lead['id'] ?>" class="lead-row">
                    <div class="lead-av">
                        <?php if ($hasPhoto): ?><img src="<?= htmlspecialchars($lead['photo']) ?>" alt="">
                        <?php else: ?><?= $initials ?><?php endif; ?>
                    </div>
                    <div class="lead-info">
                        <div class="lead-name"><?= htmlspecialchars($lead['name']) ?></div>
                        <div class="lead-course"><?= htmlspecialchars($lead['course_interested'] ?? '—') ?></div>
                    </div>
                    <div class="lead-meta">
                        <div class="lead-time"><?= date('h:i A', $fu) ?></div>
                        <div class="lead-badge <?= $badgeCls ?>"><?= $badgeTxt ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="lead-empty"><i class="fas fa-user-clock"></i>No upcoming leads.<br><a href="inquiries.php" style="color:var(--indigo-600);font-weight:600">Add a lead →</a></div>
            <?php endif; ?>
        </div>

        <div class="attn-panel">
            <div class="attn-panel-title"><i class="fas fa-bell" style="color:#ef4444"></i> Today's Attention</div>
            <a href="overdue_students_full_list.php" class="attn-row">
                <div class="attn-icon" style="background:#fef2f2;color:#dc2626"><i class="fas fa-wallet"></i></div>
                <div class="attn-count"><?= $overdue_count ?></div>
                <div class="attn-desc">Students<br>Fee Pending</div>
                <i class="fas fa-chevron-right attn-arrow"></i>
            </a>
            <a href="attendance_report.php" class="attn-row">
                <div class="attn-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-user-clock"></i>
                </div>
                <div class="attn-count"><?= $absent3plus ?></div>
                <div class="attn-desc">Students<br>Absent for 3+ Days</div>
                <i class="fas fa-chevron-right attn-arrow"></i>
            </a>
            <a href="student_task_manager.php" class="attn-row">
                <div class="attn-icon" style="background:#eef2ff;color:#4f46e5"><i class="fas fa-clipboard-list"></i>
                </div>
                <div class="attn-count"><?= $overdueProjects ?></div>
                <div class="attn-desc">Projects<br>Overdue</div>
                <i class="fas fa-chevron-right attn-arrow"></i>
            </a>
            <a href="inquiries.php" class="attn-row">
                <div class="attn-icon" style="background:#f0fdf4;color:#059669"><i class="fas fa-filter"></i></div>
                <div class="attn-count"><?= $pendingInquiries ?></div>
                <div class="attn-desc">Inquiries<br>Pending Follow-up</div>
                <i class="fas fa-chevron-right attn-arrow"></i>
            </a>
        </div>

        <!-- ═══ RECENT ACTIVITY ═══ -->
        <div class="activity-panel">
            <div class="activity-panel-title"><i class="fas fa-clock-rotate-left" style="color:#6366f1"></i> Recent
                Activity</div>
            <?php if (!empty($recentActivityArr)): ?>
                <?php foreach ($recentActivityArr as $act):
                    $icon = 'fa-circle';
                    $color = '#6b7280';
                    if ($act['type'] === 'fee_paid') {
                        $icon = 'fa-indian-rupee-sign';
                        $color = '#059669';
                    } elseif ($act['type'] === 'attendance') {
                        $icon = 'fa-calendar-check';
                        $color = '#3b82f6';
                    } elseif ($act['type'] === 'admission') {
                        $icon = 'fa-user-plus';
                        $color = '#7c3aed';
                    } elseif ($act['type'] === 'inquiry') {
                        $icon = 'fa-filter';
                        $color = '#d97706';
                    }
                    $timeAgo = '';
                    if (!empty($act['ts'])) {
                        $diff = time() - strtotime($act['ts']);
                        if ($diff < 60)
                            $timeAgo = 'Just now';
                        elseif ($diff < 3600)
                            $timeAgo = floor($diff / 60) . ' min ago';
                        elseif ($diff < 86400)
                            $timeAgo = floor($diff / 3600) . ' hr ago';
                        else
                            $timeAgo = date('d M, h:i A', strtotime($act['ts']));
                    }
                    ?>
                    <div class="activity-item">
                        <div class="activity-dot" style="background:<?= $color ?>"><i class="fas <?= $icon ?>"></i></div>
                        <div class="activity-content">
                            <div class="activity-text"><?= htmlspecialchars($act['who']) ?>
                                <?= htmlspecialchars($act['detail']) ?>
                            </div>
                            <div class="activity-time"><?= $timeAgo ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;color:var(--muted);font-size:11px;padding:16px 0">
                    <i class="fas fa-clock" style="font-size:18px;opacity:.3;display:block;margin-bottom:6px"></i>No
                    recent
                    activity
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /dash-sidebar -->

</div><!-- /row -->

<!-- ═══════════════════════════════════════════════════════
     BATCH DETAIL OFFCANVAS DRAWER
═══════════════════════════════════════════════════════ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="batchDrawer" style="width:480px;max-width:95vw">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawerTitle" style="font-weight:800;font-size:15px"><i class="fas fa-users"></i>
            Batch Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Overview Stats -->
        <div class="batch-drawer-overview">
            <div class="batch-drawer-stat"><span class="bd-val" id="drawerStatPresent"
                    style="color:#16a34a">0</span><span class="bd-lbl">Present</span></div>
            <div class="batch-drawer-stat"><span class="bd-val" id="drawerStatAbsent"
                    style="color:#dc2626">0</span><span class="bd-lbl">Absent</span></div>
            <div class="batch-drawer-stat"><span class="bd-val" id="drawerStatTotal">0</span><span
                    class="bd-lbl">Total</span></div>
            <div class="batch-drawer-stat"><span class="bd-val" id="drawerStatPct"
                    style="color:var(--indigo-600)">0%</span><span class="bd-lbl">Rate</span></div>
        </div>
        <!-- Present Section -->
        <div class="drawer-section-title" id="drawerPresentTitle"><i class="fas fa-check-circle"
                style="color:#16a34a"></i> Present Students</div>
        <div id="drawerPresentList"></div>
        <!-- Absent Section -->
        <div class="drawer-section-title" id="drawerAbsentTitle" style="color:#dc2626"><i
                class="fas fa-exclamation-circle" style="color:#dc2626"></i> Absent Students</div>
        <div id="drawerAbsentList"></div>
    </div>
</div>

<!-- ═══ HIDDEN STUDENT DATA FOR DRAWER (PHP-rendered) ═══ -->
<div id="drawerDataContainer" style="display:none">

    <!-- Morning Batch - All Students -->
    <div data-drawer-id="batch-morning">
        <?php foreach ($morningPresentArr as $s):
            $hp = !empty($s['photo']) && file_exists($s['photo']);
            ?>
            <div class="student-mini-card" data-status="present" data-student-id="<?= $s['id'] ?>">
                <div class="smc-avatar present-ring">
                    <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                            class="fas fa-user-graduate"></i><?php endif; ?>
                </div>
                <div class="smc-info">
                    <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="smc-meta"><?= $s['student_code'] ?> &bull; <?= formatIndianTime($s['check_in_time']) ?>
                    </div>
                </div>
                <span class="smc-badge badge-present">Present</span>
            </div>
        <?php endforeach; ?>
        <?php foreach ($morningAbsentArr as $s):
            $hp = !empty($s['photo']) && file_exists($s['photo']);
            $ca = $consecutiveAbsent[(int) $s['id']] ?? null;
            $days = $ca ? (int) $ca['days_absent'] : 0;
            $lastDate = $ca ? date('d M', strtotime($ca['last_present_date'])) : 'N/A';
            $phone = $s['phone'] ?? '';
            $waPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($waPhone) === 10)
                $waPhone = '91' . $waPhone;
            ?>
            <div class="student-mini-card" data-status="absent" data-student-id="<?= $s['id'] ?>">
                <div class="smc-avatar absent-ring">
                    <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                            class="fas fa-user-graduate"></i><?php endif; ?>
                </div>
                <div class="smc-info">
                    <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="smc-meta"><?= $s['student_code'] ?> &bull; <?= $days ?>d absent &bull; Last:
                        <?= $lastDate ?>
                    </div>
                </div>
                <span class="smc-badge badge-absent">Absent</span>
                <div class="smc-actions">
                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="act-call" title="Call"><i
                            class="fas fa-phone"></i></a>
                    <a href="https://wa.me/<?= $waPhone ?>" target="_blank" class="act-wa" title="WhatsApp"><i
                            class="fab fa-whatsapp"></i></a>
                    <a href="student_details.php?id=<?= $s['id'] ?>" class="act-profile" title="Profile"><i
                            class="fas fa-user"></i></a>
                    <a href="#" class="act-remind" title="Send Reminder (Coming Soon)" onclick="event.preventDefault()"><i
                            class="fas fa-envelope"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Evening Batch - All Students -->
    <div data-drawer-id="batch-evening">
        <?php foreach ($eveningPresentArr as $s):
            $hp = !empty($s['photo']) && file_exists($s['photo']);
            ?>
            <div class="student-mini-card" data-status="present" data-student-id="<?= $s['id'] ?>">
                <div class="smc-avatar present-ring">
                    <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                            class="fas fa-user-graduate"></i><?php endif; ?>
                </div>
                <div class="smc-info">
                    <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="smc-meta"><?= $s['student_code'] ?> &bull; <?= formatIndianTime($s['check_in_time']) ?>
                    </div>
                </div>
                <span class="smc-badge badge-present">Present</span>
            </div>
        <?php endforeach; ?>
        <?php foreach ($eveningAbsentArr as $s):
            $hp = !empty($s['photo']) && file_exists($s['photo']);
            $ca = $consecutiveAbsent[(int) $s['id']] ?? null;
            $days = $ca ? (int) $ca['days_absent'] : 0;
            $lastDate = $ca ? date('d M', strtotime($ca['last_present_date'])) : 'N/A';
            $phone = $s['phone'] ?? '';
            $waPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($waPhone) === 10)
                $waPhone = '91' . $waPhone;
            ?>
            <div class="student-mini-card" data-status="absent" data-student-id="<?= $s['id'] ?>">
                <div class="smc-avatar absent-ring">
                    <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                            class="fas fa-user-graduate"></i><?php endif; ?>
                </div>
                <div class="smc-info">
                    <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                    <div class="smc-meta"><?= $s['student_code'] ?> &bull; <?= $days ?>d absent &bull; Last:
                        <?= $lastDate ?>
                    </div>
                </div>
                <span class="smc-badge badge-absent">Absent</span>
                <div class="smc-actions">
                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="act-call" title="Call"><i
                            class="fas fa-phone"></i></a>
                    <a href="https://wa.me/<?= $waPhone ?>" target="_blank" class="act-wa" title="WhatsApp"><i
                            class="fab fa-whatsapp"></i></a>
                    <a href="student_details.php?id=<?= $s['id'] ?>" class="act-profile" title="Profile"><i
                            class="fas fa-user"></i></a>
                    <a href="#" class="act-remind" title="Send Reminder (Coming Soon)" onclick="event.preventDefault()"><i
                            class="fas fa-envelope"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Per-Group Student Data -->
    <?php foreach ($groupStudents as $gid => $students): ?>
        <div data-drawer-id="group-<?= $gid ?>">
            <?php foreach ($students as $s):
                $hp = !empty($s['photo']) && file_exists($s['photo']);
                $isPresent = ($s['att_status'] === 'Present');
                ?>
                <?php if ($isPresent): ?>
                    <div class="student-mini-card" data-status="present" data-student-id="<?= $s['id'] ?>">
                        <div class="smc-avatar present-ring">
                            <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                                    class="fas fa-user-graduate"></i><?php endif; ?>
                        </div>
                        <div class="smc-info">
                            <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                            <div class="smc-meta"><?= $s['student_code'] ?> &bull;
                                <?= !empty($s['check_in_time']) ? formatIndianTime($s['check_in_time']) : 'Present' ?>
                            </div>
                        </div>
                        <span class="smc-badge badge-present">Present</span>
                    </div>
                <?php else: ?>
                    <?php
                    $ca = $consecutiveAbsent[(int) $s['id']] ?? null;
                    $days = $ca ? (int) $ca['days_absent'] : 0;
                    $lastDate = $ca ? date('d M', strtotime($ca['last_present_date'])) : 'N/A';
                    $phone = $s['phone'] ?? '';
                    $waPhone = preg_replace('/[^0-9]/', '', $phone);
                    if (strlen($waPhone) === 10)
                        $waPhone = '91' . $waPhone;
                    ?>
                    <div class="student-mini-card" data-status="absent" data-student-id="<?= $s['id'] ?>">
                        <div class="smc-avatar absent-ring">
                            <?php if ($hp): ?><img src="<?= htmlspecialchars($s['photo']) ?>" alt=""><?php else: ?><i
                                    class="fas fa-user-graduate"></i><?php endif; ?>
                        </div>
                        <div class="smc-info">
                            <div class="smc-name"><?= htmlspecialchars($s['full_name']) ?></div>
                            <div class="smc-meta"><?= $s['student_code'] ?> &bull; <?= $days ?>d absent &bull; Last:
                                <?= $lastDate ?>
                            </div>
                        </div>
                        <span class="smc-badge badge-absent">Absent</span>
                        <div class="smc-actions">
                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="act-call" title="Call"><i
                                    class="fas fa-phone"></i></a>
                            <a href="https://wa.me/<?= $waPhone ?>" target="_blank" class="act-wa" title="WhatsApp"><i
                                    class="fab fa-whatsapp"></i></a>
                            <a href="student_details.php?id=<?= $s['id'] ?>" class="act-profile" title="Profile"><i
                                    class="fas fa-user"></i></a>
                            <a href="#" class="act-remind" title="Send Reminder (Coming Soon)" onclick="event.preventDefault()"><i
                                    class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

</div><!-- /drawerDataContainer -->

<!-- ═══ DRAWER JAVASCRIPT ═══ -->
<script>
    function openBatchDrawer(type, id, batch) {
        var drawerId = type === 'group' ? 'group-' + id : 'batch-' + batch;
        var source = document.querySelector('[data-drawer-id="' + drawerId + '"]');
        if (!source) return;

        // Set drawer title
        var titleEl = document.getElementById('drawerTitle');
        if (type === 'group') {
            var card = document.querySelector('[data-group-id="' + id + '"]');
            var groupName = card ? card.querySelector('.batch-card-title').textContent.trim() : 'Group Details';
            titleEl.innerHTML = '<i class="fas fa-users-rectangle"></i> ' + groupName;
        } else {
            var batchLabel = batch.charAt(0).toUpperCase() + batch.slice(1);
            var batchIcon = batch === 'morning' ? 'fa-sun' : 'fa-moon';
            titleEl.innerHTML = '<i class="fas ' + batchIcon + '"></i> ' + batchLabel + ' Batch';
        }

        // Separate present and absent
        var presentContainer = document.getElementById('drawerPresentList');
        var absentContainer = document.getElementById('drawerAbsentList');
        presentContainer.innerHTML = '';
        absentContainer.innerHTML = '';

        var cards = source.querySelectorAll('.student-mini-card');
        var presentCount = 0, absentCount = 0;
        cards.forEach(function (card) {
            var clone = card.cloneNode(true);
            if (card.getAttribute('data-status') === 'present') {
                presentContainer.appendChild(clone);
                presentCount++;
            } else {
                absentContainer.appendChild(clone);
                absentCount++;
            }
        });

        var total = presentCount + absentCount;
        var pct = total > 0 ? Math.round((presentCount / total) * 100) : 0;

        document.getElementById('drawerStatPresent').textContent = presentCount;
        document.getElementById('drawerStatAbsent').textContent = absentCount;
        document.getElementById('drawerStatTotal').textContent = total;
        document.getElementById('drawerStatPct').textContent = pct + '%';

        document.getElementById('drawerPresentTitle').style.display = presentCount > 0 ? '' : 'none';
        document.getElementById('drawerAbsentTitle').style.display = absentCount > 0 ? '' : 'none';

        // ── Make student cards clickable → student_details.php ──
        var allDrawerCards = document.querySelectorAll('#drawerPresentList .student-mini-card, #drawerAbsentList .student-mini-card');
        allDrawerCards.forEach(function (card) {
            var sid = card.getAttribute('data-student-id');
            if (!sid) return;
            card.addEventListener('click', function () {
                window.location.href = 'student_details.php?id=' + sid;
            });
            // Prevent action buttons from triggering the card click
            var actionBtns = card.querySelectorAll('.smc-actions a');
            actionBtns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            });
        });

        // Open offcanvas
        var bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('batchDrawer'));
        bsOffcanvas.show();
    }
</script>

<?php include 'includes/footer.php'; ?>