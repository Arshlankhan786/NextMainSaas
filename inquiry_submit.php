<?php
require_once 'admin/config/database.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($mobile)) {
        $response['message'] = "Name and mobile are required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $response['message'] = "Please enter a valid 10-digit mobile number.";
    } else {
        $name = $conn->real_escape_string($name);
        $mobile = $conn->real_escape_string($mobile);
        $email = $conn->real_escape_string($email);
        $course = $conn->real_escape_string($course);
        $message = $conn->real_escape_string($message);

        $stmt = $conn->prepare("INSERT INTO inquiries (name, mobile, email, course_interested, message, source) VALUES (?, ?, ?, ?, ?, 'website')");
        $stmt->bind_param("sssss", $name, $mobile, $email, $course, $message);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Thank you! Your inquiry has been submitted successfully. We'll contact you soon.";
        } else {
            $response['message'] = "Sorry, something went wrong. Please try again.";
        }
        $stmt->close();
    }
} else {
    $response['message'] = "Invalid request.";
}

// Return JSON if AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redirect for normal form submission
$_SESSION['inquiry_response'] = $response;
header('Location: contact.php');
exit;
