<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/ranking_helper.php';
requireLogin();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    header('Location: students.php');
    exit();
}

// Handle batch toggle
if (isset($_POST['toggle_batch'])) {
    $current_batch = $_POST['current_batch'];
    $new_batch = ($current_batch === 'Morning') ? 'Evening' : 'Morning';
    $stmt = $conn->prepare("UPDATE students SET batch = ? WHERE id = ?");
    $stmt->bind_param("si", $new_batch, $student_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Batch changed to $new_batch successfully!";
    } else {
        $_SESSION['error'] = "Failed to change batch.";
    }
    $stmt->close();
    header("Location: student_details.php?id=$student_id");
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_student') {
        $full_name       = sanitize($_POST['full_name']);
        $email           = sanitize($_POST['email']);
        $phone           = sanitize($_POST['phone']);
        $address         = sanitize($_POST['address']);
        $birthdate       = sanitize($_POST['birthdate']);
        $category_id     = (int)$_POST['category_id'];
        $course_id       = (int)$_POST['course_id'];
        $duration_months = (int)$_POST['duration_months'];
        $total_fees      = (float)$_POST['total_fees'];
        $stmt = $conn->prepare("UPDATE students SET full_name=?,email=?,phone=?,address=?,birthdate=?,category_id=?,course_id=?,duration_months=?,total_fees=? WHERE id=?");
        $stmt->bind_param("sssssiidii", $full_name, $email, $phone, $address, $birthdate, $category_id, $course_id, $duration_months, $total_fees, $student_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Student details updated successfully!";
        header("Location: student_details.php?id=$student_id");
        exit();
    }

    if ($_POST['action'] === 'verify_project') {
        $project_id = (int)$_POST['project_id'];
        $stmt = $conn->prepare("UPDATE student_projects SET status = 'Completed' WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_POST['action'] === 'delete_student') {
        // Delete photo file
        $photo_row = $conn->query("SELECT photo FROM students WHERE id=$student_id")->fetch_assoc();
        if ($photo_row && !empty($photo_row['photo']) && file_exists($photo_row['photo'])) {
            unlink($photo_row['photo']);
        }
        $conn->query("DELETE FROM students WHERE id=$student_id");
        $_SESSION['success'] = "Student deleted successfully.";
        header('Location: students.php');
        exit();
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_photo'])) {
    $upload_dir = __DIR__ . '/uploads/students/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['student_photo'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, GIF allowed.";
        } elseif ($file['size'] > $max_size) {
            $_SESSION['error'] = "File too large. Maximum 5MB allowed.";
        } else {
            $old_photo = $conn->query("SELECT photo FROM students WHERE id=$student_id")->fetch_assoc()['photo'];
            if ($old_photo && file_exists($old_photo)) unlink($old_photo);
            $extension  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename   = 'student_' . $student_id . '_' . time() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            $db_path    = 'uploads/students/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE students SET photo = ? WHERE id = ?");
                $stmt->bind_param("si", $db_path, $student_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Photo uploaded successfully!";
                } else {
                    $_SESSION['error'] = "Failed to save photo to database.";
                    unlink($target_path);
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Failed to upload photo. Check folder permissions.";
            }
        }
    } else {
        $_SESSION['error'] = "Upload error: " . $file['error'];
    }
    header("Location: student_details.php?id=$student_id");
    exit();
}

// Handle delete photo
if (isset($_GET['delete_photo'])) {
    $photo = $conn->query("SELECT photo FROM students WHERE id=$student_id")->fetch_assoc()['photo'];
    if ($photo && file_exists($photo)) unlink($photo);
    $conn->query("UPDATE students SET photo=NULL WHERE id=$student_id");
    $_SESSION['success'] = "Photo deleted!";
    header("Location: student_details.php?id=$student_id");
    exit();
}

