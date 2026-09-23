<?php
session_start();
require_once './config/database.php';
require_once './config/auth.php';
requireLogin();

// Administrator can only access inquiries
if ($_SESSION['admin_role'] === 'Administrator') {
    // Allowed
} elseif (!in_array($_SESSION['admin_role'], ['Super Admin', 'Admin'])) {
    $_SESSION['error'] = "Access denied.";
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = sanitize($_POST['name']);
        $mobile = sanitize($_POST['mobile']);
        $email = sanitize($_POST['email']);
        $course_interested = sanitize($_POST['course_interested']);
        $message = sanitize($_POST['message']);
        $source = sanitize($_POST['source']);
        $created_by = $_SESSION['admin_id'];

        // Build followup_at from date + time inputs
        $followup_at = null;
        if (!empty($_POST['followup_date'])) {
            $fu_date = sanitize($_POST['followup_date']);
            $fu_time = !empty($_POST['followup_time']) ? sanitize($_POST['followup_time']) : '10:00';
            $followup_at = $fu_date . ' ' . $fu_time . ':00';
        }

        // Handle optional photo upload
        $photo_path = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/inquiries/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
            if (in_array($_FILES['photo']['type'], $allowed) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'inquiry_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    $photo_path = 'uploads/inquiries/' . $filename;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO inquiries (name, mobile, email, course_interested, message, source, followup_at, photo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssi", $name, $mobile, $email, $course_interested, $message, $source, $followup_at, $photo_path, $created_by);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Lead added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add lead.";
        }
        $stmt->close();
        header('Location: inquiries.php');
        exit();
    }

    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $status = sanitize($_POST['status']);

        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update status.";
        }
        $stmt->close();
        header('Location: inquiry_details.php?id=' . $id);
        exit();
    }
}

// Handle delete - Delete inquiry only if NOT converted
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if converted to student
    $check = $conn->query("SELECT id FROM students WHERE inquiry_id = $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete. This inquiry has been converted to a student.";
    } else {
        // Delete photo file if exists
        $pRow = $conn->query("SELECT photo FROM inquiries WHERE id = $id")->fetch_assoc();
        if ($pRow && !empty($pRow['photo']) && file_exists($pRow['photo'])) unlink($pRow['photo']);
        $conn->query("DELETE FROM inquiries WHERE id = $id");
        $_SESSION['success'] = "Lead deleted successfully!";
    }
    
    header('Location: inquiries.php');
    exit();
}

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$source_filter = isset($_GET['source']) ? $_GET['source'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where = "1=1";
if ($status_filter) $where .= " AND i.status = '$status_filter'";
if ($source_filter) $where .= " AND i.source = '$source_filter'";
if ($start_date) $where .= " AND DATE(i.created_at) >= '$start_date'";
if ($end_date) $where .= " AND DATE(i.created_at) <= '$end_date'";

$inquiries = $conn->query("
    SELECT i.*, a.full_name as created_by_name 
    FROM inquiries i
    LEFT JOIN admins a ON i.created_by = a.id
    WHERE $where
    ORDER BY i.created_at DESC
");

// Stats
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM inquiries")->fetch_assoc()['count'];
$stats['new'] = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'")->fetch_assoc()['count'];
$stats['contacted'] = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'contacted'")->fetch_assoc()['count'];
$stats['converted'] = $conn->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'converted'")->fetch_assoc()['count'];

// Get active courses for dropdown
$courses = $conn->query("SELECT id, name FROM courses WHERE status = 'Active' ORDER BY name");

include 'includes/header.php';
?>

<style>
/* ── Leads Page — matches dashboard design system ── */
.ld-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px }
.ld-header h2 { font-size:18px; font-weight:800; color:var(--text); margin:0; display:flex; align-items:center; gap:8px }
.ld-header h2 i { color:var(--indigo-600); font-size:16px }
.ld-header p { font-size:12px; color:var(--muted); margin:2px 0 0 }
.ld-btn-add {
    background:linear-gradient(135deg,var(--indigo-600),var(--accent)); color:#fff;
    border:none; border-radius:10px; padding:8px 18px; font-size:12px; font-weight:700;
    cursor:pointer; transition:all .18s; display:inline-flex; align-items:center; gap:6px;
    font-family:inherit; text-decoration:none
}
.ld-btn-add:hover { box-shadow:0 4px 16px rgba(79,70,229,.3); transform:translateY(-1px); color:#fff }

/* Stats */
.ld-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px }
.ld-stat {
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    padding:14px 12px; display:flex; align-items:center; gap:10px;
    box-shadow:0 1px 3px rgba(0,0,0,.04); transition:all .18s
}
.ld-stat:hover { box-shadow:0 4px 14px rgba(0,0,0,.08); transform:translateY(-1px) }
.ld-stat-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0 }
.ld-stat-val { font-size:22px; font-weight:800; line-height:1 }
.ld-stat-lbl { font-size:10px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-top:2px }

