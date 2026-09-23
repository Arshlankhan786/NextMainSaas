<?php
/**
 * STUDENT — Quiz Results Page
 * Uses existing student header/footer pattern
 */
require_once 'includes/header.php';

$assignment_id = intval($_GET['assignment_id'] ?? 0);

// Fetch result data
$result_data = null;
$test_data = null;
$answers = [];

if ($assignment_id > 0) {
    // Get test result
    $stmt = $conn->prepare("
        SELECT tr.*, t.title, t.time_per_question, at.started_at, at.completed_at as at_completed
        FROM test_results tr
        JOIN tests t ON t.id = tr.test_id
        JOIN assigned_tests at ON at.id = tr.assigned_test_id
        WHERE tr.assigned_test_id = ? AND tr.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $sid);
    $stmt->execute();
    $result_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result_data) {
        // Get detailed answers
        $astmt = $conn->prepare("
            SELECT q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   q.correct_option, sa.selected_option, sa.is_correct, sa.time_taken_seconds
            FROM questions q
            LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.assigned_test_id = ?
            WHERE q.test_id = ?
            ORDER BY q.sort_order ASC, q.id ASC
        ");
        $astmt->bind_param("ii", $assignment_id, $result_data['test_id']);
        $astmt->execute();
        $ares = $astmt->get_result();
        while ($r = $ares->fetch_assoc()) $answers[] = $r;
        $astmt->close();
    }
}

// Calculate time taken formatted
$time_formatted = '—';
if ($result_data) {
    $total_sec = intval($result_data['total_time_seconds']);
    $mins = floor($total_sec / 60);
    $secs = $total_sec % 60;
    $time_formatted = sprintf('%02d:%02d', $mins, $secs);
}

// Score color class
$score_pct = $result_data ? floatval($result_data['score_percentage']) : 0;
$score_cls = $score_pct >= 70 ? 'score-high' : ($score_pct >= 40 ? 'score-mid' : 'score-low');
$is_disqualified = $result_data ? intval($result_data['is_disqualified'] ?? 0) : 0;
$points_awarded = $result_data ? intval($result_data['points_awarded'] ?? 0) : 0;
?>

