<?php
/**
 * Student Projects Page
 * 
 * Projects are submitted with verification_status = 'pending'.
 * Points are NOT awarded here — only after admin verification.
 * Shows verification status badges and awarded points for each project.
 */
ini_set('display_errors', 0); error_reporting(0);
include 'includes/header.php';

$success=''; $error='';

// ═══ Handle AJAX POST (Add / Edit) ═══
// NOTE: verification_status is always 'pending' on new submissions — no auto-verify
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $link  = trim($_POST['link'] ?? '');
        if (!empty($title) && !empty($link)) {
            $today = date('Y-m-d');
            // Insert with verification_status = 'pending' — NO auto-verification
            $stmt  = $conn->prepare("INSERT INTO student_projects
                (student_id, project_name, description, project_link, start_date, end_date, status, verification_status)
                VALUES (?,?,?,?,?,?,'In Progress','pending')");
            $stmt->bind_param("isssss", $sid, $title, $desc, $link, $today, $today);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'edit') {
        $pid   = (int)($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $link  = trim($_POST['link'] ?? '');
        if (!empty($title) && !empty($link) && $pid > 0) {
            // Editing a project resets verification to pending (needs re-verification)
            $stmt = $conn->prepare("UPDATE student_projects SET project_name=?, description=?, project_link=?, verification_status='pending' WHERE id=? AND student_id=?");
            $stmt->bind_param("sssii", $title, $desc, $link, $pid, $sid);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle delete via prepared statement (fallback for non-JS)
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];

    // If project had points, subtract them before deleting
    $chk = $conn->prepare("SELECT points_awarded FROM student_projects WHERE id=? AND student_id=?");
    $chk->bind_param("ii", $pid, $sid);
    $chk->execute();
    $chk_data = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($chk_data && $chk_data['points_awarded'] > 0) {
        $sub = $conn->prepare("UPDATE students SET points = GREATEST(0, points - ?) WHERE id = ?");
        $sub->bind_param("di", $chk_data['points_awarded'], $sid);
        $sub->execute();
        $sub->close();
    }

    $del = $conn->prepare("DELETE FROM student_projects WHERE id=? AND student_id=?");
    $del->bind_param("ii", $pid, $sid);
    $del->execute();
    $del->close();
    header('Location: projects.php?msg=deleted'); exit;
}

// ═══ Fetch ALL projects for this student ═══
$pstmt = $conn->prepare("SELECT * FROM student_projects WHERE student_id=? ORDER BY created_at DESC");
$pstmt->bind_param("i", $sid);
$pstmt->execute();
$projects = $pstmt->get_result();
$total    = $projects ? $projects->num_rows : 0;
$msg      = $_GET['msg'] ?? '';

// Count by verification status
$pending_count = 0; $verified_count = 0; $rejected_count = 0;
$all_projects = [];
if ($projects && $projects->num_rows > 0) {
    while ($p = $projects->fetch_assoc()) {
        $vs = $p['verification_status'] ?? 'pending';
        if ($vs === 'pending') $pending_count++;
        elseif ($vs === 'verified') $verified_count++;
        elseif ($vs === 'rejected') $rejected_count++;
        $all_projects[] = $p;
    }
}
?>

<?php if ($msg === 'added'): ?>
<div class="alert-block alert-block-success fade-up">
  <i class="fas fa-check-circle"></i> <span>Project submitted! Waiting for admin verification.</span>
  <button class="btn-close-alert">✕</button>
</div>
<?php elseif ($msg === 'updated'): ?>
<div class="alert-block alert-block-success fade-up">
  <i class="fas fa-check-circle"></i> <span>Project updated! It will need re-verification.</span>
  <button class="btn-close-alert">✕</button>
</div>
<?php elseif ($msg === 'deleted'): ?>
<div class="alert-block alert-block-warning fade-up">
  <i class="fas fa-trash"></i> <span>Project deleted.</span>
  <button class="btn-close-alert">✕</button>
</div>
<?php elseif ($msg === 'synced'): ?>
<div class="alert-block alert-block-success fade-up">
  <i class="fas fa-sync"></i> <span>Project auto-synced from task completion!</span>
  <button class="btn-close-alert">✕</button>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-layer-group" style="font-size:16px;-webkit-text-fill-color:var(--purple-light);"></i> My Projects</h2>
    <p>Showcase your work and achievements</p>
  </div>
  <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="fas fa-plus"></i> Add Project
  </button>
</div>

<!-- STATS -->
<div class="stats-grid fade-up delay-1" style="grid-template-columns:repeat(4,1fr);max-width:600px;">
  <div class="stat-card s-purple">
    <div class="stat-card-info">
      <div class="stat-card-label">Total</div>
      <div class="stat-card-value"><?php echo $total; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-layer-group"></i></div>
  </div>
  <div class="stat-card" style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);">
    <div class="stat-card-info">
      <div class="stat-card-label">Pending</div>
      <div class="stat-card-value" style="color:#f59e0b;"><?php echo $pending_count; ?></div>
    </div>
    <div class="stat-card-icon" style="color:#f59e0b;"><i class="fas fa-clock"></i></div>
  </div>
  <div class="stat-card" style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);">
    <div class="stat-card-info">
      <div class="stat-card-label">Verified</div>
      <div class="stat-card-value" style="color:#10b981;"><?php echo $verified_count; ?></div>
    </div>
    <div class="stat-card-icon" style="color:#10b981;"><i class="fas fa-check-circle"></i></div>
  </div>
  <div class="stat-card" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);">
    <div class="stat-card-info">
      <div class="stat-card-label">Rejected</div>
      <div class="stat-card-value" style="color:#ef4444;"><?php echo $rejected_count; ?></div>
    </div>
    <div class="stat-card-icon" style="color:#ef4444;"><i class="fas fa-times-circle"></i></div>
  </div>
