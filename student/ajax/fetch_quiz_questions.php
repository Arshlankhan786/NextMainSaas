<?php
/**
 * AJAX: Fetch quiz questions for a given assignment
 * Returns questions WITHOUT correct_option (anti-cheat)
 */

// Catch ALL PHP errors and return as JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});
set_exception_handler(function ($e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Exception: " . $e->getMessage()]);
    exit;
});

require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';

header('Content-Type: application/json');

// Check if student is logged in (without redirect for AJAX)
if (!isStudentLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in. Session may have expired.']);
    exit;
}

$student = getCurrentStudent();
$sid = (int) $student['id'];
$assignment_id = intval($_GET['assignment_id'] ?? 0);

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment ID']);
    exit;
}

// Check DB connection
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . ($conn ? $conn->connect_error : 'null')]);
    exit;
}

// Verify assignment belongs to this student and is not completed
$stmt = $conn->prepare("
    SELECT at.*, t.time_per_question
    FROM assigned_tests at
    JOIN tests t ON t.id = at.test_id
    WHERE at.id = ? AND at.student_id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $assignment_id, $sid);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    echo json_encode(['success' => false, 'error' => 'This quiz is not assigned to you. (student_id=' . $sid . ', assignment_id=' . $assignment_id . ')']);
    exit;
}

if ($assignment['status'] === 'Completed') {
    echo json_encode(['success' => false, 'error' => 'This quiz has already been completed.']);
    exit;
}

// Fetch questions (WITHOUT correct_option)
$qstmt = $conn->prepare("
    SELECT id, question_text, option_a, option_b, option_c, option_d, sort_order
    FROM questions
    WHERE test_id = ?
    ORDER BY sort_order ASC, id ASC
");

if (!$qstmt) {
    echo json_encode(['success' => false, 'error' => 'Questions query failed: ' . $conn->error]);
    exit;
}

$qstmt->bind_param("i", $assignment['test_id']);
$qstmt->execute();
$qres = $qstmt->get_result();
$questions = [];
while ($row = $qres->fetch_assoc()) {
    $questions[] = $row;
}
$qstmt->close();

// Fetch already-answered question indices
$answered_indices = [];
$astmt = $conn->prepare("
    SELECT sa.question_id FROM student_answers sa
    WHERE sa.assigned_test_id = ?
");

if ($astmt) {
    $astmt->bind_param("i", $assignment_id);
    $astmt->execute();
    $ares = $astmt->get_result();
    $answered_qids = [];
    while ($r = $ares->fetch_assoc()) {
        $answered_qids[] = (int) $r['question_id'];
    }
    $astmt->close();

    // Map answered question IDs to indices
    foreach ($questions as $idx => $q) {
        if (in_array((int) $q['id'], $answered_qids)) {
            $answered_indices[] = $idx;
        }
    }
}

echo json_encode([
    'success' => true,
    'questions' => $questions,
    'answered_indices' => $answered_indices,
    'current_index' => (int) $assignment['current_question_index'],
    'time_per_question' => (int) $assignment['time_per_question']
]);
?>