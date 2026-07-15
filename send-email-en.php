<?php
session_start();
require_once 'config/email-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); die('Invalid request method.');
}

$now = time();
if (isset($_SESSION['last_submit_time']) && ($now - $_SESSION['last_submit_time']) < 5) {
    http_response_code(429);
    die('Please wait ' . (5 - ($now - $_SESSION['last_submit_time'])) . ' seconds.');
}
$hash = md5(serialize($_POST));
if (isset($_SESSION['last_form_hash']) && $_SESSION['last_form_hash'] === $hash) {
    http_response_code(429); die('This request has already been submitted.');
}

// Receive & sanitize — all form fields
$name    = strip_tags(trim($_POST['name']    ?? ''));
$phone   = strip_tags(trim($_POST['phone']   ?? ''));
$email   = trim($_POST['email']              ?? '');
$company = strip_tags(trim($_POST['company'] ?? ''));
$service = strip_tags(trim($_POST['service'] ?? ''));
$date    = strip_tags(trim($_POST['date']    ?? ''));
$notes   = strip_tags(trim($_POST['notes']   ?? ''));

if (empty($name) || empty($phone) || empty($service)) {
    http_response_code(400);
    die('Please fill required fields: name, phone, and package.');
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); die('Invalid email address.');
}

$data = [
    'name'    => $name,
    'phone'   => $phone,
    'email'   => $email,
    'company' => $company,
    'service' => $service,
    'date'    => $date,
    'notes'   => $notes,
];

if (sendEmail($data, 'en')) {
    sendConfirmationEmail($data, 'en');
    $_SESSION['last_submit_time'] = $now;
    $_SESSION['last_form_hash']   = $hash;
    http_response_code(200);
    echo 'success';
} else {
    http_response_code(500);
    echo 'Failed to send. Please try again later.';
}
?>