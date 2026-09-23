<?php
// REUSE existing connection
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    echo json_encode(['success' => false, 'fees' => []]);
    exit;
}

$course_id = (int)$_GET['course_id'];

$stmt = $conn->prepare("
    SELECT duration_months, fee_amount
    FROM course_fees
    WHERE course_id = ?
    ORDER BY duration_months
");
$stmt->bind_param("i", $course_id);
$stmt->execute();

$result = $stmt->get_result();
$fees = [];

while ($row = $result->fetch_assoc()) {
    $fees[] = $row;
}

$stmt->close();
// $conn will close automatically at script end

echo json_encode([
    'success' => true,
    'fees' => $fees
]);
exit;