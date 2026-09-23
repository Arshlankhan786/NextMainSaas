<?php
require_once __DIR__ . '/../admin/config/database.php';

echo "=== student_attendance status ENUM values ===\n";
$r = $conn->query("SHOW COLUMNS FROM student_attendance LIKE 'status'");
$row = $r->fetch_assoc();
echo "Type: " . $row['Type'] . "\n";

echo "\n=== Sample attendance records ===\n";
$r2 = $conn->query("SELECT student_id, attendance_date, status FROM student_attendance ORDER BY attendance_date DESC LIMIT 10");
while ($row2 = $r2->fetch_assoc()) {
    echo "Student {$row2['student_id']} | {$row2['attendance_date']} | {$row2['status']}\n";
}
