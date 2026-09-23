<?php
/**
 * Student Project Task Manager System — AJAX Handler
 * Fixed: try-catch, prepared statements, null checks, ob_start
 */

ob_start();
error_reporting(0);
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

// Only Super Admin and Admin can manage
$role = strtolower($_SESSION['admin_role'] ?? '');
if (!in_array($role, ['super admin', 'admin'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ──────────────────────────────────────────
    // CREATE PROJECT
    // ──────────────────────────────────────────
    case 'create_project':
        try {
            $student_id   = (int)($_POST['student_id'] ?? 0);
            $project_name = trim($_POST['project_name'] ?? '');
            $description  = trim($_POST['description'] ?? '');

            if (!$student_id || empty($project_name)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Student ID and project name are required']);
                exit;
            }

            $start_date = date('Y-m-d');
            // verification_status defaults to 'pending' — requires separate verification
            $stmt = $conn->prepare("INSERT INTO student_projects (student_id, project_name, description, start_date, status, verification_status) VALUES (?, ?, ?, ?, 'In Progress', 'pending')");
            $stmt->bind_param("isss", $student_id, $project_name, $description, $start_date);

            if ($stmt->execute()) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Project created successfully', 'project_id' => $stmt->insert_id]);
            } else {
                error_log('admin/student_project_actions: create_project failed — ' . $stmt->error);
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
    case 'add_day_log':
        try {
            $project_id       = (int)($_POST['project_id'] ?? 0);
            $work_description = trim($_POST['work_description'] ?? '');

            if (!$project_id || empty($work_description)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID and work description are required']);
                exit;
            }

            // Fetch project start date using prepared statement
            $pstmt = $conn->prepare("SELECT start_date FROM student_projects WHERE id = ?");
            $pstmt->bind_param("i", $project_id);
            $pstmt->execute();
            $proj_row = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();

            if (!$proj_row) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project not found']);
                exit;
            }

            $start_date = $proj_row['start_date'];
            $log_date   = date('Y-m-d');

            // Date-based day number using prepared statement
            $dn_stmt = $conn->prepare("SELECT DATEDIFF(?, ?) + 1 AS dn");
            $dn_stmt->bind_param("ss", $log_date, $start_date);
            $dn_stmt->execute();
            $next_day = max(1, (int)$dn_stmt->get_result()->fetch_assoc()['dn']);
            $dn_stmt->close();

            $stmt = $conn->prepare("INSERT INTO project_daily_logs (project_id, day_number, log_date, work_description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $project_id, $next_day, $log_date, $work_description);

            if ($stmt->execute()) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => "Day $next_day added successfully", 'day_number' => $next_day, 'log_id' => $stmt->insert_id]);
            } else {
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
    case 'edit_day_log':
        try {
            $log_id           = (int)($_POST['log_id'] ?? 0);
            $work_description = trim($_POST['work_description'] ?? '');

            if (!$log_id || empty($work_description)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Log ID and work description required']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE project_daily_logs SET work_description = ? WHERE id = ?");
            $stmt->bind_param("si", $work_description, $log_id);

            if ($stmt->execute()) {
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Log updated successfully']);
            } else {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update log']);
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
    case 'delete_day_log':
        try {
            $log_id = (int)($_POST['log_id'] ?? 0);
            if (!$log_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Log ID required']);
                exit;
            }

            // Check existence with prepared statement
            $check = $conn->prepare("SELECT id FROM project_daily_logs WHERE id = ?");
            $check->bind_param("i", $log_id);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $check->close();
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Log not found']);
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
    // MARK PROJECT COMPLETED
    // ──────────────────────────────────────────
    case 'complete_project':
        try {
            $project_id = (int)($_POST['project_id'] ?? 0);
            if (!$project_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID required']);
                exit;
            }

            $today = date('Y-m-d');
            $project_link = trim($_POST['project_link'] ?? '');
            
            // Validate URL if provided
            if (!empty($project_link) && !filter_var($project_link, FILTER_VALIDATE_URL)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Please enter a valid URL (starting with http:// or https://)']);
                exit;
            }
            
            // Get start_date with prepared statement
            $pstmt = $conn->prepare("SELECT start_date FROM student_projects WHERE id = ?");
            $pstmt->bind_param("i", $project_id);
            $pstmt->execute();
            $proj_info = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();
            
            $start_date = $proj_info ? ($proj_info['start_date'] ?? $today) : $today;

            if (!empty($project_link)) {
                // Completing sets verification_status to 'pending' for admin review
                $stmt = $conn->prepare("UPDATE student_projects SET status = 'Completed', completed_date = ?, project_link = ?, verification_status = 'pending' WHERE id = ?");
                $stmt->bind_param("ssi", $today, $project_link, $project_id);
            } else {
                // Completing sets verification_status to 'pending' for admin review
                $stmt = $conn->prepare("UPDATE student_projects SET status = 'Completed', completed_date = ?, verification_status = 'pending' WHERE id = ?");
                $stmt->bind_param("si", $today, $project_id);
            }

            if ($stmt->execute()) {
                // Add completion log entry
                $dn_stmt = $conn->prepare("SELECT DATEDIFF(?, ?) + 1 AS dn");
                $dn_stmt->bind_param("ss", $today, $start_date);
                $dn_stmt->execute();
                $day_no = max(1, (int)$dn_stmt->get_result()->fetch_assoc()['dn']);
                $dn_stmt->close();

                // Check for duplicate
                $dup = $conn->prepare("SELECT id FROM project_daily_logs WHERE project_id = ? AND log_date = ?");
                $dup->bind_param("is", $project_id, $today);
                $dup->execute();
                if ($dup->get_result()->num_rows === 0) {
                    $note = '[COMPLETED] Project marked as completed by admin';
                    $s2 = $conn->prepare("INSERT INTO project_daily_logs (project_id, day_number, log_date, work_description) VALUES (?, ?, ?, ?)");
                    $s2->bind_param("iiss", $project_id, $day_no, $today, $note);
                    $s2->execute();
                    $s2->close();
                }
                $dup->close();

                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Project marked as completed']);
            } else {
                error_log('admin/student_project_actions: complete_project UPDATE failed — ' . $stmt->error);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update project']);
            }
            $stmt->close();
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ──────────────────────────────────────────
    // DELETE PROJECT (and its logs)
    // ──────────────────────────────────────────
    case 'delete_project':
        try {
            $project_id = (int)($_POST['project_id'] ?? 0);
            if (!$project_id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Project ID required']);
                exit;
            }

            $del1 = $conn->prepare("DELETE FROM project_daily_logs WHERE project_id = ?");
            $del1->bind_param("i", $project_id);
            $del1->execute();
            $del1->close();

            $del2 = $conn->prepare("DELETE FROM student_projects WHERE id = ?");
            $del2->bind_param("i", $project_id);
            $del2->execute();
            $del2->close();

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Project and all logs deleted']);
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
