<?php
/**
 * Admin Project Verification Panel
 * 
 * Shows all pending student projects for admin verification.
 * Admin/Super Admin can verify (award points) or reject projects.
 * 
 * Points System:
 *   - Web Development/Developer course projects = 12.5 points
 *   - All other course projects (Graphic/Digital) = 5 points
 *   - Points only awarded AFTER admin verification
 *   - Duplicate prevention via points_awarded field
 */

include 'includes/header.php';

// Access control — only Admin or Super Admin
if (!$isAdm) {
    echo '<div class="alert alert-danger"><i class="fas fa-shield-halved"></i> Access denied. Admin or Super Admin required.</div>';
    include 'includes/footer.php';
    exit;
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'verified', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

// Build WHERE clause
$where = '';
if ($filter !== 'all') {
    $where = "AND sp.verification_status = '$filter'";
}

// Get all projects with student/course info
$projects_result = $conn->query("
    SELECT sp.*, 
           s.full_name AS student_name, 
           s.student_code,
           s.photo AS student_photo,
           c.name AS course_name,
           cat.name AS category_name,
           a.full_name AS verified_by_name
    FROM student_projects sp
    JOIN students s ON sp.student_id = s.id
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    LEFT JOIN admins a ON sp.verified_by = a.id
    WHERE 1=1 $where
    ORDER BY 
        FIELD(sp.verification_status, 'pending', 'rejected', 'verified'),
        sp.created_at DESC
");

$projects = [];
if ($projects_result) {
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Count stats
$counts = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM student_projects
")->fetch_assoc();
?>

<style>
/* ═══════════════════════════════════════════
   PROJECT VERIFICATION PANEL — PREMIUM UI
═══════════════════════════════════════════ */
.pv-header {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-bottom: 20px;
}
.pv-header h2 { font-size: 18px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.pv-header h2 i { color: var(--indigo-600); }
.pv-header p { font-size: 12px; color: var(--muted); margin-top: 2px; }

/* Filter tabs */
.pv-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.pv-tab {
    padding: 7px 16px; border-radius: 9px; font-size: 12px; font-weight: 600;
    text-decoration: none; transition: all .15s; border: 1.5px solid var(--border);
    color: var(--muted); background: var(--surface); display: flex; align-items: center; gap: 6px;
}
.pv-tab:hover { border-color: var(--indigo-400); color: var(--indigo-700); }
.pv-tab.active { background: var(--indigo-600); color: #fff; border-color: var(--indigo-600); }
.pv-tab .pv-tab-count {
    background: rgba(255,255,255,0.2); padding: 1px 7px; border-radius: 99px; font-size: 10px; font-weight: 700;
}
.pv-tab.active .pv-tab-count { background: rgba(255,255,255,0.3); }

/* Stats strip */
.pv-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
.pv-stat-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    padding: 14px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); transition: all .15s;
}
.pv-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
.pv-stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); }
.pv-stat-value { font-size: 22px; font-weight: 800; margin-top: 2px; }
.pv-stat-pending .pv-stat-value { color: #f59e0b; }
.pv-stat-verified .pv-stat-value { color: #10b981; }
.pv-stat-rejected .pv-stat-value { color: #ef4444; }
.pv-stat-total .pv-stat-value { color: var(--indigo-600); }

/* Project card */
.pv-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
    padding: 16px 18px; margin-bottom: 10px; transition: all .15s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.pv-card:hover { border-color: var(--indigo-400); box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
.pv-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.pv-card-student { display: flex; align-items: center; gap: 10px; }
.pv-card-avatar {
    width: 40px; height: 40px; border-radius: 10px; overflow: hidden;
    background: linear-gradient(135deg, var(--indigo-600), #7c3aed);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
}
.pv-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pv-card-name { font-size: 13px; font-weight: 700; color: var(--text); }
.pv-card-code { font-size: 10.5px; color: var(--muted); }
.pv-card-course { font-size: 10px; color: var(--indigo-600); font-weight: 600; }

/* Project info */
.pv-card-project { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }
.pv-project-name { font-size: 14px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; }
.pv-project-name a { color: var(--indigo-700); text-decoration: none; }
.pv-project-name a:hover { text-decoration: underline; }
.pv-project-desc { font-size: 12px; color: var(--muted); margin-top: 4px; line-height: 1.5; }
.pv-project-meta { display: flex; gap: 14px; margin-top: 8px; font-size: 10.5px; color: var(--muted); flex-wrap: wrap; }
.pv-project-meta span { display: flex; align-items: center; gap: 4px; }

/* Status badges */
.pv-badge {
    font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 99px;
    text-transform: uppercase; letter-spacing: .4px; display: inline-flex; align-items: center; gap: 4px;
}
.pv-badge-pending { background: rgba(245,158,11,0.12); color: #f59e0b; }
.pv-badge-verified { background: rgba(16,185,129,0.12); color: #10b981; }
.pv-badge-rejected { background: rgba(239,68,68,0.12); color: #ef4444; }

/* Action buttons */
.pv-actions { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }
.pv-btn {
    padding: 7px 14px; border-radius: 9px; font-size: 12px; font-weight: 600;
    border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
    transition: all .15s;
}
.pv-btn-verify { background: #dcfce7; color: #166534; }
.pv-btn-verify:hover { background: #166534; color: #fff; }
.pv-btn-reject { background: #fee2e2; color: #991b1b; }
.pv-btn-reject:hover { background: #991b1b; color: #fff; }
.pv-btn-view { background: var(--indigo-100); color: var(--indigo-700); }
.pv-btn-view:hover { background: var(--indigo-600); color: #fff; }

/* Points badge */
.pv-points-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 8px;
    background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2);
    font-size: 11px; font-weight: 700; color: #10b981;
}

/* Rejection reason display */
.pv-rejection-reason {
    margin-top: 8px; padding: 6px 10px; border-radius: 8px;
    background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.12);
    font-size: 11px; color: #f87171; line-height: 1.4;
}

/* Empty state */
.pv-empty {
    text-align: center; padding: 50px 20px; color: var(--muted);
}
.pv-empty i { font-size: 42px; display: block; margin-bottom: 12px; color: #cbd5e1; }
.pv-empty h3 { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
.pv-empty p { font-size: 12.5px; }

/* Rejection modal */
.pv-modal .modal-content { border-radius: 14px; border: none; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
.pv-modal .modal-header { background: linear-gradient(120deg, #7f1d1d, #991b1b); border-radius: 14px 14px 0 0; color: #fff; }
.pv-modal .modal-header .btn-close { filter: invert(1); }

@media (max-width: 767px) {
    .pv-stats { grid-template-columns: repeat(2, 1fr); }
    .pv-card-top { flex-direction: column; }
}
</style>

<!-- Page Header -->
<div class="pv-header">
    <div>
        <h2><i class="fas fa-clipboard-check"></i> Project Verification</h2>
        <p>Review, verify or reject student project submissions</p>
    </div>
</div>

<!-- Stats -->
<div class="pv-stats">
    <div class="pv-stat-card pv-stat-pending">
        <div class="pv-stat-label"><i class="fas fa-clock"></i> Pending</div>
        <div class="pv-stat-value"><?= (int)$counts['pending'] ?></div>
    </div>
    <div class="pv-stat-card pv-stat-verified">
        <div class="pv-stat-label"><i class="fas fa-check-circle"></i> Verified</div>
        <div class="pv-stat-value"><?= (int)$counts['verified'] ?></div>
    </div>
    <div class="pv-stat-card pv-stat-rejected">
        <div class="pv-stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
        <div class="pv-stat-value"><?= (int)$counts['rejected'] ?></div>
    </div>
    <div class="pv-stat-card pv-stat-total">
        <div class="pv-stat-label"><i class="fas fa-layer-group"></i> Total</div>
        <div class="pv-stat-value"><?= (int)$counts['total'] ?></div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="pv-tabs" style="margin-bottom:16px;">
    <a href="?filter=pending" class="pv-tab <?= $filter === 'pending' ? 'active' : '' ?>">
        <i class="fas fa-clock"></i> Pending <span class="pv-tab-count"><?= (int)$counts['pending'] ?></span>
    </a>
    <a href="?filter=verified" class="pv-tab <?= $filter === 'verified' ? 'active' : '' ?>">
        <i class="fas fa-check-circle"></i> Verified <span class="pv-tab-count"><?= (int)$counts['verified'] ?></span>
    </a>
    <a href="?filter=rejected" class="pv-tab <?= $filter === 'rejected' ? 'active' : '' ?>">
        <i class="fas fa-times-circle"></i> Rejected <span class="pv-tab-count"><?= (int)$counts['rejected'] ?></span>
    </a>
    <a href="?filter=all" class="pv-tab <?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fas fa-layer-group"></i> All <span class="pv-tab-count"><?= (int)$counts['total'] ?></span>
    </a>
</div>

<!-- Projects List -->
<?php if (!empty($projects)): ?>
    <?php foreach ($projects as $p): 
        $vs = $p['verification_status'] ?? 'pending';
        $is_web_dev = (stripos($p['category_name'], 'Web Development') !== false);
        $potential_points = $is_web_dev ? 12.5 : 5;
    ?>
    <div class="pv-card" id="pv-card-<?= $p['id'] ?>">
        <div class="pv-card-top">
            <div class="pv-card-student">
                <div class="pv-card-avatar">
                    <?php if (!empty($p['student_photo']) && file_exists($p['student_photo'])): ?>
                        <img src="<?= htmlspecialchars($p['student_photo']) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($p['student_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="pv-card-name"><?= htmlspecialchars($p['student_name']) ?></div>
                    <div class="pv-card-code"><?= htmlspecialchars($p['student_code']) ?></div>
                    <div class="pv-card-course"><?= htmlspecialchars($p['course_name']) ?> · <?= htmlspecialchars($p['category_name']) ?></div>
                </div>
            </div>
            <div>
                <?php if ($vs === 'pending'): ?>
                    <span class="pv-badge pv-badge-pending"><i class="fas fa-clock"></i> Pending</span>
                <?php elseif ($vs === 'verified'): ?>
                    <span class="pv-badge pv-badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                <?php elseif ($vs === 'rejected'): ?>
                    <span class="pv-badge pv-badge-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="pv-card-project">
            <div class="pv-project-name">
                <i class="fas fa-folder-open" style="color:var(--indigo-600)"></i>
                <?php if (!empty($p['project_link'])): ?>
                    <a href="<?= htmlspecialchars($p['project_link']) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($p['project_name']) ?> <i class="fas fa-external-link-alt" style="font-size:10px;opacity:.6"></i>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($p['project_name']) ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($p['description'])): ?>
            <div class="pv-project-desc"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
            <?php endif; ?>

            <div class="pv-project-meta">
                <span><i class="fas fa-calendar"></i> Submitted <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                <?php if (!empty($p['project_link'])): ?>
                <span><i class="fas fa-link"></i> <a href="<?= htmlspecialchars($p['project_link']) ?>" target="_blank" style="color:var(--indigo-600)">View Link</a></span>
                <?php endif; ?>
                <span><i class="fas fa-tag"></i> <?= $is_web_dev ? 'Dev (' . $potential_points . ' pts)' : 'Other (' . $potential_points . ' pts)' ?></span>
                <?php if ($vs === 'verified' && !empty($p['verified_at'])): ?>
                <span><i class="fas fa-check"></i> Verified <?= date('d M Y', strtotime($p['verified_at'])) ?> by <?= htmlspecialchars($p['verified_by_name'] ?? 'Admin') ?></span>
                <?php endif; ?>
            </div>

            <?php if ($vs === 'verified' && $p['points_awarded'] > 0): ?>
            <div style="margin-top:8px">
                <span class="pv-points-badge"><i class="fas fa-star"></i> <?= $p['points_awarded'] + 0 ?> Points Awarded</span>
            </div>
            <?php endif; ?>

            <?php if ($vs === 'rejected' && !empty($p['rejection_reason'])): ?>
            <div class="pv-rejection-reason">
                <i class="fas fa-exclamation-triangle"></i> <strong>Reason:</strong> <?= htmlspecialchars($p['rejection_reason']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="pv-actions">
            <?php if ($vs === 'pending'): ?>
                <button class="pv-btn pv-btn-verify" onclick="verifyProject(<?= $p['id'] ?>, <?= $potential_points ?>)">
                    <i class="fas fa-check"></i> Verify (+<?= $potential_points ?> pts)
                </button>
                <button class="pv-btn pv-btn-reject" onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">
                    <i class="fas fa-times"></i> Reject
                </button>
            <?php elseif ($vs === 'rejected'): ?>
                <button class="pv-btn pv-btn-verify" onclick="verifyProject(<?= $p['id'] ?>, <?= $potential_points ?>)">
                    <i class="fas fa-check"></i> Verify Instead (+<?= $potential_points ?> pts)
                </button>
            <?php elseif ($vs === 'verified'): ?>
                <button class="pv-btn pv-btn-reject" onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?>')">
                    <i class="fas fa-undo"></i> Revoke & Reject
                </button>
            <?php endif; ?>
            <a href="student_details.php?id=<?= $p['student_id'] ?>" class="pv-btn pv-btn-view">
                <i class="fas fa-user"></i> View Student
            </a>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="table-card">
        <div class="pv-empty">
            <i class="fas fa-clipboard-check"></i>
            <h3>No <?= $filter !== 'all' ? ucfirst($filter) : '' ?> Projects</h3>
            <p>
                <?php if ($filter === 'pending'): ?>
                    All caught up! No projects waiting for verification.
                <?php else: ?>
                    No projects found with the selected filter.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Reject Modal -->
<div class="modal fade pv-modal" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectProjectId">
                <p style="font-size:13px;margin-bottom:12px;color:var(--muted)" id="rejectProjectInfo"></p>
                <label class="form-label" style="font-weight:600;font-size:12px">Rejection Reason (Optional)</label>
                <textarea class="form-control" id="rejectReason" rows="3" placeholder="Explain why the project was rejected..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger btn-sm" onclick="submitReject()"><i class="fas fa-times"></i> Reject Project</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Verify a project — awards points based on course category
 */
function verifyProject(projectId, points) {
    if (!confirm(`Verify this project and award ${points} points to the student?`)) return;

    const fd = new FormData();
    fd.append('action', 'verify_project');
    fd.append('project_id', projectId);

    fetch('ajax/manage_projects.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Show success feedback and reload
                alert(d.message);
                location.reload();
            } else {
                alert('Error: ' + d.message);
            }
        })
        .catch(() => alert('Network error'));
}

/**
 * Open reject modal
 */
function openRejectModal(projectId, projectName) {
    document.getElementById('rejectProjectId').value = projectId;
    document.getElementById('rejectProjectInfo').textContent = 'Project: ' + projectName;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

/**
 * Submit rejection
 */
function submitReject() {
    const projectId = document.getElementById('rejectProjectId').value;
    const reason = document.getElementById('rejectReason').value.trim();

    const fd = new FormData();
    fd.append('action', 'reject_project');
    fd.append('project_id', projectId);
    fd.append('rejection_reason', reason);

    fetch('ajax/manage_projects.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                alert(d.message);
                location.reload();
            } else {
                alert('Error: ' + d.message);
            }
        })
        .catch(() => alert('Network error'));
}
</script>

<?php include 'includes/footer.php'; ?>
