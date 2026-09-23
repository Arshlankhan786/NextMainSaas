<?php
session_start();
require_once "config/database.php";
require_once "config/email.php";

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username, full_name FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Save token to database
            $insertStmt = $conn->prepare("INSERT INTO password_resets (admin_id, token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iss", $admin['id'], $token, $expires);
            
            if ($insertStmt->execute()) {
                // Create reset link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = "{$protocol}://{$host}/reset_password.php?token={$token}";
                
                // Send email
                $emailHTML = getPasswordResetEmailHTML($admin['full_name'], $resetLink, 30);
                $emailSent = sendEmail($email, "Password Reset Request - Academy Fees Management", $emailHTML);
                
                if ($emailSent) {
                    $message = "Password reset instructions have been sent to your email.";
                } else {
                    // Even if email fails, show success message for security
                    // (don't reveal if email exists or not)
                    $message = "If the email exists in our system, you will receive password reset instructions.";
                }
            } else {
                $error = "Something went wrong. Please try again.";
            }
            
            $insertStmt->close();
        } else {
            // Don't reveal if email doesn't exist (security best practice)
            $message = "If the email exists in our system, you will receive password reset instructions.";
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
    <title>Forgot Password - Academy Fees Management</title>
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
        
        .forgot-container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }
        
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-dark));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .forgot-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .forgot-body {
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
        
        .back-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-login a {
            color: var(--purple-primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-login a:hover {
            color: var(--purple-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <i class="fas fa-key"></i>
                <h2>Forgot Password?</h2>
                <p class="mb-0">Enter your email to reset your password</p>
            </div>
            <div class="forgot-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                    <div class="text-center mt-4">
                        <p class="text-muted">Check your email inbox (and spam folder) for the reset link.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required autofocus>
                            </div>
                            <small class="text-muted">We'll send password reset instructions to this email</small>
                        </div>
                        
                        <button type="submit" class="btn btn-reset w-100">
                            <i class="fas fa-paper-plane"></i> Send Reset Link
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="back-login">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>