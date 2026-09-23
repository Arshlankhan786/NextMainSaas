<?php
/**
 * ADMIN — Typing Competition Results
 * Uses admin includes/header.php + footer.php shell
 * Completely independent — does NOT touch ranking/quiz
 */
require_once 'includes/header.php';

// ── Auto-create table if not exists ──
$conn->query("
    CREATE TABLE IF NOT EXISTS typing_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        test_text TEXT,
        typed_text LONGTEXT,
        wpm INT,
        accuracy DECIMAL(5,2),
        errors INT,
        time_taken INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Auto-cleanup: delete typing test records older than 30 days ──
$conn->query("DELETE FROM typing_results WHERE created_at < NOW() - INTERVAL 30 DAY");

// ── Filters ──
$filter_period  = $_GET['period'] ?? 'all';
$filter_student = trim($_GET['student'] ?? '');
$sort_by        = $_GET['sort'] ?? 'latest';
$page           = max(1, intval($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

// Build WHERE clause
$where = "1=1";
$params = [];
$types = '';

switch ($filter_period) {
    case 'today':
        $where .= " AND DATE(tr.created_at) = CURDATE()";
        break;
    case 'week':
        $where .= " AND tr.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $where .= " AND tr.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
}

if (!empty($filter_student)) {
    $where .= " AND s.full_name LIKE ?";
    $params[] = "%$filter_student%";
    $types .= 's';
}

// Sort
$orderBy = 'tr.created_at DESC';
switch ($sort_by) {
    case 'wpm_high':
        $orderBy = 'tr.wpm DESC, tr.created_at DESC';
        break;
    case 'accuracy_high':
        $orderBy = 'tr.accuracy DESC, tr.created_at DESC';
        break;
    case 'latest':
    default:
        $orderBy = 'tr.created_at DESC';
        break;
}

// ── Count total ──
$countSql = "SELECT COUNT(*) as total FROM typing_results tr LEFT JOIN students s ON s.id = tr.student_id WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, ceil($totalRows / $perPage));

// ── Fetch results ──
$sql = "
    SELECT tr.*, s.full_name AS student_name, s.student_code
    FROM typing_results tr
    LEFT JOIN students s ON s.id = tr.student_id
    WHERE $where
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Summary Stats ──
$statsSql = "
    SELECT
        COUNT(*) as total_tests,
        COALESCE(ROUND(AVG(wpm)), 0) as avg_wpm,
        COALESCE(ROUND(AVG(accuracy), 1), 0) as avg_accuracy,
        COALESCE(MAX(wpm), 0) as top_wpm
    FROM typing_results
";
$statsRow = $conn->query($statsSql)->fetch_assoc();
?>

<!-- Page Title -->
<div class="d-flex align-items-center justify-content-between flex-wrap mb-4" style="gap:12px;">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-keyboard text-purple me-2"></i>Typing Competition Results</h4>
        <p class="text-muted mb-0" style="font-size:13px;">Track student typing performance · Independent module</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-purple" style="font-size:11px;padding:6px 12px;">
            <i class="fas fa-database me-1"></i> <?= number_format($totalRows) ?> Results
        </span>
    </div>
</div>

<!-- ── Summary Cards ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dashboard-card p-3 text-center">
            <div class="card-icon icon-purple mx-auto mb-2"><i class="fas fa-file-alt"></i></div>
            <div style="font-size:24px;font-weight:800;color:var(--indigo-700);"><?= number_format($statsRow['total_tests']) ?></div>
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Total Tests</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dashboard-card p-3 text-center">
            <div class="card-icon icon-success mx-auto mb-2"><i class="fas fa-tachometer-alt"></i></div>
            <div style="font-size:24px;font-weight:800;color:var(--emerald);"><?= $statsRow['avg_wpm'] ?></div>
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Avg WPM</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dashboard-card p-3 text-center">
            <div class="card-icon icon-warning mx-auto mb-2"><i class="fas fa-bullseye"></i></div>
            <div style="font-size:24px;font-weight:800;color:var(--amber);"><?= $statsRow['avg_accuracy'] ?>%</div>
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Avg Accuracy</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="dashboard-card p-3 text-center">
            <div class="card-icon icon-danger mx-auto mb-2"><i class="fas fa-trophy"></i></div>
            <div style="font-size:24px;font-weight:800;color:var(--crimson);"><?= $statsRow['top_wpm'] ?></div>
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Top WPM</div>
        </div>
    </div>
</div>

<!-- ── Filters ── -->
<div class="table-card mb-4">
    <form method="GET" class="row g-2 align-items-end">

        <!-- Period Filter -->
        <div class="col-auto">
            <label class="form-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);">Period</label>
            <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all"   <?= $filter_period === 'all'   ? 'selected' : '' ?>>All Time</option>
                <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week"  <?= $filter_period === 'week'  ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
            </select>
        </div>

        <!-- Student Filter -->
        <div class="col-auto">
            <label class="form-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);">Student</label>
            <input type="text" name="student" class="form-control form-control-sm" placeholder="Search by name..."
                   value="<?= htmlspecialchars($filter_student) ?>" style="min-width:180px;">
        </div>

        <!-- Sort -->
        <div class="col-auto">
            <label class="form-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);">Sort By</label>
            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="latest"       <?= $sort_by === 'latest'       ? 'selected' : '' ?>>Latest First</option>
                <option value="wpm_high"      <?= $sort_by === 'wpm_high'     ? 'selected' : '' ?>>Highest WPM</option>
                <option value="accuracy_high" <?= $sort_by === 'accuracy_high'? 'selected' : '' ?>>Best Accuracy</option>
            </select>
        </div>

        <div class="col-auto">
            <button type="submit" class="btn btn-purple btn-sm"><i class="fas fa-search me-1"></i> Filter</button>
        </div>

        <?php if (!empty($filter_student) || $filter_period !== 'all' || $sort_by !== 'latest'): ?>
        <div class="col-auto">
            <a href="typing_results.php" class="btn btn-outline-purple btn-sm"><i class="fas fa-times me-1"></i> Clear</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ── Results Table ── -->
<div class="table-card">
    <?php if (empty($results)): ?>
        <div class="text-center py-5">
            <i class="fas fa-keyboard" style="font-size:48px;color:var(--indigo-400);opacity:0.3;"></i>
            <p class="mt-3" style="color:var(--muted);font-size:14px;">No typing results found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>WPM</th>
                        <th>Accuracy</th>
                        <th>Errors</th>
                        <th>Duration</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $r): ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($r['student_name'] ?? 'Unknown') ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['student_code'] ?? '') ?></div>
                        </td>
                        <td>
                            <span style="font-weight:800;font-size:16px;color:<?= $r['wpm'] >= 60 ? 'var(--emerald)' : ($r['wpm'] >= 30 ? 'var(--amber)' : 'var(--crimson)') ?>;">
                                <?= $r['wpm'] ?>
                            </span>
                            <span style="font-size:10px;color:var(--muted);margin-left:2px;">wpm</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:60px;height:6px;background:rgba(0,0,0,0.06);border-radius:99px;overflow:hidden;">
                                    <div style="width:<?= min(100, $r['accuracy']) ?>%;height:100%;border-radius:99px;background:<?= $r['accuracy'] >= 90 ? 'var(--emerald)' : ($r['accuracy'] >= 70 ? 'var(--amber)' : 'var(--crimson)') ?>;"></div>
                                </div>
                                <span style="font-weight:700;font-size:13px;"><?= number_format($r['accuracy'], 1) ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $r['errors'] == 0 ? 'rgba(5,150,105,0.1)' : 'rgba(220,38,38,0.1)' ?>;color:<?= $r['errors'] == 0 ? 'var(--emerald)' : 'var(--crimson)' ?>;font-weight:700;font-size:12px;">
                                <?= $r['errors'] ?>
                            </span>
                        </td>
                        <td style="font-size:13px;font-weight:600;">
                            <?php
                            $tm = (int)$r['time_taken'];
                            if ($tm >= 60) {
                                echo floor($tm / 60) . 'm ' . ($tm % 60) . 's';
                            } else {
                                echo $tm . 's';
                            }
                            ?>
                        </td>
                        <td style="font-size:12px;color:var(--muted);">
                            <?= date('d M Y', strtotime($r['created_at'])) ?>
                            <br>
                            <span style="font-size:10px;"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex align-items-center justify-content-between mt-3 px-2">
            <div style="font-size:12px;color:var(--muted);">
                Showing <?= $offset + 1 ?> – <?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&period=<?= $filter_period ?>&student=<?= urlencode($filter_student) ?>&sort=<?= $sort_by ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                    ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&period=<?= $filter_period ?>&student=<?= urlencode($filter_student) ?>&sort=<?= $sort_by ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&period=<?= $filter_period ?>&student=<?= urlencode($filter_student) ?>&sort=<?= $sort_by ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── WPM Distribution Chart ── -->
<?php
// Get WPM distribution for chart
$chartData = $conn->query("
    SELECT
        CASE
            WHEN wpm < 20 THEN '0-19'
            WHEN wpm < 40 THEN '20-39'
            WHEN wpm < 60 THEN '40-59'
            WHEN wpm < 80 THEN '60-79'
            WHEN wpm < 100 THEN '80-99'
            ELSE '100+'
        END as wpm_range,
        COUNT(*) as count
    FROM typing_results
    GROUP BY wpm_range
    ORDER BY MIN(wpm) ASC
")->fetch_all(MYSQLI_ASSOC);

if (!empty($chartData)):
?>
<div class="table-card mt-4">
    <h6 class="fw-bold mb-3" style="font-size:13px;"><i class="fas fa-chart-bar text-purple me-2"></i>WPM Distribution</h6>
    <canvas id="wpmChart" style="max-height:250px;"></canvas>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    const ctx = document.getElementById('wpmChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($chartData, 'wpm_range')) ?>,
            datasets: [{
                label: 'Number of Tests',
                data: <?= json_encode(array_map('intval', array_column($chartData, 'count'))) ?>,
                backgroundColor: [
                    'rgba(220, 38, 38, 0.7)',
                    'rgba(217, 119, 6, 0.7)',
                    'rgba(251, 191, 36, 0.7)',
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(99, 102, 241, 0.7)',
                    'rgba(167, 139, 250, 0.7)'
                ],
                borderColor: [
                    'rgba(220, 38, 38, 1)',
                    'rgba(217, 119, 6, 1)',
                    'rgba(251, 191, 36, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(99, 102, 241, 1)',
                    'rgba(167, 139, 250, 1)'
                ],
                borderWidth: 1,
                borderRadius: 6
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
                    ticks: { precision: 0, font: { size: 11, family: 'Sora' } },
                    grid: { color: 'rgba(0,0,0,0.04)' }
                },
                x: {
                    ticks: { font: { size: 11, family: 'Sora' } },
                    grid: { display: false }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
