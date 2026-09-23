<?php
include 'includes/header.php';

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $type = $_POST['type'];

    if (empty($title) || empty($message)) {
        $error = "Title and message are required.";
    } else {

        if ($student_id === 'all') {
            // SEND TO ALL ACTIVE STUDENTS
            $students_all = $conn->query("
                SELECT id FROM students 
                WHERE status = 'Active' AND login_enabled = 1
            ");

            $stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");

            while ($std = $students_all->fetch_assoc()) {
                $std_id = $std['id'];
                $stmt->bind_param("isss", $std_id, $title, $message, $type);
                $stmt->execute();
            }

            $stmt->close();
            $success = "Notification sent to ALL students!";
            $title = $message = '';

        } else {
            // SEND TO SINGLE STUDENT
            $student_id = (int)$student_id;

            $stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $student_id, $title, $message, $type);

            if ($stmt->execute()) {
                $success = "Notification sent successfully!";
                $title = $message = '';
            } else {
                $error = "Failed to send notification.";
            }

            $stmt->close();
        }
    }
}

// ⚠️ FIX: Use different variable name for URL parameter
$preselected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_name = '';
if ($preselected_student_id > 0) {
    $result = $conn->query("SELECT full_name FROM students WHERE id = $preselected_student_id AND status = 'Active'");
    if ($result->num_rows > 0) {
        $student_name = $result->fetch_assoc()['full_name'];
    }
}

// Get all active students (ALWAYS show all students in dropdown)
$students = $conn->query("SELECT id, student_code, full_name FROM students WHERE status = 'Active' AND login_enabled = 1 ORDER BY full_name");
?>

<div class="page-header">
    <h2><i class="fas fa-paper-plane text-purple"></i> Send Notification to Student</h2>
    <p class="text-muted mb-0">Send important messages to students</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Select Student *</label>
                    <select class="form-select" name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <option value="all">📢 All Students</option>
                        <?php while ($s = $students->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($preselected_student_id == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['full_name']) . ' (' . $s['student_code'] . ')'; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notification Type *</label>
                    <select class="form-select" name="type" required>
                        <option value="info">Information</option>
                        <option value="warning">Warning</option>
                        <option value="success">Success</option>
                        <option value="payment">Payment Related</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" name="title" 
                           value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" 
                           placeholder="Enter notification title" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Message *</label>
                    <textarea class="form-control" name="message" rows="5" 
                              placeholder="Enter your message here..." required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-purple">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </button>
                <a href="students.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="text-purple mb-3"><i class="fas fa-lightbulb"></i> Quick Templates</h5>
            
            <div class="mb-3">
                <button class="btn btn-sm btn-outline-primary w-100 text-start" onclick="useTemplate('Payment Reminder', 'Your course fees payment is pending. Please make the payment at the earliest to avoid any inconvenience.', 'payment')">
                    <i class="fa-solid fa-indian-rupee-sign"></i> Payment Reminder
                </button>
            </div>
            
            <div class="mb-3">
                <button class="btn btn-sm btn-outline-success w-100 text-start" onclick="useTemplate('Payment Received', 'Thank you! Your payment has been received successfully.', 'success')">
                    <i class="fas fa-check-circle"></i> Payment Received
                </button>
            </div>
            
            <div class="mb-3">
                <button class="btn btn-sm btn-outline-warning w-100 text-start" onclick="useTemplate('Course Update', 'There is an update regarding your course. Please check your dashboard for more details.', 'info')">
                    <i class="fas fa-book"></i> Course Update
                </button>
            </div>
            
            <div class="mb-3">
                <button class="btn btn-sm btn-outline-danger w-100 text-start" onclick="useTemplate('Urgent: Action Required', 'This is an urgent notification. Please contact the academy office immediately.', 'warning')">
                    <i class="fas fa-exclamation-triangle"></i> Urgent Notice
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function useTemplate(title, message, type) {
    document.querySelector('[name="title"]').value = title;
    document.querySelector('[name="message"]').value = message;
    document.querySelector('[name="type"]').value = type;
}
</script>

<?php include 'includes/footer.php'; ?>