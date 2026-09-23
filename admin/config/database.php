<?php
/**
 * Database Configuration - OPTIMIZED FOR HOSTINGER SHARED HOSTING
 * 
 * Connection optimization notes:
 * - Uses localhost for local MySQL (avoids DNS + TCP overhead)
 * - Credentials loaded from separate file for security
 * - Single connection per PHP request, auto-closed on shutdown
 * - No persistent connections (would mask quota issues)
 */

// Load credentials from separate file (keeps passwords out of main config)
require_once __DIR__ . '/db_credentials.php';

// Create database connection (one per PHP request)
$conn = null;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection — do NOT expose credentials in error message
    if ($conn->connect_error) {
        error_log('MySQL connection failed: ' . $conn->connect_error);
        if (php_sapi_name() === 'cli') {
            die("Database connection failed. Check error log for details.\n");
        }
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false)) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'Database connection error. Please try again later.']));
        }
        die("Database connection error. Please try again later.");
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
    // Set timezone to Indian Standard Time (IST)
    // Required because SQL uses NOW() in INSERT/UPDATE statements
    $conn->query("SET time_zone = '+05:30'");
    
} catch (Exception $e) {
    error_log('Database exception: ' . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Ensure connection is closed at script end to prevent connection leaks on shared hosting
register_shutdown_function(function() {
    global $conn;
    if ($conn && $conn instanceof mysqli && !$conn->connect_error) {
        @$conn->close();
    }
});

// Set PHP timezone to IST
date_default_timezone_set('Asia/Kolkata');

/**
 * Sanitize input data
 */
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Execute prepared statement
 */
function executeQuery($query, $types = "", $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}

/**
 * Get single result
 */
function getSingleResult($query, $types = "", $params = []) {
    $stmt = executeQuery($query, $types, $params);
    if (!$stmt) return null;
    
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}

/**
 * Get multiple results
 */
function getResults($query, $types = "", $params = []) {
    $stmt = executeQuery($query, $types, $params);
    if (!$stmt) return [];
    
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
    return $data;
}

/**
 * Rebuild the footer popular courses cache file.
 * Called after course create/update/delete in admin/courses.php.
 * The cache file is a simple PHP array read by includes/footer.php.
 */
function rebuildFooterCache() {
    global $conn;
    $cache_file = __DIR__ . '/footer_cache.php';
    $courses = [];
    
    try {
        $result = $conn->query("SELECT name FROM courses WHERE status = 'Active' ORDER BY created_at DESC LIMIT 5");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row['name'];
            }
        }
    } catch (Exception $e) {
        // If query fails, write an empty cache — footer will show static fallback
        error_log('Footer cache rebuild failed: ' . $e->getMessage());
    }
    
    $content = "<?php\n// Auto-generated footer cache — do not edit manually\n// Generated: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($courses, true) . ";\n";
    @file_put_contents($cache_file, $content, LOCK_EX);
}

/**
 * Get current IST date
 */
function getCurrentISTDate() {
    return date('Y-m-d');
}

/**
 * Get current IST time
 */
function getCurrentISTTime() {
    return date('H:i:s');
}

/**
 * Get current IST datetime
 */
function getCurrentISTDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Format date in Indian format
 */
function formatIndianDate($date) {
    return date('d-m-Y', strtotime($date));
}

/**
 * Format time in Indian format (12-hour with AM/PM)
 */
function formatIndianTime($time) {
    return date('h:i A', strtotime($time));
}

/**
 * Format datetime in Indian format
 */
function formatIndianDateTime($datetime) {
    return date('d-m-Y h:i A', strtotime($datetime));
}
?>