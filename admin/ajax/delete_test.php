<?php
/**
 * AJAX: Delete a test (cascades to questions, assignments, answers, results)
 */
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$test_id = intval($_POST['test_id'] ?? 0);
if ($test_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid test ID']);
    exit;
}

// Verify test exists
$stmt = $conn->prepare("SELECT id FROM tests WHERE id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Test not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Delete (CASCADE handles questions, assigned_tests, student_answers, test_results)
$del = $conn->prepare("DELETE FROM tests WHERE id = ?");
$del->bind_param("i", $test_id);
if ($del->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $del->error]);
}
$del->close();
?>
