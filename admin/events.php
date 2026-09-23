<?php
include 'includes/header.php';

// Permission check
if (!$canManageEvents) {
    header('Location: index.php');
    exit;
}

// ── Filters ──
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';

// ── Build query ──
$where = "WHERE DATE_FORMAT(event_date, '%Y-%m') = ?";
$params = [$filter_month];
$types = "s";

if (!empty($filter_status) && in_array($filter_status, ['Upcoming', 'Completed', 'Cancelled'])) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($filter_category)) {
    $where .= " AND category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

// ── Fetch events ──
$stmt = $conn->prepare("SELECT e.*, a.full_name as creator_name 
                         FROM events e 
                         LEFT JOIN admins a ON e.created_by = a.id 
                         $where 
                         ORDER BY e.event_date ASC, e.event_time ASC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$events_result = $stmt->get_result();
$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

// ── Stats for current filter ──
$total_events = count($events);
$upcoming_count = 0;
$completed_count = 0;
$cancelled_count = 0;
foreach ($events as $evt) {
    if ($evt['status'] === 'Upcoming') $upcoming_count++;
    elseif ($evt['status'] === 'Completed') $completed_count++;
    elseif ($evt['status'] === 'Cancelled') $cancelled_count++;
}

// ── Get unique categories for filter dropdown ──
$cat_result = $conn->query("SELECT DISTINCT category FROM events ORDER BY category ASC");
$categories = [];
while ($c = $cat_result->fetch_assoc()) {
    $categories[] = $c['category'];
}

// ── Get months that have events (for history navigation) ──
$months_result = $conn->query("SELECT DISTINCT DATE_FORMAT(event_date, '%Y-%m') as ym, 
                                       DATE_FORMAT(event_date, '%M %Y') as label,
                                       COUNT(*) as cnt
                                FROM events 
                                GROUP BY ym 
                                ORDER BY ym DESC 
                                LIMIT 24");
$event_months = [];
while ($m = $months_result->fetch_assoc()) {
    $event_months[] = $m;
}

// ── Category presets for create form ──
$category_presets = ['General', 'Competition', 'Celebration', 'Fun Day', 'Learning', 'Assessment', 'Ceremony', 'Sports'];

// ── Icon presets for create form ──
$icon_presets = [
    'fas fa-calendar-alt' => 'Calendar',
    'fas fa-question-circle' => 'Quiz',
    'fas fa-user-tie' => 'Interview',
    'fas fa-tshirt' => 'Dress Day',
    'fas fa-film' => 'Movie/Film',
    'fas fa-medal' => 'Medal/Award',
    'fas fa-trophy' => 'Trophy',
    'fas fa-certificate' => 'Certificate',
    'fas fa-book-open' => 'Storytelling',
    'fas fa-microphone' => 'Presentation',
    'fas fa-futbol' => 'Sports',
    'fas fa-paint-brush' => 'Art/Creative',
    'fas fa-music' => 'Music',
    'fas fa-code' => 'Coding',
    'fas fa-comments' => 'Debate',
    'fas fa-users' => 'Group Activity',
    'fas fa-star' => 'Special',
    'fas fa-gift' => 'Reward',
    'fas fa-graduation-cap' => 'Academic',
    'fas fa-gamepad' => 'Game',
];
?>

<style>
/* ── Event-specific styles (extends admin shell) ── */
.event-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.event-stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: var(--sh-sm);
    transition: var(--ease);
}
.event-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--sh-md);
}
.event-stat-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff; flex-shrink: 0;
}
.event-stat-value { font-size: 24px; font-weight: 800; color: var(--text); line-height: 1; }
.event-stat-label { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }

.filter-bar {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    margin-bottom: 20px;
}
.filter-bar .form-control, .filter-bar .form-select {
    width: auto; min-width: 140px; font-size: 13px; padding: 7px 12px;
}

