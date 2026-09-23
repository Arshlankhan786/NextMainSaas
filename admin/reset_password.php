<?php
session_start();
require_once "config/database.php";

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";
$tokenValid = false;
$adminName = "";

// Verify token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("
        SELECT pr.id, pr.admin_id, pr.expires_at, pr.used, a.full_name, a.email
        FROM password_resets pr
        JOIN admins a ON pr.admin_id = a.id
        WHERE pr.token = ? AND pr.used = FALSE
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $resetData = $result->fetch_assoc();
        
        // Check if token expired
        if (strtotime($resetData['expires_at']) > time()) {
            $tokenValid = true;
            $adminName = $resetData['full_name'];
        } else {
            $error = "This reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid or already used reset link.";
    }
    
    $stmt->close();
} else {
    $error = "No reset token provided.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        // Get admin_id from token
        $stmt = $conn->prepare("SELECT admin_id FROM password_resets WHERE token = ? AND used = FALSE");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $resetData = $result->fetch_assoc();
            $adminId = $resetData['admin_id'];
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updateStmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $adminId);
            
            if ($updateStmt->execute()) {
                // Mark token as used
                $markUsedStmt = $conn->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
                $markUsedStmt->bind_param("s", $token);
                $markUsedStmt->execute();
                $markUsedStmt->close();
                
                $success = "Password reset successful! You can now login with your new password.";
                $tokenValid = false; // Prevent form from showing again
            } else {
                $error = "Failed to update password. Please try again.";
            }
            
            $updateStmt->close();
        } else {
            $error = "Invalid reset token.";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Academy Fees Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --purple-primary: #7c3aed;
            --purple-dark: #5b21b6;
            --purple-light: #a78bfa;
            --purple-lighter: #e9d5ff;
        }
        
        body {
            background: linear-gradient(135deg, var(--purple-primary) 0%, var(--purple-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .reset-container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }
        
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .reset-header {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .reset-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .reset-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--purple-primary);
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.25);
        }
        
        .input-group-text {
            background: var(--purple-lighter);
            border: 2px solid var(--purple-lighter);
            color: var(--purple-dark);
            border-radius: 10px 0 0 10px;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: transform 0.3s;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(124, 58, 237, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .password-requirements {
            background: var(--purple-lighter);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <i class="fas fa-lock"></i>
                <h2>Reset Password</h2>
                <?php if ($tokenValid): ?>
                <p class="mb-0">Hi, <?php echo htmlspecialchars($adminName); ?>!</p>
                <?php endif; ?>
            </div>
            <div class="reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                    <?php if (!$tokenValid): ?>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php" class="btn btn-reset">
                                Request New Reset Link
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-reset">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($tokenValid && !$success): ?>
                    <form method="POST" id="resetForm">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="new_password" id="new_password" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="password-requirements">
                            <strong><i class="fas fa-info-circle"></i> Password Requirements:</strong>
                            <ul>
                                <li>Minimum 6 characters long</li>
                                <li>Both passwords must match</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-reset w-100 mt-3">
                            <i class="fas fa-check"></i> Reset Password
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none text-purple">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>