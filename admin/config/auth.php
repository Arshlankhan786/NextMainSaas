<?php
/**
 * Authentication & Session Management
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Check if user is Super Admin
 */
function isSuperAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Super Admin';
}

/**
 * Require Super Admin access
 */
function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        $_SESSION['error'] = "Access denied. Super Admin privileges required.";
        header('Location: index.php');
        exit();
    }
}

/**
 * Check if user is HR
 */
function isHR() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'HR';
}

/**
 * Require HR or higher access
 */
function requireHRAccess() {
    requireLogin();
    if (!in_array($_SESSION['admin_role'], ['Super Admin', 'Admin', 'HR'])) {
        $_SESSION['error'] = "Access denied.";
        header('Location: index.php');
        exit();
    }
}

/**
 * Login user
 */
function loginUser($username, $password) {
    global $conn;
    
    $username = sanitize($username);
    
    $query = "SELECT id, username, password, full_name, email, role FROM admins WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            
            // Update last login
            $updateQuery = "UPDATE admins SET last_login = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    session_unset();
    session_destroy();
    header('Location: ../');
    exit();
}

/**
 * Get current admin info
 */
function getCurrentAdmin() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'name' => $_SESSION['admin_name'],
        'email' => $_SESSION['admin_email'],
        'role' => $_SESSION['admin_role']
    ];
}
?>