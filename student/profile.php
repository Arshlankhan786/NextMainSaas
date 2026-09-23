<?php
include 'includes/header.php';

$msg_success = ''; $msg_error = '';

// Full student data
$student_details = $conn->query("
    SELECT s.*, c.name as course_name, cat.name as category_name
    FROM students s
    JOIN courses c ON s.course_id = c.id
    JOIN categories cat ON s.category_id = cat.id
    WHERE s.id = $sid
")->fetch_assoc();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $email   = trim($_POST['email']);
        $phone   = trim($_POST['phone']);
        $address = trim($_POST['address']);
        if (empty($phone)) {
            $msg_error = "Phone number is required.";
        } else {
            $stmt = $conn->prepare("UPDATE students SET email=?,phone=?,address=? WHERE id=?");
            $stmt->bind_param("sssi", $email, $phone, $address, $sid);
            if ($stmt->execute()) {
                $msg_success = "Profile updated successfully!";
                $student_details = $conn->query("
                    SELECT s.*, c.name as course_name, cat.name as category_name
                    FROM students s JOIN courses c ON s.course_id = c.id
                    JOIN categories cat ON s.category_id = cat.id
                    WHERE s.id = $sid")->fetch_assoc();
            } else { $msg_error = "Failed to update profile."; }
            $stmt->close();
        }
    }
    if ($_POST['action'] === 'change_password') {
        $cur = $_POST['current_password'];
        $new = $_POST['new_password'];
        $con = $_POST['confirm_password'];
        $res = $conn->query("SELECT password FROM students WHERE id = $sid");
        $usr = $res->fetch_assoc();
        if (!password_verify($cur, $usr['password'])) { $msg_error = "Current password is incorrect."; }
        elseif ($new !== $con) { $msg_error = "New passwords do not match."; }
        elseif (strlen($new) < 6) { $msg_error = "Password must be at least 6 characters."; }
        else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE students SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed, $sid);
            $msg_success = $stmt->execute() ? "Password changed successfully!" : "Failed to change password.";
            $stmt->close();
        }
    }
}

$has_photo = !empty($student_details['photo']) && file_exists(__DIR__ . '/../admin/' . $student_details['photo']);
?>

<?php if ($msg_success): ?>
<div class="alert-block alert-block-success fade-up">
  <i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($msg_success); ?></span>
  <button class="btn-close-alert">✕</button>
