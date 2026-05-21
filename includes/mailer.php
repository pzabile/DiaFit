<?php
require_once __DIR__ . '/bootstrap.php';

function send_email($toEmail, $toName, $subject, $htmlBody, $textBody = null) {
    $fromName  = cfg('mail.from_name');
    $fromEmail = cfg('mail.from_email');
    $replyTo   = cfg('mail.reply_to');

    $boundary = 'b_' . md5(uniqid('', true));

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . sprintf('%s <%s>', mb_encode_mimeheader($fromName), $fromEmail);
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'X-Mailer: DiaFitus';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    if ($textBody === null) {
        $textBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));
    }

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}--";

    $to = sprintf('%s <%s>', mb_encode_mimeheader($toName), $toEmail);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
}

function password_reset_email_html($firstName, $resetUrl) {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $urlE    = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e0d6;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
      <span style="width:14px;height:14px;border-radius:50%;background:#16a36a;display:inline-block;"></span>
      <strong style="font-size:18px;">{$brandE}</strong>
    </div>
    <h1 style="font-size:22px;margin:0 0 12px;">Reset your password</h1>
    <p style="line-height:1.55;color:#4a5651;">Hi {$fn} — we got a request to reset your {$brandE} password. Click the button below to choose a new one. The link expires in one hour.</p>
    <p style="margin:24px 0;"><a href="{$urlE}" style="background:#0f1a14;color:#fff;padding:14px 22px;border-radius:999px;text-decoration:none;font-weight:600;">Set a new password</a></p>
    <p style="font-size:13px;color:#8a8f8b;line-height:1.55;">If the button doesn't work, copy and paste this link:<br><span style="word-break:break-all;color:#0d7d4f;">{$urlE}</span></p>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:24px 0;" />
    <p style="font-size:12px;color:#8a8f8b;line-height:1.6;">If you didn't request this, ignore this email — your password won't change. Questions: <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a>.</p>
  </div>
</body></html>
HTML;
}

function account_setup_email_html($firstName, $setupUrl) {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $urlE    = htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $price   = (int)cfg('price_today');
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e0d6;">
    <div style="text-align:center;margin-bottom:24px;">
      <img src="https://diafitus.com/assets/logo/logo-mark.svg" width="64" height="64" alt="{$brandE}" style="display:inline-block;" />
      <h1 style="font-size:22px;margin:12px 0 4px;">Welcome to {$brandE}, {$fn} 👋</h1>
    </div>
    <p style="line-height:1.55;color:#4a5651;">Your payment of <strong>\${$price}/month</strong> went through and your membership is active. To finish setting up your account and access your dashboard, click below to create your password.</p>
    <p style="text-align:center;margin:28px 0;">
      <a href="{$urlE}" style="background:#0f1a14;color:#fff;padding:16px 28px;border-radius:999px;text-decoration:none;font-weight:700;display:inline-block;">Create my account →</a>
    </p>
    <p style="font-size:13px;color:#8a8f8b;line-height:1.55;text-align:center;">This link expires in 7 days. If the button doesn't work, paste this into your browser:<br><span style="word-break:break-all;color:#0d7d4f;">{$urlE}</span></p>
    <h2 style="font-size:16px;margin:28px 0 8px;">What happens next</h2>
    <ul style="line-height:1.7;color:#4a5651;padding-left:20px;">
      <li>Our team is reviewing your assessment <strong>right now</strong>.</li>
      <li>Within <strong>24 hours</strong> we'll upload your personalized 12-week program and nutrition guide to your dashboard.</li>
      <li>You'll have 24/7 access to message us with anything.</li>
    </ul>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:24px 0;" />
    <p style="font-size:12px;color:#8a8f8b;line-height:1.6;text-align:center;">
      Questions: <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a><br>
      {$brandE} provides general fitness and lifestyle suggestions only. It is not medical advice. Always consult your physician.
    </p>
  </div>
</body></html>
HTML;
}

function welcome_email_html($firstName, $email = '', $password = '') {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $site    = cfg('site_url');
    $fn      = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $emailE  = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $pwE     = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $price   = (int)cfg('price_today');
    $creds   = $password ? "<p style=\"line-height:1.55;color:#4a5651;\"><strong>Your login:</strong></p>
      <table style=\"border-collapse:collapse;margin:0 0 16px 0;\"><tr><td style=\"padding:4px 12px 4px 0;color:#8a8f8b;\">Email</td><td style=\"padding:4px 0;font-family:monospace;\">{$emailE}</td></tr><tr><td style=\"padding:4px 12px 4px 0;color:#8a8f8b;\">Password</td><td style=\"padding:4px 0;font-family:monospace;\">{$pwE}</td></tr></table>
      <p style=\"line-height:1.55;color:#4a5651;\">Sign in at <a href=\"{$site}/login\" style=\"color:#0d7d4f;\">{$site}/login</a>. You can change your password from your dashboard.</p>" : '';
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;border:1px solid #e3e0d6;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
      <span style="width:14px;height:14px;border-radius:50%;background:#16a36a;display:inline-block;"></span>
      <strong style="font-size:18px;">{$brandE}</strong>
    </div>
    <h1 style="font-size:24px;margin:0 0 12px;">Welcome to {$brandE}, {$fn} 👋</h1>
    <p style="line-height:1.55;color:#4a5651;">Thanks for joining. Your payment of <strong>\${$price}/month</strong> went through and your membership is active.</p>
    {$creds}
    <p style="line-height:1.55;color:#4a5651;"><strong>What happens next:</strong> our team is reviewing your assessment right now and <strong>building your personalized program</strong>. We'll reach out within the next <strong>24 hours</strong> with:</p>
    <ul style="line-height:1.7;color:#4a5651;">
      <li>Your personalized exercise program</li>
      <li>Your nutrition PDF guide</li>
      <li>24/7 access to support whenever you need a hand</li>
      <li>Full access to your private dashboard</li>
    </ul>
    <p style="line-height:1.55;color:#4a5651;">If you need anything in the meantime, just reply to this email or write to <a href="mailto:{$support}" style="color:#0d7d4f;">{$support}</a>.</p>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:24px 0;" />
    <p style="font-size:12px;color:#8a8f8b;line-height:1.6;">
      {$brandE} provides general fitness and lifestyle suggestions only. It is not medical advice and is not a substitute for consulting your physician. Always check with your doctor before starting any new exercise or nutrition program — especially if you have diabetes or any other medical condition.
    </p>
  </div>
</body></html>
HTML;
}
