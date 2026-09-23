<?php
/**
 * ajax/get_student_attendance.php
 *
 * Returns attendance data for a single student over a date window.
 *
 * Required response shape (used by student_details.php JS):
 * {
 *   success    : true,
 *   rawDates   : ['YYYY-MM-DD', ...],   ← CRITICAL — used for Sunday detection & all stat math
 *   dates      : ['01 Feb', ...],        ← display labels on the chart X-axis
 *   present    : [1, 0, 1, ...],        ← 1 = Present or Holiday, 0 = Absent/no-record  (index-aligned with rawDates)
 *   absent     : [0, 1, 0, ...]         ← inverse of present (for stacked bar chart)
 *   holiday    : [0, 0, 1, ...]         ← 1 = Holiday (subset of present=1 days)
 *   checkIn    : ['09:12 AM', '--', ...]  ← formatted check-in time or '--'
 *   checkOut   : ['06:18 PM', '--', ...]  ← formatted check-out time or '--'
 * }
 *
 * Query params:
 *   student_id  (int, required)
 *   days        (int, 1–365, default 30)
 */

session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// ── Auth guard ──
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// ── Input validation ──
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$days       = isset($_GET['days'])       ? (int)$_GET['days']       : 30;

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid student_id']);
    exit;
}

$days = max(1, min(365, $days));

// ── Build the date window (today going back $days days) ──
// Use IST for date calculation (UTC+5:30)
$ist_offset = 5.5 * 3600; // 19800 seconds
$now_ist    = time() + $ist_offset;
$today_ist  = gmdate('Y-m-d', $now_ist);

// Generate every date in the window — oldest first
$window = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $window[] = gmdate('Y-m-d', $now_ist - ($i * 86400));
}

// ── Fetch attendance records for this student in the window ──
$date_from = $window[0];
$date_to   = $window[count($window) - 1];

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

// Index records by date for fast lookup
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[$row['attendance_date']] = $row;
}
$stmt->close();

// ── Build parallel arrays ──
$rawDates = [];  // 'YYYY-MM-DD'  — used by JS for Sunday detection
$dates    = [];  // 'DD Mon'      — chart X-axis labels
$present  = [];  // 1 or 0 (Present or Holiday = 1)
$absent   = [];  // 1 or 0
$holiday  = [];  // 1 or 0 (Holiday-specific flag)
$checkIn  = [];  // formatted time string or '--'
$checkOut = [];  // formatted time string or '--'

foreach ($window as $date) {
    $rawDates[] = $date;
    $dates[]    = gmdate('d M', strtotime($date));   // e.g. "01 Feb"

    $row    = $records[$date] ?? null;
    $status = $row['status'] ?? null;

    if ($status === 'Present') {
        $present[]  = 1;
        $absent[]   = 0;
        $holiday[]  = 0;
        // Format check-in time
        $ci = (!empty($row['check_in_time']))
            ? date('h:i A', strtotime($row['check_in_time']))
            : '--';
        $co = (!empty($row['check_out_time']))
            ? date('h:i A', strtotime($row['check_out_time']))
            : '--';
        $checkIn[]  = $ci;
        $checkOut[] = $co;
    } elseif ($status === 'Holiday') {
        $present[]  = 1;  // treated as present-equivalent for charts/stats
        $absent[]   = 0;
        $holiday[]  = 1;
        $checkIn[]  = '--';
        $checkOut[] = '--';
    } else {
        // 'Absent', no-record, or Sunday — chart still gets a bar slot
        // Sunday colour is handled client-side via rawDates day-of-week check
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