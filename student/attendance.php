<?php
include 'includes/header.php';

// ── India timezone already set in header.php ──
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$is_sunday    = (date('N') == 7); // ISO-8601: 7 = Sunday

// ── Check if today is declared Holiday ──
$is_holiday_today = false;

// ── Student status ──
$student_status = safeRow($conn, "SELECT status FROM students WHERE id = $sid")['status'] ?? 'Active';

// ── Today's attendance ──
$today_att = safeRow($conn,
    "SELECT * FROM student_attendance
     WHERE student_id = $sid AND attendance_date = '$current_date' LIMIT 1");

$success = ''; $error = '';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $student_status !== 'Hold') {
    if ($is_sunday) {
        $error = "Sunday is a holiday — attendance is not counted today.";
    } elseif ($_POST['action'] === 'check_in') {
        if ($today_att) {
            $error = "You have already checked in today!";
        } else {
            $stmt = $conn->prepare("INSERT INTO student_attendance
                (student_id, attendance_date, status, check_in_time)
                VALUES (?, ?, 'Present', ?)");
            $stmt->bind_param("iss", $sid, $current_date, $current_time);
            if ($stmt->execute()) {
                $success = "Check-in successful at " . date('h:i A', strtotime($current_time));
                $today_att = safeRow($conn,
                    "SELECT * FROM student_attendance
                     WHERE student_id = $sid AND attendance_date = '$current_date' LIMIT 1");
            } else { $error = "Failed to check in. Please try again."; }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'check_out') {
        if (!$today_att) {
            $error = "You need to check in first!";
        } elseif (!empty($today_att['check_out_time'])) {
            $error = "You have already checked out today!";
        } else {
            $in_ts  = strtotime($today_att['check_in_time']);
            $out_ts = strtotime($current_time);
            $total_hours = round(($out_ts - $in_ts) / 3600, 2);
            if ($total_hours < 0) $total_hours = 0;
            $stmt = $conn->prepare("UPDATE student_attendance
                SET check_out_time = ?, total_hours = ?
                WHERE id = ?");
            $stmt->bind_param("sdi", $current_time, $total_hours, $today_att['id']);
            if ($stmt->execute()) {
                $success = "Check-out at " . date('h:i A', strtotime($current_time)) .
                           " — Total: " . number_format($total_hours, 2) . "h";
                $today_att = safeRow($conn,
                    "SELECT * FROM student_attendance
                     WHERE student_id = $sid AND attendance_date = '$current_date' LIMIT 1");
            } else { $error = "Failed to check out."; }
            $stmt->close();
        }
    }
}

// ════════════════════════════════════════════════════
//  MONTHLY STATS — excluding Sundays from denominator
// ════════════════════════════════════════════════════
$current_month     = date('Y-m');
$filter_month      = isset($_GET['month']) ? $_GET['month'] : $current_month;
$month_start_obj   = new DateTime(date('Y-m-01'));
$today_obj         = new DateTime($current_date);

// Count Sundays from month start → today (for rate denominator)
function countSundaysInRange($start_str, $end_str) {
    $count = 0;
    $d     = new DateTime($start_str);
    $end   = new DateTime($end_str);
    while ($d <= $end) {
        if ($d->format('N') == 7) $count++;
        $d->modify('+1 day');
    }
    return $count;
}

$month_start_str   = date('Y-m-01');
$sundays_so_far    = countSundaysInRange($month_start_str, $current_date);
$days_passed       = (int)$today_obj->diff($month_start_obj)->days + 1; // inclusive
$working_days      = max(1, $days_passed - $sundays_so_far);

$present_this_month = (int)safeVal($conn,
    "SELECT COUNT(*) AS cnt FROM student_attendance
     WHERE student_id = $sid AND status IN ('Present','Holiday')
     AND DATE_FORMAT(attendance_date, '%Y-%m') = '$current_month'
     AND DAYOFWEEK(attendance_date) != 1",   // exclude Sunday rows if any
    'cnt', 0);

$absent_working    = max(0, $working_days - $present_this_month);
$att_rate          = ($working_days > 0) ? round(($present_this_month / $working_days) * 100, 1) : 0;

// ════════════════════════════════════════════════════
//  LAST 7 DAYS chart data (Mon–Sun)
// ════════════════════════════════════════════════════
$chart_labels  = [];
$chart_present = [];
$chart_absent  = [];
$chart_sunday  = [];
$chart_holiday = [];

for ($i = 6; $i >= 0; $i--) {
    $d     = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($d)); // Mon, Tue ...
    $is_sun = (date('N', strtotime($d)) == 7);

    $chart_labels[] = $label;

    if ($is_sun) {
        $chart_present[] = null;
        $chart_absent[]  = null;
        $chart_sunday[]  = 1; // special sunday bar
        $chart_holiday[] = null;
    } else {
        $row = safeRow($conn,
            "SELECT status FROM student_attendance
             WHERE student_id = $sid AND attendance_date = '$d' LIMIT 1");
        $is_present = ($row && $row['status'] === 'Present');
        $is_holiday = ($row && $row['status'] === 'Holiday');
        
        // Track if today is holiday
        if ($d === $current_date && $is_holiday) $is_holiday_today = true;
        
        $chart_present[] = $is_present ? 1 : null;
        $chart_holiday[] = $is_holiday ? 1 : null;
        $chart_absent[]  = (!$is_present && !$is_holiday && $d <= $current_date) ? 1 : null;
        $chart_sunday[]  = null;
    }
}

// ════════════════════════════════════════════════════
//  ATTENDANCE HISTORY (filtered month)
// ════════════════════════════════════════════════════
$att_history = $conn->query("
    SELECT * FROM student_attendance
    WHERE student_id = $sid
    AND DATE_FORMAT(attendance_date, '%Y-%m') = '$filter_month'
    ORDER BY attendance_date DESC
");

// ════════════════════════════════════════════════════
//  CONSECUTIVE STREAK (skip Sundays)
// ════════════════════════════════════════════════════
$streak = 0;
$check_d = new DateTime($current_date);
while (true) {
    $ds = $check_d->format('Y-m-d');
    if ($check_d->format('N') == 7) { // Sunday — skip
        $check_d->modify('-1 day');
        continue;
    }
    $row = safeRow($conn,
        "SELECT status FROM student_attendance
         WHERE student_id = $sid AND attendance_date = '$ds' LIMIT 1");
    if ($row && in_array($row['status'], ['Present', 'Holiday'])) {
        $streak++;
        $check_d->modify('-1 day');
    } else { break; }
    if ($streak > 365) break; // safety
}
?>

<?php if ($success): ?>
<div class="alert-block alert-block-success fade-up">
  <i class="fas fa-check-circle"></i>
  <span><?php echo htmlspecialchars($success); ?></span>
  <button class="btn-close-alert">✕</button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-block alert-block-danger fade-up">
  <i class="fas fa-exclamation-circle"></i>
  <span><?php echo htmlspecialchars($error); ?></span>
  <button class="btn-close-alert">✕</button>
</div>
<?php endif; ?>

<?php if ($student_status === 'Hold'): ?>
<div class="alert-block alert-block-warning fade-up">
  <i class="fas fa-pause-circle"></i>
  <span><strong>Enrollment On Hold.</strong> Attendance is paused. Contact the academy office for details.</span>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-calendar-check" style="font-size:16px;-webkit-text-fill-color:var(--purple-light);"></i> My Attendance</h2>
    <p>India timezone (IST) · Sundays excluded from calculations</p>
  </div>
  <?php if($is_sunday): ?>
  <span class="sunday-badge"><i class="fas fa-sun"></i> Sunday — Day Off</span>
  <?php endif; ?>
</div>

<!-- CLOCK + DATE -->
<div class="glass-card fade-up delay-1">
  <div class="glass-card-body" style="text-align:center;padding:20px;">
    <div class="clock-display" id="clockDisplay"><?php echo date('h:i:s A'); ?></div>
    <div class="clock-date"><?php echo date('l, d F Y'); ?> &nbsp;·&nbsp; <?php echo date('T'); ?></div>
    <?php if($is_sunday): ?>
    <div style="margin-top:10px;">
      <span class="sunday-badge"><i class="fas fa-sun"></i> Sunday — Not Counted in Attendance</span>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- CHECK IN / CHECK OUT -->
<div class="d-grid-2 fade-up delay-2">

  <!-- CHECK IN -->
  <div class="checkin-card c-in">
    <div class="checkin-title"><i class="fas fa-sign-in-alt"></i> Check In</div>
    <?php if ($is_holiday_today): ?>
      <div style="font-size:14px;color:#9ca3af;font-weight:600;"><i class="fas fa-umbrella-beach"></i> Today is a Holiday</div>
      <span class="badge-pill" style="background:rgba(107,114,128,0.15);color:#9ca3af;border:1px solid rgba(107,114,128,0.3);"><i class="fas fa-umbrella-beach"></i> Holiday</span>
    <?php elseif ($today_att): ?>
      <div style="font-family:'Poppins',sans-serif;font-size:24px;font-weight:800;color:var(--green);">
        <?php echo date('h:i A', strtotime($today_att['check_in_time'])); ?>
      </div>
      <span class="badge-pill badge-green"><i class="fas fa-check-circle"></i> Checked In</span>
    <?php elseif($is_sunday): ?>
      <div style="font-size:13px;color:var(--text-muted);">Sunday — No attendance</div>
    <?php elseif($student_status === 'Hold'): ?>
      <div style="font-size:13px;color:var(--text-muted);">Enrollment on hold</div>
    <?php else: ?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="action" value="check_in">
        <button type="submit" class="btn btn-green btn-full btn-lg">
          <i class="fas fa-sign-in-alt"></i> Mark Check In
        </button>
      </form>
    <?php endif; ?>
  </div>

  <!-- CHECK OUT -->
  <div class="checkin-card c-out">
    <div class="checkin-title"><i class="fas fa-sign-out-alt"></i> Check Out</div>
    <?php if ($is_holiday_today): ?>
      <div style="font-size:14px;color:#9ca3af;font-weight:600;"><i class="fas fa-umbrella-beach"></i> Holiday</div>
      <span class="badge-pill" style="background:rgba(107,114,128,0.15);color:#9ca3af;border:1px solid rgba(107,114,128,0.3);">No check-out needed</span>
    <?php elseif ($today_att && !empty($today_att['check_out_time'])): ?>
      <div style="font-family:'Poppins',sans-serif;font-size:24px;font-weight:800;color:#f87171;">
        <?php echo date('h:i A', strtotime($today_att['check_out_time'])); ?>
      </div>
      <div style="font-size:12px;color:var(--text-muted);">
        Total: <strong style="color:var(--text);"><?php echo number_format($today_att['total_hours'], 2); ?>h</strong>
      </div>
      <span class="badge-pill badge-red"><i class="fas fa-check-circle"></i> Checked Out</span>
    <?php elseif ($today_att && empty($today_att['check_out_time'])): ?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="action" value="check_out">
        <button type="submit" class="btn btn-red btn-full btn-lg">
          <i class="fas fa-sign-out-alt"></i> Mark Check Out
        </button>
      </form>
    <?php elseif($is_sunday): ?>
      <div style="font-size:13px;color:var(--text-muted);">Sunday — No attendance</div>
    <?php else: ?>
      <button class="btn btn-ghost btn-full btn-lg" disabled>
        <i class="fas fa-sign-out-alt"></i> Check In First
      </button>
    <?php endif; ?>
  </div>

</div>

<!-- MONTHLY STATS (excluding Sundays) -->
<div class="stats-grid fade-up delay-3">
  <div class="stat-card s-green">
    <div class="stat-card-info">
      <div class="stat-card-label">Days Present</div>
      <div class="stat-card-value"><?php echo $present_this_month; ?></div>
      <div class="stat-card-sub"><?php echo date('F'); ?></div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
  </div>

  <div class="stat-card s-red">
    <div class="stat-card-info">
      <div class="stat-card-label">Days Absent</div>
      <div class="stat-card-value"><?php echo $absent_working; ?></div>
      <div class="stat-card-sub">Working days only</div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
  </div>

  <div class="stat-card s-purple">
    <div class="stat-card-info">
      <div class="stat-card-label">Attendance Rate</div>
      <div class="stat-card-value"><?php echo $att_rate; ?>%</div>
      <div class="stat-card-sub">Sundays excluded</div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-chart-pie"></i></div>
  </div>

  <div class="stat-card s-yellow">
    <div class="stat-card-info">
      <div class="stat-card-label">Streak</div>
      <div class="stat-card-value"><?php echo $streak; ?></div>
      <div class="stat-card-sub">Consecutive days</div>
    </div>
    <div class="stat-card-icon"><i class="fas fa-fire"></i></div>
  </div>
</div>

<!-- WEEKLY CHART -->
<div class="glass-card fade-up delay-4">
  <div class="glass-card-body">
    <div class="section-header">
      <div class="section-title"><i class="fas fa-chart-bar"></i> Last 7 Days</div>
      <span class="sunday-badge"><i class="fas fa-sun"></i> Sunday shown in yellow — not counted</span>
    </div>
    <canvas id="weeklyChart" height="90"></canvas>
  </div>
</div>

<!-- ATTENDANCE HISTORY -->
<div class="glass-card fade-up delay-5">
  <div class="glass-card-body">
    <div class="section-header">
      <div class="section-title"><i class="fas fa-history"></i> Attendance History</div>
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="month" name="month" class="form-control"
               style="width:auto;padding:6px 10px;font-size:12px;"
               value="<?php echo htmlspecialchars($filter_month); ?>"
               max="<?php echo date('Y-m'); ?>">
        <button type="submit" class="btn btn-ghost btn-sm">
          <i class="fas fa-filter"></i> Filter
        </button>
      </form>
    </div>

    <?php if ($att_history && $att_history->num_rows > 0): ?>
    <div class="table-wrap">
      <table class="dark-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Hours</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($att = $att_history->fetch_assoc()):
            $row_is_sun = (date('N', strtotime($att['attendance_date'])) == 7);
          ?>
          <tr <?php if($row_is_sun) echo 'style="opacity:0.55;"'; ?>>
            <td>
              <?php echo date('d M Y (D)', strtotime($att['attendance_date'])); ?>
              <?php if($row_is_sun): ?><span class="sunday-badge" style="margin-left:6px;"><i class="fas fa-sun"></i> Sun</span><?php endif; ?>
            </td>
            <td>
              <?php if(!empty($att['check_in_time'])): ?>
              <span class="badge-pill badge-green"><?php echo date('h:i A', strtotime($att['check_in_time'])); ?></span>
              <?php else: ?><span style="color:var(--text-dim);">—</span><?php endif; ?>
            </td>
            <td>
              <?php if(!empty($att['check_out_time'])): ?>
              <span class="badge-pill badge-red"><?php echo date('h:i A', strtotime($att['check_out_time'])); ?></span>
              <?php else: ?><span class="badge-pill badge-yellow">Not marked</span><?php endif; ?>
            </td>
            <td>
              <?php if($att['total_hours']): ?>
              <strong><?php echo number_format($att['total_hours'], 2); ?>h</strong>
              <?php else: ?><span style="color:var(--text-dim);">—</span><?php endif; ?>
            </td>
            <td>
              <?php if ($att['status'] === 'Holiday'): ?>
              <span class="badge-pill" style="background:rgba(107,114,128,0.15);color:#9ca3af;border:1px solid rgba(107,114,128,0.3);"><i class="fas fa-umbrella-beach"></i> Holiday</span>
              <?php else: ?>
              <span class="badge-pill badge-green"><i class="fas fa-check"></i> <?php echo htmlspecialchars($att['status']); ?></span>
              <?php endif; ?>
              <?php if($row_is_sun): ?><small style="color:var(--text-dim);font-size:10px;display:block;margin-top:2px;">Not counted</small><?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="alert-block alert-block-info">
      <i class="fas fa-info-circle"></i>
      No attendance records for <?php echo date('F Y', strtotime($filter_month . '-01')); ?>.
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Real-time IST clock
(function updateClock() {
  const now = new Date();
  const opts = { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true, timeZone:'Asia/Kolkata' };
  document.getElementById('clockDisplay').textContent = now.toLocaleTimeString('en-IN', opts);
  setTimeout(updateClock, 1000);
})();

// Weekly Chart
const chartLabels  = <?php echo json_encode($chart_labels); ?>;
const chartPresent = <?php echo json_encode($chart_present); ?>;
const chartAbsent  = <?php echo json_encode($chart_absent); ?>;
const chartSunday  = <?php echo json_encode($chart_sunday); ?>;
const chartHoliday = <?php echo json_encode($chart_holiday); ?>;

const ctx = document.getElementById('weeklyChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: chartLabels,
    datasets: [
      {
        label: 'Present',
        data: chartPresent,
        backgroundColor: 'rgba(16,185,129,0.8)',
        borderColor:      'rgba(16,185,129,1)',
        borderRadius: 6,
        borderWidth: 1,
      },
      {
        label: 'Holiday',
        data: chartHoliday,
        backgroundColor: 'rgba(156,163,175,0.7)',
        borderColor:      'rgba(156,163,175,1)',
        borderRadius: 6,
        borderWidth: 1,
      },
      {
        label: 'Absent',
        data: chartAbsent,
        backgroundColor: 'rgba(239,68,68,0.75)',
        borderColor:      'rgba(239,68,68,1)',
        borderRadius: 6,
        borderWidth: 1,
      },
      {
        label: 'Sunday (Not Counted)',
        data: chartSunday,
        backgroundColor: 'rgba(245,158,11,0.65)',
        borderColor:      'rgba(245,158,11,1)',
        borderRadius: 6,
        borderWidth: 1,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        display: true,
        labels: { color: 'rgba(241,240,255,0.65)', font: { size: 11 }, boxWidth: 12, padding: 14 }
      },
      tooltip: {
        backgroundColor: 'rgba(18,15,45,0.96)',
        borderColor: 'rgba(255,255,255,0.12)', borderWidth: 1,
        titleColor: '#f1f0ff', bodyColor: 'rgba(241,240,255,0.7)',
        callbacks: {
          label: ctx => ctx.dataset.label
        }
      }
    },
    scales: {
      x: {
        ticks: { color: 'rgba(241,240,255,0.55)', font: { size: 11 } },
        grid:  { color: 'rgba(255,255,255,0.04)' }
      },
      y: {
        beginAtZero: true, max: 1.2,
        ticks: { color: 'rgba(241,240,255,0.55)', font: { size: 11 },
          callback: v => v === 1 ? 'Yes' : v === 0 ? 'No' : '' },
        grid: { color: 'rgba(255,255,255,0.05)' }
      }
    }
  }
});
</script>

<?php include 'includes/footer.php'; ?>