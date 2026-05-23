<?php
require_once __DIR__ . '/bootstrap.php';

function send_email($toEmail, $toName, $subject, $htmlBody, $textBody = null, $attachments = []) {
    $fromName  = cfg('mail.from_name');
    $fromEmail = cfg('mail.from_email');
    $replyTo   = cfg('mail.reply_to');

    if ($textBody === null) {
        $textBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));
    }

    $to             = sprintf('%s <%s>', mb_encode_mimeheader($toName), $toEmail);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHeader     = sprintf('%s <%s>', mb_encode_mimeheader($fromName), $fromEmail);

    if (empty($attachments)) {
        // Simple multipart/alternative (no attachments)
        $b = 'alt_' . md5(uniqid('', true));
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "From: {$fromHeader}\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "X-Mailer: DiaFitus\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$b}\"";

        $body  = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$b}--";
    } else {
        // multipart/mixed wrapping multipart/alternative + attachments
        $outer = 'mix_' . md5(uniqid('', true));
        $inner = 'alt_' . md5(uniqid('', true));

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "From: {$fromHeader}\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "X-Mailer: DiaFitus\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$outer}\"";

        $body  = "--{$outer}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$inner}\"\r\n\r\n";
        $body .= "--{$inner}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$inner}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$inner}--\r\n\r\n";

        foreach ($attachments as $att) {
            $path = $att['path'] ?? '';
            $name = $att['name'] ?? basename($path);
            $mime = $att['mime'] ?? 'application/octet-stream';
            if (!$path || !file_exists($path)) continue;
            $data = base64_encode(file_get_contents($path));
            $body .= "--{$outer}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $body .= chunk_split($data) . "\r\n";
        }
        $body .= "--{$outer}--";
    }

    return @mail($to, $encodedSubject, $body, $headers, '-f' . $fromEmail);
}

