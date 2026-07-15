<?php
// =====================================================
// Email Configuration — Special Zone
// Version 6.2 — SMTP Native (no PHPMailer)
// =====================================================

// ─── تحميل .env ─────────────────────────────────────────────────────────────
function sz_load_env(): void {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        error_log('[SZ] .env not found: ' . $envFile);
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (!array_key_exists($k, $_ENV)) {
            putenv("$k=$v");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }
}
sz_load_env();

function _e(string $key, string $fallback = ''): string {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $fallback;
}

// ─── SMTP Sender — SSL port 465 ──────────────────────────────────────────────
// $replyTo = '' : بدون Reply-To (إيميل الشركة الداخلي)
// $replyTo = 'info@...' : العميل لو رد يذهب لـ info@ (إيميل التأكيد للعميل)
function sz_smtp_send(string $toAddr, string $toName, string $subj, string $html, string $replyTo = ''): bool {
    $host     = _e('MAIL_HOST',      'smtp.hostinger.com');
    $port     = (int)_e('MAIL_PORT', '465');
    $user     = _e('MAIL_USERNAME');
    $pass     = _e('MAIL_PASSWORD');
    $from     = _e('MAIL_FROM');
    $fromName = _e('MAIL_FROM_NAME', 'Special Zone');

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);

    $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        error_log("[SZ] SMTP connect failed: {$errno} {$errstr}");
        return false;
    }
    stream_set_timeout($sock, 15);

    $cmd = function(string $line) use ($sock): string {
        fwrite($sock, $line . "\r\n");
        $out = '';
        while (!feof($sock)) {
            $l = fgets($sock, 512);
            $out .= $l;
            if (isset($l[3]) && $l[3] === ' ') break;
        }
        return $out;
    };

    fgets($sock, 512);
    $cmd('EHLO ' . (gethostname() ?: 'localhost'));
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($user));
    $auth = $cmd(base64_encode($pass));

    if (strpos($auth, '235') === false) {
        error_log('[SZ] Auth failed: ' . $auth);
        fclose($sock);
        return false;
    }

    $cmd("MAIL FROM:<{$from}>");
    $cmd("RCPT TO:<{$toAddr}>");
    $cmd('DATA');

    $toField = $toName
        ? '=?UTF-8?B?' . base64_encode($toName) . "?= <{$toAddr}>"
        : $toAddr;

    // Reply-To — يُضاف فقط إذا مُرِّر قيمة
    $replyToHeader = !empty($replyTo) ? "Reply-To: {$replyTo}\r\n" : '';

    $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n"
          . "To: {$toField}\r\n"
          . "Subject: =?UTF-8?B?" . base64_encode($subj) . "?=\r\n"
          . $replyToHeader
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n"
          . "X-Mailer: SZ-Mailer/6.2\r\n"
          . "Message-ID: <" . time() . "." . md5($toAddr . microtime()) . "@specialzone.com.sa>\r\n"
          . "\r\n"
          . chunk_split(base64_encode($html))
          . "\r\n.\r\n";

    $r = $cmd($msg);
    $cmd('QUIT');
    fclose($sock);

    $ok = strpos($r, '250') !== false || strpos($r, '2.0.0') !== false;
    if (!$ok) error_log('[SZ] Send failed: ' . $r);
    return $ok;
}

