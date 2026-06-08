<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/stripe.php';
require __DIR__ . '/includes/paypal.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/pdf.php';

$sessionId = $_GET['session_id'] ?? '';
$via       = $_GET['via'] ?? '';
$paid = false;
$user = user_session();
$answers = answers();

function _success_mark_paid(string $email, string $name, string $phone, int $planDays, string $stripeCustomer, string $stripeSub, array $answers, ?float $amountTotal): void {
    $res    = lead_mark_paid($email, $stripeCustomer, $stripeSub, $name, $phone, $planDays);
    $leadId = $res['id'];
    $_SESSION['user'] = ['firstName' => $name, 'email' => $email, 'phone' => $phone];

    if (!empty($_SESSION['welcome_sent_' . $leadId])) return;

    // Build setup URL — fall back to /login if the DB token columns don't exist yet
    $setupUrl = rtrim(cfg('site_url'), '/') . '/login';
    try {
        $token    = create_account_setup_token($leadId, 168);
        $setupUrl = rtrim(cfg('site_url'), '/') . '/setup?token=' . $token;
    } catch (Throwable $ex) {
        error_log('setup token error: ' . $ex->getMessage());
    }

    // Send welcome email independently — even if token creation failed
    try {
        $sent = send_email(
            $email, $name ?: 'there',
            'Welcome to DiaFitus — you\'re in 🎉',
            account_setup_email_html($name ?: 'there', $setupUrl, $email),
            null,
            nutrition_guide_attachment()
        );
        if (!$sent) error_log('welcome email: mail() returned false for ' . $email);
    } catch (Throwable $ex) { error_log('welcome email error: ' . $ex->getMessage()); }

    try {
        $tmpPdf = sys_get_temp_dir() . '/diafitus_paid_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
        build_lead_pdf($tmpPdf, $answers, ['firstName' => $name, 'email' => $email, 'phone' => $phone]);
        $amt    = $amountTotal !== null ? number_format($amountTotal / 100, 2) : '?';
        $lines  = ['<b>💸 DiaFitus — NEW PAID MEMBER</b>'];
        $lines[] = 'Amount: $' . $amt . ' (one-time)';
        $lines[] = '<b>Name:</b> ' . htmlspecialchars($name);
        $lines[] = '<b>Email:</b> ' . htmlspecialchars($email);
        $lines[] = '<b>Phone:</b> ' . htmlspecialchars($phone);
        $lines[] = '';
        foreach ($answers as $k => $v) {
            $val     = is_array($v) ? implode(', ', $v) : $v;
            $lines[] = '<b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ':</b> ' . htmlspecialchars($val);
        }
        tg_send_message(implode("\n", $lines));
        tg_send_document($tmpPdf, 'New paid member — questionnaire (PDF)');
        @unlink($tmpPdf);
    } catch (Throwable $ex) { error_log('telegram paid: ' . $ex->getMessage()); }

    $_SESSION['welcome_sent_' . $leadId] = true;
}

$apiError = false;
if ($sessionId) {
    try {
        $sess = stripe_get_session($sessionId);
        if (($sess['payment_status'] ?? '') === 'paid' || ($sess['status'] ?? '') === 'complete') {
            $paid  = true;
            $email = $sess['customer_details']['email'] ?? ($user['email'] ?? '');
            // Prefer the first name from the signup form (session) over Stripe's full name
            $name  = !empty($user['firstName']) ? $user['firstName'] : ($sess['customer_details']['name'] ?? '');
            $phone = $user['phone'] ?? '';
            if ($email) {
                $planDays = (int)($sess['metadata']['plan_days'] ?? 84);
                _success_mark_paid($email, $name, $phone, $planDays, $sess['customer'] ?? '', $sess['subscription'] ?? '', $answers, isset($sess['amount_total']) ? (float)$sess['amount_total'] : null);
            }
        }
    } catch (Throwable $ex) {
        error_log('success.php stripe API error: ' . $ex->getMessage());
        $apiError = true;
    }
}

