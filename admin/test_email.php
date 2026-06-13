<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';
require_admin();

$result = null;
$error  = null;
$log    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['email'] ?? '');
    if ($toEmail && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $sent = send_email(
                $toEmail,
                'Test',
                'DiaFitus SMTP test — ' . date('H:i:s'),
                '<div style="font-family:sans-serif;padding:24px"><h2>SMTP test ✅</h2><p>If you received this, email sending is working correctly from DiaFitus.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p></div>'
            );
            $result = $sent ? 'ok' : 'returned_false';
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}

$smtpHost = cfg('mail.smtp_host', '');
$smtpUser = cfg('mail.smtp_user', '');
$smtpPass = cfg('mail.smtp_pass', '');
$smtpPort = cfg('mail.smtp_port', 587);
$from     = cfg('mail.from_email', '');
$usingSmtp = ($smtpHost && $smtpUser && $smtpPass);
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Email test — DiaFitus admin</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f7f5f0; margin: 0; padding: 40px; color: #0f1a14; }
    .box { max-width: 640px; margin: 0 auto; background: #fff; border: 1.5px solid #e3e0d6; border-radius: 16px; padding: 32px; }
    h2 { margin: 0 0 20px; }
    .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e3e0d6; font-size: 14px; }
    .row:last-child { border-bottom: none; }
    .label { color: #6b7a72; }
    .val { font-family: monospace; color: #0f1a14; }
    .ok  { color: #16a36a; font-weight: 700; }
    .err { color: #d8493c; font-weight: 700; }
    .warn { color: #c87a00; font-weight: 700; }
    input[type=email] { padding: 10px 14px; border: 1.5px solid #e3e0d6; border-radius: 10px; font-size: 14px; width: 280px; }
    button { padding: 10px 20px; background: #0f1a14; color: #fff; border: none; border-radius: 10px; font-size: 14px; cursor: pointer; }
    pre { background: #fee; border: 1px solid #f5c6c6; border-radius: 10px; padding: 14px; font-size: 13px; white-space: pre-wrap; word-break: break-all; }
  </style>
</head>
<body>
<div class="box">
  <h2>Email configuration test</h2>

  <div style="margin-bottom:24px">
    <div class="row"><span class="label">Transport</span><span class="val <?= $usingSmtp ? 'ok' : 'warn' ?>"><?= $usingSmtp ? 'SMTP ✓' : 'Native mail() — SMTP not configured' ?></span></div>
    <div class="row"><span class="label">smtp_host</span><span class="val"><?= $smtpHost ? htmlspecialchars($smtpHost) : '<span class="err">not set</span>' ?></span></div>
    <div class="row"><span class="label">smtp_port</span><span class="val"><?= (int)$smtpPort ?></span></div>
    <div class="row"><span class="label">smtp_user</span><span class="val"><?= $smtpUser ? htmlspecialchars($smtpUser) : '<span class="err">not set</span>' ?></span></div>
    <div class="row"><span class="label">smtp_pass</span><span class="val"><?= $smtpPass ? '<span class="ok">set (' . strlen($smtpPass) . ' chars)</span>' : '<span class="err">EMPTY — this is why it falls back to mail()</span>' ?></span></div>
    <div class="row"><span class="label">from_email</span><span class="val"><?= htmlspecialchars($from) ?></span></div>
  </div>

  <form method="post" style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Send test to this address" required>
    <button type="submit">Send test email</button>
  </form>

  <?php if ($result === 'ok'): ?>
    <p class="ok">✓ Email sent successfully — check the inbox (and spam folder).</p>
  <?php elseif ($result === 'returned_false'): ?>
    <p class="err">✗ mail() / SMTP returned false without an exception. On Hostinger this usually means PHP's native mail() is disabled. Make sure smtp_pass is filled in config.php.</p>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <p class="err">✗ Exception thrown:</p>
    <pre><?= htmlspecialchars($error) ?></pre>
    <p style="font-size:13px;color:#6b7a72">
      <strong>Common fixes:</strong><br>
      • "connect failed": wrong host/port or firewall blocking outbound SMTP. Try <code>smtp.hostinger.com</code> port 587.<br>
      • "AUTH … expected 334": wrong username format — use full email address.<br>
      • "expected 235": wrong password.<br>
      • "expected 250 got 550": from address rejected — must match your email account.
    </p>
  <?php endif; ?>
</div>
</body>
</html>
