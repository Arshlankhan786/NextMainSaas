<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';


header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit();
}

$id = (int)$_GET['id'];

$course = getSingleResult("SELECT * FROM courses WHERE id = ?", "i", [$id]);
$fees = getResults("SELECT duration_months, fee_amount FROM course_fees WHERE course_id = ? ORDER BY duration_months", "i", [$id]);

echo json_encode([
    'success' => true,
    'course' => $course,
    'fees' => $fees
]);
?>