function nutrition_guide_attachment() {
    $path = __DIR__ . '/../assets/guides/nutrition-guide.pdf';
    if (!file_exists($path)) return [];
    return [[
        'path' => $path,
        'name' => 'DiaFitus-Eating-for-Stable-Blood-Sugar.pdf',
        'mime' => 'application/pdf',
    ]];
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

function account_setup_email_html($firstName, $setupUrl, $memberEmail = '') {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $site    = cfg('site_url');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $urlE    = htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $siteE   = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    $emailE  = htmlspecialchars($memberEmail ?: '', ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
      <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
    </div>

    <h1 style="font-size:26px;font-weight:700;margin:0 0 10px;letter-spacing:-.02em;">Welcome to {$brandE}, {$fn}! 👋</h1>
    <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 20px;">Your payment went through and your membership is <strong style="color:#16a36a;">active</strong>. We're already reviewing your assessment so we can build something that actually fits you.</p>

    <!-- Personal note -->
    <div style="background:#f3faf6;border-left:3px solid #16a36a;border-radius:0 12px 12px 0;padding:16px 18px;margin:0 0 24px;">
      <p style="font-size:13px;font-weight:700;color:#16a36a;letter-spacing:.06em;text-transform:uppercase;margin:0 0 8px;">A personal note from the founder</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0 0 10px;">I was diagnosed with diabetes when I was 3 years old. Two very strong antibiotics prescribed by a doctor — and that was the start of a lifelong journey I never asked for.</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0 0 10px;">Through the good, the bad, and the ugly — I still find a way to live with a smile and be genuinely happy for this life.</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0;">That is exactly why {$brandE} exists. <strong>You are not alone in this.</strong></p>
    </div>

    <!-- Create account CTA -->
    <p style="font-size:15px;font-weight:700;color:#0f1a14;margin:0 0 8px;">One last step — create your password</p>
    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 16px;">Click the button below to set your password. Everything lives in your Member Portal after that.</p>
    <div style="text-align:center;margin:0 0 16px;">
      <a href="{$urlE}" style="background:#16a36a;color:#fff;padding:16px 32px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Create my account →</a>
    </div>
    <p style="font-size:12px;color:#8a8f8b;text-align:center;margin:0 0 24px;">Link expires in 7 days. If the button doesn't work, paste this URL into your browser:<br><span style="word-break:break-all;color:#0d7d4f;">{$urlE}</span></p>

    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 20px;" />

    <!-- What happens next -->
    <p style="font-size:15px;font-weight:700;color:#0f1a14;margin:0 0 12px;">What happens in the next 24 hours</p>
    <table style="border-collapse:collapse;width:100%;margin:0 0 20px;">
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;width:28px;">🩸</td><td style="padding:6px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>blood-sugar-aware workout plan</strong> is uploaded to your account.</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">💬</td><td style="padding:6px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>coach sends you a first message</strong> with your focus for the days ahead.</td></tr>
    </table>

    <!-- Login info -->
    <div style="background:#f7f5f0;border-radius:12px;padding:14px 16px;margin:0 0 24px;">
      <p style="font-size:14px;line-height:1.65;color:#4a5651;margin:0;">Once you've set your password, sign in at <a href="{$siteE}/login" style="color:#0d7d4f;font-weight:600;">{$siteE}/login</a> using <strong>{$emailE}</strong>. If you ever get stuck, reply to this email or write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a> — a real person will help.</p>
    </div>

    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 24px;text-align:center;">Welcome aboard — we're glad you're here.<br><br><strong>The {$brandE} Team</strong></p>

    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 14px;" />
    <p style="font-size:11px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">
      Not medical advice. Always consult your doctor — especially with diabetes.
    </p>
  </div>
</body></html>
HTML;
}

function plan_changed_email_html($firstName, $planDays, $startedAt = '', $email = '') {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $site    = cfg('site_url');
    $fn      = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $siteE   = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    $emailE  = htmlspecialchars($email ?: '', ENT_QUOTES, 'UTF-8');

    $days = (int) $planDays;
    if ($days <= 7) {
        $planName = '7-Day Jump-Start';
        $duration = '7 days';
    } elseif ($days <= 28) {
        $planName = '28-Day (4-Week) Reset';
        $duration = '4 weeks';
    } else {
        $planName = '84-Day (12-Week) Transformation';
        $duration = '12 weeks';
    }

    $endLine = '';
    $startLine = '';
    if ($startedAt) {
        try {
            $startDt = new DateTime($startedAt);
            $endDt   = clone $startDt;
            $endDt->modify('+' . $days . ' days');
            $startLine = '<tr><td style="padding:4px 0;color:#4a5651;font-size:14px;">Starts</td><td style="padding:4px 0;font-size:14px;font-weight:600;">' . htmlspecialchars($startDt->format('F j, Y'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
            $endLine   = '<tr><td style="padding:4px 0;color:#4a5651;font-size:14px;">Ends</td><td style="padding:4px 0;font-size:14px;font-weight:600;">' . htmlspecialchars($endDt->format('F j, Y'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        } catch (Exception $ignored) {}
    }

    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
      <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
    </div>

    <h1 style="font-size:24px;font-weight:700;margin:0 0 10px;letter-spacing:-.02em;">Your plan has been updated, {$fn}!</h1>
    <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 24px;">Your coach has made a change to your program. Here's what's new:</p>

    <div style="background:#f3faf6;border-radius:14px;padding:20px 22px;margin:0 0 24px;">
      <p style="font-size:13px;font-weight:700;color:#16a36a;letter-spacing:.06em;text-transform:uppercase;margin:0 0 12px;">New program</p>
      <table style="border-collapse:collapse;width:100%;">
        <tr><td style="padding:4px 0;color:#4a5651;font-size:14px;">Plan</td><td style="padding:4px 0;font-size:14px;font-weight:600;">{$planName}</td></tr>
        <tr><td style="padding:4px 0;color:#4a5651;font-size:14px;">Duration</td><td style="padding:4px 0;font-size:14px;font-weight:600;">{$duration}</td></tr>
        {$startLine}
        {$endLine}
      </table>
    </div>

    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 20px;">Your dashboard has already been updated to reflect this change — your progress bar and week count now match your new program length.</p>

    <div style="text-align:center;margin:0 0 24px;">
      <a href="{$siteE}/dashboard" style="background:#16a36a;color:#fff;padding:14px 28px;border-radius:999px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;">View my dashboard →</a>
    </div>

    <div style="background:#f7f5f0;border-radius:12px;padding:14px 16px;margin:0 0 24px;">
      <p style="font-size:14px;line-height:1.65;color:#4a5651;margin:0;">Sign in at <a href="{$siteE}/login" style="color:#0d7d4f;font-weight:600;">{$siteE}/login</a> using <strong>{$emailE}</strong>. Questions? Write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a> — we're here.</p>
    </div>

    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 24px;text-align:center;">Keep going — we believe in you.<br><br><strong>The {$brandE} Team</strong></p>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 14px;" />
    <p style="font-size:11px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">Not medical advice. Always consult your doctor — especially with diabetes.</p>
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
    $sup     = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $siteE   = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
      <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
      <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
    </div>
    <h1 style="font-size:26px;font-weight:700;margin:0 0 10px;letter-spacing:-.02em;">Welcome to {$brandE}, {$fn}! 👋</h1>
    <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 20px;">Your payment went through and your membership is <strong style="color:#16a36a;">active</strong>. We're already reviewing your assessment so we can build something that actually fits you.</p>

    <div style="background:#f3faf6;border-left:3px solid #16a36a;border-radius:0 12px 12px 0;padding:16px 18px;margin:0 0 24px;">
      <p style="font-size:13px;font-weight:700;color:#16a36a;letter-spacing:.06em;text-transform:uppercase;margin:0 0 8px;">A personal note from the founder</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0 0 10px;">I was diagnosed with diabetes when I was 3 years old. Two very strong antibiotics prescribed by a doctor — and that was the start of a lifelong journey I never asked for.</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0 0 10px;">Through the good, the bad, and the ugly — I still find a way to live with a smile and be genuinely happy for this life.</p>
      <p style="font-size:14px;line-height:1.75;color:#2d4a3a;margin:0;">That is exactly why {$brandE} exists. <strong>You are not alone in this.</strong></p>
    </div>

    <p style="font-size:15px;font-weight:700;color:#0f1a14;margin:0 0 12px;">What happens in the next 24 hours</p>
    <table style="border-collapse:collapse;width:100%;margin:0 0 20px;">
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;width:28px;">🩸</td><td style="padding:6px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>blood-sugar-aware workout plan</strong> is uploaded to your account.</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">💬</td><td style="padding:6px 0;font-size:14px;color:#4a5651;line-height:1.5;">Your <strong>coach sends you a first message</strong> with your focus for the days ahead.</td></tr>
    </table>

    <div style="background:#f7f5f0;border-radius:12px;padding:14px 16px;margin:0 0 24px;">
      <p style="font-size:14px;line-height:1.65;color:#4a5651;margin:0;">Sign in at <a href="{$siteE}/login" style="color:#0d7d4f;font-weight:600;">{$siteE}/login</a> using <strong>{$emailE}</strong>. If you ever get stuck, reply here or write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a>.</p>
    </div>

    <p style="font-size:14px;line-height:1.6;color:#4a5651;margin:0 0 24px;text-align:center;">Welcome aboard — we're glad you're here.<br><br><strong>The {$brandE} Team</strong></p>
    <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 14px;" />
    <p style="font-size:11px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">Not medical advice. Always consult your doctor — especially with diabetes.</p>
  </div>
</body></html>
HTML;
}
