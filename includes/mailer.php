<?php
require_once __DIR__ . '/bootstrap.php';

/* ── MIME builder (shared by both transports) ─────────────────────────── */
function _build_mime($fromHeader, $toHeader, $replyTo, $subject, $htmlBody, $textBody, $attachments): array {
    $b64subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $date    = date('r');
    $msgId   = '<' . time() . '.' . bin2hex(random_bytes(6)) . '@' . (explode('@', $fromHeader)[1] ?? 'mail') . '>';

    if (empty($attachments)) {
        $b        = 'alt_' . bin2hex(random_bytes(8));
        $headers  = "Date: {$date}\r\nMessage-ID: {$msgId}\r\nFrom: {$fromHeader}\r\nTo: {$toHeader}\r\nReply-To: {$replyTo}\r\n";
        $headers .= "Subject: {$b64subj}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$b}\"";
        $body     = "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$textBody}\r\n\r\n";
        $body    .= "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$htmlBody}\r\n\r\n";
        $body    .= "--{$b}--";
    } else {
        $outer   = 'mix_' . bin2hex(random_bytes(8));
        $inner   = 'alt_' . bin2hex(random_bytes(8));
        $headers = "Date: {$date}\r\nMessage-ID: {$msgId}\r\nFrom: {$fromHeader}\r\nTo: {$toHeader}\r\nReply-To: {$replyTo}\r\n";
        $headers .= "Subject: {$b64subj}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$outer}\"";
        $body    = "--{$outer}\r\nContent-Type: multipart/alternative; boundary=\"{$inner}\"\r\n\r\n";
        $body   .= "--{$inner}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$textBody}\r\n\r\n";
        $body   .= "--{$inner}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$htmlBody}\r\n\r\n";
        $body   .= "--{$inner}--\r\n\r\n";
        foreach ($attachments as $att) {
            $path = $att['path'] ?? '';
            $name = $att['name'] ?? basename($path);
            $mime = $att['mime'] ?? 'application/octet-stream';
            if (!$path || !file_exists($path)) continue;
            $data  = base64_encode(file_get_contents($path));
            $body .= "--{$outer}\r\nContent-Type: {$mime}; name=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $body .= chunk_split($data) . "\r\n";
        }
        $body .= "--{$outer}--";
    }
    return [$headers, $body, $b64subj];
}

/* ── SMTP transport ────────────────────────────────────────────────────── */
function _smtp_send($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments): bool {
    $host     = cfg('mail.smtp_host');
    $port     = (int)cfg('mail.smtp_port', 587);
    $user     = cfg('mail.smtp_user');
    $pass     = cfg('mail.smtp_pass');
    $from     = cfg('mail.from_email');
    $fromName = cfg('mail.from_name');
    $replyTo  = cfg('mail.reply_to', $from);

    $toHeader   = $toName ? sprintf('%s <%s>', mb_encode_mimeheader($toName), $toEmail) : "<{$toEmail}>";
    $fromHeader = sprintf('%s <%s>', mb_encode_mimeheader($fromName), $from);

    [$headers, $body] = _build_mime($fromHeader, $toHeader, $replyTo, $subject, $htmlBody, $textBody ?? '', $attachments);

    // Connect
    $ssl  = ($port === 465);
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $addr = ($ssl ? 'ssl://' : '') . $host . ':' . $port;
    $sock = @stream_socket_client($addr, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) throw new RuntimeException("SMTP connect failed ({$addr}): {$errstr} ({$errno})");
    stream_set_timeout($sock, 30);

    $rd = function () use ($sock): string {
        $buf = '';
        while (($line = fgets($sock, 1024)) !== false) {
            $buf .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $buf;
    };
    $cmd = function (string $c) use ($sock, $rd): string { fwrite($sock, $c . "\r\n"); return $rd(); };
    $ok  = function (string $c, int $code) use ($cmd): string {
        $r = $cmd($c);
        if (substr($r, 0, 3) !== (string)$code) throw new RuntimeException("SMTP [{$c}] expected {$code}, got: " . trim($r));
        return $r;
    };

    $rd(); // server greeting
    $domain = explode('@', $from)[1] ?? 'localhost';

    if ($ssl) {
        $ok("EHLO {$domain}", 250);
    } else {
        $ehlo = $cmd("EHLO {$domain}");
        if (strpos($ehlo, 'STARTTLS') !== false) {
            $ok('STARTTLS', 220);
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $ok("EHLO {$domain}", 250);
        }
    }

    $ok('AUTH LOGIN', 334);
    $ok(base64_encode($user), 334);
    $ok(base64_encode($pass), 235);
    $ok("MAIL FROM:<{$from}>", 250);
    $ok("RCPT TO:<{$toEmail}>", 250);
    $ok('DATA', 354);

    // Dot-stuffing: escape lines that start with a lone dot
    $raw = $headers . "\r\n" . $body;
    $raw = str_replace("\r\n.", "\r\n..", $raw);
    fwrite($sock, $raw . "\r\n.\r\n");
    $result = $rd();
    $cmd('QUIT');
    fclose($sock);

    return substr($result, 0, 3) === '250';
}

/* ── Native mail() transport (fallback) ───────────────────────────────── */
function _native_send($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments): bool {
    $fromEmail  = cfg('mail.from_email');
    $fromName   = cfg('mail.from_name');
    $replyTo    = cfg('mail.reply_to');
    $toHeader   = sprintf('%s <%s>', mb_encode_mimeheader($toName), $toEmail);
    $fromHeader = sprintf('%s <%s>', mb_encode_mimeheader($fromName), $fromEmail);
    [,$headers, $body, $b64subj] = array_merge([''], _build_mime($fromHeader, $toHeader, $replyTo, $subject, $textBody ?? '', $htmlBody, $attachments));
    // mail() injects its own To/Subject/Date — strip them from our headers block
    $hdrsForMail = preg_replace('/^(Date|Message-ID|From|To|Subject):[^\r\n]*\r\n/im', '', $headers);
    return (bool)@mail($toHeader, $b64subj, $body, trim($hdrsForMail), '-f' . $fromEmail);
}

/* ── Public API ────────────────────────────────────────────────────────── */
function send_email($toEmail, $toName, $subject, $htmlBody, $textBody = null, $attachments = []): bool {
    if ($textBody === null) {
        $textBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));
    }
    if (cfg('mail.smtp_host') && cfg('mail.smtp_user') && cfg('mail.smtp_pass')) {
        try {
            return _smtp_send($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments);
        } catch (Throwable $ex) {
            error_log('SMTP error: ' . $ex->getMessage());
            throw $ex; // re-throw so callers see the real error
        }
    }
    return _native_send($toEmail, $toName, $subject, $htmlBody, $textBody, $attachments);
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

