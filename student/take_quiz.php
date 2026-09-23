<?php
/**
 * STUDENT — Take Quiz (Immersive Full-Page Experience)
 * Standalone page — does NOT use student includes/header.php
 */
require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/student_auth.php';
requireStudentLogin();

$student = getCurrentStudent();
$sid = (int)$student['id'];
$assignment_id = intval($_GET['assignment_id'] ?? 0);

// ── Validate assignment ─────────────────────────────────────────
$error = null;
$assignment = null;
$test = null;
$question_count = 0;

if ($assignment_id <= 0) {
    $error = 'Invalid quiz link.';
} else {
    $stmt = $conn->prepare("
        SELECT at.*, t.title, t.time_per_question
        FROM assigned_tests at
        JOIN tests t ON t.id = at.test_id
        WHERE at.id = ? AND at.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $sid);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        $error = 'This quiz is not assigned to you.';
    } elseif ($assignment['status'] === 'Completed') {
        $error = 'completed'; // Special flag
    } else {
        $test = $assignment;

        // Count questions
        $cstmt = $conn->prepare("SELECT COUNT(*) as cnt FROM questions WHERE test_id = ?");
        $cstmt->bind_param("i", $assignment['test_id']);
        $cstmt->execute();
        $question_count = $cstmt->get_result()->fetch_assoc()['cnt'];
        $cstmt->close();

        // If "Not Started" → mark "In Progress"
        if ($assignment['status'] === 'Not Started') {
            $ustmt = $conn->prepare("UPDATE assigned_tests SET status = 'In Progress', started_at = NOW() WHERE id = ?");
            $ustmt->bind_param("i", $assignment_id);
            $ustmt->execute();
            $ustmt->close();
        }

        // ── Strategy G: Embed questions directly (eliminates fetch_quiz_questions AJAX call) ──
        $embedded_questions = [];
        $qstmt = $conn->prepare("
            SELECT id, question_text, option_a, option_b, option_c, option_d, sort_order
            FROM questions
            WHERE test_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $qstmt->bind_param("i", $assignment['test_id']);
        $qstmt->execute();
        $qres = $qstmt->get_result();
        while ($row = $qres->fetch_assoc()) {
            $embedded_questions[] = $row;
        }
        $qstmt->close();

        // Fetch already-answered question IDs
        $answered_indices = [];
        $astmt = $conn->prepare("SELECT question_id FROM student_answers WHERE assigned_test_id = ?");
        $astmt->bind_param("i", $assignment_id);
        $astmt->execute();
        $ares = $astmt->get_result();
        $answered_qids = [];
        while ($r = $ares->fetch_assoc()) {
            $answered_qids[] = (int)$r['question_id'];
        }
        $astmt->close();

        // Map answered question IDs to indices
        foreach ($embedded_questions as $idx => $q) {
            if (in_array((int)$q['id'], $answered_qids)) {
                $answered_indices[] = $idx;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $test ? htmlspecialchars($test['title']) : 'Quiz' ?> — Next Academy</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Quiz Theme -->
    <link rel="stylesheet" href="quiz_assets/quiz.css">
</head>
<body class="quiz-body">

<div class="quiz-shell">
    <?php if ($error === 'completed'): ?>
        <!-- Already completed -->
        <div class="quiz-top-bar">
            <div class="quiz-logo"><i class="fas fa-graduation-cap"></i> Next Academy</div>
        </div>
        <div class="quiz-blocked">
            <div class="blocked-icon" style="background:rgba(74,222,128,0.12);">
                <i class="fas fa-check-circle" style="color:var(--neon-green);"></i>
            </div>
            <h2>Already Completed</h2>
            <p>You have already submitted this test. Each test can only be taken once.</p>
            <a href="quiz_result.php?assignment_id=<?= $assignment_id ?>" class="back-btn" style="margin-right:8px;">
                <i class="fas fa-chart-pie"></i> View Results
            </a>
            <a href="my_tests.php" class="back-btn"><i class="fas fa-arrow-left"></i> My Tests</a>
        </div>

    <?php elseif ($error): ?>
        <!-- Error -->
        <div class="quiz-top-bar">
            <div class="quiz-logo"><i class="fas fa-graduation-cap"></i> Next Academy</div>
        </div>
        <div class="quiz-blocked">
            <div class="blocked-icon"><i class="fas fa-lock"></i></div>
            <h2>Access Denied</h2>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="my_tests.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to My Tests</a>
        </div>

    <?php else: ?>
        <!-- Quiz UI -->
        <div class="quiz-top-bar">
            <div class="quiz-logo"><i class="fas fa-graduation-cap"></i> Next Academy</div>
            <div class="quiz-title-bar">
                <h2><?= htmlspecialchars($test['title']) ?></h2>
                <small><?= $question_count ?> Questions · <?= $test['time_per_question'] ?>s per question</small>
            </div>
            <a href="my_tests.php" class="quiz-exit-btn" id="exitBtn">
                <i class="fas fa-sign-out-alt"></i> Exit
            </a>
        </div>

        <!-- Main quiz container — JS renders here -->
        <div id="quizContainer">
            <div class="quiz-loading">
                <div class="quiz-spinner"></div>
                <p>Preparing your quiz…</p>
            </div>
        </div>

        <!-- Anti-cheat: Tab switch warning overlay -->
        <div class="quiz-warning-overlay" id="warningOverlay">
            <div class="quiz-warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Tab Switch Detected!</h3>
                <p>Leaving this tab during the quiz is not allowed. If you leave for more than 30 seconds, your test will be auto-submitted.</p>
                <button onclick="window._quizDismissWarning()">I Understand</button>
            </div>
        </div>

        <!-- Anti-cheat: DevTools detection warning overlay -->
        <div class="quiz-warning-overlay devtools-warning" id="devtoolsWarningOverlay">
            <div class="quiz-warning-box devtools-box">
                <div class="dt-icon-wrap">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>⚠️ Developer Tools Detected!</h3>
                <p>Close developer tools immediately or your test will be <strong>disqualified</strong>.</p>
                <div class="dt-countdown-wrap">
                    <div class="dt-countdown-ring">
                        <span id="devtoolsCountdown">15</span>
                    </div>
                    <div class="dt-countdown-label">seconds remaining</div>
                </div>
                <div class="dt-strikes">
                    <i class="fas fa-exclamation-circle"></i>
                    Warning <span class="dt-open-count">1</span> of <span class="dt-max-count">3</span> — exceeding 3 = instant disqualification
                </div>
            </div>
        </div>

        <!-- Quiz config for JS (includes embedded questions — Strategy G) -->
        <script>
        window.QUIZ_CONFIG = {
            assignmentId: <?= $assignment_id ?>,
            studentId: <?= $sid ?>,
            timePerQuestion: <?= intval($test['time_per_question']) ?>,
            totalQuestions: <?= $question_count ?>,
            resumeIndex: <?= intval($assignment['current_question_index']) ?>,
            questions: <?= json_encode($embedded_questions, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            answeredIndices: <?= json_encode($answered_indices) ?>
        };
        </script>
        <script src="quiz_assets/quiz.js"></script>

        <!-- Exit confirmation -->
        <script>
        document.getElementById('exitBtn').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to exit? Your progress is saved, but unanswered questions will be marked wrong if the timer runs out.')) {
                e.preventDefault();
            }
        });
        </script>
    <?php endif; ?>
</div>

</body>
</html>
