<?php
include 'includes/header.php';

$payment_summary = $conn->query("
    SELECT s.total_fees,
           COALESCE(SUM(p.amount_paid),0) as total_paid,
           (s.total_fees - COALESCE(SUM(p.amount_paid),0)) as pending_fees,
           COUNT(p.id) as payment_count
    FROM students s
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.id = $sid
    GROUP BY s.id
")->fetch_assoc();

$payments = $conn->query("
    SELECT p.*, a.full_name as admin_name
    FROM payments p
    LEFT JOIN admins a ON p.created_by = a.id
    WHERE p.student_id = $sid
    ORDER BY p.payment_date DESC, p.created_at DESC
");

$paid_pct = ($payment_summary['total_fees'] > 0)
    ? min(100, round(($payment_summary['total_paid'] / $payment_summary['total_fees']) * 100))
    : 0;
?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-indian-rupee-sign" style="font-size:16px;-webkit-text-fill-color:var(--green);"></i> Payment History</h2>
    <p>All your transactions and fee details</p>
  </div>
  <?php if ($payments->num_rows > 0): ?>
  <button class="btn btn-ghost btn-sm" onclick="window.print()">
    <i class="fas fa-print"></i> Print
  </button>
  <?php endif; ?>
</div>

<!-- STATS -->
<div class="stats-grid fade-up delay-1">
  <div class="stat-card s-purple">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Fees</div>
      <div class="stat-card-value" style="font-size:18px;">₹<?php echo number_format($payment_summary['total_fees'], 0); ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-file-invoice"></i></div>
  </div>
  <div class="stat-card s-green">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Paid</div>
      <div class="stat-card-value" style="font-size:18px;">₹<?php echo number_format($payment_summary['total_paid'], 0); ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
  </div>
  <div class="stat-card s-red">
    <div class="stat-card-info">
      <div class="stat-card-label">Pending</div>
      <div class="stat-card-value" style="font-size:18px;">₹<?php echo number_format($payment_summary['pending_fees'], 0); ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
  </div>
  <div class="stat-card s-cyan">
    <div class="stat-card-info">
      <div class="stat-card-label">Transactions</div>
      <div class="stat-card-value"><?php echo $payment_summary['payment_count']; ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-receipt"></i></div>
  </div>
</div>

<!-- PROGRESS BAR -->
<div class="glass-card fade-up delay-2">
  <div class="glass-card-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <span style="font-size:12px;font-weight:600;color:var(--text-muted);">PAYMENT PROGRESS</span>
      <span style="font-size:13px;font-weight:800;color:var(--green);"><?php echo $paid_pct; ?>%</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill green" style="width:<?php echo $paid_pct; ?>%;"></div>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:var(--text-dim);">
      <span>₹<?php echo number_format($payment_summary['total_paid'], 0); ?> paid</span>
      <span>₹<?php echo number_format($payment_summary['pending_fees'], 0); ?> remaining</span>
    </div>
  </div>
</div>

<?php if ($payment_summary['pending_fees'] > 0): ?>
<div class="alert-block alert-block-warning fade-up delay-2">
  <i class="fas fa-exclamation-triangle"></i>
  <span><strong>Pending Fees:</strong> You have ₹<?php echo number_format($payment_summary['pending_fees'], 2); ?> outstanding. Please contact the academy office.</span>
</div>
<?php endif; ?>

<!-- TRANSACTION TABLE -->
<div class="glass-card fade-up delay-3">
  <div class="glass-card-body">
    <div class="section-header">
      <div class="section-title"><i class="fas fa-history"></i> All Transactions</div>
    </div>

    <?php if ($payments->num_rows > 0): ?>
    <div class="table-wrap">
      <table class="dark-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Receipt No.</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Received By</th>
            <th>Notes</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $sno = 1; while ($p = $payments->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--text-dim);"><?php echo $sno++; ?></td>
            <td><strong><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></strong></td>
            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
            <td><span class="badge-pill badge-green">₹<?php echo number_format($p['amount_paid'], 2); ?></span></td>
            <td><span class="badge-pill badge-cyan"><?php echo htmlspecialchars($p['payment_method']); ?></span></td>
            <td style="font-size:12px;color:var(--text-muted);"><?php echo htmlspecialchars($p['admin_name'] ?? 'N/A'); ?></td>
            <td style="font-size:12px;color:var(--text-muted);max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($p['notes'] ?? '—'); ?></td>
            <td>
              <a href="receipt_view.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-purple btn-sm" target="_blank">
                <i class="fas fa-receipt"></i> View
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="color:var(--text-muted);">Total Paid</td>
            <td colspan="5">
              <span style="font-family:'Poppins',sans-serif;font-size:16px;font-weight:800;color:var(--green);">
                ₹<?php echo number_format($payment_summary['total_paid'], 2); ?>
              </span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php else: ?>
    <div class="alert-block alert-block-info">
      <i class="fas fa-info-circle"></i> No payment records found.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>