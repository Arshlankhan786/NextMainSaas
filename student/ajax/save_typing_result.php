<?php
/**
 * AJAX: Save Typing Competition Result
 * Endpoint: student/ajax/save_typing_result.php
 * Follows same pattern as fetch_quiz_questions.php
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

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Check if student is logged in (without redirect for AJAX)
if (!isStudentLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in. Session may have expired.']);
    exit;
}

$student = getCurrentStudent();
$sid = (int)$student['id'];

// Check DB connection
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Validate inputs
$wpm        = isset($_POST['wpm'])        ? intval($_POST['wpm'])       : 0;
$accuracy   = isset($_POST['accuracy'])   ? floatval($_POST['accuracy']): 0;
$errors     = isset($_POST['errors'])     ? intval($_POST['errors'])    : 0;
$time_taken = isset($_POST['time_taken']) ? intval($_POST['time_taken']): 0;
$test_text  = isset($_POST['test_text'])  ? trim($_POST['test_text'])   : '';
$typed_text = isset($_POST['typed_text']) ? trim($_POST['typed_text'])  : '';

// Basic validation
if ($wpm < 0) $wpm = 0;
if ($accuracy < 0) $accuracy = 0;
if ($accuracy > 100) $accuracy = 100;
if ($errors < 0) $errors = 0;
if ($time_taken <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid time taken.']);
    exit;
}
if (empty($test_text)) {
    echo json_encode(['success' => false, 'error' => 'Missing test text.']);
    exit;
}

// Ensure table exists
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

// Insert result with prepared statement
$stmt = $conn->prepare("
    INSERT INTO typing_results (student_id, test_text, typed_text, wpm, accuracy, errors, time_taken)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("issidii", $sid, $test_text, $typed_text, $wpm, $accuracy, $errors, $time_taken);

if ($stmt->execute()) {
    $inserted_id = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'id' => $inserted_id]);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $err]);
}
?>
