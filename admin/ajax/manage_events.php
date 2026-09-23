<?php
/**
 * Event Management AJAX Endpoint
 * Handles: create_event, update_event, cancel_event, delete_event,
 *          mark_participation, bulk_mark_participation, get_students
 * 
 * Auth: requireLogin() + $canManageEvents check
 * Returns: JSON
 */
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

// Permission check — Super Admin, Admin, or Administrator
$role = strtolower($_SESSION['admin_role'] ?? '');
$isSA = ($role === 'super admin');
$isAdm = ($role === 'admin' || $isSA);
$canManageEvents = ($isAdm || $role === 'administrator');

if (!$canManageEvents) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // ══════════════════════════════════════
    // CREATE EVENT
    // ══════════════════════════════════════
    case 'create_event':
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $event_time = trim($_POST['event_time'] ?? '') ?: null;
        $category = trim($_POST['category'] ?? 'General');
        $icon = trim($_POST['icon'] ?? 'fas fa-calendar-alt');
        $status = trim($_POST['status'] ?? 'Upcoming');

        if (empty($title) || empty($event_date)) {
            echo json_encode(['success' => false, 'error' => 'Title and date are required.']);
            exit;
        }

        // Validate status
        $valid_statuses = ['Upcoming', 'Completed', 'Cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'Upcoming';
        }

        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, category, icon, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $title, $description, $event_date, $event_time, $category, $icon, $status, $admin_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event created successfully.', 'event_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create event.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════
    // UPDATE EVENT
    // ══════════════════════════════════════
    case 'update_event':
        $event_id = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $event_time = trim($_POST['event_time'] ?? '') ?: null;
        $category = trim($_POST['category'] ?? 'General');
        $icon = trim($_POST['icon'] ?? 'fas fa-calendar-alt');
        $status = trim($_POST['status'] ?? 'Upcoming');

        if ($event_id <= 0 || empty($title) || empty($event_date)) {
            echo json_encode(['success' => false, 'error' => 'Event ID, title and date are required.']);
            exit;
        }

        $valid_statuses = ['Upcoming', 'Completed', 'Cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'Upcoming';
        }

        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time=?, category=?, icon=?, status=? WHERE id=?");
        $stmt->bind_param("sssssssi", $title, $description, $event_date, $event_time, $category, $icon, $status, $event_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update event.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════
    // CANCEL EVENT
    // ══════════════════════════════════════
    case 'cancel_event':
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event ID.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE events SET status='Cancelled' WHERE id=?");
        $stmt->bind_param("i", $event_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event cancelled.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to cancel event.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════
    // DELETE EVENT (Super Admin only)
    // ══════════════════════════════════════
    case 'delete_event':
        if (!$isSA) {
            echo json_encode(['success' => false, 'error' => 'Only Super Admin can delete events.']);
            exit;
        }

        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event ID.']);
            exit;
        }

        // Delete participation records first
        $stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id=?");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $stmt->close();

        // Delete event
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $event_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event deleted permanently.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete event.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════
    // MARK SINGLE PARTICIPATION
    // ══════════════════════════════════════
    case 'mark_participation':
        $event_id = (int)($_POST['event_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($event_id <= 0 || $student_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event or student ID.']);
            exit;
        }

        $valid_participation = ['Participated', 'Not Participated'];
        if (!in_array($status, $valid_participation)) {
            echo json_encode(['success' => false, 'error' => 'Invalid participation status.']);
            exit;
        }

        $participated_at = ($status === 'Participated') ? date('Y-m-d H:i:s') : null;

        // INSERT ... ON DUPLICATE KEY UPDATE (uses uk_event_student unique key)
        $stmt = $conn->prepare("INSERT INTO event_participants (event_id, student_id, status, participated_at, marked_by) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE status=VALUES(status), participated_at=VALUES(participated_at), marked_by=VALUES(marked_by)");
        $stmt->bind_param("iissi", $event_id, $student_id, $status, $participated_at, $admin_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Participation updated.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update participation.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════
    // BULK MARK PARTICIPATION
    // ══════════════════════════════════════
    case 'bulk_mark_participation':
        $event_id = (int)($_POST['event_id'] ?? 0);
        $student_ids_json = $_POST['student_ids'] ?? '[]';
        $status = trim($_POST['status'] ?? '');

        if ($event_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event ID.']);
            exit;
        }

        $student_ids = json_decode($student_ids_json, true);
        if (!is_array($student_ids) || empty($student_ids)) {
            echo json_encode(['success' => false, 'error' => 'No students selected.']);
            exit;
        }

        $valid_participation = ['Participated', 'Not Participated'];
        if (!in_array($status, $valid_participation)) {
            echo json_encode(['success' => false, 'error' => 'Invalid participation status.']);
            exit;
        }

        $participated_at = ($status === 'Participated') ? date('Y-m-d H:i:s') : null;
        $success_count = 0;

        $stmt = $conn->prepare("INSERT INTO event_participants (event_id, student_id, status, participated_at, marked_by) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE status=VALUES(status), participated_at=VALUES(participated_at), marked_by=VALUES(marked_by)");

        foreach ($student_ids as $sid) {
            $sid = (int)$sid;
            if ($sid <= 0) continue;
            $stmt->bind_param("iissi", $event_id, $sid, $status, $participated_at, $admin_id);
            if ($stmt->execute()) $success_count++;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => "$success_count student(s) updated.", 'count' => $success_count]);
        break;

    // ══════════════════════════════════════
    // GET STUDENTS FOR PARTICIPATION (AJAX)
    // ══════════════════════════════════════
    case 'get_students':
        $event_id = (int)($_GET['event_id'] ?? 0);
        $batch_filter = trim($_GET['batch'] ?? '');

        if ($event_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event ID.']);
            exit;
        }

        $where = "s.status = 'Active' AND s.login_enabled = 1";
        $params = [];
        $types = "";

        if (!empty($batch_filter) && in_array($batch_filter, ['Morning', 'Evening'])) {
            $where .= " AND s.batch = ?";
            $params[] = $batch_filter;
            $types .= "s";
        }

        $sql = "SELECT s.id, s.full_name, s.student_code, s.batch,
                       ep.status as participation_status, ep.participated_at
                FROM students s
                LEFT JOIN event_participants ep ON s.id = ep.student_id AND ep.event_id = ?
                WHERE $where
                ORDER BY s.full_name ASC";
        
        array_unshift($params, $event_id);
        $types = "i" . $types;

        $stmt = $conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'students' => $students]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
?>
