<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_REQUEST['action'] ?? '';
$admin_id = $_SESSION['admin_id'];

// Add manual points
if ($action === 'add_points') {
    $student_id = (int)$_POST['student_id'];
    $points = (int)$_POST['points'];
    $reason = trim($_POST['reason']);
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Reason is required']);
        exit();
    }
    
    $stmt = $conn->prepare("
        INSERT INTO student_manual_points (student_id, points, reason, awarded_by) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisi", $student_id, $points, $reason, $admin_id);
    
    if ($stmt->execute()) {
        // Get student name for notification
        $student = $conn->query("SELECT full_name FROM students WHERE id = $student_id")->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Points added successfully!',
            'student' => $student['full_name'],
            'points' => $points
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add points']);
    }
    $stmt->close();
    exit();
}

// Get manual points history
if ($action === 'get_history') {
    $student_id = (int)$_REQUEST['student_id'];
    
    $history = $conn->query("
        SELECT 
            smp.*,
            a.full_name as admin_name
        FROM student_manual_points smp
        JOIN admins a ON smp.awarded_by = a.id
        WHERE smp.student_id = $student_id
        ORDER BY smp.created_at DESC
    ");
    
    $records = [];
    while ($row = $history->fetch_assoc()) {
        $records[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'history' => $records
    ]);
    exit();
}

// Delete manual point entry
if ($action === 'delete_point') {
    $point_id = (int)$_POST['point_id'];
    
    // Only super admin can delete
    if (!isSuperAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Only Super Admin can delete points']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM student_manual_points WHERE id = ?");
    $stmt->bind_param("i", $point_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Point entry deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
    $stmt->close();
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>