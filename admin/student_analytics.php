<?php
include 'includes/header.php';
?>

<style>
/* ── ANALYTICS PAGE STYLES ── */
.analytics-header { margin-bottom: 24px; }
.analytics-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--indigo-900); }
.analytics-header p { color: var(--muted); font-size: 13px; }

/* Quick Stats Bar */
.stat-pill {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 16px 20px; box-shadow: var(--sh-sm);
    transition: var(--ease); display: flex; align-items: center; gap: 14px;
}
.stat-pill:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.stat-pill .stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #fff; flex-shrink: 0;
}
.stat-pill .stat-val { font-size: 1.4rem; font-weight: 800; line-height: 1.1; }
.stat-pill .stat-lbl { font-size: 11px; color: var(--muted); font-weight: 600; letter-spacing: .3px; }

/* Duration Filter Pills */
.dur-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.dur-pill {
    padding: 8px 20px; border-radius: 99px; font-size: 12.5px; font-weight: 700;
    border: 2px solid var(--border); background: var(--surface); color: var(--text);
    cursor: pointer; transition: var(--ease); letter-spacing: .3px;
}
.dur-pill:hover { border-color: var(--indigo-400); color: var(--indigo-600); background: var(--indigo-100); }
.dur-pill.active {
    background: linear-gradient(135deg, var(--indigo-600), var(--accent));
    color: #fff; border-color: transparent; box-shadow: 0 4px 14px rgba(99,102,241,.35);
}

/* Filter Bar */
.filter-bar {
    background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
    padding: 14px 18px; margin-bottom: 20px; box-shadow: var(--sh-sm);
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
.filter-bar .form-control, .filter-bar .form-select {
    max-width: 220px; font-size: 12.5px; height: 38px;
}

/* Student Cards Grid */
.students-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px; margin-bottom: 32px;
}
.stu-card {
    background: var(--surface); border-radius: 16px; padding: 18px;
    border: 2px solid var(--border); box-shadow: var(--sh-sm);
    transition: all .25s ease; cursor: pointer; position: relative; overflow: hidden;
}
.stu-card:hover { transform: translateY(-4px); box-shadow: var(--sh-md); }
.stu-card.border-green { border-color: #10b981; }
.stu-card.border-orange { border-color: #f59e0b; }
.stu-card.border-red { border-color: #ef4444; }
.stu-card .stu-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.stu-card .stu-avatar {
    width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
    background: linear-gradient(135deg, var(--indigo-600), var(--accent));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px; font-weight: 700;
}
.stu-card .stu-avatar img { width: 100%; height: 100%; object-fit: cover; }
.stu-card .stu-avatar i { font-size: 20px; color: rgba(255,255,255,.85); }
.stu-card .stu-name { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.2; }
.stu-card .stu-course { font-size: 11.5px; color: var(--muted); font-weight: 500; }
.stu-card .stu-info-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px;
    font-size: 12px; margin-bottom: 10px;
}
.stu-card .stu-info-grid .info-label { color: var(--muted); font-weight: 500; font-size: 10.5px; letter-spacing: .3px; text-transform: uppercase; }
.stu-card .stu-info-grid .info-value { font-weight: 700; color: var(--text); }
.stu-card .stu-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--border); }

