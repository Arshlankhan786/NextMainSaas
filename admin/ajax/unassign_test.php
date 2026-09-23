<?php
/**
 * AJAX: Unassign a test from a student (only if status = 'Not Started')
 */
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$assignment_id = intval($_POST['assignment_id'] ?? 0);
if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment ID']);
    exit;
}

// Only allow removal if status is "Not Started"
$stmt = $conn->prepare("SELECT id, status FROM assigned_tests WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Assignment not found']);
    exit;
}

if ($row['status'] !== 'Not Started') {
    echo json_encode(['success' => false, 'error' => 'Cannot remove: test is already ' . $row['status']]);
    exit;
}

$del = $conn->prepare("DELETE FROM assigned_tests WHERE id = ?");
$del->bind_param("i", $assignment_id);
if ($del->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}
$del->close();
?>
