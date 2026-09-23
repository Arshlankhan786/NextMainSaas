<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$group_id = (int)($_POST['group_id'] ?? 0);
$student_id = (int)($_POST['student_id'] ?? 0);

if (!$group_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

if ($action === 'add') {

    $exists = $conn->query("
        SELECT id FROM student_group_members
        WHERE group_id = $group_id AND student_id = $student_id
    ");

    if ($exists->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student already in group']);
        exit;
    }

    $conn->query("
        INSERT INTO student_group_members (group_id, student_id)
        VALUES ($group_id, $student_id)
    ");

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove') {

    $conn->query("
        DELETE FROM student_group_members
        WHERE group_id = $group_id AND student_id = $student_id
    ");

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
