<?php
/**
 * Email Configuration for Password Reset
 * Using PHPMailer or PHP's mail() function
 */

// Email settings - UPDATE THESE WITH YOUR SMTP DETAILS
define('SMTP_HOST', 'smtp.gmail.com'); // Your SMTP server
define('SMTP_PORT', 587); // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password'); // Your email password or app password
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Academy Fees Management');

// For Gmail: You need to create an "App Password"
// Go to: Google Account > Security > 2-Step Verification > App Passwords

/**
 * Send email using PHP mail() function
 * Simple version without external libraries
 */
function sendSimpleEmail($to, $subject, $message)
{
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";

    return mail($to, $subject, $message, $headers);
}

/**
 * Send email using PHPMailer (recommended for production)
 * Install: composer require phpmailer/phpmailer
 */
function sendEmail($to, $subject, $htmlMessage)
{
    // If PHPMailer is available, use it
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendWithPHPMailer($to, $subject, $htmlMessage);
    }

    // Fallback to simple PHP mail()
    return sendSimpleEmail($to, $subject, $htmlMessage);
}

/**
 * PHPMailer implementation (if installed)
 */
function sendWithPHPMailer($to, $subject, $htmlMessage)
{
    require_once 'vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);

        $mail->send();
        return true;
    }
    catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Generate password reset email HTML
 */
function getPasswordResetEmailHTML($recipientName, $resetLink, $expiryMinutes = 30)
{
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #7c3aed; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Academy Fees Management</h1>
            <p>Password Reset Request</p>
        </div>
        <div class="content">
            <p>Hello <strong>{$recipientName}</strong>,</p>
            
            <p>We received a request to reset your password. Click the button below to reset it:</p>
            
            <p style="text-align: center;">
                <a href="{$resetLink}" class="button">Reset Password</a>
            </p>
            
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: white; padding: 10px; border-radius: 5px;">
                {$resetLink}
            </p>
            
            <p><strong>⚠️ Important:</strong></p>
            <ul>
                <li>This link will expire in <strong>{$expiryMinutes} minutes</strong></li>
                <li>If you didn't request this reset, please ignore this email</li>
                <li>Your password won't change until you create a new one</li>
            </ul>
        </div>
        <div class="footer">
            <p>© 2025 Academy Fees Management System</p>
            <p>This is an automated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>