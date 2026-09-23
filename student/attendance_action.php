<?php
/**
 * attendance_action.php — AJAX handler (IST timezone, Sunday-aware)
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// India timezone
date_default_timezone_set('Asia/Kolkata');

require_once '../admin/config/database.php';
require_once './student_auth.php';
requireStudentLogin();

ob_end_clean();
header('Content-Type: application/json');

$student  = getCurrentStudent();
$sid      = (int)$student['id'];
$action   = $_POST['action'] ?? '';
$today    = date('Y-m-d');
$now_time = date('H:i:s');
$is_sunday = (date('N') == 7);

function getTodayRow($conn, $sid, $today) {
    $r = $conn->query(
        "SELECT * FROM student_attendance
         WHERE student_id = $sid AND attendance_date = '$today' LIMIT 1");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}

if ($action === 'check_in') {
    if ($is_sunday) {
        echo json_encode(['success' => false, 'message' => 'Sunday is a holiday — attendance not counted.']);
        exit();
    }
    $row = getTodayRow($conn, $sid, $today);
    if ($row && $row['status'] === 'Holiday') {
        echo json_encode(['success' => false, 'message' => 'Today is a declared Holiday — attendance is not required.']);
        exit();
    }
    if ($row) {
        echo json_encode(['success' => false, 'message' => 'Already checked in today.']);
        exit();
    }
    $ok = $conn->query(
        "INSERT INTO student_attendance
             (student_id, attendance_date, status, check_in_time, created_at)
         VALUES ($sid, '$today', 'Present', '$now_time', NOW())"
    );
    if ($ok) {
        echo json_encode([
            'success'       => true,
            'message'       => 'Checked in at ' . date('h:i A'),
            'check_in_time' => date('h:i A'),
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit();
}

if ($action === 'check_out') {
    if ($is_sunday) {
        echo json_encode(['success' => false, 'message' => 'Sunday is a holiday.']);
        exit();
    }
    $row = getTodayRow($conn, $sid, $today);
    if ($row && $row['status'] === 'Holiday') {
        echo json_encode(['success' => false, 'message' => 'Today is a declared Holiday — no check-out needed.']);
        exit();
    }
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Check in first.']);
        exit();
    }
    if (!empty($row['check_out_time'])) {
        echo json_encode(['success' => false, 'message' => 'Already checked out today.']);
        exit();
    }
    $in_ts  = strtotime($row['check_in_time']);
    $out_ts = strtotime($now_time);
    $total_hours = round(($out_ts - $in_ts) / 3600, 2);
    if ($total_hours < 0) $total_hours = 0;
    $ok = $conn->query(
        "UPDATE student_attendance
         SET check_out_time = '$now_time', total_hours = $total_hours
         WHERE student_id = $sid AND attendance_date = '$today'"
    );
    if ($ok) {
        echo json_encode([
            'success'        => true,
            'message'        => 'Checked out at ' . date('h:i A') . '. Total: ' . $total_hours . 'h',
            'check_in_time'  => date('h:i A', $in_ts),
            'check_out_time' => date('h:i A'),
            'total_hours'    => $total_hours,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit();
}

if ($action === 'status') {
    $row = getTodayRow($conn, $sid, $today);
    echo json_encode([
        'success'        => true,
        'is_sunday'      => $is_sunday,
        'checked_in'     => !empty($row),
        'checked_out'    => !empty($row['check_out_time']),
        'check_in_time'  => $row ? date('h:i A', strtotime($row['check_in_time'])) : null,
        'check_out_time' => ($row && $row['check_out_time']) ? date('h:i A', strtotime($row['check_out_time'])) : null,
        'total_hours'    => $row['total_hours'] ?? null,
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);