// ─── Helper: بناء صفوف الجدول ────────────────────────────────────────────────
function _buildRows(array $data, array $L, string $sec): string {
    $e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    $lTd = "padding:13px 14px;border-bottom:1px solid #e8e8e8;background:linear-gradient(135deg,#f8f9fa,#edf0f2);font-weight:700;width:34%;color:{$sec};font-size:14px;";
    $vTd = "padding:13px 14px;border-bottom:1px solid #e8e8e8;color:#333;font-size:14px;";

    $rows  = "<tr><td style='{$lTd}'>{$L['name']}</td><td style='{$vTd}'>{$e($data['name'])}</td></tr>";
    if (!empty($data['phone']))   $rows .= "<tr><td style='{$lTd}'>{$L['phone']}</td><td style='{$vTd}'>{$e($data['phone'])}</td></tr>";
    if (!empty($data['email']))   $rows .= "<tr><td style='{$lTd}'>{$L['email']}</td><td style='{$vTd}'>{$e($data['email'])}</td></tr>";
    if (!empty($data['company'])) $rows .= "<tr><td style='{$lTd}'>{$L['company']}</td><td style='{$vTd}'>{$e($data['company'])}</td></tr>";
    if (!empty($data['service'])) $rows .= "<tr><td style='{$lTd}'>{$L['service']}</td><td style='{$vTd}'><strong>{$e($data['service'])}</strong></td></tr>";
    if (!empty($data['date']))    $rows .= "<tr><td style='{$lTd}'>{$L['date']}</td><td style='{$vTd}'>{$e($data['date'])}</td></tr>";
    if (!empty($data['notes']))   $rows .= "<tr><td style='{$lTd}border-bottom:none;'>{$L['notes']}</td><td style='{$vTd}border-bottom:none;white-space:pre-wrap;'>{$e($data['notes'])}</td></tr>";

    return $rows;
}

// ─── Template: إيميل الشركة (الداخلي) ───────────────────────────────────────
function getEmailTemplate(array $data, string $lang = 'ar'): string {
    $p    = _e('BRAND_PRIMARY_COLOR',   '#86A131');
    $s    = _e('BRAND_SECONDARY_COLOR', '#1E68B2');
    $logo = _e('BRAND_LOGO_URL',        'https://specialzone.com.sa/assets/img/logo.png');
    $co   = $lang === 'ar' ? _e('COMPANY_NAME_AR', 'شركة سبيشال زون') : _e('COMPANY_NAME', 'Special Zone');
    $dir  = $lang === 'ar' ? 'rtl' : 'ltr';

    $L = $lang === 'ar' ? [
        'title'   => '&#x1F4CB; طلب حجز جديد من الموقع',
        'name'    => 'الاسم',     'phone'   => 'الجوال',
        'email'   => 'البريد',    'company' => 'الشركة / المشروع',
        'service' => 'الباقة',    'date'    => 'تاريخ البدء',
        'notes'   => 'ملاحظات',   'rights'  => 'جميع الحقوق محفوظة',
    ] : [
        'title'   => '&#x1F4CB; New Booking Request',
        'name'    => 'Name',      'phone'   => 'Phone',
        'email'   => 'Email',     'company' => 'Company / Project',
        'service' => 'Package',   'date'    => 'Start Date',
        'notes'   => 'Notes',     'rights'  => 'All Rights Reserved',
    ];

    $rows = _buildRows($data, $L, $s);
    $yr   = date('Y');

    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f0f2f5;direction:{$dir};font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' border='0' style='background:#f0f2f5;padding:24px 0;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' border='0' style='max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);'>
  <tr><td style='background:linear-gradient(135deg,{$p} 0%,{$s} 100%);padding:30px 20px;text-align:center;'>
    <table cellpadding='0' cellspacing='0' border='0' style='background:#fff;display:inline-block;padding:10px 24px;border-radius:10px;margin-bottom:14px;'>
      <tr><td><img src='{$logo}' alt='{$co}' style='max-width:140px;height:auto;display:block;' width='140'></td></tr>
    </table>
    <div style='color:#fff;font-size:20px;font-weight:700;'>{$L['title']}</div>
  </td></tr>
  <tr><td style='height:4px;background:linear-gradient(to right,{$p},{$s});'></td></tr>
  <tr><td style='padding:26px 22px;'>
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;'>
      {$rows}
    </table>
  </td></tr>
  <tr><td style='background:#f8f9fa;padding:18px;text-align:center;border-top:1px solid #eee;'>
    <p style='margin:0 0 5px;color:#555;font-size:13px;'>{$L['rights']} &copy; {$yr} <strong style='color:{$p};'>{$co}</strong></p>
    <p style='margin:0;font-size:11px;color:#999;'>برمجة وتصميم <a href='https://airmenu.net' target='_blank' style='color:#3db3c5;text-decoration:none;font-weight:700;'>Air Menu</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>";
}

