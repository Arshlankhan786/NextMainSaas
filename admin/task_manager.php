<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$current_page = 'task_manager';
$page_class = 'task-manager-page';

$admin = getCurrentAdmin();
$admin_id = $_SESSION['admin_id'];
$raw_role = strtolower(trim($_SESSION['admin_role'] ?? ''));
switch ($raw_role) {
    case 'super admin':
        $admin_role = 'super admin';
        break;
    case 'administrator':
        $admin_role = 'administrator';
        break;
    case 'admin':
    default:
        $admin_role = 'admin';
        break;
}

$all_admins = [];
if ($admin_role === 'super admin') {
    $result = $conn->query("
        SELECT id, username, full_name, role 
        FROM admins 
        WHERE role IN ('Admin', 'Administrator')
        ORDER BY full_name
    ");
    while ($row = $result->fetch_assoc()) {
        $all_admins[] = $row;
    }
}

include 'includes/header.php';
$is_super_admin = ($admin_role === 'super admin');
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<style>
/* =============================================
   FOCUS MODE — DARK DASHBOARD
   ============================================= */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --purple-primary:      #2c184f;
    --purple-dark:         #260e4c;
    --purple-light:        #8e73e0;
    --purple-lighter:      #d6b8f5;
    --purple-extra-light:  #f3e8ff;
    --bg-deep:    #1a0d38;
    --bg-card:    #220f43;
    --bg-input:   #2c184f;
    --bg-hover:   #341a5e;
    --border:     rgba(142,115,224,0.15);
    --border-hi:  rgba(142,115,224,0.5);
    --accent:     #8e73e0;
    --accent2:    #b994f0;
    --green:      #22c97a;
    --red:        #ff5263;
    --amber:      #ffb347;
    --text-1:     #f0e8ff;
    --text-2:     #b09dd4;
    --text-3:     #6b5a8a;
    --glow:       0 0 28px rgba(142,115,224,0.2);
    --r-card:     14px;
    --r-pill:     50px;
    --shadow:     0 4px 28px rgba(0,0,0,0.5);
    --font-head:  'Rajdhani', sans-serif;
    --font-body:  'DM Sans', sans-serif;
    --priority-high:   #ff4757;
    --priority-medium: #ffb347;
    --priority-low:    #22c97a;
}

body.page-task_manager .main-content {
    padding: 0 !important;
    background: var(--bg-deep) !important;
}
body.task-manager-page .container,
body.task-manager-page .container-fluid {
    padding-left: 0 !important;
    padding-right: 0 !important;
}
.task-manager-page .main-content {
    padding: 0 !important;
    margin: 0 !important;
    background: var(--bg-deep) !important;
}
.task-manager-page .container,
.task-manager-page .container-fluid {
    padding: 0 !important;
    margin: 0 auto !important;
}
.focus-wrapper {
    margin-top: 0 !important;
    padding-top: 20px !important;
    font-family: var(--font-body);
    background: radial-gradient(ellipse at top left, #2c1060 0%, #1a0d38 40%, #120830 100%);
    min-height: 100vh;
    padding: 0 0 80px;
}
.focus-wrapper::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
    opacity: 0.4;
}
.fm-container {
    position: relative;
    z-index: 1;
    max-width: 1120px;
    margin: 0 auto;
    padding: 28px 20px 20px;
}

/* =============================================
   TOP CONTROL BAR
   ============================================= */
.fm-topbar {
    display: flex;
    flex-direction: column;
    gap: 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-top: 2px solid rgba(142,115,224,0.35);
    border-radius: var(--r-card);
    padding: 18px 24px;
    margin-bottom: 18px;
    box-shadow: var(--shadow);
}
.fm-topbar-row1 {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 20px;
    align-items: center;
    width: 100%;
}
.fm-create-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.fm-create-row1 {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    min-width: 0;
}
.fm-repeat-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0;
    overflow: hidden;
}
.fm-task-input {
    flex: 1;
    min-width: 0;
    width: 0;
    height: 44px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-1);
    font-family: var(--font-body);
    font-size: 14px;
    padding: 0 20px;
    outline: none;
    transition: border-color .25s, box-shadow .25s;
}
.fm-task-input::placeholder { color: var(--text-3); }
.fm-task-input:focus {
    border-color: var(--purple-light);
    box-shadow: 0 0 0 3px rgba(142,115,224,0.15);
}

/* =============================================
   REPEAT CONTROLS
   ============================================= */
.fm-repeat-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--text-2);
    font-size: 13px;
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
    padding: 2px 0;
}
.fm-repeat-label input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
}
.fm-repeat-extra {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height .32s ease, opacity .25s ease, padding .3s ease;
    padding-top: 0;
}
.fm-repeat-extra.show {
    max-height: 80px;
    opacity: 1;
    padding-top: 8px;
}
.fm-select {
    height: 44px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-2);
    font-family: var(--font-body);
    font-size: 13px;
    padding: 0 16px;
    outline: none;
    cursor: pointer;
    transition: border-color .25s;
}
.fm-select:focus { border-color: var(--purple-light); }
.fm-select option { background: var(--bg-card); color: var(--text-1); }
.fm-btn-create {
    height: 44px;
    padding: 0 26px;
    background: linear-gradient(135deg, #8e73e0 0%, #b994f0 100%);
    color: #fff;
    border: none;
    border-radius: var(--r-pill);
    font-family: var(--font-head);
    font-size: 15px;
    font-weight: 600;
    letter-spacing: .5px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    white-space: nowrap;
    flex-shrink: 0;
}
.fm-btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(142,115,224,0.45);
}
.fm-btn-create:active { transform: translateY(0); }

/* =============================================
   PRIORITY BUTTONS
   ============================================= */
.fm-priority-group {
    display: flex;
    align-items: center;
    gap: 0;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    padding: 3px;
    overflow: hidden;
}
.fm-priority-btn {
    height: 36px;
    padding: 0 14px;
    border: none;
    border-radius: var(--r-pill);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: all .25s;
    background: transparent;
    color: var(--text-3);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 5px;
}
.fm-priority-btn .prio-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.fm-priority-btn[data-priority="high"] .prio-dot  { background: var(--priority-high); }
.fm-priority-btn[data-priority="medium"] .prio-dot { background: var(--priority-medium); }
.fm-priority-btn[data-priority="low"] .prio-dot    { background: var(--priority-low); }
.fm-priority-btn[data-priority="high"].active {
    background: rgba(255,71,87,0.18);
    color: var(--priority-high);
    box-shadow: 0 0 14px rgba(255,71,87,0.35);
}
.fm-priority-btn[data-priority="medium"].active {
    background: rgba(255,179,71,0.18);
    color: var(--priority-medium);
    box-shadow: 0 0 14px rgba(255,179,71,0.3);
}
.fm-priority-btn[data-priority="low"].active {
    background: rgba(34,201,122,0.18);
    color: var(--priority-low);
    box-shadow: 0 0 14px rgba(34,201,122,0.3);
}

/* =============================================
   ASSIGN SECTION (Super Admin only)
   ============================================= */
.fm-assign-section {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding: 14px 18px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
}
.fm-assign-label {
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .5px;
    color: var(--text-2);
    text-transform: uppercase;
    white-space: nowrap;
}
.fm-assign-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.fm-assign-pill {
    height: 36px;
    padding: 0 16px;
    border-radius: var(--r-pill);
    border: 1.5px solid var(--border);
    background: var(--bg-input);
    color: var(--text-2);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: all .25s;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 6px;
}
.fm-assign-pill .assign-avatar {
    width: 20px; height: 20px;
    border-radius: 50%;
    background: var(--purple-primary);
    color: var(--purple-lighter);
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.fm-assign-pill:hover {
    border-color: var(--purple-light);
    color: var(--purple-lighter);
    background: rgba(142,115,224,0.08);
}
.fm-assign-pill.active {
    border-color: var(--purple-light);
    color: #fff;
    background: linear-gradient(135deg, rgba(142,115,224,0.3) 0%, rgba(185,148,240,0.3) 100%);
    box-shadow: 0 0 0 1px rgba(142,115,224,0.5), 0 0 18px rgba(142,115,224,0.25);
}
.fm-assign-pill.active .assign-avatar {
    background: var(--purple-light);
    color: #fff;
}

/* =============================================
   RIGHT PANEL — STATS + CLOCK
   ============================================= */
.fm-right-panel {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    min-width: 180px;
}
.fm-stats { display: flex; gap: 14px; }
.fm-stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-head);
    font-size: 16px;
    font-weight: 600;
    letter-spacing: .4px;
}
.fm-stat .dot { width: 8px; height: 8px; border-radius: 50%; }
.fm-stat.pending { color: var(--red); }
.fm-stat.pending .dot { background: var(--red); box-shadow: 0 0 6px var(--red); }
.fm-stat.done    { color: var(--green); }
.fm-stat.done .dot { background: var(--green); box-shadow: 0 0 6px var(--green); }
.fm-clock-block { text-align: right; }
.fm-date-str { font-size: 12px; color: var(--text-2); letter-spacing: .5px; margin-bottom: 2px; }
.fm-clock {
    font-family: var(--font-head);
    font-size: 26px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: 3px;
    line-height: 1;
}
.fm-clock span { color: var(--purple-lighter); }

/* =============================================
   USER FILTER PILLS
   ============================================= */
