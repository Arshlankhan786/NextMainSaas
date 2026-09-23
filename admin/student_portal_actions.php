<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: students.php');
    exit;
}

$action = $_POST['action'];
$student_id = (int)$_POST['student_id'];

if ($action === 'enable_login') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Check if username already exists
    $check = $conn->query("SELECT id FROM students WHERE username = '$username' AND id != $student_id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Username already exists. Please choose a different one.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE students SET login_enabled = 1, username = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $hashed_password, $student_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Portal access enabled successfully! Username: $username, Password: $password";
            
            // Create welcome notification
            $title = "Welcome to Student Portal!";
            $message = "Your portal access has been activated. You can now login and view your payment history, receipts, and profile information.";
            $type = "success";
            
            $notif_stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notif_stmt->bind_param("isss", $student_id, $title, $message, $type);
            $notif_stmt->execute();
            $notif_stmt->close();
        } else {
            $_SESSION['error'] = "Failed to enable portal access.";
        }
        $stmt->close();
    }
}

if ($action === 'reset_password') {
    $new_password = trim($_POST['new_password']);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Password reset successfully! New password: $new_password";
        
        // Create notification
        $title = "Password Reset";
        $message = "Your student portal password has been reset by the administrator. Please use your new credentials to login.";
        $type = "warning";
        
        $notif_stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notif_stmt->bind_param("isss", $student_id, $title, $message, $type);
        $notif_stmt->execute();
        $notif_stmt->close();
    } else {
        $_SESSION['error'] = "Failed to reset password.";
    }
    $stmt->close();
}

if ($action === 'disable_login') {
    $stmt = $conn->prepare("UPDATE students SET login_enabled = 0 WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Portal access disabled successfully!";
    } else {
        $_SESSION['error'] = "Failed to disable portal access.";
    }
    $stmt->close();
}

header("Location: student_details.php?id=$student_id");
exit;
?>