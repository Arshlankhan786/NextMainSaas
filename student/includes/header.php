<?php
ob_start();
/**
 * includes/header.php
 * Unified layout header for all student portal pages.
 * Loads DB connection, authenticates the student, then opens
 * the app-shell so page content can be injected directly.
 */
require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';
requireStudentLogin();

// India timezone for all IST calculations
date_default_timezone_set('Asia/Kolkata');

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$student      = getCurrentStudent();
$sid          = (int)$student['id'];

/* ── Safe query helpers (available to every page) ─────────── */
if (!function_exists('safeRow')) {
    function safeRow($conn, $sql) {
        $r = $conn->query($sql);
        return ($r && $r !== true) ? $r->fetch_assoc() : null;
    }
}
if (!function_exists('safeVal')) {
    function safeVal($conn, $sql, $col, $default = 0) {
        $row = safeRow($conn, $sql);
        return isset($row[$col]) ? $row[$col] : $default;
    }
}

/* ── Student details ──────────────────────────────────────── */
$student_data = safeRow($conn, "SELECT * FROM students WHERE id = $sid");
$first_name   = explode(' ', $student_data['full_name'] ?? $student['name'] ?? 'Student')[0];

/* ── Unread notification count ────────────────────────────── */
$unread_count = (int)safeVal($conn,
    "SELECT COUNT(*) AS cnt FROM student_notifications
     WHERE student_id = $sid AND is_read = 0", 'cnt', 0);

/* ── Today's check-in state ───────────────────────────────── */
$today = date('Y-m-d');
$already_checked_in = (int)safeVal($conn,
    "SELECT COUNT(*) AS cnt FROM student_attendance
     WHERE student_id = $sid AND attendance_date = '$today'", 'cnt', 0) > 0;

/* ── Page meta ────────────────────────────────────────────── */
$page_titles = [
    'dashboard'       => ['Dashboard',       'Overview of your portal'],
    'attendance'      => ['Attendance',       'Track your daily check-in & out'],
    'course_progress' => ['Course Progress',  'Follow your learning journey'],
    'projects'        => ['My Projects',      'Showcase your work'],
    'task_manager'    => ['Task Manager',      'Track your daily project progress'],
    'payments'        => ['Payments',         'Payment history & fee details'],
    'receipts'        => ['Receipts',         'Download your payment receipts'],
    'notifications'   => ['Notifications',    'Your messages & alerts'],
    'profile'         => ['My Profile',       'Manage your account settings'],
    'my_tests'              => ['My Tests',              'View assigned quizzes and results'],
    'quiz_result'           => ['Quiz Results',          'Review your test performance'],
    'typing_competition'    => ['Typing Competition',    'Test your typing speed & accuracy'],
    'activities'            => ['Activities',             'Academy events & your participation'],
];
$pt       = $page_titles[$current_page] ?? ['Student Portal', ''];
$title    = $pt[0];
$subtitle = $pt[1];

/* ── Greeting ─────────────────────────────────────────────── */
$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title); ?> — Student Portal</title>
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">

  <!-- ── Poppins font ── -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- ── Font Awesome ── -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- ── Bootstrap JS only (no BS CSS — we use our own theme) ── -->

  <!-- ── SINGLE unified dark theme — loaded ONCE here ── -->
  <link rel="stylesheet" href="assets/css/student-theme.css">
</head>
<body>

<!-- ── Mobile sidebar overlay ── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">

  <a href="dashboard.php" class="sidebar-logo" title="Student Portal">
    <i class="fas fa-graduation-cap"></i>
  </a>

  <a href="dashboard.php"
     class="sidebar-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>"
     title="Dashboard">
    <i class="fas fa-th-large"></i>
    <span class="sidebar-item-label">Dashboard</span>
    <span class="sidebar-tooltip">Dashboard</span>
  </a>

  <a href="attendance.php"
     class="sidebar-item <?php echo $current_page === 'attendance' ? 'active' : ''; ?>"
     title="Attendance">
    <i class="fas fa-calendar-check"></i>
    <?php if (!$already_checked_in): ?>
      <span class="sidebar-badge"></span>
    <?php endif; ?>
    <span class="sidebar-item-label">Attendance</span>
    <span class="sidebar-tooltip">Attendance</span>
  </a>

  <a href="activities.php"
     class="sidebar-item <?php echo $current_page === 'activities' ? 'active' : ''; ?>"
     title="Activities">
    <i class="fas fa-calendar-day"></i>
    <span class="sidebar-item-label">Activities</span>
    <span class="sidebar-tooltip">Activities</span>
  </a>

  <a href="course_progress.php"
     class="sidebar-item <?php echo $current_page === 'course_progress' ? 'active' : ''; ?>"
     title="Course Progress">
    <i class="fas fa-chart-line"></i>
    <span class="sidebar-item-label">Course Progress</span>
    <span class="sidebar-tooltip">Course Progress</span>
  </a>

  <a href="projects.php"
     class="sidebar-item <?php echo $current_page === 'projects' ? 'active' : ''; ?>"
     title="My Projects">
    <i class="fas fa-layer-group"></i>
    <span class="sidebar-item-label">Projects</span>
    <span class="sidebar-tooltip">Projects</span>
  </a>

  <a href="task_manager.php"
     class="sidebar-item <?php echo $current_page === 'task_manager' ? 'active' : ''; ?>"
     title="Task Manager">
    <i class="fas fa-clipboard-list"></i>
    <span class="sidebar-item-label">Task Manager</span>
    <span class="sidebar-tooltip">Task Manager</span>
  </a>

  <a href="my_tests.php"
     class="sidebar-item <?php echo in_array($current_page, ['my_tests','take_quiz','quiz_result']) ? 'active' : ''; ?>"
     title="My Tests">
    <i class="fas fa-file-alt"></i>
    <span class="sidebar-item-label">My Tests</span>
    <span class="sidebar-tooltip">My Tests</span>
  </a>

  <a href="typing_competition.php"
     class="sidebar-item <?php echo $current_page === 'typing_competition' ? 'active' : ''; ?>"
     title="Typing Competition">
    <i class="fas fa-keyboard"></i>
    <span class="sidebar-item-label">Typing Test</span>
    <span class="sidebar-tooltip">Typing Competition</span>
  </a>

  <a href="payments.php"
     class="sidebar-item <?php echo $current_page === 'payments' ? 'active' : ''; ?>"
     title="Payments">
    <i class="fas fa-indian-rupee-sign"></i>
    <span class="sidebar-item-label">Payments</span>
    <span class="sidebar-tooltip">Payments</span>
  </a>

  <a href="receipts.php"
     class="sidebar-item <?php echo $current_page === 'receipts' ? 'active' : ''; ?>"
     title="Receipts">
    <i class="fas fa-receipt"></i>
    <span class="sidebar-item-label">Receipts</span>
    <span class="sidebar-tooltip">Receipts</span>
  </a>

  <a href="notifications.php"
     class="sidebar-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>"
     title="Notifications">
    <i class="fas fa-bell"></i>
    <?php if ($unread_count > 0): ?>
      <span class="sidebar-badge"></span>
    <?php endif; ?>
    <span class="sidebar-item-label">
      Notifications<?php echo $unread_count > 0 ? " ($unread_count)" : ''; ?>
    </span>
    <span class="sidebar-tooltip">
      Notifications<?php echo $unread_count > 0 ? " ($unread_count)" : ''; ?>
    </span>
  </a>

  <a href="profile.php"
     class="sidebar-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>"
     title="My Profile">
    <i class="fas fa-user-circle"></i>
    <span class="sidebar-item-label">My Profile</span>
    <span class="sidebar-tooltip">My Profile</span>
  </a>

  <div class="sidebar-spacer"></div>

  <a href="logout.php" class="sidebar-item" title="Logout">
    <i class="fas fa-sign-out-alt"></i>
    <span class="sidebar-item-label">Logout</span>
    <span class="sidebar-tooltip">Logout</span>
  </a>

</nav><!-- /sidebar -->

<!-- ════════════════════════════════════════
     APP SHELL  (wraps top-bar + page content)
════════════════════════════════════════ -->
<div class="app-shell" id="appShell">

  <!-- ── TOP BAR ── -->
  <div class="top-bar">
    <div class="top-bar-left">
      <!-- Mobile hamburger -->
      <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="openSidebar()">
        <i class="fas fa-bars"></i>
      </button>

      <!-- Page title -->
      <div class="page-title-bar">
        <h5><?php echo htmlspecialchars($title); ?></h5>
        <?php if ($subtitle): ?>
          <small><?php echo htmlspecialchars($subtitle); ?></small>
        <?php endif; ?>
      </div>
    </div>

    <div class="top-bar-right">
      <!-- Greeting + check-in CTA -->
      <span style="font-size:12px;color:var(--text-muted);display:none;" class="greeting-inline">
        <?php echo $greeting . ', ' . htmlspecialchars($first_name); ?>
      </span>

      <?php if ($already_checked_in): ?>
        <span class="topbar-btn topbar-btn-ghost" style="color:var(--green);border-color:rgba(16,185,129,0.35);cursor:default;">
          <i class="fas fa-check-circle"></i> Checked In
        </span>
      <?php else: ?>
        <a href="attendance.php" class="topbar-btn topbar-btn-success">
          <i class="fas fa-sign-in-alt"></i> Check In
        </a>
      <?php endif; ?>

      <!-- Notifications bell -->
      <a href="notifications.php" class="notif-btn" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </a>
    </div>
  </div><!-- /top-bar -->

  <!-- ── PAGE CONTENT starts here — pages inject their HTML below ── -->
  <div class="page-content">