// ─── Template: تأكيد للعميل ──────────────────────────────────────────────────
function getConfirmationEmailTemplate(array $data, string $lang = 'ar'): string {
    $p    = _e('BRAND_PRIMARY_COLOR',   '#86A131');
    $s    = _e('BRAND_SECONDARY_COLOR', '#1E68B2');
    $logo = _e('BRAND_LOGO_URL',        'https://specialzone.com.sa/assets/img/logo.png');
    $web  = _e('BRAND_WEBSITE',         'SpecialZone.com.sa');
    $coEm = _e('COMPANY_EMAIL',         'info@SpecialZone.com.sa');
    $co   = $lang === 'ar' ? _e('COMPANY_NAME_AR', 'شركة سبيشال زون') : _e('COMPANY_NAME', 'Special Zone');
    $dir  = $lang === 'ar' ? 'rtl' : 'ltr';
    $e    = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

    $L = $lang === 'ar' ? [
        'title'    => 'شكراً لتواصلك معنا! &#x1F389;',
        'greeting' => 'عزيزنا',
        'confirm'  => 'تم استلام طلبك بنجاح. سيتواصل معك فريقنا خلال 24 ساعة لتأكيد الحجز.',
        'copy'     => 'نسخة من طلبك',
        'name'     => 'الاسم',     'phone'   => 'الجوال',
        'email'    => 'البريد',    'company' => 'الشركة / المشروع',
        'service'  => 'الباقة',    'date'    => 'تاريخ البدء',
        'notes'    => 'ملاحظات',
        'note'     => '&#x1F4AC; للرد على هذا الإيميل، سيصل ردك مباشرة إلى فريق سبيشال زون.',
        'hours'    => '&#x1F550; أوقات العمل: السبت &#x2013; الخميس | 8 ص &#x2013; 10 م',
        'regards'  => 'مع أطيب التحيات،',
        'team'     => 'فريق سبيشال زون',
        'rights'   => 'جميع الحقوق محفوظة',
    ] : [
        'title'    => 'Thank you for contacting us! &#x1F389;',
        'greeting' => 'Dear',
        'confirm'  => 'Your booking request has been received. Our team will contact you within 24 hours.',
        'copy'     => 'Copy of your request',
        'name'     => 'Name',      'phone'   => 'Phone',
        'email'    => 'Email',     'company' => 'Company / Project',
        'service'  => 'Package',   'date'    => 'Start Date',
        'notes'    => 'Notes',
        'note'     => '&#x1F4AC; Replying to this email will reach the Special Zone team directly.',
        'hours'    => '&#x1F550; Working Hours: Sat &#x2013; Thu | 8 AM &#x2013; 10 PM',
        'regards'  => 'Best regards,',
        'team'     => 'Special Zone Team',
        'rights'   => 'All Rights Reserved',
    ];

    $rows = _buildRows($data, $L, $s);
    $yr   = date('Y');

    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f0f2f5;direction:{$dir};font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' border='0' style='background:#f0f2f5;padding:24px 0;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' border='0' style='max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);'>

  <tr><td style='background:linear-gradient(135deg,{$p} 0%,{$s} 100%);padding:30px 20px;text-align:center;'>
    <table cellpadding='0' cellspacing='0' border='0' style='background:#fff;display:inline-block;padding:10px 24px;border-radius:10px;margin-bottom:14px;box-shadow:0 2px 10px rgba(0,0,0,.12);'>
      <tr><td><img src='{$logo}' alt='{$co}' style='max-width:140px;height:auto;display:block;' width='140'></td></tr>
    </table>
    <div style='color:#fff;font-size:22px;font-weight:700;'>{$L['title']}</div>
  </td></tr>
  <tr><td style='height:4px;background:linear-gradient(to right,{$p},{$s});'></td></tr>

  <tr><td style='padding:26px 22px;'>
    <table width='100%' cellpadding='0' cellspacing='0' border='0'>

      <tr><td style='font-size:17px;color:#333;padding-bottom:14px;'>
        {$L['greeting']} <strong style='color:{$p};'>{$e($data['name'])}</strong>،
      </td></tr>

      <tr><td style='background:linear-gradient(135deg,#f0f8e8,#e8f4e8);padding:15px 18px;border-radius:10px;border-right:5px solid {$p};color:#2d5a0e;font-size:15px;line-height:1.8;'>
        &#x2705; {$L['confirm']}
      </td></tr>

      <tr><td style='height:18px;'></td></tr>

      <tr><td style='font-size:15px;font-weight:700;color:{$s};padding-bottom:10px;border-bottom:2px solid {$p};'>
        &#x1F4C4; {$L['copy']}
      </td></tr>
      <tr><td style='height:10px;'></td></tr>

      <tr><td>
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;'>
          {$rows}
        </table>
      </td></tr>

      <tr><td style='height:18px;'></td></tr>

      <tr><td style='background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:13px 15px;font-size:14px;color:#795548;'>
        {$L['note']}
      </td></tr>

      <tr><td style='height:18px;'></td></tr>

      <tr><td style='background:#f8f9fa;border-radius:10px;padding:18px;text-align:center;'>
        <p style='margin:0 0 7px;font-size:14px;color:#666;'>{$L['hours']}</p>
        <p style='margin:0 0 5px;'><a href='mailto:{$coEm}' style='color:{$p};text-decoration:none;font-weight:700;font-size:14px;'>&#x2709;&#xFE0F; {$coEm}</a></p>
        <p style='margin:0;'><a href='https://{$web}' target='_blank' style='color:{$s};text-decoration:none;font-weight:700;font-size:14px;'>&#x1F310; {$web}</a></p>
        <p style='margin:14px 0 0;font-size:14px;color:#555;'>{$L['regards']}<br><strong style='color:{$p};'>{$L['team']}</strong></p>
      </td></tr>

    </table>
  </td></tr>

  <tr><td style='background:#f8f9fa;padding:18px;text-align:center;border-top:1px solid #eee;'>
    <p style='margin:0 0 5px;color:#555;font-size:13px;'>{$L['rights']} &copy; {$yr} <strong style='color:{$p};'>{$co}</strong></p>
    <p style='margin:0;font-size:11px;color:#999;'>برمجة وتصميم <a href='https://airmenu.net' target='_blank' style='color:#3db3c5;text-decoration:none;font-weight:700;'>Air Menu</a></p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
}