</div>

<!-- PROJECTS GRID -->
<?php if (!empty($all_projects)): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;" class="fade-up delay-2" id="projects-grid">
  <?php foreach ($all_projects as $p): ?>
  <div class="project-card">
    <div class="project-card-header">
      <div class="project-card-icon"><i class="fas fa-folder-open"></i></div>
      <?php
        // Verification status badge (primary indicator)
        $vs = $p['verification_status'] ?? 'pending';
        $vsClass = 'badge-pending';
        $vsLabel = 'Waiting for Verification';
        $vsIcon  = '<i class="fas fa-clock"></i> ';
        if ($vs === 'verified') {
            $vsClass = 'badge-verified';
            $vsLabel = 'Verified · +' . ($p['points_awarded'] + 0) . ' pts';
            $vsIcon  = '<i class="fas fa-check-circle"></i> ';
        } elseif ($vs === 'rejected') {
            $vsClass = 'badge-rejected';
            $vsLabel = 'Rejected';
            $vsIcon  = '<i class="fas fa-times-circle"></i> ';
        }
      ?>
      <span class="project-status-badge <?= $vsClass ?>"><?= $vsIcon . htmlspecialchars($vsLabel) ?></span>
    </div>
    <div class="project-card-body">
      <div class="project-card-title">
        <?php if (!empty($p['project_link'])): ?>
        <a href="<?php echo htmlspecialchars($p['project_link']); ?>" target="_blank" rel="noopener">
          <?php echo htmlspecialchars($p['project_name']); ?>
          <i class="fas fa-external-link-alt" style="font-size:11px;margin-left:4px;opacity:0.7;"></i>
        </a>
        <?php else: ?>
          <?php echo htmlspecialchars($p['project_name']); ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($p['description'])): ?>
      <div class="project-card-desc"><?php echo nl2br(htmlspecialchars($p['description'])); ?></div>
      <?php endif; ?>

      <!-- Rejection reason -->
      <?php if ($vs === 'rejected' && !empty($p['rejection_reason'])): ?>
      <div class="rejection-reason">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($p['rejection_reason']) ?>
      </div>
      <?php endif; ?>

      <!-- Points badge for verified projects -->
      <?php if ($vs === 'verified' && $p['points_awarded'] > 0): ?>
      <div class="points-awarded-badge">
        <i class="fas fa-star"></i> <?= $p['points_awarded'] + 0 ?> Points Earned
      </div>
      <?php endif; ?>

      <div style="margin-top:10px;font-size:11px;color:var(--text-dim);">
        <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($p['created_at'])); ?>
        <?php if ($vs === 'verified' && !empty($p['verified_at'])): ?>
          · <i class="fas fa-check"></i> Verified <?= date('d M Y', strtotime($p['verified_at'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="project-card-footer">
      <?php if ($vs !== 'verified'): ?>
      <button class="btn btn-outline-purple btn-sm" style="flex:1;" onclick='editProject(<?php echo json_encode($p); ?>)'>
        <i class="fas fa-edit"></i> Edit
      </button>
      <?php endif; ?>
      <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-red" style="flex:1;"
         onclick="return confirm('Delete this project?')">
        <i class="fas fa-trash"></i> Delete
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="glass-card fade-up delay-2">
  <div class="glass-card-body" style="text-align:center;padding:40px 20px;">
    <i class="fas fa-layer-group" style="font-size:40px;color:var(--text-dim);margin-bottom:14px;display:block;"></i>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;">No Projects Yet</div>
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Showcase your work by adding your first project!</div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="fas fa-plus"></i> Add Your First Project
    </button>
  </div>
</div>
<?php endif; ?>

<style>
/* ── Verification Status Badges ── */
.project-status-badge {
  font-size:9.5px; font-weight:700; padding:3px 9px; border-radius:99px;
  text-transform:uppercase; letter-spacing:.4px;
  display:inline-flex; align-items:center; gap:3px;
}
.badge-verified { background:rgba(16,185,129,0.15); color:#10b981; }
.badge-pending  { background:rgba(245,158,11,0.15); color:#f59e0b; }
.badge-rejected { background:rgba(239,68,68,0.15); color:#ef4444; }
.badge-success  { background:rgba(16,185,129,0.15); color:#10b981; }
.badge-info     { background:rgba(59,130,246,0.15); color:#3b82f6; }
.badge-warning  { background:rgba(245,158,11,0.15); color:#f59e0b; }
.badge-muted    { background:rgba(148,163,184,0.15); color:#94a3b8; }

/* Points earned badge */
.points-awarded-badge {
  display:inline-flex; align-items:center; gap:5px;
  margin-top:8px; padding:4px 10px; border-radius:8px;
  background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2);
  font-size:11px; font-weight:700; color:#10b981;
}

/* Rejection reason */
.rejection-reason {
  margin-top:8px; padding:6px 10px; border-radius:8px;
  background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.15);
  font-size:11px; color:#f87171; line-height:1.4;
}
.rejection-reason i { margin-right:3px; }
</style>

<!-- ADD MODAL (AJAX — Part 5) -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addProjectForm">
        <div class="modal-body">
          <div class="form-hint" style="margin-bottom:12px;padding:8px 12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;font-size:11px;color:#f59e0b;">
            <i class="fas fa-info-circle"></i> Your project will be submitted for admin verification. Points will be awarded after verification.
          </div>
          <div class="form-group">
            <label class="form-label">Project Title *</label>
            <input type="text" class="form-control" id="add_title" required placeholder="E.g., E-commerce Website">
          </div>
          <div class="form-group">
            <label class="form-label">Project Link *</label>
            <input type="url" class="form-control" id="add_link" required placeholder="https://github.com/...">
            <div class="form-hint">Clicking the title will open this URL in a new tab</div>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="add_desc" rows="3" placeholder="Brief description of the project..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-purple" id="add_submit_btn"><i class="fas fa-save"></i> Submit for Verification</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL (AJAX — Part 5) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editProjectForm">
        <div class="modal-body">
          <div class="form-hint" style="margin-bottom:12px;padding:8px 12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;font-size:11px;color:#f59e0b;">
            <i class="fas fa-info-circle"></i> Editing will reset verification status to pending.
          </div>
          <input type="hidden" id="edit_id">
          <div class="form-group">
            <label class="form-label">Project Title *</label>
            <input type="text" class="form-control" id="edit_title" required>
          </div>
          <div class="form-group">
            <label class="form-label">Project Link *</label>
            <input type="url" class="form-control" id="edit_link" required>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="edit_desc" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-purple" id="edit_submit_btn"><i class="fas fa-save"></i> Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editProject(p) {
  document.getElementById('edit_id').value    = p.id;
  document.getElementById('edit_title').value = p.project_name;
  document.getElementById('edit_desc').value  = p.description || '';
  document.getElementById('edit_link').value  = p.project_link || '';
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

// ADD form AJAX
document.getElementById('addProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn   = document.getElementById('add_submit_btn');
    const title = document.getElementById('add_title').value.trim();
    const link  = document.getElementById('add_link').value.trim();
    const desc  = document.getElementById('add_desc').value.trim();

    if (!title || !link) { alert('Title and Link are required'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('title', title);
    fd.append('link', link);
    fd.append('description', desc);

    fetch('projects.php', { method: 'POST', body: fd })
        .then(() => {
            bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
            location.href = 'projects.php?msg=added';
        })
        .catch(() => alert('Network error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Submit for Verification';
        });
});

// EDIT form AJAX
document.getElementById('editProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn   = document.getElementById('edit_submit_btn');
    const pid   = document.getElementById('edit_id').value;
    const title = document.getElementById('edit_title').value.trim();
    const link  = document.getElementById('edit_link').value.trim();
    const desc  = document.getElementById('edit_desc').value.trim();

    if (!title || !link) { alert('Title and Link are required'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    const fd = new FormData();
    fd.append('action', 'edit');
    fd.append('project_id', pid);
    fd.append('title', title);
    fd.append('link', link);
    fd.append('description', desc);

    fetch('projects.php', { method: 'POST', body: fd })
        .then(() => {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            location.href = 'projects.php?msg=updated';
        })
        .catch(() => alert('Network error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Update';
        });
});
</script>

<?php include 'includes/footer.php'; ?>