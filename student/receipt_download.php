<?php
require_once '../admin/config/database.php';
require_once 'config/student_auth.php';
requireStudentLogin();

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id === 0) {
    die("Invalid payment ID");
}

$student = getCurrentStudent();

// Get payment details - VERIFY OWNERSHIP
$query = "SELECT 
    p.*,
    s.student_code,
    s.full_name as student_name,
    s.phone,
    s.total_fees,
    c.name as course_name,
    COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id), 0) as total_paid_till_now,
    (s.total_fees - COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id AND id <= p.id), 0)) as remaining_after_payment
FROM payments p
JOIN students s ON p.student_id = s.id
JOIN courses c ON s.course_id = c.id
WHERE p.id = ? AND s.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $payment_id, $student['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Receipt not found or access denied");
}

$payment = $result->fetch_assoc();
$stmt->close();

// Set headers for download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="Receipt_' . $payment['receipt_number'] . '.html"');

// Generate simple HTML receipt for download
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo $payment['receipt_number']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; border-bottom: 3px solid #7c3aed; padding-bottom: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f3e8ff; }
        .total { background: #fff3cd; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>NEXT ACADEMY</h1>
        <h3>Payment Receipt</h3>
        <h2>#<?php echo htmlspecialchars($payment['receipt_number']); ?></h2>
    </div>
    
    <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($payment['payment_date'])); ?></p>
    <p><strong>Student Code:</strong> <?php echo htmlspecialchars($payment['student_code']); ?></p>
    <p><strong>Student Name:</strong> <?php echo htmlspecialchars($payment['student_name']); ?></p>
    <p><strong>Course:</strong> <?php echo htmlspecialchars($payment['course_name']); ?></p>
    
    <table>
        <tr>
            <th>Description</th>
            <th>Amount</th>
        </tr>
        <tr>
            <td>Total Course Fees</td>
            <td>₹<?php echo number_format($payment['total_fees'], 2); ?></td>
        </tr>
        <tr>
            <td><strong>Amount Paid (This Payment)</strong></td>
            <td><strong>₹<?php echo number_format($payment['amount_paid'], 2); ?></strong></td>
        </tr>
        <tr>
            <td>Total Paid Till Now</td>
            <td>₹<?php echo number_format($payment['total_paid_till_now'], 2); ?></td>
        </tr>
        <tr class="total">
            <td>Remaining Balance</td>
            <td>₹<?php echo number_format($payment['remaining_after_payment'], 2); ?></td>
        </tr>
    </table>
    
    <p style="text-align: center; margin-top: 40px; border-top: 2px dashed #7c3aed; padding-top: 20px;">
        <strong>Thank you for your payment!</strong><br>
        <small>This is a computer-generated receipt.</small>
    </p>
</body>
</html>