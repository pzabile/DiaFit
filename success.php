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
$plainPwd = null;

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
                $res = lead_mark_paid($email, $stripeCustomer, $stripeSub, $name, $phone);
                $leadId = $res['id'];
                $plainPwd = $res['password'];

                $_SESSION['member_id'] = $leadId;
                $_SESSION['user'] = ['firstName' => $name, 'email' => $email, 'phone' => $phone];

                if (empty($_SESSION['post_purchase_done'])) {
                    try {
                        send_email(
                            $email, $name ?: 'there',
                            'Welcome to DiaFitus — your program is being built',
                            welcome_email_html($name ?: 'there', $email, $plainPwd)
                        );
                    } catch (Throwable $ex) { error_log('welcome email: ' . $ex->getMessage()); }

                    try {
                        $tmpPdf = sys_get_temp_dir() . '/diafitus_paid_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
                        build_lead_pdf($tmpPdf, $answers, ['firstName' => $name, 'email' => $email, 'phone' => $phone]);

                        $lines = ['<b>💸 DiaFitus — NEW PAID MEMBER</b>'];
                        $lines[] = 'Amount: $' . (int) cfg('price_today') . '/month';
                        $lines[] = 'Stripe session: ' . $sessionId;
                        $lines[] = '';
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
        <h1>You're in, <?= e($user['firstName'] ?? 'champion') ?>!</h1>
        <p class="lede">Your DiaFitus membership is active. We've sent a welcome email to <strong><?= e($user['email'] ?? '') ?></strong>.</p>
        <?php if ($plainPwd): ?>
          <div class="cred-box">
            <p><strong>Save your login</strong> — we've also emailed this to you:</p>
            <div class="cred-row"><span>Email</span><code><?= e($user['email'] ?? '') ?></code></div>
            <div class="cred-row"><span>Password</span><code><?= e($plainPwd) ?></code></div>
            <p class="micro">You can change your password in your dashboard.</p>
          </div>
        <?php endif; ?>
        <div class="next-card">
          <h3>What happens next</h3>
          <ul class="check-list">
            <li>Our team is building your program <strong>right now</strong></li>
            <li>You'll have your personalized plan and nutrition guide within <strong>24 hours</strong></li>
            <li>You'll get 24/7 support to ask any questions</li>
            <li>Track everything from your private dashboard</li>
          </ul>
        </div>
        <a href="dashboard" class="btn btn-primary btn-xl">Open my dashboard →</a>
      <?php else: ?>
        <div class="success-icon">⏳</div>
        <h1>Almost there…</h1>
        <p class="lede">We're confirming your payment. If you've been charged but don't see your dashboard within a minute, contact <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a>.</p>
        <a href="login" class="btn btn-primary btn-lg">Go to login</a>
      <?php endif; ?>
    </div>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
