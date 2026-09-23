<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$admin_id = $_SESSION['admin_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$student_id = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

if ($student_id === 0 || empty($action)) {
    $_SESSION['error'] = "Invalid request.";
    header('Location: students.php');
    exit();
}

// Get student details
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
if (!$student) {
    $_SESSION['error'] = "Student not found.";
    header('Location: students.php');
    exit();
}

// HOLD STUDENT
// HOLD STUDENT
if ($action === 'hold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $hold_reason = trim($_POST['hold_reason'] ?? '');
    $hold_date = getCurrentISTDate();
    
    // Update student status to Hold
    $stmt = $conn->prepare("
        UPDATE students 
        SET status = ?,
            hold_start_date = ?,
            hold_reason = ?
        WHERE id = ?
    ");
    
    $hold_status = 'Hold';  // Explicitly set variable
    $stmt->bind_param("sssi", $hold_status, $hold_date, $hold_reason, $student_id);
    
    if ($stmt->execute()) {
        // Log in history
        $stmt2 = $conn->prepare("
            INSERT INTO student_hold_history (student_id, hold_date, reason, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt2->bind_param("issi", $student_id, $hold_date, $hold_reason, $admin_id);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['success'] = "Student placed on hold successfully. Attendance, ranking, and course duration are paused.";
    } else {
        $_SESSION['error'] = "Failed to hold student. Error: " . $stmt->error;
    }
    $stmt->close();
    
    header("Location: student_details.php?id=$student_id");
    exit();
}

// RESUME STUDENT
if ($action === 'resume') {
    if ($student['status'] !== 'Hold') {
        $_SESSION['error'] = "Student is not on hold.";
        header("Location: student_details.php?id=$student_id");
        exit();
    }
    
    $resume_date = getCurrentISTDate();
    $hold_start = $student['hold_start_date'];
    
    // Calculate hold days
    $hold_days = 0;
    if ($hold_start) {
        $start = new DateTime($hold_start);
        $end = new DateTime($resume_date);
        $hold_days = $end->diff($start)->days;
    }
    
    // Update student
    $stmt = $conn->prepare("
        UPDATE students 
        SET status = 'Active',
            resume_date = ?,
            total_hold_days = total_hold_days + ?
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $resume_date, $hold_days, $student_id);
    
    if ($stmt->execute()) {
        // Update history
        $conn->query("
            UPDATE student_hold_history 
            SET resume_date = '$resume_date',
                hold_days = $hold_days
            WHERE student_id = $student_id 
            AND resume_date IS NULL
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $_SESSION['success'] = "Student resumed successfully. Enrollment continued from where it was paused.";
    } else {
        $_SESSION['error'] = "Failed to resume student.";
    }
    $stmt->close();
    
    header("Location: student_details.php?id=$student_id");
    exit();
}

$_SESSION['error'] = "Invalid action.";
header('Location: students.php');
exit();