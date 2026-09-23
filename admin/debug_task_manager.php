<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Task Manager Debugging</h2>";

// Test 1: Session
echo "<h3>1. Session Check</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✅ Session active - User ID: " . $_SESSION['user_id'] . "<br>";
    echo "✅ User Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
} else {
    echo "❌ No session found - You need to login first<br>";
}

// Test 2: Database Connection
echo "<h3>2. Database Connection</h3>";
if (file_exists('config/config.php')) {
    echo "✅ Config file exists<br>";
    require_once 'config/config.php';
    
    if (isset($conn)) {
        echo "✅ Database connection object exists<br>";
        
        // Test connection
        if ($conn->ping()) {
            echo "✅ Database connection active<br>";
        } else {
            echo "❌ Database connection failed: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ \$conn variable not found in config<br>";
    }
} else {
    echo "❌ config/config.php not found<br>";
}

// Test 3: Check if tasks table exists
echo "<h3>3. Tasks Table Check</h3>";
if (isset($conn)) {
    $result = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tasks table exists<br>";
        
        // Check table structure
        $columns = $conn->query("DESCRIBE tasks");
        echo "Table columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
        }
    } else {
        echo "❌ Tasks table does NOT exist - You need to run the SQL schema first<br>";
    }
}

// Test 4: Check required files
echo "<h3>4. Required Files Check</h3>";
$files = [
    'includes/auth_check.php',
    'includes/header.php',
    'includes/footer.php',
    'assets/css/task_manager.css',
    'assets/js/task_manager.js'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file NOT found<br>";
    }
}

// Test 5: Check ajax directory
echo "<h3>5. AJAX Files Check</h3>";
$ajax_files = [
    'ajax/get_tasks.php',
    'ajax/toggle_task.php',
    'ajax/manage_tasks.php'
];

foreach ($ajax_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file NOT found - Create this file<br>";
    }
}

echo "<h3>6. Permissions Check</h3>";
if (is_writable('ajax/')) {
    echo "✅ ajax/ directory is writable<br>";
} else {
    echo "⚠️ ajax/ directory may not be writable<br>";
}

echo "<hr><p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Fix any ❌ errors shown above</li>";
echo "<li>If tasks table doesn't exist, run the SQL schema</li>";
echo "<li>Create missing files</li>";
echo "<li>Then try accessing task_manager.php again</li>";
echo "</ol>";
?>