<?php
session_start();
require_once 'config/email-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); die('طريقة طلب غير صحيحة.');
}

// منع الإرسال المتكرر
$now = time();
if (isset($_SESSION['last_submit_time']) && ($now - $_SESSION['last_submit_time']) < 5) {
    http_response_code(429);
    die('يرجى الانتظار ' . (5 - ($now - $_SESSION['last_submit_time'])) . ' ثانية.');
}
$hash = md5(serialize($_POST));
if (isset($_SESSION['last_form_hash']) && $_SESSION['last_form_hash'] === $hash) {
    http_response_code(429); die('تم إرسال هذه الرسالة بالفعل.');
}

// استقبال وتنظيف الحقول
// ✅ كل حقول الفورم: name, phone, email, company, service, date, notes
$name    = strip_tags(trim($_POST['name']    ?? ''));
$phone   = strip_tags(trim($_POST['phone']   ?? ''));
$email   = trim($_POST['email']              ?? '');
$company = strip_tags(trim($_POST['company'] ?? ''));
$service = strip_tags(trim($_POST['service'] ?? ''));
$date    = strip_tags(trim($_POST['date']    ?? ''));
$notes   = strip_tags(trim($_POST['notes']   ?? ''));

// التحقق من الحقول الإلزامية
if (empty($name) || empty($phone) || empty($service)) {
    http_response_code(400);
    die('يرجى ملء الحقول المطلوبة: الاسم والجوال والباقة.');
}

// التحقق من البريد إن أُدخل
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); die('البريد الإلكتروني غير صحيح.');
}

// تجهيز مصفوفة البيانات الكاملة
$data = [
    'name'    => $name,
    'phone'   => $phone,
    'email'   => $email,      // اختياري
    'company' => $company,    // اختياري
    'service' => $service,    // ✅ عربي مباشرة من الفورم
    'date'    => $date,       // اختياري
    'notes'   => $notes,      // اختياري
];

if (sendEmail($data, 'ar')) {
    sendConfirmationEmail($data, 'ar'); // يُرسل فقط إذا أُدخل بريد صحيح
    $_SESSION['last_submit_time'] = $now;
    $_SESSION['last_form_hash']   = $hash;
    http_response_code(200);
    echo 'success';
} else {
    http_response_code(500);
    echo 'فشل الإرسال. يرجى المحاولة لاحقاً.';
}
?>