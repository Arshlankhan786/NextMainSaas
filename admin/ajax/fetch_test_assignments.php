<?php
/**
 * AJAX: Fetch assignments for a given test_id (used by polling UI)
 */
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$test_id = intval($_GET['test_id'] ?? 0);
if ($test_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid test ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT at.id, at.student_id, at.status, at.started_at, at.completed_at,
           s.full_name, s.student_code,
           tr.score_percentage, tr.correct_count, tr.total_questions
    FROM assigned_tests at
    JOIN students s ON s.id = at.student_id
    LEFT JOIN test_results tr ON tr.assigned_test_id = at.id
    WHERE at.test_id = ?
    ORDER BY at.status ASC, s.full_name ASC
");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$res = $stmt->get_result();

$assignments = [];
while ($r = $res->fetch_assoc()) {
    $assignments[] = $r;
}
$stmt->close();

echo json_encode(['success' => true, 'assignments' => $assignments]);
?>
