<?php
include 'includes/header.php';

// Get all payments with student info
$payments = $conn->query("SELECT p.*, s.student_code, s.full_name, s.phone, c.name as course_name,
                          a.full_name as admin_name
                          FROM payments p
                          JOIN students s ON p.student_id = s.id
                          JOIN courses c ON s.course_id = c.id
                          JOIN admins a ON p.created_by = a.id
                          ORDER BY p.payment_date DESC, p.created_at DESC
                          LIMIT 100");

// Get payment statistics
$today_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total 
                                FROM payments 
                                WHERE DATE(payment_date) = CURDATE()")->fetch_assoc();

$month_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total 
                                FROM payments 
                                WHERE MONTH(payment_date) = MONTH(CURDATE()) 
                                AND YEAR(payment_date) = YEAR(CURDATE())")->fetch_assoc();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-money-bill-wave text-purple"></i> Payments</h2>
        <p class="text-muted mb-0">View all payment transactions</p>
    </div>
    <a href="add_payment.php" class="btn btn-purple">
        <i class="fas fa-plus"></i> Add Payment
    </a>
</div>

<!-- Payment Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Today's Collections</p>
                        <h3 class="mb-0">₹<?php echo number_format($today_payments['total'], 2); ?></h3>
                        <small class="text-muted"><?php echo $today_payments['count']; ?> transactions</small>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">This Month's Collections</p>
                        <h3 class="mb-0">₹<?php echo number_format($month_payments['total'], 2); ?></h3>
                        <small class="text-muted"><?php echo $month_payments['count']; ?> transactions</small>
                    </div>
                    <div class="card-icon icon-purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="mb-3 row">
        <div class="col-md-4">
            <input type="text" class="form-control" id="searchPayment" placeholder="Search payments...">
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control" id="filterDate" onchange="filterPaymentsByDate()">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="filterMethod" onchange="filterPaymentsByMethod()">
                <option value="">All Methods</option>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Card">Card</option>
                <option value="UPI">UPI</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-secondary w-100" onclick="exportTableToCSV('paymentsTable', 'payments.csv')">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover" id="paymentsTable">
            <thead>
                <tr>
                
                    <th>Date</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Received By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($payment = $payments->fetch_assoc()): ?>
                <tr>

                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                        <small class="text-muted"><?php echo $payment['student_code']; ?></small>
                    </td>
                    <td><small><?php echo htmlspecialchars($payment['course_name']); ?></small></td>
                    <td><span class="badge bg-success fs-6">₹<?php echo number_format($payment['amount_paid'], 2); ?></span></td>
                    <td><span class="badge bg-info"><?php echo $payment['payment_method']; ?></span></td>
                    <td><small><?php echo htmlspecialchars($payment['admin_name']); ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $payment['id']; ?>)">
                            <i class="fas fa-receipt"></i>
                        </button>
                        <a href="student_details.php?id=<?php echo $payment['student_id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-user"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // ============================
// SEARCH FUNCTIONALITY - FIXED
// Searches by Student Code (hidden) + visible fields
// ============================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPayment');
    const table = document.getElementById('paymentsTable');
    
    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let found = false;
                
                // Check student code (hidden in data attribute)
                const studentCode = rows[i].getAttribute('data-student-code');
                if (studentCode && studentCode.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                }
                
                // If not found in code, search in visible cells
                if (!found) {
                    const cells = rows[i].getElementsByTagName('td');
                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent || cells[j].innerText;
                        if (cellText.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
});

    
// searchTable('searchPayment', 'paymentsTable');

function filterPaymentsByDate() {
    const date = document.getElementById('filterDate').value;
    const table = document.getElementById('paymentsTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        if (date === '') {
            rows[i].style.display = '';
        } else {
            const dateCell = rows[i].cells[1].innerText;
            rows[i].style.display = dateCell.includes(date) ? '' : 'none';
        }
    }
}

function filterPaymentsByMethod() {
    const method = document.getElementById('filterMethod').value;
    const table = document.getElementById('paymentsTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        if (method === '') {
            rows[i].style.display = '';
        } else {
            const methodCell = rows[i].cells[5].innerText;
            rows[i].style.display = methodCell.includes(method) ? '' : 'none';
        }
    }
}

function viewReceipt(paymentId) {
    window.open(`receipt.php?id=${paymentId}`, '_blank', 'width=800,height=600');
}
</script>

<?php include 'includes/footer.php'; ?>