<?php
/**
 * STUDENT — My Tests
 * Lists all assigned tests with status badges and action buttons
 */
require_once 'includes/header.php';

// Fetch all tests assigned to this student
$tests = [];
$stmt = $conn->prepare("
    SELECT at.id as assignment_id, at.status, at.started_at, at.completed_at,
           t.id as test_id, t.title, t.time_per_question,
           (SELECT COUNT(*) FROM questions WHERE test_id = t.id) as question_count,
           tr.score_percentage, tr.correct_count, tr.total_questions,
           tr.is_disqualified, tr.points_awarded
    FROM assigned_tests at
    JOIN tests t ON t.id = at.test_id
    LEFT JOIN test_results tr ON tr.assigned_test_id = at.id
    WHERE at.student_id = ?
    ORDER BY
        FIELD(at.status, 'In Progress', 'Not Started', 'Completed'),
        at.completed_at DESC,
        at.started_at DESC
");
$stmt->bind_param("i", $sid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $tests[] = $r;
$stmt->close();

// Stats
$total = count($tests);
$completed = 0;
$in_progress = 0;
foreach ($tests as $t) {
    if ($t['status'] === 'Completed') $completed++;
    if ($t['status'] === 'In Progress') $in_progress++;
}
?>

<style>
/* ── My Tests Page ── */
.mt-header { margin-bottom: 24px; }
.mt-header h4 { font-size: 20px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.mt-header small { font-size: 12px; color: var(--text-muted, rgba(255,255,255,0.5)); }

.mt-stats {
    display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;
}

.mt-stat {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 14px 20px;
    flex: 1; min-width: 120px;
    text-align: center;
}

.mt-stat .val {
    font-size: 24px; font-weight: 900; color: #fff;
    font-variant-numeric: tabular-nums;
}

.mt-stat .lbl {
    font-size: 11px; font-weight: 600;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase; letter-spacing: .5px;
    margin-top: 2px;
}

.mt-stat.stat-total .val { color: #A78BFA; }
.mt-stat.stat-progress .val { color: #00F0FF; }
.mt-stat.stat-done .val { color: #4ADE80; }

/* Test cards */
.mt-list { display: flex; flex-direction: column; gap: 12px; }

.mt-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: 0.2s ease;
}

.mt-card:hover {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.12);
}

.mt-card .mt-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}

.mt-icon.ns { background: rgba(167,139,250,0.12); color: #A78BFA; }
.mt-icon.ip { background: rgba(0,240,255,0.12); color: #00F0FF; }
.mt-icon.co { background: rgba(74,222,128,0.12); color: #4ADE80; }

.mt-card .mt-info { flex: 1; min-width: 0; }
.mt-card .mt-title {
    font-size: 14px; font-weight: 700; color: #fff;
    margin-bottom: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mt-card .mt-meta {
    font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 500;
    display: flex; gap: 12px; flex-wrap: wrap;
}

.mt-card .mt-status {
    padding: 4px 12px; border-radius: 99px;
    font-size: 11px; font-weight: 700; letter-spacing: .3px;
    flex-shrink: 0;
}

.mt-status.ns { background: rgba(167,139,250,0.1); color: #A78BFA; }
.mt-status.ip { background: rgba(0,240,255,0.1); color: #00F0FF; }
.mt-status.co { background: rgba(74,222,128,0.1); color: #4ADE80; }

.mt-card .mt-score {
    font-size: 16px; font-weight: 900;
    font-variant-numeric: tabular-nums;
    min-width: 55px; text-align: right; flex-shrink: 0;
}
.mt-score.high { color: #4ADE80; }
.mt-score.mid  { color: #FBBF24; }
.mt-score.low  { color: #FF6B6B; }

.mt-badge-dq {
    padding: 3px 10px; border-radius: 99px;
    font-size: 10px; font-weight: 700; letter-spacing: .3px;
    background: rgba(239,68,68,0.12); color: #EF4444;
    border: 1px solid rgba(239,68,68,0.2);
    display: inline-flex; align-items: center; gap: 4px;
}

.mt-card .mt-action { flex-shrink: 0; }
.mt-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px;
    font-size: 12px; font-weight: 700;
    text-decoration: none; transition: 0.2s ease;
    border: none; cursor: pointer;
}

.mt-btn.start {
    background: linear-gradient(135deg, #A78BFA, #7C3AED);
    color: #fff; box-shadow: 0 4px 14px rgba(167,139,250,0.25);
}
.mt-btn.start:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(167,139,250,0.35);
    color: #fff;
}

.mt-btn.resume {
    background: linear-gradient(135deg, #00F0FF, #0EA5E9);
    color: #fff; box-shadow: 0 4px 14px rgba(0,240,255,0.2);
}
.mt-btn.resume:hover {
    transform: translateY(-1px); color: #fff;
}

.mt-btn.result {
    background: rgba(74,222,128,0.12);
    color: #4ADE80; border: 1px solid rgba(74,222,128,0.2);
}
.mt-btn.result:hover {
    background: rgba(74,222,128,0.18); color: #4ADE80;
}

.mt-empty {
    text-align: center; padding: 60px 20px;
    color: rgba(255,255,255,0.3); font-size: 14px;
}
.mt-empty i { font-size: 40px; display: block; margin-bottom: 12px; }

@media(max-width:575px) {
    .mt-card { flex-wrap: wrap; gap: 10px; }
    .mt-card .mt-score { min-width: auto; }
}
</style>

<!-- ═══ PAGE CONTENT ═══ -->
<div class="mt-header">
    <h4><i class="fas fa-clipboard-list me-2"></i>My Tests</h4>
    <small>View your assigned quizzes and test results</small>
</div>

<!-- Stats -->
<div class="mt-stats">
    <div class="mt-stat stat-total">
        <div class="val"><?= $total ?></div>
        <div class="lbl">Total Tests</div>
    </div>
    <div class="mt-stat stat-progress">
        <div class="val"><?= $in_progress ?></div>
        <div class="lbl">In Progress</div>
    </div>
    <div class="mt-stat stat-done">
        <div class="val"><?= $completed ?></div>
        <div class="lbl">Completed</div>
    </div>
</div>

<!-- Test List -->
<div class="mt-list">
    <?php if (empty($tests)): ?>
        <div class="mt-empty">
            <i class="fas fa-inbox"></i>
            No tests assigned to you yet. Check back later!
        </div>
    <?php endif; ?>

    <?php foreach ($tests as $t):
        $st = $t['status'];
        $sc = $st === 'In Progress' ? 'ip' : ($st === 'Completed' ? 'co' : 'ns');
        $icon = $st === 'In Progress' ? 'fa-play-circle' : ($st === 'Completed' ? 'fa-check-circle' : 'fa-file-alt');
    ?>
    <div class="mt-card">
        <div class="mt-icon <?= $sc ?>"><i class="fas <?= $icon ?>"></i></div>
        <div class="mt-info">
            <div class="mt-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="mt-meta">
                <span><i class="fas fa-question-circle me-1"></i><?= $t['question_count'] ?> Questions</span>
                <span><i class="fas fa-clock me-1"></i><?= $t['time_per_question'] ?>s / question</span>
                <?php if ($t['completed_at']): ?>
                    <span><i class="fas fa-calendar me-1"></i><?= date('d M Y', strtotime($t['completed_at'])) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <span class="mt-status <?= $sc ?>"><?= $st ?></span>

        <?php if ($st === 'Completed' && $t['score_percentage'] !== null):
            $pct = floatval($t['score_percentage']);
            $pcls = $pct >= 70 ? 'high' : ($pct >= 40 ? 'mid' : 'low');
            $isDQ = intval($t['is_disqualified'] ?? 0);
        ?>
            <?php if ($isDQ): ?>
                <span class="mt-badge-dq"><i class="fas fa-ban"></i> Disqualified</span>
            <?php endif; ?>
            <div class="mt-score <?= $pcls ?>"><?= number_format($pct, 1) ?>%</div>
        <?php else: ?>
            <div class="mt-score" style="color:rgba(255,255,255,0.15);">—</div>
        <?php endif; ?>

        <div class="mt-action">
            <?php if ($st === 'Not Started'): ?>
                <a href="take_quiz.php?assignment_id=<?= $t['assignment_id'] ?>" class="mt-btn start">
                    <i class="fas fa-play"></i> Start
                </a>
            <?php elseif ($st === 'In Progress'): ?>
                <a href="take_quiz.php?assignment_id=<?= $t['assignment_id'] ?>" class="mt-btn resume">
                    <i class="fas fa-redo"></i> Resume
                </a>
            <?php else: ?>
                <a href="quiz_result.php?assignment_id=<?= $t['assignment_id'] ?>" class="mt-btn result">
                    <i class="fas fa-chart-pie"></i> Results
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
