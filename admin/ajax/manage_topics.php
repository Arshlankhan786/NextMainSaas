<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$student_id = (int)($_POST['student_id'] ?? 0);

if ($action === 'add_topic') {
    $topic_name = sanitize($_POST['topic_name']);
    $description = sanitize($_POST['description'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO course_topics (student_id, topic_name, description, status) VALUES (?, ?, ?, 'Pending')");
    $stmt->bind_param("iss", $student_id, $topic_name, $description);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Topic added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add topic']);
    }
    $stmt->close();
}

if ($action === 'toggle_topic') {
    $topic_id = (int)$_POST['topic_id'];
    $status = $_POST['status'];
    $completed_date = $status === 'Completed' ? date('Y-m-d') : null;
    
    $stmt = $conn->prepare("UPDATE course_topics SET status = ?, completed_date = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $completed_date, $topic_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    $stmt->close();
}

if ($action === 'delete_topic') {
    $topic_id = (int)$_POST['topic_id'];
    
    if ($conn->query("DELETE FROM course_topics WHERE id = $topic_id")) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

if ($action === 'get_topics') {
    $topics = $conn->query("SELECT * FROM course_topics WHERE student_id = $student_id ORDER BY created_at DESC");
    $data = [];
    while ($row = $topics->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'topics' => $data]);
}
?>