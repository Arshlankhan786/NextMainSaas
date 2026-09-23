<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $title = "Attendance Reminder";
    $message = "You were marked absent on " . date('d M Y', strtotime($date)) . ". Please ensure to mark your attendance daily to maintain good attendance record.";
    $type = "warning";
    
    // Get all absent students for that date
    $absentStudents = $conn->query("
        SELECT id FROM students
        WHERE status = 'Active'
        AND login_enabled = 1
        AND NOT EXISTS (
            SELECT 1 FROM student_attendance a
            WHERE a.student_id = students.id
            AND a.attendance_date = '$date'
        )
    ");
    
    $count = 0;
    $stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
    
    while ($student = $absentStudents->fetch_assoc()) {
        $student_id = $student['id'];
        $stmt->bind_param("isss", $student_id, $title, $message, $type);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    $stmt->close();
    $_SESSION['success'] = "Notification sent to $count absent students!";
    header('Location: attendance_report.php?date=' . $date);
    exit;
}

header('Location: attendance_report.php');
exit;
?>