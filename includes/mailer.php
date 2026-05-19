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

function welcome_email_html($firstName) {
    $brand   = cfg('brand_name');
    $support = cfg('support_email');
    $fn      = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $brandE  = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $price   = (int)cfg('price_today');
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
    <p style="line-height:1.55;color:#4a5651;"><strong>What happens next:</strong> our coaches and doctors are reviewing your assessment right now and <strong>building your personalized program</strong>. We'll reach out within the next <strong>24 hours</strong> with:</p>
    <ul style="line-height:1.7;color:#4a5651;">
      <li>Your personalized exercise program</li>
      <li>Your nutrition PDF guide</li>
      <li>An invite to our private Telegram with coaches &amp; doctors</li>
      <li>Access to your private dashboard</li>
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