/* Filter */
.ld-filter-card {
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    padding:14px 16px; margin-bottom:14px; box-shadow:0 1px 3px rgba(0,0,0,.04)
}
.ld-filter-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap }
.ld-filter-grp { flex:1; min-width:120px }
.ld-filter-grp label { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; display:block }
.ld-filter-grp select, .ld-filter-grp input {
    width:100%; padding:7px 10px; border:1px solid var(--border); border-radius:8px;
    font-size:12px; font-family:inherit; background:#fff; color:var(--text); transition:var(--ease)
}
.ld-filter-grp select:focus, .ld-filter-grp input:focus { outline:none; border-color:var(--indigo-400); box-shadow:0 0 0 3px rgba(99,102,241,.1) }
.ld-filter-btn {
    padding:7px 16px; border:none; border-radius:8px; font-size:12px; font-weight:700;
    cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:5px;
    background:var(--indigo-600); color:#fff; transition:all .18s
}
.ld-filter-btn:hover { background:var(--indigo-700) }
.ld-filter-reset { background:transparent; color:var(--muted); border:1px solid var(--border); padding:7px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; transition:var(--ease) }
.ld-filter-reset:hover { background:#f8fafc; color:var(--text) }

/* Table */
.ld-table-card {
    background:var(--surface); border:1px solid var(--border); border-radius:12px;
    box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden
}
.ld-search-wrap { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px }
.ld-search-wrap i { color:var(--muted); font-size:12px }
.ld-search-wrap input {
    border:none; outline:none; font-size:12px; font-family:inherit; flex:1;
    color:var(--text); background:transparent
}
.ld-table { width:100%; border-collapse:collapse; font-size:12px }
.ld-table th {
    padding:10px 14px; text-align:left; font-size:10px; font-weight:700;
    color:var(--muted); text-transform:uppercase; letter-spacing:.4px;
    background:#fafbfc; border-bottom:1px solid var(--border)
}
.ld-table td { padding:10px 14px; border-bottom:1px solid rgba(0,0,0,.04); vertical-align:middle }
.ld-table tbody tr { cursor:pointer; transition:background .12s }
.ld-table tbody tr:hover td { background:rgba(99,102,241,.03) }
.ld-table tbody tr:last-child td { border-bottom:none }

.ld-av-sm { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; background:linear-gradient(135deg,var(--indigo-600),var(--accent)) }
.ld-av-sm img { width:100%; height:100%; object-fit:cover; border-radius:50% }

.ld-name-cell { display:flex; align-items:center; gap:9px }
.ld-name-text { font-weight:700; color:var(--text) }

.ld-badge {
    display:inline-flex; align-items:center; gap:3px; padding:2px 8px;
    border-radius:99px; font-size:10px; font-weight:700; letter-spacing:.2px
}
.ld-badge-new { background:#fffbeb; color:#d97706; border:1px solid rgba(217,119,6,.2) }
.ld-badge-contacted { background:#eff6ff; color:#2563eb; border:1px solid rgba(37,99,235,.2) }
.ld-badge-followup { background:#f5f3ff; color:#7c3aed; border:1px solid rgba(124,58,237,.2) }
.ld-badge-converted { background:#f0fdf4; color:#059669; border:1px solid rgba(5,150,105,.2) }
.ld-badge-closed { background:#fef2f2; color:#dc2626; border:1px solid rgba(220,38,38,.2) }
.ld-badge-src { background:#f8fafc; color:var(--muted); border:1px solid var(--border) }

.ld-followup-cell { font-size:11px; color:var(--muted) }
.ld-followup-cell .overdue { color:#dc2626; font-weight:700 }

.ld-actions { display:flex; gap:4px }
.ld-act-btn {
    width:28px; height:28px; border-radius:8px; border:1px solid var(--border);
    background:#fff; display:flex; align-items:center; justify-content:center;
    font-size:11px; cursor:pointer; transition:all .15s; color:var(--muted); text-decoration:none
}
.ld-act-btn:hover { background:var(--indigo-100); color:var(--indigo-700); border-color:var(--indigo-400) }
.ld-act-btn.danger:hover { background:#fef2f2; color:#dc2626; border-color:rgba(220,38,38,.3) }

/* Modal overrides */
.ld-modal .modal-content { border-radius:14px; border:1px solid var(--border); font-family:inherit }
.ld-modal .modal-header { border-bottom:1px solid var(--border); padding:14px 20px; background:linear-gradient(135deg,var(--indigo-600),var(--accent)); border-radius:14px 14px 0 0 }
.ld-modal .modal-title { font-size:14px; font-weight:800; color:#fff; display:flex; align-items:center; gap:8px }
.ld-modal .modal-body { padding:18px 20px }
.ld-modal .modal-footer { border-top:1px solid var(--border); padding:12px 20px }
.ld-modal .form-label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px }
.ld-modal .form-control, .ld-modal .form-select { border-radius:8px; border:1px solid var(--border); font-size:12px; font-family:inherit; padding:8px 12px }
.ld-modal .form-control:focus, .ld-modal .form-select:focus { border-color:var(--indigo-400); box-shadow:0 0 0 3px rgba(99,102,241,.1) }
.ld-section-title { font-size:11px; font-weight:800; color:var(--indigo-600); text-transform:uppercase; letter-spacing:.5px; margin:14px 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border) }

.ld-empty { text-align:center; padding:40px 20px; color:var(--muted) }
.ld-empty i { font-size:28px; opacity:.25; display:block; margin-bottom:8px }
.ld-empty p { font-size:13px; margin:0 }

@media (max-width:768px) {
    .ld-stats { grid-template-columns:repeat(2,1fr) }
    .ld-filter-row { flex-direction:column }
    .ld-filter-grp { min-width:100% }
    .ld-table th:nth-child(3), .ld-table td:nth-child(3),
    .ld-table th:nth-child(5), .ld-table td:nth-child(5) { display:none }
}
@media (max-width:480px) { .ld-stats { grid-template-columns:1fr 1fr } }
</style>

<!-- Flash Messages -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(5,150,105,.2)">
    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;font-size:13px;border:1px solid rgba(220,38,38,.2)">
    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="ld-header">
    <div>
        <h2><i class="fas fa-clipboard-list"></i> Leads Management</h2>
        <p>Manage course inquiries and follow-ups</p>
    </div>
    <button class="ld-btn-add" data-bs-toggle="modal" data-bs-target="#addInquiryModal">
        <i class="fas fa-plus"></i> Add Lead
    </button>
</div>

<!-- Stats Cards -->
<div class="ld-stats">
    <div class="ld-stat">
        <div class="ld-stat-icon" style="background:#eef2ff;color:var(--indigo-600)"><i class="fas fa-clipboard-list"></i></div>
        <div><div class="ld-stat-val"><?= $stats['total'] ?></div><div class="ld-stat-lbl">Total Leads</div></div>
    </div>
    <div class="ld-stat">
        <div class="ld-stat-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-exclamation-circle"></i></div>
        <div><div class="ld-stat-val" style="color:#d97706"><?= $stats['new'] ?></div><div class="ld-stat-lbl">New</div></div>
    </div>
    <div class="ld-stat">
        <div class="ld-stat-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-phone-alt"></i></div>
        <div><div class="ld-stat-val"><?= $stats['contacted'] ?></div><div class="ld-stat-lbl">Contacted</div></div>
    </div>
    <div class="ld-stat">
        <div class="ld-stat-icon" style="background:#f0fdf4;color:#059669"><i class="fas fa-check-circle"></i></div>
        <div><div class="ld-stat-val" style="color:#059669"><?= $stats['converted'] ?></div><div class="ld-stat-lbl">Converted</div></div>
    </div>
</div>

<!-- Filters -->
<div class="ld-filter-card">
    <form method="GET" class="ld-filter-row">
        <div class="ld-filter-grp">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $status_filter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="followup" <?= $status_filter === 'followup' ? 'selected' : '' ?>>Follow-up</option>
                <option value="converted" <?= $status_filter === 'converted' ? 'selected' : '' ?>>Converted</option>
                <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
        <div class="ld-filter-grp">
            <label>Source</label>
            <select name="source">
                <option value="">All Sources</option>
                <option value="website" <?= $source_filter === 'website' ? 'selected' : '' ?>>Website</option>
                <option value="manual" <?= $source_filter === 'manual' ? 'selected' : '' ?>>Manual</option>
                <option value="phone" <?= $source_filter === 'phone' ? 'selected' : '' ?>>Phone</option>
                <option value="walkin" <?= $source_filter === 'walkin' ? 'selected' : '' ?>>Walk-in</option>
                <option value="referral" <?= $source_filter === 'referral' ? 'selected' : '' ?>>Referral</option>
            </select>
        </div>
        <div class="ld-filter-grp">
            <label>From</label>
            <input type="date" name="start_date" value="<?= $start_date ?>">
        </div>
        <div class="ld-filter-grp">
            <label>To</label>
            <input type="date" name="end_date" value="<?= $end_date ?>">
        </div>
        <div style="display:flex;gap:6px;align-items:center">
            <button type="submit" class="ld-filter-btn"><i class="fas fa-filter"></i> Filter</button>
            <a href="inquiries.php" class="ld-filter-reset"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="ld-table-card">
    <div class="ld-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInquiry" placeholder="Search by name, mobile, email...">
    </div>
    <div style="overflow-x:auto">
        <table class="ld-table" id="inquiriesTable">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Mobile</th>
                    <th>Course</th>
                    <th>Follow-up</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $inquiries->data_seek(0);
                while ($inquiry = $inquiries->fetch_assoc()): 
                    $statusCls = 'ld-badge-' . $inquiry['status'];
                    $is_converted = $conn->query("SELECT id FROM students WHERE inquiry_id = {$inquiry['id']}")->num_rows > 0;
                    $initials = strtoupper(substr($inquiry['name'], 0, 1));
                    $hasPhoto = !empty($inquiry['photo']) && file_exists($inquiry['photo']);
                    
                    // Follow-up display
                    $fuDisplay = '—';
                    $fuOverdue = false;
                    if (!empty($inquiry['followup_at'])) {
                        $fuTs = strtotime($inquiry['followup_at']);
                        $fuOverdue = $fuTs < time() && !in_array($inquiry['status'], ['converted','closed']);
                        $fuDisplay = date('d M, h:i A', $fuTs);
                    }
                ?>
                <tr onclick="window.location.href='inquiry_details.php?id=<?= $inquiry['id'] ?>'">
                    <td>
                        <div class="ld-name-cell">
                            <div class="ld-av-sm">
                                <?php if ($hasPhoto): ?><img src="<?= htmlspecialchars($inquiry['photo']) ?>" alt="">
                                <?php else: ?><?= $initials ?><?php endif; ?>
                            </div>
                            <span class="ld-name-text"><?= htmlspecialchars($inquiry['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($inquiry['mobile']) ?></td>
                    <td style="font-size:11px"><?= htmlspecialchars($inquiry['course_interested']) ?></td>
                    <td class="ld-followup-cell">
                        <?php if ($fuOverdue): ?><span class="overdue"><i class="fas fa-exclamation-circle"></i> <?= $fuDisplay ?></span>
                        <?php else: ?><?= $fuDisplay ?><?php endif; ?>
                    </td>
                    <td><span class="ld-badge ld-badge-src"><?= ucfirst($inquiry['source']) ?></span></td>
                    <td><span class="ld-badge <?= $statusCls ?>"><?= ucfirst($inquiry['status']) ?></span></td>
                    <td style="font-size:11px;color:var(--muted)"><?= date('d M Y', strtotime($inquiry['created_at'])) ?></td>
                    <td>
                        <div class="ld-actions" onclick="event.stopPropagation()">
                            <a href="inquiry_details.php?id=<?= $inquiry['id'] ?>" class="ld-act-btn" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (!$is_converted): ?>
                            <a href="?delete=<?= $inquiry['id'] ?>" class="ld-act-btn danger" title="Delete" onclick="return confirm('Delete this lead?')"><i class="fas fa-trash"></i></a>
                            <?php else: ?>
                            <span class="ld-act-btn" style="opacity:.4;cursor:default" title="Converted"><i class="fas fa-lock"></i></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($inquiries->num_rows === 0): ?>
    <div class="ld-empty"><i class="fas fa-clipboard-list"></i><p>No leads found</p></div>
    <?php endif; ?>
</div>

<!-- Add Lead Modal -->
<div class="modal fade ld-modal" id="addInquiryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Lead</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="ld-section-title">Contact Information</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile *</label>
                            <input type="tel" class="form-control" name="mobile" required pattern="[0-9]{10}" title="10-digit mobile number">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/gif">
                        </div>
                    </div>
                    
                    <div class="ld-section-title">Lead Details</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Course Interested *</label>
                            <select class="form-select" name="course_interested" required>
                                <option value="">Select Course</option>
                                <?php $courses->data_seek(0); while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($course['name']) ?>"><?= htmlspecialchars($course['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Source *</label>
                            <select class="form-select" name="source" required>
                                <option value="manual">Manual</option>
                                <option value="phone">Phone</option>
                                <option value="walkin">Walk-in</option>
                                <option value="referral">Referral</option>
                                <option value="website">Website</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ld-section-title">Follow-up Schedule</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" class="form-control" name="followup_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Follow-up Time</label>
                            <input type="time" class="form-control" name="followup_time" value="10:00">
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="2" placeholder="Notes about this lead..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:12px;font-weight:600">Cancel</button>
                    <button type="submit" class="ld-btn-add">Add Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('searchInquiry').addEventListener('keyup', function() {
    const filter = this.value.toUpperCase();
    const rows = document.querySelectorAll('#inquiriesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent || row.innerText;
        row.style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>