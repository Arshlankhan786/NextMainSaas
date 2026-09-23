<?php
/**
 * DATABASE CHECKER - Run this first to verify setup
 * Access: yourdomain.com/admin/check_task_tables.php
 */

require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

echo "<!DOCTYPE html><html><head><title>Task Manager - Database Check</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin:10px 0;}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:8px;margin:10px 0;}";
echo "pre{background:#fff;padding:10px;border-left:3px solid #007bff;overflow:auto;}";
echo "</style></head><body>";

echo "<h1>🔍 Task Manager Database Check</h1>";

// Check if tables exist
$tables_exist = true;

// Check admin_tasks table
$result = $conn->query("SHOW TABLES LIKE 'admin_tasks'");
if ($result->num_rows > 0) {
    echo "<div class='success'>✅ Table 'admin_tasks' exists</div>";
    
    // Show structure
    $structure = $conn->query("DESCRIBE admin_tasks");
    echo "<div class='info'><strong>Table Structure:</strong><pre>";
    while ($row = $structure->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre></div>";
    
    // Count rows
    $count = $conn->query("SELECT COUNT(*) as cnt FROM admin_tasks WHERE admin_id = {$_SESSION['admin_id']}")->fetch_assoc();
    echo "<div class='info'>📊 You have <strong>{$count['cnt']}</strong> task(s)</div>";
} else {
    echo "<div class='error'>❌ Table 'admin_tasks' does NOT exist</div>";
    $tables_exist = false;
}

// Check admin_task_history table
$result = $conn->query("SHOW TABLES LIKE 'admin_task_history'");
if ($result->num_rows > 0) {
    echo "<div class='success'>✅ Table 'admin_task_history' exists</div>";
} else {
    echo "<div class='error'>❌ Table 'admin_task_history' does NOT exist</div>";
    $tables_exist = false;
}

if (!$tables_exist) {
    echo "<div class='error'>";
    echo "<h3>❌ TABLES MISSING - Run this SQL:</h3>";
    echo "<pre>";
    echo "CREATE TABLE IF NOT EXISTS `admin_tasks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `is_repeating` TINYINT(1) DEFAULT 0,
  `repeat_interval_hours` INT(11) DEFAULT NULL,
  `repeat_count` INT(11) DEFAULT 0,
  `max_repeats` INT(11) DEFAULT NULL,
  `next_due_date` DATETIME DEFAULT NULL,
  `status` ENUM('active', 'completed', 'incomplete') DEFAULT 'active',
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_task_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL,
  `admin_id` INT(11) NOT NULL,
  `action` ENUM('completed', 'missed', 'created') NOT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    echo "</pre></div>";
} else {
    echo "<div class='success'>";
    echo "<h3>✅ DATABASE READY!</h3>";
    echo "<p>All required tables exist. You can now use the Task Manager.</p>";
    echo "<p><a href='task_manager.php' style='background:#7c3aed;color:white;padding:10px 20px;text-decoration:none;border-radius:8px;display:inline-block;'>Go to Task Manager →</a></p>";
    echo "</div>";
}

// Test admin authentication
echo "<div class='info'>";
echo "<strong>👤 Logged in as:</strong> {$_SESSION['admin_name']} (ID: {$_SESSION['admin_id']})<br>";
echo "<strong>🔑 Role:</strong> {$_SESSION['admin_role']}";
echo "</div>";

echo "</body></html>";
?>