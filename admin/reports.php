<?php
include 'includes/header.php';

// ============================================
// DASHBOARD STATISTICS
// All pending fees calculated at runtime
// ============================================

// Total Active Students
$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'Active'");
$stats['total_students'] = $result->fetch_assoc()['total'];

// Total Revenue (sum of all payments)
$result = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments");
$stats['total_revenue'] = $result->fetch_assoc()['total'];

// Pending Fees (calculated: total_fees - total_paid for active students)
$result = $conn->query("
    SELECT COALESCE(SUM(s.total_fees - COALESCE(p.paid, 0)), 0) as pending 
    FROM students s 
    LEFT JOIN (
        SELECT student_id, SUM(amount_paid) as paid 
        FROM payments 
        GROUP BY student_id
    ) p ON s.id = p.student_id 
    WHERE s.status = 'Active'
");
$stats['pending_fees'] = $result->fetch_assoc()['pending'];

// Total Courses
$result = $conn->query("SELECT COUNT(*) as total FROM courses WHERE status = 'Active'");
$stats['total_courses'] = $result->fetch_assoc()['total'];

// Overdue Students (no payment this month AND has pending)
$result = $conn->query("
    SELECT COUNT(DISTINCT s.id) as count
    FROM students s
    LEFT JOIN (
        SELECT student_id, SUM(amount_paid) as paid 
        FROM payments 
        GROUP BY student_id
    ) p ON s.id = p.student_id
    WHERE s.status = 'Active'
    AND (s.total_fees - COALESCE(p.paid, 0)) > 0
    AND NOT EXISTS (
        SELECT 1 FROM payments p2
        WHERE p2.student_id = s.id
        AND YEAR(p2.payment_date) = YEAR(CURDATE())
        AND MONTH(p2.payment_date) = MONTH(CURDATE())
    )
");
$stats['overdue_students'] = $result->fetch_assoc()['count'];

// ============================================
// MONTHLY REVENUE DATA (Last 6 Months)
// For Chart.js - calculated from payments table
// ============================================
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM payments 
        WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month'
    ");
    $monthlyRevenue[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'amount' => $result->fetch_assoc()['total']
    ];
}

// ============================================
// RECENT STUDENTS (Last 10)
// ============================================
$recentStudents = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.enrollment_date,
        s.status,
        s.total_fees,
        c.name as course_name,
        cat.name as category_name,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    JOIN categories cat ON s.category_id = cat.id 
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status = 'Active'
    GROUP BY s.id
    ORDER BY s.created_at DESC 
    LIMIT 10
");

// ============================================
// OVERDUE STUDENTS (Top 5)
// No payment this month + pending > 0
// ============================================
$overdueStudents = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.phone,
        s.total_fees,
        COALESCE(SUM(p.amount_paid), 0) as total_paid,
        (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) as pending_fees
    FROM students s
    LEFT JOIN payments p ON s.id = p.student_id
    WHERE s.status = 'Active'
    GROUP BY s.id
    HAVING pending_fees > 0
    AND NOT EXISTS (
        SELECT 1 FROM payments p2
        WHERE p2.student_id = s.id
        AND YEAR(p2.payment_date) = YEAR(CURDATE())
        AND MONTH(p2.payment_date) = MONTH(CURDATE())
    )
    ORDER BY pending_fees DESC
    LIMIT 5
");


// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Revenue by course
$course_revenue = $conn->query("SELECT c.name, 
                                SUM(p.amount_paid) as total_revenue,
                                COUNT(DISTINCT s.id) as student_count
                                FROM payments p
                                JOIN students s ON p.student_id = s.id
                                JOIN courses c ON s.course_id = c.id
                                WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'
                                GROUP BY c.id
                                ORDER BY total_revenue DESC
                                LIMIT 10");

// Monthly collection trend (last 12 months)
$monthly_collection = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total 
                           FROM payments 
                           WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month'");
    $monthly_collection[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'amount' => $result->fetch_assoc()['total']
    ];
}

// Payment method distribution
$payment_methods = $conn->query("SELECT payment_method, 
                                COUNT(*) as count,
                                SUM(amount_paid) as total
                                FROM payments
                                WHERE payment_date BETWEEN '$start_date' AND '$end_date'
                                GROUP BY payment_method");

// Top paying students
$top_students = $conn->query("SELECT s.student_code, s.full_name, 
                             SUM(p.amount_paid) as total_paid
                             FROM payments p
                             JOIN students s ON p.student_id = s.id
                             WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'
                             GROUP BY s.id
                             ORDER BY total_paid DESC
                             LIMIT 10");

// Summary statistics
$total_collection = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total 
                                 FROM payments 
                                 WHERE payment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];

$total_students = $conn->query("SELECT COUNT(DISTINCT student_id) as count 
                               FROM payments 
                               WHERE payment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];

// Duration-based revenue
$duration_revenue = $conn->query("SELECT 
                                  s.duration_months,
                                  SUM(p.amount_paid) as total_revenue,
                                  COUNT(DISTINCT s.id) as student_count
                                  FROM payments p
                                  JOIN students s ON p.student_id = s.id
                                  WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'
                                  GROUP BY s.duration_months
                                  ORDER BY s.duration_months ASC");
?>



<div class="page-header">
    <h2><i class="fas fa-chart-bar text-purple"></i> Reports & Analytics</h2>
    <p class="text-muted mb-0">Financial reports and insights</p>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Students</p>
                        <h3 class="mb-0"><?php echo number_format($stats['total_students']); ?></h3>
                    </div>
                    <div class="card-icon icon-purple">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Revenue</p>
                        <h3 class="mb-0">₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Pending Fees</p>
                        <h3 class="mb-0">₹<?php echo number_format($stats['pending_fees'], 2); ?></h3>
                    </div>
                    <div class="card-icon icon-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Overdue Students</p>
                        <h3 class="mb-0 text-danger"><?php echo number_format($stats['overdue_students']); ?></h3>
                        <small class="text-muted">No payment this month</small>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables -->
<div class="row g-4">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-chart-line"></i> Revenue Trend (Last 6 Months)</h5>
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>
    
    <!-- Overdue Students -->
    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle"></i> Overdue Students</h5>
            <small class="text-muted d-block mb-2">Students with no payment this month</small>
            <?php if ($overdueStudents->num_rows > 0): ?>
            <div class="list-group list-group-flush">
                <?php while ($student = $overdueStudents->fetch_assoc()): ?>
                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="list-group-item list-group-item-action list-group-item-danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo $student['student_code']; ?></small>
                        </div>
                        <span class="badge bg-danger">₹<?php echo number_format($student['pending_fees'], 2); ?></span>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> All students are up to date!
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-clock"></i> Revenue by Course Duration</h5>
            <canvas id="durationChart" height="60"></canvas>
        </div>
    </div>
    
    <!-- Recent Students -->
    <div class="col-12">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-users"></i> Recent Students</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Course</th>
                            <th>Total Fees</th>
                            <th>Paid</th>
                            <th>Pending</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $recentStudents->fetch_assoc()): 
                            // Calculate payment status
                            $payment_status = 'pending';
                            if ($student['pending_fees'] <= 0) {
                                $payment_status = 'paid';
                            } elseif ($student['total_paid'] > 0) {
                                $payment_status = 'partial';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><small><?php echo htmlspecialchars($student['category_name']); ?></small></td>
                            <td><small><?php echo htmlspecialchars($student['course_name']); ?></small></td>
                            <td>₹<?php echo number_format($student['total_fees'], 2); ?></td>
                            <td><span class="badge bg-success">₹<?php echo number_format($student['total_paid'], 2); ?></span></td>
                            <td><span class="badge bg-warning">₹<?php echo number_format($student['pending_fees'], 2); ?></span></td>
                            <td><span class="badge status-<?php echo strtolower($payment_status); ?>"><?php echo ucfirst($payment_status); ?></span></td>
                            <td>
                                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// CHART.JS v4 - REVENUE CHART
// Load Chart.js only once from header
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?php echo json_encode(array_column($monthlyRevenue, 'amount')); ?>,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#7c3aed',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₹' + context.parsed.y.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }

    // Duration-based Revenue Chart (Bar)
    const durationCtx = document.getElementById('durationChart');
    if (durationCtx) {
        <?php 
        $duration_labels = [];
        $duration_revenues = [];
        while ($dr = $duration_revenue->fetch_assoc()) {
            $duration_labels[] = $dr['duration_months'] . ' Months';
            $duration_revenues[] = $dr['total_revenue'];
        }
        ?>
        
        new Chart(durationCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($duration_labels); ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?php echo json_encode($duration_revenues); ?>,
                    backgroundColor: 'rgba(124, 58, 237, 0.8)',
                    borderColor: '#7c3aed',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₹' + context.parsed.y.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }
});

 
    
</script>

<?php include 'includes/footer.php'; ?>