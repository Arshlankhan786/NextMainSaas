<?php
/**
 * One-time DB setup for Student Project Task Manager
 * Run once via browser, then delete this file.
 */
require_once __DIR__ . '/../admin/config/database.php';

$results = [];

$tables = [
    'student_projects' => "CREATE TABLE IF NOT EXISTS `student_projects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `project_name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `project_link` varchar(500) DEFAULT NULL,
        `start_date` date NOT NULL,
        `end_date` date DEFAULT NULL,
        `status` enum('Not Started','In Progress','Completed','On Hold') DEFAULT 'In Progress',
        `completed_date` date DEFAULT NULL,
        `remarks` text DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_student` (`student_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'project_daily_logs' => "CREATE TABLE IF NOT EXISTS `project_daily_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `project_id` int(11) NOT NULL,
        `day_number` int(11) NOT NULL,
        `log_date` date NOT NULL,
        `work_description` text NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project` (`project_id`),
        KEY `idx_day` (`project_id`, `day_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql)) {
        $results[] = "✅ Table <b>$name</b> — created or already exists";
    } else {
        $results[] = "❌ Table <b>$name</b> — ERROR: " . $conn->error;
    }
}

// Check existing data
$sp_count  = $conn->query("SELECT COUNT(*) AS c FROM student_projects")->fetch_assoc()['c'] ?? 0;
$log_count = $conn->query("SELECT COUNT(*) AS c FROM project_daily_logs")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
  <title>DB Setup</title>
  <style>body{font-family:monospace;padding:30px;background:#0f0a1e;color:#e2e8f0}h2{color:#a78bfa}p{margin:8px 0}a{color:#60a5fa}</style>
</head>
<body>
  <h2>🔧 Student Task Manager — DB Setup</h2>
  <?php foreach ($results as $r): ?>
  <p><?= $r ?></p>
  <?php endforeach; ?>
  <p style="margin-top:16px;color:#94a3b8">
    📊 Current data: <b style="color:#a78bfa"><?= $sp_count ?></b> projects, <b style="color:#a78bfa"><?= $log_count ?></b> daily logs
  </p>
  <p style="margin-top:20px;color:#34d399">✅ Setup complete! <a href="../student/task_manager.php">→ Open Student Task Manager</a></p>
  <p><a href="../admin/student_task_manager.php">→ Open Admin Task Manager</a></p>
  <p style="color:#f87171;font-size:11px;margin-top:20px">⚠ Delete this file after setup: <code>student/setup_task_db.php</code></p>
</body>
</html>
