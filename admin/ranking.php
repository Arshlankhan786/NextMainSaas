<?php
include 'includes/header.php';

// Filters
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_course = isset($_GET['course']) ? (int)$_GET['course'] : 0;
$filter_batch = isset($_GET['batch']) ? $_GET['batch'] : '';

$current_month_start = date('Y-m-01', strtotime($filter_month));
$current_month_end = date('Y-m-t', strtotime($filter_month));

// Build WHERE clause for filters
$where_clauses = ["s.status = 'Active'", "s.login_enabled = 1"];
if ($filter_course > 0) {
    $where_clauses[] = "s.course_id = $filter_course";
}
if (!empty($filter_batch)) {
    $where_clauses[] = "s.batch = '" . $conn->real_escape_string($filter_batch) . "'";
}
$where_sql = implode(' AND ', $where_clauses);

// Get ranked students with detailed breakdown
// $ranked_students = $conn->query("
//     SELECT 
//         s.id,
//         s.student_code,
//         s.full_name,
//         s.photo,
//         c.name as course_name,
//         cat.name as category_name,
//         s.batch,
        
//         -- Payment points (10 if paid this month)
//         (CASE 
//             WHEN EXISTS(
//                 SELECT 1 FROM payments p 
//                 WHERE p.student_id = s.id 
//                 AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
//             ) THEN 10 ELSE 0 
//         END) as payment_points,
        
//         -- Project points (15 for web dev, 6 for others)
//         (SELECT COUNT(*) 
//          FROM student_projects sp 
//          WHERE sp.student_id = s.id 
//          AND sp.status = 'Completed'
//         ) * (CASE WHEN cat.name = 'Web Development' THEN 15 ELSE 6 END) as project_points,
        
//         -- Attendance points (1 per day)
//         (SELECT COUNT(*) 
//          FROM student_attendance sa 
//          WHERE sa.student_id = s.id 
//          AND sa.status = 'Present'
//          AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
//         ) as attendance_points,
        
//         -- Topic completion points (5 per topic)
//         (SELECT COUNT(*) 
//          FROM course_topics ct 
//          WHERE ct.student_id = s.id 
//          AND ct.status = 'Completed'
//          AND ct.completed_date BETWEEN '$current_month_start' AND '$current_month_end'
//         ) * 5 as topic_points,
        
//         -- Manual points
//         COALESCE((SELECT SUM(points) 
//          FROM student_manual_points smp 
//          WHERE smp.student_id = s.id
//         ), 0) as manual_points,
        
//         -- Total points
//         (
//             (CASE 
//                 WHEN EXISTS(
//                     SELECT 1 FROM payments p 
//                     WHERE p.student_id = s.id 
//                     AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
//                 ) THEN 10 ELSE 0 
//             END) +
//             (SELECT COUNT(*) 
//              FROM student_projects sp 
//              WHERE sp.student_id = s.id 
//              AND sp.status = 'Completed'
//             ) * (CASE WHEN cat.name = 'Web Development' THEN 15 ELSE 6 END) +
//             (SELECT COUNT(*) 
//              FROM student_attendance sa 
//              WHERE sa.student_id = s.id 
//              AND sa.status = 'Present'
//              AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
//             ) +
//             (SELECT COUNT(*) 
//              FROM course_topics ct 
//              WHERE ct.student_id = s.id 
//              AND ct.status = 'Completed'
//              AND ct.completed_date BETWEEN '$current_month_start' AND '$current_month_end'
//             ) * 5 +
//             COALESCE((SELECT SUM(points) 
//              FROM student_manual_points smp 
//              WHERE smp.student_id = s.id
//             ), 0)
//         ) as total_points,
        
//         -- Completed project count
//         (SELECT COUNT(*) 
//          FROM student_projects sp 
//          WHERE sp.student_id = s.id 
//          AND sp.status = 'Completed'
//         ) as completed_projects,
        
//         -- Attendance count
//         (SELECT COUNT(*) 
//          FROM student_attendance sa 
//          WHERE sa.student_id = s.id 
//          AND sa.status = 'Present'
//          AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
//         ) as attendance_count,
        
//         -- Topic count
//         (SELECT COUNT(*) 
//          FROM course_topics ct 
//          WHERE ct.student_id = s.id 
//          AND ct.status = 'Completed'
//          AND ct.completed_date BETWEEN '$current_month_start' AND '$current_month_end'
//         ) as completed_topics
        