.event-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 6px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
}
.event-badge-upcoming { background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.25); }
.event-badge-completed { background: rgba(99,102,241,0.12); color: var(--indigo-600); border: 1px solid rgba(99,102,241,0.25); }
.event-badge-cancelled { background: rgba(220,38,38,0.1); color: var(--crimson); border: 1px solid rgba(220,38,38,0.2); }

.event-icon-cell {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
    background: var(--indigo-100); color: var(--indigo-600);
}

.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
    margin-top: 8px;
}
.icon-picker-item {
    width: 100%; aspect-ratio: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 3px;
    background: var(--bg);
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: var(--ease);
    padding: 6px;
}
.icon-picker-item:hover { border-color: var(--accent); background: rgba(99,102,241,0.05); }
.icon-picker-item.selected { border-color: var(--accent); background: rgba(99,102,241,0.1); box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
.icon-picker-item i { font-size: 18px; color: var(--indigo-600); }
.icon-picker-item span { font-size: 9px; color: var(--muted); font-weight: 600; text-align: center; line-height: 1.1; }

.month-history { margin-bottom: 24px; }
.month-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
    background: var(--bg); border: 1px solid var(--border);
    color: var(--muted); text-decoration: none;
    transition: var(--ease); margin: 3px;
}
.month-chip:hover { border-color: var(--accent); color: var(--indigo-700); background: rgba(99,102,241,0.05); }
.month-chip.active { border-color: var(--accent); color: #fff; background: var(--indigo-600); }
.month-chip .chip-count {
    background: rgba(0,0,0,0.08); border-radius: 99px; padding: 1px 7px;
    font-size: 10px; font-weight: 700;
}
.month-chip.active .chip-count { background: rgba(255,255,255,0.2); }

@media(max-width:768px) {
    .event-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar .form-control, .filter-bar .form-select { width: 100%; min-width: auto; }
}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1" style="font-weight:800;color:var(--text);">
            <i class="fas fa-calendar-star text-purple"></i> Event Management
        </h4>
        <p class="mb-0" style="font-size:13px;color:var(--muted);">
            Create and manage academy events & activities
        </p>
    </div>
    <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetEventForm()">
        <i class="fas fa-plus"></i> Create Event
    </button>
</div>

<!-- Stats Cards -->
<div class="event-stats-grid">
    <div class="event-stat-card">
        <div class="event-stat-icon icon-purple"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <div class="event-stat-value"><?= $total_events ?></div>
            <div class="event-stat-label">Total Events</div>
        </div>
    </div>
    <div class="event-stat-card">
        <div class="event-stat-icon icon-success"><i class="fas fa-clock"></i></div>
        <div>
            <div class="event-stat-value"><?= $upcoming_count ?></div>
            <div class="event-stat-label">Upcoming</div>
        </div>
    </div>
    <div class="event-stat-card">
        <div class="event-stat-icon" style="background:linear-gradient(135deg,var(--indigo-600),var(--accent))"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="event-stat-value"><?= $completed_count ?></div>
            <div class="event-stat-label">Completed</div>
        </div>
    </div>
    <div class="event-stat-card">
        <div class="event-stat-icon icon-danger"><i class="fas fa-ban"></i></div>
        <div>
            <div class="event-stat-value"><?= $cancelled_count ?></div>
            <div class="event-stat-label">Cancelled</div>
        </div>
    </div>
</div>

<!-- Month History Chips -->
<?php if (!empty($event_months)): ?>
<div class="month-history">
    <small style="color:var(--muted);font-weight:700;font-size:11px;letter-spacing:0.5px;text-transform:uppercase;">
        <i class="fas fa-history"></i> Event History
    </small>
    <div class="mt-2">
        <?php foreach ($event_months as $em): ?>
        <a href="?month=<?= htmlspecialchars($em['ym']) ?>" 
           class="month-chip <?= ($filter_month === $em['ym']) ? 'active' : '' ?>">
            <?= htmlspecialchars($em['label']) ?>
            <span class="chip-count"><?= $em['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap" style="width:100%;">
        <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="Upcoming" <?= $filter_status==='Upcoming'?'selected':'' ?>>Upcoming</option>
            <option value="Completed" <?= $filter_status==='Completed'?'selected':'' ?>>Completed</option>
            <option value="Cancelled" <?= $filter_status==='Cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category===$cat?'selected':'' ?>>
                <?= htmlspecialchars($cat) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-purple btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <?php if (!empty($filter_status) || !empty($filter_category) || $filter_month !== date('Y-m')): ?>
        <a href="events.php" class="btn btn-sm" style="color:var(--muted);"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Events Table -->
<div class="table-card">
    <?php if (empty($events)): ?>
    <div class="text-center py-5" style="color:var(--muted);">
        <i class="fas fa-calendar-xmark" style="font-size:48px;opacity:0.3;"></i>
        <p class="mt-3 mb-0" style="font-size:14px;font-weight:600;">No events found for <?= date('F Y', strtotime($filter_month . '-01')) ?></p>
        <p style="font-size:12px;">Create a new event or change the filter.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $evt): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="event-icon-cell">
                                <i class="<?= htmlspecialchars($evt['icon']) ?>"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($evt['title']) ?></div>
                                <?php if (!empty($evt['description'])): ?>
                                <div style="font-size:11px;color:var(--muted);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($evt['description']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= date('d M Y', strtotime($evt['event_date'])) ?></div>
                        <div style="font-size:11px;color:var(--muted);"><?= date('l', strtotime($evt['event_date'])) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($evt['event_time'])): ?>
                        <span style="font-size:13px;font-weight:600;"><?= date('h:i A', strtotime($evt['event_time'])) ?></span>
                        <?php else: ?>
                        <span style="color:var(--muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($evt['category']) ?></span></td>
                    <td>
                        <?php
                        $badge_class = 'event-badge-upcoming';
                        if ($evt['status'] === 'Completed') $badge_class = 'event-badge-completed';
                        elseif ($evt['status'] === 'Cancelled') $badge_class = 'event-badge-cancelled';
                        ?>
                        <span class="event-badge <?= $badge_class ?>"><?= $evt['status'] ?></span>
                    </td>
                    <td><span style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($evt['creator_name'] ?? 'Unknown') ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="event_details.php?id=<?= $evt['id'] ?>" class="btn btn-sm btn-outline-purple" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-purple" title="Edit" onclick="editEvent(<?= htmlspecialchars(json_encode($evt)) ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if ($evt['status'] !== 'Cancelled'): ?>
                            <button class="btn btn-sm" style="color:var(--crimson);border:1px solid rgba(220,38,38,0.2);" title="Cancel Event"
                                    onclick="cancelEvent(<?= $evt['id'] ?>, '<?= htmlspecialchars(addslashes($evt['title']), ENT_QUOTES) ?>')">
                                <i class="fas fa-ban"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($isSA): ?>
                            <button class="btn btn-sm" style="color:var(--crimson);border:1px solid rgba(220,38,38,0.2);" title="Delete"
                                    onclick="deleteEvent(<?= $evt['id'] ?>, '<?= htmlspecialchars(addslashes($evt['title']), ENT_QUOTES) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle"><i class="fas fa-plus"></i> Create Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <input type="hidden" id="editEventId" value="">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Event Title *</label>
                        <input type="text" class="form-control" id="eventTitle" placeholder="e.g. Quiz Competition" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Status</label>
                        <select class="form-select" id="eventStatus">
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Event Date *</label>
                        <input type="date" class="form-control" id="eventDate" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Event Time (optional)</label>
                        <input type="time" class="form-control" id="eventTime">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Category</label>
                        <select class="form-select" id="eventCategory">
                            <?php foreach ($category_presets as $cp): ?>
                            <option value="<?= htmlspecialchars($cp) ?>"><?= htmlspecialchars($cp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Description</label>
                        <textarea class="form-control" id="eventDescription" rows="3" placeholder="Brief description of the event..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="font-weight:700;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Icon</label>
                        <input type="hidden" id="eventIcon" value="fas fa-calendar-alt">
                        <div class="icon-picker-grid">
                            <?php foreach ($icon_presets as $cls => $lbl): ?>
                            <div class="icon-picker-item <?= $cls === 'fas fa-calendar-alt' ? 'selected' : '' ?>" data-icon="<?= htmlspecialchars($cls) ?>" onclick="selectIcon(this)">
                                <i class="<?= htmlspecialchars($cls) ?>"></i>
                                <span><?= htmlspecialchars($lbl) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);">
                <button type="button" class="btn btn-sm" style="color:var(--muted);" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-purple" id="saveEventBtn" onclick="saveEvent()">
                    <i class="fas fa-check"></i> Save Event
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function selectIcon(el) {
    document.querySelectorAll('.icon-picker-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('eventIcon').value = el.dataset.icon;
}

function resetEventForm() {
    document.getElementById('editEventId').value = '';
    document.getElementById('eventTitle').value = '';
    document.getElementById('eventDescription').value = '';
    document.getElementById('eventDate').value = '';
    document.getElementById('eventTime').value = '';
    document.getElementById('eventCategory').value = 'General';
    document.getElementById('eventStatus').value = 'Upcoming';
    document.getElementById('eventIcon').value = 'fas fa-calendar-alt';
    document.querySelectorAll('.icon-picker-item').forEach(i => i.classList.remove('selected'));
    document.querySelector('.icon-picker-item[data-icon="fas fa-calendar-alt"]').classList.add('selected');
    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-plus"></i> Create Event';
    document.getElementById('saveEventBtn').innerHTML = '<i class="fas fa-check"></i> Save Event';
}

function editEvent(evt) {
    document.getElementById('editEventId').value = evt.id;
    document.getElementById('eventTitle').value = evt.title;
    document.getElementById('eventDescription').value = evt.description || '';
    document.getElementById('eventDate').value = evt.event_date;
    document.getElementById('eventTime').value = evt.event_time || '';
    document.getElementById('eventCategory').value = evt.category;
    document.getElementById('eventStatus').value = evt.status;
    document.getElementById('eventIcon').value = evt.icon;
    
    // Select icon in picker
    document.querySelectorAll('.icon-picker-item').forEach(i => {
        i.classList.toggle('selected', i.dataset.icon === evt.icon);
    });
    
    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-pen"></i> Edit Event';
    document.getElementById('saveEventBtn').innerHTML = '<i class="fas fa-check"></i> Update Event';
    
    var modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
}

function saveEvent() {
    var eventId = document.getElementById('editEventId').value;
    var title = document.getElementById('eventTitle').value.trim();
    var date = document.getElementById('eventDate').value;
    
    if (!title || !date) {
        alert('Title and date are required.');
        return;
    }
    
    var btn = document.getElementById('saveEventBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    var formData = new FormData();
    formData.append('action', eventId ? 'update_event' : 'create_event');
    if (eventId) formData.append('event_id', eventId);
    formData.append('title', title);
    formData.append('description', document.getElementById('eventDescription').value.trim());
    formData.append('event_date', date);
    formData.append('event_time', document.getElementById('eventTime').value);
    formData.append('category', document.getElementById('eventCategory').value);
    formData.append('icon', document.getElementById('eventIcon').value);
    formData.append('status', document.getElementById('eventStatus').value);
    
    fetch('ajax/manage_events.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to save event.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Save Event';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Save Event';
        });
}

function cancelEvent(id, title) {
    if (!confirm('Cancel event "' + title + '"? This will mark it as Cancelled.')) return;
    
    var formData = new FormData();
    formData.append('action', 'cancel_event');
    formData.append('event_id', id);
    
    fetch('ajax/manage_events.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Failed to cancel event.');
        });
}

function deleteEvent(id, title) {
    if (!confirm('PERMANENTLY delete event "' + title + '"? This cannot be undone.')) return;
    
    var formData = new FormData();
    formData.append('action', 'delete_event');
    formData.append('event_id', id);
    
    fetch('ajax/manage_events.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Failed to delete event.');
        });
}
</script>

<?php include 'includes/footer.php'; ?>
