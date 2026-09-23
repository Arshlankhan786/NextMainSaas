<?php
/**
 * STUDENT — Typing Competition (Immersive Full-Page Experience)
 * Standalone page — does NOT use student includes/header.php
 * Matches the take_quiz.php pattern
 */
require_once __DIR__ . '/../admin/config/database.php';
require_once __DIR__ . '/student_auth.php';
requireStudentLogin();

$student = getCurrentStudent();
$sid = (int)$student['id'];

// ── Auto-create typing_results table if not exists ──
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

// ── Fetch student's best WPM for motivation display ──
$bestWpm = 0;
$totalTests = 0;
$bstmt = $conn->prepare("SELECT MAX(wpm) as best_wpm, COUNT(*) as total FROM typing_results WHERE student_id = ?");
$bstmt->bind_param("i", $sid);
$bstmt->execute();
$brow = $bstmt->get_result()->fetch_assoc();
$bestWpm = (int)($brow['best_wpm'] ?? 0);
$totalTests = (int)($brow['total'] ?? 0);
$bstmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Typing Competition — Next Academy</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Typing Theme -->
    <link rel="stylesheet" href="typing_assets/typing.css">
</head>
<body class="typing-body">

<div class="typing-shell">

    <!-- ── Top Bar ── -->
    <div class="typing-top-bar">
        <a href="dashboard.php" class="typing-logo">
            <i class="fas fa-graduation-cap"></i> Next Academy
        </a>
        <div class="typing-title-area">
            <h2><i class="fas fa-keyboard"></i> Typing Competition</h2>
        </div>
        <a href="dashboard.php" class="typing-exit-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <!-- ── Controls Row ── -->
    <div class="typing-controls">
        <div class="duration-wrapper">
            <select id="durationSelect" class="duration-select">
                <option value="30">30 Seconds</option>
                <option value="60" selected>1 Minute</option>
                <option value="120">2 Minutes</option>
                <option value="180">3 Minutes</option>
                <option value="300">5 Minutes</option>
                <option value="600">10 Minutes</option>
            </select>
        </div>

        <?php if ($totalTests > 0): ?>
        <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--type-muted);font-weight:600;">
            <i class="fas fa-trophy" style="color:var(--neon-yellow);"></i>
            Best: <span style="color:var(--neon-green);font-weight:800;"><?= $bestWpm ?> WPM</span>
            <span style="margin-left:6px;">·</span>
            <span style="margin-left:6px;"><i class="fas fa-chart-bar" style="color:var(--neon-purple);margin-right:3px;"></i><?= $totalTests ?> test<?= $totalTests > 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Stats Bar ── -->
    <div class="typing-stats-bar">
        <div class="stat-card timer-card">
            <div class="stat-label"><i class="fas fa-clock"></i> Time</div>
            <div class="stat-value timer-val" id="timerValue">1:00</div>
        </div>
        <div class="stat-card wpm-card">
            <div class="stat-label"><i class="fas fa-bolt"></i> WPM</div>
            <div class="stat-value wpm-val" id="wpmValue">0</div>
        </div>
        <div class="stat-card accuracy-card">
            <div class="stat-label"><i class="fas fa-bullseye"></i> Accuracy</div>
            <div class="stat-value accuracy-val" id="accuracyValue">100<span class="stat-unit">%</span></div>
        </div>
    </div>

    <!-- ── Typing Card ── -->
    <div class="typing-card">
        <!-- Focus hint overlay -->
        <div class="typing-focus-hint" id="focusHint">
            <div class="focus-hint-content">
                <i class="fas fa-keyboard"></i>
                <span>Click here or start typing to begin</span>
            </div>
        </div>

        <!-- Paragraph display -->
        <div class="typing-text-display" id="textDisplay"></div>

        <!-- Hidden input for capturing keystrokes -->
        <input type="text" class="typing-hidden-input" id="hiddenInput"
               autocomplete="off" autocorrect="off" autocapitalize="off"
               spellcheck="false" aria-label="Type here">

        <!-- Progress bar -->
        <div class="typing-progress-wrap">
            <div class="typing-progress-bar">
                <div class="typing-progress-fill" id="progressFill"></div>
            </div>
        </div>

        <!-- Errors count -->
        <div class="typing-errors-bar">
            <div class="errors-label">
                <i class="fas fa-exclamation-triangle"></i> Errors
            </div>
            <div class="errors-count" id="errorsCount">0</div>
        </div>
    </div>

</div><!-- /typing-shell -->

<!-- ═══════════════════════════════════════
     RESULT OVERLAY
═══════════════════════════════════════ -->
<div class="typing-result-overlay" id="resultOverlay">
    <div class="result-container">
        <!-- Header -->
        <div class="result-header">
            <div class="result-icon">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <h2>Test Complete!</h2>
            <p>Here's how you performed</p>
        </div>

        <!-- Status message -->
        <div class="typing-status" id="resultStatus" style="display:none;"></div>

        <!-- Stats Grid -->
        <div class="result-stats-grid">
            <div class="result-stat-card">
                <div class="result-stat-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="result-stat-value" id="resultWpm">0</div>
                <div class="result-stat-label">Words / Min</div>
            </div>
            <div class="result-stat-card">
                <div class="result-stat-icon"><i class="fas fa-bullseye"></i></div>
                <div class="result-stat-value" id="resultAccuracy">0%</div>
                <div class="result-stat-label">Accuracy</div>
            </div>
            <div class="result-stat-card">
                <div class="result-stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="result-stat-value" id="resultErrors">0</div>
                <div class="result-stat-label">Errors</div>
            </div>
            <div class="result-stat-card">
                <div class="result-stat-icon"><i class="fas fa-stopwatch"></i></div>
                <div class="result-stat-value" id="resultTime">0s</div>
                <div class="result-stat-label">Time Taken</div>
            </div>
        </div>

        <!-- Text Comparison -->
        <div class="result-text-comparison">
            <h4><i class="fas fa-file-alt"></i> Text Comparison</h4>
            <div class="result-text-content" id="resultTextDiff"></div>
        </div>

        <!-- Actions -->
        <div class="result-actions">
            <button class="result-btn result-btn-primary" id="retryBtn">
                <i class="fas fa-redo"></i> Try Again
            </button>
            <a href="dashboard.php" class="result-btn result-btn-secondary" id="backBtn">
                <i class="fas fa-arrow-left"></i> Back to Portal
            </a>
        </div>
    </div>
</div>

<!-- Config for JS -->
<script>
window.TYPING_CONFIG = {
    studentId: <?= $sid ?>
};
</script>
<script src="typing_assets/typing.js"></script>

</body>
</html>