/* Fee badges */
.fee-badge {
    padding: 3px 10px; border-radius: 99px; font-size: 10.5px; font-weight: 700; letter-spacing: .3px;
}
.fee-badge.paid { background: rgba(16,185,129,.12); color: #059669; }
.fee-badge.partial { background: rgba(245,158,11,.12); color: #d97706; }
.fee-badge.overdue { background: rgba(239,68,68,.12); color: #dc2626; }

/* Days badge */
.days-badge {
    padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 800;
}
.days-badge.green { background: rgba(16,185,129,.12); color: #059669; }
.days-badge.orange { background: rgba(245,158,11,.12); color: #d97706; }
.days-badge.red { background: rgba(239,68,68,.12); color: #dc2626; }

/* Status ribbon */
.stu-ribbon {
    position: absolute; top: 12px; right: -28px; transform: rotate(45deg);
    font-size: 8px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
    padding: 3px 32px; color: #fff;
}
.stu-ribbon.expired { background: #ef4444; }

/* ── FORECAST SECTION ── */
.forecast-section {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 24px; box-shadow: var(--sh-sm); margin-top: 8px;
}
.forecast-section h4 {
    font-size: 1.1rem; font-weight: 700; color: var(--indigo-900); margin-bottom: 20px;
}
.forecast-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;
}
.forecast-card {
    background: linear-gradient(145deg, #f8f9ff, #fff); border: 1px solid var(--border);
    border-radius: 14px; padding: 16px; text-align: center; transition: var(--ease);
    position: relative; overflow: hidden;
}
.forecast-card:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.forecast-card.current {
    background: linear-gradient(145deg, var(--indigo-900), var(--indigo-800));
    border-color: var(--indigo-700); color: #fff;
}
.forecast-card.current .fc-month { color: var(--indigo-400); }
.forecast-card.current .fc-active { color: #fff; }
.forecast-card.current .fc-expiring { color: rgba(255,255,255,.7); }
.fc-month { font-size: 11px; font-weight: 700; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 8px; }
.fc-active { font-size: 1.6rem; font-weight: 800; color: var(--indigo-700); line-height: 1; }
.fc-label { font-size: 10px; color: var(--muted); font-weight: 600; margin: 2px 0 8px; }
.fc-expiring {
    font-size: 11px; font-weight: 700; color: var(--crimson);
    background: rgba(239,68,68,.08); border-radius: 8px; padding: 4px 8px;
    display: inline-block;
}
.forecast-card.current .fc-expiring { background: rgba(255,255,255,.12); }

/* Chart container */
.forecast-chart-wrap {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 20px; box-shadow: var(--sh-sm); margin-top: 16px;
}

/* Loading & empty states */
.loading-state, .empty-state {
    text-align: center; padding: 60px 20px; color: var(--muted);
}
.loading-state i { font-size: 2rem; color: var(--indigo-400); margin-bottom: 10px; }
.empty-state i { font-size: 2.5rem; color: #d1d5db; margin-bottom: 10px; }

/* Counter animation */
.count-up { transition: all .4s ease; }

/* Progress bar in forecast */
.fc-progress {
    height: 4px; background: rgba(0,0,0,.06); border-radius: 99px;
    margin-top: 8px; overflow: hidden;
}
.fc-progress-bar {
    height: 100%; border-radius: 99px; transition: width .6s ease;
}
.forecast-card.current .fc-progress { background: rgba(255,255,255,.15); }

@media(max-width:767px) {
    .students-grid { grid-template-columns: 1fr; }
    .forecast-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-bar .form-control, .filter-bar .form-select { max-width: 100%; }
}
@media(max-width:480px) {
    .forecast-grid { grid-template-columns: 1fr; }
}
</style>

<!-- PAGE HEADER -->
<div class="analytics-header">
    <h2><i class="fas fa-chart-line text-purple"></i> Student Analytics & Forecast</h2>
    <p class="mb-0">Real-time course expiry tracking, student status cards & future enrollment predictions</p>
</div>

<!-- QUICK STATISTICS BAR -->
<div class="row g-3 mb-4" id="statsBar">
    <div class="col-6 col-lg-3">
        <div class="stat-pill">
            <div class="stat-icon icon-purple"><i class="fas fa-users"></i></div>
            <div><div class="stat-val" id="statActive">–</div><div class="stat-lbl">Active Students</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-pill">
            <div class="stat-icon icon-warning"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-val" id="statExpiring">–</div><div class="stat-lbl">Expiring This Month</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-pill">
            <div class="stat-icon icon-danger"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="stat-val" id="statOverdue">–</div><div class="stat-lbl">Already Overdue</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-pill">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#ea580c)"><i class="fas fa-indian-rupee-sign"></i></div>
            <div><div class="stat-val" id="statFees">–</div><div class="stat-lbl">Pending Fees</div></div>
        </div>
    </div>
</div>

<!-- DURATION CATEGORY FILTER -->
<div class="dur-filters" id="durationFilters">
    <button class="dur-pill active" data-duration="0">All Durations</button>
    <button class="dur-pill" data-duration="3">3 Months</button>
    <button class="dur-pill" data-duration="6">6 Months</button>
    <button class="dur-pill" data-duration="12">12 Months</button>
    <button class="dur-pill" data-duration="18">18 Months</button>
    <button class="dur-pill" data-duration="24">24 Months</button>
</div>

<!-- SEARCH & FILTER BAR -->
<div class="filter-bar">
    <div class="position-relative" style="flex:1;min-width:180px;max-width:280px">
        <i class="fas fa-search position-absolute" style="left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px"></i>
        <input type="text" class="form-control" id="searchInput" placeholder="Search student name..." style="padding-left:34px">
    </div>
    <select class="form-select" id="courseFilter"><option value="0">All Courses</option></select>
    <select class="form-select" id="batchFilter">
        <option value="">All Batches</option>
        <option value="Morning">Morning</option>
        <option value="Evening">Evening</option>
    </select>
    <span class="ms-auto" style="font-size:12px;font-weight:700;color:var(--muted)">
        Showing <span id="resultCount" class="text-purple">0</span> students
    </span>
</div>

<!-- STUDENT CARDS GRID -->
<div id="studentsContainer">
    <div class="loading-state">
        <i class="fas fa-spinner fa-spin d-block"></i>
        <span>Loading students...</span>
    </div>
</div>

<!-- ═══ FORECAST SECTION ═══ -->
<div class="forecast-section mt-4">
    <h4><i class="fas fa-chart-bar text-purple"></i> Upcoming Active Student Forecast</h4>
    <p style="font-size:12.5px;color:var(--muted);margin-bottom:20px">
        Predicts how many students will remain active over the next 12 months based on course end dates.
    </p>
    <div id="forecastContainer">
        <div class="loading-state">
            <i class="fas fa-spinner fa-spin d-block"></i>
            <span>Generating forecast...</span>
        </div>
    </div>
</div>

<!-- FORECAST CHART -->
<div class="forecast-chart-wrap mt-3">
    <h5 style="font-size:14px;font-weight:700;color:var(--indigo-900);margin-bottom:14px">
        <i class="fas fa-wave-square text-purple"></i> Active Students Trend
    </h5>
    <canvas id="forecastChart" height="100"></canvas>
</div>

<!-- ═══ JAVASCRIPT ═══ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE = 'ajax/student_analytics_data.php';
    let currentDuration = 0;
    let searchTimer = null;
    let forecastChart = null;

    // ── Load Stats ──
    function loadStats() {
        fetch(BASE + '?action=get_stats')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    animateCount('statActive', d.stats.total_active);
                    animateCount('statExpiring', d.stats.expiring_this_month);
                    animateCount('statOverdue', d.stats.already_overdue);
                    animateCount('statFees', d.stats.pending_fees);
                }
            });
    }

    // ── Load Courses for filter ──
    function loadCourses() {
        fetch(BASE + '?action=get_courses')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const sel = document.getElementById('courseFilter');
                    d.courses.forEach(c => {
                        sel.innerHTML += '<option value="'+c.id+'">'+escHtml(c.name)+'</option>';
                    });
                }
            });
    }

    // ── Load Students ──
    function loadStudents() {
        const search = document.getElementById('searchInput').value.trim();
        const course = document.getElementById('courseFilter').value;
        const batch = document.getElementById('batchFilter').value;

        const params = new URLSearchParams({
            action: 'get_students', duration: currentDuration,
            search: search, course_id: course, batch: batch
        });

        document.getElementById('studentsContainer').innerHTML =
            '<div class="loading-state"><i class="fas fa-spinner fa-spin d-block"></i><span>Loading...</span></div>';

        fetch(BASE + '?' + params.toString())
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('resultCount').textContent = d.total;
                    renderStudents(d.students);
                }
            });
    }

    // ── Render Student Cards ──
    function renderStudents(students) {
        const container = document.getElementById('studentsContainer');
        if (!students.length) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-user-slash d-block"></i><div style="font-size:14px;font-weight:600;margin-top:6px">No students found</div><div style="font-size:12px">Try adjusting filters</div></div>';
            return;
        }

        let html = '<div class="students-grid">';
        students.forEach(s => {
            const borderClass = s.border_status === 'expired' ? 'border-red'
                : s.border_status === 'warning' ? 'border-orange' : 'border-green';
            const feeClass = s.fee_status === 'fully_paid' ? 'paid'
                : s.fee_status === 'partial' ? 'partial' : 'overdue';
            const daysClass = s.border_status === 'expired' ? 'red'
                : s.border_status === 'warning' ? 'orange' : 'green';

            const avatar = s.photo
                ? '<img src="'+escHtml(s.photo)+'" alt="'+escHtml(s.full_name)+'" onerror="this.style.display=\'none\';this.parentElement.innerHTML=\'<i class=\\\'fas fa-user-graduate\\\'></i>\'">'
                : '<i class="fas fa-user-graduate"></i>';

            let daysText = '';
            if (s.days_remaining < 0) {
                daysText = s.overdue_days + ' days overdue';
            } else {
                daysText = s.days_remaining + ' days left';
            }

            const ribbon = s.border_status === 'expired'
                ? '<div class="stu-ribbon expired">EXPIRED</div>' : '';

            html += `
            <div class="stu-card ${borderClass}" onclick="window.location.href='student_details.php?id=${s.id}'">
                ${ribbon}
                <div class="stu-top">
                    <div class="stu-avatar">${avatar}</div>
                    <div style="min-width:0">
                        <div class="stu-name">${escHtml(s.full_name)}</div>
                        <div class="stu-course">${escHtml(s.course_name)} &bull; ${escHtml(s.batch)}</div>
                    </div>
                </div>
                <div class="stu-info-grid">
                    <div><div class="info-label">Joined</div><div class="info-value">${s.enrollment_date}</div></div>
                    <div><div class="info-label">Ending</div><div class="info-value">${s.end_date}</div></div>
                    <div><div class="info-label">Duration</div><div class="info-value">${s.duration_months} Months</div></div>
                    <div><div class="info-label">Fees Pending</div><div class="info-value">₹${s.pending_fees}</div></div>
                </div>
                <div class="stu-footer">
                    <span class="days-badge ${daysClass}">${daysText}</span>
                    <span class="fee-badge ${feeClass}">${s.fee_label}</span>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // ── Load Forecast ──
    function loadForecast() {
        fetch(BASE + '?action=get_forecast')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    renderForecast(d.forecast, d.total_active);
                    renderForecastChart(d.forecast);
                }
            });
    }

    function renderForecast(forecast, totalActive) {
        const container = document.getElementById('forecastContainer');
        let html = '<div class="forecast-grid">';
        const maxActive = totalActive || 1;

        forecast.forEach(f => {
            const pct = Math.round((f.active / maxActive) * 100);
            const barColor = f.is_current
                ? 'background:linear-gradient(90deg,var(--indigo-400),#818cf8)'
                : pct > 60 ? 'background:linear-gradient(90deg,#10b981,#34d399)'
                : pct > 30 ? 'background:linear-gradient(90deg,#f59e0b,#fbbf24)'
                : 'background:linear-gradient(90deg,#ef4444,#f87171)';

            html += `
            <div class="forecast-card ${f.is_current ? 'current' : ''}">
                <div class="fc-month">${escHtml(f.month)}</div>
                <div class="fc-active">${f.active}</div>
                <div class="fc-label">Active Students</div>
                ${f.expiring > 0 ? '<div class="fc-expiring"><i class="fas fa-arrow-down" style="font-size:9px"></i> '+f.expiring+' expiring</div>' : '<div class="fc-expiring" style="color:var(--emerald);background:rgba(16,185,129,.08)"><i class="fas fa-check" style="font-size:9px"></i> 0 expiring</div>'}
                <div class="fc-progress"><div class="fc-progress-bar" style="width:${pct}%;${barColor}"></div></div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderForecastChart(forecast) {
        const ctx = document.getElementById('forecastChart').getContext('2d');
        const labels = forecast.map(f => f.month.split(' ')[0]);
        const activeData = forecast.map(f => f.active);
        const expiringData = forecast.map(f => f.expiring);

        if (forecastChart) forecastChart.destroy();

        forecastChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Active Students',
                        data: activeData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Expiring',
                        data: expiringData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.06)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ef4444',
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Sora', size: 11, weight: '600' }, usePointStyle: true, padding: 16 } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { family: 'Sora', size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Sora', size: 10 } } }
                }
            }
        });
    }

    // ── Helpers ──
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function animateCount(id, target) {
        const el = document.getElementById(id);
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 25));
        const interval = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(interval); }
            el.textContent = current;
        }, 30);
    }

    // ── Event Listeners ──
    document.querySelectorAll('.dur-pill').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.dur-pill').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentDuration = parseInt(this.dataset.duration);
            loadStudents();
        });
    });

    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadStudents, 350);
    });

    document.getElementById('courseFilter').addEventListener('change', loadStudents);
    document.getElementById('batchFilter').addEventListener('change', loadStudents);

    // ── Init ──
    loadStats();
    loadCourses();
    loadStudents();
    loadForecast();
});
</script>

<?php include 'includes/footer.php'; ?>
