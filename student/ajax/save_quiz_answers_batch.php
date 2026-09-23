<?php
/**
 * AJAX: Save multiple quiz answers in a single request (batch endpoint)
 * 
 * Accepts a JSON array of answers and processes them in one DB connection.
 * This dramatically reduces connection count during a quiz session.
 * 
 * Expected POST body (JSON):
 * {
 *   "assignment_id": 123,
 *   "answers": [
 *     { "question_id": 1, "selected_option": "A", "time_taken": 12 },
 *     { "question_id": 2, "selected_option": null, "time_taken": 25 },
 *     ...
 *   ],
 *   "current_index": 10
 * }
 * 
 * Also accepts FormData via sendBeacon (fallback).
 */
require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';

// For sendBeacon, the data arrives as FormData or plain text
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = (strpos($contentType, 'application/json') !== false);

if ($isJson) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }
} else {
    // FormData from sendBeacon fallback
    $input = [
        'assignment_id' => intval($_POST['assignment_id'] ?? 0),
        'current_index' => intval($_POST['current_index'] ?? 0),
        'answers' => []
    ];
    // sendBeacon may also send answers as JSON string in a field
    if (!empty($_POST['answers'])) {
        $input['answers'] = json_decode($_POST['answers'], true) ?: [];
    }
}

header('Content-Type: application/json');

// Check login (without redirect for AJAX)
if (!isStudentLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$student = getCurrentStudent();
$sid = (int)$student['id'];
$assignment_id = intval($input['assignment_id'] ?? 0);
$current_index = intval($input['current_index'] ?? 0);
$answers = $input['answers'] ?? [];

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment']);
    exit;
}

// Verify assignment ownership and status (ONE query for the whole batch)
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

$test_id = $assignment['test_id'];

// Update current_question_index
if ($current_index > 0) {
    $ustmt = $conn->prepare("UPDATE assigned_tests SET current_question_index = ? WHERE id = ?");
    $ustmt->bind_param("ii", $current_index, $assignment_id);
    $ustmt->execute();
    $ustmt->close();
}

// If no answers to save (just an index sync), we're done
if (empty($answers)) {
    echo json_encode(['success' => true, 'saved' => 'index_only', 'count' => 0]);
    exit;
}

// Pre-fetch all correct options for this test in ONE query
$correct_map = [];
$cstmt = $conn->prepare("SELECT id, correct_option FROM questions WHERE test_id = ?");
$cstmt->bind_param("i", $test_id);
$cstmt->execute();
$cres = $cstmt->get_result();
while ($row = $cres->fetch_assoc()) {
    $correct_map[(int)$row['id']] = strtoupper($row['correct_option']);
}
$cstmt->close();

// Prepare the upsert statement ONCE, execute for each answer
$astmt = $conn->prepare("
    INSERT INTO student_answers (assigned_test_id, question_id, selected_option, is_correct, time_taken_seconds)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        selected_option = VALUES(selected_option),
        is_correct = VALUES(is_correct),
        time_taken_seconds = VALUES(time_taken_seconds),
        answered_at = NOW()
");

$saved_count = 0;

foreach ($answers as $answer) {
    $question_id = intval($answer['question_id'] ?? 0);
    if ($question_id <= 0) continue;
    
    $selected_option = $answer['selected_option'] ?? null;
    $time_taken = intval($answer['time_taken'] ?? 0);
    
    // Validate option
    if ($selected_option !== null) {
        $selected_option = strtoupper($selected_option);
        if (!in_array($selected_option, ['A', 'B', 'C', 'D'])) {
            $selected_option = null;
        }
    }
    
    // Check correctness using pre-fetched map (NO extra query)
    $is_correct = 0;
    if ($selected_option && isset($correct_map[$question_id])) {
        if ($correct_map[$question_id] === $selected_option) {
            $is_correct = 1;
        }
    }
    
    $astmt->bind_param("iisii", $assignment_id, $question_id, $selected_option, $is_correct, $time_taken);
    $astmt->execute();
    $saved_count++;
}

$astmt->close();

echo json_encode(['success' => true, 'count' => $saved_count]);
?>
