<?php
/**
 * SINGLE SOURCE OF TRUTH FOR STUDENT RANKING
 * Monthly based ranking system
 * 
 * UPDATED: Project points now only count VERIFIED projects (verification_status = 'verified')
 * Points values: Web Development/Developer = 12.5, Others (Graphic/Digital) = 5
 * Attendance: Present/Holiday (non-Sunday) = +2, Absent (non-Sunday) = -1, Sunday = 0
 * Points are tracked via points_awarded column on student_projects
 */

function getMonthlyRanking($conn, $month_start, $month_end) {

    $ranking_query = $conn->query("
        SELECT 
            s.id,
            s.full_name,
            s.photo,

            -- PAYMENT (Monthly)
            (CASE 
                WHEN EXISTS(
                    SELECT 1 FROM payments p 
                    WHERE p.student_id = s.id 
                    AND p.payment_date BETWEEN '$month_start' AND '$month_end'
                ) THEN 10 ELSE 0 
            END) as payment_points,

            -- PROJECT COUNT (Monthly — only VERIFIED projects)
            (SELECT COUNT(*) 
             FROM student_projects sp 
             WHERE sp.student_id = s.id 
             AND sp.verification_status = 'verified'
             AND sp.verified_at BETWEEN '$month_start' AND '$month_end'
            ) as completed_projects,

            -- PROJECT POINTS (Monthly — sum of points_awarded for verified projects)
            COALESCE((
                SELECT SUM(sp.points_awarded)
                FROM student_projects sp
                WHERE sp.student_id = s.id
                AND sp.verification_status = 'verified'
                AND sp.verified_at BETWEEN '$month_start' AND '$month_end'
            ), 0) as project_points,

            -- ATTENDANCE (Monthly: Present/Holiday non-Sunday = +2, Absent non-Sunday = -1, Sunday = 0)
            COALESCE((
                SELECT SUM(
                    CASE 
                        WHEN sa.status IN ('Present','Holiday') AND DAYOFWEEK(sa.attendance_date) != 1 THEN 2
                        WHEN sa.status = 'Absent' AND DAYOFWEEK(sa.attendance_date) != 1 THEN -1
                        ELSE 0
                    END
                )
                FROM student_attendance sa
                WHERE sa.student_id = s.id
                AND sa.attendance_date BETWEEN '$month_start' AND '$month_end'
            ), 0) as attendance_points,

            -- MANUAL POINTS (Monthly using created_at)
            COALESCE((
                SELECT SUM(points) 
                FROM student_manual_points smp 
                WHERE smp.student_id = s.id
                AND smp.created_at BETWEEN '$month_start' AND '$month_end'
            ), 0) as manual_points,

            -- QUIZ POINTS (Monthly — from test_results.points_awarded)
            COALESCE((
                SELECT SUM(tr.points_awarded)
                FROM test_results tr
                WHERE tr.student_id = s.id
                AND tr.completed_at BETWEEN '$month_start' AND '$month_end'
            ), 0) as quiz_points,

            -- TOTAL POINTS (Monthly Final)
            (
                (CASE 
                    WHEN EXISTS(
                        SELECT 1 FROM payments p 
                        WHERE p.student_id = s.id 
                        AND p.payment_date BETWEEN '$month_start' AND '$month_end'
                    ) THEN 10 ELSE 0 
                END)
                +
                COALESCE((
                    SELECT SUM(sp.points_awarded)
                    FROM student_projects sp
                    WHERE sp.student_id = s.id
                    AND sp.verification_status = 'verified'
                    AND sp.verified_at BETWEEN '$month_start' AND '$month_end'
                ), 0)
                +
                COALESCE((
                    SELECT SUM(
                        CASE 
                            WHEN sa.status IN ('Present','Holiday') AND DAYOFWEEK(sa.attendance_date) != 1 THEN 2
                            WHEN sa.status = 'Absent' AND DAYOFWEEK(sa.attendance_date) != 1 THEN -1
                            ELSE 0
                        END
                    )
                    FROM student_attendance sa
                    WHERE sa.student_id = s.id
                    AND sa.attendance_date BETWEEN '$month_start' AND '$month_end'
                ), 0)
                +
                COALESCE((
                    SELECT SUM(points) 
                    FROM student_manual_points smp 
                    WHERE smp.student_id = s.id
                    AND smp.created_at BETWEEN '$month_start' AND '$month_end'
                ), 0)
                +
                COALESCE((
                    SELECT SUM(tr.points_awarded)
                    FROM test_results tr
                    WHERE tr.student_id = s.id
                    AND tr.completed_at BETWEEN '$month_start' AND '$month_end'
                ), 0)
            ) as total_points

        FROM students s
        JOIN categories cat ON s.category_id = cat.id
        WHERE s.status = 'Active' AND s.login_enabled = 1
     ORDER BY 
    total_points DESC,
    (
        SELECT MIN(CONCAT(sa.attendance_date,' ',sa.check_in_time))
        FROM student_attendance sa
        WHERE sa.student_id = s.id
        AND sa.status = 'Present'
        AND sa.attendance_date BETWEEN '$month_start' AND '$month_end'
    ) ASC,
    s.full_name ASC
    ");

    $ranking = [];
    $rank = 1;

    while ($row = $ranking_query->fetch_assoc()) {
        $ranking[$row['id']] = [
            'rank' => $rank,
            'total_points' => floatval($row['total_points']),
            'payment_points' => (int)$row['payment_points'],
            'project_points' => floatval($row['project_points']),
            'attendance_points' => floatval($row['attendance_points']),
            'manual_points' => floatval($row['manual_points']),
            'quiz_points' => floatval($row['quiz_points']),
            'completed_projects' => (int)$row['completed_projects'],
            'full_name' => $row['full_name'],
            'photo' => $row['photo']
        ];
        $rank++;
    }

    return $ranking;
}

function getStudentRank($ranking, $student_id) {
    return $ranking[$student_id]['rank'] ?? 0;
}

function getTotalActiveStudents($conn) {
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM students 
        WHERE status = 'Active' AND login_enabled = 1
    ");
    return (int)$result->fetch_assoc()['count'];
}
?>