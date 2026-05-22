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
    $site    = cfg('site_url');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $urlE    = htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $siteE   = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">

    <!-- Header -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
      <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
    </div>

    <h1 style="font-size:26px;font-weight:700;margin:0 0 10px;letter-spacing:-.02em;">Welcome to {$brandE}, {$fn}! 👋</h1>
    <p style="font-size:16px;line-height:1.6;color:#4a5651;margin:0 0 24px;">Your payment went through and your membership is <strong style="color:#16a36a;">active</strong>. One last step — click the button below to create your password and access your private dashboard.</p>

    <!-- CTA -->
    <div style="text-align:center;margin:28px 0;">
      <a href="{$urlE}" style="background:#16a36a;color:#fff;padding:16px 32px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;letter-spacing:.01em;">Create my account →</a>
    </div>
    <p style="font-size:13px;color:#8a8f8b;text-align:center;margin:0 0 28px;">Link expires in 7 days. If the button doesn't work, copy and paste this URL:<br><span style="word-break:break-all;color:#0d7d4f;">{$urlE}</span></p>

    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 24px;" />

    <!-- What's included -->
    <h2 style="font-size:17px;font-weight:700;margin:0 0 14px;">What's waiting for you inside</h2>
    <table style="border-collapse:collapse;width:100%;">
      <tr><td style="padding:7px 0;vertical-align:top;font-size:20px;width:32px;">🩸</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;"><strong style="color:#0f1a14;">Blood-sugar-aware workout plan</strong><br>Every session is designed to help stabilize your glucose safely.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:20px;">🥗</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;"><strong style="color:#0f1a14;">Personalized nutrition guide</strong><br>Eat the foods you love — built around your diabetes type, not a generic diet.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:20px;">💬</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;"><strong style="color:#0f1a14;">24/7 coach messaging</strong><br>Ask anything — glucose, fueling, schedule changes — we reply fast.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:20px;">📊</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;"><strong style="color:#0f1a14;">Progress &amp; glucose tracker</strong><br>Log weight, steps and readings in one private dashboard.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:20px;">📅</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;"><strong style="color:#0f1a14;">Weekly coach check-ins</strong><br>Your coach reviews your week and adjusts the plan — every single week.</td></tr>
    </table>

    <hr style="border:none;border-top:1px solid #e3e0d6;margin:24px 0;" />

    <!-- Timeline -->
    <h2 style="font-size:17px;font-weight:700;margin:0 0 10px;">What happens next</h2>
    <p style="font-size:14px;color:#4a5651;line-height:1.6;margin:0 0 6px;">✅ <strong>Right now</strong> — Our team is reviewing your assessment and building your personalized program.</p>
    <p style="font-size:14px;color:#4a5651;line-height:1.6;margin:0 0 6px;">⏰ <strong>Within 24 hours</strong> — Your program PDF and nutrition guide will appear in your dashboard.</p>
    <p style="font-size:14px;color:#4a5651;line-height:1.6;margin:0 0 24px;">💬 <strong>Any time</strong> — Message your coach directly from your dashboard with any question.</p>

    <div style="text-align:center;margin:0 0 24px;">
      <a href="{$urlE}" style="background:#0f1a14;color:#fff;padding:14px 28px;border-radius:999px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;">Set up my account now</a>
    </div>

    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 16px;" />
    <p style="font-size:12px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">
      Questions? Reply to this email or write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a><br>
      Sign in any time at <a href="{$siteE}/login" style="color:#0d7d4f;">{$siteE}/login</a><br><br>
      {$brandE} provides general fitness and lifestyle guidance only. Not medical advice. Always consult your physician — especially with diabetes.
    </p>
  </div>
</body></html>
HTML;
}

function welcome_email_html($firstName, $email = '', $password = '') {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $site    = cfg('site_url');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $emailE  = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $pwE     = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $siteE   = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    $creds   = $password ? "<p style=\"line-height:1.55;color:#4a5651;margin:0 0 6px;\"><strong>Your login details:</strong></p>
      <table style=\"border-collapse:collapse;margin:0 0 20px;background:#f7f5f0;border-radius:10px;padding:12px;width:100%;\"><tr><td style=\"padding:5px 14px 5px 0;color:#8a8f8b;font-size:14px;\">Email</td><td style=\"padding:5px 0;font-family:monospace;font-size:14px;\">{$emailE}</td></tr><tr><td style=\"padding:5px 14px 5px 0;color:#8a8f8b;font-size:14px;\">Password</td><td style=\"padding:5px 0;font-family:monospace;font-size:14px;\">{$pwE}</td></tr></table>
      <p style=\"line-height:1.55;color:#4a5651;font-size:14px;\">Sign in at <a href=\"{$siteE}/login\" style=\"color:#0d7d4f;\">{$siteE}/login</a>. You can change your password from your dashboard at any time.</p>" : '';
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
      <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
    </div>
    <h1 style="font-size:26px;font-weight:700;margin:0 0 10px;letter-spacing:-.02em;">Welcome to {$brandE}, {$fn}! 👋</h1>
    <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 20px;">Your payment went through and your membership is <strong style="color:#16a36a;">active</strong>. We are already reviewing your assessment and building your program.</p>
    {$creds}

    <!-- Personal note from the founder -->
    <div style="background:#f3faf6;border-left:3px solid #16a36a;border-radius:0 12px 12px 0;padding:16px 18px;margin:0 0 24px;">
      <p style="font-size:14px;line-height:1.7;color:#2d4a3a;margin:0 0 10px;"><strong>A personal note from the founder —</strong></p>
      <p style="font-size:14px;line-height:1.7;color:#2d4a3a;margin:0 0 10px;">I was diagnosed with diabetes when I was 3 years old. Two very strong antibiotics prescribed by a doctor — and that was the start of a lifelong journey I never asked for.</p>
      <p style="font-size:14px;line-height:1.7;color:#2d4a3a;margin:0 0 10px;">Through the good, the bad, and the ugly — the low days, the confusion, the moments of doubt — I still find a way to live with a smile and be genuinely happy for this life. And more than anything, I want to help people around me do the same.</p>
      <p style="font-size:14px;line-height:1.7;color:#2d4a3a;margin:0;">That is exactly why {$brandE} exists. You are not alone in this. I have been where you are, and I built this program because I know what actually works — not just in theory, but in real life, with real blood sugar.</p>
    </div>

    <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 14px;"><strong>What happens in the next 24 hours:</strong></p>
    <table style="border-collapse:collapse;width:100%;margin:0 0 24px;">
      <tr><td style="padding:7px 0;vertical-align:top;font-size:18px;width:32px;">🩸</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>blood-sugar-aware workout plan</strong> is uploaded to your private dashboard.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:18px;">🥗</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>personalized nutrition guide PDF</strong> — built around your diabetes type and goals — is ready to download.</td></tr>
      <tr><td style="padding:7px 0;vertical-align:top;font-size:18px;">💬</td><td style="padding:7px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your coach sends you a <strong>first message</strong> with your focus for the days ahead.</td></tr>
    </table>
    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 24px;">You have <strong>24/7 access</strong> to message your coach from your dashboard — glucose questions, fueling, schedule changes, anything. We respond fast.</p>
    <p style="font-size:14px;color:#4a5651;margin:0 0 24px;">Questions? Reply directly to this email or write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a>.</p>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 16px;" />
    <p style="font-size:12px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">
      {$brandE} provides general fitness and lifestyle guidance only. Not medical advice. Always consult your physician — especially with diabetes.
    </p>
  </div>
</body></html>
HTML;
}
