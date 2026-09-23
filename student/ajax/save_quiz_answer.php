<?php
/**
 * AJAX: Save a single quiz answer
 * Also updates current_question_index for resume support
 */
require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';
requireStudentLogin();

header('Content-Type: application/json');

$student = getCurrentStudent();
$sid = (int)$student['id'];

$assignment_id   = intval($_POST['assignment_id'] ?? 0);
$question_id     = intval($_POST['question_id'] ?? 0);
$selected_option = $_POST['selected_option'] ?? null;
$time_taken      = intval($_POST['time_taken'] ?? 0);
$current_index   = intval($_POST['current_index'] ?? 0);

// Validate option
if ($selected_option !== null) {
    $selected_option = strtoupper($selected_option);
    if (!in_array($selected_option, ['A', 'B', 'C', 'D'])) {
        $selected_option = null;
    }
}

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment']);
    exit;
}

// Verify assignment ownership and status
$stmt = $conn->prepare("SELECT id, test_id, status FROM assigned_tests WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $assignment_id, $sid);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($assignment['status'] === 'Completed') {
    echo json_encode(['success' => false, 'error' => 'Quiz already completed']);
    exit;
}

// Update current_question_index
$ustmt = $conn->prepare("UPDATE assigned_tests SET current_question_index = ? WHERE id = ?");
$ustmt->bind_param("ii", $current_index, $assignment_id);
$ustmt->execute();
$ustmt->close();

// If this is just a beacon state-sync (no question_id), we're done
if ($question_id <= 0) {
    echo json_encode(['success' => true, 'saved' => 'index_only']);
    exit;
}

// Check correct answer
$is_correct = 0;
if ($selected_option) {
    $cstmt = $conn->prepare("SELECT correct_option FROM questions WHERE id = ? AND test_id = ?");
    $cstmt->bind_param("ii", $question_id, $assignment['test_id']);
    $cstmt->execute();
    $q = $cstmt->get_result()->fetch_assoc();
    $cstmt->close();
    if ($q && strtoupper($q['correct_option']) === $selected_option) {
        $is_correct = 1;
    }
}

// Insert or update answer
$astmt = $conn->prepare("
    INSERT INTO student_answers (assigned_test_id, question_id, selected_option, is_correct, time_taken_seconds)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        selected_option = VALUES(selected_option),
        is_correct = VALUES(is_correct),
        time_taken_seconds = VALUES(time_taken_seconds),
        answered_at = NOW()
");
$astmt->bind_param("iisii", $assignment_id, $question_id, $selected_option, $is_correct, $time_taken);
$astmt->execute();
$astmt->close();

echo json_encode(['success' => true, 'is_correct' => $is_correct]);
?>