//     FROM students s
//     JOIN courses c ON s.course_id = c.id
//     JOIN categories cat ON s.category_id = cat.id
//     WHERE $where_sql
//     ORDER BY total_points DESC, s.full_name ASC
// ");
$ranked_students = $conn->query("
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.photo,
        c.name as course_name,
        cat.name as category_name,
        s.batch,

        -- PAYMENT POINTS (Monthly)
        (CASE 
            WHEN EXISTS(
                SELECT 1 FROM payments p 
                WHERE p.student_id = s.id 
                AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
            ) THEN 10 ELSE 0 
        END) as payment_points,

        -- PROJECT COUNT (Monthly — only VERIFIED projects)
        (SELECT COUNT(*) 
         FROM student_projects sp 
         WHERE sp.student_id = s.id 
         AND sp.verification_status = 'verified'
         AND sp.verified_at BETWEEN '$current_month_start' AND '$current_month_end'
        ) as completed_projects,

        -- PROJECT POINTS (Monthly — sum of points_awarded for verified projects)
        COALESCE((
            SELECT SUM(sp.points_awarded)
            FROM student_projects sp
            WHERE sp.student_id = s.id
            AND sp.verification_status = 'verified'
            AND sp.verified_at BETWEEN '$current_month_start' AND '$current_month_end'
        ), 0) as project_points,

        -- ATTENDANCE POINTS (Monthly: Present non-Sunday = +2, Absent non-Sunday = -1, Sunday = 0)
        COALESCE((
            SELECT SUM(
                CASE 
                    WHEN sa.status = 'Present' AND DAYOFWEEK(sa.attendance_date) != 1 THEN 2
                    WHEN sa.status = 'Absent' AND DAYOFWEEK(sa.attendance_date) != 1 THEN -1
                    ELSE 0
                END
            )
            FROM student_attendance sa
            WHERE sa.student_id = s.id
            AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
        ), 0) as attendance_points,

        -- MANUAL POINTS (Monthly)
        COALESCE((
            SELECT SUM(points) 
            FROM student_manual_points smp 
            WHERE smp.student_id = s.id
            AND smp.created_at BETWEEN '$current_month_start' AND '$current_month_end'
        ), 0) as manual_points,

        -- QUIZ POINTS (Monthly — from test_results.points_awarded)
        COALESCE((
            SELECT SUM(tr.points_awarded)
            FROM test_results tr
            WHERE tr.student_id = s.id
            AND tr.completed_at BETWEEN '$current_month_start' AND '$current_month_end'
        ), 0) as quiz_points,

        -- TOTAL POINTS (Final Monthly Calculation)
        (
            (CASE 
                WHEN EXISTS(
                    SELECT 1 FROM payments p 
                    WHERE p.student_id = s.id 
                    AND p.payment_date BETWEEN '$current_month_start' AND '$current_month_end'
                ) THEN 10 ELSE 0 
            END)
            +
            COALESCE((
                SELECT SUM(sp.points_awarded)
                FROM student_projects sp
                WHERE sp.student_id = s.id
                AND sp.verification_status = 'verified'
                AND sp.verified_at BETWEEN '$current_month_start' AND '$current_month_end'
            ), 0)
            +
            COALESCE((
                SELECT SUM(
                    CASE 
                        WHEN sa.status = 'Present' AND DAYOFWEEK(sa.attendance_date) != 1 THEN 2
                        WHEN sa.status = 'Absent' AND DAYOFWEEK(sa.attendance_date) != 1 THEN -1
                        ELSE 0
                    END
                )
                FROM student_attendance sa
                WHERE sa.student_id = s.id
                AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
            ), 0)
            +
            COALESCE((
                SELECT SUM(points) 
                FROM student_manual_points smp 
                WHERE smp.student_id = s.id
                AND smp.created_at BETWEEN '$current_month_start' AND '$current_month_end'
            ), 0)
            +
            COALESCE((
                SELECT SUM(tr.points_awarded)
                FROM test_results tr
                WHERE tr.student_id = s.id
                AND tr.completed_at BETWEEN '$current_month_start' AND '$current_month_end'
            ), 0)
        ) as total_points,

        -- ATTENDANCE COUNT (for display)
        (SELECT COUNT(*) 
         FROM student_attendance sa 
         WHERE sa.student_id = s.id 
         AND sa.status = 'Present'
         AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
        ) as attendance_count

    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    WHERE $where_sql
   ORDER BY 
    total_points DESC,
    (
        SELECT MIN(CONCAT(sa.attendance_date,' ',sa.check_in_time))
        FROM student_attendance sa
        WHERE sa.student_id = s.id
        AND sa.status = 'Present'
        AND sa.attendance_date BETWEEN '$current_month_start' AND '$current_month_end'
    ) ASC,
    s.full_name ASC
");

// Get courses for filter
$courses = $conn->query("SELECT id, name FROM courses WHERE status = 'Active' ORDER BY name");

// Get stats
$total_students = $ranked_students->num_rows;
$top_scorer = null;
if ($total_students > 0) {
    $ranked_students->data_seek(0);
    $top_scorer = $ranked_students->fetch_assoc();
    $ranked_students->data_seek(0);
}
?>

<div class="page-header">
    <h2><i class="fas fa-trophy text-purple"></i> Student Ranking System</h2>
    <p class="text-muted mb-0">Points-based performance tracking</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="table-card mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Month</label>
            <input type="month" class="form-control" name="month" value="<?php echo $filter_month; ?>" max="<?php echo date('Y-m'); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Course</label>
            <select class="form-select" name="course">
                <option value="">All Courses</option>
                <?php while ($course = $courses->fetch_assoc()): ?>
                <option value="<?php echo $course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($course['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Batch</label>
            <select class="form-select" name="batch">
                <option value="">All Batches</option>
                <option value="Morning" <?php echo $filter_batch === 'Morning' ? 'selected' : ''; ?>>Morning</option>
                <option value="Evening" <?php echo $filter_batch === 'Evening' ? 'selected' : ''; ?>>Evening</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-purple w-100">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h3 class="text-purple mb-0"><?php echo $total_students; ?></h3>
                <small class="text-muted">Total Students</small>
            </div>
        </div>
    </div>
    
    <?php if ($top_scorer): ?>
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h3 class="text-success mb-0"><?php echo $top_scorer['total_points']; ?></h3>
                <small class="text-muted">Highest Score</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <h5 class="mb-0"><i class="fas fa-crown text-warning"></i> <?php echo htmlspecialchars($top_scorer['full_name']); ?></h5>
                <small class="text-muted">Top Performer</small>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Point Legend -->
<div class="table-card mb-4">
    <h6 class="text-purple mb-3"><i class="fas fa-info-circle"></i> Point System</h6>
    <div class="row">
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-success me-2">10</span>
                <small>Monthly Fee Payment</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-info me-2">12.5/5</span>
                <small>Per Verified Project (Dev/Other)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-warning me-2">+2/−1</span>
                <small>Attendance (Present/Absent, Sun excl.)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-primary me-2">5</span>
                <small>Per Topic Completed</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-purple me-2">+10/−1</span>
                <small>Quiz Score Points</small>
            </div>
        </div>
    </div>
</div>

<!-- Ranking Table -->
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-purple mb-0"><i class="fas fa-medal"></i> Student Rankings</h5>
        <button class="btn btn-sm btn-secondary" onclick="exportTableToCSV('rankingTable', 'student_ranking_<?php echo $filter_month; ?>.csv')">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
    
    <?php if ($total_students > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover" id="rankingTable">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Payment</th>
                    <th>Projects</th>
                    <th>Attendance</th>
                  
                    <th>Manual</th>
                    <th>Quiz</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                while ($student = $ranked_students->fetch_assoc()): 
                    $rank_badge = 'secondary';
                    $rank_icon = '';
                    if ($rank == 1) {
                        $rank_badge = 'warning';
                        $rank_icon = '<i class="fas fa-crown"></i> ';
                    } elseif ($rank == 2) {
                        $rank_badge = 'secondary';
                        $rank_icon = '<i class="fas fa-medal"></i> ';
                    } elseif ($rank == 3) {
                        $rank_badge = 'bronze';
                        $rank_icon = '<i class="fas fa-medal"></i> ';
                    } elseif ($rank <= 10) {
                        $rank_badge = 'success';
                    }
                    
                    $has_photo = !empty($student['photo']) && file_exists($student['photo']);
                ?>
                <tr>
                    <td>
                        <span class="badge bg-<?php echo $rank_badge; ?> fs-6">
                            <?php echo $rank_icon . '' . $rank; ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($has_photo): ?>
                                <img src="<?php echo htmlspecialchars($student['photo']); ?>" 
                                     class="rounded-circle me-2" 
                                     style="width: 35px; height: 35px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                     style="width: 35px; height: 35px; font-size: 14px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="text-decoration-none">
                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                </a>
                                <br><small class="text-muted"><?php echo $student['student_code']; ?></small>
                            </div>
                        </div>
                    </td>
                    <td><small><?php echo htmlspecialchars($student['course_name']); ?></small></td>
                    <td>
                        <span class="badge <?php echo $student['batch'] === 'Morning' ? 'bg-warning' : 'bg-info'; ?>">
                            <?php echo $student['batch']; ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-success">
                            <?php echo $student['payment_points']; ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-info">
                            <?php echo $student['project_points']; ?>
                        </span>
                        <small class="text-muted">(<?php echo $student['completed_projects']; ?>)</small>
                    </td>
                    <td>
                        <span class="badge bg-warning">
                            <?php echo $student['attendance_points']; ?>
                        </span>
                        <small class="text-muted">(<?php echo $student['attendance_count']; ?>d)</small>
                    </td>
                    <!--<td>-->
                    <!--    <span class="badge bg-primary">-->
                    <!--        <?php// echo $student['topic_points']; ?>-->
                    <!--    </span>-->
                    <!--    <small class="text-muted">(<?php // echo $student['completed_topics']; ?>)</small>-->
                    <!--</td>-->
                    <td>
                        <?php if ($student['manual_points'] != 0): ?>
                        <span class="badge bg-<?php echo $student['manual_points'] > 0 ? 'success' : 'danger'; ?>">
                            <?php echo $student['manual_points'] > 0 ? '+' : ''; ?><?php echo $student['manual_points']; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($student['quiz_points'] != 0): ?>
                        <span class="badge bg-<?php echo $student['quiz_points'] > 0 ? 'success' : 'danger'; ?>">
                            <?php echo $student['quiz_points'] > 0 ? '+' : ''; ?><?php echo $student['quiz_points']; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="fs-5 text-purple"><?php echo $student['total_points']; ?></strong>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="showPointsBreakdown(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                        <!--<a href="student_details.php?id=<?php // echo $student['id']; ?>" class="btn btn-sm btn-info">-->
                        <!--    <i class="fas fa-eye"></i>-->
                        <!--</a>-->
                    </td>
                </tr>
                <?php 
                $rank++;
                endwhile; 
                ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No students found matching the filters.
    </div>
    <?php endif; ?>
</div>

<!-- Points Breakdown Modal -->
<div class="modal fade" id="pointsBreakdownModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-bar"></i> Points Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pointsBreakdownContent">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
</div>

<style>
.badge.bg-bronze {
    background: linear-gradient(135deg, #cd7f32, #b8722c) !important;
    color: white;
}
</style>

<script>
function showPointsBreakdown(student) {
    const content = `
        <div class="text-center mb-3">
            <h4>${student.full_name}</h4>
            <p class="text-muted">${student.student_code}</p>
        </div>
        
        <div class="list-group">
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-money-bill-wave text-success"></i> Payment Points
                </div>
                <span class="badge bg-success">${student.payment_points}</span>
            </div>
            
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-project-diagram text-info"></i> Project Points
                    <small class="text-muted">(${student.completed_projects} projects)</small>
                </div>
                <span class="badge bg-info">${student.project_points}</span>
            </div>
            
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-calendar-check text-warning"></i> Attendance Points
                    <small class="text-muted">(${student.attendance_count} days)</small>
                </div>
                <span class="badge bg-warning">${student.attendance_points}</span>
            </div>
            
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list-check text-primary"></i> Topic Points
                    <small class="text-muted">(${student.completed_topics} topics)</small>
                </div>
                <span class="badge bg-primary">${student.topic_points}</span>
            </div>
            
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-star text-secondary"></i> Manual Points
                </div>
                <span class="badge bg-${student.manual_points >= 0 ? 'success' : 'danger'}">
                    ${student.manual_points > 0 ? '+' : ''}${student.manual_points}
                </span>
            </div>
            
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-clipboard-check text-info"></i> Quiz Points
                </div>
                <span class="badge bg-${(student.quiz_points || 0) >= 0 ? 'success' : 'danger'}">
                    ${(student.quiz_points || 0) > 0 ? '+' : ''}${student.quiz_points || 0}
                </span>
            </div>
            
            <div class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <div>
                    <strong><i class="fas fa-trophy"></i> Total Points</strong>
                </div>
                <span class="badge bg-success fs-5">${student.total_points}</span>
            </div>
        </div>
        
        <div class="mt-3 text-center">
            <a href="student_details.php?id=${student.id}" class="btn btn-primary">
                <i class="fas fa-user"></i> View Student Details
            </a>
        </div>
    `;
    
    document.getElementById('pointsBreakdownContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('pointsBreakdownModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