.fm-users-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 22px;
    padding: 0 2px;
}
.fm-user-pill {
    padding: 7px 20px;
    border-radius: var(--r-pill);
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    color: var(--text-2);
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .5px;
    cursor: pointer;
    transition: all .25s;
    text-transform: uppercase;
}
.fm-user-pill:hover {
    border-color: var(--purple-light);
    color: var(--purple-lighter);
    background: rgba(142,115,224,0.08);
}
.fm-user-pill.active {
    border-color: var(--purple-light);
    color: #fff;
    background: linear-gradient(135deg, rgba(142,115,224,0.25) 0%, rgba(185,148,240,0.25) 100%);
    box-shadow: 0 0 0 1px rgba(142,115,224,0.4), var(--glow);
}

/* =============================================
   TASK LIST HEADER
   ============================================= */
.fm-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding: 0 4px;
}
.fm-list-title {
    font-family: var(--font-head);
    font-size: 20px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: .5px;
}
.fm-toggle-done {
    height: 36px;
    padding: 0 18px;
    background: transparent;
    border: 1.5px solid var(--border);
    border-radius: var(--r-pill);
    color: var(--text-2);
    font-family: var(--font-body);
    font-size: 13px;
    cursor: pointer;
    transition: all .25s;
    display: flex; align-items: center; gap: 7px;
}
.fm-toggle-done:hover { border-color: var(--purple-light); color: var(--purple-lighter); }
.fm-toggle-done.active {
    border-color: var(--green);
    color: var(--green);
    background: rgba(34,201,122,0.07);
}

/* =============================================
   TASK CARDS
   ============================================= */
.fm-task-list { display: flex; flex-direction: column; gap: 10px; }
.fm-task-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    padding: 16px 20px;
    transition: transform .2s, border-color .2s, box-shadow .2s;
    animation: slideIn .3s ease both;
    position: relative;
    overflow: hidden;
}
.fm-task-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #8e73e0, #d6b8f5);
    border-radius: 3px 0 0 3px;
    transition: width .2s;
}
.fm-task-card.priority-high::before {
    background: linear-gradient(180deg, #ff4757, #ff6b81);
    width: 4px;
    box-shadow: 2px 0 12px rgba(255,71,87,0.5);
}
.fm-task-card.priority-medium::before {
    background: linear-gradient(180deg, #ffb347, #ffd080);
    width: 3px;
}
.fm-task-card.priority-low::before {
    background: linear-gradient(180deg, #22c97a, #6bffb8);
    width: 3px;
}
.fm-task-card:hover {
    transform: translateY(-2px);
    border-color: rgba(142,115,224,0.45);
    box-shadow: var(--shadow), var(--glow);
}
.fm-task-card:hover::before { width: 5px; }
.fm-task-card.priority-high:hover { box-shadow: var(--shadow), 0 0 24px rgba(255,71,87,0.2); }
.fm-task-card.status-completed { opacity: 0.65; }
.fm-task-card.status-completed::before { background: var(--green); }
.fm-task-card.status-completed .fm-task-title { text-decoration: line-through; color: var(--text-3); }
.fm-task-card.status-incomplete::before { background: var(--red); }
.fm-task-num {
    min-width: 32px; height: 32px;
    border-radius: 50%;
    border: 1.5px solid var(--border);
    background: var(--bg-input);
    color: var(--text-2);
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}
.fm-task-info { flex: 1; min-width: 0; }
.fm-task-title {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}
.fm-task-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.fm-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: var(--r-pill);
    font-size: 11px;
    font-weight: 500;
    letter-spacing: .3px;
}
.fm-badge-cat {
    background: rgba(142,115,224,0.15);
    color: var(--purple-lighter);
    border: 1px solid rgba(142,115,224,0.25);
}
.fm-badge-date { background: rgba(255,255,255,0.05); color: var(--text-2); }
.fm-badge-repeat {
    background: rgba(214,184,245,0.12);
    color: #d6b8f5;
    border: 1px solid rgba(214,184,245,0.25);
}
.fm-badge-priority {
    font-family: var(--font-head);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    padding: 2px 9px;
    border-radius: var(--r-pill);
}
.fm-badge-priority.prio-high {
    background: rgba(255,71,87,0.15);
    color: var(--priority-high);
    border: 1px solid rgba(255,71,87,0.3);
}
.fm-badge-priority.prio-medium {
    background: rgba(255,179,71,0.15);
    color: var(--priority-medium);
    border: 1px solid rgba(255,179,71,0.3);
}
.fm-badge-priority.prio-low {
    background: rgba(34,201,122,0.15);
    color: var(--priority-low);
    border: 1px solid rgba(34,201,122,0.3);
}
.fm-task-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    align-self: flex-start;
    padding-top: 2px;
}
.fm-status-badge {
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .4px;
    padding: 4px 14px;
    border-radius: var(--r-pill);
}
.fm-status-badge.pending {
    color: var(--amber);
    background: rgba(255,179,71,0.1);
    border: 1px solid rgba(255,179,71,0.25);
}
.fm-status-badge.done {
    color: var(--green);
    background: rgba(34,201,122,0.1);
    border: 1px solid rgba(34,201,122,0.25);
}
.fm-status-badge.incomplete {
    color: var(--red);
    background: rgba(255,82,99,0.1);
    border: 1px solid rgba(255,82,99,0.25);
}
.fm-btn-done {
    height: 36px;
    padding: 0 18px;
    background: linear-gradient(135deg, #8e73e0, #b994f0);
    color: #fff;
    border: none;
    border-radius: var(--r-pill);
    font-family: var(--font-head);
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .3px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, opacity .2s;
    white-space: nowrap;
}
.fm-btn-done:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(142,115,224,0.45); }
.fm-btn-delete {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-3);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    transition: all .2s;
    flex-shrink: 0;
}
.fm-btn-delete:hover { border-color: var(--red); color: var(--red); background: rgba(255,82,99,0.08); }

/* =============================================
   PIN BUTTON & PINNED STATE
   ============================================= */