</div>
<?php endif; ?>
<?php if ($msg_error): ?>
<div class="alert-block alert-block-danger fade-up">
  <i class="fas fa-exclamation-circle"></i> <span><?php echo htmlspecialchars($msg_error); ?></span>
  <button class="btn-close-alert">✕</button>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header-block fade-up">
  <div>
    <h2><i class="fas fa-user-circle" style="font-size:16px;-webkit-text-fill-color:var(--purple-light);"></i> My Profile</h2>
    <p>View and update your personal information</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start;" class="profile-grid">

  <!-- LEFT: PROFILE CARD -->
  <div style="display:flex;flex-direction:column;gap:14px;">

    <div class="glass-card accent-purple fade-up delay-1">
      <div class="glass-card-body" style="text-align:center;padding:24px 16px 20px;">
        <!-- Avatar -->
        <div style="margin-bottom:14px;">
          <?php if ($has_photo): ?>
          <img src="../admin/<?php echo htmlspecialchars($student_details['photo']); ?>"
               style="width:90px;height:90px;border-radius:50%;object-fit:cover;
                      border:3px solid rgba(124,58,237,0.5);
                      box-shadow:0 0 20px rgba(124,58,237,0.3);">
          <?php else: ?>
          <div style="width:90px;height:90px;border-radius:50%;
                      background:linear-gradient(135deg,var(--purple),var(--indigo));
                      display:inline-flex;align-items:center;justify-content:center;
                      font-size:36px;color:#fff;
                      box-shadow:0 0 0 3px rgba(124,58,237,0.3),0 0 24px rgba(124,58,237,0.25);">
            <i class="fas fa-user-graduate"></i>
          </div>
          <?php endif; ?>
        </div>

        <div style="font-size:16px;font-weight:800;margin-bottom:4px;">
          <?php echo htmlspecialchars($student_details['full_name']); ?>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
          <?php echo htmlspecialchars($student_details['student_code']); ?>
        </div>
        <span class="badge-pill badge-green">
          <i class="fas fa-circle" style="font-size:6px;"></i>
          <?php echo htmlspecialchars($student_details['status']); ?>
        </span>
      </div>

      <div style="padding:0 16px 16px;">
        <div class="profile-info-row">
          <label><i class="fas fa-folder" style="width:14px;"></i> Category</label>
          <span><?php echo htmlspecialchars($student_details['category_name']); ?></span>
        </div>
        <div class="profile-info-row">
          <label><i class="fas fa-book" style="width:14px;"></i> Course</label>
          <span style="font-size:12px;text-align:right;max-width:130px;"><?php echo htmlspecialchars($student_details['course_name']); ?></span>
        </div>
        <div class="profile-info-row">
          <label><i class="fas fa-clock" style="width:14px;"></i> Duration</label>
          <span><?php echo $student_details['duration_months']; ?> months</span>
        </div>
        <div class="profile-info-row">
          <label><i class="fas fa-calendar" style="width:14px;"></i> Enrolled</label>
          <span><?php echo date('d M Y', strtotime($student_details['enrollment_date'])); ?></span>
        </div>
      </div>
    </div>

  </div>

  <!-- RIGHT: EDIT FORMS -->
  <div style="display:flex;flex-direction:column;gap:14px;">

    <!-- Update Contact -->
    <div class="glass-card fade-up delay-2">
      <div class="glass-card-body">
        <div class="section-header">
          <div class="section-title"><i class="fas fa-edit"></i> Update Contact Info</div>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="d-grid-2" style="gap:12px;margin-bottom:12px;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control"
                     value="<?php echo htmlspecialchars($student_details['full_name']); ?>" disabled>
              <div class="form-hint">Contact admin to change name</div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Student Code</label>
              <input type="text" class="form-control"
                     value="<?php echo htmlspecialchars($student_details['student_code']); ?>" disabled>
            </div>
          </div>
          <div class="d-grid-2" style="gap:12px;margin-bottom:12px;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" name="email"
                     value="<?php echo htmlspecialchars($student_details['email']); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Phone *</label>
              <input type="tel" class="form-control" name="phone" required
                     value="<?php echo htmlspecialchars($student_details['phone']); ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($student_details['address'] ?? ''); ?></textarea>
          </div>
          <button type="submit" class="btn btn-purple">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="glass-card fade-up delay-3">
      <div class="glass-card-body">
        <div class="section-header">
          <div class="section-title"><i class="fas fa-lock"></i> Change Password</div>
        </div>
        <form method="POST" id="passwordForm">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" name="current_password" required placeholder="Enter current password">
          </div>
          <div class="d-grid-2" style="gap:12px;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" name="new_password" id="new_password" required placeholder="Min 6 characters">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" name="confirm_password" id="confirm_password" required placeholder="Repeat new password">
            </div>
          </div>
          <div style="margin-top:14px;">
            <button type="submit" class="btn btn-ghost">
              <i class="fas fa-key"></i> Update Password
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<style>
@media(max-width:768px){
  .profile-grid{grid-template-columns:1fr!important;}
}
</style>

<script>
document.getElementById('passwordForm').addEventListener('submit', function(e) {
  const np = document.getElementById('new_password').value;
  const cp = document.getElementById('confirm_password').value;
  if (np !== cp) { e.preventDefault(); alert('New passwords do not match!'); }
});
</script>

<?php include 'includes/footer.php'; ?>