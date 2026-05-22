<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/stripe.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/pdf.php';

$sessionId = $_GET['session_id'] ?? '';
$paid = false;
$user = user_session();
$answers = answers();

if ($sessionId) {
    try {
        $sess = stripe_get_session($sessionId);
        if (($sess['payment_status'] ?? '') === 'paid' || ($sess['status'] ?? '') === 'complete') {
            $paid = true;
            $email = $sess['customer_details']['email'] ?? ($user['email'] ?? '');
            $name  = $sess['customer_details']['name']  ?? ($user['firstName'] ?? '');
            $phone = $user['phone'] ?? '';
            $stripeCustomer = $sess['customer'] ?? '';
            $stripeSub      = $sess['subscription'] ?? '';

            if ($email) {
                $planDays = (int) ($sess['metadata']['plan_days'] ?? 84);
                $res = lead_mark_paid($email, $stripeCustomer, $stripeSub, $name, $phone, $planDays);
                $leadId = $res['id'];
                $_SESSION['user'] = ['firstName' => $name, 'email' => $email, 'phone' => $phone];

                if (empty($_SESSION['post_purchase_done'])) {
                    try {
                        $token = create_account_setup_token($leadId, 168);
                        $url = rtrim(cfg('site_url'), '/') . '/setup?token=' . $token;
                        send_email(
                            $email, $name ?: 'there',
                            'Welcome to DiaFitus — create your account',
                            account_setup_email_html($name ?: 'there', $url)
                        );
                    } catch (Throwable $ex) { error_log('setup email: ' . $ex->getMessage()); }

                    try {
                        $tmpPdf = sys_get_temp_dir() . '/diafitus_paid_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
                        build_lead_pdf($tmpPdf, $answers, ['firstName' => $name, 'email' => $email, 'phone' => $phone]);
                        $lines = ['<b>💸 DiaFitus — NEW PAID MEMBER</b>'];
                        $lines[] = 'Amount: $' . number_format((float)($sess['amount_total'] ?? cfg('price_today') * 100) / 100, 2) . ' (one-time)';
                        $lines[] = '<b>Name:</b> ' . htmlspecialchars($name);
                        $lines[] = '<b>Email:</b> ' . htmlspecialchars($email);
                        $lines[] = '<b>Phone:</b> ' . htmlspecialchars($phone);
                        $lines[] = '';
                        foreach ($answers as $k => $v) {
                            $val = is_array($v) ? implode(', ', $v) : $v;
                            $lines[] = '<b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ':</b> ' . htmlspecialchars($val);
                        }
                        tg_send_message(implode("\n", $lines));
                        tg_send_document($tmpPdf, 'New paid member — questionnaire (PDF)');
                        @unlink($tmpPdf);
                    } catch (Throwable $ex) { error_log('telegram paid: ' . $ex->getMessage()); }

                    $_SESSION['post_purchase_done'] = true;
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('success.php verify error: ' . $ex->getMessage());
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
            <li>Check your inbox for the <strong>"Create your account"</strong> email from <?= e(cfg('mail.from_email')) ?></li>
            <li>Click the link in that email to set your password</li>
            <li>Sign in at <a href="/login">diafitus.com/login</a> with your email and the password you just chose</li>
            <li>Our team is building your personalized program — it'll appear in your dashboard within 24 hours</li>
          </ul>
        </div>
        <p class="muted">Didn't get the email after a minute? Check spam, or email <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a> and we'll resend.</p>
      <?php else: ?>
        <div class="success-icon">⏳</div>
        <h1>Almost there…</h1>
        <p class="lede">We're confirming your payment. If you've been charged but don't get the welcome email in a minute, contact <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a>.</p>
        <a href="/login" class="btn btn-primary btn-lg">Go to login</a>
      <?php endif; ?>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
