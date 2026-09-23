<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$group_id = (int)($_GET['id'] ?? 0);

if ($group_id === 0) {
    $_SESSION['error'] = "Invalid group ID!";
    header('Location: student_groups.php');
    exit();
}

// Get group details
$group_query = $conn->query("
    SELECT sg.*, c.name as course_name, c.id as course_id
    FROM student_groups sg
    JOIN courses c ON sg.course_id = c.id
    WHERE sg.id = $group_id
");

if (!$group_query || $group_query->num_rows === 0) {
    $_SESSION['error'] = "Group not found!";
    header('Location: student_groups.php');
    exit();
}

$group = $group_query->fetch_assoc();

// Get members
$members = $conn->query("
    SELECT s.id, s.student_code, s.full_name, s.phone
    FROM student_group_members sgm
    JOIN students s ON sgm.student_id = s.id
    WHERE sgm.group_id = $group_id
    ORDER BY s.full_name
");

// Get topic progress
$topics = $conn->query("
    SELECT ct.id, ct.topic_name, ct.order_index,
           COALESCE(gtp.status, 'upcoming') as status, 
           gtp.start_date, gtp.end_date,
           (SELECT COUNT(*) FROM course_sub_topics WHERE topic_id = ct.id) as sub_count
    FROM course_topics ct
    LEFT JOIN group_topic_progress gtp ON ct.id = gtp.topic_id AND gtp.group_id = $group_id
    WHERE ct.course_id = {$group['course_id']}
    ORDER BY ct.order_index ASC
");

include 'includes/header.php';
?>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="student_groups.php">Groups</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($group['group_name']); ?></li>
        </ol>
    </nav>
    <h2><i class="fas fa-users text-purple"></i> <?php echo htmlspecialchars($group['group_name']); ?></h2>
    <p class="text-muted mb-0">Course: <?php echo htmlspecialchars($group['course_name']); ?></p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Members -->
    <div class="col-lg-4">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="text-purple mb-0">
        <i class="fas fa-users"></i> Group Members 
        <?php echo $members ? '(' . $members->num_rows . ')' : '(0)'; ?>
    </h5>

    <button class="btn btn-sm btn-purple" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="fas fa-plus"></i> Add
    </button>
</div>

            
            <?php if ($members && $members->num_rows > 0): ?>
            <div class="list-group list-group-flush">
                <?php while ($member = $members->fetch_assoc()): ?>
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                            <br><small class="text-muted"><?php echo $member['student_code']; ?></small>
                        </div>
                     <div class="btn-group btn-group-sm">
    <a href="student_details.php?id=<?php echo $member['id']; ?>" class="btn btn-info">
        <i class="fas fa-eye"></i>
    </a>

    <button class="btn btn-danger"
        onclick="removeStudent(<?php echo $member['id']; ?>)">
        <i class="fas fa-trash"></i>
    </button>
</div>

                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">No members yet</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Topic Progress -->
    <div class="col-lg-8">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-list"></i> Topic Progress</h5>
            
            <?php if ($topics && $topics->num_rows > 0): ?>
            <div class="topic-flow">
                <?php 
                $index = 0;
                while ($topic = $topics->fetch_assoc()): 
                    $index++;
                    
                    $status = $topic['status'] ?? 'upcoming';
                    $status_class = 'secondary';
                    $status_icon = 'fa-clock';
                    $status_text = 'Upcoming';
                    
                    if ($status === 'active') {
                        $status_class = 'primary';
                        $status_icon = 'fa-play';
                        $status_text = 'Active';
                    } elseif ($status === 'completed') {
                        $status_class = 'success';
                        $status_icon = 'fa-check';
                        $status_text = 'Completed';
                    }
                ?>
                <div class="card mb-3 border-<?php echo $status_class; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo $index; ?></span>
                                    <strong><?php echo htmlspecialchars($topic['topic_name']); ?></strong>
                                    <span class="badge bg-info"><?php echo $topic['sub_count']; ?> sub-topics</span>
                                </div>
                                
                                <?php if ($topic['start_date']): ?>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> Started: <?php echo date('d M Y', strtotime($topic['start_date'])); ?>
                                </small>
                                <?php endif; ?>
                                
                                <?php if ($topic['end_date']): ?>
                                <small class="text-muted ms-3">
                                    <i class="fas fa-flag-checkered"></i> Completed: <?php echo date('d M Y', strtotime($topic['end_date'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="btn-group btn-group-sm">
                                <?php if ($status === 'upcoming'): ?>
<button 
    type="button"
    class="btn btn-success"
    onclick="startTopic(event, <?php echo $topic['id']; ?>, <?php echo $group_id; ?>)">
    <i class="fas fa-play"></i> Start
</button>

                                <?php elseif ($status === 'active'): ?>
<button type="button" class="btn btn-primary"
        onclick="completeTopic(<?php echo $topic['id']; ?>, <?php echo $group_id; ?>)">
                                    <i class="fas fa-check"></i> Complete
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($topic['sub_count'] > 0): ?>
                                <button class="btn btn-info" onclick="viewSubTopics(<?php echo $topic['id']; ?>, '<?php echo addslashes($topic['topic_name']); ?>')">
                                    <i class="fas fa-list-ul"></i> Sub-Topics
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No topics defined for this course yet. 
                <a href="courses.php" class="alert-link">Add topics to the course first</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sub-Topics View Modal -->
<div class="modal fade" id="subTopicsViewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-list-ul"></i> <span id="viewSubTopicTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="subTopicsViewContent">
                <div class="text-center">
                    <div class="spinner-border text-purple"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus"></i> Add Student to Group
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <select class="form-select" id="newStudentId">
                    <option value="">Select Student</option>
                    <?php
                    $available_students = $conn->query("
                        SELECT id, full_name, student_code
                        FROM students
                        WHERE status='Active'
                        AND id NOT IN (
                            SELECT student_id FROM student_group_members 
                            WHERE group_id = $group_id
                        )
                        ORDER BY full_name
                    ");
                    while ($stu = $available_students->fetch_assoc()):
                    ?>
                    <option value="<?php echo $stu['id']; ?>">
                        <?php echo $stu['student_code']; ?> - <?php echo htmlspecialchars($stu['full_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-purple" onclick="addStudent()">
                    Add Student
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const groupId = <?php echo $group_id; ?>;

function startTopic(e, topicId, groupId) {
    e.preventDefault();
    e.stopPropagation();   // 👈 VERY IMPORTANT

    if (!confirm('Start this topic? This will mark it as active.')) return;

    fetch('ajax/manage_group_topics.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=start_topic&group_id=${groupId}&topic_id=${topicId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Cannot start topic');
        }
    })
    .catch(error => {
        console.error(error);
        alert('JS Error occurred');
    });
}


function completeTopic(topicId, groupId) {
    if (!confirm('Mark this topic as completed? This will auto-start the next topic.')) return;
    
    fetch('ajax/manage_group_topics.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=complete_topic&group_id=${groupId}&topic_id=${topicId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to complete topic');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('An error occurred');
    });
}

function viewSubTopics(topicId, topicName) {
    document.getElementById('viewSubTopicTitle').textContent = topicName;
    
    fetch(`ajax/manage_course_topics.php?action=get_subtopics&topic_id=${topicId}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('subTopicsViewContent');
            
            if (data.success && data.subtopics.length > 0) {
                let html = '<div class="list-group">';
                data.subtopics.forEach((sub, index) => {
                    html += `
                    <div class="list-group-item">
                        <span class="badge bg-secondary me-2">${index + 1}</span>
                        ${sub.sub_topic_name}
                    </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert alert-info">No sub-topics defined</div>';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('subTopicsViewContent').innerHTML = '<div class="alert alert-danger">Failed to load sub-topics</div>';
        });
    
    new bootstrap.Modal(document.getElementById('subTopicsViewModal')).show();
}
function addStudent() {
    const studentId = document.getElementById('newStudentId').value;

    if (!studentId) {
        alert('Select a student');
        return;
    }

    fetch('ajax/manage_group_members.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&group_id=${groupId}&student_id=${studentId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to add');
        }
    });
}

function removeStudent(studentId) {
    if (!confirm('Remove this student from group?')) return;

    fetch('ajax/manage_group_members.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=remove&group_id=${groupId}&student_id=${studentId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to remove');
        }
    });
}

</script>

<?php include 'includes/footer.php'; ?>