<?php
/**
 * AJAX: Submit (finalize) a quiz
 * Marks assignment completed, calculates score, inserts test_results
 * ── RANKING INTEGRATION: Calculates & stores points_awarded
 * ── ANTI-CHEAT: Handles disqualification flag from frontend
 * ── OPTIMIZED: Accepts unsaved answers in same POST, combined stats query
 */
require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';
requireStudentLogin();

header('Content-Type: application/json');

// Accept both form-urlencoded and JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $postData = json_decode($raw, true) ?: [];
} else {
    $postData = $_POST;
    // If answers came as JSON string in a form field
    if (isset($postData['answers']) && is_string($postData['answers'])) {
        $postData['answers'] = json_decode($postData['answers'], true) ?: [];
    }
}

$student = getCurrentStudent();
$sid = (int)$student['id'];
$assignment_id = intval($postData['assignment_id'] ?? 0);
$is_disqualified = intval($postData['is_disqualified'] ?? 0) ? 1 : 0;
$unsaved_answers = $postData['answers'] ?? [];

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment']);
    exit;
}

// Query 1: Verify ownership
$stmt = $conn->prepare("SELECT id, test_id, status, started_at FROM assigned_tests WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $assignment_id, $sid);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($assignment['status'] === 'Completed') {
    // Already completed — return existing result (single-attempt enforcement)
    $rstmt = $conn->prepare("SELECT * FROM test_results WHERE assigned_test_id = ?");
    $rstmt->bind_param("i", $assignment_id);
    $rstmt->execute();
    $existing = $rstmt->get_result()->fetch_assoc();
    $rstmt->close();

    echo json_encode([
        'success' => true,
        'already_completed' => true,
        'score_percentage' => floatval($existing['score_percentage'] ?? 0),
        'correct_count' => intval($existing['correct_count'] ?? 0),
        'total_questions' => intval($existing['total_questions'] ?? 0),
        'points_awarded' => intval($existing['points_awarded'] ?? 0),
        'is_disqualified' => intval($existing['is_disqualified'] ?? 0)
    ]);
    exit;
}

$test_id = $assignment['test_id'];

// ── Save any unsaved answers included in the submit request ──────
if (!empty($unsaved_answers) && is_array($unsaved_answers)) {
    // Pre-fetch correct options for correctness check
    $correct_map = [];
    $cstmt = $conn->prepare("SELECT id, correct_option FROM questions WHERE test_id = ?");
    $cstmt->bind_param("i", $test_id);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    while ($row = $cres->fetch_assoc()) {
        $correct_map[(int)$row['id']] = strtoupper($row['correct_option']);
    }
    $cstmt->close();

    $astmt = $conn->prepare("
        INSERT INTO student_answers (assigned_test_id, question_id, selected_option, is_correct, time_taken_seconds)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            selected_option = VALUES(selected_option),
            is_correct = VALUES(is_correct),
            time_taken_seconds = VALUES(time_taken_seconds),
            answered_at = NOW()
    ");

    foreach ($unsaved_answers as $answer) {
        $question_id = intval($answer['question_id'] ?? 0);
        if ($question_id <= 0) continue;

        $selected_option = $answer['selected_option'] ?? null;
        $time_taken = intval($answer['time_taken'] ?? 0);

        if ($selected_option !== null) {
            $selected_option = strtoupper($selected_option);
            if (!in_array($selected_option, ['A', 'B', 'C', 'D'])) {
                $selected_option = null;
            }
        }

        $is_correct = 0;
        if ($selected_option && isset($correct_map[$question_id])) {
            if ($correct_map[$question_id] === $selected_option) {
                $is_correct = 1;
            }
        }

        $astmt->bind_param("iisii", $assignment_id, $question_id, $selected_option, $is_correct, $time_taken);
        $astmt->execute();
    }
    $astmt->close();
}

// Query 2: Combined stats — total questions, correct answers, total time (was 3 separate queries)
$stats_stmt = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM questions WHERE test_id = ?) AS total_questions,
        (SELECT COUNT(*) FROM student_answers WHERE assigned_test_id = ? AND is_correct = 1) AS correct_count,
        (SELECT COALESCE(SUM(time_taken_seconds), 0) FROM student_answers WHERE assigned_test_id = ?) AS total_time
");
$stats_stmt->bind_param("iii", $test_id, $assignment_id, $assignment_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$total = (int)$stats['total_questions'];
$correct = (int)$stats['correct_count'];
$total_time = (int)$stats['total_time'];

// ── Calculate percentage ────────────────────────────────────────
$percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

// ── ANTI-CHEAT: Override if disqualified ────────────────────────
if ($is_disqualified) {
    $percentage = 0;
    $correct = 0;
}

// ── RANKING: Calculate points based on score percentage ─────────
// Points are awarded ONCE per test (enforced by UNIQUE KEY + status check above)
//
// ── TEMPORARILY DISABLED (2026-07-08): Auto ranking points disabled per admin request.
//    Students still complete tests and get scores. Only auto point-awarding is stopped.
//    To re-enable, uncomment the function call below and remove the $points_awarded = 0 line.
//
// function calculateQuizPoints($pct, $disqualified) {
//     if ($disqualified) return -1;
//
//     if ($pct >= 90) return 10;
//     if ($pct >= 70) return 8;
//     if ($pct >= 60) return 6;
//     if ($pct >= 50) return 5;
//     if ($pct >= 40) return 4;
//     if ($pct >= 30) return 3;
//     if ($pct >= 10) return 0;
//     return -1; // Below 10%
// }
//
// $points_awarded = calculateQuizPoints($percentage, $is_disqualified);
$points_awarded = 0; // Auto ranking points temporarily disabled

// Query 3: Mark assignment as completed
$ustmt = $conn->prepare("UPDATE assigned_tests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
$ustmt->bind_param("i", $assignment_id);
$ustmt->execute();
$ustmt->close();

// Query 4: Insert test result with points (atomic upsert)
$istmt = $conn->prepare("
    INSERT INTO test_results (test_id, student_id, assigned_test_id, score_percentage, correct_count, total_questions, total_time_seconds, is_disqualified, points_awarded)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        score_percentage = VALUES(score_percentage),
        correct_count = VALUES(correct_count),
        total_questions = VALUES(total_questions),
        total_time_seconds = VALUES(total_time_seconds),
        is_disqualified = VALUES(is_disqualified),
        points_awarded = VALUES(points_awarded),
        completed_at = NOW()
");
$istmt->bind_param("iiidiiiii", $test_id, $sid, $assignment_id, $percentage, $correct, $total, $total_time, $is_disqualified, $points_awarded);
$istmt->execute();
$istmt->close();

echo json_encode([
    'success' => true,
    'score_percentage' => $percentage,
    'correct_count' => $correct,
    'total_questions' => $total,
    'total_time_seconds' => $total_time,
    'points_awarded' => $points_awarded,
    'is_disqualified' => $is_disqualified
]);
?>

