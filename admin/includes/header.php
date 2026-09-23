<?php
ob_start();

require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

// NOTE: ranking_helper.php is NOT loaded here to save DB queries on every page.
// Pages that need ranking data (index.php, ranking.php, student_details.php) include it themselves.

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$admin        = getCurrentAdmin();

// Pending tasks badge — only tasks assigned to THIS admin, status Pending only
$pending_tasks = 0;
try {
    $tr = $conn->query("
        SELECT COUNT(*) as cnt FROM admin_tasks
        WHERE status = 'Pending'
          AND assigned_to = {$_SESSION['admin_id']}
    ");
    if ($tr) $pending_tasks = (int)$tr->fetch_assoc()['cnt'];
} catch (Exception $e) { $pending_tasks = 0; }

// Pending project verifications badge — for Admin/Super Admin
$pending_projects_count = 0;
try {
    $pp = $conn->query("SELECT COUNT(*) as cnt FROM student_projects WHERE verification_status = 'pending'");
    if ($pp) $pending_projects_count = (int)$pp->fetch_assoc()['cnt'];
} catch (Exception $e) { $pending_projects_count = 0; }

$role  = strtolower($_SESSION['admin_role'] ?? '');
$isSA  = ($role === 'super admin');
$isAdm = ($role === 'admin' || $isSA);
$canManageEvents = ($isAdm || $role === 'administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Next Academy &mdash; <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?></title>
<meta name="description" content="Next Academy Admin Panel — <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="Next Academy">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================================================================
   NEXT ACADEMY — PREMIUM ADMIN SHELL v2
   Aesthetic: Enterprise SaaS / Refined Indigo System
================================================================ */
:root{
    --sb-w:68px;
    --nb-h:56px;
    --indigo-950:#13103a;
    --indigo-900:#1e1b4b;
    --indigo-800:#2d2a6e;
    --indigo-700:#3730a3;
    --indigo-600:#4f46e5;
    --indigo-400:#818cf8;
    --indigo-100:#e0e7ff;
    --accent:#6366f1;
    --emerald:#059669;
    --crimson:#dc2626;
    --amber:#d97706;
    --sky:#0ea5e9;
    --bg:#f0f2f8;
    --surface:#fff;
    --border:rgba(0,0,0,.07);
    --text:#0f0a2e;
    --muted:#6b7280;
    --sh-sm:0 1px 3px rgba(0,0,0,.08),0 6px 16px rgba(0,0,0,.04);
    --sh-md:0 4px 14px rgba(0,0,0,.12),0 20px 40px rgba(0,0,0,.06);
    --radius:14px;
    --ease:.18s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sora','Segoe UI',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-thumb{background:var(--indigo-400);border-radius:99px}
::-webkit-scrollbar-track{background:transparent}

/* ── NAVBAR ── */
.na-nav{
    position:fixed;top:0;left:var(--sb-w);right:0;height:var(--nb-h);
    background:var(--indigo-900);
    display:flex;align-items:center;padding:0 20px;
    z-index:900;
    border-bottom:1px solid rgba(255,255,255,.06);
    gap:10px;
}
.na-hamburger{
    display:none;background:none;border:none;
    color:rgba(255,255,255,.7);font-size:18px;cursor:pointer;
    padding:6px 8px;border-radius:8px;transition:var(--ease);
}
.na-hamburger:hover{background:rgba(255,255,255,.1);color:#fff}
.na-brand img{height:30px;object-fit:contain;display:block}
.na-right{margin-left:auto;display:flex;align-items:center;gap:8px;position:relative}
.na-avatar-wrap{
    display:flex;align-items:center;gap:8px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    border-radius:99px;padding:4px 14px 4px 4px;
    cursor:pointer;transition:var(--ease);position:relative;
}
.na-avatar-wrap:hover{background:rgba(255,255,255,.14)}
.na-avatar-circ{
    width:30px;height:30px;border-radius:50%;
    background:linear-gradient(135deg,var(--indigo-600),var(--accent));
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:700;color:#fff;
}
.na-avatar-name{font-size:12.5px;font-weight:600;color:rgba(255,255,255,.88)}
.na-avatar-role{
    font-size:10px;
    background:rgba(99,102,241,.35);
    border:1px solid rgba(99,102,241,.5);
    border-radius:99px;padding:1px 8px;
    color:var(--indigo-400);font-weight:600;letter-spacing:.3px;
}
.na-drop{
    position:absolute;top:calc(100% + 8px);right:0;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--sh-md);
    min-width:200px;padding:6px;
    opacity:0;visibility:hidden;transform:translateY(-6px);
    transition:var(--ease);z-index:9999;
}
.na-avatar-wrap:hover .na-drop,.na-drop.open{opacity:1;visibility:visible;transform:translateY(0)}
.na-drop a{
    display:flex;align-items:center;gap:10px;
    padding:9px 12px;border-radius:8px;
    color:var(--text);text-decoration:none;
    font-size:13px;font-weight:500;transition:var(--ease);
}
.na-drop a:hover{background:var(--indigo-100)}
.na-drop a i{width:16px;text-align:center;color:var(--muted)}
.na-drop a.dd-danger{color:var(--crimson)}
.na-drop a.dd-danger i{color:var(--crimson)}
.na-drop hr{border-color:var(--border);margin:4px 0}

/* ── SIDEBAR ── */
.na-sb{
    position:fixed;left:0;top:0;bottom:0;
    width:var(--sb-w);
    background:var(--indigo-950);
    display:flex;flex-direction:column;align-items:center;
    padding:14px 0 10px;
    z-index:1000;
    border-right:1px solid rgba(255,255,255,.06);
    overflow:visible;
}
.sb-mark{
    width:36px;height:36px;
    background:linear-gradient(135deg,var(--indigo-600),var(--accent));
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:16px;flex-shrink:0;
}
.sb-mark i{color:#fff;font-size:16px}
.sb-sep{width:32px;height:1px;background:rgba(255,255,255,.1);margin:6px 0;flex-shrink:0}
.sb-spacer{flex:1}

/* nav item */
.sb-item{position:relative;width:100%;display:flex;justify-content:center;flex-shrink:0}
.sb-lnk{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    color:rgba(255,255,255,.4);text-decoration:none;
    transition:var(--ease);cursor:pointer;position:relative;
    border:none;background:none;margin:2px 0;
}
.sb-lnk:hover{background:rgba(255,255,255,.1);color:rgba(255,255,255,.85)}
.sb-lnk.active{
    background:rgba(99,102,241,.22);color:var(--indigo-400);
    box-shadow:inset 0 0 0 1px rgba(99,102,241,.4);
}
.sb-lnk i{font-size:17px}

/* badge */
.sb-bdg{
    position:absolute;top:5px;right:4px;
    background:var(--crimson);color:#fff;
    font-size:8.5px;font-weight:700;
    border-radius:99px;min-width:15px;height:15px;
    display:flex;align-items:center;justify-content:center;
    padding:0 3px;border:2px solid var(--indigo-950);line-height:1;
}

/* tooltip */
.sb-tip{
    position:absolute;left:calc(var(--sb-w) + 8px);top:50%;transform:translateY(-50%);
    background:#0a0a1a;color:#fff;
    font-size:11px;font-weight:600;letter-spacing:.2px;
    padding:5px 11px;border-radius:7px;
    white-space:nowrap;pointer-events:none;
    opacity:0;transition:opacity .15s ease;
    z-index:9999;box-shadow:0 4px 14px rgba(0,0,0,.45);
}
.sb-tip::before{
    content:'';position:absolute;right:100%;top:50%;transform:translateY(-50%);
    border:5px solid transparent;border-right-color:#0a0a1a;
}
.sb-item:hover .sb-tip{opacity:1}

/* ── OTHERS POPUP ── */
.sb-others-pop{
    position:fixed;left:var(--sb-w);bottom:10px;width:252px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--sh-md);
    padding:8px;
    opacity:0;visibility:hidden;transform:translateX(-10px);
    transition:opacity .18s ease,transform .18s ease,visibility .18s;
    z-index:9998;max-height:80vh;overflow-y:auto;
}
.sb-others-pop.open{opacity:1;visibility:visible;transform:translateX(0)}
.sop-title{
    font-size:9.5px;font-weight:700;letter-spacing:1.2px;
    text-transform:uppercase;color:var(--muted);
    padding:4px 8px 8px;
}
.sop-link{
    display:flex;align-items:center;gap:10px;
    padding:8px 10px;border-radius:8px;
    color:var(--text);text-decoration:none;
    font-size:12.5px;font-weight:500;transition:var(--ease);
}
.sop-link:hover{background:var(--indigo-100);color:var(--indigo-700)}
.sop-link i{
    width:22px;height:22px;border-radius:6px;
    background:var(--indigo-100);color:var(--indigo-600);
    display:flex;align-items:center;justify-content:center;
    font-size:11px;flex-shrink:0;
}
.sop-link.sop-active i{background:var(--indigo-600);color:#fff}

/* ── MAIN ── */
.main-content{
    margin-left:var(--sb-w);
    margin-top:var(--nb-h);
    padding:24px 28px;
    min-height:calc(100vh - var(--nb-h));
}
/* Task Manager: compact focused layout */
body.page-task-manager .main-content{
    padding:10px 14px !important;
}

/* ── OVERLAY ── */
.sb-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.48);z-index:999;
}
.sb-overlay.open{display:block}

/* ── RESPONSIVE ── */
@media(max-width:991px){
    .na-nav{left:0}
    .na-hamburger{display:flex;align-items:center}
    .na-sb{transform:translateX(-100%);transition:transform .25s cubic-bezier(.4,0,.2,1)}
    .na-sb.open{transform:translateX(0)}
    .main-content{margin-left:0;padding:16px}
    .sb-tip{display:none}
}
@media(max-width:575px){
    .na-avatar-name,.na-avatar-role{display:none}
    .na-avatar-wrap{padding:4px;border-radius:50%}
    .main-content{padding:12px}
}

/* ── BOOTSTRAP OVERRIDES ── */
.form-control,.form-select{
    border:1.5px solid #e5e7eb;border-radius:9px;
    font-family:inherit;font-size:13.5px;
}
.form-control:focus,.form-select:focus{
    border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,.15);
}
.btn{font-family:inherit;font-weight:600;font-size:13px;border-radius:9px}
.btn-purple{background:var(--indigo-600);color:#fff;border:none}
.btn-purple:hover{background:var(--indigo-700);color:#fff}
.btn-outline-purple{border:1.5px solid var(--indigo-600);color:var(--indigo-600);background:transparent}
.btn-outline-purple:hover{background:var(--indigo-600);color:#fff}
.text-purple{color:var(--indigo-700)!important}
.bg-purple{background:var(--indigo-900)!important}
.badge.bg-purple{background:var(--indigo-600)!important}
.table{font-size:13px}
.table thead th{
    background:var(--indigo-100);color:var(--indigo-700);
    font-weight:700;font-size:11px;letter-spacing:.5px;
    text-transform:uppercase;border:none;padding:12px 14px;
}
.table tbody td{padding:12px 14px;vertical-align:middle;border-color:var(--border)}
.table-hover tbody tr:hover{background:rgba(99,102,241,.04)}
.modal-header{background:var(--indigo-900);color:#fff;border-radius:var(--radius) var(--radius) 0 0}
.modal-content{border-radius:var(--radius);border:none;box-shadow:var(--sh-md)}
.alert{border-radius:var(--radius);border:none;font-size:13.5px}

/* legacy compat */
.table-card{
    background:var(--surface);border-radius:var(--radius);
    padding:20px;box-shadow:var(--sh-sm);border:1px solid var(--border);
}
.dashboard-card{
    background:var(--surface);border-radius:var(--radius);
    border:1px solid var(--border);box-shadow:var(--sh-sm);transition:var(--ease);
}
.dashboard-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md)}
.card-icon{
    width:42px;height:42px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:20px;color:#fff;
}
.icon-purple{background:linear-gradient(135deg,var(--indigo-600),var(--accent))}
.icon-success{background:linear-gradient(135deg,#10b981,#059669)}
.icon-warning{background:linear-gradient(135deg,#f59e0b,#d97706)}
.icon-danger{background:linear-gradient(135deg,#ef4444,#dc2626)}

/* story/attendance compat styles */
.story-container{display:flex;gap:12px;overflow-x:auto;padding:15px 0;scrollbar-width:thin}
.story-card{flex:0 0 auto;width:85px;text-align:center;cursor:pointer;transition:transform .2s}
.story-card:hover{transform:translateY(-3px)}
.story-avatar{width:75px;height:75px;border-radius:50%;padding:3px;margin:0 auto 6px;box-shadow:0 3px 8px rgba(0,0,0,.15);position:relative}
.story-rank-badge{position:absolute;top:-5px;right:-5px;width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,.2);z-index:10}
.story-rank-badge.top-3{background:linear-gradient(135deg,#fbbf24,#f59e0b)}
.story-rank-badge.top-10{background:linear-gradient(135deg,#10b981,#34d399)}
.story-avatar.present{background:linear-gradient(45deg,#10b981,#34d399)}
.story-avatar.absent{background:linear-gradient(45deg,#ef4444,#f87171)}
.story-avatar-inner{width:100%;height:100%;border-radius:50%;border:2px solid #fff;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f3f4f6}
.story-avatar-inner img{width:100%;height:100%;object-fit:cover}
.story-avatar-inner i{font-size:28px;color:#9ca3af}
.story-name{font-size:10px;font-weight:600;color:#374151;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:85px}
.story-time{font-size:9px;color:#6b7280}
.batch-section{background:#fff;border-radius:10px;padding:15px;margin-bottom:15px;box-shadow:0 1px 5px rgba(0,0,0,.05)}
.batch-header{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid #f3f4f6}
.batch-icon{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px}
.batch-icon.morning{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff}
.batch-icon.evening{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff}
.fees-story-wrapper{overflow:hidden;position:relative;width:100%;padding:10px 0}
.fees-story-track{display:flex;gap:14px;width:max-content;animation:feesScroll 35s linear infinite}
.fees-story-wrapper:hover .fees-story-track{animation-play-state:paused}
@keyframes feesScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.fees-story-card{flex:0 0 auto;width:95px;text-align:center;cursor:pointer;transition:transform .25s ease}
.fees-story-card:hover{transform:translateY(-4px) scale(1.05)}
.fees-avatar{width:75px;height:75px;border-radius:50%;padding:3px;margin:auto;background:linear-gradient(45deg,#10b981,#34d399);box-shadow:0 0 12px rgba(16,185,129,.6);position:relative}
.fees-avatar-inner{width:100%;height:100%;border-radius:50%;border:2px solid #fff;overflow:hidden;background:#f3f4f6}
.fees-avatar-inner img{width:100%;height:100%;object-fit:cover}
.fees-rank{position:absolute;top:-5px;right:-5px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;font-size:10px;font-weight:700;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff}
.fees-name{font-size:11px;font-weight:600;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fees-amount{font-size:11px;color:#10b981;font-weight:700}
.fees-date{font-size:9px;color:#6b7280}
.recent-admissions-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin}
.admission-story-card{display:flex;flex-direction:column;align-items:center;min-width:100px;text-align:center;transition:transform .3s ease}
.admission-story-card:hover{transform:translateY(-5px)}
.story-photo-wrapper{width:80px;height:80px;border-radius:50%;border:3px solid rgb(153,255,0);padding:3px;background:#fff;margin-bottom:8px;overflow:hidden;box-shadow:0 4px 10px rgba(124,58,237,.2)}
.story-photo-wrapper img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.story-placeholder{width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,var(--indigo-600),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px}
.story-photo-wrapper i{font-size:28px;color:#9ca3af}
.badge.bg-bronze{background:linear-gradient(135deg,#cd7f32,#b8722c)!important;color:#fff}
</style>
</head>
<body class="page-<?php echo htmlspecialchars($current_page); ?>">

<!-- ══ SIDEBAR ══ -->
<aside class="na-sb" id="naSidebar">
    <div class="sb-mark"><i class="fas fa-graduation-cap"></i></div>

    <?php if ($isAdm): ?>
    <div class="sb-item">
        <a href="index.php" class="sb-lnk <?= $current_page==='index'?'active':'' ?>"><i class="fas fa-house-chimney"></i></a>
        <span class="sb-tip">Home</span>
    </div>
    <div class="sb-item">
        <a href="students.php" class="sb-lnk <?= $current_page==='students'?'active':'' ?>"><i class="fas fa-user-plus"></i></a>
        <span class="sb-tip">Register</span>
    </div>
    <div class="sb-item">
        <a href="payments.php" class="sb-lnk <?= $current_page==='payments'?'active':'' ?>"><i class="fas fa-wallet"></i></a>
        <span class="sb-tip">Fees Collection</span>
    </div>
    <?php endif; ?>

    <div class="sb-item">
        <a href="inquiries.php" class="sb-lnk <?= in_array($current_page,['inquiries','inquiry_details'])?'active':'' ?>"><i class="fas fa-filter"></i></a>
        <span class="sb-tip">Lead Management</span>
    </div>

    <?php if ($isAdm): ?>
    <div class="sb-item">
        <a href="ranking.php" class="sb-lnk <?= $current_page==='ranking'?'active':'' ?>"><i class="fas fa-trophy"></i></a>
        <span class="sb-tip">Rank Report</span>
    </div>
    <?php endif; ?>

    <div class="sb-item">
        <a href="task_manager.php" class="sb-lnk <?= $current_page==='task_manager'?'active':'' ?>">
            <i class="fas fa-crosshairs"></i>
            <?php if ($pending_tasks > 0): ?><span class="sb-bdg"><?= $pending_tasks > 99 ? '99+' : $pending_tasks ?></span><?php endif; ?>
        </a>
        <span class="sb-tip">My Tasks<?= $pending_tasks>0?' ('.$pending_tasks.')':'' ?></span>
    </div>

    <?php if ($isAdm): ?>
    <!-- ── ATTENDANCE REPORT — promoted from "More" into main sidebar ── -->
    <div class="sb-item">
        <a href="attendance_report.php" class="sb-lnk <?= $current_page==='attendance_report'?'active':'' ?>">
            <i class="fas fa-calendar-check"></i>
        </a>
        <span class="sb-tip">Attendance Report</span>
    </div>
    <?php endif; ?>

    <?php if ($canManageEvents): ?>
    <!-- ── EVENTS — event & activity management ── -->
    <div class="sb-item">
        <a href="events.php" class="sb-lnk <?= in_array($current_page,['events','event_details'])?'active':'' ?>">
            <i class="fas fa-calendar-day"></i>
        </a>
        <span class="sb-tip">Events</span>
    </div>
    <?php endif; ?>

    <?php if ($isAdm): ?>

    <!-- ── STUDENT ANALYTICS — course expiry cards & forecast ── -->
    <div class="sb-item">
        <a href="student_analytics.php" class="sb-lnk <?= in_array($current_page,['student_analytics','students_course_expiry'])?'active':'' ?>"><i class="fas fa-chart-line"></i></a>
        <span class="sb-tip">Student Analytics</span>
    </div>

    <!-- ── PROJECT VERIFICATION — admin verify/reject student projects ── -->
    <div class="sb-item">
        <a href="project_verification.php" class="sb-lnk <?= $current_page==='project_verification'?'active':'' ?>">
            <i class="fas fa-clipboard-check"></i>
            <?php if ($pending_projects_count > 0): ?><span class="sb-bdg"><?= $pending_projects_count > 99 ? '99+' : $pending_projects_count ?></span><?php endif; ?>
        </a>
        <span class="sb-tip">Verify Projects<?= $pending_projects_count>0?' ('.$pending_projects_count.')':'' ?></span>
    </div>

    <!-- ── STUDENT TASK MANAGER — project progress tracker ── -->
    <div class="sb-item">
        <a href="student_task_manager.php" class="sb-lnk <?= in_array($current_page,['student_task_manager','student_project_analytics'])?'active':'' ?>">
            <i class="fas fa-clipboard-list"></i>
        </a>
        <span class="sb-tip">Student Task Manager</span>
    </div>
    <?php endif; ?>

    <?php if ($isSA): ?>
    <div class="sb-item">
        <a href="admins.php" class="sb-lnk <?= $current_page==='admins'?'active':'' ?>"><i class="fas fa-shield-halved"></i></a>
        <span class="sb-tip">Admins</span>
    </div>
    <?php endif; ?>

    <div class="sb-spacer"></div>
    <div class="sb-sep"></div>

    <div class="sb-item">
        <button class="sb-lnk" id="othersBtn" aria-label="More menus"><i class="fas fa-grip-vertical"></i></button>
        <span class="sb-tip">More</span>
    </div>
</aside>

<!-- Others Popup — attendance_report removed from here -->
<div class="sb-others-pop" id="othersPopup">
    <div class="sop-title">More Pages</div>
    <?php if ($isAdm): ?>
    <a href="courses.php" class="sop-link <?= $current_page==='courses'?'sop-active':'' ?>"><i class="fas fa-book"></i> Courses</a>
    <a href="categories.php" class="sop-link <?= $current_page==='categories'?'sop-active':'' ?>"><i class="fas fa-folder"></i> Categories</a>
    <a href="hold_students.php" class="sop-link <?= $current_page==='hold_students'?'sop-active':'' ?>"><i class="fas fa-pause-circle"></i> Hold Students</a>
    <a href="past_students.php" class="sop-link <?= in_array($current_page,['past_students','past_student_details'])?'sop-active':'' ?>"><i class="fas fa-history"></i> Past Students</a>
    <a href="student_groups.php" class="sop-link <?= in_array($current_page,['student_groups','group_details'])?'sop-active':'' ?>"><i class="fas fa-users"></i> Student Groups</a>
    <a href="student_analytics.php" class="sop-link <?= in_array($current_page,['student_analytics','students_course_expiry'])?'sop-active':'' ?>"><i class="fas fa-chart-line"></i> Student Analytics</a>
    <a href="students_course_expiry.php" class="sop-link <?= $current_page==='students_course_expiry'?'sop-active':'' ?>"><i class="fas fa-calendar-check"></i> Students Timeline</a>
    <a href="send_notification.php" class="sop-link <?= $current_page==='send_notification'?'sop-active':'' ?>"><i class="fas fa-paper-plane"></i> Send Notification</a>
    <a href="expenses.php" class="sop-link <?= $current_page==='expenses'?'sop-active':'' ?>"><i class="fas fa-receipt"></i> Expenses &amp; Profit</a>
    <a href="project_verification.php" class="sop-link <?= $current_page==='project_verification'?'sop-active':'' ?>"><i class="fas fa-clipboard-check"></i> Verify Projects<?= $pending_projects_count>0?' ('.$pending_projects_count.')':'' ?></a>
    <?php endif; ?>
    <?php if ($isSA): ?>
    <a href="reports.php" class="sop-link <?= $current_page==='reports'?'sop-active':'' ?>"><i class="fas fa-chart-bar"></i> Overall Report</a>
    <?php endif; ?>
    <?php if ($isAdm): ?>
    <div style="width:100%;height:1px;background:var(--border);margin:6px 0;"></div>
    <div class="sop-title" style="padding-top:4px;">Quiz System</div>
    <a href="create_test.php" class="sop-link <?= $current_page==='create_test'?'sop-active':'' ?>"><i class="fas fa-clipboard-list"></i> Create Test</a>
    <a href="assign_test.php" class="sop-link <?= $current_page==='assign_test'?'sop-active':'' ?>"><i class="fas fa-paper-plane"></i> Assign Test</a>
    <div style="width:100%;height:1px;background:var(--border);margin:6px 0;"></div>
    <div class="sop-title" style="padding-top:4px;">Typing System</div>
    <a href="typing_results.php" class="sop-link <?= $current_page==='typing_results'?'sop-active':'' ?>"><i class="fas fa-keyboard"></i> Typing Results</a>
    <?php endif; ?>
    <a href="profile.php" class="sop-link <?= $current_page==='profile'?'sop-active':'' ?>"><i class="fas fa-user"></i> Profile</a>
    <a href="settings.php" class="sop-link <?= $current_page==='settings'?'sop-active':'' ?>"><i class="fas fa-gear"></i> Settings</a>
</div>

<!-- Mobile backdrop -->
<div class="sb-overlay" id="sbOverlay"></div>

<!-- ══ NAVBAR ══ -->
<nav class="na-nav">
    <button class="na-hamburger" id="naHamburger" aria-label="Menu"><i class="fas fa-bars"></i></button>
    <div class="na-right">
        <div class="na-avatar-wrap">
            <div class="na-avatar-circ"><?php echo strtoupper(substr($admin['name'],0,1)); ?></div>
            <span class="na-avatar-name d-none d-md-inline"><?php echo htmlspecialchars($admin['name']); ?></span>
            <span class="na-avatar-role d-none d-lg-inline"><?php echo htmlspecialchars($admin['role']); ?></span>
            <div class="na-drop" id="naDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="settings.php"><i class="fas fa-gear"></i> Settings</a>
                <hr>
                <a href="logout.php" class="dd-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- MAIN wrapper opens here -->
<div class="main-content" id="mainContent">

<script>
(function(){
    var hamburger  = document.getElementById('naHamburger');
    var sidebar    = document.getElementById('naSidebar');
    var overlay    = document.getElementById('sbOverlay');
    var othersBtn  = document.getElementById('othersBtn');
    var othersPop  = document.getElementById('othersPopup');
    var naDropdown = document.getElementById('naDropdown');
    var avatarWrap = naDropdown ? naDropdown.closest('.na-avatar-wrap') : null;

    // Hamburger
    if(hamburger && sidebar && overlay){
        hamburger.addEventListener('click',function(){
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
        overlay.addEventListener('click',function(){
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            if(othersPop) othersPop.classList.remove('open');
        });
    }

  // Others popup (desktop hover + mobile click)
if (othersBtn && othersPop) {
    let hideTimer;

    // Desktop hover
    othersBtn.addEventListener('mouseenter', function () {
        clearTimeout(hideTimer);
        othersPop.classList.add('open');
    });

    othersBtn.addEventListener('mouseleave', function () {
        hideTimer = setTimeout(function () {
            if (!othersPop.matches(':hover')) {
                othersPop.classList.remove('open');
            }
        }, 150);
    });

    othersPop.addEventListener('mouseenter', function () {
        clearTimeout(hideTimer);
        othersPop.classList.add('open');
    });

    othersPop.addEventListener('mouseleave', function () {
        hideTimer = setTimeout(function () {
            othersPop.classList.remove('open');
        }, 150);
    });

    // ✅ MOBILE CLICK SUPPORT
    othersBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        othersPop.classList.toggle('open');
    });
}

    // Avatar dropdown click (mobile fallback)
    if(avatarWrap && naDropdown){
        avatarWrap.addEventListener('click',function(e){
            e.stopPropagation();
            naDropdown.classList.toggle('open');
        });
    }

    // Close on outside click
    document.addEventListener('click',function(){
        if(othersPop) othersPop.classList.remove('open');
        if(naDropdown) naDropdown.classList.remove('open');
    });
})();
</script>