// ─── Public API ───────────────────────────────────────────────────────────────
function sendEmail(array $data, string $lang = 'ar'): bool {
    $to   = _e('MAIL_TO');
    $name = $lang === 'ar' ? _e('COMPANY_NAME_AR', 'سبيشال زون') : _e('COMPANY_NAME', 'Special Zone');
    $subj = ($lang === 'ar' ? 'طلب حجز: ' : 'Booking: ')
          . ($data['service'] ?? '') . ' — ' . $name;
    if (empty($to)) { error_log('[SZ] MAIL_TO not set'); return false; }
    // إيميل الشركة الداخلي — بدون Reply-To
    return sz_smtp_send($to, $name, $subj, getEmailTemplate($data, $lang));
}

function sendConfirmationEmail(array $data, string $lang = 'ar'): bool {
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) return false;
    $subj = $lang === 'ar'
        ? '✓ تأكيد استلام طلبك — سبيشال زون'
        : '✓ Booking Received — Special Zone';
    // Reply-To: info@ — لو العميل رد على الإيميل يصل لـ info وليس site
    $replyTo = _e('COMPANY_EMAIL', 'info@SpecialZone.com.sa');
    return sz_smtp_send($data['email'], $data['name'], $subj, getConfirmationEmailTemplate($data, $lang), $replyTo);
}
?>