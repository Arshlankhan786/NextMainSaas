<?php
session_start();
require_once './config/database.php';
require_once './config/auth.php';
requireLogin();

if ($_SESSION['admin_role'] === 'Administrator') {
    // Allowed
} elseif (!in_array($_SESSION['admin_role'], ['Super Admin', 'Admin'])) {
    $_SESSION['error'] = "Access denied.";
    header('Location: index.php');
    exit;
}

$inquiry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($inquiry_id === 0) {
    header('Location: inquiries.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add_note') {
        $note = sanitize($_POST['note']);
        $created_by = $_SESSION['admin_id'];

        $stmt = $conn->prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $inquiry_id, $note, $created_by);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Note added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add note.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }

    if ($_POST['action'] === 'update_status') {
        $status = sanitize($_POST['status']);

        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $inquiry_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update status.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }

    if ($_POST['action'] === 'update_followup') {
        $followup_at = null;
        if (!empty($_POST['followup_date'])) {
            $fu_date = sanitize($_POST['followup_date']);
            $fu_time = !empty($_POST['followup_time']) ? sanitize($_POST['followup_time']) : '10:00';
            $followup_at = $fu_date . ' ' . $fu_time . ':00';
        }
        $stmt = $conn->prepare("UPDATE inquiries SET followup_at = ? WHERE id = ?");
        $stmt->bind_param("si", $followup_at, $inquiry_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Follow-up scheduled!";
        } else {
            $_SESSION['error'] = "Failed to update follow-up.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }

    if ($_POST['action'] === 'upload_photo') {
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/inquiries/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
            if (in_array($_FILES['photo']['type'], $allowed) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                // Delete old photo
                $old = $conn->query("SELECT photo FROM inquiries WHERE id = $inquiry_id")->fetch_assoc();
                if ($old && !empty($old['photo']) && file_exists($old['photo'])) unlink($old['photo']);

                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'inquiry_' . $inquiry_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    $path = 'uploads/inquiries/' . $filename;
                    $conn->query("UPDATE inquiries SET photo = '$path' WHERE id = $inquiry_id");
                    $_SESSION['success'] = "Photo updated!";
                } else {
                    $_SESSION['error'] = "Upload failed.";
                }
            } else {
                $_SESSION['error'] = "Invalid file. JPG/PNG/GIF under 5MB.";
            }
        }
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }

    if ($_POST['action'] === 'update_inquiry') {
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $course_interested = sanitize($_POST['course_interested']);
        $source = sanitize($_POST['source']);
        $message = sanitize($_POST['message']);
        $stmt = $conn->prepare("UPDATE inquiries SET name=?, mobile=?, email=?, course_interested=?, source=?, message=? WHERE id=?");
        $stmt->bind_param("ssssssi", $name, $mobile, $email, $course_interested, $source, $message, $inquiry_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Lead updated!";
        } else {
            $_SESSION['error'] = "Update failed.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }

    // DIRECT ENROLLMENT - NO REDIRECT
    if ($_POST['action'] === 'enroll_student') {
        // Check if already converted
        $check = $conn->query("SELECT id FROM students WHERE inquiry_id = $inquiry_id");
        if ($check->num_rows > 0) {
            $_SESSION['error'] = "This inquiry has already been converted to a student.";
            header('Location: inquiry_details.php?id=' . $inquiry_id);
            exit();
        }

        $student_code = 'STU' . date('Ymd') . rand(1000, 9999);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $birthdate = sanitize($_POST['birthdate']);
        $category_id = (int)$_POST['category_id'];
        $course_id = (int)$_POST['course_id'];
        $duration_months = (int)$_POST['duration_months'];
        $total_fees = (float)$_POST['total_fees'];
        $enrollment_date = sanitize($_POST['enrollment_date']);

        $stmt = $conn->prepare("INSERT INTO students (student_code, full_name, email, phone, address, birthdate, category_id, course_id, duration_months, total_fees, enrollment_date, status, inquiry_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
        $stmt->bind_param("ssssssiiidsi", $student_code, $full_name, $email, $phone, $address, $birthdate, $category_id, $course_id, $duration_months, $total_fees, $enrollment_date, $inquiry_id);

        if ($stmt->execute()) {
            $student_id = $stmt->insert_id;
            
            // Update inquiry status to converted
            $conn->query("UPDATE inquiries SET status = 'converted' WHERE id = $inquiry_id");
            
            // Add enrollment note
            $note = "Lead converted to student. Student Code: $student_code";
            $created_by = $_SESSION['admin_id'];
            $note_stmt = $conn->prepare("INSERT INTO inquiry_notes (inquiry_id, note, created_by) VALUES (?, ?, ?)");
            $note_stmt->bind_param("isi", $inquiry_id, $note, $created_by);
            $note_stmt->execute();
            $note_stmt->close();
            
            $_SESSION['success'] = "Student enrolled successfully! Student Code: $student_code";
        } else {
            $_SESSION['error'] = "Failed to enroll student.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $inquiry_id);
        exit();
    }
}

// Get inquiry details
$inquiry = $conn->query("
    SELECT i.*, a.full_name as created_by_name 
    FROM inquiries i
    LEFT JOIN admins a ON i.created_by = a.id
    WHERE i.id = $inquiry_id
")->fetch_assoc();

if (!$inquiry) {
    $_SESSION['error'] = "Inquiry not found!";
    header('Location: inquiries.php');
    exit();
}

// Get notes
$notes = $conn->query("
    SELECT n.*, a.full_name as created_by_name 
    FROM inquiry_notes n
    JOIN admins a ON n.created_by = a.id
    WHERE n.inquiry_id = $inquiry_id
    ORDER BY n.created_at DESC
");

// Check if converted to student
$converted_student = null;
if ($inquiry['status'] === 'converted') {
    $result = $conn->query("SELECT id, student_code, full_name FROM students WHERE inquiry_id = $inquiry_id");
    if ($result->num_rows > 0) {
        $converted_student = $result->fetch_assoc();
    }
}

// Photo
$hasPhoto = !empty($inquiry['photo']) && file_exists($inquiry['photo']);
$initials = strtoupper(substr($inquiry['name'], 0, 1));

// Follow-up
$hasFu = !empty($inquiry['followup_at']);
$fuOverdue = $hasFu && strtotime($inquiry['followup_at']) < time() && !in_array($inquiry['status'], ['converted','closed']);

// Status badge class
$statusCls = 'id-badge-' . $inquiry['status'];

// Get categories and courses for enrollment form
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 'Active' ORDER BY name");
$all_courses = $conn->query("SELECT id, category_id, name FROM courses WHERE status = 'Active' ORDER BY name");
$courses_list = $conn->query("SELECT id, name FROM courses WHERE status = 'Active' ORDER BY name");

include 'includes/header.php';
?>

<style>
/* ── Lead Details — dashboard design system ── */
.id-header { margin-bottom:14px }
.id-breadcrumb { font-size:11px; color:var(--muted); display:flex; align-items:center; gap:5px; margin-bottom:4px }
.id-breadcrumb a { color:var(--indigo-600); text-decoration:none; font-weight:600 }
.id-breadcrumb a:hover { text-decoration:underline }
.id-title { font-size:18px; font-weight:800; color:var(--text); display:flex; align-items:center; gap:8px; margin:0 }
.id-title i { color:var(--indigo-600); font-size:15px }

.id-shell { display:grid; grid-template-columns:320px 1fr; gap:14px; align-items:start }

/* Left panel */
.id-panel {
    background:var(--surface); border:1px solid var(--border); border-radius:14px;
    box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; position:sticky; top:68px
}
.id-panel-hd {
    background:linear-gradient(145deg,#1e1b4b 0%,#3730a3 50%,#4f46e5 100%);
    padding:22px 16px 16px; text-align:center; position:relative
}
.id-panel-hd::before {
    content:''; position:absolute; inset:0;
    background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events:none
}
.id-avatar {
    width:72px; height:72px; border-radius:50%; margin:0 auto 10px;
    border:3px solid rgba(255,255,255,.4); box-shadow:0 0 0 3px rgba(255,255,255,.12);
    display:flex; align-items:center; justify-content:center;
    font-size:26px; font-weight:800; color:#fff; overflow:hidden;
    background:rgba(255,255,255,.12); position:relative; z-index:1; cursor:pointer; transition:transform .2s
}
.id-avatar:hover { transform:scale(1.04) }
.id-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50% }
.id-name { font-size:15px; font-weight:800; color:#fff; position:relative; z-index:1 }
.id-course-tag { font-size:11px; color:rgba(255,255,255,.65); position:relative; z-index:1; margin-top:2px }
.id-photo-btns { display:flex; gap:5px; justify-content:center; margin-top:8px; position:relative; z-index:1 }
.id-photo-btn {
    background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.25);
    border-radius:6px; padding:3px 10px; font-size:10px; font-weight:600;
    cursor:pointer; transition:all .15s; font-family:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:4px
}
.id-photo-btn:hover { background:rgba(255,255,255,.25); color:#fff }

.id-panel-body { padding:12px 16px }
.id-info-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:7px 0; border-bottom:1px solid rgba(0,0,0,.05); font-size:12px
}
.id-info-row:last-child { border-bottom:none }
.id-info-lbl { color:var(--muted); font-weight:600; display:flex; align-items:center; gap:5px; font-size:11px }
.id-info-lbl i { width:12px; font-size:10px; color:var(--indigo-400) }
.id-info-val { font-weight:700; text-align:right; word-break:break-word }
.id-info-val a { color:var(--indigo-600); text-decoration:none }
.id-info-val a:hover { text-decoration:underline }

.id-badge {
    display:inline-flex; align-items:center; gap:3px; padding:2px 8px;
    border-radius:99px; font-size:10px; font-weight:700; letter-spacing:.2px
}
.id-badge-new { background:#fffbeb; color:#d97706; border:1px solid rgba(217,119,6,.2) }
.id-badge-contacted { background:#eff6ff; color:#2563eb; border:1px solid rgba(37,99,235,.2) }
.id-badge-followup { background:#f5f3ff; color:#7c3aed; border:1px solid rgba(124,58,237,.2) }
.id-badge-converted { background:#f0fdf4; color:#059669; border:1px solid rgba(5,150,105,.2) }
.id-badge-closed { background:#fef2f2; color:#dc2626; border:1px solid rgba(220,38,38,.2) }

/* Sections in panel */
.id-section { padding:10px 16px; border-top:1px solid var(--border) }
.id-section-title { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px }

.id-fu-display { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:8px; font-size:12px; font-weight:600; margin-bottom:8px }
.id-fu-ok { background:#f0fdf4; color:#059669; border:1px solid rgba(5,150,105,.15) }
.id-fu-overdue { background:#fef2f2; color:#dc2626; border:1px solid rgba(220,38,38,.15) }
.id-fu-none { background:#f8fafc; color:var(--muted); border:1px solid var(--border) }

.id-fu-form { display:flex; gap:6px; align-items:flex-end }
.id-fu-form input { flex:1; padding:6px 8px; border:1px solid var(--border); border-radius:7px; font-size:11px; font-family:inherit }
.id-fu-form input:focus { outline:none; border-color:var(--indigo-400) }
.id-fu-btn {
    padding:6px 12px; border:none; border-radius:7px; font-size:10px; font-weight:700;
    background:var(--indigo-600); color:#fff; cursor:pointer; font-family:inherit; transition:all .15s; white-space:nowrap
}
.id-fu-btn:hover { background:var(--indigo-700) }

.id-status-form select { width:100%; padding:7px 10px; border:1px solid var(--border); border-radius:8px; font-size:12px; font-family:inherit; margin-bottom:6px }
.id-status-form select:focus { outline:none; border-color:var(--indigo-400) }
.id-status-btn { width:100%; padding:8px; border:none; border-radius:8px; font-size:12px; font-weight:700; background:var(--indigo-600); color:#fff; cursor:pointer; font-family:inherit; transition:all .15s }
.id-status-btn:hover { background:var(--indigo-700) }

.id-enroll-btn {
    width:100%; padding:9px; border:none; border-radius:8px; font-size:12px; font-weight:700;
    background:linear-gradient(135deg,#059669,#10b981); color:#fff; cursor:pointer;
    font-family:inherit; display:flex; align-items:center; justify-content:center; gap:6px; transition:all .15s
}
.id-enroll-btn:hover { box-shadow:0 4px 12px rgba(5,150,105,.3) }

.id-converted-alert {
    padding:10px 12px; border-radius:8px; background:#f0fdf4;
    border:1px solid rgba(5,150,105,.2); font-size:12px; color:#065f46
}
.id-converted-alert a { color:#059669; font-weight:700; text-decoration:none }
.id-converted-alert a:hover { text-decoration:underline }

.id-edit-btn {
    width:100%; padding:7px; border:1px solid var(--border); border-radius:8px;
    font-size:11px; font-weight:600; background:#fff; color:var(--muted);
    cursor:pointer; font-family:inherit; transition:all .15s; display:flex; align-items:center; justify-content:center; gap:5px; text-decoration:none
}
.id-edit-btn:hover { background:var(--indigo-100); color:var(--indigo-700); border-color:var(--indigo-400) }

/* Right panel — notes */
.id-notes-card {
    background:var(--surface); border:1px solid var(--border); border-radius:14px;
    box-shadow:0 1px 3px rgba(0,0,0,.06)
}
.id-notes-hd {
    padding:14px 18px; border-bottom:1px solid var(--border);
    font-size:13px; font-weight:800; color:var(--text); display:flex; align-items:center; gap:8px
}
.id-notes-hd i { color:var(--indigo-600); font-size:13px }

.id-note-form { padding:16px 18px; border-bottom:1px solid var(--border) }
.id-note-form textarea {
    width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px;
    font-size:12px; font-family:inherit; resize:vertical; min-height:60px; margin-bottom:8px
}
.id-note-form textarea:focus { outline:none; border-color:var(--indigo-400); box-shadow:0 0 0 3px rgba(99,102,241,.08) }
.id-note-submit {
    padding:7px 16px; border:none; border-radius:8px; font-size:12px; font-weight:700;
    background:var(--indigo-600); color:#fff; cursor:pointer; font-family:inherit;
    display:inline-flex; align-items:center; gap:5px; transition:all .15s
}
.id-note-submit:hover { background:var(--indigo-700) }

/* Timeline */
.id-timeline { padding:0 18px 16px; position:relative }
.id-tl-item { display:flex; gap:12px; padding:14px 0; position:relative }
.id-tl-item:not(:last-child)::before {
    content:''; position:absolute; left:13px; top:40px; bottom:0;
    width:2px; background:linear-gradient(to bottom, var(--indigo-100), transparent)
}
.id-tl-dot {
    width:28px; height:28px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:10px;
    background:var(--indigo-100); color:var(--indigo-600); border:2px solid #fff;
    box-shadow:0 0 0 2px rgba(99,102,241,.15); z-index:1
}
.id-tl-content { flex:1; min-width:0 }
.id-tl-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:3px }
.id-tl-author { font-size:12px; font-weight:700; color:var(--text) }
.id-tl-time { font-size:10px; color:var(--muted); display:flex; align-items:center; gap:4px }
.id-tl-note {
    font-size:12px; color:#374151; line-height:1.5;
    padding:8px 12px; background:#f8fafc; border:1px solid rgba(0,0,0,.04);
    border-radius:8px; margin-top:4px
}

.id-empty-notes { text-align:center; padding:30px 18px; color:var(--muted); font-size:12px }
.id-empty-notes i { font-size:22px; opacity:.25; display:block; margin-bottom:6px }

/* Modal */
.id-modal .modal-content { border-radius:14px; border:1px solid var(--border); font-family:inherit }
.id-modal .modal-header { border-bottom:1px solid var(--border); padding:14px 20px; background:linear-gradient(135deg,#059669,#10b981); border-radius:14px 14px 0 0 }
.id-modal .modal-title { font-size:14px; font-weight:800; color:#fff }
.id-modal .modal-body { padding:18px 20px }
.id-modal .modal-footer { border-top:1px solid var(--border); padding:12px 20px }
.id-modal .form-label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px }
.id-modal .form-control, .id-modal .form-select { border-radius:8px; border:1px solid var(--border); font-size:12px; font-family:inherit; padding:8px 12px }
.id-modal .form-control:focus, .id-modal .form-select:focus { border-color:var(--indigo-400); box-shadow:0 0 0 3px rgba(99,102,241,.1) }
.id-section-divider { font-size:11px; font-weight:800; color:var(--indigo-600); text-transform:uppercase; letter-spacing:.5px; margin:14px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border) }

/* Edit modal */
.id-edit-modal .modal-header { background:linear-gradient(135deg,var(--indigo-600),var(--accent)) }

@media (max-width:768px) {
    .id-shell { grid-template-columns:1fr }
    .id-panel { position:static }
}
</style>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(5,150,105,.2)">
    <i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(220,38,38,.2)">
    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="id-header">
    <div class="id-breadcrumb">
        <a href="inquiries.php"><i class="fas fa-clipboard-list" style="font-size:9px"></i> Leads</a>
        <span>›</span>
        <span><?= htmlspecialchars($inquiry['name']) ?></span>
    </div>
    <h4 class="id-title"><i class="fas fa-user"></i> Lead Details</h4>
</div>

<!-- Main Shell -->
<div class="id-shell">

    <!-- ━━━ LEFT PANEL ━━━ -->
    <div class="id-panel">
        <!-- Avatar header -->
        <div class="id-panel-hd">
            <div class="id-avatar" onclick="document.getElementById('photoInput').click()">
                <?php if ($hasPhoto): ?><img src="<?= htmlspecialchars($inquiry['photo']) ?>" alt="">
                <?php else: ?><?= $initials ?><?php endif; ?>
            </div>
            <div class="id-name"><?= htmlspecialchars($inquiry['name']) ?></div>
            <div class="id-course-tag"><?= htmlspecialchars($inquiry['course_interested'] ?? '—') ?></div>
            <div class="id-photo-btns">
                <form method="POST" enctype="multipart/form-data" id="photoForm" style="display:none">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/gif" onchange="document.getElementById('photoForm').submit()">
                </form>
                <button class="id-photo-btn" onclick="document.getElementById('photoInput').click()">
                    <i class="fas fa-camera"></i> <?= $hasPhoto ? 'Change' : 'Upload' ?>
                </button>
            </div>
        </div>

        <!-- Info rows -->
        <div class="id-panel-body">
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-phone-alt"></i> Mobile</span>
                <span class="id-info-val"><a href="tel:<?= $inquiry['mobile'] ?>"><?= htmlspecialchars($inquiry['mobile']) ?></a></span>
            </div>
            <?php if ($inquiry['email']): ?>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-envelope"></i> Email</span>
                <span class="id-info-val" style="font-size:11px"><?= htmlspecialchars($inquiry['email']) ?></span>
            </div>
            <?php endif; ?>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-book-open"></i> Course</span>
                <span class="id-info-val"><?= htmlspecialchars($inquiry['course_interested']) ?></span>
            </div>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-tag"></i> Source</span>
                <span class="id-info-val"><span class="id-badge" style="background:#f8fafc;color:var(--muted);border:1px solid var(--border)"><?= ucfirst($inquiry['source']) ?></span></span>
            </div>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-flag"></i> Status</span>
                <span class="id-info-val"><span class="id-badge <?= $statusCls ?>"><?= ucfirst($inquiry['status']) ?></span></span>
            </div>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-calendar"></i> Created</span>
                <span class="id-info-val" style="font-size:11px"><?= date('d M Y, h:i A', strtotime($inquiry['created_at'])) ?></span>
            </div>
            <?php if ($inquiry['created_by_name']): ?>
            <div class="id-info-row">
                <span class="id-info-lbl"><i class="fas fa-user"></i> By</span>
                <span class="id-info-val"><?= htmlspecialchars($inquiry['created_by_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($inquiry['message']): ?>
            <div style="padding:8px 0;font-size:12px;color:#374151;border-top:1px solid rgba(0,0,0,.05);margin-top:4px">
                <div style="font-size:10px;color:var(--muted);font-weight:600;margin-bottom:3px"><i class="fas fa-comment" style="font-size:9px"></i> Message</div>
                <?= nl2br(htmlspecialchars($inquiry['message'])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Follow-up scheduling -->
        <div class="id-section">
            <div class="id-section-title"><i class="fas fa-clock"></i> Follow-up</div>
            <?php if ($hasFu): ?>
            <div class="id-fu-display <?= $fuOverdue ? 'id-fu-overdue' : 'id-fu-ok' ?>">
                <i class="fas <?= $fuOverdue ? 'fa-exclamation-triangle' : 'fa-calendar-check' ?>"></i>
                <?= date('d M Y, h:i A', strtotime($inquiry['followup_at'])) ?>
                <?php if ($fuOverdue): ?><span style="margin-left:auto;font-size:9px;font-weight:800;text-transform:uppercase">Overdue</span><?php endif; ?>
            </div>
            <?php else: ?>
            <div class="id-fu-display id-fu-none"><i class="fas fa-clock"></i> No follow-up scheduled</div>
            <?php endif; ?>
            <form method="POST" class="id-fu-form">
                <input type="hidden" name="action" value="update_followup">
                <input type="date" name="followup_date" value="<?= $hasFu ? date('Y-m-d', strtotime($inquiry['followup_at'])) : '' ?>" required>
                <input type="time" name="followup_time" value="<?= $hasFu ? date('H:i', strtotime($inquiry['followup_at'])) : '10:00' ?>">
                <button type="submit" class="id-fu-btn"><i class="fas fa-check"></i></button>
            </form>
        </div>

        <!-- Status update -->
        <div class="id-section">
            <div class="id-section-title"><i class="fas fa-exchange-alt"></i> Update Status</div>
            <form method="POST" class="id-status-form">
                <input type="hidden" name="action" value="update_status">
                <select name="status">
                    <option value="new" <?= $inquiry['status'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="contacted" <?= $inquiry['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                    <option value="followup" <?= $inquiry['status'] === 'followup' ? 'selected' : '' ?>>Follow-up</option>
                    <option value="converted" <?= $inquiry['status'] === 'converted' ? 'selected' : '' ?>>Converted</option>
                    <option value="closed" <?= $inquiry['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
                <button type="submit" class="id-status-btn">Update Status</button>
            </form>
        </div>

        <!-- Enroll / Converted -->
        <div class="id-section">
            <?php if ($converted_student): ?>
            <div class="id-converted-alert">
                <i class="fas fa-check-circle"></i> <strong>Enrolled</strong><br>
                <?php if (in_array($_SESSION['admin_role'], ['Super Admin', 'Admin'])): ?>
                <a href="student_details.php?id=<?= $converted_student['id'] ?>"><?= $converted_student['full_name'] ?> (<?= $converted_student['student_code'] ?>)</a>
                <?php else: ?>
                <?= $converted_student['full_name'] ?> (<?= $converted_student['student_code'] ?>)
                <?php endif; ?>
            </div>
            <?php elseif ($inquiry['status'] !== 'converted'): ?>
            <button class="id-enroll-btn" data-bs-toggle="modal" data-bs-target="#enrollStudentModal">
                <i class="fas fa-user-plus"></i> Enroll as Student
            </button>
            <?php endif; ?>
        </div>

        <!-- Edit lead -->
        <div class="id-section" style="padding-bottom:14px">
            <button class="id-edit-btn" data-bs-toggle="modal" data-bs-target="#editLeadModal">
                <i class="fas fa-pencil-alt"></i> Edit Lead Info
            </button>
        </div>
    </div>

    <!-- ━━━ RIGHT PANEL — Notes ━━━ -->
    <div class="id-notes-card">
        <div class="id-notes-hd"><i class="fas fa-sticky-note"></i> Follow-up Notes</div>
        
        <!-- Add note form -->
        <form method="POST" class="id-note-form">
            <input type="hidden" name="action" value="add_note">
            <textarea name="note" required placeholder="Add a follow-up note..."></textarea>
            <button type="submit" class="id-note-submit"><i class="fas fa-plus"></i> Add Note</button>
        </form>

        <!-- Timeline -->
        <?php if ($notes->num_rows > 0): ?>
        <div class="id-timeline">
            <?php while ($note = $notes->fetch_assoc()): ?>
            <div class="id-tl-item">
                <div class="id-tl-dot"><i class="fas fa-comment"></i></div>
                <div class="id-tl-content">
                    <div class="id-tl-header">
                        <span class="id-tl-author"><?= htmlspecialchars($note['created_by_name']) ?></span>
                        <span class="id-tl-time"><i class="fas fa-clock"></i> <?= date('d M Y, h:i A', strtotime($note['created_at'])) ?></span>
                    </div>
                    <div class="id-tl-note"><?= nl2br(htmlspecialchars($note['note'])) ?></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="id-empty-notes"><i class="fas fa-sticky-note"></i>No notes added yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade id-modal id-edit-modal" id="editLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pencil-alt"></i> Edit Lead</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_inquiry">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Name *</label><input type="text" class="form-control" name="name" value="<?= htmlspecialchars($inquiry['name']) ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Mobile *</label><input type="tel" class="form-control" name="mobile" value="<?= htmlspecialchars($inquiry['mobile']) ?>" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($inquiry['email']) ?>"></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course *</label>
                            <select class="form-select" name="course_interested" required>
                                <?php $courses_list->data_seek(0); while ($c = $courses_list->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= $c['name'] === $inquiry['course_interested'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source</label>
                        <select class="form-select" name="source">
                            <?php foreach (['manual','phone','walkin','referral','website'] as $s): ?>
                            <option value="<?= $s ?>" <?= $inquiry['source'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="2"><?= htmlspecialchars($inquiry['message']) ?></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:12px">Cancel</button>
                    <button type="submit" style="padding:7px 16px;border:none;border-radius:8px;font-size:12px;font-weight:700;background:var(--indigo-600);color:#fff;cursor:pointer;font-family:inherit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enroll Student Modal -->
<div class="modal fade id-modal" id="enrollStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Enroll Student from Lead</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="enrollmentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="enroll_student">
                    
                    <div class="id-section-divider">Personal Information</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($inquiry['name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($inquiry['mobile']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($inquiry['email']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="birthdate">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="id-section-divider">Course & Fees</div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category_id" id="enroll_category" required>
                            <option value="">Select Category</option>
                            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select class="form-select" name="course_id" id="enroll_course" required>
                            <option value="">Select Course</option>
                            <?php $all_courses->data_seek(0); while ($course = $all_courses->fetch_assoc()): ?>
                            <option value="<?= $course['id'] ?>" data-category="<?= $course['category_id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted" id="enroll_course_help">Choose a course to see available durations</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duration *</label>
                            <select class="form-select" name="duration_months" id="enroll_duration" required>
                                <option value="">Select Duration</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Fees *</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:8px 0 0 8px;font-size:12px">₹</span>
                                <input type="number" class="form-control" name="total_fees" id="enroll_total_fees" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Enrollment Date *</label>
                            <input type="date" class="form-control" name="enrollment_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:12px">Cancel</button>
                    <button type="submit" class="id-enroll-btn" style="width:auto;padding:8px 20px"><i class="fas fa-check"></i> Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Category change - filter courses
document.getElementById('enroll_category')?.addEventListener('change', function() {
    const categoryId = this.value;
    const courseSelect = document.getElementById('enroll_course');
    const allOptions = courseSelect.querySelectorAll('option');
    
    allOptions.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (categoryId === '' || option.dataset.category === categoryId) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
    
    courseSelect.value = '';
    document.getElementById('enroll_duration').innerHTML = '<option value="">Select Duration</option>';
    document.getElementById('enroll_total_fees').value = '';
});

// Course change - load fees
document.getElementById('enroll_course')?.addEventListener('change', function() {
    const courseId = this.value;
    const durationSelect = document.getElementById('enroll_duration');
    const feesInput = document.getElementById('enroll_total_fees');
    const courseHelp = document.getElementById('enroll_course_help');

    durationSelect.innerHTML = '<option value="">Select Duration</option>';
    feesInput.value = '';

    if (!courseId) return;

    fetch(`ajax/get_course_fees.php?course_id=${courseId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.fees) && data.fees.length > 0) {
                data.fees.forEach(fee => {
                    const opt = document.createElement('option');
                    opt.value = fee.duration_months;
                    opt.textContent = `${fee.duration_months} Months`;
                    opt.dataset.fee = fee.fee_amount;
                    durationSelect.appendChild(opt);
                });
                courseHelp.textContent = 'Durations loaded successfully';
                courseHelp.className = 'text-success';
            } else {
                [3, 6, 9, 12, 18, 24].forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = `${m} Months`;
                    durationSelect.appendChild(opt);
                });
                courseHelp.textContent = 'No preset fees. Please enter custom amount.';
                courseHelp.className = 'text-warning';
            }
        })
        .catch(err => {
            console.error('AJAX error:', err);
            [3, 6, 9, 12, 18, 24].forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = `${m} Months`;
                durationSelect.appendChild(opt);
            });
            courseHelp.textContent = 'Error loading durations';
            courseHelp.className = 'text-danger';
        });
});

// Duration change - auto-fill fees
document.getElementById('enroll_duration')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (!selected) return;
    const fee = selected.dataset.fee;
    const feesInput = document.getElementById('enroll_total_fees');
    const courseHelp = document.getElementById('enroll_course_help');
    if (fee) {
        feesInput.value = fee;
        courseHelp.textContent = 'Fee auto-filled. You can adjust it.';
        courseHelp.className = 'text-success';
    } else {
        feesInput.value = '';
        feesInput.focus();
        courseHelp.textContent = 'Please enter total fees manually.';
        courseHelp.className = 'text-info';
    }
});
</script>

<?php include 'includes/footer.php'; ?>