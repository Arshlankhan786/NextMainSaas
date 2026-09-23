<?php
/**
 * Student Analytics AJAX Endpoint
 * Handles: duration filter, search, forecast, statistics
 * HOLD students are excluded from ALL queries
 */
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Auth check
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ============================================
    // ACTION: Get students by duration filter
    // ============================================
    case 'get_students':
        $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 0;
        $search   = isset($_GET['search']) ? trim($_GET['search']) : '';
        $course   = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
        $batch    = isset($_GET['batch']) ? trim($_GET['batch']) : '';

        // Build WHERE conditions - ALWAYS exclude Hold
        $conditions = ["s.status = 'Active'"];
        $types = '';
        $params = [];

        if ($duration > 0) {
            $conditions[] = "s.duration_months = ?";
            $types .= 'i';
            $params[] = $duration;
        }

        if (!empty($search)) {
            $conditions[] = "(s.full_name LIKE ? OR s.student_code LIKE ?)";
            $types .= 'ss';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($course > 0) {
            $conditions[] = "s.course_id = ?";
            $types .= 'i';
            $params[] = $course;
        }

        if (!empty($batch) && in_array($batch, ['Morning', 'Evening'])) {
            $conditions[] = "s.batch = ?";
            $types .= 's';
            $params[] = $batch;
        }

        $whereClause = implode(' AND ', $conditions);

        $query = "
            SELECT 
                s.id,
                s.student_code,
                s.full_name,
                s.photo,
                s.phone,
                s.email,
                s.enrollment_date,
                s.duration_months,
                s.total_fees,
                s.total_hold_days,
                s.batch,
                c.name AS course_name,
                cat.name AS category_name,
                COALESCE(SUM(p.amount_paid), 0) AS total_paid,
                (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) AS pending_fees,
                DATE_ADD(
                    DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH), 
                    INTERVAL COALESCE(s.total_hold_days, 0) DAY
                ) AS expected_end_date,
                DATEDIFF(
                    DATE_ADD(
                        DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH), 
                        INTERVAL COALESCE(s.total_hold_days, 0) DAY
                    ),
                    CURDATE()
                ) AS days_remaining
            FROM students s 
            JOIN courses c ON s.course_id = c.id 
            JOIN categories cat ON s.category_id = cat.id 
            LEFT JOIN payments p ON s.id = p.student_id
            WHERE {$whereClause}
            GROUP BY s.id
            ORDER BY days_remaining ASC
        ";

        $stmt = $conn->prepare($query);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate fee status
            $pending = (float)$row['pending_fees'];
            $totalFees = (float)$row['total_fees'];
            $totalPaid = (float)$row['total_paid'];

            if ($pending <= 0) {
                $fee_status = 'fully_paid';
                $fee_label = 'Fully Paid';
            } elseif ($totalPaid > 0 && $pending > 0) {
                $fee_status = 'partial';
                $fee_label = 'Partial Pending';
            } else {
                $fee_status = 'overdue';
                $fee_label = 'Overdue';
            }

            // Determine border color status
            $daysRemaining = (int)$row['days_remaining'];
            if ($daysRemaining < 0) {
                $border_status = 'expired'; // RED
                $overdue_days = abs($daysRemaining);
            } elseif ($daysRemaining <= 60) {
                $border_status = 'warning'; // ORANGE
                $overdue_days = 0;
            } else {
                $border_status = 'safe'; // GREEN
                $overdue_days = 0;
            }

            // Photo — DB stores 'uploads/students/filename.ext' relative to admin/
            // file_exists() needs path relative to THIS script (admin/ajax/), so prepend ../
            // But return raw DB path for browser since page is in admin/
            $photo = '';
            if (!empty($row['photo'])) {
                $diskPath = __DIR__ . '/../' . $row['photo'];
                if (file_exists($diskPath)) {
                    $photo = $row['photo']; // raw DB value works for browser from admin/
                }
            }

            $students[] = [
                'id'              => $row['id'],
                'student_code'    => $row['student_code'],
                'full_name'       => $row['full_name'],
                'photo'           => $photo,
                'phone'           => $row['phone'],
                'course_name'     => $row['course_name'],
                'category_name'   => $row['category_name'],
                'batch'           => $row['batch'],
                'enrollment_date' => date('d M Y', strtotime($row['enrollment_date'])),
                'end_date'        => date('d M Y', strtotime($row['expected_end_date'])),
                'days_remaining'  => $daysRemaining,
                'overdue_days'    => $overdue_days,
                'total_fees'      => number_format($totalFees, 2),
                'total_paid'      => number_format($totalPaid, 2),
                'pending_fees'    => number_format(max(0, $pending), 2),
                'fee_status'      => $fee_status,
                'fee_label'       => $fee_label,
                'border_status'   => $border_status,
                'duration_months' => $row['duration_months']
            ];
        }
        $stmt->close();

        echo json_encode(['success' => true, 'students' => $students, 'total' => count($students)]);
        break;

    // ============================================
    // ACTION: Get forecast data (next 12 months)
    // ============================================
    case 'get_forecast':
        // Get all active students with their end dates (exclude HOLD)
        $query = "
            SELECT 
                s.id,
                DATE_ADD(
                    DATE_ADD(s.enrollment_date, INTERVAL s.duration_months MONTH), 
                    INTERVAL COALESCE(s.total_hold_days, 0) DAY
                ) AS expected_end_date
            FROM students s
            WHERE s.status = 'Active'
            ORDER BY expected_end_date ASC
        ";
        $result = $conn->query($query);
        
        $endDates = [];
        $totalActive = 0;
        while ($row = $result->fetch_assoc()) {
            $endDates[] = $row['expected_end_date'];
            $totalActive++;
        }

        // Build month-wise forecast for next 12 months
        $forecast = [];
        $currentActive = $totalActive;

        for ($i = 0; $i <= 12; $i++) {
            $monthStart = date('Y-m-01', strtotime("+{$i} months"));
            $monthEnd   = date('Y-m-t', strtotime("+{$i} months"));
            $monthName  = date('F Y', strtotime("+{$i} months"));
            $monthKey   = date('Y-m', strtotime("+{$i} months"));

            // Count students expiring this month
            $expiringThisMonth = 0;
            foreach ($endDates as $endDate) {
                if ($endDate >= $monthStart && $endDate <= $monthEnd) {
                    $expiringThisMonth++;
                }
            }

            $forecast[] = [
                'month'       => $monthName,
                'month_key'   => $monthKey,
                'active'      => $currentActive,
                'expiring'    => $expiringThisMonth,
                'is_current'  => ($i === 0)
            ];

            $currentActive -= $expiringThisMonth;
            if ($currentActive < 0) $currentActive = 0;
        }

        echo json_encode(['success' => true, 'forecast' => $forecast, 'total_active' => $totalActive]);
        break;

    // ============================================
    // ACTION: Quick statistics
    // ============================================
    case 'get_stats':
        $stats = [];

        // Total active students (exclude HOLD)
        $r = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE status = 'Active'");
        $stats['total_active'] = (int)$r->fetch_assoc()['cnt'];

        // Students expiring this month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as cnt FROM students 
            WHERE status = 'Active' 
            AND DATE_ADD(
                DATE_ADD(enrollment_date, INTERVAL duration_months MONTH), 
                INTERVAL COALESCE(total_hold_days, 0) DAY
            ) BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $monthStart, $monthEnd);
        $stmt->execute();
        $stats['expiring_this_month'] = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Already overdue (expired but still active)
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as cnt FROM students 
            WHERE status = 'Active' 
            AND DATE_ADD(
                DATE_ADD(enrollment_date, INTERVAL duration_months MONTH), 
                INTERVAL COALESCE(total_hold_days, 0) DAY
            ) < ?
        ");
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $stats['already_overdue'] = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Students with pending fees
        $r = $conn->query("
            SELECT COUNT(*) as cnt FROM (
                SELECT s.id, (s.total_fees - COALESCE(SUM(p.amount_paid), 0)) AS pending
                FROM students s
                LEFT JOIN payments p ON s.id = p.student_id
                WHERE s.status = 'Active'
                GROUP BY s.id
                HAVING pending > 0
            ) AS sub
        ");
        $stats['pending_fees'] = (int)$r->fetch_assoc()['cnt'];

        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    // ============================================
    // ACTION: Get available durations
    // ============================================
    case 'get_durations':
        $r = $conn->query("
            SELECT DISTINCT duration_months 
            FROM students 
            WHERE status = 'Active'
            ORDER BY duration_months ASC
        ");
        $durations = [];
        while ($row = $r->fetch_assoc()) {
            $durations[] = (int)$row['duration_months'];
        }
        echo json_encode(['success' => true, 'durations' => $durations]);
        break;

    // ============================================
    // ACTION: Get courses list for filter
    // ============================================
    case 'get_courses':
        $r = $conn->query("
            SELECT DISTINCT c.id, c.name 
            FROM courses c
            INNER JOIN students s ON s.course_id = c.id AND s.status = 'Active'
            ORDER BY c.name ASC
        ");
        $courses = [];
        while ($row = $r->fetch_assoc()) {
            $courses[] = $row;
        }
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
exit;
