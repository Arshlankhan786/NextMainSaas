<?php
/**
 * Admin AJAX — Project Management & Verification
 * 
 * Handles: add, update, delete, verify, reject, get projects
 * Verification: Only Admin/Super Admin can verify/reject projects.
 * Points: Awarded ONLY after admin verification.
 *   - Web Development/Developer course projects = 12.5 points
 *   - All other course projects (Graphic/Digital) = 5 points
 * Duplicate prevention: points_awarded tracked per project.
 */

ob_start();
error_reporting(0);
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
requireLogin();

header('Content-Type: application/json');

$action     = $_POST['action'] ?? '';
$student_id = (int)($_POST['student_id'] ?? 0);

// Helper: check if current admin is Admin or Super Admin
$admin_role = strtolower($_SESSION['admin_role'] ?? '');
$admin_id   = (int)($_SESSION['admin_id'] ?? 0);
$isAdminOrSA = in_array($admin_role, ['super admin', 'admin']);


/* ===============================
   ADD PROJECT
================================*/
if ($action === 'add_project') {
    try {
        $project_name = trim(sanitize($_POST['project_name'] ?? ''));
        $description  = trim(sanitize($_POST['description'] ?? ''));
        $start_date   = $_POST['start_date'] ?? null;
        $end_date     = $_POST['end_date'] ?? null;
        $status       = $_POST['status'] ?? 'Not Started';

        if (empty($project_name)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Project name is required']);
            exit;
        }
        if ($student_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
            exit;
        }

        // verification_status defaults to 'pending' in the database
        $stmt = $conn->prepare("
            INSERT INTO student_projects 
            (student_id, project_name, description, start_date, end_date, status, verification_status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isssss", $student_id, $project_name, $description, $start_date, $end_date, $status);
        $result = $stmt->execute();
        $stmt->close();

        ob_end_clean();
        echo json_encode(['success' => $result, 'message' => $result ? 'Project added (pending verification)' : 'Failed to add project']);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   UPDATE PROJECT
================================*/
if ($action === 'update_project') {
    try {
        $project_id   = (int)($_POST['project_id'] ?? 0);
        $project_name = trim(sanitize($_POST['project_name'] ?? ''));
        $description  = trim(sanitize($_POST['description'] ?? ''));
        $start_date   = $_POST['start_date'] ?? null;
        $end_date     = $_POST['end_date'] ?? null;
        $status       = $_POST['status'] ?? 'Not Started';
        $remarks      = trim(sanitize($_POST['remarks'] ?? ''));

        if ($project_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
            exit;
        }
        if (empty($project_name)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Project name is required']);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE student_projects 
            SET project_name=?, description=?, start_date=?, end_date=?, status=?, remarks=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssssssi", $project_name, $description, $start_date, $end_date, $status, $remarks, $project_id);
        $result = $stmt->execute();
        $stmt->close();

        ob_end_clean();
        echo json_encode(['success' => $result, 'message' => $result ? 'Project updated' : 'Failed to update project']);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   DELETE PROJECT
================================*/
if ($action === 'delete_project') {
    try {
        $project_id = (int)($_POST['project_id'] ?? 0);

        if ($project_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
            exit;
        }

        // If project was verified and had points, subtract them from student
        $proj = $conn->prepare("SELECT student_id, points_awarded FROM student_projects WHERE id = ?");
        $proj->bind_param("i", $project_id);
        $proj->execute();
        $proj_data = $proj->get_result()->fetch_assoc();
        $proj->close();

        if ($proj_data && $proj_data['points_awarded'] > 0) {
            $sub_stmt = $conn->prepare("UPDATE students SET points = GREATEST(0, points - ?) WHERE id = ?");
            $sub_stmt->bind_param("di", $proj_data['points_awarded'], $proj_data['student_id']);
            $sub_stmt->execute();
            $sub_stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM student_projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $result = $stmt->execute();
        $stmt->close();

        ob_end_clean();
        echo json_encode(['success' => $result, 'message' => $result ? 'Project deleted' : 'Failed to delete project']);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   VERIFY PROJECT (Admin Only)
   Awards points based on course:
   - Web Development/Developer = 12.5 points
   - All others (Graphic/Digital) = 5 points
   Prevents duplicate point awards.
================================*/
if ($action === 'verify_project') {
    try {
        // Access control: only Admin or Super Admin
        if (!$isAdminOrSA) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Access denied. Only Admin/Super Admin can verify projects.']);
            exit;
        }

        $project_id = (int)($_POST['project_id'] ?? 0);

        if ($project_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
            exit;
        }

        // Fetch project details with student's course category
        $check = $conn->prepare("
            SELECT sp.id, sp.student_id, sp.verification_status, sp.points_awarded,
                   cat.name AS category_name
            FROM student_projects sp
            JOIN students s ON sp.student_id = s.id
            JOIN categories cat ON s.category_id = cat.id
            WHERE sp.id = ?
        ");
        $check->bind_param("i", $project_id);
        $check->execute();
        $project = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$project) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Project not found']);
            exit;
        }

        // Prevent duplicate verification — if already verified with points, skip
        if ($project['verification_status'] === 'verified' && $project['points_awarded'] > 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Project already verified. Points already awarded.']);
            exit;
        }

        // Determine points: Web Development/Developer = 12.5, others = 5
        $is_web_dev = (stripos($project['category_name'], 'Web Development') !== false);
        $points = $is_web_dev ? 12.5 : 5;

        // Begin transaction for atomicity
        $conn->begin_transaction();

        // Update project verification status
        $upd = $conn->prepare("
            UPDATE student_projects 
            SET verification_status = 'verified', 
                verified_by = ?, 
                verified_at = NOW(), 
                points_awarded = ?,
                status = 'Completed'
            WHERE id = ?
        ");
        $upd->bind_param("idi", $admin_id, $points, $project_id);
        $upd->execute();
        $upd->close();

        // Award points to the student (atomically add)
        $pts = $conn->prepare("UPDATE students SET points = points + ? WHERE id = ?");
        $pts->bind_param("di", $points, $project['student_id']);
        $pts->execute();
        $pts->close();

        // Send notification to student
        $notif_title = 'Project Verified!';
        $notif_msg   = "Your project has been verified by admin. You earned $points points!";
        $notif_type  = 'success';
        $notif = $conn->prepare("
            INSERT INTO student_notifications (student_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        $notif->bind_param("isss", $project['student_id'], $notif_title, $notif_msg, $notif_type);
        $notif->execute();
        $notif->close();

        $conn->commit();

        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => "Project verified! $points points awarded.",
            'points_awarded' => $points
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   REJECT PROJECT (Admin Only)
================================*/
if ($action === 'reject_project') {
    try {
        // Access control
        if (!$isAdminOrSA) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Access denied. Only Admin/Super Admin can reject projects.']);
            exit;
        }

        $project_id = (int)($_POST['project_id'] ?? 0);
        $reason     = trim($_POST['rejection_reason'] ?? '');

        if ($project_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
            exit;
        }

        // Check if project was previously verified with points
        $check = $conn->prepare("SELECT student_id, points_awarded, verification_status FROM student_projects WHERE id = ?");
        $check->bind_param("i", $project_id);
        $check->execute();
        $project = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$project) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Project not found']);
            exit;
        }

        $conn->begin_transaction();

        // If previously verified, subtract the points
        if ($project['verification_status'] === 'verified' && $project['points_awarded'] > 0) {
            $sub = $conn->prepare("UPDATE students SET points = GREATEST(0, points - ?) WHERE id = ?");
            $sub->bind_param("di", $project['points_awarded'], $project['student_id']);
            $sub->execute();
            $sub->close();
        }

        // Update project to rejected
        $upd = $conn->prepare("
            UPDATE student_projects 
            SET verification_status = 'rejected', 
                verified_by = ?, 
                verified_at = NOW(), 
                points_awarded = 0,
                rejection_reason = ?
            WHERE id = ?
        ");
        $upd->bind_param("isi", $admin_id, $reason, $project_id);
        $upd->execute();
        $upd->close();

        // Notify student
        $notif_title = 'Project Rejected';
        $notif_msg   = 'Your project has been rejected by admin.' . (!empty($reason) ? " Reason: $reason" : '');
        $notif_type  = 'warning';
        $notif = $conn->prepare("
            INSERT INTO student_notifications (student_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        $notif->bind_param("isss", $project['student_id'], $notif_title, $notif_msg, $notif_type);
        $notif->execute();
        $notif->close();

        $conn->commit();

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Project rejected.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   GET PROJECTS
================================*/
if ($action === 'get_projects') {
    try {
        if ($student_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT sp.*, a.full_name AS verified_by_name
            FROM student_projects sp
            LEFT JOIN admins a ON sp.verified_by = a.id
            WHERE sp.student_id = ? 
            ORDER BY sp.created_at DESC
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        ob_end_clean();
        echo json_encode(['success' => true, 'projects' => $data]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   GET PENDING PROJECTS (for admin verification panel)
================================*/
if ($action === 'get_pending_projects') {
    try {
        if (!$isAdminOrSA) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $filter = $_POST['filter'] ?? 'pending';
        $allowed_filters = ['pending', 'verified', 'rejected', 'all'];
        if (!in_array($filter, $allowed_filters)) $filter = 'pending';

        $where = '';
        if ($filter !== 'all') {
            $where = "WHERE sp.verification_status = '$filter'";
        }

        $result = $conn->query("
            SELECT sp.*, 
                   s.full_name AS student_name, 
                   s.student_code,
                   c.name AS course_name,
                   cat.name AS category_name,
                   a.full_name AS verified_by_name
            FROM student_projects sp
            JOIN students s ON sp.student_id = s.id
            JOIN courses c ON s.course_id = c.id
            JOIN categories cat ON s.category_id = cat.id
            LEFT JOIN admins a ON sp.verified_by = a.id
            $where
            ORDER BY 
                FIELD(sp.verification_status, 'pending', 'rejected', 'verified'),
                sp.created_at DESC
        ");

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'projects' => $data]);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


/* ===============================
   FALLBACK
================================*/
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
