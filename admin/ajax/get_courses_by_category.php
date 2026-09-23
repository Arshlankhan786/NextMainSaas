<?php
// ============================================
// AJAX: Get Courses by Category
// Returns JSON list of courses for selected category
// ============================================
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// require_once '../config/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
// if (!isLoggedIn()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit();
// }

// Validate input
if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit();
}

$category_id = (int)$_GET['category_id'];

// Get courses for this category
$query = "SELECT id, name FROM courses WHERE category_id = ? AND status = 'Active' ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

$stmt->close();

// Return JSON response
echo json_encode([
    'success' => true,
    'courses' => $courses,
    'count' => count($courses)
]);
?>