.fm-btn-pin {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-3);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    transition: all .2s;
    flex-shrink: 0;
}
.fm-btn-pin:hover { border-color: #ffd700; color: #ffd700; background: rgba(255,215,0,0.08); }
.fm-btn-pin.pinned {
    border-color: rgba(255,215,0,0.5);
    color: #ffd700;
    background: rgba(255,215,0,0.1);
    box-shadow: 0 0 10px rgba(255,215,0,0.2);
}
.fm-task-card.task-pinned {
    background: linear-gradient(135deg, rgba(255,215,0,0.04) 0%, var(--bg-card) 60%);
    border-color: rgba(255,215,0,0.22);
    box-shadow: 0 0 18px rgba(255,215,0,0.09), var(--shadow);
}
.fm-task-card.task-pinned::before {
    background: linear-gradient(180deg, #ffd700, #ffaa00) !important;
    width: 4px !important;
    box-shadow: 2px 0 10px rgba(255,215,0,0.4);
}
.fm-badge-pin {
    background: rgba(255,215,0,0.12);
    color: #ffd700;
    border: 1px solid rgba(255,215,0,0.3);
    font-family: var(--font-head);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: var(--r-pill);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* =============================================
   MULTI-SELECT SYSTEM
   ============================================= */
.fm-task-checkbox {
    width: 17px; height: 17px;
    border-radius: 4px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
    display: none;
    opacity: 0;
    transition: opacity .2s;
    margin-top: 6px;
}
.fm-multiselect-active .fm-task-checkbox { display: block; opacity: 1; }
.fm-multiselect-active .fm-task-card { padding-left: 14px; }
.fm-bulk-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: max-height .3s ease, opacity .25s ease, margin .3s ease;
    margin-top: 0;
}
.fm-bulk-bar.show { max-height: 60px; opacity: 1; margin-top: 10px; }
.fm-bulk-select-btn {
    height: 34px;
    padding: 0 16px;
    border-radius: var(--r-pill);
    border: 1.5px solid var(--border);
    background: transparent;
    color: var(--text-2);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: all .22s;
    display: flex; align-items: center; gap: 6px;
    white-space: nowrap;
}
.fm-bulk-select-btn:hover { border-color: var(--purple-light); color: var(--purple-lighter); }
.fm-bulk-select-btn.active {
    border-color: var(--purple-light);
    color: #fff;
    background: rgba(142,115,224,0.2);
}
.fm-bulk-action-btn {
    height: 34px;
    padding: 0 16px;
    border-radius: var(--r-pill);
    border: 1.5px solid rgba(255,215,0,0.35);
    background: rgba(255,215,0,0.08);
    color: #ffd700;
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: all .22s;
    display: flex; align-items: center; gap: 6px;
    white-space: nowrap;
}
.fm-bulk-action-btn:hover { background: rgba(255,215,0,0.15); box-shadow: 0 0 12px rgba(255,215,0,0.2); }
.fm-bulk-action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.fm-bulk-count {
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-3);
    letter-spacing: .3px;
    margin-left: 2px;
}

/* =============================================
   EMPTY STATE + SHIMMER
   ============================================= */
.fm-empty { text-align: center; padding: 60px 20px; color: var(--text-3); }
.fm-empty i { font-size: 48px; margin-bottom: 14px; display: block; opacity: .4; }
.fm-empty p { font-size: 15px; color: var(--text-3); }
.fm-shimmer {
    background: linear-gradient(90deg, var(--bg-card) 25%, var(--bg-hover) 50%, var(--bg-card) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: var(--r-card);
    height: 68px;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
@keyframes slideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* =============================================
   MISC INPUT HELPERS
   ============================================= */
.fm-mini-input {
    width: 70px;
    height: 36px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    color: var(--text-1);
    font-family: var(--font-body);
    font-size: 13px;
    padding: 0 10px;
    outline: none;
}
.fm-mini-input:focus { border-color: var(--purple-light); }

/* =============================================
   MODAL OVERRIDES
   ============================================= */
.fm-modal .modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    color: var(--text-1);
    font-family: var(--font-body);
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
}
.fm-modal .modal-header {
    background: linear-gradient(135deg, rgba(44,24,79,0.9) 0%, rgba(142,115,224,0.15) 100%);
    border-bottom: 1px solid var(--border);
    border-radius: 16px 16px 0 0;
    padding: 18px 24px;
}
.fm-modal .modal-title {
    font-family: var(--font-head);
    font-size: 20px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: .5px;
}
.fm-modal .btn-close { filter: invert(1) brightness(0.6); }
.fm-modal .modal-body { padding: 24px; }
.fm-modal .modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }
.fm-modal label.form-label {
    color: var(--text-2);
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}
.fm-modal .form-control,
.fm-modal .form-select {
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    color: var(--text-1);
    font-family: var(--font-body);
    padding: 10px 14px;
    font-size: 14px;
    outline: none;
    transition: border-color .25s;
}
.fm-modal .form-control:focus,
.fm-modal .form-select:focus {
    border-color: var(--purple-light);
    background: var(--bg-input);
    box-shadow: 0 0 0 3px rgba(142,115,224,0.18);
    color: var(--text-1);
}
.fm-modal .form-control::placeholder { color: var(--text-3); }
.fm-modal .form-check-input { accent-color: var(--accent); }
.fm-modal .form-check-label { color: var(--text-2); font-size: 13px; }
.fm-modal select option { background: #131d2b; color: var(--text-1); }

/* =============================================
   ANALYTICS SECTION
   ============================================= */
.fm-analytics {
    margin-top: 36px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-top: 2px solid rgba(142,115,224,0.35);
    border-radius: var(--r-card);
    padding: 24px;
    box-shadow: var(--shadow);
}
.fm-analytics-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.fm-analytics-title {
    font-family: var(--font-head);
    font-size: 22px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: .5px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fm-analytics-title i { color: var(--purple-light); }
.fm-analytics-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}
.fm-astat-card {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    padding: 16px 18px;
    text-align: center;
    transition: transform .2s, box-shadow .2s;
}
.fm-astat-card:hover { transform: translateY(-2px); box-shadow: var(--glow); }
.fm-astat-value {
    font-family: var(--font-head);
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 4px;
}
.fm-astat-label { font-size: 11px; font-weight: 500; letter-spacing: .5px; text-transform: uppercase; color: var(--text-3); }
.fm-astat-card.total   .fm-astat-value { color: var(--purple-lighter); }
.fm-astat-card.done    .fm-astat-value { color: var(--green); }
.fm-astat-card.pending .fm-astat-value { color: var(--amber); }
.fm-astat-card.high    .fm-astat-value { color: var(--priority-high); }
.fm-astat-card.medium  .fm-astat-value { color: var(--priority-medium); }
.fm-astat-card.low     .fm-astat-value { color: var(--priority-low); }
.fm-astat-card.total   { border-top: 2px solid rgba(214,184,245,0.4); }
.fm-astat-card.done    { border-top: 2px solid rgba(34,201,122,0.4); }
.fm-astat-card.pending { border-top: 2px solid rgba(255,179,71,0.4); }
.fm-astat-card.high    { border-top: 2px solid rgba(255,71,87,0.4); }
.fm-astat-card.medium  { border-top: 2px solid rgba(255,179,71,0.4); }
.fm-astat-card.low     { border-top: 2px solid rgba(34,201,122,0.4); }
.fm-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.fm-chart-box {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    padding: 20px;
}
.fm-chart-box h4 {
    font-family: var(--font-head);
    font-size: 15px;
    font-weight: 600;
    color: var(--text-2);
    letter-spacing: .5px;
    margin-bottom: 16px;
    text-transform: uppercase;
}
.fm-chart-box canvas { max-height: 220px; }

/* ANALYTICS DATE FILTER BAR */
.fm-an-filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 14px 18px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    margin-bottom: 22px;
}
.fm-an-filter-label {
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .6px;
    color: var(--text-3);
    text-transform: uppercase;
    white-space: nowrap;
    margin-right: 2px;
}
.fm-an-filter-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.fm-an-filter-btn {
    height: 34px;
    padding: 0 16px;
    border-radius: var(--r-pill);
    border: 1.5px solid var(--border);
    background: transparent;
    color: var(--text-2);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: all .22s;
    white-space: nowrap;
}
.fm-an-filter-btn:hover { border-color: var(--purple-light); color: var(--purple-lighter); background: rgba(142,115,224,0.08); }
.fm-an-filter-btn.active {
    border-color: var(--purple-light);
    color: #fff;
    background: linear-gradient(135deg, rgba(142,115,224,0.3) 0%, rgba(185,148,240,0.3) 100%);
    box-shadow: 0 0 0 1px rgba(142,115,224,0.4), 0 0 14px rgba(142,115,224,0.2);
}
.fm-an-custom-range { display: none; align-items: center; gap: 8px; flex-wrap: wrap; }
.fm-an-custom-range.show { display: flex; }
.fm-an-date-input {
    height: 34px;
    padding: 0 12px;
    background: var(--bg-card);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    color: var(--text-1);
    font-family: var(--font-body);
    font-size: 13px;
    outline: none;
    cursor: pointer;
    transition: border-color .22s;
    color-scheme: dark;
}
.fm-an-date-input:focus { border-color: var(--purple-light); box-shadow: 0 0 0 3px rgba(142,115,224,0.15); }
.fm-an-divider { color: var(--text-3); font-size: 12px; white-space: nowrap; }
.fm-an-apply-btn {
    height: 34px;
    padding: 0 18px;
    background: linear-gradient(135deg, #8e73e0 0%, #b994f0 100%);
    color: #fff;
    border: none;
    border-radius: var(--r-pill);
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    white-space: nowrap;
}
.fm-an-apply-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(142,115,224,0.45); }
.fm-analytics-loading { position: relative; pointer-events: none; }
.fm-analytics-loading::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(26,13,56,0.55);
    border-radius: var(--r-card);
    backdrop-filter: blur(1px);
    z-index: 10;
}
.fm-an-active-label {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
    color: var(--purple-lighter);
    background: rgba(142,115,224,0.12);
    border: 1px solid rgba(142,115,224,0.2);
    border-radius: var(--r-pill);
    padding: 3px 12px;
    white-space: nowrap;
}

/* =============================================
   TASK DESCRIPTION
   ============================================= */
.fm-task-desc {
    font-size: 12.5px;
    color: var(--text-2);
    margin: 5px 0 6px;
    line-height: 1.5;
    max-width: 560px;
    white-space: pre-wrap;
    word-break: break-word;
}

/* =============================================
   SUBTASK SECTION
   ============================================= */
.fm-subtask-section {
    margin-top: 8px;
    border-top: 1px solid var(--border);
    padding-top: 7px;
}
.fm-subtask-header {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
    padding: 3px 0;
}
.fm-subtask-header:hover .fm-subtask-progress-label { color: var(--purple-lighter); }
.fm-subtask-toggle-icon {
    width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-3);
    font-size: 10px;
    transition: transform .22s ease;
    flex-shrink: 0;
}
.fm-subtask-section.open .fm-subtask-toggle-icon { transform: rotate(90deg); }
.fm-subtask-progress-label {
    font-family: var(--font-head);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
    color: var(--text-3);
    text-transform: uppercase;
    white-space: nowrap;
    transition: color .2s;
}
.fm-subtask-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(142,115,224,0.12);
    border-radius: 4px;
    overflow: hidden;
    max-width: 140px;
}
.fm-subtask-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--purple-light), var(--accent2));
    border-radius: 4px;
    transition: width .45s ease;
    width: 0%;
}
.fm-subtask-bar-fill.all-done { background: linear-gradient(90deg, var(--green), #6bffb8); }
.fm-subtask-panel {
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: max-height .32s ease, opacity .25s ease, padding .3s ease;
    padding-top: 0;
}
.fm-subtask-section.open .fm-subtask-panel { max-height: 600px; opacity: 1; padding-top: 8px; }
.fm-subtask-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 8px; }
.fm-subtask-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border-radius: 7px;
    background: var(--bg-input);
    border: 1px solid transparent;
    transition: border-color .18s, background .18s;
    animation: slideIn .2s ease both;
}
.fm-subtask-item:hover { border-color: var(--border); background: var(--bg-hover); }
.fm-subtask-cb {
    width: 15px; height: 15px;
    flex-shrink: 0;
    accent-color: var(--accent);
    cursor: pointer;
    border-radius: 3px;
}
.fm-subtask-text {
    flex: 1;
    font-size: 13px;
    color: var(--text-2);
    cursor: pointer;
    line-height: 1.4;
    transition: color .18s;
}
.fm-subtask-text:hover { color: var(--text-1); }
.fm-subtask-text.done { text-decoration: line-through; color: var(--text-3); }
.fm-subtask-del {
    width: 22px; height: 22px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--text-3);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity .18s, color .18s, background .18s;
}
.fm-subtask-item:hover .fm-subtask-del { opacity: 1; }
.fm-subtask-del:hover { color: var(--red); background: rgba(255,82,99,0.12); }
.fm-subtask-add-area { display: flex; flex-direction: column; gap: 6px; }
.fm-subtask-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 28px;
    padding: 0 12px;
    border-radius: var(--r-pill);
    border: 1.5px dashed rgba(142,115,224,0.3);
    background: transparent;
    color: var(--text-3);
    font-family: var(--font-body);
    font-size: 12px;
    cursor: pointer;
    transition: all .2s;
    width: fit-content;
}
.fm-subtask-add-btn:hover { border-color: var(--purple-light); color: var(--purple-lighter); background: rgba(142,115,224,0.06); }
.fm-subtask-add-btn i { font-size: 10px; }
.fm-subtask-input-row { display: none; align-items: center; gap: 6px; }
.fm-subtask-input-row.show { display: flex; }
.fm-subtask-input-field {
    flex: 1;
    height: 30px;
    background: var(--bg-input);
    border: 1.5px solid var(--border);
    border-radius: 7px;
    color: var(--text-1);
    font-family: var(--font-body);
    font-size: 13px;
    padding: 0 10px;
    outline: none;
    transition: border-color .2s;
}
.fm-subtask-input-field::placeholder { color: var(--text-3); }
.fm-subtask-input-field:focus { border-color: var(--purple-light); box-shadow: 0 0 0 2px rgba(142,115,224,0.14); }
.fm-subtask-save-btn,
.fm-subtask-cancel-btn {
    width: 28px; height: 28px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    flex-shrink: 0;
    transition: all .2s;
}
.fm-subtask-save-btn { background: rgba(34,201,122,0.18); color: var(--green); }
.fm-subtask-save-btn:hover { background: rgba(34,201,122,0.35); }
.fm-subtask-cancel-btn { background: rgba(142,115,224,0.1); color: var(--text-3); }
.fm-subtask-cancel-btn:hover { background: rgba(255,82,99,0.14); color: var(--red); }
.fm-subtask-loading {
    font-size: 12px;
    color: var(--text-3);
    padding: 4px 0;
    display: flex;
    align-items: center;
    gap: 7px;
}

