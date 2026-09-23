<?php
/**
 * Student Project Task Manager — Student AJAX Handler
 * Fixed: all raw SQL → prepared statements, try-catch, ob_start
 */

ob_start();
error_reporting(0);
require_once __DIR__ . '/../../admin/config/database.php';
require_once __DIR__ . '/../student_auth.php';
requireStudentLogin();

header('Content-Type: application/json');

$student = getCurrentStudent();
$sid     = (int)$student['id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ──────────────────────────────────────────
    // CREATE PROJECT
    // ──────────────────────────────────────────
    case 'create_project':
        try {
            $project_name = trim($_POST['project_name'] ?? '');
            $description  = trim($_POST['description'] ?? '');

            if (empty($project_name)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project name is required']);
                exit;
            }

            $start_date = date('Y-m-d');
            // verification_status defaults to 'pending' — no auto-verification
            $stmt = $conn->prepare("INSERT INTO student_projects (student_id, project_name, description, start_date, status, verification_status) VALUES (?, ?, ?, ?, 'In Progress', 'pending')");
            $stmt->bind_param("isss", $sid, $project_name, $description, $start_date);

            if ($stmt->execute()) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Project created successfully', 'project_id' => $stmt->insert_id]);
            } else {
                error_log('student/project_actions: create_project failed — ' . $stmt->error);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to create project']);
            }
            $stmt->close();
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // ADD DAY LOG
    // ──────────────────────────────────────────
    case 'add_day':
        try {
            $project_id       = (int)($_POST['project_id'] ?? 0);
            $work_description = trim($_POST['work_description'] ?? '');

            if (!$project_id || empty($work_description)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID and work description required']);
                exit;
            }

            // Verify ownership with prepared statement
            $pstmt = $conn->prepare("SELECT id, start_date FROM student_projects WHERE id = ? AND student_id = ? AND status = 'In Progress'");
            $pstmt->bind_param("ii", $project_id, $sid);
            $pstmt->execute();
            $proj_row = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();

            if (!$proj_row) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project not found or not active']);
                exit;
            }

            $start_date = $proj_row['start_date'];
            $log_date   = date('Y-m-d');

            // Duplicate check
            $dup = $conn->prepare("SELECT id FROM project_daily_logs WHERE project_id = ? AND log_date = ?");
            $dup->bind_param("is", $project_id, $log_date);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                $dup->close();
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'You already added an update for today. Use Edit to change it.']);
                exit;
            }
            $dup->close();

            // Day number
            $dn_stmt = $conn->prepare("SELECT DATEDIFF(?, ?) + 1 AS dn");
            $dn_stmt->bind_param("ss", $log_date, $start_date);
            $dn_stmt->execute();
            $next_day = max(1, (int)$dn_stmt->get_result()->fetch_assoc()['dn']);
            $dn_stmt->close();

            $stmt = $conn->prepare("INSERT INTO project_daily_logs (project_id, day_number, log_date, work_description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $project_id, $next_day, $log_date, $work_description);

            if ($stmt->execute()) {
                ob_end_clean();
                echo json_encode([
                    'success'    => true,
                    'message'    => "Day $next_day added!",
                    'day_number' => $next_day,
                    'log_date'   => $log_date,
                    'log_id'     => $stmt->insert_id
                ]);
            } else {
                error_log('student/project_actions: add_day INSERT failed — ' . $stmt->error);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to add day log']);
            }
            $stmt->close();
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // EDIT DAY LOG
    // ──────────────────────────────────────────
    case 'edit_day':
        try {
            $log_id           = (int)($_POST['log_id'] ?? 0);
            $work_description = trim($_POST['work_description'] ?? '');

            if (!$log_id || empty($work_description)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Log ID and description required']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE project_daily_logs dl JOIN student_projects sp ON dl.project_id = sp.id SET dl.work_description = ? WHERE dl.id = ? AND sp.student_id = ?");
            $stmt->bind_param("sii", $work_description, $log_id, $sid);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Log updated']);
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update or not authorized']);
            }
            $stmt->close();
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // DELETE DAY LOG
    // ──────────────────────────────────────────
    case 'delete_day':
        try {
            $log_id = (int)($_POST['log_id'] ?? 0);
            if (!$log_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Log ID required']);
                exit;
            }

            // Verify ownership
            $check = $conn->prepare("SELECT dl.id FROM project_daily_logs dl JOIN student_projects sp ON dl.project_id = sp.id WHERE dl.id = ? AND sp.student_id = ?");
            $check->bind_param("ii", $log_id, $sid);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $check->close();
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Not found or not authorized']);
                exit;
            }
            $check->close();

            $del = $conn->prepare("DELETE FROM project_daily_logs WHERE id = ?");
            $del->bind_param("i", $log_id);
            $del->execute();
            $del->close();

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Log deleted']);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // GET ALL LOGS FOR A PROJECT
    // ──────────────────────────────────────────
    case 'get_project_logs':
        try {
            $project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
            if (!$project_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID required']);
                exit;
            }

            // Verify ownership
            $pstmt = $conn->prepare("SELECT id, project_name, start_date FROM student_projects WHERE id = ? AND student_id = ?");
            $pstmt->bind_param("ii", $project_id, $sid);
            $pstmt->execute();
            $proj_row = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();

            if (!$proj_row) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Not found or not authorized']);
                exit;
            }

            $lstmt = $conn->prepare("SELECT id, day_number, log_date, work_description, created_at FROM project_daily_logs WHERE project_id = ? ORDER BY log_date DESC");
            $lstmt->bind_param("i", $project_id);
            $lstmt->execute();
            $logs_result = $lstmt->get_result();

            $logs = [];
            while ($row = $logs_result->fetch_assoc()) {
                $logs[] = [
                    'id'               => $row['id'],
                    'day_number'       => $row['day_number'],
                    'log_date'         => $row['log_date'],
                    'log_date_display' => date('d-m-Y', strtotime($row['log_date'])),
                    'work_description' => $row['work_description'],
                ];
            }
            $lstmt->close();

            ob_end_clean();
            echo json_encode([
                'success'      => true,
                'project_name' => $proj_row['project_name'],
                'start_date'   => $proj_row['start_date'],
                'logs'         => $logs
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // COMPLETE PROJECT
    // ──────────────────────────────────────────
    case 'complete_project':
        try {
            $project_id = (int)($_POST['project_id'] ?? 0);
            if (!$project_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID required']);
                exit;
            }

            $project_link = trim($_POST['project_link'] ?? '');
            if (empty($project_link)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project link is required to complete a project']);
                exit;
            }
            if (!filter_var($project_link, FILTER_VALIDATE_URL)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Please enter a valid URL (starting with http:// or https://)']);
                exit;
            }

            // Verify ownership
            $pstmt = $conn->prepare("SELECT id, start_date FROM student_projects WHERE id = ? AND student_id = ? AND status = 'In Progress'");
            $pstmt->bind_param("ii", $project_id, $sid);
            $pstmt->execute();
            $proj_row = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();

            if (!$proj_row) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project not found or already completed']);
                exit;
            }

            $start_date = $proj_row['start_date'];
            $today      = date('Y-m-d');

            // Day number
            $dn_stmt = $conn->prepare("SELECT DATEDIFF(?, ?) + 1 AS dn");
            $dn_stmt->bind_param("ss", $today, $start_date);
            $dn_stmt->execute();
            $day_number = max(1, (int)$dn_stmt->get_result()->fetch_assoc()['dn']);
            $dn_stmt->close();

            // Mark completed + save project link + set verification_status to 'pending'
            // NOTE: verification_status stays 'pending' — admin must verify to award points
            $upd = $conn->prepare("UPDATE student_projects SET status = 'Completed', completed_date = ?, project_link = ?, verification_status = 'pending' WHERE id = ?");
            $upd->bind_param("ssi", $today, $project_link, $project_id);
            if (!$upd->execute()) {
                error_log('student/project_actions: complete_project UPDATE failed — ' . $upd->error);
            }
            $upd->close();

            // Log completion entry
            $dup = $conn->prepare("SELECT id FROM project_daily_logs WHERE project_id = ? AND log_date = ?");
            $dup->bind_param("is", $project_id, $today);
            $dup->execute();
            if ($dup->get_result()->num_rows === 0) {
                $completion_note = '[COMPLETED] Project marked as completed';
                $ins = $conn->prepare("INSERT INTO project_daily_logs (project_id, day_number, log_date, work_description) VALUES (?, ?, ?, ?)");
                $ins->bind_param("iiss", $project_id, $day_number, $today, $completion_note);
                $ins->execute();
                $ins->close();
            }
            $dup->close();

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Project marked as completed!']);
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
