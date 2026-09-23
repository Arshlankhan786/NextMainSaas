<?php
date_default_timezone_set('Asia/Kolkata');
require_once '../admin/config/database.php';
require_once './student_auth.php';
requireStudentLogin();

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id === 0) die("Invalid payment ID");

$student = getCurrentStudent();
$sid     = (int)$student['id'];

$query = "
    SELECT p.*, s.student_code, s.full_name as student_name, s.phone, s.email, s.address, s.total_fees,
           c.name as course_name, cat.name as category_name, a.full_name as admin_name,
           COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id),0) as total_paid_till_now,
           (s.total_fees - COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id),0)) as remaining_after_payment
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    LEFT JOIN admins a ON p.created_by = a.id
    WHERE p.id = ? AND s.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $payment_id, $sid);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Receipt not found or access denied");
$payment = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt – <?php echo htmlspecialchars($payment['receipt_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
  .receipt-wrap { max-width: 750px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.12); overflow: hidden; }
  .receipt-header { background: linear-gradient(135deg, #7c3aed, #4f46e5); padding: 32px; text-align: center; color: #fff; }
  .receipt-header h1 { font-size: 26px; font-weight: 800; margin: 0 0 4px; }
  .receipt-number { font-size: 16px; opacity: 0.85; }
  .receipt-body { padding: 28px 32px; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
  .info-box label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 3px; }
  .info-box span { font-size: 14px; font-weight: 600; color: #111; }
  .amount-table { width: 100%; border-collapse: collapse; }
  .amount-table th { background: #f3e8ff; color: #5b21b6; font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 10px 14px; text-align: left; }
  .amount-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
  .amount-table tr:last-child td { border: none; }
  .amount-highlight { font-size: 20px; font-weight: 800; color: #10b981; }
  .receipt-footer { text-align: center; padding: 20px; border-top: 2px dashed #7c3aed; margin: 0 32px 28px; color: #6b7280; font-size: 12px; }
  .no-print { margin: 16px; text-align: center; display: flex; justify-content: center; gap: 10px; }
  @media print { .no-print { display: none !important; } body { background: #fff; } .receipt-wrap { box-shadow: none; margin: 0; border-radius: 0; } }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
  <button onclick="window.close()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
</div>

<div class="receipt-wrap">
  <div class="receipt-header">
    <h1>🎓 NEXT ACADEMY</h1>
    <p class="receipt-number">Receipt #<?php echo htmlspecialchars($payment['receipt_number']); ?></p>
  </div>

  <div class="receipt-body">
    <div class="info-grid">
      <div class="info-box">
        <label>Payment Date</label>
        <span><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></span>
      </div>
      <div class="info-box">
        <label>Payment Method</label>
        <span><?php echo htmlspecialchars($payment['payment_method']); ?></span>
      </div>
      <div class="info-box">
        <label>Student Code</label>
        <span><?php echo htmlspecialchars($payment['student_code']); ?></span>
      </div>
      <div class="info-box">
        <label>Student Name</label>
        <span><?php echo htmlspecialchars($payment['student_name']); ?></span>
      </div>
      <div class="info-box">
        <label>Course</label>
        <span><?php echo htmlspecialchars($payment['course_name']); ?></span>
      </div>
      <div class="info-box">
        <label>Received By</label>
        <span><?php echo htmlspecialchars($payment['admin_name'] ?? 'N/A'); ?></span>
      </div>
    </div>

    <table class="amount-table">
      <thead>
        <tr><th>Description</th><th style="text-align:right;">Amount</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>Total Course Fees</td>
          <td style="text-align:right;">₹<?php echo number_format($payment['total_fees'], 2); ?></td>
        </tr>
        <tr style="background:#f0fdf4;">
          <td><strong>Amount Paid (This Payment)</strong></td>
          <td style="text-align:right;" class="amount-highlight">₹<?php echo number_format($payment['amount_paid'], 2); ?></td>
        </tr>
        <tr>
          <td>Total Paid Till Now</td>
          <td style="text-align:right;">₹<?php echo number_format($payment['total_paid_till_now'], 2); ?></td>
        </tr>
        <tr style="background:<?php echo $payment['remaining_after_payment'] > 0 ? '#fffbeb' : '#f0fdf4'; ?>;">
          <td><strong>Remaining Balance</strong></td>
          <td style="text-align:right;font-size:18px;font-weight:800;color:<?php echo $payment['remaining_after_payment'] > 0 ? '#ef4444' : '#10b981'; ?>;">
            ₹<?php echo number_format($payment['remaining_after_payment'], 2); ?>
            <?php if ($payment['remaining_after_payment'] <= 0): ?><small style="font-size:11px;display:block;color:#10b981;">✓ Fully Paid</small><?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>

    <?php if ($payment['notes']): ?>
    <div style="margin-top:16px;padding:12px;background:#f9fafb;border-radius:8px;border-left:3px solid #7c3aed;">
      <strong style="font-size:12px;color:#5b21b6;">Notes:</strong>
      <p style="font-size:13px;color:#374151;margin:4px 0 0;"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
    </div>
    <?php endif; ?>
  </div>

  <div class="receipt-footer">
    <strong>Thank you for your payment!</strong><br>
    This is a computer-generated receipt. Printed on <?php echo date('d M Y, h:i A'); ?> IST.
  </div>
</div>
</body>
</html>