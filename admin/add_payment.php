<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$admin = getCurrentAdmin();

// ================================
// GET STUDENT IF SELECTED
// ================================
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student = null;
$pending_amount = 0;

if ($student_id > 0) {
    $result = $conn->query("
        SELECT s.*, c.name AS course_name, cat.name AS category_name,
        COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id), 0) AS paid_amount,
        (s.total_fees - COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id), 0)) AS pending_amount
        FROM students s
        JOIN courses c ON s.course_id = c.id
        JOIN categories cat ON s.category_id = cat.id
        WHERE s.id = $student_id AND s.status = 'Active'
    ");
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $pending_amount = $student['pending_amount'];
    }
}

// ================================
// HANDLE PAYMENT SUBMISSION WITH AUTO NOTIFICATION
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_id = (int)$_POST['student_id'];
    $amount_paid = (float)$_POST['amount_paid'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $receipt_number = $_POST['receipt_number'];
    $notes = $_POST['notes'];
    $created_by = $admin['id'];

    // Insert payment
    $stmt = $conn->prepare("
        INSERT INTO payments (student_id, amount_paid, payment_date, payment_method, receipt_number, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("idssssi", $student_id, $amount_paid, $payment_date, $payment_method, $receipt_number, $notes, $created_by);

    if ($stmt->execute()) {
        $payment_id = $stmt->insert_id;
        
        // ================================
        // AUTO-CREATE NOTIFICATION
        // ================================
        
        // Get updated payment info
        $payment_info = $conn->query("
            SELECT 
                s.full_name,
                s.total_fees,
                COALESCE(SUM(p.amount_paid), 0) as total_paid,
                (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as remaining
            FROM students s
            LEFT JOIN payments p ON s.id = p.student_id
            WHERE s.id = $student_id
            GROUP BY s.id
        ")->fetch_assoc();
        
        // Create notification title
        $notif_title = "Payment Received - Receipt #" . $receipt_number;
        
        // Create detailed notification message
        $notif_message = "Dear " . $payment_info['full_name'] . ",\n\n";
        $notif_message .= "Your payment has been successfully received!\n\n";
        $notif_message .= "Payment Details:\n";
        $notif_message .= "• Amount Paid: ₹" . number_format($amount_paid, 2) . "\n";
        $notif_message .= "• Payment Date: " . date('d M Y', strtotime($payment_date)) . "\n";
        $notif_message .= "• Payment Method: " . $payment_method . "\n";
        $notif_message .= "• Receipt Number: " . $receipt_number . "\n\n";
        $notif_message .= "Fee Summary:\n";
        $notif_message .= "• Total Course Fees: ₹" . number_format($payment_info['total_fees'], 2) . "\n";
        $notif_message .= "• Total Paid: ₹" . number_format($payment_info['total_paid'], 2) . "\n";
        $notif_message .= "• Remaining Balance: ₹" . number_format($payment_info['remaining'], 2) . "\n\n";
        
        if ($payment_info['remaining'] <= 0) {
            $notif_message .= "🎉 Congratulations! Your fees are now fully paid.\n\n";
        } else {
            $notif_message .= "Please pay the remaining balance at your earliest convenience.\n\n";
        }
        
        $notif_message .= "You can view your payment receipt in the Student Portal.\n\n";
        $notif_message .= "Thank you for your payment!";
        
        // Insert notification
        $notif_type = ($payment_info['remaining'] <= 0) ? 'success' : 'payment';
        
        $notif_stmt = $conn->prepare("
            INSERT INTO student_notifications (student_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        $notif_stmt->bind_param("isss", $student_id, $notif_title, $notif_message, $notif_type);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        // ================================
        // END AUTO NOTIFICATION
        // ================================
        
        $_SESSION['success'] = "Payment recorded and notification sent to student!";
        header("Location: receipt.php?id=$payment_id&whatsapp=1");
        exit();
    } else {
        $_SESSION['error'] = "Failed to record payment.";
    }

    $stmt->close();
}

// ================================
// GET STUDENTS WITH PENDING FEES
// ================================
$students = $conn->query("
    SELECT s.id, s.student_code, s.full_name, s.total_fees,
    COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id), 0) AS paid,
    (s.total_fees - COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id), 0)) AS pending
    FROM students s
    WHERE s.status = 'Active'
    AND s.total_fees > COALESCE((SELECT SUM(amount_paid) FROM payments WHERE student_id = s.id), 0)
    ORDER BY s.full_name ASC
");

// ================================
// NOW LOAD HEADER (SAFE)
// ================================
$current_page = 'add_payment';
include 'includes/header.php';
?>

<div class="page-header">
    <h2><i class="fa-solid fa-indian-rupee-sign"></i> Add Payment</h2>
    <p class="text-muted mb-0">Record student fee payment - Notification sent automatically</p>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <form method="POST" id="paymentForm">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Select Student *</label>
                        <select class="form-select" name="student_id" id="student_select" required onchange="loadStudentDetails(this.value)">
                            <option value="">-- Select Student --</option>
                            <?php while ($s = $students->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['full_name']) . ' (' . $s['student_code'] . ') - Pending: ₹' . number_format($s['pending'], 2); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div id="studentDetails" style="display: <?php echo $student ? 'block' : 'none'; ?>;">
                    <?php if ($student): ?>
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Student:</strong> <?php echo htmlspecialchars($student['full_name']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Course:</strong> <?php echo htmlspecialchars($student['course_name']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Total Fees:</strong> ₹<?php echo number_format($student['total_fees'], 2); ?>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Paid:</strong> <span class="text-success">₹<?php echo number_format($student['paid_amount'], 2); ?></span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <strong>Pending:</strong> <span class="text-danger">₹<?php echo number_format($pending_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" name="receipt_number" value="RCP<?php echo date('Ymd') . rand(1000, 9999); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount Paid (₹) *</label>
                            <input type="number" class="form-control" name="amount_paid" step="0.01" min="1" 
                                   max="<?php echo $pending_amount; ?>" required>
                            <small class="text-muted">Maximum: ₹<?php echo number_format($pending_amount, 2); ?></small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-bell"></i> <strong>Auto Notification:</strong> Student will automatically receive a payment confirmation notification on their portal with full payment details.
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-purple">
                            <i class="fas fa-check"></i> Record Payment & Send Notification
                        </button>
                        <a href="payments.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-info-circle"></i> Payment Information</h5>
            <ul class="list-unstyled">
                <li class="mb-2"><i class="fas fa-check text-success"></i> All fields marked with * are required</li>
                <li class="mb-2"><i class="fas fa-check text-success"></i> Payment amount cannot exceed pending fees</li>
                <li class="mb-2"><i class="fas fa-check text-success"></i> Receipt will be generated automatically</li>
                <li class="mb-2"><i class="fas fa-check text-success"></i> Receipt can be shared via WhatsApp</li>
                <li class="mb-2"><i class="fas fa-bell text-success"></i> <strong>Student notification sent automatically</strong></li>
                <li class="mb-2"><i class="fas fa-check text-success"></i> Payment history is tracked for each student</li>
            </ul>
        </div>
        
        <div class="table-card mt-3">
            <h6 class="text-purple mb-2"><i class="fas fa-bell"></i> Auto Notification Details</h6>
            <p class="small text-muted mb-2">When you record a payment, the system automatically sends:</p>
            <ul class="small">
                <li>Payment confirmation with receipt number</li>
                <li>Amount paid and payment date</li>
                <li>Updated fee summary (total, paid, remaining)</li>
                <li>Congratulations message if fully paid</li>
                <li>Link to view receipt in portal</li>
            </ul>
        </div>
        
        <div class="table-card mt-3">
            <h6 class="text-purple mb-2">Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="students.php" class="btn btn-outline-purple btn-sm">
                    <i class="fas fa-users"></i> View All Students
                </a>
                <a href="payments.php" class="btn btn-outline-purple btn-sm">
                    <i class="fas fa-history"></i> Payment History
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function loadStudentDetails(studentId) {
    if (studentId) {
        window.location.href = 'add_payment.php?student_id=' + studentId;
    }
}
</script>

<?php include 'includes/footer.php'; ?>