/* Fallback: Stripe API failed but session_id matches what we stored before the redirect.
   The session value was set server-side after a real Stripe checkout session was created,
   so matching it proves the user came back from a legitimate Stripe payment page. */
if (!$paid && $apiError && $sessionId && !empty($_SESSION['stripe_session_id'])
    && hash_equals((string)$_SESSION['stripe_session_id'], $sessionId)
    && !empty($user['email'])) {
    try {
        $planKey  = $_SESSION['plan'] ?? cfg('default_plan');
        $plans    = cfg('plans');
        $planDays = (int)($plans[$planKey]['days'] ?? 84);
        _success_mark_paid($user['email'], $user['firstName'] ?? '', $user['phone'] ?? '', $planDays, '', '', $answers, null);
        $paid = true;
        error_log('success.php: used session fallback for ' . $user['email']);
    } catch (Throwable $ex) {
        error_log('success.php fallback error: ' . $ex->getMessage());
    }
}

/* PayPal path: JS redirected here after successful capture in paypal_capture.php.
   The session flag ensures this is a genuine server-side capture, not a forged URL. */
if (!$paid && $via === 'paypal' && !empty($_SESSION['paypal_paid']) && !empty($user['email'])) {
    try {
        $planKey  = $_SESSION['paypal_plan'] ?? cfg('default_plan');
        $plans    = cfg('plans');
        $planDays = (int)($plans[$planKey]['days'] ?? 84);

        // Use stored capture result to extract amount (avoid a second API call)
        $captureData = $_SESSION['paypal_capture'] ?? [];
        $amtCents    = paypal_captured_cents($captureData);

        _success_mark_paid(
            $user['email'],
            $user['firstName'] ?? '',
            $user['phone'] ?? '',
            $planDays,
            'paypal',
            $_SESSION['paypal_order_id'] ?? '',
            $answers,
            $amtCents
        );
        $paid = true;

        // Clear so a page refresh doesn't re-run the block (emails already deduplicated by session flag anyway)
        unset($_SESSION['paypal_paid'], $_SESSION['paypal_capture'], $_SESSION['paypal_order_id']);
    } catch (Throwable $ex) {
        error_log('success.php paypal path: ' . $ex->getMessage());
    }
}

$pageTitle = 'Welcome to DiaFitus';
$bodyClass = 'success-page';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
  </header>

  <main class="success-main">
    <div class="success-card">
      <?php if ($paid): ?>
        <div class="success-icon">✅</div>
        <h1>You're in!</h1>
        <p class="lede">Your DiaFitus membership is active. We've just emailed <strong><?= e($user['email'] ?? 'your email') ?></strong> a link to create your account.</p>
        <div class="next-card">
          <h3>Next steps</h3>
          <ul class="check-list">
            <li>Check your inbox (and <strong>spam / junk folder</strong>) for the <strong>"Create your account"</strong> email from <?= e(cfg('mail.from_email')) ?></li>
            <li>Click the link in that email to set your password</li>
            <li>Sign in at <a href="/login">diafitus.com/login</a> with your email and the password you just chose</li>
            <li>Our team is building your personalized program — it'll appear in your dashboard within 24 hours</li>
          </ul>
        </div>
        <div style="margin-top:16px;padding:14px 18px;background:#fff8e1;border:1.5px solid #ffe082;border-radius:12px;font-size:14px;color:#5a4a00;">
          <strong>No email after 5 minutes?</strong> Please check your spam / junk folder first. If it's still not there, email us at <a href="mailto:<?= e(cfg('support_email')) ?>" style="color:#b8860b;font-weight:600;"><?= e(cfg('support_email')) ?></a> and we'll resend it right away.
        </div>
      <?php else: ?>
        <div class="success-icon">⏳</div>
        <h1>Almost there…</h1>
        <p class="lede">We're confirming your payment. If you've been charged but don't get the welcome email in a minute, contact <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a>.</p>
        <a href="/login" class="btn btn-primary btn-lg">Go to login</a>
      <?php endif; ?>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
