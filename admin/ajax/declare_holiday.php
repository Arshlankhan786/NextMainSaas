<?php
/**
 * ajax/declare_holiday.php
 *
 * Declare a date as Holiday for a filtered set of students.
 * Supports check_holiday (GET) and declare_holiday (POST) actions.
 *
 * POST params:
 *   action    = 'declare_holiday' | 'check_holiday'
 *   date      = 'YYYY-MM-DD'
 *   batch     = '' | 'Morning' | 'Evening'   (optional)
 *   group_id  = 0 | int                       (optional)
 *
 * Response: { success, message, affected_count, ... }
 */

session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// ── Auth guard ──
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action   = $_REQUEST['action'] ?? '';
$date     = $_REQUEST['date']   ?? '';
$batch    = $_REQUEST['batch']  ?? '';
$group_id = isset($_REQUEST['group_id']) ? (int)$_REQUEST['group_id'] : 0;

// ── Validate date ──
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}
// Ensure the date is not in the future
$today = date('Y-m-d');
if ($date > $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot declare holiday for a future date.']);
    exit;
}

// ── Validate batch ──
if (!empty($batch) && !in_array($batch, ['Morning', 'Evening'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch filter.']);
    exit;
}

// ── Validate group_id ──
if ($group_id > 0) {
    $gCheck = $conn->prepare("SELECT id FROM student_groups WHERE id = ?");
    $gCheck->bind_param('i', $group_id);
    $gCheck->execute();
    if ($gCheck->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid student group.']);
        $gCheck->close();
        exit;
    }
    $gCheck->close();
}

// ════════════════════════════════════════
// Build student scope query
// ════════════════════════════════════════
function buildStudentScope($conn, $batch, $group_id) {
    $where  = "s.status = 'Active' AND s.login_enabled = 1";
    $params = [];
    $types  = '';

    if (!empty($batch)) {
        $where .= " AND s.batch = ?";
        $params[] = $batch;
        $types   .= 's';
    }

    $join = '';
    if ($group_id > 0) {
        $join = "INNER JOIN student_group_members sgm ON s.id = sgm.student_id AND sgm.group_id = ?";
        $params[] = $group_id;
        $types   .= 'i';
    }

    $sql = "SELECT s.id FROM students s $join WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt->close();
    return $ids;
}

// ════════════════════════════════════════
// ACTION: check_holiday
// ════════════════════════════════════════
if ($action === 'check_holiday') {
    $student_ids = buildStudentScope($conn, $batch, $group_id);
    $total = count($student_ids);

    if ($total === 0) {
        echo json_encode([
            'success'        => true,
            'status'         => 'no_students',
            'total'          => 0,
            'holiday_count'  => 0,
            'existing_count' => 0,
            'message'        => 'No students found for the selected filters.'
        ]);
        exit;
    }

    // Count how many already have Holiday for this date
    $placeholders = implode(',', array_fill(0, $total, '?'));
    $types = str_repeat('i', $total);

    $stmt = $conn->prepare("
        SELECT student_id, status
        FROM student_attendance
        WHERE attendance_date = ?
          AND student_id IN ($placeholders)
    ");
    $bindTypes = 's' . $types;
    $bindParams = array_merge([$date], $student_ids);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $holiday_count  = 0;
    $existing_count = 0; // students with Present/Absent
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Holiday') {
            $holiday_count++;
        } else {
            $existing_count++;
        }
    }
    $stmt->close();

    $no_record_count = $total - $holiday_count - $existing_count;

    $status = 'no_holiday';
    if ($holiday_count === $total) {
        $status = 'already_holiday';
    } elseif ($holiday_count > 0) {
        $status = 'partial_holiday';
    }

    echo json_encode([
        'success'         => true,
        'status'          => $status,
        'total'           => $total,
        'holiday_count'   => $holiday_count,
        'existing_count'  => $existing_count,
        'no_record_count' => $no_record_count,
        'message'         => $status === 'already_holiday'
            ? 'This date is already declared as a holiday for all selected students.'
            : ($existing_count > 0
                ? "$existing_count student(s) already have attendance records for this date."
                : '')
    ]);
    exit;
}

// ════════════════════════════════════════
// ACTION: declare_holiday
// ════════════════════════════════════════
if ($action === 'declare_holiday') {
    $student_ids = buildStudentScope($conn, $batch, $group_id);
    $total = count($student_ids);

    if ($total === 0) {
        echo json_encode(['success' => false, 'message' => 'No students found for the selected filters.']);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        $affected = 0;

        // Check existing attendance for each student
        $checkStmt = $conn->prepare("
            SELECT id, status FROM student_attendance
            WHERE student_id = ? AND attendance_date = ?
            LIMIT 1
        ");

        $insertStmt = $conn->prepare("
            INSERT INTO student_attendance (student_id, attendance_date, status, check_in_time, check_out_time, total_hours, created_at)
            VALUES (?, ?, 'Holiday', NULL, NULL, NULL, NOW())
        ");

        $updateStmt = $conn->prepare("
            UPDATE student_attendance
            SET status = 'Holiday', check_in_time = NULL, check_out_time = NULL, total_hours = NULL
            WHERE id = ?
        ");

        foreach ($student_ids as $sid) {
            $checkStmt->bind_param('is', $sid, $date);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();

            if (!$existing) {
                // No record → insert Holiday
                $insertStmt->bind_param('is', $sid, $date);
                $insertStmt->execute();
                $affected++;
            } elseif ($existing['status'] !== 'Holiday') {
                // Has Present/Absent → update to Holiday
                $updateStmt->bind_param('i', $existing['id']);
                $updateStmt->execute();
                $affected++;
            }
            // If already Holiday → skip (idempotent)
        }

        $checkStmt->close();
        $insertStmt->close();
        $updateStmt->close();

        $conn->commit();

        $message = $affected > 0
            ? "Holiday declared successfully for $affected student(s)."
            : 'This date is already declared as a holiday for all selected students.';

        echo json_encode([
            'success'        => true,
            'message'        => $message,
            'affected_count' => $affected,
            'total'          => $total
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('Holiday declaration failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to declare holiday. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