/* =============================================
   RESPONSIVENESS
   ============================================= */
@media (max-width: 900px) { .fm-charts-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .fm-topbar-row1 { grid-template-columns: 1fr; }
    .fm-right-panel { align-items: flex-start; flex-direction: row; justify-content: space-between; }
    .fm-create-row1 { flex-wrap: wrap; }
    .fm-create-row1 .fm-task-input { min-width: 160px; }
    .fm-task-card { flex-wrap: wrap; }
    .fm-task-right { width: 100%; justify-content: flex-end; }
    .fm-analytics-stats { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
    .fm-create-row1 { flex-direction: column; align-items: stretch; }
    .fm-create-row1 .fm-task-input { width: 100%; }
    .fm-create-row1 .fm-btn-create { width: 100%; justify-content: center; }
    .fm-priority-group { width: 100%; justify-content: center; }
    .fm-analytics-stats { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="focus-wrapper">
<div class="fm-container">

    <!-- ASSIGN SECTION (Super Admin) -->
    <?php if ($is_super_admin): ?>
    <div class="fm-assign-section" id="fm-assign-section">
        <span class="fm-assign-label"><i class="fas fa-user-tag" style="margin-right:6px;color:var(--purple-light);"></i>Assign To</span>
        <div class="fm-assign-pills" id="fm-assign-pills">
            <button class="fm-assign-pill active" data-assign-id="<?= $admin_id ?>" type="button">
                <span class="assign-avatar">ME</span>
                Self
            </button>
            <?php foreach ($all_admins as $adm): ?>
                <?php if ($adm['id'] != $admin_id): ?>
                <?php
                    $nameParts = explode(' ', trim($adm['full_name']));
                    $initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                    $firstName = htmlspecialchars($nameParts[0]);
                ?>
                <button class="fm-assign-pill" data-assign-id="<?= $adm['id'] ?>" type="button">
                    <span class="assign-avatar"><?= $initials ?></span>
                    <?= $firstName ?>
                </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TOP CONTROL BAR -->
    <div class="fm-topbar">
        <div class="fm-topbar-row1">
            <div class="fm-create-group">
                <div class="fm-create-row1">
                    <input type="text" id="fm-task-title" class="fm-task-input" placeholder="Create Task..." autocomplete="off">
                    <div class="fm-priority-group" id="fm-priority-group">
                        <button class="fm-priority-btn" data-priority="high" type="button"><span class="prio-dot"></span> HIGH</button>
                        <button class="fm-priority-btn active" data-priority="medium" type="button"><span class="prio-dot"></span> MED</button>
                        <button class="fm-priority-btn" data-priority="low" type="button"><span class="prio-dot"></span> LOW</button>
                    </div>
                    <button class="fm-btn-create" id="fm-btn-create" type="button">
                        <i class="fas fa-plus" style="font-size:13px;margin-right:6px;"></i>Create
                    </button>
                </div>
                <div class="fm-repeat-wrapper">
                    <label class="fm-repeat-label">
                        <input type="checkbox" id="fm-repeat-daily" name="repeat_enabled">
                        <i class="fas fa-redo" style="font-size:11px;color:var(--purple-light);"></i>
                        Repeat Daily
                    </label>
                    <div class="fm-repeat-extra" id="fm-repeat-extra">
                        <span style="color:var(--text-2);font-size:13px;">Every</span>
                        <input type="number" id="fm-repeat-interval" class="fm-mini-input" min="1" value="1" placeholder="1">
                        <select id="fm-repeat-type" class="fm-select" style="height:36px;padding:0 12px;width:auto;">
                            <option value="day">Day(s)</option>
                            <option value="hour">Hour(s)</option>
                        </select>
                        <input type="number" id="fm-repeat-count" class="fm-mini-input" min="0" max="100" value="0" placeholder="∞">
                        <span style="color:var(--text-3);font-size:11px;">times (0=∞)</span>
                    </div>
                </div>
            </div>
            <div class="fm-right-panel">
                <div class="fm-stats">
                    <div class="fm-stat pending"><span class="dot"></span><span id="stat-pending">0</span>&nbsp;Pending</div>
                    <div class="fm-stat done"><span class="dot"></span><span id="stat-done">0</span>&nbsp;Done</div>
                </div>
                <div class="fm-clock-block">
                    <div class="fm-date-str" id="fm-date-str">—</div>
                    <div class="fm-clock">
                        <span id="fm-h">00</span><span>:</span><span id="fm-m">00</span><span>:</span><span id="fm-s">00</span>
                        <span id="fm-ampm" style="font-size:14px;margin-left:6px;color:var(--purple-lighter);">AM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- USER FILTER PILLS -->
    <?php if ($is_super_admin): ?>
    <div class="fm-users-bar" id="fm-users-bar">
        <span style="font-family:var(--font-head);font-size:12px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;align-self:center;">View:</span>
        <button class="fm-user-pill active" data-user="<?= $admin_id ?>">My Tasks</button>
        <?php foreach ($all_admins as $adm): ?>
            <?php if ($adm['id'] != $admin_id): ?>
            <button class="fm-user-pill" data-user="<?= $adm['id'] ?>">
                <?= htmlspecialchars(strtoupper(explode(' ', trim($adm['full_name']))[0])) ?>
            </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TASK LIST AREA -->
    <div class="fm-list-header">
        <div class="fm-list-title">
            <i class="fas fa-bolt" style="color:var(--purple-light);margin-right:8px;font-size:16px;"></i>Task Queue
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="fm-bulk-select-btn" id="fm-multiselect-btn" type="button" title="Multi-select mode">
                <i class="fas fa-check-square" style="font-size:12px;"></i> Select
            </button>
            <button class="fm-toggle-done active" id="fm-toggle-done">
                <i class="fas fa-eye-slash"></i> Hide Completed
            </button>
        </div>
    </div>

    <div class="fm-bulk-bar" id="fm-bulk-bar">
        <span class="fm-bulk-count" id="fm-bulk-count">0 selected</span>
        <button class="fm-bulk-action-btn" id="fm-bulk-pin-btn" type="button" disabled>
            <i class="fas fa-star" style="font-size:11px;"></i> Pin Selected
        </button>
        <button class="fm-bulk-action-btn" id="fm-bulk-unpin-btn" type="button" disabled
                style="border-color:rgba(142,115,224,0.35);background:rgba(142,115,224,0.08);color:var(--purple-lighter);">
            <i class="far fa-star" style="font-size:11px;"></i> Unpin Selected
        </button>
        <button class="fm-bulk-select-btn" id="fm-multiselect-cancel" type="button" style="color:var(--red);border-color:rgba(255,82,99,0.3);">
            <i class="fas fa-times" style="font-size:11px;"></i> Cancel
        </button>
    </div>

    <div class="fm-task-list" id="fm-task-list">
        <div class="fm-shimmer"></div>
        <div class="fm-shimmer" style="opacity:.6;"></div>
        <div class="fm-shimmer" style="opacity:.35;"></div>
    </div>

    <!-- ANALYTICS SECTION -->
    <div class="fm-analytics" id="fm-analytics">
        <div class="fm-analytics-header">
            <div class="fm-analytics-title"><i class="fas fa-chart-bar"></i> Task Analytics</div>
        </div>
        <div class="fm-an-filter-bar" id="fm-an-filter-bar">
            <span class="fm-an-filter-label"><i class="fas fa-calendar-alt" style="margin-right:5px;"></i>Period</span>
            <div class="fm-an-filter-btns">
                <button class="fm-an-filter-btn active" data-filter="today"  type="button">Today</button>
                <button class="fm-an-filter-btn"        data-filter="week"   type="button">This Week</button>
                <button class="fm-an-filter-btn"        data-filter="month"  type="button">This Month</button>
                <button class="fm-an-filter-btn"        data-filter="custom" type="button">
                    <i class="fas fa-sliders-h" style="margin-right:5px;font-size:11px;"></i>Custom Range
                </button>
            </div>
            <div class="fm-an-custom-range" id="fm-an-custom-range">
                <span class="fm-an-divider">From</span>
                <input type="date" id="an-from-date" class="fm-an-date-input" />
                <span class="fm-an-divider">To</span>
                <input type="date" id="an-to-date" class="fm-an-date-input" />
                <button class="fm-an-apply-btn" id="fm-an-apply-btn" type="button">
                    <i class="fas fa-check" style="margin-right:5px;font-size:11px;"></i>Apply
                </button>
            </div>
            <span class="fm-an-active-label" id="fm-an-active-label">
                <i class="fas fa-circle" style="font-size:7px;"></i>
                <span id="fm-an-label-text">Today</span>
            </span>
        </div>
        <div class="fm-analytics-stats">
            <div class="fm-astat-card total"><div class="fm-astat-value" id="an-total">—</div><div class="fm-astat-label">Total Tasks</div></div>
            <div class="fm-astat-card done"><div class="fm-astat-value" id="an-done">—</div><div class="fm-astat-label">Completed</div></div>
            <div class="fm-astat-card pending"><div class="fm-astat-value" id="an-pending">—</div><div class="fm-astat-label">Pending</div></div>
            <div class="fm-astat-card high"><div class="fm-astat-value" id="an-high">—</div><div class="fm-astat-label">High Priority</div></div>
            <div class="fm-astat-card medium"><div class="fm-astat-value" id="an-medium">—</div><div class="fm-astat-label">Medium Priority</div></div>
            <div class="fm-astat-card low"><div class="fm-astat-value" id="an-low">—</div><div class="fm-astat-label">Low Priority</div></div>
        </div>
        <div class="fm-charts-grid">
            <div class="fm-chart-box">
                <h4><i class="fas fa-layer-group" style="margin-right:6px;color:var(--purple-light);"></i>Priority Breakdown</h4>
                <canvas id="chart-priority"></canvas>
            </div>
            <div class="fm-chart-box">
                <h4><i class="fas fa-circle-half-stroke" style="margin-right:6px;color:var(--purple-light);"></i>Status Overview</h4>
                <canvas id="chart-status"></canvas>
            </div>
        </div>
    </div>

</div><!-- /fm-container -->
</div><!-- /focus-wrapper -->

<!-- ADVANCED TASK MODAL -->
<div class="modal fade fm-modal" id="fmAdvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h" style="margin-right:10px;color:var(--purple-lighter);"></i>Advanced Task Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="fm-adv-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task Title *</label>
                        <input type="text" id="adv-title" class="form-control" placeholder="Enter task title..." required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Category</label>
                            <select id="adv-category" class="form-select">
                                <option value="Work">Work</option>
                                <option value="Personal">Personal</option>
                                <option value="Study">Study</option>
                                <option value="Health">Health</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select id="adv-priority" class="form-select">
                                <option value="high">🔴 High</option>
                                <option value="medium" selected>🟡 Medium</option>
                                <option value="low">🟢 Low</option>
                            </select>
                        </div>
                    </div>
                    <?php if ($is_super_admin): ?>
                    <div class="mb-3">
                        <label class="form-label">Time Block</label>
                        <select id="adv-time-block" class="form-select">
                            <option value="all_day">All Day</option>
                            <option value="6-9am">6–9 AM</option>
                            <option value="9-2pm">9–2 PM</option>
                            <option value="2-7pm">2–7 PM</option>
                            <option value="7-12am">7–12 AM</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="adv-start" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" id="adv-end" class="form-control">
                        </div>
                    </div>
                    <?php if ($is_super_admin): ?>
                    <div class="mb-3">
                        <label class="form-label">Assign To</label>
                        <select id="adv-assign" class="form-select">
                            <option value="<?= $admin_id ?>">Self</option>
                            <?php foreach ($all_admins as $adm): ?>
                                <?php if ($adm['id'] != $admin_id): ?>
                                <option value="<?= $adm['id'] ?>"><?= htmlspecialchars($adm['full_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            style="background:var(--bg-input);border:1px solid var(--border);color:var(--text-2);">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                            style="background:linear-gradient(135deg,#8e73e0,#b994f0);border:none;font-family:var(--font-head);font-size:16px;font-weight:600;padding:10px 28px;border-radius:50px;">
                        <i class="fas fa-plus" style="margin-right:6px;"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* =============================================
   FOCUS MODE — JAVASCRIPT
   ============================================= */
const IS_SUPER_ADMIN = <?= $is_super_admin ? 'true' : 'false' ?>;
const MY_ADMIN_ID    = <?= $admin_id ?>;

let currentUserId    = MY_ADMIN_ID;
let showCompleted    = true;
let selectedPriority = 'medium';
let chartPriority    = null;
let chartStatus      = null;

/* Track open subtask panels across renders */
const _openSubtaskPanels = new Set();

/* =============================================
   LIVE CLOCK
   ============================================= */
function updateClock() {
    const now    = new Date();
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const d  = now.getDate().toString().padStart(2,'0');
    const mo = months[now.getMonth()];
    const y  = now.getFullYear();
    document.getElementById('fm-date-str').textContent = `${days[now.getDay()]}, ${d} ${mo} ${y}`;
    let hours = now.getHours();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    document.getElementById('fm-h').textContent = String(hours).padStart(2,'0');
    document.getElementById('fm-m').textContent = String(now.getMinutes()).padStart(2,'0');
    document.getElementById('fm-s').textContent = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('fm-ampm').textContent = ampm;
}
updateClock();
setInterval(updateClock, 1000);

/* =============================================
   PRIORITY BUTTONS
   ============================================= */
document.querySelectorAll('.fm-priority-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.fm-priority-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedPriority = this.dataset.priority;
    });
});

/* =============================================
   ASSIGN PILLS
   ============================================= */
let selectedAssignId = MY_ADMIN_ID;
document.querySelectorAll('.fm-assign-pill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.fm-assign-pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedAssignId = parseInt(this.dataset.assignId);
    });
});

