<?php
include 'includes/header.php';

$group = $conn->query("
    SELECT sg.id, sg.group_name, c.name as course_name
    FROM student_group_members sgm
    JOIN student_groups sg ON sgm.group_id = sg.id
    JOIN courses c ON sg.course_id = c.id
    WHERE sgm.student_id = $sid
")->fetch_assoc();

if (!$group) { ?>
<div class="page-header-block fade-up">
  <div><h2>Course Progress</h2><p>Follow your learning journey</p></div>
</div>
<div class="alert-block alert-block-info fade-up delay-1">
  <i class="fas fa-info-circle"></i>
  You are not assigned to any group yet. Please contact the admin.
</div>
<?php include 'includes/footer.php'; exit; }

$topics = $conn->query("
    SELECT ct.id, ct.topic_name, ct.order_index,
           gtp.status, gtp.start_date, gtp.end_date,
           (SELECT COUNT(*) FROM course_sub_topics WHERE topic_id = ct.id) as sub_count
    FROM course_topics ct
    JOIN group_topic_progress gtp ON ct.id = gtp.topic_id
    WHERE gtp.group_id = {$group['id']}
    ORDER BY ct.order_index ASC
");

$completed = 0; $active = 0; $upcoming = 0;
$total = $topics ? $topics->num_rows : 0;

if ($topics) {
    $topics->data_seek(0);
    while ($t = $topics->fetch_assoc()) {
        if ($t['status']==='completed') $completed++;
        elseif ($t['status']==='active') $active++;
        else $upcoming++;
    }
}

$progress_pct = $total > 0 ? round(($completed / $total) * 100) : 0;
?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-chart-line" style="font-size:16px;-webkit-text-fill-color:var(--cyan);"></i> Course Progress</h2>
    <p>Group: <strong style="color:var(--text);"><?php echo htmlspecialchars($group['group_name']); ?></strong>
       &nbsp;·&nbsp; <?php echo htmlspecialchars($group['course_name']); ?></p>
  </div>
</div>

<!-- STATS -->
<div class="stats-grid fade-up delay-1">
  <div class="stat-card s-purple">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Topics</div>
      <div class="stat-card-value"><?php echo $total; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-list"></i></div>
  </div>
  <div class="stat-card s-green">
    <div class="stat-card-info">
      <div class="stat-card-label">Completed</div>
      <div class="stat-card-value"><?php echo $completed; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-card-info">
      <div class="stat-card-label">In Progress</div>
      <div class="stat-card-value"><?php echo $active; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-play-circle"></i></div>
  </div>
  <div class="stat-card s-cyan">
    <div class="stat-card-info">
      <div class="stat-card-label">Progress</div>
      <div class="stat-card-value"><?php echo $progress_pct; ?>%</div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-rocket"></i></div>
  </div>
</div>

<!-- PROGRESS BAR -->
<div class="glass-card fade-up delay-2">
  <div class="glass-card-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <span style="font-size:12px;font-weight:600;color:var(--text-muted);">OVERALL COMPLETION</span>
      <span style="font-size:13px;font-weight:800;color:var(--cyan);"><?php echo $completed; ?> / <?php echo $total; ?> topics</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill cyan" style="width:<?php echo $progress_pct; ?>%;"></div>
    </div>
  </div>
</div>

<!-- TOPIC FLOW -->
<div class="glass-card fade-up delay-3">
  <div class="glass-card-body">
    <div class="section-header">
      <div class="section-title"><i class="fas fa-list-check"></i> Course Topics</div>
    </div>

    <?php if ($total > 0): ?>
    <div class="topic-flow">
      <?php $topics->data_seek(0); $idx = 0; while ($topic = $topics->fetch_assoc()):
        $idx++;
        $cls = 't-pending'; $badge_cls = 'badge-gray'; $badge_txt = 'Upcoming'; $badge_ico = 'fa-clock';
        if ($topic['status'] === 'active')     { $cls='t-active'; $badge_cls='badge-purple'; $badge_txt='In Progress'; $badge_ico='fa-play'; }
        elseif ($topic['status'] === 'completed') { $cls='t-done';   $badge_cls='badge-green';  $badge_txt='Completed';   $badge_ico='fa-check'; }
      ?>
      <div class="topic-item <?php echo $cls; ?>">
        <div class="topic-num"><?php echo $idx; ?></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
            <span class="badge-pill <?php echo $badge_cls; ?>">
              <i class="fas <?php echo $badge_ico; ?>"></i> <?php echo $badge_txt; ?>
            </span>
            <strong style="font-size:13px;"><?php echo htmlspecialchars($topic['topic_name']); ?></strong>
          </div>
          <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:11px;color:var(--text-muted);">
            <?php if ($topic['start_date']): ?>
            <span><i class="fas fa-calendar-plus"></i> Started: <?php echo date('d M Y', strtotime($topic['start_date'])); ?></span>
            <?php endif; ?>
            <?php if ($topic['end_date']): ?>
            <span style="color:var(--green);"><i class="fas fa-flag-checkered"></i> Done: <?php echo date('d M Y', strtotime($topic['end_date'])); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($topic['sub_count'] > 0 && $topic['status'] !== 'upcoming'): ?>
          <div style="margin-top:8px;">
            <button class="btn btn-ghost btn-sm" onclick="loadSubTopics(<?php echo $topic['id']; ?>,'<?php echo htmlspecialchars(addslashes($topic['topic_name'])); ?>')">
              <i class="fas fa-list-ul"></i> Sub-topics (<?php echo $topic['sub_count']; ?>)
            </button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="alert-block alert-block-info">
      <i class="fas fa-info-circle"></i> No topics added to your course yet.
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- SUB-TOPICS MODAL -->
<div class="modal fade" id="subModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-list-ul"></i> <span id="subTitle"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="subBody">
        <div style="text-align:center;padding:20px;">
          <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--purple-light);"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function loadSubTopics(id, name) {
  document.getElementById('subTitle').textContent = name;
  document.getElementById('subBody').innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin" style="font-size:20px;color:var(--purple-light);"></i></div>';
  new bootstrap.Modal(document.getElementById('subModal')).show();

  fetch(`../admin/ajax/manage_course_topics.php?action=get_subtopics&topic_id=${id}`)
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('subBody');
      if (data.success && data.subtopics.length > 0) {
        c.innerHTML = data.subtopics.map((s,i) =>
          `<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.07);">
            <span style="width:24px;height:24px;border-radius:6px;background:rgba(124,58,237,0.2);color:var(--purple-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">${i+1}</span>
            <span style="font-size:13px;">${s.sub_topic_name}</span>
           </div>`
        ).join('');
      } else {
        c.innerHTML = '<div class="alert-block alert-block-info"><i class="fas fa-info-circle"></i> No sub-topics defined.</div>';
      }
    })
    .catch(() => {
      document.getElementById('subBody').innerHTML = '<div class="alert-block alert-block-danger"><i class="fas fa-exclamation-circle"></i> Failed to load.</div>';
    });
}
</script>

<?php include 'includes/footer.php'; ?>