<style>
/* ── Results Page Styles ── */
.qr-header { margin-bottom: 24px; }
.qr-header h4 { font-size: 20px; font-weight: 700; margin: 0 0 4px; color: #fff; }
.qr-header small { font-size: 12px; color: var(--text-muted, rgba(255,255,255,0.5)); }

.qr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
@media(max-width:767px) { .qr-grid { grid-template-columns: 1fr; } }

.qr-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

/* Score summary card */
.score-summary { text-align: center; }
.score-big {
    font-size: 56px; font-weight: 900;
    line-height: 1; margin: 12px 0 6px;
    font-variant-numeric: tabular-nums;
}
.score-high .score-big { color: #4ADE80; }
.score-mid .score-big  { color: #FBBF24; }
.score-low .score-big  { color: #FF6B6B; }

.score-label { font-size: 13px; color: rgba(255,255,255,0.5); font-weight: 600; }
.score-details { display: flex; justify-content: center; gap: 24px; margin-top: 16px; }
.score-stat { text-align: center; }
.score-stat .val { font-size: 18px; font-weight: 800; color: #fff; }
.score-stat .lbl { font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }

/* Chart */
.chart-wrap { display: flex; align-items: center; justify-content: center; position: relative; }
.chart-wrap canvas { max-width: 220px; max-height: 220px; }

/* Answer review */
.qr-answers-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.qr-answers-card h5 {
    font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}

.qa-item {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
}

.qa-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; cursor: pointer;
    transition: background 0.2s ease;
}

.qa-header:hover { background: rgba(255,255,255,0.03); }

.qa-q-text {
    font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.85);
    flex: 1; margin-right: 12px;
}

.qa-badge {
    flex-shrink: 0; padding: 3px 10px; border-radius: 99px;
    font-size: 10px; font-weight: 700; letter-spacing: .3px;
}
.qa-badge.correct { background: rgba(74,222,128,0.15); color: #4ADE80; }
.qa-badge.wrong   { background: rgba(255,107,107,0.15); color: #FF6B6B; }
.qa-badge.skipped  { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.4); }

.qa-body {
    display: none; padding: 0 16px 14px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.qa-body.open { display: block; padding-top: 12px; }

.qa-opt {
    padding: 6px 12px; border-radius: 8px;
    font-size: 12.5px; font-weight: 500; margin-bottom: 4px;
    color: rgba(255,255,255,0.6);
    display: flex; align-items: center; gap: 8px;
}

.qa-opt .opt-ltr {
    width: 24px; height: 24px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; flex-shrink: 0;
    background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.4);
}

.qa-opt.is-correct {
    background: rgba(74,222,128,0.1); color: #4ADE80;
}
.qa-opt.is-correct .opt-ltr { background: #4ADE80; color: #1a1035; }

.qa-opt.is-wrong {
    background: rgba(255,107,107,0.1); color: #FF6B6B;
}
.qa-opt.is-wrong .opt-ltr { background: #FF6B6B; color: #fff; }

.qa-time { font-size: 10px; color: rgba(255,255,255,0.3); margin-top: 6px; }

/* Back link */
.qr-back {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 10px;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #fff; text-decoration: none; font-size: 13px; font-weight: 600;
    transition: 0.2s ease;
}
.qr-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

/* Disqualification banner */
.qr-dq-banner {
    background: rgba(239,68,68,0.1);
    border: 1.5px solid rgba(239,68,68,0.25);
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.qr-dq-banner .dq-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: rgba(239,68,68,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #EF4444; flex-shrink: 0;
}
.qr-dq-banner .dq-text {
    font-size: 13px; color: rgba(255,255,255,0.7); line-height: 1.5;
}
.qr-dq-banner .dq-text strong { color: #EF4444; }

/* Points badge */
.qr-points-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 800;
    margin-top: 12px;
}
.qr-points-badge.reward {
    background: rgba(74,222,128,0.12);
    color: #4ADE80;
    border: 1px solid rgba(74,222,128,0.2);
}
.qr-points-badge.penalty {
    background: rgba(239,68,68,0.12);
    color: #EF4444;
    border: 1px solid rgba(239,68,68,0.2);
}
.qr-points-badge.neutral {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.4);
    border: 1px solid rgba(255,255,255,0.1);
}
</style>

<?php if (!$result_data): ?>
    <div class="qr-header">
        <h4><i class="fas fa-chart-pie me-2"></i>Quiz Results</h4>
    </div>
    <div class="qr-card" style="text-align:center;padding:40px;">
        <i class="fas fa-search" style="font-size:32px;color:rgba(255,255,255,0.2);margin-bottom:12px;display:block;"></i>
        <p style="color:rgba(255,255,255,0.5);font-size:14px;">No results found. The test might not have been submitted yet.</p>
        <a href="my_tests.php" class="qr-back mt-3"><i class="fas fa-arrow-left"></i> My Tests</a>
    </div>
<?php else: ?>

<div class="qr-header">
    <h4><i class="fas fa-chart-pie me-2"></i><?= htmlspecialchars($result_data['title']) ?> — Results</h4>
    <small>Completed on <?= date('d M Y, h:i A', strtotime($result_data['at_completed'])) ?></small>
</div>

<?php if ($is_disqualified): ?>
<div class="qr-dq-banner">
    <div class="dq-icon"><i class="fas fa-ban"></i></div>
    <div class="dq-text">
        <strong>This test was disqualified</strong> due to anti-cheat violation (developer tools detected).
        Score set to 0% and <strong><?= $points_awarded ?> ranking point<?= abs($points_awarded) !== 1 ? 's' : '' ?></strong> applied.
    </div>
</div>
<?php endif; ?>

<!-- Score + Chart Grid -->
<div class="qr-grid">
    <!-- Score Card -->
    <div class="qr-card score-summary <?= $score_cls ?>">
        <div class="score-label">YOUR SCORE</div>
        <div class="score-big"><?= number_format($score_pct, 1) ?>%</div>
        <div class="score-label"><?= $result_data['correct_count'] ?> / <?= $result_data['total_questions'] ?> Correct</div>
        <div class="score-details">
            <div class="score-stat">
                <div class="val" style="color:#4ADE80"><?= $result_data['correct_count'] ?></div>
                <div class="lbl">Correct</div>
            </div>
            <div class="score-stat">
                <div class="val" style="color:#FF6B6B"><?= $result_data['total_questions'] - $result_data['correct_count'] ?></div>
                <div class="lbl">Wrong</div>
            </div>
            <div class="score-stat">
                <div class="val"><?= $time_formatted ?></div>
                <div class="lbl">Time</div>
            </div>
        </div>
        <?php if ($points_awarded > 0): ?>
            <div class="qr-points-badge reward"><i class="fas fa-arrow-up"></i> +<?= $points_awarded ?> ranking points</div>
        <?php elseif ($points_awarded < 0): ?>
            <div class="qr-points-badge penalty"><i class="fas fa-arrow-down"></i> <?= $points_awarded ?> ranking points</div>
        <?php else: ?>
            <div class="qr-points-badge neutral"><i class="fas fa-minus"></i> 0 ranking points</div>
        <?php endif; ?>
    </div>

    <!-- Chart Card -->
    <div class="qr-card chart-wrap">
        <canvas id="resultChart"></canvas>
    </div>
</div>

<!-- Answer Review -->
<div class="qr-answers-card">
    <h5><i class="fas fa-clipboard-check"></i> Answer Review</h5>
    <?php foreach ($answers as $i => $a):
        $opts = ['A' => $a['option_a'], 'B' => $a['option_b'], 'C' => $a['option_c'], 'D' => $a['option_d']];
        $selected = $a['selected_option'];
        $correct  = $a['correct_option'];
        $badge = 'skipped';
        $badgeText = 'Skipped';
        if ($selected) {
            $badge = $a['is_correct'] ? 'correct' : 'wrong';
            $badgeText = $a['is_correct'] ? 'Correct' : 'Wrong';
        }
    ?>
    <div class="qa-item">
        <div class="qa-header" onclick="this.nextElementSibling.classList.toggle('open')">
            <span class="qa-q-text"><strong style="color:rgba(255,255,255,0.4);margin-right:6px;">Q<?= $i+1 ?>.</strong> <?= htmlspecialchars($a['question_text']) ?></span>
            <span class="qa-badge <?= $badge ?>"><?= $badgeText ?></span>
        </div>
        <div class="qa-body">
            <?php foreach ($opts as $letter => $text):
                $cls = '';
                if ($letter === $correct) $cls = 'is-correct';
                elseif ($letter === $selected && $letter !== $correct) $cls = 'is-wrong';
            ?>
            <div class="qa-opt <?= $cls ?>">
                <span class="opt-ltr"><?= $letter ?></span>
                <?= htmlspecialchars($text) ?>
                <?php if ($letter === $correct): ?> <i class="fas fa-check" style="margin-left:auto;font-size:11px;"></i><?php endif; ?>
                <?php if ($letter === $selected && $letter !== $correct): ?> <i class="fas fa-times" style="margin-left:auto;font-size:11px;"></i><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="qa-time"><i class="fas fa-clock me-1"></i> Time: <?= intval($a['time_taken_seconds']) ?>s</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<a href="my_tests.php" class="qr-back"><i class="fas fa-arrow-left"></i> Back to My Tests</a>
<br><br>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('resultChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Correct', 'Wrong'],
            datasets: [{
                data: [<?= $result_data['correct_count'] ?>, <?= $result_data['total_questions'] - $result_data['correct_count'] ?>],
                backgroundColor: ['#4ADE80', '#FF6B6B'],
                borderColor: ['rgba(74,222,128,0.3)', 'rgba(255,107,107,0.3)'],
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255,255,255,0.6)',
                        font: { family: "'Poppins', sans-serif", size: 12, weight: '600' },
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 10
                    }
                }
            },
            animation: {
                animateRotate: true,
                duration: 1200,
                easing: 'easeOutQuart'
            }
        }
    });
});
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
