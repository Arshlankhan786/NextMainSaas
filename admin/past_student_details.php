<?php
include 'includes/header.php';

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    header('Location: past_students.php');
    exit();
}

// Get past student details
$student = $conn->query("
    SELECT s.*, c.name as course_name, cat.name as category_name
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    WHERE s.id = $student_id 
    AND s.status IN ('Completed', 'Dropped', 'Deleted')
")->fetch_assoc();

if (!$student) {
    $_SESSION['error'] = "Past student not found!";
    header('Location: past_students.php');
    exit();
}


// Check if student has photo
$has_photo = !empty($student['photo']) && file_exists($student['photo']);

// Get payment summary
$payment_summary = $conn->query("
    SELECT 
        COALESCE(SUM(amount_paid), 0) as total_paid,
        COUNT(*) as payment_count
    FROM payments 
    WHERE student_id = $student_id
")->fetch_assoc();

$total_paid = $payment_summary['total_paid'] ?? 0;
$pending = $student['total_fees'] - $total_paid;

// Get payment history
$payments = $conn->query("
    SELECT p.*, a.full_name as admin_name 
    FROM payments p 
    JOIN admins a ON p.created_by = a.id 
    WHERE p.student_id = $student_id 
    ORDER BY p.payment_date DESC, p.created_at DESC
");

// Payment status
$payment_status = 'Pending';
if ($pending <= 0) $payment_status = 'Fully Paid';
elseif ($total_paid > 0) $payment_status = 'Partial';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="past_students.php">Past Students</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($student['full_name']); ?></li>
        </ol>
    </nav>
    <h2><i class="fas fa-history text-purple"></i> Past Student Details</h2>
</div>

<div class="row g-4">
    <!-- Student Information Card -->
    <div class="col-lg-4">
        <div class="table-card">
            <div class="text-center mb-3">
                <?php if ($has_photo): ?>
                    <img src="<?php echo htmlspecialchars($student['photo']); ?>" 
                         alt="Student Photo" 
                         class="rounded-circle mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover; border: 5px solid #6c757d;">
                <?php else: ?>
                    <div class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px; font-size: 40px;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                <?php endif; ?>
                
                <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo $student['student_code']; ?></p>
                <?php
                $status_class = 'secondary';
                if ($student['status'] === 'Completed') $status_class = 'success';
                if ($student['status'] === 'Dropped') $status_class = 'warning';
                if ($student['status'] === 'Deleted') $status_class = 'danger';
                ?>
                <span class="badge bg-<?php echo $status_class; ?> mb-2"><?php echo $student['status']; ?></span>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-phone"></i> Phone</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($student['phone']); ?></strong></p>
            </div>
            
            <?php if ($student['email']): ?>
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-envelope"></i> Email</label>
                <p class="mb-0"><?php echo htmlspecialchars($student['email']); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($student['address']): ?>
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-map-marker-alt"></i> Address</label>
                <p class="mb-0"><?php echo htmlspecialchars($student['address']); ?></p>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-folder"></i> Category</label>
                <p class="mb-0"><span class="badge bg-secondary"><?php echo htmlspecialchars($student['category_name']); ?></span></p>
            </div>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-book"></i> Course</label>
                <p class="mb-0"><strong><?php echo htmlspecialchars($student['course_name']); ?></strong></p>
            </div>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-clock"></i> Duration</label>
                <p class="mb-0"><?php echo $student['duration_months']; ?> months</p>
            </div>
            
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-calendar"></i> Enrollment Date</label>
                <p class="mb-0"><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></p>
            </div>
            
            <?php if ($student['completion_date']): ?>
            <div class="mb-3">
                <label class="text-muted mb-1"><i class="fas fa-calendar-check"></i> Completion Date</label>
                <p class="mb-0"><strong class="text-success"><?php echo date('d M Y', strtotime($student['completion_date'])); ?></strong></p>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <div class="d-grid gap-2">
                <?php if ($student['status'] !== 'Deleted'): ?>
                <a href="past_students.php?restore=<?php echo $student_id; ?>" class="btn btn-primary" onclick="return confirm('Restore this student to active list?')">
                    <i class="fas fa-undo"></i> Restore to Active
                </a>
                <?php endif; ?>
                <button class="btn btn-outline-danger" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Details
                </button>
                <a href="past_students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
    
    <!-- Fee Summary and Payments -->
    <div class="col-lg-8">
        <!-- Fee Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total Fees</p>
                        <h4 class="mb-0 text-purple">₹<?php echo number_format($student['total_fees'], 2); ?></h4>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Amount Paid</p>
                        <h4 class="mb-0 text-success">₹<?php echo number_format($total_paid, 2); ?></h4>
                        <small class="text-muted"><?php echo $payment_summary['payment_count']; ?> payments</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Pending Amount</p>
                        <h4 class="mb-0 <?php echo $pending > 0 ? 'text-danger' : 'text-success'; ?>">
                            ₹<?php echo number_format($pending, 2); ?>
                        </h4>
                        <span class="badge bg-<?php echo $pending > 0 ? 'danger' : 'success'; ?>">
                            <?php echo $payment_status; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment History -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-purple mb-0"><i class="fas fa-history"></i> Payment History</h5>
                <?php if ($payments->num_rows > 0): ?>
                <button class="btn btn-sm btn-secondary" onclick="exportTableToCSV('paymentHistoryTable', 'past_student_payments.csv')">
                    <i class="fas fa-download"></i> Export
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($payments->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="paymentHistoryTable">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <!-- <th>Notes</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                            <td><span class="badge bg-success fs-6">₹<?php echo number_format($payment['amount_paid'], 2); ?></span></td>
                            <td><span class="badge bg-info"><?php echo $payment['payment_method']; ?></span></td>
                            <td><small><?php echo htmlspecialchars($payment['admin_name']); ?></small></td>
                            <!-- <td><small><?php // echo htmlspecialchars($payment['notes'] ?? '-'); ?></small></td> -->
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active">
                            <td colspan="2"><strong>Total Paid:</strong></td>
                            <td colspan="4"><strong class="text-success fs-5">₹<?php echo number_format($total_paid, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payments recorded for this student.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Additional Info -->
        <?php if ($pending > 0): ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> <strong>Pending Fees:</strong> This student still has ₹<?php echo number_format($pending, 2); ?> pending.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>