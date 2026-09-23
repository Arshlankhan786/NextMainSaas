<?php
/**
 * Authentication Check
 * Include this file at the top of protected admin pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Optional: Check if user has admin privileges
$allowed_roles = ['admin', 'super_admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../index.php'); // Redirect to public homepage
    exit();
}

// User is authenticated - continue