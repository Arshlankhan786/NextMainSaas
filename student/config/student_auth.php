<?php
/**
 * Student Authentication & Session Management
 * All date/time operations use Asia/Kolkata (IST) timezone.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Always enforce India timezone
date_default_timezone_set('Asia/Kolkata');

function isStudentLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['student_code']);
}

function requireStudentLogin() {
    if (!isStudentLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function logoutStudent() {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

function getCurrentStudent() {
    if (!isStudentLoggedIn()) return null;
    return [
        'id'    => $_SESSION['student_id'],
        'code'  => $_SESSION['student_code'],
        'name'  => $_SESSION['student_name'],
        'email' => $_SESSION['student_email'] ?? '',
    ];
}
?>