/* =============================================
   REPEAT CHECKBOX
   ============================================= */
document.getElementById('fm-repeat-daily').addEventListener('change', function() {
    const extra = document.getElementById('fm-repeat-extra');
    if (this.checked) {
        extra.classList.add('show');
    } else {
        extra.classList.remove('show');
        document.getElementById('fm-repeat-interval').value = 1;
        document.getElementById('fm-repeat-type').value     = 'day';
        document.getElementById('fm-repeat-count').value    = 0;
    }
});

/* =============================================
   USER FILTER PILLS
   ============================================= */
document.querySelectorAll('.fm-user-pill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.fm-user-pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentUserId = this.dataset.user;
        loadTasks();
        loadAnalytics();
    });
});

/* =============================================
   TOGGLE COMPLETED
   ============================================= */
const toggleDoneBtn = document.getElementById('fm-toggle-done');
toggleDoneBtn.addEventListener('click', function() {
    showCompleted = !showCompleted;
    this.classList.toggle('active', showCompleted);
    this.innerHTML = showCompleted
        ? '<i class="fas fa-eye-slash"></i> Hide Completed'
        : '<i class="fas fa-eye"></i> Show Completed';
    loadTasks();
});

/* =============================================
   QUICK CREATE
   ============================================= */
document.getElementById('fm-task-title').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') createTask();
});
document.getElementById('fm-btn-create').addEventListener('click', createTask);

function createTask() {
    const title = document.getElementById('fm-task-title').value.trim();
    if (!title) { shake(document.getElementById('fm-task-title')); return; }

    const fd = new FormData();
    fd.append('action',    'add_focus');
    fd.append('title',     title);
    fd.append('category',  'Work');
    fd.append('time_block','all_day');
    fd.append('priority',  selectedPriority);

    const today = new Date().toISOString().split('T')[0];
    fd.append('start_date', today);
    fd.append('end_date',   today);
    fd.append('assigned_to', IS_SUPER_ADMIN ? selectedAssignId : MY_ADMIN_ID);

    if (document.getElementById('fm-repeat-daily').checked) {
        fd.append('repeat_enabled',  '1');
        fd.append('repeat_interval', document.getElementById('fm-repeat-interval').value || 1);
        fd.append('repeat_type',     document.getElementById('fm-repeat-type').value || 'day');
        fd.append('repeat_count',    document.getElementById('fm-repeat-count').value || 0);
    }

    const btn = document.getElementById('fm-btn-create');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:13px;"></i> Creating...';

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('fm-task-title').value = '';
                document.getElementById('fm-repeat-daily').checked = false;
                document.getElementById('fm-repeat-extra').classList.remove('show');
                loadTasks();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus" style="font-size:13px;margin-right:6px;"></i>Create';
        });
}

/* =============================================
   ADVANCED MODAL
   ============================================= */
