<?php
/**
 * student/ajax/get_attendance.php
 *
 * Returns attendance data for the currently logged-in student.
 * Used by the Attendance History section in student/dashboard.php.
 *
 * Response shape:
 * {
 *   success    : true,
 *   rawDates   : ['YYYY-MM-DD', ...],
 *   dates      : ['01 Feb', ...],
 *   present    : [1, 0, 1, ...],
 *   absent     : [0, 1, 0, ...],
 *   holiday    : [0, 0, 1, ...],
 *   checkIn    : ['09:12 AM', '--', ...],
 *   checkOut   : ['06:18 PM', '--', ...]
 * }
 *
 * Query params:
 *   days  (int, 7 | 15 | 30, default 30)
 */

require_once '../../admin/config/database.php';
require_once '../student_auth.php';

requireStudentLogin();

header('Content-Type: application/json');

$student_id = (int)$_SESSION['student_id'];
$days       = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days       = max(1, min(365, $days));

// ── Build date window (IST, oldest-first) ──
date_default_timezone_set('Asia/Kolkata');
$window = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $window[] = date('Y-m-d', strtotime("-{$i} days"));
}

$date_from = $window[0];
$date_to   = $window[count($window) - 1];

// ── Fetch attendance records ──
$stmt = $conn->prepare("
    SELECT attendance_date, status, check_in_time, check_out_time
    FROM student_attendance
    WHERE student_id = ?
      AND attendance_date BETWEEN ? AND ?
    ORDER BY attendance_date ASC
");
$stmt->bind_param('iss', $student_id, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[$row['attendance_date']] = $row;
}
$stmt->close();

// ── Build parallel arrays ──
$rawDates = [];
$dates    = [];
$present  = [];
$absent   = [];
$holiday  = [];
$checkIn  = [];
$checkOut = [];

foreach ($window as $date) {
    $rawDates[] = $date;
    $dates[]    = date('d M', strtotime($date));

    $row    = $records[$date] ?? null;
    $status = $row['status'] ?? null;

    if ($status === 'Present') {
        $present[]  = 1;
        $absent[]   = 0;
        $holiday[]  = 0;
        $checkIn[]  = (!empty($row['check_in_time']))
            ? date('h:i A', strtotime($row['check_in_time']))
            : '--';
        $checkOut[] = (!empty($row['check_out_time']))
            ? date('h:i A', strtotime($row['check_out_time']))
            : '--';
    } elseif ($status === 'Holiday') {
        $present[]  = 1;  // present-equivalent
        $absent[]   = 0;
        $holiday[]  = 1;
        $checkIn[]  = '--';
        $checkOut[] = '--';
    } else {
        $present[]  = 0;
        $absent[]   = 1;
        $holiday[]  = 0;
        $checkIn[]  = '--';
        $checkOut[] = '--';
    }
}

echo json_encode([
    'success'  => true,
    'rawDates' => $rawDates,
    'dates'    => $dates,
    'present'  => $present,
    'absent'   => $absent,
    'holiday'  => $holiday,
    'checkIn'  => $checkIn,
    'checkOut' => $checkOut,
]);
