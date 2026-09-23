<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$admin_id = $_SESSION['admin_id'];

// Start topic
if ($action === 'start_topic') {
    $group_id = (int)$_POST['group_id'];
    $topic_id = (int)$_POST['topic_id'];

    // Check if record exists
    $check = $conn->query("SELECT id FROM group_topic_progress 
                           WHERE group_id = $group_id AND topic_id = $topic_id");

    if ($check->num_rows == 0) {
        // Insert if missing
        $conn->query("INSERT INTO group_topic_progress 
                      (group_id, topic_id, status, start_date)
                      VALUES ($group_id, $topic_id, 'active', CURDATE())");
    } else {
        // Update if exists
        $conn->query("UPDATE group_topic_progress 
                      SET status = 'active', start_date = CURDATE()
                      WHERE group_id = $group_id AND topic_id = $topic_id");
    }

    echo json_encode(['success' => true]);
    exit;
}


// Complete topic
// Complete topic
if ($action === 'complete_topic') {
    $group_id = (int)$_POST['group_id'];
    $topic_id = (int)$_POST['topic_id'];
    
    $today = date('Y-m-d');

    // 1️⃣ Complete current topic
    $conn->query("UPDATE group_topic_progress 
                  SET status = 'completed', 
                      end_date = '$today', 
                      completed_by = $admin_id 
                  WHERE group_id = $group_id 
                  AND topic_id = $topic_id");

    // 2️⃣ Get next topic (by order_index)
    $next = $conn->query("
        SELECT ct.id AS topic_id
        FROM course_topics ct
        WHERE ct.course_id = (
            SELECT course_id FROM student_groups WHERE id = $group_id
        )
        AND ct.id NOT IN (
            SELECT topic_id FROM group_topic_progress 
            WHERE group_id = $group_id AND status = 'completed'
        )
        ORDER BY ct.order_index ASC
        LIMIT 1
    ")->fetch_assoc();

    if ($next) {
        $next_topic_id = $next['topic_id'];

        // Check if record exists
        $check = $conn->query("SELECT id FROM group_topic_progress 
                               WHERE group_id = $group_id 
                               AND topic_id = $next_topic_id");

        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO group_topic_progress 
                          (group_id, topic_id, status, start_date)
                          VALUES ($group_id, $next_topic_id, 'active', '$today')");
        } else {
            $conn->query("UPDATE group_topic_progress 
                          SET status = 'active', start_date = '$today'
                          WHERE group_id = $group_id 
                          AND topic_id = $next_topic_id");
        }
    }

    echo json_encode(['success' => true]);
    exit;
}


echo json_encode(['success' => false, 'message' => 'Invalid action']);