document.getElementById('fm-adv-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const title = document.getElementById('adv-title').value.trim();
    if (!title) return;

    const fd = new FormData();
    fd.append('action',      'add_focus');
    fd.append('title',       title);
    fd.append('category',    document.getElementById('adv-category').value);
    fd.append('priority',    document.getElementById('adv-priority').value);
    fd.append('time_block',  IS_SUPER_ADMIN ? (document.getElementById('adv-time-block')?.value || 'all_day') : 'all_day');
    fd.append('start_date',  document.getElementById('adv-start').value);
    fd.append('end_date',    document.getElementById('adv-end').value);
    fd.append('assigned_to', IS_SUPER_ADMIN ? (document.getElementById('adv-assign')?.value || MY_ADMIN_ID) : MY_ADMIN_ID);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('fmAdvModal')).hide();
                document.getElementById('fm-adv-form').reset();
                loadTasks();
            } else {
                alert('Error: ' + data.message);
            }
        });
});

/* =============================================
   LOAD + RENDER TASKS
   ============================================= */
function loadTasks(silent = false) {
    const list = document.getElementById('fm-task-list');
    if (!silent) {
        list.innerHTML = `
            <div class="fm-shimmer"></div>
            <div class="fm-shimmer" style="opacity:.6;"></div>
            <div class="fm-shimmer" style="opacity:.35;"></div>
        `;
    }

    const params = new URLSearchParams();
    params.append('action', 'get_focus_tasks');
    if (IS_SUPER_ADMIN) params.append('user_id', currentUserId);
    params.append('show_completed', showCompleted ? '1' : '0');

    fetch(`task_manager_actions.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTasks(data.tasks);
                updateAnalytics(data.tasks);
            } else {
                if (!silent) {
                    list.innerHTML = `<div class="fm-empty"><i class="fas fa-exclamation-circle"></i><p>${data.message || 'Failed to load tasks'}</p></div>`;
                }
            }
        })
        .catch(() => {
            if (!silent) {
                list.innerHTML = `<div class="fm-empty"><i class="fas fa-wifi"></i><p>Network error — check connection</p></div>`;
            }
        });
}

function renderTasks(tasks) {
    const list = document.getElementById('fm-task-list');
    list.innerHTML = '';

    const pending   = tasks.filter(t => t.status === 'Pending').length;
    const completed = tasks.filter(t => t.status === 'Completed').length;
    document.getElementById('stat-pending').textContent = pending;
    document.getElementById('stat-done').textContent    = completed;

    if (!tasks.length) {
        list.innerHTML = `<div class="fm-empty"><i class="fas fa-check-double"></i><p>No tasks here — you're all clear!</p></div>`;
        return;
    }

    tasks.forEach((task, idx) => {
        const card = buildTaskCard(task, idx + 1);
        list.appendChild(card);
        // Re-open subtask panels that were open before re-render
        if (_openSubtaskPanels.has(String(task.id))) {
            const section = card.querySelector('.fm-subtask-section');
            if (section) {
                section.classList.add('open');
                loadSubtasks(task.id);
            }
        }
    });
}

/* =============================================
   BUILD TASK CARD — single clean definition with subtasks
   ============================================= */
function buildTaskCard(task, num) {
    const card = document.createElement('div');

    const priority   = task.priority  || 'medium';
    const isPinned   = task.is_pinned == 1;
    const statusClass = task.status.toLowerCase() === 'completed' ? 'status-completed'
                       : task.status.toLowerCase() === 'incomplete' ? 'status-incomplete'
                       : '';
    const pinnedClass = isPinned ? 'task-pinned' : '';
    card.className  = `fm-task-card priority-${priority} ${statusClass} ${pinnedClass}`.trim();
    card.style.animationDelay = `${(num - 1) * 0.04}s`;
    card.dataset.taskId = task.id;

    /* Badges */
    let repeatBadge = '';
    if (task.is_repeating == 1) {
        const rLabel = (task.max_repeats == 0 || task.max_repeats == null) ? '♾ Repeat' : `🔄 ×${task.max_repeats}`;
        repeatBadge = `<span class="fm-badge fm-badge-repeat">${rLabel}</span>`;
    }
    const pinBadge      = isPinned ? `<span class="fm-badge-pin"><i class="fas fa-star" style="font-size:9px;"></i> Pinned</span>` : '';
    const prioMap       = { high:'HIGH', medium:'MED', low:'LOW' };
    const priorityBadge = `<span class="fm-badge fm-badge-priority prio-${priority}">${prioMap[priority] || 'MED'}</span>`;

    const statusMap   = { 'Pending':'pending', 'Completed':'done', 'Incomplete':'incomplete' };
    const statusLabel = { 'Pending':'Pending', 'Completed':'Done', 'Incomplete':'Incomplete' };
    const sClass = statusMap[task.status] || 'pending';
    const sLabel = statusLabel[task.status] || task.status;

    const doneBtn = task.status === 'Pending'
        ? `<button class="fm-btn-done" onclick="completeTask(${task.id},this)" type="button">
               <i class="fas fa-check" style="margin-right:5px;font-size:12px;"></i>Mark Done
           </button>`
        : '';

    const dateStr = `${formatDate(task.start_date)}${task.end_date && task.end_date !== task.start_date ? ' → ' + formatDate(task.end_date) : ''}`;

    /* Description (only if non-empty) */
    const descHtml = (task.description && task.description.trim())
        ? `<div class="fm-task-desc">${escHtml(task.description.trim())}</div>`
        : '';

    /* Subtask section — lazy loads on first open */
    const subtaskHtml = `
        <div class="fm-subtask-section" id="subtask-section-${task.id}">
            <div class="fm-subtask-header" onclick="toggleSubtaskPanel(${task.id})">
                <span class="fm-subtask-toggle-icon"><i class="fas fa-chevron-right"></i></span>
                <span class="fm-subtask-progress-label" id="subtask-label-${task.id}">Subtasks</span>
                <div class="fm-subtask-bar-track">
                    <div class="fm-subtask-bar-fill" id="subtask-bar-${task.id}"></div>
                </div>
            </div>
            <div class="fm-subtask-panel" id="subtask-panel-${task.id}">
                <div class="fm-subtask-list" id="subtask-list-${task.id}">
                    <div class="fm-subtask-loading">
                        <i class="fas fa-spinner fa-spin" style="font-size:11px;color:var(--purple-light);"></i>
                        Loading subtasks…
                    </div>
                </div>
                <div class="fm-subtask-add-area">
                    <button class="fm-subtask-add-btn" onclick="showSubtaskInput(${task.id})" type="button">
                        <i class="fas fa-plus"></i> Add Subtask
                    </button>
                    <div class="fm-subtask-input-row" id="subtask-input-row-${task.id}">
                        <input
                            type="text"
                            class="fm-subtask-input-field"
                            id="subtask-input-field-${task.id}"
                            placeholder="Subtask title…"
                            onkeydown="if(event.key==='Enter')addSubtask(${task.id}); if(event.key==='Escape')hideSubtaskInput(${task.id});"
                        >
                        <button class="fm-subtask-save-btn" onclick="addSubtask(${task.id})" title="Add" type="button">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="fm-subtask-cancel-btn" onclick="hideSubtaskInput(${task.id})" title="Cancel" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

    card.innerHTML = `
        <input type="checkbox" class="fm-task-checkbox" data-task-id="${task.id}" onchange="onCheckboxChange()">
        <div class="fm-task-num">${num}</div>
        <div class="fm-task-info">
            <div class="fm-task-title">${escHtml(task.title)}</div>
            ${descHtml}
            <div class="fm-task-meta">
                ${pinBadge}
                ${priorityBadge}
                <span class="fm-badge fm-badge-cat"><i class="fas fa-tag" style="font-size:9px;"></i>&nbsp;${escHtml(task.category || 'Task')}</span>
                <span class="fm-badge fm-badge-date"><i class="fas fa-calendar" style="font-size:9px;"></i>&nbsp;${dateStr}</span>
                ${repeatBadge}
            </div>
            ${subtaskHtml}
        </div>
        <div class="fm-task-right">
            <span class="fm-status-badge ${sClass}" id="task-status-badge-${task.id}">${sLabel}</span>
            ${doneBtn}
            <button class="fm-btn-pin ${isPinned ? 'pinned' : ''}" onclick="pinTask(${task.id}, this)" type="button"
                    title="${isPinned ? 'Unpin task' : 'Pin task'}">
                <i class="${isPinned ? 'fas' : 'far'} fa-star"></i>
            </button>
            <button class="fm-btn-delete" onclick="deleteTask(${task.id},this)" title="Delete task" type="button">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    `;

    return card;
}

/* =============================================
   COMPLETE TASK
   ============================================= */
function completeTask(taskId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData();
    fd.append('action',  'complete');
    fd.append('task_id', taskId);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadTasks();
            else {
                alert('Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check" style="margin-right:5px;font-size:12px;"></i>Mark Done';
            }
        });
}

/* =============================================
   DELETE TASK
   ============================================= */
function deleteTask(taskId, btn) {
    if (!confirm('Delete this task?')) return;
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action',  'delete');
    fd.append('task_id', taskId);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const card = btn.closest('.fm-task-card');
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'translateX(20px)';
                _openSubtaskPanels.delete(String(taskId));
                setTimeout(loadTasks, 300);
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false;
            }
        });
}

/* =============================================
   PIN TASK
   ============================================= */
function pinTask(taskId, btn) {
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action',  'toggle_pin');
    fd.append('task_id', taskId);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const isPinned = data.is_pinned == 1;
                btn.classList.toggle('pinned', isPinned);
                btn.querySelector('i').className = isPinned ? 'fas fa-star' : 'far fa-star';
                btn.title = isPinned ? 'Unpin task' : 'Pin task';
                setTimeout(() => loadTasks(true), 200);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error — could not pin task'))
        .finally(() => { btn.disabled = false; });
}

/* =============================================
   SUBTASK FUNCTIONS
   ============================================= */

/** Open/close the subtask panel. Lazy-loads on first open. */
function toggleSubtaskPanel(taskId) {
    const section = document.getElementById(`subtask-section-${taskId}`);
    if (!section) return;

    const isOpen = section.classList.contains('open');
    if (isOpen) {
        section.classList.remove('open');
        _openSubtaskPanels.delete(String(taskId));
    } else {
        section.classList.add('open');
        _openSubtaskPanels.add(String(taskId));
        const list = document.getElementById(`subtask-list-${taskId}`);
        if (list && list.querySelector('.fm-subtask-loading')) {
            loadSubtasks(taskId);
        }
    }
}

/** Fetch subtasks from backend and render them. */
function loadSubtasks(taskId) {
    fetch(`task_manager_actions.php?action=get_subtasks&task_id=${taskId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderSubtasks(taskId, data.subtasks);
            } else {
                const list = document.getElementById(`subtask-list-${taskId}`);
                if (list) list.innerHTML = `<div class="fm-subtask-loading" style="color:var(--red);">${escHtml(data.message)}</div>`;
            }
        })
        .catch(() => {
            const list = document.getElementById(`subtask-list-${taskId}`);
            if (list) list.innerHTML = `<div class="fm-subtask-loading" style="color:var(--red);">Network error — check server</div>`;
        });
}

