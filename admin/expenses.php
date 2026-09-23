<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$admin = getCurrentAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_expense') {
        $expense_name = sanitize($_POST['expense_name']);
        $amount = (float)$_POST['amount'];
        $expense_date = sanitize($_POST['expense_date']);
        $notes = sanitize($_POST['notes']);
        
        $stmt = $conn->prepare("INSERT INTO expenses (expense_name, amount, expense_date, notes, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssi", $expense_name, $amount, $expense_date, $notes, $admin['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Expense added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add expense.";
        }
        $stmt->close();
        header('Location: expenses.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
        exit();
    }
    
    if ($_POST['action'] === 'delete_expense') {
        $id = (int)$_POST['expense_id'];
        $conn->query("DELETE FROM expenses WHERE id = $id");
        $_SESSION['success'] = "Expense deleted!";
        header('Location: expenses.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
        exit();
    }
}

// Filters
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'current_month';
$custom_start = isset($_GET['custom_start']) ? $_GET['custom_start'] : '';
$custom_end = isset($_GET['custom_end']) ? $_GET['custom_end'] : '';

// Date range logic
switch ($filter_type) {
    case 'current_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-01');
        $end_date = $custom_end ?: date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Analytics
$total_fees = $conn->query("
    SELECT COALESCE(SUM(amount_paid), 0) as total 
    FROM payments 
    WHERE payment_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'];

$total_expenses = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM expenses 
    WHERE expense_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'];

$net_profit = $total_fees - $total_expenses;

// Expense list
$expenses = $conn->query("
    SELECT e.*, a.full_name as added_by 
    FROM expenses e 
    LEFT JOIN admins a ON e.created_by = a.id 
    WHERE e.expense_date BETWEEN '$start_date' AND '$end_date'
    ORDER BY e.expense_date DESC, e.created_at DESC
");

$expense_count = $expenses->num_rows;

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-chart-line text-purple"></i> Monthly Expense & Profit Analysis</h2>
        <p class="text-muted mb-0">Track expenses and calculate profit</p>
    </div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
        <i class="fas fa-plus"></i> Add Expense
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="table-card mb-4">
    <h6 class="text-purple mb-3"><i class="fas fa-filter"></i> Date Filters</h6>
    <form method="GET" id="filterForm">
        <div class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="filter_type" id="filter_type" onchange="toggleCustomDates()">
                    <option value="current_month" <?php echo $filter_type === 'current_month' ? 'selected' : ''; ?>>Current Month</option>
                    <option value="last_month" <?php echo $filter_type === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                    <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-3" id="custom_start_wrapper" style="display: <?php echo $filter_type === 'custom' ? 'block' : 'none'; ?>;">
                <input type="date" class="form-control" name="custom_start" value="<?php echo $custom_start; ?>" placeholder="Start Date">
            </div>
            <div class="col-md-3" id="custom_end_wrapper" style="display: <?php echo $filter_type === 'custom' ? 'block' : 'none'; ?>;">
                <input type="date" class="form-control" name="custom_end" value="<?php echo $custom_end; ?>" placeholder="End Date">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-purple w-100">
                    <i class="fas fa-search"></i> Apply Filter
                </button>
            </div>
        </div>
    </form>
    <div class="mt-2">
        <small class="text-muted">
            <i class="fas fa-calendar"></i> Showing: <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong>
        </small>
    </div>
</div>

<!-- Analytics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Fees Collected</p>
                        <h4 class="mb-0 text-success">₹<?php echo number_format($total_fees, 2); ?></h4>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Expenses</p>
                        <h4 class="mb-0 text-danger">₹<?php echo number_format($total_expenses, 2); ?></h4>
                        <small class="text-muted"><?php echo $expense_count; ?> expenses</small>
                    </div>
                    <div class="card-icon icon-danger">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Net Profit / Loss</p>
                        <h4 class="mb-0 <?php echo $net_profit >= 0 ? 'text-purple' : 'text-warning'; ?>">
                            ₹<?php echo number_format(abs($net_profit), 2); ?>
                        </h4>
                        <small class="<?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?>
                        </small>
                    </div>
                    <div class="card-icon <?php echo $net_profit >= 0 ? 'icon-purple' : 'icon-warning'; ?>">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Profit Margin</p>
                        <h4 class="mb-0 text-info">
                            <?php echo $total_fees > 0 ? number_format(($net_profit / $total_fees) * 100, 1) : '0'; ?>%
                        </h4>
                    </div>
                    <div class="card-icon icon-info">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="table-card">
            <h6 class="text-purple mb-3"><i class="fas fa-chart-bar"></i> Fees vs Expenses Comparison</h6>
            <canvas id="profitChart" height="80"></canvas>
        </div>
    </div>
</div>

<!-- Expense List -->
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-purple mb-0"><i class="fas fa-list"></i> Expense Details (<?php echo $expense_count; ?>)</h5>
        <?php if ($expense_count > 0): ?>
        <button class="btn btn-sm btn-secondary" onclick="exportTableToCSV('expenseTable', 'expenses_<?php echo $start_date; ?>_to_<?php echo $end_date; ?>.csv')">
            <i class="fas fa-download"></i> Export CSV
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($expense_count > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover" id="expenseTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense Name</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Added By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($expense = $expenses->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($expense['expense_name']); ?></strong></td>
                    <td><span class="badge bg-danger fs-6">₹<?php echo number_format($expense['amount'], 2); ?></span></td>
                    <td><small><?php echo htmlspecialchars($expense['notes'] ?: '-'); ?></small></td>
                    <td><small><?php echo htmlspecialchars($expense['added_by'] ?: 'N/A'); ?></small></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense?');">
                            <input type="hidden" name="action" value="delete_expense">
                            <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="table-active">
                    <td colspan="2"><strong>Total Expenses:</strong></td>
                    <td colspan="4"><strong class="text-danger fs-5">₹<?php echo number_format($total_expenses, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No expenses recorded for this period.
    </div>
    <?php endif; ?>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_expense">
                    
                    <div class="mb-3">
                        <label class="form-label">Expense Name *</label>
                        <input type="text" class="form-control" name="expense_name" required placeholder="E.g., Office Rent, Electricity Bill">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (₹) *</label>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Additional details about this expense..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-purple">
                        <i class="fas fa-save"></i> Add Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleCustomDates() {
    const filterType = document.getElementById('filter_type').value;
    const showCustom = filterType === 'custom';
    document.getElementById('custom_start_wrapper').style.display = showCustom ? 'block' : 'none';
    document.getElementById('custom_end_wrapper').style.display = showCustom ? 'block' : 'none';
    
    if (filterType !== 'custom') {
        document.getElementById('filterForm').submit();
    }
}

// Profit Chart
const ctx = document.getElementById('profitChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Fees Collected', 'Expenses', 'Net Profit/Loss'],
        datasets: [{
            label: 'Amount (₹)',
            data: [
                <?php echo $total_fees; ?>,
                <?php echo $total_expenses; ?>,
                <?php echo abs($net_profit); ?>
            ],
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                '<?php echo $net_profit >= 0 ? "rgba(124, 58, 237, 0.8)" : "rgba(251, 191, 36, 0.8)"; ?>'
            ],
            borderColor: [
                'rgba(16, 185, 129, 1)',
                'rgba(239, 68, 68, 1)',
                '<?php echo $net_profit >= 0 ? "rgba(124, 58, 237, 1)" : "rgba(251, 191, 36, 1)"; ?>'
            ],
            borderWidth: 2
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
                        return '₹' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2});
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
</script>

<?php include 'includes/footer.php'; ?>