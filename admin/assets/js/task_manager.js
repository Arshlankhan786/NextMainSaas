// =============================================
// FOCUS MODE - TASK MANAGER
// Frontend Logic & Interactivity
// =============================================

let currentUserId = null;
let showCompleted = false;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDateTime();
    initializeEventListeners();
    loadTasks();
    
    // Visibility-aware polling — stops when tab is hidden to save DB connections
    var _taskPoll = null;
    function startTaskPoll() {
        if (!_taskPoll) _taskPoll = setInterval(function() { if (!document.hidden) loadTasks(); }, 300000);
    }
    function stopTaskPoll() {
        if (_taskPoll) { clearInterval(_taskPoll); _taskPoll = null; }
    }
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) { stopTaskPoll(); } else { loadTasks(); startTaskPoll(); }
    });
    startTaskPoll();
});

// ===== DATE & TIME =====
function initializeDateTime() {
    function updateDateTime() {
        const now = new Date();
        
        // Day
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        document.getElementById('current-day').textContent = days[now.getDay()];
        
        // Date
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateStr = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
        document.getElementById('current-date').textContent = dateStr;
        
        // Time
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        const timeStr = `${hours}:${minutes}:${seconds} ${ampm}`;
        document.getElementById('current-time').textContent = timeStr;
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
}

// ===== EVENT LISTENERS =====
function initializeEventListeners() {
    // Add Task Button
    document.getElementById('btn-add-task').addEventListener('click', function() {
        $('#taskModal').modal('show');
        document.getElementById('task-form').reset();
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="start_date"]').value = today;
        document.querySelector('input[name="end_date"]').value = today;
    });
    
    // Repeat Toggle
    document.getElementById('repeat_enabled').addEventListener('change', function() {
        document.getElementById('repeat-options').style.display = 
            this.checked ? 'block' : 'none';
    });
    
    // Task Form Submit
    document.getElementById('task-form').addEventListener('submit', function(e) {
        e.preventDefault();
        createTask();
    });
    
    // User Filter (Super Admin only)
    const userFilter = document.getElementById('user-filter');
    if (userFilter) {
        userFilter.addEventListener('change', function() {
            currentUserId = this.value === 'all' ? null : this.value;
            loadTasks();
        });
    }
    
    // Toggle Completed Tasks
    document.getElementById('toggle-completed').addEventListener('click', function() {
        showCompleted = !showCompleted;
        this.innerHTML = showCompleted ? 
            '<i class="fas fa-eye-slash"></i> Hide Completed' : 
            '<i class="fas fa-eye"></i> Show Completed';
        loadTasks();
    });
}

// ===== LOAD TASKS =====
function loadTasks() {
    const params = new URLSearchParams();
    if (currentUserId) params.append('user_id', currentUserId);
    params.append('show_completed', showCompleted ? '1' : '0');
    
    fetch(`ajax/get_tasks.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTasks(data.tasks);
            } else {
                console.error('Error loading tasks:', data.message);
            }
        })
        .catch(error => console.error('Fetch error:', error));
}

// ===== RENDER TASKS =====
function renderTasks(tasks) {
    // Clear all columns
    const timeBlocks = ['all_day', '6-9am', '9-2pm', '2-7pm', '7-12am'];
    timeBlocks.forEach(block => {
        const container = document.getElementById(`tasks-${block}`);
        container.innerHTML = '';
    });
    
    // Group tasks by time block
    const tasksByBlock = {};
    timeBlocks.forEach(block => tasksByBlock[block] = []);
    
    tasks.forEach(task => {
        const block = task.time_block;
        if (tasksByBlock[block]) {
            tasksByBlock[block].push(task);
        }
    });
    
    // Render each block
    timeBlocks.forEach(block => {
        const container = document.getElementById(`tasks-${block}`);
        const taskList = tasksByBlock[block];
        
        // Update count
        const countEl = container.closest('.time-column').querySelector('.task-count');
        countEl.textContent = taskList.length;
        
        if (taskList.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>No tasks</p></div>';
        } else {
            taskList.forEach(task => {
                container.appendChild(createTaskElement(task));
            });
        }
    });
}

// ===== CREATE TASK ELEMENT =====
function createTaskElement(task) {
    const div = document.createElement('div');
    div.className = `task-item ${task.status}`;
    div.dataset.taskId = task.id;
    
    let repeatBadge = '';
    if (task.repeat_enabled == 1 && task.repeat_count > 0) {
        repeatBadge = `<span class="task-repeat-badge">🔄 ${task.repeat_count}</span>`;
    }
    
    div.innerHTML = `
        ${repeatBadge}
        <div class="task-title">${escapeHtml(task.title)}</div>
        <div class="task-meta">
            <span class="task-category">${escapeHtml(task.category)}</span>
            <span class="task-dates">${formatDate(task.start_date)} - ${formatDate(task.end_date)}</span>
        </div>
    `;
    
    // Click to toggle completion (only active tasks)
    if (task.status === 'active') {
        div.addEventListener('click', function() {
            toggleTaskCompletion(task.id);
        });
    }
    
    return div;
}

// ===== TOGGLE TASK COMPLETION =====
function toggleTaskCompletion(taskId) {
    fetch('ajax/toggle_task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ task_id: taskId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTasks(); // Reload to show updated state
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => console.error('Toggle error:', error));
}

// ===== CREATE NEW TASK =====
function createTask() {
    const formData = new FormData(document.getElementById('task-form'));
    
    fetch('ajax/manage_tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#taskModal').modal('hide');
            document.getElementById('task-form').reset();
            loadTasks();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => console.error('Create task error:', error));
}

// ===== UTILITY FUNCTIONS =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[date.getMonth()]} ${date.getDate()}`;
}