/** Render subtask list and update progress. */
function renderSubtasks(taskId, subtasks) {
    const list = document.getElementById(`subtask-list-${taskId}`);
    if (!list) return;

    if (!subtasks.length) {
        list.innerHTML = `<div style="font-size:12px;color:var(--text-3);padding:2px 0;">No subtasks yet — add one below</div>`;
    } else {
        list.innerHTML = subtasks.map(st => buildSubtaskRow(taskId, st)).join('');
    }

    updateSubtaskProgress(taskId, subtasks);
}

/** HTML string for one subtask row. */
function buildSubtaskRow(taskId, st) {
    const isDone = st.is_completed == 1;
    return `
        <div class="fm-subtask-item" id="subtask-item-${st.id}">
            <input type="checkbox" class="fm-subtask-cb" ${isDone ? 'checked' : ''}
                   onchange="toggleSubtask(${st.id}, ${taskId})">
            <span class="fm-subtask-text ${isDone ? 'done' : ''}"
                  onclick="toggleSubtask(${st.id}, ${taskId})">${escHtml(st.title)}</span>
            <button class="fm-subtask-del" onclick="deleteSubtask(${st.id}, ${taskId})"
                    title="Delete subtask" type="button"><i class="fas fa-times"></i></button>
        </div>
    `;
}

/** Update progress label and bar. Accepts array of subtasks or {total, done} object. */
function updateSubtaskProgress(taskId, subtasksOrCounts) {
    const label = document.getElementById(`subtask-label-${taskId}`);
    const bar   = document.getElementById(`subtask-bar-${taskId}`);
    if (!label || !bar) return;

    let total, done;
    if (Array.isArray(subtasksOrCounts)) {
        total = subtasksOrCounts.length;
        done  = subtasksOrCounts.filter(s => s.is_completed == 1).length;
    } else {
        total = subtasksOrCounts.total;
        done  = subtasksOrCounts.done;
    }

    if (total === 0) {
        label.textContent = 'Subtasks';
        bar.style.width   = '0%';
        bar.classList.remove('all-done');
        return;
    }

    label.textContent = `${done} / ${total} done`;
    const pct = Math.round((done / total) * 100);
    bar.style.width = `${pct}%`;
    bar.classList.toggle('all-done', done === total);
}

/** Toggle a subtask — optimistic UI then confirm with server. */
function toggleSubtask(subtaskId, taskId) {
    const item = document.getElementById(`subtask-item-${subtaskId}`);
    if (!item) return;
    const cb   = item.querySelector('.fm-subtask-cb');
    const text = item.querySelector('.fm-subtask-text');
    if (!cb || !text) return;

    const newState = cb.checked;   // already toggled by browser
    text.classList.toggle('done', newState);

    const fd = new FormData();
    fd.append('action',     'toggle_subtask');
    fd.append('subtask_id', subtaskId);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                cb.checked = data.is_completed == 1;
                text.classList.toggle('done', data.is_completed == 1);
                updateSubtaskProgress(taskId, { total: data.subtasks_total, done: data.subtasks_done });

                // Update parent status badge without full reload
                const badge = document.getElementById(`task-status-badge-${taskId}`);
                if (badge) {
                    const sMap = { 'Completed':'done', 'Pending':'pending', 'Incomplete':'incomplete' };
                    const lMap = { 'Completed':'Done', 'Pending':'Pending', 'Incomplete':'Incomplete' };
                    badge.className   = `fm-status-badge ${sMap[data.parent_status] || 'pending'}`;
                    badge.textContent = lMap[data.parent_status] || data.parent_status;
                }

                loadTasks(true);  // silent refresh for top stat counters
            } else {
                // Revert
                cb.checked = !newState;
                text.classList.toggle('done', !newState);
                console.error('Toggle failed:', data.message);
            }
        })
        .catch(() => {
            cb.checked = !newState;
            text.classList.toggle('done', !newState);
        });
}

/** Add a new subtask. */
function addSubtask(taskId) {
    const input = document.getElementById(`subtask-input-field-${taskId}`);
    if (!input) return;
    const title = input.value.trim();
    if (!title) { shake(input); return; }

    const fd = new FormData();
    fd.append('action',  'add_subtask');
    fd.append('task_id', taskId);
    fd.append('title',   title);

    input.disabled = true;

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                hideSubtaskInput(taskId);
                loadSubtasks(taskId);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error — could not add subtask'))
        .finally(() => { input.disabled = false; });
}

/** Delete a subtask with animation. */
function deleteSubtask(subtaskId, taskId) {
    if (!confirm('Delete this subtask?')) return;

    const item = document.getElementById(`subtask-item-${subtaskId}`);
    if (item) {
        item.style.transition = 'opacity .2s, transform .2s';
        item.style.opacity    = '0';
        item.style.transform  = 'translateX(8px)';
    }

    const fd = new FormData();
    fd.append('action',     'delete_subtask');
    fd.append('subtask_id', subtaskId);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => loadSubtasks(taskId), 220);
            } else {
                if (item) { item.style.opacity = '1'; item.style.transform = ''; }
                alert('Error: ' + data.message);
            }
        })
        .catch(() => {
            if (item) { item.style.opacity = '1'; item.style.transform = ''; }
            alert('Network error');
        });
}

function showSubtaskInput(taskId) {
    const row = document.getElementById(`subtask-input-row-${taskId}`);
    if (row) {
        row.classList.add('show');
        setTimeout(() => { const f = document.getElementById(`subtask-input-field-${taskId}`); if (f) f.focus(); }, 50);
    }
}

function hideSubtaskInput(taskId) {
    const row = document.getElementById(`subtask-input-row-${taskId}`);
    if (row) {
        row.classList.remove('show');
        const f = document.getElementById(`subtask-input-field-${taskId}`);
        if (f) f.value = '';
    }
}

/* =============================================
   MULTI-SELECT SYSTEM
   ============================================= */
let multiSelectMode = false;

function toggleMultiSelectMode(on) {
    multiSelectMode = (on !== undefined) ? on : !multiSelectMode;
    const list   = document.getElementById('fm-task-list');
    const bar    = document.getElementById('fm-bulk-bar');
    const selBtn = document.getElementById('fm-multiselect-btn');
    list.classList.toggle('fm-multiselect-active', multiSelectMode);
    bar.classList.toggle('show', multiSelectMode);
    selBtn.classList.toggle('active', multiSelectMode);
    if (!multiSelectMode) {
        document.querySelectorAll('.fm-task-checkbox').forEach(cb => cb.checked = false);
        updateBulkCount();
    }
}

function onCheckboxChange() { updateBulkCount(); }

function updateBulkCount() {
    const checked = document.querySelectorAll('.fm-task-checkbox:checked');
    const count   = checked.length;
    document.getElementById('fm-bulk-count').textContent      = `${count} selected`;
    document.getElementById('fm-bulk-pin-btn').disabled       = count === 0;
    document.getElementById('fm-bulk-unpin-btn').disabled     = count === 0;
}

function bulkPinAction(pinState) {
    const checked = document.querySelectorAll('.fm-task-checkbox:checked');
    if (!checked.length) return;
    const ids = Array.from(checked).map(cb => cb.dataset.taskId);
    document.getElementById('fm-bulk-pin-btn').disabled   = true;
    document.getElementById('fm-bulk-unpin-btn').disabled = true;

    const fd = new FormData();
    fd.append('action',    'bulk_pin');
    fd.append('task_ids',  JSON.stringify(ids));
    fd.append('pin_state', pinState);

    fetch('task_manager_actions.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toggleMultiSelectMode(false);
                loadTasks();
            } else {
                alert('Error: ' + data.message);
                updateBulkCount();
            }
        })
        .catch(() => { alert('Network error'); updateBulkCount(); });
}