// Get student details
$student = $conn->query("
    SELECT s.*, c.name as course_name, cat.id as category_id, cat.name as category_name
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    WHERE s.id = $student_id AND s.status = 'Active'
")->fetch_assoc();

if (!$student) {
    $_SESSION['error'] = "Student not found!";
    header('Location: students.php');
    exit();
}

$categories  = $conn->query("SELECT id, name FROM categories WHERE status = 'Active' ORDER BY name");
$all_courses = $conn->query("SELECT id, category_id, name FROM courses WHERE status = 'Active' ORDER BY name");
$has_photo   = !empty($student['photo']) && file_exists($student['photo']);

// Payment summary
$payment_summary = $conn->query("
    SELECT COALESCE(SUM(amount_paid), 0) as total_paid, COUNT(*) as payment_count
    FROM payments WHERE student_id = $student_id
")->fetch_assoc();
$total_paid     = $payment_summary['total_paid'] ?? 0;
$pending        = $student['total_fees'] - $total_paid;
$paid_this_month = $conn->query("
    SELECT COUNT(*) as count FROM payments
    WHERE student_id = $student_id
    AND YEAR(payment_date) = YEAR(CURDATE())
    AND MONTH(payment_date) = MONTH(CURDATE())
")->fetch_assoc()['count'] > 0;
$is_overdue     = (!$paid_this_month && $pending > 0);
$payment_status = $pending <= 0 ? 'Paid' : ($total_paid > 0 ? 'Partial' : 'Pending');
$paid_pct       = $student['total_fees'] > 0 ? min(100, round(($total_paid / $student['total_fees']) * 100)) : 0;

// Payment history
$payments = $conn->query("
    SELECT p.*, a.full_name as admin_name
    FROM payments p
    JOIN admins a ON p.created_by = a.id
    WHERE p.student_id = $student_id
    ORDER BY p.payment_date DESC, p.created_at DESC
");

// Age
$age = '';
if ($student['birthdate']) {
    $age = (new DateTime())->diff(new DateTime($student['birthdate']))->y;
}

// Course timeline
$course_end_date = date('Y-m-d', strtotime($student['enrollment_date'] . ' + ' . $student['duration_months'] . ' months'));
$total_days      = max(1, (strtotime($course_end_date) - strtotime($student['enrollment_date'])) / 86400);
$days_elapsed    = (strtotime('now') - strtotime($student['enrollment_date'])) / 86400;
$course_progress = min(100, max(0, ($days_elapsed / $total_days) * 100));
$days_remaining  = (strtotime($course_end_date) - strtotime('now')) / 86400;

// Hold days
$hold_days_current = 0;
if ($student['status'] === 'Hold' && !empty($student['hold_start_date'])) {
    $hold_days_current = (new DateTime())->diff(new DateTime($student['hold_start_date']))->days;
}

// Student group & completed topics
$student_group   = $conn->query("
    SELECT sg.id as group_id, sg.group_name
    FROM student_group_members sgm
    JOIN student_groups sg ON sgm.group_id = sg.id
    WHERE sgm.student_id = $student_id LIMIT 1
")->fetch_assoc();
$completed_topics = [];
if ($student_group) {
    $topics_query = $conn->query("
        SELECT ct.topic_name, gtp.start_date, gtp.end_date
        FROM group_topic_progress gtp
        JOIN course_topics ct ON gtp.topic_id = ct.id
        WHERE gtp.group_id = {$student_group['group_id']} AND gtp.status = 'completed'
        ORDER BY ct.order_index ASC
    ");
    while ($row = $topics_query->fetch_assoc()) $completed_topics[] = $row;
}

// Projects
$projects = $conn->query("SELECT * FROM student_projects WHERE student_id = $student_id ORDER BY created_at DESC");

// ── RANK — uses shared ranking_helper (same logic as dashboard & ranking.php) ──
$cm_start     = date('Y-m-01');
$cm_end       = date('Y-m-t');
$ranking      = getMonthlyRanking($conn, $cm_start, $cm_end);
$student_rank = getStudentRank($ranking, $student_id) ?: null;
?>
<?php include 'includes/header.php'; ?>

<style>
/* ══════════════════════════════════════════════════════════
   DESIGN SYSTEM — Deep Violet Enterprise SaaS
   ══════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
  --pp:    #6d28d9;
  --pp-l:  #8b5cf6;
  --pp-d:  #4c1d95;
  --pp-xl: #a78bfa;
  --pp-xs: #ede9fe;
  --ind:   #4f46e5;
  --em:    #059669;
  --em-l:  #10b981;
  --am:    #d97706;
  --am-l:  #f59e0b;
  --rd:    #dc2626;
  --rd-l:  #ef4444;
  --bl:    #2563eb;
  --bg:    #f3f0fb;
  --bg2:   #ede9fe;
  --surf:  #ffffff;
  --surf2: #faf8ff;
  --brd:  rgba(109,40,217,0.09);
  --brd2: rgba(109,40,217,0.18);
  --tx:  #1e1b4b;
  --tx2: #4c1d95;
  --tx3: #7c6db0;
  --tx4: #a39dc4;
  --sh:  0 1px 4px rgba(109,40,217,0.07), 0 4px 16px rgba(109,40,217,0.05);
  --sh2: 0 4px 20px rgba(109,40,217,0.12), 0 1px 4px rgba(109,40,217,0.06);
  --sh3: 0 8px 32px rgba(109,40,217,0.18);
  --r:  14px;
  --r2: 10px;
  --r3: 7px;
  --r4: 5px;
}

*, *::before, *::after { box-sizing: border-box; }
body {
  font-family: 'DM Sans', sans-serif !important;
  background: var(--bg) !important;
  background-image:
    radial-gradient(ellipse 60% 50% at 20% 0%, rgba(109,40,217,0.07) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 80% 100%, rgba(79,70,229,0.06) 0%, transparent 55%) !important;
  color: var(--tx);
  min-height: 100vh;
}

/* ── Flash banners ── */
.flash-banner {
  display: flex; align-items: center; gap: 9px;
  padding: 9px 14px; border-radius: var(--r2);
  font-size: 13px; font-weight: 600; margin-bottom: 10px;
  animation: slideDown .25s ease;
}
.flash-em { background:#ecfdf5; border:1px solid rgba(5,150,105,.25); color:#065f46; }
.flash-rd { background:#fef2f2; border:1px solid rgba(220,38,38,.25); color:#991b1b; }
@keyframes slideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

/* ══════════════════════════════════
   TOP BAR
   ══════════════════════════════════ */
.sd-topbar {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
}
.sd-topbar-left { display: flex; flex-direction: column; gap: 2px; }
.sd-breadcrumb {
  font-size: 11px; color: var(--tx3); display: flex; align-items: center; gap: 5px;
}
.sd-breadcrumb a { color: var(--pp-l); text-decoration: none; font-weight: 500; }
.sd-breadcrumb a:hover { color: var(--pp); }
.sd-breadcrumb-sep { color: var(--tx4); }
.sd-page-title {
  font-size: 16px; font-weight: 800; color: var(--tx);
  display: flex; align-items: center; gap: 8px; margin: 0;
  letter-spacing: -.2px;
}
.sd-topbar-actions { display: flex; gap: 7px; align-items: center; }

/* ══════════════════════════════════
   MAIN SHELL
   ══════════════════════════════════ */
.sd-shell {
  display: grid;
  grid-template-columns: 288px 1fr;
  gap: 14px;
  align-items: start;
}
.sd-left {
  position: sticky;
  top: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sd-right {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

/* ══════════════════════════════════
   CARDS
   ══════════════════════════════════ */
.pc {
  background: var(--surf);
  border: 1px solid var(--brd);
  border-radius: var(--r);
  box-shadow: var(--sh);
  overflow: hidden;
  transition: box-shadow .2s;
}
.pc:hover { box-shadow: var(--sh2); }
.pc-hd {
  padding: 10px 14px;
  border-bottom: 1px solid var(--brd);
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; flex-wrap: wrap;
  background: linear-gradient(to right, #faf8ff, #fff);
}
.pc-hd-title {
  font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  color: var(--pp); display: flex; align-items: center; gap: 6px;
}
.pc-bd { padding: 12px 14px; }

/* ══════════════════════════════════
   PROFILE PANEL
   ══════════════════════════════════ */
.sd-profile-card {
  background: var(--surf);
  border: 1px solid var(--brd);
  border-radius: var(--r);
  box-shadow: var(--sh);
  overflow: hidden;
}
.sd-profile-header {
  background: linear-gradient(145deg, #4c1d95 0%, #6d28d9 50%, #4f46e5 100%);
  padding: 18px 14px 14px;
  text-align: center;
  position: relative;
}
.sd-profile-header::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}

/* ── Avatar wrapper for rank badge positioning ── */
.sd-avatar-wrap {
  position: relative;
  width: 76px;
  margin: 0 auto 10px;
  z-index: 1;
}
.sd-avatar {
  width: 76px; height: 76px; border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.5);
  box-shadow: 0 0 0 3px rgba(255,255,255,0.15), 0 4px 16px rgba(0,0,0,0.25);
  background: rgba(255,255,255,0.15);
  color: #fff; font-size: 28px;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; position: relative;
  cursor: pointer; transition: transform .2s;
}
.sd-avatar:hover { transform: scale(1.04); }
.sd-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

/* ── Rank badge — top-right of avatar ── */
.sd-rank-badge {
  position: absolute;
  top: -6px;
  right: -10px;
  background: linear-gradient(135deg, #5b21b6, #4f46e5);
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
  padding: 4px 7px;
  border-radius: 20px;
  border: 2px solid rgba(255,255,255,0.6);
  box-shadow: 0 2px 8px rgba(0,0,0,0.35);
  white-space: nowrap;
  letter-spacing: .3px;
  display: flex;
  align-items: center;
  gap: 3px;
  z-index: 10;
  animation: rankPop .35s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes rankPop {
  from { transform: scale(0.5); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

.sd-name {
  font-size: 14.5px; font-weight: 800; color: #fff;
  line-height: 1.2; margin-bottom: 2px;
  position: relative; z-index: 1;
}
.sd-code {
  font-size: 11px; color: rgba(255,255,255,.65);
  margin-bottom: 9px; position: relative; z-index: 1;
  font-family: 'JetBrains Mono', monospace;
}
.sd-status-row {
  display: flex; gap: 4px; justify-content: center;
  flex-wrap: wrap; margin-bottom: 10px;
  position: relative; z-index: 1;
}
.sd-photo-btns {
  display: flex; gap: 5px; justify-content: center;
  position: relative; z-index: 1;
}

/* ── Info rows ── */
.sd-info-body { padding: 10px 14px; }
.info-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 5px 0; border-bottom: 1px solid var(--brd);
  font-size: 12px; gap: 8px;
}
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-row label {
  color: var(--tx3); font-weight: 600;
  display: flex; align-items: center; gap: 5px;
  white-space: nowrap; flex-shrink: 0; font-size: 11.5px;
}
.info-row label i { width: 12px; font-size: 10px; color: var(--pp-xl); }
.info-row .val { font-weight: 700; text-align: right; word-break: break-word; font-size: 12px; }

/* ── Remaining days inline under end date ── */
.end-date-block {
  display: flex; flex-direction: column; align-items: flex-end; gap: 3px;
}
.end-date-text {
  font-weight: 700; font-size: 12px; color: var(--tx);
}

/* ── Fees mini bar ── */
.fees-mini {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 6px; padding: 10px 14px;
  border-top: 1px solid var(--brd);
  background: var(--surf2);
}
.fee-cell { text-align: center; }
.fee-cell .fc-lbl { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--tx4); margin-bottom: 2px; }
.fee-cell .fc-val { font-size: 13px; font-weight: 800; line-height: 1; }
.fc-total { color: var(--pp); }
.fc-paid  { color: var(--em); }
.fc-pend  { color: var(--rd); }
.fees-prog-wrap { padding: 6px 14px 10px; background: var(--surf2); }
.fees-prog-bar { height: 4px; background: rgba(109,40,217,.1); border-radius: 99px; overflow: hidden; }
.fees-prog-fill { height: 100%; border-radius: 99px; transition: width .6s ease; }

/* ── Portal info strip ── */
.portal-strip {
  padding: 8px 14px;
  border-top: 1px solid var(--brd);
  background: var(--surf2);
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.portal-username { font-size: 11.5px; font-weight: 700; color: var(--tx2); display: flex; align-items: center; gap: 5px; }

/* ══════════════════════════════════
   BADGES
   ══════════════════════════════════ */
.bd {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 99px;
  font-size: 10.5px; font-weight: 700; line-height: 1.5;
  white-space: nowrap;
}
.bd-pp { background:#ede9fe; color:var(--pp);   border:1px solid rgba(109,40,217,0.2); }
.bd-em { background:#ecfdf5; color:#065f46;     border:1px solid rgba(5,150,105,0.25); }
.bd-rd { background:#fef2f2; color:#991b1b;     border:1px solid rgba(220,38,38,0.25); }
.bd-am { background:#fffbeb; color:#92400e;     border:1px solid rgba(217,119,6,0.28); }
.bd-bl { background:#eff6ff; color:#1e40af;     border:1px solid rgba(37,99,235,0.25); }
.bd-gy { background:#f9fafb; color:#374151;     border:1px solid #e5e7eb; }
.bd-cy { background:#ecfeff; color:#0e7490;     border:1px solid rgba(6,182,212,0.25); }
.bd-wh { background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.3); }

/* ══════════════════════════════════
   BUTTONS
   ══════════════════════════════════ */
.btn-pp {
  background: linear-gradient(135deg, var(--pp), var(--ind));
  color: #fff; border: none; border-radius: var(--r3);
  padding: 7px 14px; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-pp:hover { box-shadow: 0 4px 16px rgba(109,40,217,.38); transform: translateY(-1px); color: #fff; }
.btn-em {
  background: linear-gradient(135deg, var(--em), var(--em-l));
  color: #fff; border: none; border-radius: var(--r3);
  padding: 7px 14px; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-em:hover { box-shadow: 0 4px 14px rgba(5,150,105,.35); transform: translateY(-1px); color: #fff; }
.btn-rd {
  background: linear-gradient(135deg, var(--rd), var(--rd-l));
  color: #fff; border: none; border-radius: var(--r3);
  padding: 7px 14px; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-rd:hover { box-shadow: 0 4px 14px rgba(220,38,38,.35); transform: translateY(-1px); color: #fff; }
.btn-am {
  background: linear-gradient(135deg, var(--am), var(--am-l));
  color: #fff; border: none; border-radius: var(--r3);
  padding: 7px 14px; font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-am:hover { color: #fff; box-shadow: 0 4px 12px rgba(217,119,6,.3); transform: translateY(-1px); }
.btn-ghost {
  background: #fff; color: var(--pp);
  border: 1px solid var(--brd2); border-radius: var(--r3);
  padding: 6px 12px; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 5px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-ghost:hover { background: var(--bg2); color: var(--pp-d); border-color: var(--pp-l); }
.btn-ghost-wh {
  background: rgba(255,255,255,.15); color: #fff;
  border: 1px solid rgba(255,255,255,.3); border-radius: var(--r3);
  padding: 4px 10px; font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all .18s;
  display: inline-flex; align-items: center; gap: 5px;
  text-decoration: none; font-family: 'DM Sans', sans-serif;
}
.btn-ghost-wh:hover { background: rgba(255,255,255,.25); color: #fff; }
.btn-icon {
  width: 28px; height: 28px; border-radius: 7px;
  display: inline-flex; align-items: center; justify-content: center;
  border: none; cursor: pointer; font-size: 11px;
  transition: all .18s; flex-shrink: 0;
}
.btn-manage {
  width: 100%;
  background: linear-gradient(135deg, var(--pp), var(--ind));
  color: #fff; border: none; border-radius: var(--r3);
  padding: 9px 14px; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all .2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-family: 'DM Sans', sans-serif; letter-spacing: .1px;
}
.btn-manage:hover { box-shadow: 0 6px 20px rgba(109,40,217,.4); transform: translateY(-1px); }

/* ══════════════════════════════════
   PROGRESS BARS
   ══════════════════════════════════ */
.prog-wrap { height: 4px; background: rgba(109,40,217,.1); border-radius: 99px; overflow: hidden; }
.prog-fill { height: 100%; border-radius: 99px; }
.pf-pp  { background: linear-gradient(90deg, var(--pp), var(--pp-l)); }
.pf-em  { background: linear-gradient(90deg, var(--em), var(--em-l)); }
.pf-rd  { background: linear-gradient(90deg, var(--rd), var(--rd-l)); }

/* ══════════════════════════════════
   ATTENDANCE — 4-box premium layout
   ══════════════════════════════════ */
.att-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-bottom: 10px;
}
.att-stat-box {
  background: var(--surf2);
  border: 1px solid var(--brd);
  border-radius: var(--r2);
  padding: 14px 8px 12px;
  text-align: center;
  transition: all .18s;
  box-shadow: 0 1px 4px rgba(109,40,217,0.05);
}
.att-stat-box:hover {
  border-color: var(--pp-xl);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(109,40,217,0.1);
}
/* ── BIGGER, BOLDER stat numbers ── */
.att-val {
  font-size: 28px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.5px;
  font-family: 'DM Sans', sans-serif;
}
.att-lbl {
  font-size: 9px;
  font-weight: 700;
  color: var(--tx3);
  text-transform: uppercase;
  letter-spacing: .6px;
  margin-top: 5px;
}

/* ── Filter buttons ── */
.att-filter-grp {
  display: flex; border: 1px solid var(--brd2);
  border-radius: var(--r3); overflow: hidden;
}
.aff-btn {
  padding: 4px 10px; font-size: 11.5px; font-weight: 600;
  border: none; background: #fff; color: var(--tx3);
  cursor: pointer; transition: all .15s;
  font-family: 'DM Sans', sans-serif;
}
.aff-btn.active, .aff-btn:hover { background: var(--pp); color: #fff; }
#customDayArea { display: none; align-items: center; gap: 4px; }

/* ══════════════════════════════════
   TABS
   ══════════════════════════════════ */
.sd-tabs {
  display: flex; gap: 3px; padding: 5px;
  background: var(--surf2); border-radius: var(--r2);
  border: 1px solid var(--brd);
}
.sd-tab {
  flex: 1; padding: 6px 7px; border-radius: var(--r3);
  font-size: 11.5px; font-weight: 600; color: var(--tx3);
  cursor: pointer; border: none; background: transparent;
  transition: all .18s; text-align: center;
  display: flex; align-items: center; justify-content: center; gap: 4px;
  white-space: nowrap; font-family: 'DM Sans', sans-serif;
}
.sd-tab.active {
  background: #fff; color: var(--pp);
  box-shadow: 0 2px 8px rgba(109,40,217,.1);
}
.sd-tab-pane { display: none; }
.sd-tab-pane.active { display: block; }

/* ══════════════════════════════════
   TABLE
   ══════════════════════════════════ */
.sd-table { width: 100%; border-collapse: collapse; font-size: 12.5px; font-family: 'DM Sans', sans-serif; }
.sd-table th {
  background: var(--surf2); color: var(--pp);
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; padding: 8px 11px; text-align: left; white-space: nowrap;
}
.sd-table td { padding: 8px 11px; border-bottom: 1px solid var(--brd); vertical-align: middle; }
.sd-table tbody tr:last-child td { border-bottom: none; }
.sd-table tbody tr:hover td { background: #faf8ff; }

/* ══════════════════════════════════
   TOPIC + PROJECT ITEMS
   ══════════════════════════════════ */
.topic-item {
  display: flex; align-items: flex-start; gap: 9px;
  padding: 7px 10px; border: 1px solid var(--brd);
  border-radius: var(--r3); background: var(--surf2); margin-bottom: 5px;
}
.topic-item:last-child { margin-bottom: 0; }
.topic-num {
  width: 22px; height: 22px; border-radius: 6px;
  background: linear-gradient(135deg, var(--pp), var(--ind));
  color: #fff; font-size: 10px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.topic-name { font-size: 12.5px; font-weight: 700; color: var(--tx); }
.topic-dates { font-size: 11px; color: var(--tx3); margin-top: 1px; }
.proj-item {
  display: flex; align-items: center; gap: 9px;
  padding: 7px 10px; border: 1px solid var(--brd);
  border-radius: var(--r3); background: #fff; margin-bottom: 5px;
  transition: all .18s;
}
.proj-item:last-child { margin-bottom: 0; }
.proj-item:hover { border-color: var(--pp-xl); background: #faf8ff; }
.proj-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.pd-em { background: var(--em); }
.pd-pp { background: var(--pp); }
.pd-am { background: var(--am); }
.pd-gy { background: #9ca3af; }

/* ══════════════════════════════════
   EMPTY STATE
   ══════════════════════════════════ */
.empty-st {
  text-align: center; padding: 18px 12px;
  color: var(--tx3); font-size: 12.5px;
}
.empty-st i { font-size: 24px; color: #c4b5fd; display: block; margin-bottom: 6px; }

/* ══════════════════════════════════
   ALERTS
   ══════════════════════════════════ */
.al-pp { background:#f5f3ff; border:1px solid rgba(109,40,217,.2); border-radius:var(--r2); padding:9px 12px; font-size:12.5px; color:var(--pp-d); }
.al-em { background:#ecfdf5; border:1px solid rgba(5,150,105,.2);  border-radius:var(--r2); padding:9px 12px; font-size:12.5px; color:#065f46; }
.al-rd { background:#fef2f2; border:1px solid rgba(220,38,38,.2);  border-radius:var(--r2); padding:9px 12px; font-size:12.5px; color:#991b1b; }
.al-am { background:#fffbeb; border:1px solid rgba(217,119,6,.22); border-radius:var(--r2); padding:9px 12px; font-size:12.5px; color:#92400e; }

/* Hold strip */
.hold-strip { background:#fffbeb; border:1px solid rgba(217,119,6,.28); border-radius:var(--r3); padding:8px 11px; }
.hold-strip h6 { font-size:11.5px; font-weight:700; color:#92400e; margin:0 0 2px; }
.hold-strip p  { font-size:11px; color:#78350f; margin:0; }

/* ══════════════════════════════════
   MODALS
   ══════════════════════════════════ */
.modal-content  { border-radius: var(--r) !important; border: 1px solid var(--brd) !important; font-family: 'DM Sans', sans-serif !important; }
.modal-header   { border-bottom: 1px solid var(--brd) !important; padding: 13px 17px !important; }
.modal-title    { font-size: 14px !important; font-weight: 700 !important; color: var(--tx); font-family: 'DM Sans', sans-serif !important; }
.modal-body     { padding: 15px 17px !important; }
.modal-footer   { border-top: 1px solid var(--brd) !important; padding: 11px 17px !important; }
.form-control, .form-select { border-radius: var(--r3) !important; border-color: var(--brd2) !important; font-size: 13px !important; font-family: 'DM Sans', sans-serif !important; }
.form-control:focus, .form-select:focus { border-color: var(--pp) !important; box-shadow: 0 0 0 3px rgba(109,40,217,.1) !important; }
.form-label { font-size: 12px !important; font-weight: 600 !important; color: var(--tx3) !important; margin-bottom: 4px !important; }

/* ── Manage modal ── */
.manage-actions { display: flex; flex-direction: column; gap: 6px; }
.manage-action-btn {
  display: flex; align-items: center; gap: 11px;
  padding: 10px 13px; border-radius: var(--r2);
  border: 1px solid var(--brd); background: var(--surf2);
  cursor: pointer; transition: all .18s; text-decoration: none;
  font-size: 13px; font-weight: 600; color: var(--tx);
  font-family: 'DM Sans', sans-serif; width: 100%; text-align: left;
}
.manage-action-btn:hover { border-color: var(--pp-xl); background: var(--pp-xs); color: var(--pp); transform: translateX(2px); }
.manage-action-btn .mab-icon {
  width: 32px; height: 32px; border-radius: var(--r3);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; flex-shrink: 0;
}
.mab-pp  { background: #ede9fe; color: var(--pp); }
.mab-em  { background: #ecfdf5; color: var(--em); }
.mab-am  { background: #fffbeb; color: var(--am); }
.mab-bl  { background: #eff6ff; color: var(--bl); }
.mab-cy  { background: #ecfeff; color: #0e7490; }
.mab-gy  { background: #f9fafb; color: #374151; }
.mab-rd  { background: #fef2f2; color: var(--rd); }
.manage-divider { border: none; border-top: 1px solid var(--brd); margin: 5px 0; }
.manage-action-btn.danger-action { border-color: rgba(220,38,38,.25); background: #fef2f2; color: var(--rd); }
.manage-action-btn.danger-action:hover { background: #fee2e2; border-color: rgba(220,38,38,.45); }

/* ══════════════════════════════════
   ATTENDANCE DETAILS LIST
   ══════════════════════════════════ */
.att-details-section {
  margin-top: 14px;
  border-top: 1px solid var(--brd);
  padding-top: 12px;
}
.att-details-hd {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px; flex-wrap: wrap; gap: 6px;
}
.att-details-title {
  font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  color: var(--pp); display: flex; align-items: center; gap: 6px;
}
.att-details-count {
  font-size: 10px; font-weight: 600; color: var(--tx3);
}
.att-details-table {
  width: 100%; border-collapse: collapse;
  font-size: 12.5px; font-family: 'DM Sans', sans-serif;
}
.att-details-table th {
  background: var(--surf2); color: var(--pp);
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; padding: 8px 10px; text-align: left;
  white-space: nowrap; border-bottom: 1px solid var(--brd2);
}
.att-details-table td {
  padding: 8px 10px; border-bottom: 1px solid var(--brd);
  vertical-align: middle; font-size: 12.5px;
}
.att-details-table tbody tr:last-child td { border-bottom: none; }
.att-details-table tbody tr {
  transition: background .15s;
}
.att-details-table tbody tr:hover td { background: #faf8ff; }
.att-details-table .att-date-cell {
  font-weight: 700; color: var(--tx); white-space: nowrap;
}
.att-details-table .att-day-cell {
  font-weight: 600; color: var(--tx3); font-size: 11.5px;
}
.att-details-table .att-time-cell {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px; font-weight: 600; color: var(--tx2);
}
.att-details-table .att-time-cell.no-time {
  color: var(--tx4); font-weight: 500;
}

/* Status badges in details list */
.att-status-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 9px; border-radius: 99px;
  font-size: 10.5px; font-weight: 700; line-height: 1.5;
  white-space: nowrap;
}
.att-status-badge.st-present {
  background: #ecfdf5; color: #065f46; border: 1px solid rgba(5,150,105,0.25);
}
.att-status-badge.st-absent {
  background: #fef2f2; color: #991b1b; border: 1px solid rgba(220,38,38,0.25);
}
.att-status-badge.st-sunday {
  background: #fffbeb; color: #92400e; border: 1px solid rgba(217,119,6,0.28);
}

/* Mobile attendance cards */
.att-card-list { display: none; }
.att-card-item {
  background: var(--surf2); border: 1px solid var(--brd);
  border-radius: var(--r2); padding: 10px 12px; margin-bottom: 6px;
  transition: all .18s;
}
.att-card-item:last-child { margin-bottom: 0; }
.att-card-item:hover {
  border-color: var(--pp-xl);
  box-shadow: 0 2px 8px rgba(109,40,217,0.08);
}
.att-card-top {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 6px;
}
.att-card-date {
  font-size: 12.5px; font-weight: 700; color: var(--tx);
}
.att-card-day {
  font-size: 11px; font-weight: 600; color: var(--tx3); margin-left: 6px;
}
.att-card-times {
  display: flex; gap: 14px;
}
.att-card-time-block {
  display: flex; flex-direction: column; gap: 1px;
}
.att-card-time-label {
  font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--tx4);
}
.att-card-time-val {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px; font-weight: 600; color: var(--tx2);
}
.att-card-time-val.no-time { color: var(--tx4); font-weight: 500; }

/* Empty state for details list */
.att-details-empty {
  text-align: center; padding: 18px 12px;
  color: var(--tx3); font-size: 12.5px;
}
.att-details-empty i {
  font-size: 20px; color: #c4b5fd; display: block; margin-bottom: 6px;
}

/* Custom date range picker */
.att-custom-daterange {
  display: none; align-items: center; gap: 6px;
  flex-wrap: wrap; margin-top: 0;
}
.att-custom-daterange label {
  font-size: 10.5px; font-weight: 700; color: var(--tx3);
  text-transform: uppercase; letter-spacing: .4px;
}
.att-custom-daterange input[type="date"] {
  padding: 4px 8px; font-size: 12px; font-weight: 600;
  border: 1px solid var(--brd2); border-radius: var(--r4);
  color: var(--tx); font-family: 'DM Sans', sans-serif;
  background: #fff; outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.att-custom-daterange input[type="date"]:focus {
  border-color: var(--pp); box-shadow: 0 0 0 3px rgba(109,40,217,.1);
}
.att-date-arrow {
  font-size: 11px; color: var(--tx4); font-weight: 700;
}

/* ══════════════════════════════════
   RESPONSIVE
   ══════════════════════════════════ */
@media (max-width: 1024px) {
  .sd-shell { grid-template-columns: 260px 1fr; }
  .att-stat-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 768px) {
  .sd-shell { grid-template-columns: 1fr; }
  .sd-left { position: static; }
  .att-stat-grid { grid-template-columns: repeat(2,1fr); }
  /* Switch to card layout on mobile */
  .att-details-table-wrap { display: none !important; }
  .att-card-list { display: block !important; }
}
@media (max-width: 480px) {
  .att-stat-grid { grid-template-columns: 1fr 1fr; }
  .att-card-times { gap: 10px; }
  .att-custom-daterange { gap: 4px; }
  .att-custom-daterange input[type="date"] { font-size: 11px; padding: 3px 6px; }
}
</style>

<!-- ══ Flash Messages ══ -->
<?php if (isset($_SESSION['success'])): ?>
<div class="flash-banner flash-em">
  <i class="fas fa-check-circle"></i>
  <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="flash-banner flash-rd">
  <i class="fas fa-exclamation-circle"></i>
  <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- ══ Top Bar ══ -->
<div class="sd-topbar">
  <div class="sd-topbar-left">
    <div class="sd-breadcrumb">
      <a href="students.php"><i class="fas fa-users" style="font-size:9px;"></i> Students</a>
      <span class="sd-breadcrumb-sep">›</span>
      <span><?php echo htmlspecialchars($student['full_name']); ?></span>
    </div>
    <h4 class="sd-page-title">
      <i class="fas fa-id-card-alt" style="color:var(--pp-l);font-size:14px;"></i>
      Student Profile
      <?php if ($student['status'] === 'Hold'): ?>
        <span class="bd bd-am"><i class="fas fa-pause-circle"></i> On Hold</span>
      <?php elseif ($is_overdue): ?>
        <span class="bd bd-rd"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
      <?php else: ?>
        <span class="bd bd-em"><i class="fas fa-circle" style="font-size:5px;"></i> Active</span>
      <?php endif; ?>
    </h4>
  </div>
  <div class="sd-topbar-actions">
    <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#editStudentModal">
      <i class="fas fa-pencil-alt"></i> Edit
    </button>
    <button class="btn-pp" data-bs-toggle="modal" data-bs-target="#manageStudentModal">
      <i class="fas fa-sliders-h"></i> Manage Student
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════
     MAIN 2-COLUMN SHELL
     ════════════════════════════════════════════ -->
<div class="sd-shell">

<!-- ━━━━━━━━━━━━━━━━━━━━━ LEFT PANEL (STICKY) ━━━━━━━━━━━━━━━━━━━━━ -->
<div class="sd-left">

  <div class="sd-profile-card">

    <!-- Dark header: avatar with rank badge + name -->
    <div class="sd-profile-header">

      <!-- ① Avatar wrapper with rank badge -->
      <div class="sd-avatar-wrap" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal" title="Click to change photo">

        <!-- Rank badge — top-right corner of avatar -->
        <?php if ($student_rank): ?>
          <div class="sd-rank-badge">
            <?php if ($student_rank === 1): ?>
              <i class="fas fa-crown" style="font-size:8px;color:#fde68a;"></i>
            <?php elseif ($student_rank <= 3): ?>
              <i class="fas fa-medal" style="font-size:8px;color:#fde68a;"></i>
            <?php else: ?>
              <i class="fas fa-hashtag" style="font-size:8px;opacity:.8;"></i>
            <?php endif; ?>
            <?php echo $student_rank; ?>
          </div>
        <?php endif; ?>

        <div class="sd-avatar">
          <?php if ($has_photo): ?>
            <img src="<?php echo htmlspecialchars($student['photo']); ?>" alt="photo">
          <?php else: ?>
            <i class="fas fa-user-graduate"></i>
          <?php endif; ?>
        </div>
      </div>

      <div class="sd-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
      <div class="sd-code"><?php echo $student['student_code']; ?><?php if ($age): ?> · <?php echo $age; ?> yrs<?php endif; ?></div>

      <!-- Status badges — single occurrence only -->
      <div class="sd-status-row">
        <?php if ($student['status'] === 'Hold'): ?>
          <span class="bd bd-am"><i class="fas fa-pause-circle"></i> Hold</span>
        <?php else: ?>
          <span class="bd bd-wh"><i class="fas fa-circle" style="font-size:5px;"></i> Active</span>
        <?php endif; ?>
        <?php if ($is_overdue): ?>
          <span class="bd bd-rd"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
        <?php endif; ?>
        <?php if (!empty($student['batch'])): ?>
          <span class="bd bd-wh">
            <i class="fas <?php echo $student['batch'] === 'Morning' ? 'fa-sun' : 'fa-moon'; ?>"></i>
            <?php echo $student['batch']; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Photo action buttons -->
      <div class="sd-photo-btns">
        <button class="btn-ghost-wh" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
          <i class="fas fa-camera"></i> <?php echo $has_photo ? 'Change Photo' : 'Add Photo'; ?>
        </button>
        <?php if ($has_photo): ?>
          <a href="?id=<?php echo $student_id; ?>&delete_photo=1"
             class="btn-ghost-wh" style="color:rgba(255,180,180,0.9);"
             onclick="return confirm('Delete photo?')">
            <i class="fas fa-trash"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Info rows — NO category row -->
    <div class="sd-info-body">
      <div class="info-row">
        <label><i class="fas fa-book-open"></i> Course</label>
        <span class="val" style="max-width:160px;font-size:11.5px;"><?php echo htmlspecialchars($student['course_name']); ?></span>
      </div>
      <div class="info-row">
        <label><i class="fas fa-hourglass-half"></i> Duration</label>
        <span class="val"><?php echo $student['duration_months']; ?> months</span>
      </div>
      <div class="info-row">
        <label><i class="fas fa-calendar-plus"></i> Enrolled</label>
        <span class="val"><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></span>
      </div>

      <!-- ③ End Date + Remaining badge — separate rows -->
      <div class="info-row">
        <label><i class="fas fa-flag-checkered"></i> End Date</label>
        <span class="end-date-text"><?php echo date('d M Y', strtotime($course_end_date)); ?></span>
      </div>
      <div class="info-row" style="border-bottom:1px solid var(--brd);">
        <label><i class="fas fa-clock"></i> Remaining</label>
        <?php
          if ($days_remaining < 0):
            $expired_days = abs(round($days_remaining));
        ?>
          <span class="bd bd-rd">
            <i class="fas fa-exclamation-circle"></i>
            Expired <?php echo $expired_days; ?> day<?php echo $expired_days != 1 ? 's' : ''; ?> ago
          </span>
        <?php elseif ($days_remaining < 30): ?>
          <span class="bd bd-am">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo round($days_remaining); ?> days left
          </span>
        <?php else: ?>
          <span class="bd bd-em">
            <i class="fas fa-check-circle"></i>
            <?php echo round($days_remaining); ?> days left
          </span>
        <?php endif; ?>
      </div>

      <?php if ($student['phone']): ?>
      <div class="info-row">
        <label><i class="fas fa-phone-alt"></i> Phone</label>
        <span class="val"><?php echo htmlspecialchars($student['phone']); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($student['email']): ?>
      <div class="info-row" style="border-bottom:none;">
        <label><i class="fas fa-envelope"></i> Email</label>
        <span class="val" style="font-size:11px;"><?php echo htmlspecialchars($student['email']); ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Course progress -->
    <div style="padding:4px 14px 8px; border-top:1px solid var(--brd);">
      <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--tx3);margin-bottom:3px;">
        <span>Course progress</span><span><?php echo round($course_progress); ?>%</span>
      </div>
      <div class="prog-wrap">
        <div class="prog-fill pf-pp" style="width:<?php echo round($course_progress); ?>%;"></div>
      </div>
    </div>

    <!-- Fees mini summary -->
    <div class="fees-mini">
      <div class="fee-cell">
        <div class="fc-lbl">Total</div>
        <div class="fc-val fc-total">₹<?php echo number_format($student['total_fees'], 0); ?></div>
      </div>
      <div class="fee-cell">
        <div class="fc-lbl">Paid</div>
        <div class="fc-val fc-paid">₹<?php echo number_format($total_paid, 0); ?></div>
      </div>
      <div class="fee-cell">
        <div class="fc-lbl">Pending</div>
        <div class="fc-val fc-pend">₹<?php echo number_format($pending, 0); ?></div>
      </div>
    </div>
    <div class="fees-prog-wrap">
      <div style="display:flex;justify-content:space-between;font-size:9.5px;color:var(--tx3);margin-bottom:3px;">
        <span>Payment <?php echo $paid_pct; ?>% paid ·
          <span class="bd bd-<?php echo $payment_status === 'Paid' ? 'em' : ($payment_status === 'Partial' ? 'am' : 'rd'); ?>" style="font-size:9px;padding:1px 6px;"><?php echo $payment_status; ?></span>
        </span>
        <span><?php echo $payment_summary['payment_count']; ?> txn</span>
      </div>
      <div class="fees-prog-bar">
        <div class="fees-prog-fill <?php echo $paid_pct >= 100 ? 'pf-em' : ($paid_pct > 50 ? 'pf-pp' : 'pf-rd'); ?>" style="width:<?php echo $paid_pct; ?>%;"></div>
      </div>
    </div>

    <!-- Portal strip -->
    <div class="portal-strip">
      <?php if ($student['login_enabled']): ?>
        <div class="portal-username">
          <i class="fas fa-key" style="color:var(--pp-l);font-size:10px;"></i>
          <?php echo htmlspecialchars($student['username']); ?>
          <?php if (!empty($student['batch'])): ?>
            &nbsp;·&nbsp;
            <span class="bd bd-<?php echo $student['batch'] === 'Morning' ? 'am' : 'bl'; ?>" style="font-size:9px;">
              <i class="fas <?php echo $student['batch'] === 'Morning' ? 'fa-sun' : 'fa-moon'; ?>"></i>
              <?php echo $student['batch']; ?>
            </span>
          <?php endif; ?>
        </div>
        <span class="bd bd-em" style="font-size:9.5px;"><i class="fas fa-circle" style="font-size:5px;"></i> Portal On</span>
      <?php else: ?>
        <div class="portal-username" style="color:var(--tx3);">
          <i class="fas fa-lock" style="color:var(--rd-l);font-size:10px;"></i> No portal access
        </div>
        <span class="bd bd-rd" style="font-size:9.5px;">Off</span>
      <?php endif; ?>
    </div>

    <!-- Batch switch -->
    <?php if ($student['login_enabled'] && !empty($student['batch'])): ?>
    <div style="padding:7px 14px;border-top:1px solid var(--brd);">
      <form method="POST" style="margin:0;display:flex;align-items:center;justify-content:space-between;">
        <input type="hidden" name="toggle_batch" value="1">
        <input type="hidden" name="current_batch" value="<?php echo $student['batch']; ?>">
        <span style="font-size:11px;color:var(--tx3);">Batch</span>
        <button type="submit" class="btn-ghost" style="font-size:11px;padding:3px 9px;"
          onclick="return confirm('Switch to <?php echo $student['batch'] === 'Morning' ? 'Evening' : 'Morning'; ?> batch?')">
          <i class="fas fa-exchange-alt"></i> Switch to <?php echo $student['batch'] === 'Morning' ? 'Evening' : 'Morning'; ?>
        </button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Hold info -->
    <?php if ($student['status'] === 'Hold'): ?>
    <div style="padding:8px 14px;border-top:1px solid var(--brd);">
      <div class="hold-strip">
        <h6><i class="fas fa-pause-circle"></i> On Hold — <?php echo $hold_days_current; ?> days</h6>
        <?php if (!empty($student['hold_reason'])): ?>
          <p><?php echo htmlspecialchars($student['hold_reason']); ?></p>
        <?php endif; ?>
      </div>
      <?php if ($student['total_hold_days'] > 0): ?>
      <div style="font-size:10px;color:var(--tx3);margin-top:5px;">
        <i class="fas fa-history"></i> Total hold history: <strong><?php echo $student['total_hold_days']; ?> days</strong>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Manage button -->
    <div style="padding:10px 14px;border-top:1px solid var(--brd);">
      <button class="btn-manage" data-bs-toggle="modal" data-bs-target="#manageStudentModal">
        <i class="fas fa-sliders-h"></i> Manage Student
      </button>
    </div>

  </div><!-- /sd-profile-card -->
</div><!-- /sd-left -->


<!-- ━━━━━━━━━━━━━━━━━━━━━ RIGHT PANEL ━━━━━━━━━━━━━━━━━━━━━ -->
<div class="sd-right">

  <!-- ══ ATTENDANCE CARD ══ -->
  <div class="pc">
    <div class="pc-hd" style="flex-wrap:wrap;gap:7px;">
      <div class="pc-hd-title"><i class="fas fa-chart-bar"></i> Attendance Report</div>
      <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
        <div class="att-filter-grp">
          <button class="aff-btn" id="aff7"  onclick="loadAtt(7,this)">7d</button>
          <button class="aff-btn" id="aff15" onclick="loadAtt(15,this)">15d</button>
          <button class="aff-btn active" id="aff30" onclick="loadAtt(30,this)">30d</button>
          <button class="aff-btn" id="affC"  onclick="toggleCustomAtt(this)">Custom</button>
        </div>
        <div id="customDayArea" class="att-custom-daterange">
          <label>From</label>
          <input type="date" id="customDateFrom">
          <span class="att-date-arrow">→</span>
          <label>To</label>
          <input type="date" id="customDateTo">
          <button onclick="applyCustomDateRange()" class="btn-pp" style="padding:4px 10px;font-size:11px;"><i class="fas fa-check"></i> Apply</button>
          <button onclick="closeCustomAtt()" class="btn-ghost" style="padding:4px 8px;font-size:11px;"><i class="fas fa-times"></i></button>
        </div>
      </div>
    </div>
    <div class="pc-bd" style="padding-bottom:10px;">

      <!-- ① 4 premium stat boxes only — Working Days / Longest rows REMOVED -->
      <div class="att-stat-grid">
        <div class="att-stat-box">
          <div class="att-val" id="attPresent" style="color:var(--em);">—</div>
          <div class="att-lbl">Present</div>
        </div>
        <div class="att-stat-box">
          <div class="att-val" id="attAbsent" style="color:var(--rd);">—</div>
          <div class="att-lbl">Absent</div>
        </div>
        <div class="att-stat-box">
          <div class="att-val" id="attRate" style="color:var(--pp);">—</div>
          <div class="att-lbl">Rate </div>
        </div>
        <div class="att-stat-box">
          <div class="att-val" id="attStreak" style="color:var(--am);">—</div>
          <div class="att-lbl">Streak</div>
        </div>
      </div>

      <!-- Period info -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;flex-wrap:wrap;gap:4px;">
        <span id="attPeriodInfo" style="font-size:10.5px;color:var(--tx3);"></span>
        <span style="font-size:10px;color:var(--am);"><i class="fas fa-sun"></i> Sunday = amber, not counted</span>
      </div>

      <!-- Loading indicator -->
      <div id="attLoading" style="text-align:center;padding:14px;display:none;">
        <div class="spinner-border" style="width:1.3rem;height:1.3rem;border-color:var(--pp-xl);border-right-color:transparent;" role="status"></div>
      </div>

      <!-- Chart -->
      <div id="attChartWrap" style="position:relative;height:140px;">
        <canvas id="attendanceChart"></canvas>
      </div>

      <!-- ── Attendance Details List ── -->
      <div class="att-details-section" id="attDetailsSection">
        <div class="att-details-hd">
          <div class="att-details-title"><i class="fas fa-list-ul"></i> Attendance Details</div>
          <div class="att-details-count" id="attDetailsCount"></div>
        </div>

        <!-- Desktop: table view -->
        <div class="att-details-table-wrap" style="overflow-x:auto;">
          <table class="att-details-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
              </tr>
            </thead>
            <tbody id="attDetailsBody"></tbody>
          </table>
        </div>

        <!-- Mobile: card view -->
        <div class="att-card-list" id="attCardList"></div>
      </div>

    </div>
  </div>

  <!-- ══ TABS CARD ══ -->
  <div class="pc">
    <div class="pc-bd" style="padding-bottom:0;">
      <div class="sd-tabs">
        <button class="sd-tab active" onclick="sdTab('topics',this)">
          <i class="fas fa-check-circle"></i> Topics (<?php echo count($completed_topics); ?>)
        </button>
        <button class="sd-tab" onclick="sdTab('projects',this)">
          <i class="fas fa-layer-group"></i> Projects (<?php echo $projects->num_rows; ?>)
        </button>
        <button class="sd-tab" onclick="sdTab('points',this)">
          <i class="fas fa-star"></i> Points
        </button>
        <button class="sd-tab" onclick="sdTab('payments',this)">
          <i class="fas fa-receipt"></i> Payments
        </button>
      </div>
    </div>

    <!-- TOPICS PANE -->
    <div id="paneTopics" class="sd-tab-pane active">
      <div class="pc-bd" style="padding-top:11px;">
        <?php if (!empty($completed_topics)): ?>
          <?php foreach ($completed_topics as $i => $t): ?>
          <div class="topic-item">
            <div class="topic-num"><?php echo $i + 1; ?></div>
            <div>
              <div class="topic-name"><?php echo htmlspecialchars($t['topic_name']); ?></div>
              <div class="topic-dates">
                <i class="fas fa-calendar" style="color:var(--pp-xl);font-size:9px;"></i>
                <?php echo date('d M Y', strtotime($t['start_date'])); ?> →
                <?php echo date('d M Y', strtotime($t['end_date'])); ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-st"><i class="fas fa-list-check"></i> No completed topics yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PROJECTS PANE -->
    <div id="paneProjects" class="sd-tab-pane">
      <div class="pc-bd" style="padding-top:10px;">
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button class="btn-pp" style="font-size:12px;padding:5px 12px;" onclick="showAddProjectModal()">
            <i class="fas fa-plus"></i> Add Project
          </button>
        </div>
        <?php
          $projects->data_seek(0);
          if ($projects->num_rows > 0):
            while ($proj = $projects->fetch_assoc()):
              $dot_cls = $proj['status'] === 'Completed' ? 'pd-em'
                : ($proj['status'] === 'In Progress' ? 'pd-pp'
                : ($proj['status'] === 'On Hold' ? 'pd-am' : 'pd-gy'));
        ?>
        <div class="proj-item">
          <div class="proj-dot <?php echo $dot_cls; ?>"></div>
          <div style="flex:1;min-width:0;">
            <a href="<?php echo htmlspecialchars($proj['project_link'] ?? '#'); ?>" target="_blank"
               style="font-size:12.5px;font-weight:700;color:var(--tx);text-decoration:none;">
              <?php echo htmlspecialchars($proj['project_name']); ?>
              <?php if (!empty($proj['project_link'])): ?>
                <i class="fas fa-external-link-alt" style="font-size:9px;color:var(--pp-l);margin-left:3px;"></i>
              <?php endif; ?>
            </a>
            <?php if ($proj['start_date'] || $proj['end_date']): ?>
            <div style="font-size:11px;color:var(--tx3);">
              <?php echo $proj['start_date'] ? date('d M Y', strtotime($proj['start_date'])) : '—'; ?>
              → <?php echo $proj['end_date'] ? date('d M Y', strtotime($proj['end_date'])) : '—'; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php if ($proj['status'] === 'Completed'): ?>
            <span class="bd bd-em" style="font-size:10px;"><i class="fas fa-check-circle"></i> Verified</span>
          <?php else: ?>
            <button class="btn-ghost" style="font-size:11px;padding:3px 9px;" onclick="verifyProject(<?php echo $proj['id']; ?>)">
              <i class="fas fa-check"></i> Verify
            </button>
          <?php endif; ?>
        </div>
        <?php endwhile; else: ?>
          <div class="empty-st"><i class="fas fa-layer-group"></i> No projects yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- POINTS PANE -->
    <div id="panePoints" class="sd-tab-pane">
      <div class="pc-bd" style="padding-top:10px;">
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button class="btn-pp" style="font-size:12px;padding:5px 12px;" onclick="showAddPointsModal()">
            <i class="fas fa-plus"></i> Add Points
          </button>
        </div>
        <div id="manualPointsHistory">
          <!-- Spinner shown initially; JS will replace it -->
          <div id="pointsSpinner" style="text-align:center;padding:16px;">
            <div class="spinner-border" style="width:1.3rem;height:1.3rem;border-color:var(--pp-xl);border-right-color:transparent;" role="status"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- PAYMENTS PANE -->
    <div id="panePayments" class="sd-tab-pane">
      <div class="pc-bd" style="padding-top:10px;">
        <?php if ($payments->num_rows > 0):
          $payments->data_seek(0); ?>
          <div style="overflow-x:auto;">
            <table class="sd-table" id="paymentHistoryTable">
              <thead>
                <tr>
                  <th>Receipt</th><th>Date</th><th>Amount</th><th>Method</th><th>By</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php while ($pay = $payments->fetch_assoc()): ?>
                <tr>
                  <td><strong style="font-family:'JetBrains Mono',monospace;font-size:11.5px;"><?php echo htmlspecialchars($pay['receipt_number'] ?? 'N/A'); ?></strong></td>
                  <td><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                  <td><span class="bd bd-em">₹<?php echo number_format($pay['amount_paid'], 2); ?></span></td>
                  <td><span class="bd bd-cy"><?php echo $pay['payment_method']; ?></span></td>
                  <td style="font-size:11px;color:var(--tx3);"><?php echo htmlspecialchars($pay['admin_name']); ?></td>
                  <td>
                    <button class="btn-icon" style="background:var(--pp-xs);color:var(--pp);"
                      onclick="window.open('receipt.php?id=<?php echo $pay['id']; ?>','_blank')"
                      title="View Receipt">
                      <i class="fas fa-receipt"></i>
                    </button>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-st">
            <i class="fas fa-receipt"></i> No payments recorded.
            <a href="add_payment.php?student_id=<?php echo $student_id; ?>" style="color:var(--pp);font-weight:700;display:block;margin-top:7px;">
              <i class="fas fa-plus"></i> Add first payment
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /tabs pc -->

</div><!-- /sd-right -->
</div><!-- /sd-shell -->


<!-- ════ MANAGE STUDENT MODAL ════ -->
<div class="modal fade" id="manageStudentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--pp),var(--ind));color:#fff;">
        <h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-sliders-h"></i> Manage Student</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="font-size:10.5px;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Student Actions</div>
        <div class="manage-actions">
          <a href="add_payment.php?student_id=<?php echo $student_id; ?>" class="manage-action-btn">
            <span class="mab-icon mab-em"><i class="fas fa-indian-rupee-sign"></i></span><span>Add Payment</span>
          </a>
          <?php if ($student['status'] === 'Hold'): ?>
          <button class="manage-action-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#resumeStudentModal">
            <span class="mab-icon mab-em"><i class="fas fa-play"></i></span><span>Resume Student</span>
          </button>
          <?php else: ?>
          <button class="manage-action-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#holdStudentModal">
            <span class="mab-icon mab-am"><i class="fas fa-pause"></i></span><span>Hold Student</span>
          </button>
          <?php endif; ?>
          <?php if ($student['login_enabled']): ?>
          <button class="manage-action-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
            <span class="mab-icon mab-am"><i class="fas fa-key"></i></span><span>Reset Password</span>
          </button>
          <button class="manage-action-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#disableLoginModal">
            <span class="mab-icon mab-rd"><i class="fas fa-lock"></i></span><span>Disable Portal</span>
          </button>
          <?php else: ?>
          <button class="manage-action-btn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#enableLoginModal">
            <span class="mab-icon mab-em"><i class="fas fa-unlock"></i></span><span>Enable Portal</span>
          </button>
          <?php endif; ?>
          <a href="send_notification.php?student_id=<?php echo $student_id; ?>" class="manage-action-btn">
            <span class="mab-icon mab-bl"><i class="fas fa-bell"></i></span><span>Send Notification</span>
          </a>
          <button class="manage-action-btn" onclick="window.print()">
            <span class="mab-icon mab-gy"><i class="fas fa-print"></i></span><span>Print Profile</span>
          </button>
          <hr class="manage-divider">
          <button class="manage-action-btn danger-action" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#deleteStudentModal">
            <span class="mab-icon mab-rd"><i class="fas fa-trash-alt"></i></span>
            <span>Delete Student</span>
            <i class="fas fa-chevron-right" style="margin-left:auto;font-size:10px;opacity:.5;"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Student -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--pp),var(--ind));color:#fff;">
        <h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-edit"></i> Edit Student</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="update_student">
          <div style="font-size:11px;font-weight:700;color:var(--pp);text-transform:uppercase;letter-spacing:.6px;margin-bottom:9px;">Personal Information</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:11px;">
            <div><label class="form-label">Full Name *</label><input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required></div>
            <div><label class="form-label">Phone *</label><input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>" required></div>
            <div><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student['email']); ?>"></div>
            <div><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="birthdate" value="<?php echo $student['birthdate']; ?>"></div>
          </div>
          <div style="margin-bottom:11px;"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($student['address']); ?></textarea></div>
          <hr style="border-color:var(--brd);">
          <div style="font-size:11px;font-weight:700;color:var(--pp);text-transform:uppercase;letter-spacing:.6px;margin-bottom:9px;">Course & Fees</div>
          <div style="margin-bottom:11px;">
            <label class="form-label">Category *</label>
            <select class="form-select" name="category_id" id="edit_category" required>
              <option value="">Select Category</option>
              <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
              <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $student['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div style="margin-bottom:11px;">
            <label class="form-label">Course *</label>
            <select class="form-select" name="course_id" id="edit_course" required>
              <option value="">Select Course</option>
              <?php $all_courses->data_seek(0); while ($c = $all_courses->fetch_assoc()): ?>
              <option value="<?php echo $c['id']; ?>" data-category="<?php echo $c['category_id']; ?>" <?php echo $c['id'] == $student['course_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:11px;">
            <div>
              <label class="form-label">Duration (Months) *</label>
              <select class="form-select" name="duration_months" required>
                <?php foreach ([3,6,9,12,18,24] as $d): ?>
                <option value="<?php echo $d; ?>" <?php echo $d == $student['duration_months'] ? 'selected' : ''; ?>><?php echo $d; ?> Months</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div><label class="form-label">Total Fees (₹) *</label><input type="number" class="form-control" name="total_fees" value="<?php echo $student['total_fees']; ?>" step="0.01" required></div>
          </div>
          <div class="al-am" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> Changing fees won't affect existing payment records — only pending amount will update.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-pp"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Upload Photo -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-camera"></i> <?php echo $has_photo ? 'Change' : 'Upload'; ?> Photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <label class="form-label">Select Photo (JPG/PNG/GIF, max 5MB)</label>
          <input type="file" class="form-control" id="student_photo" name="student_photo" accept="image/jpeg,image/jpg,image/png,image/gif" required>
          <div id="imagePreview" style="display:none;text-align:center;margin-top:10px;">
            <img id="previewImg" src="" style="max-width:100%;max-height:180px;border-radius:var(--r2);border:2px solid var(--brd2);">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-pp"><i class="fas fa-upload"></i> Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Enable Login -->
<div class="modal fade" id="enableLoginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-unlock"></i> Enable Portal Access</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="student_portal_actions.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="enable_login">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <div class="al-pp" style="margin-bottom:11px;font-size:12px;"><i class="fas fa-info-circle"></i> Creates login credentials for the student.</div>
        <div style="margin-bottom:11px;"><label class="form-label">Username *</label><input type="text" class="form-control" name="username" value="<?php echo strtolower(str_replace(' ', '', $student['student_code'])); ?>" required><small style="font-size:11px;color:var(--tx3);">Student uses this to login</small></div>
        <div><label class="form-label">Password *</label><input type="text" class="form-control" name="password" value="<?php echo substr($student['phone'], -6); ?>" required><small style="font-size:11px;color:var(--tx3);">Default: last 6 digits of phone</small></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-em"><i class="fas fa-unlock"></i> Enable</button></div>
    </form>
  </div></div>
</div>

<!-- Reset Password -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="student_portal_actions.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <div class="al-am" style="margin-bottom:11px;">Resetting password for: <strong><?php echo htmlspecialchars($student['full_name']); ?></strong></div>
        <label class="form-label">New Password *</label>
        <input type="text" class="form-control" name="new_password" value="<?php echo substr($student['phone'], -6); ?>" required>
        <small style="font-size:11px;color:var(--tx3);">Recommended: last 6 digits of phone</small>
      </div>
      <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-am"><i class="fas fa-key"></i> Reset</button></div>
    </form>
  </div></div>
</div>

<!-- Disable Login -->
<div class="modal fade" id="disableLoginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:var(--rd);color:#fff;"><h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-lock"></i> Disable Portal</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="student_portal_actions.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="disable_login">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <div class="al-rd"><i class="fas fa-exclamation-triangle"></i> <strong><?php echo htmlspecialchars($student['full_name']); ?></strong> will not be able to log in.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-rd"><i class="fas fa-lock"></i> Disable</button></div>
    </form>
  </div></div>
</div>

<!-- Hold Student -->
<div class="modal fade" id="holdStudentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#d97706,var(--am-l));color:#fff;"><h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-pause-circle"></i> Hold Student</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST" action="student_hold_actions.php">
      <div class="modal-body">
        <input type="hidden" name="action" value="hold">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <div class="al-am" style="margin-bottom:11px;font-size:12.5px;">Pauses attendance, ranking points, and course duration for <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>. Portal access remains active.</div>
        <label class="form-label">Reason (Optional)</label>
        <textarea class="form-control" name="hold_reason" rows="3" placeholder="e.g., Medical leave, Financial issues..."></textarea>
      </div>
      <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-am"><i class="fas fa-pause"></i> Hold</button></div>
    </form>
  </div></div>
</div>

<!-- Resume Student -->
<div class="modal fade" id="resumeStudentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#059669,var(--em-l));color:#fff;"><h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-play"></i> Resume Student</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="al-em">Enrollment will resume from where it was paused. <strong><?php echo $hold_days_current; ?> hold days</strong> will be recorded in history.</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
      <a href="student_hold_actions.php?action=resume&student_id=<?php echo $student_id; ?>" class="btn-em"><i class="fas fa-play"></i> Resume</a>
    </div>
  </div></div>
</div>

<!-- Add Points -->
<div class="modal fade" id="addPointsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-star"></i> Add Manual Points</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="al-pp" style="margin-bottom:11px;font-size:12px;"><i class="fas fa-info-circle"></i> Positive = reward · Negative = penalty. Directly affects ranking.</div>
      <div style="margin-bottom:11px;"><label class="form-label">Points *</label><input type="number" class="form-control" id="manual_points" placeholder="e.g. +10 or -5" required></div>
      <div><label class="form-label">Reason *</label><textarea class="form-control" id="points_reason" rows="3" placeholder="Explain why..."></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-pp" onclick="addManualPoints()"><i class="fas fa-check"></i> Award</button></div>
  </div></div>
</div>

<!-- Add / Edit Project -->
<div class="modal fade" id="projectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="projectModalTitle"><i class="fas fa-plus"></i> Add Project</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="project_id">
      <div style="margin-bottom:11px;"><label class="form-label">Project Name *</label><input type="text" class="form-control" id="project_name" required></div>
      <div style="margin-bottom:11px;"><label class="form-label">Description</label><textarea class="form-control" id="project_description" rows="2"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:11px;">
        <div><label class="form-label">Start Date</label><input type="date" class="form-control" id="project_start_date"></div>
        <div><label class="form-label">End Date</label><input type="date" class="form-control" id="project_end_date"></div>
      </div>
      <div style="margin-bottom:11px;"><label class="form-label">Status</label>
        <select class="form-select" id="project_status">
          <option value="Not Started">Not Started</option>
          <option value="In Progress">In Progress</option>
          <option value="Completed">Completed</option>
          <option value="On Hold">On Hold</option>
        </select>
      </div>
      <div><label class="form-label">Remarks</label><textarea class="form-control" id="project_remarks" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-pp" onclick="saveProject()"><i class="fas fa-save"></i> Save</button></div>
  </div></div>
</div>

<!-- Delete Student -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-color:rgba(220,38,38,0.3)!important;">
    <div class="modal-header" style="background:linear-gradient(135deg,#991b1b,var(--rd));color:#fff;">
      <h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-exclamation-triangle"></i> Delete Student — Confirm</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="al-rd" style="margin-bottom:13px;">
        <strong><i class="fas fa-exclamation-triangle"></i> This action cannot be undone!</strong>
        <div style="margin-top:6px;line-height:1.5;">
          All data for <strong><?php echo htmlspecialchars($student['full_name']); ?></strong> — including payments, attendance, projects, and points — will be <em>permanently deleted</em>.
        </div>
      </div>
      <p style="font-size:13px;font-weight:700;color:var(--tx);margin:0;">Are you absolutely sure you want to proceed?</p>
    </div>
    <div class="modal-footer" style="justify-content:space-between;background:#fef2f2;">
      <button type="button" class="btn-ghost" data-bs-dismiss="modal"><i class="fas fa-arrow-left"></i> No, Keep Student</button>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="action" value="delete_student">
        <button type="submit" class="btn-rd"><i class="fas fa-trash-alt"></i> Yes, Delete Permanently</button>
      </form>
    </div>
  </div></div>
</div>


<!-- ════════════════════════════════════════════
     JAVASCRIPT
     ════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const studentId = <?php echo $student_id; ?>;
let attChart = null;

/* ─── Tab switching ─── */
function sdTab(name, el) {
  document.querySelectorAll('.sd-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.sd-tab-pane').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  const pane = document.getElementById('pane' + name.charAt(0).toUpperCase() + name.slice(1));
  if (pane) pane.classList.add('active');
  if (name === 'points') loadManualPointsHistory();
}

/* ─── Attendance filters ─── */
let _customDateMode = false;  // tracks if we're in custom date-range mode

function loadAtt(days, btn) {
  _customDateMode = false;
  document.querySelectorAll('.aff-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.getElementById('customDayArea').style.display = 'none';
  fetchAtt(days);
}
function toggleCustomAtt(btn) {
  document.querySelectorAll('.aff-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const area = document.getElementById('customDayArea');
  area.style.display = area.style.display === 'none' ? 'flex' : 'none';
  if (area.style.display !== 'none') {
    // Pre-fill dates: default last 30 days
    const today = new Date();
    const from  = new Date(); from.setDate(from.getDate() - 29);
    document.getElementById('customDateTo').value   = today.toISOString().split('T')[0];
    document.getElementById('customDateFrom').value  = from.toISOString().split('T')[0];
    document.getElementById('customDateFrom').focus();
  }
}
function applyCustomDateRange() {
  const fromVal = document.getElementById('customDateFrom').value;
  const toVal   = document.getElementById('customDateTo').value;
  if (!fromVal || !toVal) { alert('Please select both From and To dates'); return; }
  const from = new Date(fromVal);
  const to   = new Date(toVal);
  if (from > to) { alert('From date must be before To date'); return; }
  // Calculate number of days between from and to (inclusive)
  const diffMs = to.getTime() - from.getTime();
  const days   = Math.round(diffMs / 86400000) + 1;
  if (days > 365) { alert('Maximum range is 365 days'); return; }
  if (days < 1) { alert('Invalid date range'); return; }
  _customDateMode = true;
  // We pass a custom "days" offset from today, calculated so the window ends at "to" date
  // But the AJAX endpoint always counts back from today. So we need to compute accordingly.
  const todayDate = new Date();
  todayDate.setHours(0,0,0,0);
  to.setHours(0,0,0,0);
  from.setHours(0,0,0,0);
  // Calculate how many days from "from" to today (the AJAX endpoint goes back N days from today)
  const daysFromToday = Math.round((todayDate.getTime() - from.getTime()) / 86400000) + 1;
  // Fetch that many days, then we'll filter client-side to the requested range
  fetchAttCustom(daysFromToday, fromVal, toVal);
}
function closeCustomAtt() {
  _customDateMode = false;
  document.getElementById('customDayArea').style.display = 'none';
  loadAtt(30, document.getElementById('aff30'));
}

/* ─── Fetch attendance ─── */
function fetchAtt(days) {
  document.getElementById('attLoading').style.display = 'block';
  document.getElementById('attChartWrap').style.opacity = '0.3';
  fetch(`ajax/get_student_attendance.php?student_id=${studentId}&days=${days}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('attLoading').style.display = 'none';
      document.getElementById('attChartWrap').style.opacity = '1';
      if (data.success) {
        renderAttChart(data);
        computeAttStats(data, days);
        renderAttDetailsList(data);
      }
    })
    .catch(() => {
      document.getElementById('attLoading').style.display = 'none';
      document.getElementById('attChartWrap').style.opacity = '1';
    });
}

/* ─── Fetch attendance with custom date range ─── */
function fetchAttCustom(daysBack, fromDate, toDate) {
  document.getElementById('attLoading').style.display = 'block';
  document.getElementById('attChartWrap').style.opacity = '0.3';
  fetch(`ajax/get_student_attendance.php?student_id=${studentId}&days=${daysBack}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('attLoading').style.display = 'none';
      document.getElementById('attChartWrap').style.opacity = '1';
      if (data.success) {
        // Filter data to only include dates within [fromDate, toDate]
        const filtered = filterDataByDateRange(data, fromDate, toDate);
        renderAttChart(filtered);
        computeAttStatsCustom(filtered, fromDate, toDate);
        renderAttDetailsList(filtered);
      }
    })
    .catch(() => {
      document.getElementById('attLoading').style.display = 'none';
      document.getElementById('attChartWrap').style.opacity = '1';
    });
}

/* ─── Filter data arrays by date range ─── */
function filterDataByDateRange(data, fromDate, toDate) {
  const rawDates = data.rawDates || [];
  const indices = [];
  rawDates.forEach((d, i) => {
    if (d >= fromDate && d <= toDate) indices.push(i);
  });
  return {
    success:  true,
    rawDates: indices.map(i => data.rawDates[i]),
    dates:    indices.map(i => data.dates[i]),
    present:  indices.map(i => data.present[i]),
    absent:   indices.map(i => data.absent[i]),
    holiday:  indices.map(i => (data.holiday || [])[i] || 0),
    checkIn:  indices.map(i => (data.checkIn || [])[i] || '--'),
    checkOut: indices.map(i => (data.checkOut || [])[i] || '--'),
  };
}

/* ─── Render Chart (Sunday = amber) ─── */
function renderAttChart(data) {
  const ctx      = document.getElementById('attendanceChart');
  const rawDates = data.rawDates || [];
  const present  = data.present;
  const absent   = data.absent;
  const holiday  = data.holiday || [];
  const checkIn  = data.checkIn  || [];
  const checkOut = data.checkOut || [];

  // Build multi-line labels with day name from rawDates: ["Sun", "28 Jun"]
  const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const chartLabels = rawDates.map(dateStr => {
    const d = new Date(dateStr + 'T00:00:00');
    const dayName = dayNames[d.getDay()];
    const dd = String(d.getDate()).padStart(2, '0');
    const mon = monthNames[d.getMonth()];
    return [dayName, dd + ' ' + mon];
  });

  const sunC  = 'rgba(245,158,11,0.80)';
  const preC  = 'rgba(16,185,129,0.88)';
  const absC  = 'rgba(239,68,68,0.85)';
  const holC  = 'rgba(156,163,175,0.80)';
  const bgPre = [], bgAbs = [];
  for (let i = 0; i < rawDates.length; i++) {
    const isSun = rawDates[i] ? (new Date(rawDates[i] + 'T00:00:00').getDay() === 0) : false;
    const isHol = holiday[i] === 1;
    bgPre.push(isSun ? sunC : (isHol ? holC : preC));
    bgAbs.push(isSun ? sunC : absC);
  }
  if (attChart) attChart.destroy();
  attChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartLabels,
      datasets: [
        { label: 'Present', data: present, backgroundColor: bgPre, borderRadius: 3, barThickness: 9 },
        { label: 'Absent',  data: absent,  backgroundColor: bgAbs, borderRadius: 3, barThickness: 9 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } },
        tooltip: {
          mode: 'index',
          intersect: false,
          callbacks: {
            title(items) {
              const idx = items[0]?.dataIndex;
              if (idx === undefined || !rawDates[idx]) return '';
              const d = new Date(rawDates[idx] + 'T00:00:00');
              return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
            },
            label(ctx) {
              const idx = ctx.dataIndex;
              const isSun = rawDates[idx] && new Date(rawDates[idx] + 'T00:00:00').getDay() === 0;
              const isHol = holiday[idx] === 1;
              if (isSun) {
                return [
                  'Status   : Sunday',
                  'Check In : --',
                  'Check Out: --'
                ];
              }
              if (isHol) {
                return [
                  'Status   : Holiday',
                  'Check In : --',
                  'Check Out: --'
                ];
              }
              if (present[idx] === 1) {
                return [
                  'Status   : Present',
                  'Check In : ' + (checkIn[idx]  || '--'),
                  'Check Out: ' + (checkOut[idx] || '--')
                ];
              }
              return [
                'Status   : Absent',
                'Check In : --',
                'Check Out: --'
              ];
            },
            // Suppress the second dataset from adding duplicate lines
            afterLabel() { return null; },
            // Dynamic tooltip color indicator — matches the actual bar color
            labelColor(ctx) {
              const idx = ctx.dataIndex;
              const isSun = rawDates[idx] && new Date(rawDates[idx] + 'T00:00:00').getDay() === 0;
              const isHol = holiday[idx] === 1;
              let color;
              if (isSun) {
                color = sunC;
              } else if (isHol) {
                color = holC;
              } else if (present[idx] === 1) {
                color = preC;
              } else {
                color = absC;
              }
              return { backgroundColor: color, borderColor: color };
            }
          },
          filter(item) {
            // Only render tooltip lines for the first dataset (avoid duplicate blocks)
            return item.datasetIndex === 0;
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true, max: 1.15,
          ticks: { stepSize: 1, callback: v => v === 1 ? '✓' : '', font: { size: 10 } },
          grid: { color: 'rgba(109,40,217,0.06)' }
        },
        x: {
          type: 'category',
          offset: true,
          ticks: {
            align: 'center',
            font: { size: 9 },
            maxRotation: 90,
            minRotation: 45,
            autoSkip: false
          },
          grid: { display: false }
        }
      }
    }
  });
}

/* ─── Compute 4 stats (Sundays excluded) ─── */
function computeAttStats(data, days) {
  const rawDates = data.rawDates || [];
  const present  = data.present  || [];
  const holiday  = data.holiday  || [];

  let sundays = 0;
  rawDates.forEach(d => { if (new Date(d).getDay() === 0) sundays++; });

  const workingDays = rawDates.length - sundays;
  let presentCount  = 0;
  rawDates.forEach((d, i) => {
    if (new Date(d).getDay() !== 0 && present[i] === 1) presentCount++;
  });
  const absentCount = Math.max(0, workingDays - presentCount);
  const rate = workingDays > 0 ? Math.round((presentCount / workingDays) * 100) : 0;

  // Current streak (skip Sundays, treat Holiday as valid)
  let curStreak = 0;
  for (let i = present.length - 1; i >= 0; i--) {
    if (rawDates[i] && new Date(rawDates[i]).getDay() === 0) continue;
    if (present[i] === 1) curStreak++; else break;  // present includes Holiday
  }

  document.getElementById('attPresent').textContent = presentCount;
  document.getElementById('attAbsent').textContent  = absentCount;
  document.getElementById('attRate').textContent    = rate + '%';
  document.getElementById('attStreak').textContent  = curStreak + 'd';

  // Period label
  const fmtDate = d => d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const start = new Date(); start.setDate(start.getDate() - (days - 1));
  document.getElementById('attPeriodInfo').innerHTML =
    `<i class="fas fa-calendar"></i> <strong>${fmtDate(start)}</strong> → <strong>${fmtDate(new Date())}</strong> · ${days} days`;
}

/* ─── Compute stats for custom date range ─── */
function computeAttStatsCustom(data, fromDate, toDate) {
  const rawDates = data.rawDates || [];
  const present  = data.present  || [];
  const holiday  = data.holiday  || [];

  let sundays = 0;
  rawDates.forEach(d => { if (new Date(d + 'T00:00:00').getDay() === 0) sundays++; });

  const workingDays = rawDates.length - sundays;
  let presentCount  = 0;
  rawDates.forEach((d, i) => {
    if (new Date(d + 'T00:00:00').getDay() !== 0 && present[i] === 1) presentCount++;
  });
  const absentCount = Math.max(0, workingDays - presentCount);
  const rate = workingDays > 0 ? Math.round((presentCount / workingDays) * 100) : 0;

  let curStreak = 0;
  for (let i = present.length - 1; i >= 0; i--) {
    if (rawDates[i] && new Date(rawDates[i] + 'T00:00:00').getDay() === 0) continue;
    if (present[i] === 1) curStreak++; else break;
  }

  document.getElementById('attPresent').textContent = presentCount;
  document.getElementById('attAbsent').textContent  = absentCount;
  document.getElementById('attRate').textContent    = rate + '%';
  document.getElementById('attStreak').textContent  = curStreak + 'd';

  // Period label using actual from/to dates
  const fmtDate = d => d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const fDate = new Date(fromDate + 'T00:00:00');
  const tDate = new Date(toDate + 'T00:00:00');
  const totalDays = rawDates.length;
  document.getElementById('attPeriodInfo').innerHTML =
    `<i class="fas fa-calendar"></i> <strong>${fmtDate(fDate)}</strong> → <strong>${fmtDate(tDate)}</strong> · ${totalDays} days`;
}

/* ─── Render Attendance Details List ─── */
function renderAttDetailsList(data) {
  const rawDates = data.rawDates || [];
  const present  = data.present  || [];
  const checkIn  = data.checkIn  || [];
  const checkOut = data.checkOut || [];

  const dayFullNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const tbody    = document.getElementById('attDetailsBody');
  const cardList = document.getElementById('attCardList');
  const countEl  = document.getElementById('attDetailsCount');

  if (!rawDates.length) {
    tbody.innerHTML = '<tr><td colspan="5"><div class="att-details-empty"><i class="fas fa-calendar-times"></i> No attendance records found for this period.</div></td></tr>';
    cardList.innerHTML = '<div class="att-details-empty"><i class="fas fa-calendar-times"></i> No attendance records found for this period.</div>';
    countEl.textContent = '';
    return;
  }

  countEl.textContent = rawDates.length + ' day' + (rawDates.length !== 1 ? 's' : '');

  let tableHtml = '';
  let cardHtml  = '';

  // Iterate in reverse (newest first)
  for (let i = rawDates.length - 1; i >= 0; i--) {
    const dateObj = new Date(rawDates[i] + 'T00:00:00');
    const dayOfWeek = dateObj.getDay();
    const isSunday = dayOfWeek === 0;

    // Format date: "10 Aug 2026"
    const dateFormatted = dateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const dayName = dayFullNames[dayOfWeek];

    // Determine status
    let statusText, statusClass, statusIcon;
    const isHol = (data.holiday || [])[i] === 1;
    if (isSunday) {
      statusText  = 'Sunday';
      statusClass = 'st-sunday';
      statusIcon  = 'fa-sun';
    } else if (isHol) {
      statusText  = 'Holiday';
      statusClass = 'st-sunday';  // reuse the amber/grey style
      statusIcon  = 'fa-umbrella-beach';
    } else if (present[i] === 1) {
      statusText  = 'Present';
      statusClass = 'st-present';
      statusIcon  = 'fa-check-circle';
    } else {
      statusText  = 'Absent';
      statusClass = 'st-absent';
      statusIcon  = 'fa-times-circle';
    }

    // Check-in / Check-out
    const ci = (isSunday || isHol || !checkIn[i] || checkIn[i] === '--') ? '—' : checkIn[i];
    const co = (isSunday || isHol || !checkOut[i] || checkOut[i] === '--') ? '—' : checkOut[i];
    const ciClass = ci === '—' ? 'att-time-cell no-time' : 'att-time-cell';
    const coClass = co === '—' ? 'att-time-cell no-time' : 'att-time-cell';

    // Desktop table row
    tableHtml += `<tr>
      <td class="att-date-cell">${dateFormatted}</td>
      <td class="att-day-cell">${dayName}</td>
      <td><span class="att-status-badge ${statusClass}"><i class="fas ${statusIcon}" style="font-size:9px;"></i> ${statusText}</span></td>
      <td class="${ciClass}">${ci}</td>
      <td class="${coClass}">${co}</td>
    </tr>`;

    // Mobile card
    cardHtml += `<div class="att-card-item">
      <div class="att-card-top">
        <div>
          <span class="att-card-date">${dateFormatted}</span>
          <span class="att-card-day">${dayName}</span>
        </div>
        <span class="att-status-badge ${statusClass}"><i class="fas ${statusIcon}" style="font-size:9px;"></i> ${statusText}</span>
      </div>
      <div class="att-card-times">
        <div class="att-card-time-block">
          <span class="att-card-time-label">Check In</span>
          <span class="att-card-time-val ${ci === '—' ? 'no-time' : ''}">${ci}</span>
        </div>
        <div class="att-card-time-block">
          <span class="att-card-time-label">Check Out</span>
          <span class="att-card-time-val ${co === '—' ? 'no-time' : ''}">${co}</span>
        </div>
      </div>
    </div>`;
  }

  tbody.innerHTML    = tableHtml;
  cardList.innerHTML = cardHtml;
}

// Default load
document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => loadAtt(30, document.getElementById('aff30')), 400);
});

/* ─── ⑥ Manual Points — fixed with timeout + always-clear spinner ─── */
let _pointsLoaded = false;

document.addEventListener('DOMContentLoaded', () => {
  // Pre-load points history immediately so it's ready when tab is opened
  _loadPointsSilent();
});

function _loadPointsSilent() {
  // Load in background without showing spinner (already shown from PHP)
  const TIMEOUT_MS = 5000;
  const el = document.getElementById('manualPointsHistory');
  if (!el) return;

  const controller = new AbortController();
  const timer = setTimeout(() => {
    controller.abort();
    el.innerHTML = '<div class="al-am" style="font-size:12px;"><i class="fas fa-clock"></i> Request timed out. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
  }, TIMEOUT_MS);

  fetch(`ajax/manage_manual_points.php?action=get_history&student_id=${studentId}`, { signal: controller.signal })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(d => {
      clearTimeout(timer);
      if (d && d.success) {
        _pointsLoaded = true;
        renderPointsHistory(d.history);
      } else {
        el.innerHTML = '<div class="al-rd" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> Could not load points. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
      }
    })
    .catch(err => {
      clearTimeout(timer);
      if (err.name === 'AbortError') return; // Already handled by timer
      el.innerHTML = '<div class="al-rd" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> Failed to load. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
    });
}

function loadManualPointsHistory() {
  const el = document.getElementById('manualPointsHistory');
  if (!el) return;

  // Show spinner
  el.innerHTML = '<div style="text-align:center;padding:16px;"><div class="spinner-border" style="width:1.3rem;height:1.3rem;border-color:var(--pp-xl);border-right-color:transparent;" role="status"></div></div>';

  const TIMEOUT_MS = 5000;
  const controller = new AbortController();
  const timer = setTimeout(() => {
    controller.abort();
    el.innerHTML = '<div class="al-am" style="font-size:12px;"><i class="fas fa-clock"></i> Request timed out. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
  }, TIMEOUT_MS);

  fetch(`ajax/manage_manual_points.php?action=get_history&student_id=${studentId}`, { signal: controller.signal })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(d => {
      clearTimeout(timer);
      if (d && d.success) {
        renderPointsHistory(d.history);
      } else {
        el.innerHTML = '<div class="al-rd" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> Could not load points. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
      }
    })
    .catch(err => {
      clearTimeout(timer);
      if (err.name === 'AbortError') return;
      el.innerHTML = '<div class="al-rd" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> Failed to load. <button class="btn-ghost" style="font-size:11px;padding:2px 8px;margin-left:6px;" onclick="loadManualPointsHistory()">Retry</button></div>';
    });
}

function showAddPointsModal() {
  document.getElementById('manual_points').value = '';
  document.getElementById('points_reason').value = '';
  new bootstrap.Modal(document.getElementById('addPointsModal')).show();
}

function addManualPoints() {
  const pts    = parseInt(document.getElementById('manual_points').value);
  const reason = document.getElementById('points_reason').value.trim();
  if (isNaN(pts) || pts === 0) { alert('Enter a valid non-zero value'); return; }
  if (!reason) { alert('Reason is required'); return; }
  const fd = new FormData();
  fd.append('action', 'add_points'); fd.append('student_id', studentId);
  fd.append('points', pts); fd.append('reason', reason);
  fetch('ajax/manage_manual_points.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        bootstrap.Modal.getInstance(document.getElementById('addPointsModal')).hide();
        loadManualPointsHistory();
      } else alert(d.message || 'Failed');
    })
    .catch(() => alert('Request failed. Please try again.'));
}

function renderPointsHistory(history) {
  const el = document.getElementById('manualPointsHistory');
  if (!el) return;
  if (!history || !history.length) {
    el.innerHTML = '<div class="empty-st"><i class="fas fa-star"></i> No manual points yet.</div>';
    return;
  }
  const total = history.reduce((s, i) => s + parseInt(i.points), 0);
  const tc    = total >= 0 ? 'bd-em' : 'bd-rd';
  let html = `<div style="margin-bottom:9px;"><span class="bd ${tc}">Total: ${total > 0 ? '+' : ''}${total} pts</span></div>
  <div style="overflow-x:auto;"><table class="sd-table">
  <thead><tr><th>Date</th><th>Points</th><th>Reason</th><th>By</th><?php if (isSuperAdmin()): ?><th></th><?php endif; ?></tr></thead><tbody>`;
  history.forEach(item => {
    const sign = item.points > 0 ? '+' : '';
    const cls  = item.points > 0 ? 'bd-em' : 'bd-rd';
    const dt   = new Date(item.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
    html += `<tr>
      <td style="font-size:11px;">${dt}</td>
      <td><span class="bd ${cls}">${sign}${item.points}</span></td>
      <td style="font-size:12px;">${item.reason}</td>
      <td style="font-size:11px;color:var(--tx3);">${item.admin_name}</td>
      <?php if (isSuperAdmin()): ?>
      <td><button class="btn-icon" style="background:#fee2e2;color:#dc2626;" onclick="deleteManualPoint(${item.id})"><i class="fas fa-trash"></i></button></td>
      <?php endif; ?>
    </tr>`;
  });
  html += '</tbody></table></div>';
  el.innerHTML = html;
}

<?php if (isSuperAdmin()): ?>
function deleteManualPoint(id) {
  if (!confirm('Delete this entry?')) return;
  const fd = new FormData(); fd.append('action', 'delete_point'); fd.append('point_id', id);
  fetch('ajax/manage_manual_points.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) loadManualPointsHistory(); })
    .catch(() => alert('Delete failed.'));
}
<?php endif; ?>

/* ─── Projects ─── */
function showAddProjectModal() {
  document.getElementById('projectModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Project';
  ['project_id','project_name','project_description','project_start_date','project_end_date','project_remarks'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('project_status').value = 'Not Started';
  new bootstrap.Modal(document.getElementById('projectModal')).show();
}
function saveProject() {
  const pid  = document.getElementById('project_id').value;
  const name = document.getElementById('project_name').value;
  if (!name) { alert('Project name required'); return; }
  const fd = new FormData();
  fd.append('action', pid ? 'update_project' : 'add_project');
  if (pid) fd.append('project_id', pid);
  fd.append('student_id', studentId);
  fd.append('project_name', name);
  fd.append('description', document.getElementById('project_description').value);
  fd.append('start_date',  document.getElementById('project_start_date').value);
  fd.append('end_date',    document.getElementById('project_end_date').value);
  fd.append('status',      document.getElementById('project_status').value);
  fd.append('remarks',     document.getElementById('project_remarks').value);
  fetch('ajax/manage_projects.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert('Failed to save'); });
}
function verifyProject(projectId) {
  if (!confirm('Mark this project as Completed?')) return;
  const fd = new FormData(); fd.append('action', 'verify_project'); fd.append('project_id', projectId);
  fetch('ajax/manage_projects.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message || 'Verification failed'); })
    .catch(() => alert('Something went wrong'));
}

/* ─── Photo preview ─── */
document.getElementById('student_photo')?.addEventListener('change', function (e) {
  const f = e.target.files[0];
  if (!f) return;
  if (f.size > 5242880) { alert('Max 5MB'); this.value = ''; return; }
  const allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
  if (!allowed.includes(f.type)) { alert('Only JPG, PNG, GIF allowed'); this.value = ''; return; }
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('previewImg').src = e.target.result;
    document.getElementById('imagePreview').style.display = 'block';
  };
  r.readAsDataURL(f);
});
</script>

<?php include 'includes/footer.php'; ?>