function lead_followup_email_html($firstName, $toEmail, $diabType = '', $goals = []) {
    $brand    = cfg('brand_name');
    $support  = cfg('support_email');
    $site     = cfg('site_url');
    $fn       = htmlspecialchars($firstName ?: '', ENT_QUOTES, 'UTF-8');
    $brandE   = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $sup      = htmlspecialchars($support, ENT_QUOTES, 'UTF-8');
    $offerUrl = htmlspecialchars(rtrim($site, '/') . '/offer', ENT_QUOTES, 'UTF-8');
    $h1       = $fn ? "{$fn}, we built your personalized diabetes program 🎯" : "We built your personalized diabetes program 🎯";

    // Personalise benefit line by diabetes type
    switch (strtolower((string)$diabType)) {
        case 'type_1': case 'type1':
            $typeLabel   = 'Type&nbsp;1 diabetes';
            $typeBenefit = 'safe exercise protocols that keep your blood sugar stable — no more guessing how a workout will affect your levels';
            break;
        case 'type_2': case 'type2':
            $typeLabel   = 'Type&nbsp;2 diabetes';
            $typeBenefit = 'blood-sugar-lowering workouts and nutrition guidance — many members see A1C improvements within 4&nbsp;weeks';
            break;
        case 'pre_diabetes': case 'prediabetes':
            $typeLabel   = 'pre-diabetes';
            $typeBenefit = 'evidence-based programs that have helped members reverse their pre-diabetes diagnosis through targeted exercise and nutrition';
            break;
        case 'gestational':
            $typeLabel   = 'gestational diabetes';
            $typeBenefit = 'pregnancy-safe workouts and glucose-stable meal plans reviewed by licensed physicians';
            break;
        default:
            $typeLabel   = '';
            $typeBenefit = 'blood-sugar-aware workouts and personalised nutrition to help you feel better every day';
    }

    $typeIntro = $typeLabel
        ? "As someone managing <strong>{$typeLabel}</strong>, you need a program built around <em>your</em> blood sugar — not a generic fitness app."
        : "You need a program built around your blood sugar — not a generic fitness app.";

    // Pricing table with promo discount applied
    $plans    = cfg('plans');
    $planRows = '';
    foreach ($plans as $p) {
        $orig  = number_format((float)$p['price_today'], 2);
        $disc  = number_format((float)$p['price_today'] * 0.8, 2);
        $lbl   = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
        $planRows .= "<tr style=\"border-top:1px solid #e3e0d6;\">
          <td style=\"padding:9px 12px;font-size:14px;color:#0f1a14;\">{$lbl}</td>
          <td style=\"padding:9px 12px;font-size:14px;text-decoration:line-through;color:#8a8f8b;\">\${$orig}</td>
          <td style=\"padding:9px 12px;font-size:14px;font-weight:700;color:#16a36a;\">\${$disc}</td>
        </tr>";
    }

    return <<<HTML
<!doctype html>
<html><body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;margin:0;padding:24px;color:#0f1a14;">
<div style="max-width:580px;margin:0 auto;background:#fff;border-radius:18px;padding:40px 36px;border:1px solid #e3e0d6;box-shadow:0 4px 24px rgba(15,26,20,.06);">

  <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
    <span style="width:16px;height:16px;border-radius:50%;background:#16a36a;display:inline-block;flex-shrink:0;"></span>
    <strong style="font-size:18px;letter-spacing:-.01em;">{$brandE}</strong>
  </div>

  <h1 style="font-size:24px;font-weight:700;margin:0 0 12px;letter-spacing:-.02em;">{$h1}</h1>
  <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 18px;">{$typeIntro}</p>
  <p style="font-size:15px;line-height:1.6;color:#4a5651;margin:0 0 22px;">With {$brandE} you get <strong>{$typeBenefit}</strong>.</p>

  <!-- Benefits -->
  <div style="background:#f3faf6;border-radius:14px;padding:20px 22px;margin:0 0 24px;">
    <p style="font-size:12px;font-weight:700;color:#16a36a;letter-spacing:.07em;text-transform:uppercase;margin:0 0 14px;">What's inside your program</p>
    <table style="border-collapse:collapse;width:100%;">
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;width:30px;">🩸</td><td style="padding:6px 0;font-size:14px;color:#2d4a3a;line-height:1.5;"><strong>Blood-sugar-aware workouts</strong> — every session designed to stabilise your glucose, not spike it</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">🥗</td><td style="padding:6px 0;font-size:14px;color:#2d4a3a;line-height:1.5;"><strong>Personalised meal guide</strong> — eat foods you love, built around your blood sugar response</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">💬</td><td style="padding:6px 0;font-size:14px;color:#2d4a3a;line-height:1.5;"><strong>24/7 coach access</strong> — message your coach any time, real answers fast</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">📊</td><td style="padding:6px 0;font-size:14px;color:#2d4a3a;line-height:1.5;"><strong>Progress &amp; glucose tracker</strong> — log weight, steps and readings in one place</td></tr>
      <tr><td style="padding:6px 0;vertical-align:top;font-size:18px;">📅</td><td style="padding:6px 0;font-size:14px;color:#2d4a3a;line-height:1.5;"><strong>Weekly check-ins</strong> — your coach reviews your week and adjusts the plan</td></tr>
    </table>
  </div>

  <!-- Discount Code -->
  <div style="background:#fffbea;border:2px dashed #f0c84a;border-radius:14px;padding:22px;text-align:center;margin:0 0 24px;">
    <p style="font-size:12px;font-weight:700;color:#92700a;letter-spacing:.07em;text-transform:uppercase;margin:0 0 8px;">Exclusive offer — just for you</p>
    <p style="font-size:14px;color:#4a5651;margin:0 0 14px;">Use this code at checkout for an <strong style="color:#92700a;">extra 20%&nbsp;off</strong> any plan:</p>
    <div style="background:#fff;border:2px solid #f0c84a;border-radius:10px;padding:14px 28px;display:inline-block;margin:0 0 12px;">
      <span style="font-family:'Courier New',Courier,monospace;font-size:24px;font-weight:700;letter-spacing:.15em;color:#0f1a14;">JUSTFORYOU</span>
    </div>
    <p style="font-size:12px;color:#8a8f8b;margin:0;">Limited time — expires in 48&nbsp;hours</p>
  </div>

  <!-- Pricing with discount -->
  <p style="font-size:14px;font-weight:700;color:#0f1a14;margin:0 0 8px;">Your prices with the code applied:</p>
  <table style="border-collapse:collapse;width:100%;border:1px solid #e3e0d6;border-radius:10px;overflow:hidden;margin:0 0 24px;">
    <thead><tr style="background:#f7f5f0;">
      <th style="padding:9px 12px;font-size:12px;color:#8a8f8b;text-align:left;font-weight:600;">Plan</th>
      <th style="padding:9px 12px;font-size:12px;color:#8a8f8b;text-align:left;font-weight:600;">Without code</th>
      <th style="padding:9px 12px;font-size:12px;color:#16a36a;text-align:left;font-weight:700;">With JUSTFORYOU</th>
    </tr></thead>
    <tbody>{$planRows}</tbody>
  </table>

  <div style="text-align:center;margin:0 0 24px;">
    <a href="{$offerUrl}" style="background:#16a36a;color:#fff;padding:16px 36px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;display:inline-block;">Get my plan now →</a>
  </div>

  <hr style="border:none;border-top:1px solid #e3e0d6;margin:0 0 14px;" />
  <p style="font-size:11px;color:#8a8f8b;line-height:1.6;margin:0;text-align:center;">Questions? Reply here or write to <a href="mailto:{$sup}" style="color:#0d7d4f;">{$sup}</a>.<br>DiaFitus is fitness coaching, not medical advice. Always consult your doctor.</p>
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
