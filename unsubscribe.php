<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';

function _optout_token(string $email): string {
    $secret = cfg('telegram.bot_token', 'diafitus-optout-salt');
    return substr(hash_hmac('sha256', strtolower(trim($email)), $secret), 0, 40);
}

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
$done  = false;
$error = '';

if ($email && $token) {
    if (hash_equals(_optout_token($email), $token)) {
        try {
            $lead = db_get('SELECT id, opted_out FROM leads WHERE email = ? LIMIT 1', [$email]);
            if ($lead) {
                if (!$lead['opted_out']) {
                    db_exec('UPDATE leads SET opted_out = 1, opted_out_at = NOW() WHERE email = ?', [$email]);
                }
                $done = true;
            } else {
                $error = 'Email address not found in our system.';
            }
        } catch (Throwable $ex) {
            $error = 'Something went wrong. Please email us directly.';
            error_log('unsubscribe error: ' . $ex->getMessage());
        }
    } else {
        $error = 'Invalid unsubscribe link. It may have expired or been copied incorrectly.';
    }
} else {
    $error = 'Missing parameters. Please use the link from your email.';
}

$brand   = cfg('brand_name', 'DiaFitus');
$support = cfg('support_email', 'support@diafitus.com');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Unsubscribe — <?= htmlspecialchars($brand) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .box{max-width:460px;width:100%;background:#fff;border-radius:20px;padding:40px 32px;border:1px solid #e3e0d6;text-align:center}
    .icon{font-size:44px;margin-bottom:18px}
    h1{font-size:22px;font-weight:700;color:#0f1a14;margin-bottom:10px}
    p{color:#6b7a72;font-size:14px;line-height:1.65;margin-bottom:12px}
    a{color:#0d7d4f;text-decoration:underline}
    .err{color:#d8493c}
  </style>
</head>
<body>
<div class="box">
<?php if ($done): ?>
  <div class="icon">✅</div>
  <h1>You're unsubscribed</h1>
  <p><strong><?= htmlspecialchars($email) ?></strong> has been removed from our promotional email list. You won't receive discount or follow-up emails from us again.</p>
  <p>Changed your mind? Email <a href="mailto:<?= htmlspecialchars($support) ?>"><?= htmlspecialchars($support) ?></a> and we'll re-add you.</p>
<?php else: ?>
  <div class="icon">⚠️</div>
  <h1>Couldn't unsubscribe</h1>
  <p class="err"><?= htmlspecialchars($error) ?></p>
  <p>To unsubscribe manually, email <a href="mailto:<?= htmlspecialchars($support) ?>?subject=Unsubscribe+<?= urlencode($email) ?>"><?= htmlspecialchars($support) ?></a> with "Unsubscribe" in the subject line and we'll remove you within 24 hours.</p>
<?php endif; ?>
</div>
</body>
</html>
