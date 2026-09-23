<?php
// CRITICAL: Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

ob_end_clean();
header('Content-Type: application/json');

// Authentication check
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$raw_role = strtolower(trim($_SESSION['admin_role'] ?? ''));

// Normalize role names
switch ($raw_role) {
    case 'super admin':
        $admin_role = 'super admin';
        break;
    case 'administrator':
        $admin_role = 'administrator';
        break;
    case 'admin':
    default:
        $admin_role = 'admin';
        break;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ===============================
// ACTION: ADD FOCUS MODE TASK
// ===============================
if ($action === 'add_focus') {
    try {
        $title      = trim($_POST['title'] ?? '');
        $category   = trim($_POST['category'] ?? 'Work');
        $time_block = $_POST['time_block'] ?? 'all_day';
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $end_date   = $_POST['end_date'] ?? date('Y-m-d');
        $assigned_to = isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $admin_id;

        // Priority — validate and default to medium
        $raw_priority = strtolower(trim($_POST['priority'] ?? 'medium'));
        $priority = in_array($raw_priority, ['high', 'medium', 'low']) ? $raw_priority : 'medium';

        // Validation
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Task title is required']);
            exit();
        }

        // Repeat fields
        $is_repeating    = isset($_POST['repeat_enabled']) ? 1 : 0;
        $repeat_interval = $is_repeating ? (int)($_POST['repeat_interval'] ?? 1) : 0;
        $repeat_type     = $is_repeating ? ($_POST['repeat_type'] ?? 'day') : null;
        $max_repeats     = null;

        if ($is_repeating) {
            $max_repeats = isset($_POST['repeat_count']) ? (int)$_POST['repeat_count'] : null;
        }

        // Permission check: only super admin can assign to others
        if ($admin_role !== 'super admin' && $assigned_to != $admin_id) {
            echo json_encode(['success' => false, 'message' => 'You can only create tasks for yourself']);
            exit();
        }

        // Calculate repeat_interval_hours for database
        $repeat_interval_hours = null;
        if ($is_repeating && $repeat_interval > 0) {
            if ($repeat_type === 'hour') {
                $repeat_interval_hours = $repeat_interval;
            } else {
                $repeat_interval_hours = $repeat_interval * 24;
            }
        }

        // Calculate next_due_date
        if ($is_repeating && $repeat_interval_hours > 0) {
            $next_due_date = date('Y-m-d', strtotime("+{$repeat_interval_hours} hours"));
        } else {
            $next_due_date = $start_date;
        }

        $stmt = $conn->prepare("
            INSERT INTO admin_tasks
                (admin_id, title, description, category, time_block, start_date, end_date,
                 is_repeating, repeat_interval_hours, max_repeats, repeat_count,
                 next_due_date, assigned_to, created_by, status, priority, created_at)
            VALUES
                (?, ?, '', ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'Pending', ?, NOW())
        ");

        $created_by = $admin_id;

        $stmt->bind_param('isssssiiisiss',
            $assigned_to,
            $title,
            $category,
            $time_block,
            $start_date,
            $end_date,
            $is_repeating,
            $repeat_interval_hours,
            $max_repeats,
            $next_due_date,
            $assigned_to,
            $created_by,
            $priority
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Task created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create task: ' . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: GET FOCUS MODE TASKS
// ===============================
if ($action === 'get_focus_tasks') {
    try {
        $user_id_filter = isset($_GET['user_id']) && $_GET['user_id'] !== ''
            ? (int)$_GET['user_id']
            : $admin_id;

        $show_completed = isset($_GET['show_completed']) && $_GET['show_completed'] == '1';

        $where  = [];
        $params = [];
        $types  = '';

        // Role-based filtering
        if ($admin_role === 'admin' || $admin_role === 'administrator') {
            $where[]  = "assigned_to = ?";
            $params[] = $admin_id;
            $types   .= 'i';
        } else {
            $where[]  = "assigned_to = ?";
            $params[] = $user_id_filter;
            $types   .= 'i';
        }

        // Status filtering
        if (!$show_completed) {
            $where[] = "status = 'Pending'";
        } else {
            $where[] = "status IN ('Pending', 'Completed')";
        }

        // Date filtering
        $where[] = "(
            (status = 'Pending' AND start_date <= CURDATE())
            OR
            (status = 'Completed' AND is_repeating = 0)
            OR
            (status = 'Completed' AND is_repeating = 1
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
            OR
            status = 'Incomplete'
        )";

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "
            SELECT id, title, description, category, time_block, start_date, end_date,
                   is_repeating, repeat_interval_hours, max_repeats, repeat_count,
                   status, completed_at, created_at,
                   COALESCE(priority, 'medium') AS priority,
                   COALESCE(is_pinned, 0) AS is_pinned
            FROM admin_tasks
            $where_sql
      ORDER BY
    CASE status
        WHEN 'Pending' THEN 1
        WHEN 'Incomplete' THEN 2
        WHEN 'Completed' THEN 3
        ELSE 4
    END,
    COALESCE(is_pinned, 0) DESC,
    CASE COALESCE(priority, 'medium')
        WHEN 'high' THEN 1
        WHEN 'medium' THEN 2
        WHEN 'low' THEN 3
        ELSE 2
    END,
    CASE time_block
        WHEN 'all_day' THEN 1
        WHEN '6-9am' THEN 2
        WHEN '9-2pm' THEN 3
        WHEN '2-7pm' THEN 4
        WHEN '7-12am' THEN 5
        ELSE 6
    END,
    created_at DESC
        ";

        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $tasks = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }

        echo json_encode(['success' => true, 'tasks' => $tasks]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}
// ===============================
// ACTION: GET FOCUS DASHBOARD (combined tasks + analytics — saves 1 DB connection per poll)
// ===============================
if ($action === 'get_focus_dashboard') {
    try {
        // ── Tasks (same logic as get_focus_tasks) ──
        $user_id_filter = isset($_GET['user_id']) && $_GET['user_id'] !== ''
            ? (int)$_GET['user_id']
            : $admin_id;

        $show_completed = isset($_GET['show_completed']) && $_GET['show_completed'] == '1';

        $where  = [];
        $params = [];
        $types  = '';

        if ($admin_role === 'admin' || $admin_role === 'administrator') {
            $where[]  = "assigned_to = ?";
            $params[] = $admin_id;
            $types   .= 'i';
        } else {
            $where[]  = "assigned_to = ?";
            $params[] = $user_id_filter;
            $types   .= 'i';
        }

        if (!$show_completed) {
            $where[] = "status = 'Pending'";
        } else {
            $where[] = "status IN ('Pending', 'Completed')";
        }

        $where[] = "(
            (status = 'Pending' AND start_date <= CURDATE())
            OR
            (status = 'Completed' AND is_repeating = 0)
            OR
            (status = 'Completed' AND is_repeating = 1
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
            OR
            status = 'Incomplete'
        )";

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "
            SELECT id, title, description, category, time_block, start_date, end_date,
                   is_repeating, repeat_interval_hours, max_repeats, repeat_count,
                   status, completed_at, created_at,
                   COALESCE(priority, 'medium') AS priority,
                   COALESCE(is_pinned, 0) AS is_pinned
            FROM admin_tasks
            $where_sql
      ORDER BY
    CASE status
        WHEN 'Pending' THEN 1
        WHEN 'Incomplete' THEN 2
        WHEN 'Completed' THEN 3
        ELSE 4
    END,
    COALESCE(is_pinned, 0) DESC,
    CASE COALESCE(priority, 'medium')
        WHEN 'high' THEN 1
        WHEN 'medium' THEN 2
        WHEN 'low' THEN 3
        ELSE 2
    END,
    CASE time_block
        WHEN 'all_day' THEN 1
        WHEN '6-9am' THEN 2
        WHEN '9-2pm' THEN 3
        WHEN '2-7pm' THEN 4
        WHEN '7-12am' THEN 5
        ELSE 6
    END,
    created_at DESC
        ";

        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $tasks = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
        }

        // ── Analytics (same logic as get_analytics) ──
        $scope_id = ($admin_role === 'super admin' && isset($_GET['user_id']) && $_GET['user_id'] !== '')
            ? (int)$_GET['user_id']
            : $admin_id;

        $filter_type = $_GET['filter_type'] ?? 'today';
        $from_date = $to_date = date('Y-m-d');

        switch ($filter_type) {
            case 'week':
                $from_date = date('Y-m-d', strtotime('monday this week'));
                $to_date   = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $from_date = date('Y-m-01');
                $to_date   = date('Y-m-t');
                break;
            case 'custom':
                $from_date = $_GET['from_date'] ?? date('Y-m-01');
                $to_date   = $_GET['to_date']   ?? date('Y-m-d');
                break;
        }

        $astmt = $conn->prepare("
            SELECT
                COUNT(*)                                                AS total,
                SUM(status = 'Completed')                               AS done,
                SUM(status IN ('Pending', 'Incomplete'))                AS pending,
                SUM(COALESCE(priority, 'medium') = 'high')              AS high,
                SUM(COALESCE(priority, 'medium') = 'medium')            AS medium,
                SUM(COALESCE(priority, 'medium') = 'low')               AS low
            FROM admin_tasks
            WHERE assigned_to = ?
              AND start_date BETWEEN ? AND ?
        ");
        $astmt->bind_param('iss', $scope_id, $from_date, $to_date);
        $astmt->execute();
        $arow = $astmt->get_result()->fetch_assoc();
        $astmt->close();

        echo json_encode([
            'success'   => true,
            'tasks'     => $tasks,
            'analytics' => [
                'total'   => (int)($arow['total']   ?? 0),
                'done'    => (int)($arow['done']     ?? 0),
                'pending' => (int)($arow['pending']  ?? 0),
                'high'    => (int)($arow['high']     ?? 0),
                'medium'  => (int)($arow['medium']   ?? 0),
                'low'     => (int)($arow['low']      ?? 0),
                'filter'  => $filter_type,
                'from'    => $from_date,
                'to'      => $to_date,
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: COMPLETE TASK
// ===============================
if ($action === 'complete') {
    try {
        $task_id = (int)($_POST['task_id'] ?? 0);

        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }

        $stmt = $conn->prepare("SELECT * FROM admin_tasks
                               WHERE id = ?
                               AND assigned_to = ?
                               AND status = 'Pending'");
        $stmt->bind_param('ii', $task_id, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            exit();
        }

        $task = $result->fetch_assoc();

        $stmt = $conn->prepare("UPDATE admin_tasks
                              SET status = 'Completed',
                                  completed_at = NOW()
                              WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param('i', $task_id);

        if ($stmt->execute()) {
            $message = 'Task completed!';

            if ($task['is_repeating'] == 1 && $task['repeat_interval_hours'] > 0) {
                $current_repeat = (int)$task['repeat_count'];
                $max_repeats    = $task['max_repeats'] ? (int)$task['max_repeats'] : null;

                if ($max_repeats === null || $max_repeats == 0 || $current_repeat < $max_repeats) {
                    $interval_hours = (int)$task['repeat_interval_hours'];
                    $new_start_date = date('Y-m-d', strtotime("+{$interval_hours} hours"));

                    $duration_days = 0;
                    if ($task['start_date'] && $task['end_date']) {
                        $duration_days = (strtotime($task['end_date']) - strtotime($task['start_date'])) / 86400;
                    }
                    $new_end_date     = date('Y-m-d', strtotime($new_start_date . " +{$duration_days} days"));
                    $new_repeat_count = $current_repeat + 1;

                    $insert = $conn->prepare("
                        INSERT INTO admin_tasks
                            (admin_id, title, description, category, time_block, start_date, end_date,
                             is_repeating, repeat_interval_hours, max_repeats, repeat_count,
                             next_due_date, assigned_to, created_by, status, priority, created_at)
                        SELECT admin_id, title, description, category, time_block, ?, ?,
                               is_repeating, repeat_interval_hours, max_repeats, ?,
                               ?, assigned_to, created_by, 'Pending',
                               COALESCE(priority, 'medium'), NOW()
                        FROM admin_tasks WHERE id = ?
                    ");

                    $insert->bind_param('ssisi',
                        $new_start_date,
                        $new_end_date,
                        $new_repeat_count,
                        $new_start_date,
                        $task_id
                    );

                    if ($insert->execute()) {
                        $message = 'Task completed! Next occurrence scheduled.';
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to complete task']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: DELETE TASK
// ===============================
if ($action === 'delete') {
    try {
        $task_id = (int)($_POST['task_id'] ?? 0);

        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }

        if ($admin_role === 'super admin') {
            $stmt = $conn->prepare("DELETE FROM admin_tasks WHERE id = ?");
            $stmt->bind_param('i', $task_id);
        } else {
            $stmt = $conn->prepare("DELETE FROM admin_tasks WHERE id = ? AND assigned_to = ?");
            $stmt->bind_param('ii', $task_id, $admin_id);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Task deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// LEGACY: GET ALL TASKS
// ===============================
if ($action === 'get_all') {
    try {
        $active     = [];
        $completed  = [];
        $incomplete = [];

        $result = $conn->query("SELECT * FROM admin_tasks
            WHERE admin_id = $admin_id AND status = 'Pending'
            ORDER BY
                CASE COALESCE(priority,'medium') WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 2 END,
                next_due_date ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) $active[] = $row;
        }

        $result = $conn->query("SELECT * FROM admin_tasks
            WHERE admin_id = $admin_id AND status = 'Completed'
            ORDER BY completed_at DESC LIMIT 50");
        if ($result) {
            while ($row = $result->fetch_assoc()) $completed[] = $row;
        }

        $result = $conn->query("SELECT * FROM admin_tasks
            WHERE admin_id = $admin_id AND status = 'Incomplete'
            ORDER BY next_due_date DESC LIMIT 50");
        if ($result) {
            while ($row = $result->fetch_assoc()) $incomplete[] = $row;
        }

        echo json_encode([
            'success'    => true,
            'active'     => $active,
            'completed'  => $completed,
            'incomplete' => $incomplete
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: TOGGLE PIN (single task)
// ===============================
if ($action === 'toggle_pin') {
    try {
        $task_id = (int)($_POST['task_id'] ?? 0);

        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }

        if ($admin_role === 'super admin') {
            $stmt = $conn->prepare("SELECT id, is_pinned FROM admin_tasks WHERE id = ?");
            $stmt->bind_param('i', $task_id);
        } else {
            $stmt = $conn->prepare("SELECT id, is_pinned FROM admin_tasks WHERE id = ? AND assigned_to = ?");
            $stmt->bind_param('ii', $task_id, $admin_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
            exit();
        }

        $task    = $result->fetch_assoc();
        $new_pin = ($task['is_pinned'] == 1) ? 0 : 1;

        $upd = $conn->prepare("UPDATE admin_tasks SET is_pinned = ? WHERE id = ?");
        $upd->bind_param('ii', $new_pin, $task_id);

        if ($upd->execute()) {
            echo json_encode(['success' => true, 'is_pinned' => $new_pin,
                              'message' => $new_pin ? 'Task pinned' : 'Task unpinned']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update pin state']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: BULK PIN / UNPIN
// ===============================
if ($action === 'bulk_pin') {
    try {
        $raw_ids   = $_POST['task_ids'] ?? '[]';
        $pin_state = (int)($_POST['pin_state'] ?? 1);
        $ids_arr   = json_decode($raw_ids, true);

        if (!is_array($ids_arr) || empty($ids_arr)) {
            echo json_encode(['success' => false, 'message' => 'No task IDs provided']);
            exit();
        }

        $ids_int = array_map('intval', $ids_arr);
        $ids_int = array_filter($ids_int, fn($id) => $id > 0);

        if (empty($ids_int)) {
            echo json_encode(['success' => false, 'message' => 'Invalid task IDs']);
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($ids_int), '?'));

        if ($admin_role === 'super admin') {
            $sql    = "UPDATE admin_tasks SET is_pinned = ? WHERE id IN ($placeholders)";
            $types  = 'i' . str_repeat('i', count($ids_int));
            $params = array_merge([$pin_state], $ids_int);
        } else {
            $sql    = "UPDATE admin_tasks SET is_pinned = ? WHERE id IN ($placeholders) AND assigned_to = ?";
            $types  = 'i' . str_repeat('i', count($ids_int)) . 'i';
            $params = array_merge([$pin_state], $ids_int, [$admin_id]);
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            echo json_encode(['success' => true,
                              'message' => ($pin_state ? 'Tasks pinned' : 'Tasks unpinned'),
                              'affected' => $stmt->affected_rows]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bulk pin failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: GET ANALYTICS
// ===============================
if ($action === 'get_analytics') {
    try {
        if ($admin_role === 'super admin') {
            $scope_id = isset($_GET['user_id']) && $_GET['user_id'] !== ''
                ? (int)$_GET['user_id']
                : $admin_id;
        } else {
            $scope_id = $admin_id;
        }

        $filter_type = $_GET['filter_type'] ?? 'today';
        $from_date   = null;
        $to_date     = null;

        switch ($filter_type) {
            case 'today':
                $from_date = date('Y-m-d');
                $to_date   = date('Y-m-d');
                break;
            case 'week':
                $from_date = date('Y-m-d', strtotime('monday this week'));
                $to_date   = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $from_date = date('Y-m-01');
                $to_date   = date('Y-m-t');
                break;
            case 'custom':
                $from_date = $_GET['from_date'] ?? date('Y-m-01');
                $to_date   = $_GET['to_date']   ?? date('Y-m-d');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) ||
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                    exit();
                }
                if ($from_date > $to_date) {
                    [$from_date, $to_date] = [$to_date, $from_date];
                }
                break;
            default:
                $from_date = date('Y-m-d');
                $to_date   = date('Y-m-d');
                break;
        }

        $stmt = $conn->prepare("
            SELECT
                COUNT(*)                                                AS total,
                SUM(status = 'Completed')                               AS done,
                SUM(status IN ('Pending', 'Incomplete'))                AS pending,
                SUM(COALESCE(priority, 'medium') = 'high')              AS high,
                SUM(COALESCE(priority, 'medium') = 'medium')            AS medium,
                SUM(COALESCE(priority, 'medium') = 'low')               AS low
            FROM admin_tasks
            WHERE assigned_to = ?
              AND start_date BETWEEN ? AND ?
        ");

        $stmt->bind_param('iss', $scope_id, $from_date, $to_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'total'   => (int)($row['total']   ?? 0),
            'done'    => (int)($row['done']     ?? 0),
            'pending' => (int)($row['pending']  ?? 0),
            'high'    => (int)($row['high']     ?? 0),
            'medium'  => (int)($row['medium']   ?? 0),
            'low'     => (int)($row['low']      ?? 0),
            'filter'  => $filter_type,
            'from'    => $from_date,
            'to'      => $to_date,
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: GET SUBTASKS
// ===============================
if ($action === 'get_subtasks') {
    try {
        $task_id = (int)($_GET['task_id'] ?? 0);

        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }

        // Verify the requesting user can access this task
        if ($admin_role === 'super admin') {
            $check = $conn->prepare("SELECT id FROM admin_tasks WHERE id = ?");
            $check->bind_param('i', $task_id);
        } else {
            $check = $conn->prepare("SELECT id FROM admin_tasks WHERE id = ? AND assigned_to = ?");
            $check->bind_param('ii', $task_id, $admin_id);
        }
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("
            SELECT id, title, is_completed, created_at
            FROM admin_task_subtasks
            WHERE task_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $subtasks = [];
        while ($row = $result->fetch_assoc()) {
            $subtasks[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'subtasks' => $subtasks]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: ADD SUBTASK
// ===============================
if ($action === 'add_subtask') {
    try {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');

        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Subtask title is required']);
            exit();
        }

        if ($admin_role === 'super admin') {
            $check = $conn->prepare("SELECT id FROM admin_tasks WHERE id = ?");
            $check->bind_param('i', $task_id);
        } else {
            $check = $conn->prepare("SELECT id FROM admin_tasks WHERE id = ? AND assigned_to = ?");
            $check->bind_param('ii', $task_id, $admin_id);
        }
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Task not found or permission denied']);
            exit();
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO admin_task_subtasks (task_id, title) VALUES (?, ?)");
        $stmt->bind_param('is', $task_id, $title);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            echo json_encode([
                'success'    => true,
                'subtask_id' => $new_id,
                'message'    => 'Subtask added'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add subtask: ' . $stmt->error]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: TOGGLE SUBTASK
// ===============================
if ($action === 'toggle_subtask') {
    try {
        $subtask_id = (int)($_POST['subtask_id'] ?? 0);

        if ($subtask_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid subtask ID']);
            exit();
        }

        if ($admin_role === 'super admin') {
            $check = $conn->prepare("
                SELECT s.id, s.is_completed, s.task_id
                FROM admin_task_subtasks s
                INNER JOIN admin_tasks t ON t.id = s.task_id
                WHERE s.id = ?
            ");
            $check->bind_param('i', $subtask_id);
        } else {
            $check = $conn->prepare("
                SELECT s.id, s.is_completed, s.task_id
                FROM admin_task_subtasks s
                INNER JOIN admin_tasks t ON t.id = s.task_id
                WHERE s.id = ? AND t.assigned_to = ?
            ");
            $check->bind_param('ii', $subtask_id, $admin_id);
        }
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Subtask not found or permission denied']);
            exit();
        }

        $subtask   = $result->fetch_assoc();
        $task_id   = (int)$subtask['task_id'];
        $new_state = ($subtask['is_completed'] == 1) ? 0 : 1;
        $check->close();

        // Toggle the subtask
        $upd = $conn->prepare("UPDATE admin_task_subtasks SET is_completed = ? WHERE id = ?");
        $upd->bind_param('ii', $new_state, $subtask_id);
        $upd->execute();
        $upd->close();

        // Count totals
        $cnt = $conn->prepare("
            SELECT COUNT(*) AS total, SUM(is_completed = 1) AS done
            FROM admin_task_subtasks
            WHERE task_id = ?
        ");
        $cnt->bind_param('i', $task_id);
        $cnt->execute();
        $counts = $cnt->get_result()->fetch_assoc();
        $cnt->close();

        $total = (int)($counts['total'] ?? 0);
        $done  = (int)($counts['done']  ?? 0);

        // Auto-update parent task status
        if ($total > 0 && $done === $total) {
            $updP = $conn->prepare("
                UPDATE admin_tasks SET status = 'Completed', completed_at = NOW()
                WHERE id = ? AND status != 'Completed'
            ");
            $updP->bind_param('i', $task_id);
            $updP->execute();
            $updP->close();
            $new_parent_status = 'Completed';
        } else {
            $updP = $conn->prepare("
                UPDATE admin_tasks SET status = 'Pending', completed_at = NULL
                WHERE id = ? AND status = 'Completed'
            ");
            $updP->bind_param('i', $task_id);
            $updP->execute();
            $updP->close();
            $new_parent_status = 'Pending';
        }

        echo json_encode([
            'success'        => true,
            'is_completed'   => $new_state,
            'parent_status'  => $new_parent_status,
            'subtasks_total' => $total,
            'subtasks_done'  => $done
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// ACTION: DELETE SUBTASK
// ===============================
if ($action === 'delete_subtask') {
    try {
        $subtask_id = (int)($_POST['subtask_id'] ?? 0);

        if ($subtask_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid subtask ID']);
            exit();
        }

        if ($admin_role === 'super admin') {
            $stmt = $conn->prepare("
                DELETE s FROM admin_task_subtasks s
                INNER JOIN admin_tasks t ON t.id = s.task_id
                WHERE s.id = ?
            ");
            $stmt->bind_param('i', $subtask_id);
        } else {
            $stmt = $conn->prepare("
                DELETE s FROM admin_task_subtasks s
                INNER JOIN admin_tasks t ON t.id = s.task_id
                WHERE s.id = ? AND t.assigned_to = ?
            ");
            $stmt->bind_param('ii', $subtask_id, $admin_id);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Subtask deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Subtask not found or permission denied']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ===============================
// DEFAULT: Invalid action
// ===============================
echo json_encode(['success' => false, 'message' => 'Invalid action']);