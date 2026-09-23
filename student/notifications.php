<?php
include 'includes/header.php';

// Actions
if (isset($_GET['read'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE student_notifications SET is_read=1 WHERE id=$id AND student_id=$sid");
    header('Location: notifications.php'); exit;
}
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE student_notifications SET is_read=1 WHERE student_id=$sid");
    header('Location: notifications.php'); exit;
}
if (isset($_GET['delete'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM student_notifications WHERE id=$id AND student_id=$sid");
    header('Location: notifications.php'); exit;
}

$filter  = $_GET['filter'] ?? 'all';
$where   = "WHERE student_id = $sid";
if ($filter === 'unread') $where .= " AND is_read = 0";
elseif ($filter === 'read') $where .= " AND is_read = 1";

$notifications  = $conn->query("SELECT * FROM student_notifications $where ORDER BY created_at DESC");
$total_count    = (int)safeVal($conn, "SELECT COUNT(*) as cnt FROM student_notifications WHERE student_id=$sid", 'cnt', 0);
$unread_notif   = (int)safeVal($conn, "SELECT COUNT(*) as cnt FROM student_notifications WHERE student_id=$sid AND is_read=0", 'cnt', 0);
$read_count     = $total_count - $unread_notif;

$type_icon = ['info'=>'info-circle','warning'=>'exclamation-triangle','success'=>'check-circle','payment'=>'indian-rupee-sign'];
$icon_class= ['info'=>'notif-icon-info','warning'=>'notif-icon-warning','success'=>'notif-icon-success','payment'=>'notif-icon-payment'];
?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-bell" style="font-size:16px;-webkit-text-fill-color:var(--yellow);"></i> Notifications</h2>
    <p>Stay updated with messages and alerts</p>
  </div>
  <?php if ($unread_notif > 0): ?>
  <a href="?mark_all_read=1" class="btn btn-ghost btn-sm">
    <i class="fas fa-check-double"></i> Mark All Read
  </a>
  <?php endif; ?>
</div>

<!-- STATS -->
<div class="stats-grid fade-up delay-1" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-purple">
    <div class="stat-card-info">
      <div class="stat-card-label">Total</div>
      <div class="stat-card-value"><?php echo $total_count; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-bell"></i></div>
  </div>
  <div class="stat-card s-red">
    <div class="stat-card-info">
      <div class="stat-card-label">Unread</div>
      <div class="stat-card-value"><?php echo $unread_notif; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-circle-exclamation"></i></div>
  </div>
  <div class="stat-card s-green">
    <div class="stat-card-info">
      <div class="stat-card-label">Read</div>
      <div class="stat-card-value"><?php echo $read_count; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
  </div>
</div>

<!-- FILTER TABS -->
<div class="dark-tabs fade-up delay-2" style="width:fit-content;">
  <a href="?filter=all"    class="dark-tab <?php echo $filter==='all'?'active':''; ?>">All (<?php echo $total_count; ?>)</a>
  <a href="?filter=unread" class="dark-tab <?php echo $filter==='unread'?'active':''; ?>">Unread (<?php echo $unread_notif; ?>)</a>
  <a href="?filter=read"   class="dark-tab <?php echo $filter==='read'?'active':''; ?>">Read (<?php echo $read_count; ?>)</a>
</div>

<!-- NOTIFICATIONS LIST -->
<div style="display:flex;flex-direction:column;gap:8px;" class="fade-up delay-3">
  <?php if ($notifications && $notifications->num_rows > 0): ?>
    <?php while ($n = $notifications->fetch_assoc()):
      $ico  = $type_icon[$n['type']] ?? 'bell';
      $icls = $icon_class[$n['type']] ?? 'notif-icon-info';
    ?>
    <div class="notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>">
      <div class="notif-icon <?php echo $icls; ?>">
        <i class="fas fa-<?php echo $ico; ?>"></i>
      </div>
      <div class="notif-body">
        <div class="notif-title">
          <?php echo htmlspecialchars($n['title']); ?>
          <?php if (!$n['is_read']): ?>
          <span class="badge-pill badge-red" style="font-size:9px;margin-left:6px;">NEW</span>
          <?php endif; ?>
        </div>
        <div class="notif-message"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
        <div class="notif-time"><i class="fas fa-clock" style="font-size:10px;"></i> <?php echo date('d M Y, h:i A', strtotime($n['created_at'])); ?></div>
      </div>
      <div class="notif-actions">
        <?php if (!$n['is_read']): ?>
        <a href="?read=1&id=<?php echo $n['id']; ?>" class="btn btn-outline-purple btn-sm" title="Mark as read">
          <i class="fas fa-check"></i>
        </a>
        <?php endif; ?>
        <a href="?delete=1&id=<?php echo $n['id']; ?>" class="btn btn-sm" style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#f87171;" title="Delete"
           onclick="return confirm('Delete this notification?')">
          <i class="fas fa-trash"></i>
        </a>
      </div>
    </div>
    <?php endwhile; ?>
  <?php else: ?>
  <div class="glass-card">
    <div class="glass-card-body" style="text-align:center;padding:40px 20px;">
      <i class="fas fa-bell-slash" style="font-size:40px;color:var(--text-dim);margin-bottom:14px;display:block;"></i>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;">No Notifications</div>
      <div style="font-size:13px;color:var(--text-muted);">
        <?php
        if ($filter === 'unread') echo "You're all caught up — no unread notifications.";
        elseif ($filter === 'read') echo "No read notifications.";
        else echo "You don't have any notifications yet.";
        ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>