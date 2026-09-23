<?php
include 'includes/header.php';

$receipts = $conn->query("
    SELECT p.*, a.full_name as admin_name
    FROM payments p
    LEFT JOIN admins a ON p.created_by = a.id
    WHERE p.student_id = $sid
    AND p.receipt_number IS NOT NULL
    ORDER BY p.payment_date DESC, p.created_at DESC
");
?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-receipt" style="font-size:16px;-webkit-text-fill-color:var(--yellow);"></i> My Receipts</h2>
    <p>Download and view your payment receipts</p>
  </div>
</div>

<?php if ($receipts && $receipts->num_rows > 0): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;" class="fade-up delay-1">
  <?php while ($r = $receipts->fetch_assoc()): ?>
  <div class="glass-card" style="transition:border-color 0.2s,transform 0.2s;">
    <div class="glass-card-body" style="padding:16px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
        <div>
          <div style="font-size:14px;font-weight:700;margin-bottom:3px;">
            Receipt #<?php echo htmlspecialchars($r['receipt_number']); ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted);">
            <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($r['payment_date'])); ?>
          </div>
        </div>
        <div style="font-family:'Poppins',sans-serif;font-size:18px;font-weight:800;color:var(--green);">
          ₹<?php echo number_format($r['amount_paid'], 2); ?>
        </div>
      </div>

      <div style="margin-bottom:12px;">
        <span class="badge-pill badge-cyan"><?php echo htmlspecialchars($r['payment_method']); ?></span>
        <?php if ($r['admin_name']): ?>
        <span style="font-size:11px;color:var(--text-muted);margin-left:8px;">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($r['admin_name']); ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if (!empty($r['notes'])): ?>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:12px;">
        <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($r['notes']); ?>
      </div>
      <?php endif; ?>

      <a href="receipt_view.php?id=<?php echo $r['id']; ?>" class="btn btn-purple btn-sm btn-full" target="_blank">
        <i class="fas fa-eye"></i> View Receipt
      </a>
    </div>
  </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="glass-card fade-up delay-1">
  <div class="glass-card-body" style="text-align:center;padding:40px 20px;">
    <i class="fas fa-receipt" style="font-size:40px;color:var(--text-dim);margin-bottom:14px;display:block;"></i>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;">No Receipts Yet</div>
    <div style="font-size:13px;color:var(--text-muted);">Your payment receipts will appear here.</div>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>