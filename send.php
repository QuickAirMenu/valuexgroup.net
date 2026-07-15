<?php
// =====================================
// Value X Group — Contact Form Handler
// =====================================

// Load .env file
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

loadEnv(__DIR__ . '/.env');

// =====================================
// CORS & Headers
// =====================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// =====================================
// Get & Sanitize Input
// =====================================
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$message = trim($_POST['message'] ?? '');

// =====================================
// Validation
// =====================================
$errors = [];

if (empty($name)) {
    $errors[] = 'الاسم مطلوب';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'البريد الإلكتروني غير صحيح';
}

if (empty($message)) {
    $errors[] = 'الرسالة مطلوبة';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// =====================================
// Build HTML Email
// =====================================
$primaryColor = $_ENV['BRAND_PRIMARY_COLOR'] ?? '#CDAA72';
$secondaryColor = $_ENV['BRAND_SECONDARY_COLOR'] ?? '#2D2D1D';
$logoUrl = $_ENV['BRAND_LOGO_URL'] ?? '';
$website = $_ENV['BRAND_WEBSITE'] ?? 'valuexgroup.net';
$companyName = $_ENV['COMPANY_NAME'] ?? 'Value X Group';
$companyNameAr = $_ENV['COMPANY_NAME_AR'] ?? 'مجموعة فاليو إكس لتطوير الأعمال';

$html = "
<!DOCTYPE html>
<html dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: $secondaryColor; padding: 30px; text-align: center; }
        .header img { max-height: 60px; margin-bottom: 15px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; }
        .header p { color: rgba(255,255,255,0.7); font-size: 14px; margin: 5px 0 0; }
        .content { padding: 30px; }
        .field { margin-bottom: 20px; }
        .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .value { font-size: 16px; color: #333; padding: 10px; background: #f9f9f9; border-radius: 4px; border-right: 3px solid $primaryColor; }
        .message-box { background: #f9f9f9; padding: 15px; border-radius: 4px; border-right: 3px solid $primaryColor; white-space: pre-wrap; line-height: 1.6; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #888; }
        .footer a { color: $primaryColor; text-decoration: none; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <img src='$logoUrl' alt='Logo'>
            <h1>$companyNameAr</h1>
            <p>$companyName</p>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>الاسم / Name</div>
                <div class='value'>" . htmlspecialchars($name) . "</div>
            </div>
            <div class='field'>
                <div class='label'>البريد الإلكتروني / Email</div>
                <div class='value'>" . htmlspecialchars($email) . "</div>
            </div>
            <div class='field'>
                <div class='label'>الجوال / Mobile</div>
                <div class='value'>" . htmlspecialchars($phone ?: 'غير محدد') . "</div>
            </div>
            <div class='field'>
                <div class='label'>الخدمة / Service</div>
                <div class='value'>" . htmlspecialchars($service ?: 'غير محدد') . "</div>
            </div>
            <div class='field'>
                <div class='label'>الرسالة / Message</div>
                <div class='message-box'>" . htmlspecialchars($message) . "</div>
            </div>
        </div>
        <div class='footer'>
            <p>تم إرسال هذه الرسالة من <a href='https://$website'>$website</a></p>
            <p>This message was sent from <a href='https://$website'>$website</a></p>
        </div>
    </div>
</body>
</html>";

// =====================================
// Send Email
// =====================================
$to = $_ENV['MAIL_TO'] ?? 'info@valuexgroup.net';
$from = $_ENV['MAIL_FROM'] ?? 'site@valuexgroup.net';
$fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Value X Group';

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: $fromName <$from>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$subject = "رسالة جديدة من $name — $website";

if (mail($to, $subject, $html, $headers)) {
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال رسالتك بنجاح! سنتواصل معك قريباً.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء الإرسال. يرجى المحاولة مرة أخرى.'
    ]);
}
