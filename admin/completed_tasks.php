<?php
include 'includes/header.php';

$admin = getCurrentAdmin();

// Get completed tasks for current admin
$completed_tasks = $conn->query("
    SELECT * FROM admin_tasks 
    WHERE admin_id = {$admin['id']} 
    AND status = 'Completed'
    ORDER BY completed_at DESC
");

// Monthly analysis
$monthly_analysis = $conn->query("
    SELECT 
        DATE_FORMAT(completed_at, '%Y-%m') as month,
        DATE_FORMAT(completed_at, '%M %Y') as month_name,
        COUNT(*) as task_count
    FROM admin_tasks
    WHERE admin_id = {$admin['id']}
    AND status = 'Completed'
    GROUP BY DATE_FORMAT(completed_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

$total_completed = $completed_tasks->num_rows;
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="fas fa-check-circle text-success"></i> Completed Tasks</h2>
            <p class="text-muted mb-0">Your task completion history</p>
        </div>
        <a href="tasks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tasks
        </a>
    </div>
</div>

<!-- Stats Card -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Total Completed Tasks</p>
                        <h3 class="mb-0 text-success"><?php echo $total_completed; ?></h3>
                        <small class="text-muted">All time</small>
                    </div>
                    <div class="card-icon icon-success">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Analysis -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-chart-bar"></i> Monthly Task Analysis</h5>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Completed Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $monthly_analysis->data_seek(0);
                        while ($month = $monthly_analysis->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong><?php echo $month['month_name']; ?></strong></td>
                            <td><span class="badge bg-success"><?php echo $month['task_count']; ?> tasks</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-chart-line"></i> Completion Trend</h5>
            <canvas id="taskChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Completed Tasks List -->
<div class="table-card">
    <h5 class="text-purple mb-3"><i class="fas fa-history"></i> All Completed Tasks</h5>
    
    <?php if ($total_completed > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $completed_tasks->data_seek(0);
                $sno = 1;
                while ($task = $completed_tasks->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td><strong><?php echo htmlspecialchars($task['task_title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($task['task_description'] ?? '-'); ?></td>
                    <td><small><?php echo date('d M Y', strtotime($task['created_at'])); ?></small></td>
                    <td>
                        <span class="badge bg-success">
                            <i class="fas fa-check"></i> <?php echo date('d M Y, h:i A', strtotime($task['completed_at'])); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No completed tasks yet. Start completing your tasks!
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('taskChart');
    if (ctx) {
        <?php 
        $monthly_analysis->data_seek(0);
        $months = [];
        $counts = [];
        while ($m = $monthly_analysis->fetch_assoc()) {
            $months[] = $m['month_name'];
            $counts[] = $m['task_count'];
        }
        $months = array_reverse($months);
        $counts = array_reverse($counts);
        ?>
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Tasks Completed',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>