document.getElementById('fm-multiselect-btn').addEventListener('click', () => toggleMultiSelectMode());
document.getElementById('fm-multiselect-cancel').addEventListener('click', () => toggleMultiSelectMode(false));
document.getElementById('fm-bulk-pin-btn').addEventListener('click', () => bulkPinAction(1));
document.getElementById('fm-bulk-unpin-btn').addEventListener('click', () => bulkPinAction(0));

/* =============================================
   ANALYTICS
   ============================================= */
let analyticsFilter   = 'today';
let analyticsFromDate = '';
let analyticsToDate   = '';
const AN_FILTER_LABELS = { today:'Today', week:'This Week', month:'This Month', custom:null };

document.querySelectorAll('.fm-an-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const f = this.dataset.filter;
        document.querySelectorAll('.fm-an-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        analyticsFilter = f;
        const customRow = document.getElementById('fm-an-custom-range');
        if (f === 'custom') {
            customRow.classList.add('show');
            if (!document.getElementById('an-from-date').value) {
                const d = new Date(); d.setDate(1);
                document.getElementById('an-from-date').value = d.toISOString().split('T')[0];
                document.getElementById('an-to-date').value   = new Date().toISOString().split('T')[0];
            }
            return;
        } else {
            customRow.classList.remove('show');
        }
        document.getElementById('fm-an-label-text').textContent = AN_FILTER_LABELS[f];
        loadAnalytics();
    });
});

document.getElementById('fm-an-apply-btn').addEventListener('click', function() {
    const from = document.getElementById('an-from-date').value;
    const to   = document.getElementById('an-to-date').value;
    if (!from || !to) { shake(document.getElementById('an-from-date')); return; }
    if (from > to)    { shake(document.getElementById('an-to-date')); return; }
    analyticsFromDate = from;
    analyticsToDate   = to;
    document.getElementById('fm-an-label-text').textContent = `${formatDate(from)} → ${formatDate(to)}`;
    loadAnalytics();
});

function loadAnalytics() {
    const box = document.getElementById('fm-analytics');
    box.classList.add('fm-analytics-loading');
    const params = new URLSearchParams();
    params.append('action',      'get_analytics');
    params.append('filter_type', analyticsFilter);
    if (analyticsFilter === 'custom') {
        params.append('from_date', analyticsFromDate);
        params.append('to_date',   analyticsToDate);
    }
    if (IS_SUPER_ADMIN) params.append('user_id', currentUserId);
    fetch(`task_manager_actions.php?${params}`)
        .then(r => r.json())
        .then(data => {
            box.classList.remove('fm-analytics-loading');
            if (data.success) applyAnalyticsData(data);
            else console.warn('Analytics error:', data.message);
        })
        .catch(err => {
            box.classList.remove('fm-analytics-loading');
            console.error('Analytics fetch failed:', err);
        });
}

function applyAnalyticsData(data) {
    animateCountUp('an-total',   data.total   ?? 0);
    animateCountUp('an-done',    data.done    ?? 0);
    animateCountUp('an-pending', data.pending ?? 0);
    animateCountUp('an-high',    data.high    ?? 0);
    animateCountUp('an-medium',  data.medium  ?? 0);
    animateCountUp('an-low',     data.low     ?? 0);
    renderCharts(data.high ?? 0, data.medium ?? 0, data.low ?? 0, data.done ?? 0, data.pending ?? 0);
}

function animateCountUp(elId, target) {
    const el = document.getElementById(elId);
    const steps = 15;
    let frame = 0;
    const timer = setInterval(() => {
        frame++;
        el.textContent = frame >= steps ? target : Math.round((target / steps) * frame);
        if (frame >= steps) clearInterval(timer);
    }, 33);
}

function updateAnalytics() { loadAnalytics(); }

function renderCharts(high, medium, low, done, pending) {
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { labels: { color:'#b09dd4', font:{ family:'DM Sans', size:12 }, padding:16, usePointStyle:true } },
            tooltip: { backgroundColor:'#220f43', borderColor:'rgba(142,115,224,0.3)', borderWidth:1, titleColor:'#f0e8ff', bodyColor:'#b09dd4', padding:12, cornerRadius:8 }
        }
    };

    const ctxBar = document.getElementById('chart-priority').getContext('2d');
    if (chartPriority) { chartPriority.destroy(); chartPriority = null; }
    chartPriority = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['High', 'Medium', 'Low'],
            datasets: [{
                label: 'Tasks',
                data: [high, medium, low],
                backgroundColor: ['rgba(255,71,87,0.65)','rgba(255,179,71,0.65)','rgba(34,201,122,0.65)'],
                borderColor:     ['rgba(255,71,87,1)',    'rgba(255,179,71,1)',    'rgba(34,201,122,1)'],
                borderWidth: 2, borderRadius: 8, borderSkipped: false
            }]
        },
        options: {
            ...chartDefaults,
            scales: {
                x: { ticks:{ color:'#b09dd4', font:{ family:'DM Sans', size:12 } }, grid:{ color:'rgba(142,115,224,0.07)' } },
                y: { beginAtZero:true, ticks:{ color:'#b09dd4', font:{ family:'DM Sans', size:12 }, stepSize:1, precision:0 }, grid:{ color:'rgba(142,115,224,0.07)' } }
            },
            plugins: { ...chartDefaults.plugins, legend:{ display:false } }
        }
    });

    const ctxPie = document.getElementById('chart-status').getContext('2d');
    if (chartStatus) { chartStatus.destroy(); chartStatus = null; }
    chartStatus = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending / Incomplete'],
            datasets: [{
                data: [done, pending],
                backgroundColor: ['rgba(34,201,122,0.7)','rgba(255,179,71,0.7)'],
                borderColor:     ['rgba(34,201,122,1)',   'rgba(255,179,71,1)'],
                borderWidth: 2, hoverOffset: 8
            }]
        },
        options: { ...chartDefaults, cutout:'60%', plugins:{ ...chartDefaults.plugins, legend:{ ...chartDefaults.plugins.legend, position:'bottom' } } }
    });
}

/* =============================================
   UTILITIES
   ============================================= */
function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${d.getDate()} ${months[d.getMonth()]}`;
}

function shake(el) {
    el.style.animation = 'none';
    el.offsetHeight;
    el.style.animation = 'shake .3s ease';
    setTimeout(() => el.style.animation = '', 400);
}

const _shakeStyle = document.createElement('style');
_shakeStyle.textContent = `@keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }`;
document.head.appendChild(_shakeStyle);

/* =============================================
   INIT — visibility-aware polling (saves DB connections)
   Combined loadTasks + loadAnalytics into single request
   ============================================= */
let _dashboardPollTimer = null;
let _dashboardInFlight = false;

function loadDashboard(silent) {
    if (_dashboardInFlight) return;
    _dashboardInFlight = true;

    const params = new URLSearchParams();
    params.append('action', 'get_focus_dashboard');
    if (IS_SUPER_ADMIN) params.append('user_id', currentUserId);
    params.append('show_completed', showCompleted ? '1' : '0');
    params.append('filter_type', analyticsFilter);
    if (analyticsFilter === 'custom') {
        params.append('from_date', analyticsFromDate);
        params.append('to_date', analyticsToDate);
    }

    if (!silent) {
        const list = document.getElementById('fm-task-list');
        list.innerHTML = `
            <div class="fm-shimmer"></div>
            <div class="fm-shimmer" style="opacity:.6;"></div>
            <div class="fm-shimmer" style="opacity:.35;"></div>
        `;
    }

    fetch(`task_manager_actions.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update tasks
                renderTasks(data.tasks);

                // Update analytics from combined response
                if (data.analytics) {
                    var box = document.getElementById('fm-analytics');
                    if (box) box.classList.remove('fm-analytics-loading');
                    applyAnalyticsData(data.analytics);
                }
            } else {
                if (!silent) {
                    const list = document.getElementById('fm-task-list');
                    list.innerHTML = `<div class="fm-empty"><i class="fas fa-exclamation-circle"></i><p>${data.message || 'Failed to load'}</p></div>`;
                }
            }
        })
        .catch(() => {
            if (!silent) {
                const list = document.getElementById('fm-task-list');
                list.innerHTML = `<div class="fm-empty"><i class="fas fa-wifi"></i><p>Network error — check connection</p></div>`;
            }
        })
        .finally(() => { _dashboardInFlight = false; });
}

// Backwards compatibility — loadTasks and loadAnalytics still work
function loadTasks(silent) { loadDashboard(silent); }
function loadAnalytics() { /* handled by loadDashboard */ }

function _startPolling() {
    if (!_dashboardPollTimer) _dashboardPollTimer = setInterval(() => { if (!document.hidden) loadDashboard(true); }, 300000);
}
function _stopPolling() {
    if (_dashboardPollTimer) { clearInterval(_dashboardPollTimer); _dashboardPollTimer = null; }
}
document.addEventListener('visibilitychange', function() {
    if (document.hidden) { _stopPolling(); } else { loadDashboard(true); _startPolling(); }
});
_startPolling();
loadDashboard();

window.openAdvModal = function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('adv-start').value = today;
    document.getElementById('adv-end').value   = today;
    new bootstrap.Modal(document.getElementById('fmAdvModal')).show();
};
</script>

<!--<?php // include 'includes/footer.php'; ?>-->