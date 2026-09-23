<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Get topics for a course
if ($action === 'get_topics') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    
    $topics = $conn->query("
        SELECT ct.*, 
               (SELECT COUNT(*) FROM course_sub_topics WHERE topic_id = ct.id) as sub_count
        FROM course_topics ct
        WHERE ct.course_id = $course_id
        ORDER BY ct.order_index ASC
    ");
    
    $data = [];
    while ($row = $topics->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'topics' => $data]);
    exit;
}

// Get sub-topics for a topic
if ($action === 'get_subtopics') {
    $topic_id = (int)($_GET['topic_id'] ?? 0);
    
    $subtopics = $conn->query("
        SELECT * FROM course_sub_topics
        WHERE topic_id = $topic_id
        ORDER BY order_index ASC
    ");
    
    $data = [];
    while ($row = $subtopics->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['success' => true, 'subtopics' => $data]);
    exit;
}

// Add topic
if ($action === 'add_topic') {
    $course_id = (int)$_POST['course_id'];
    $topic_name = sanitize($_POST['topic_name']);
    
    // Get max order
    $max_order = $conn->query("SELECT MAX(order_index) as max_order FROM course_topics WHERE course_id = $course_id")->fetch_assoc()['max_order'] ?? 0;
    $order_index = $max_order + 1;
    
    $stmt = $conn->prepare("INSERT INTO course_topics (course_id, topic_name, order_index) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $course_id, $topic_name, $order_index);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Topic added']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add topic']);
    }
    $stmt->close();
    exit;
}

// Update topic
if ($action === 'update_topic') {
    $topic_id = (int)$_POST['topic_id'];
    $topic_name = sanitize($_POST['topic_name']);
    
    $stmt = $conn->prepare("UPDATE course_topics SET topic_name = ? WHERE id = ?");
    $stmt->bind_param("si", $topic_name, $topic_id);
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
    exit;
}

// Delete topic
if ($action === 'delete_topic') {
    $topic_id = (int)$_POST['topic_id'];
    
    // Check if topic is used in any group progress
    $used = $conn->query("SELECT COUNT(*) as cnt FROM group_topic_progress WHERE topic_id = $topic_id")->fetch_assoc()['cnt'];
    
    if ($used > 0) {
        echo json_encode(['success' => false, 'message' => 'Topic is assigned to groups and cannot be deleted']);
        exit;
    }
    
    $conn->query("DELETE FROM course_topics WHERE id = $topic_id");
    echo json_encode(['success' => true]);
    exit;
}

// Reorder topics
if ($action === 'reorder_topics') {
    $orders = json_decode($_POST['orders'], true);
    
    foreach ($orders as $id => $order) {
        $conn->query("UPDATE course_topics SET order_index = $order WHERE id = $id");
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// Add sub-topic
if ($action === 'add_subtopic') {
    $topic_id = (int)$_POST['topic_id'];
    $sub_topic_name = sanitize($_POST['sub_topic_name']);
    
    $max_order = $conn->query("SELECT MAX(order_index) as max_order FROM course_sub_topics WHERE topic_id = $topic_id")->fetch_assoc()['max_order'] ?? 0;
    $order_index = $max_order + 1;
    
    $stmt = $conn->prepare("INSERT INTO course_sub_topics (topic_id, sub_topic_name, order_index) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $topic_id, $sub_topic_name, $order_index);
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
    exit;
}

// Update sub-topic
if ($action === 'update_subtopic') {
    $subtopic_id = (int)$_POST['subtopic_id'];
    $sub_topic_name = sanitize($_POST['sub_topic_name']);
    
    $stmt = $conn->prepare("UPDATE course_sub_topics SET sub_topic_name = ? WHERE id = ?");
    $stmt->bind_param("si", $sub_topic_name, $subtopic_id);
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
    exit;
}

// Delete sub-topic
if ($action === 'delete_subtopic') {
    $subtopic_id = (int)$_POST['subtopic_id'];
    $conn->query("DELETE FROM course_sub_topics WHERE id = $subtopic_id");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);