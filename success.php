<?php
require __DIR__ . '/includes/bootstrap.php';
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
            $name  = $sess['customer_details']['name']  ?? ($user['firstName'] ?? 'there');
            if ($email && empty($user['email'])) {
                $_SESSION['user']['email'] = $email;
                $user['email'] = $email;
            }
            if (!isset($user['firstName']) && $name) {
                $_SESSION['user']['firstName'] = $name;
                $user['firstName'] = $name;
            }

            if (empty($_SESSION['post_purchase_done'])) {
                // Welcome email
                try {
                    send_email(
                        $email,
                        $name,
                        'Welcome to DiaFitus — your program is being built',
                        welcome_email_html($name)
                    );
                } catch (Throwable $ex) {
                    error_log('Welcome email failed: ' . $ex->getMessage());
                }

                // Telegram notification + PDF
                try {
                    $tmpPdf = sys_get_temp_dir() . '/diafitus_paid_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
                    build_lead_pdf($tmpPdf, $answers, $user);

                    $lines = ["<b>💸 DiaFitus — NEW PAID MEMBER</b>"];
                    $lines[] = "Amount: $" . (int) cfg('price_today') . "/month";
                    $lines[] = "Stripe session: " . $sessionId;
                    $lines[] = "";
                    $lines[] = "<b>Contact</b>";
                    $lines[] = "Name: " . htmlspecialchars($user['firstName'] ?? '');
                    $lines[] = "Email: " . htmlspecialchars($email);
                    $lines[] = "Phone: " . htmlspecialchars($user['phone'] ?? '');
                    $lines[] = "";
                    $lines[] = "<b>Questionnaire</b>";
                    foreach ($answers as $k => $v) {
                        $val = is_array($v) ? implode(', ', $v) : $v;
                        $lines[] = "<b>" . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ":</b> " . htmlspecialchars($val);
                    }
                    tg_send_message(implode("\n", $lines));
                    tg_send_document($tmpPdf, 'New paid member — full questionnaire (PDF)');
                    @unlink($tmpPdf);
                } catch (Throwable $ex) {
                    error_log('Telegram dispatch failed: ' . $ex->getMessage());
                }

                $_SESSION['post_purchase_done'] = true;
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
    <a href="index.php" class="brand">
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
        <div class="next-card">
          <h3>What happens next</h3>
          <ul class="check-list">
            <li>Our coaches and doctors are building your program <strong>right now</strong></li>
            <li>You'll receive your personalized plan, nutrition PDF, and Telegram invite within <strong>24 hours</strong></li>
            <li>Access your dashboard any time at <a href="dashboard.php">my.diafitus.com</a></li>
          </ul>
        </div>
        <a href="dashboard.php" class="btn btn-primary btn-xl">Open my dashboard →</a>
      <?php else: ?>
        <div class="success-icon">⏳</div>
        <h1>Almost there…</h1>
        <p class="lede">We're confirming your payment. If you've been charged but don't see your dashboard within a minute, contact <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a>.</p>
        <a href="dashboard.php" class="btn btn-primary btn-lg">Go to dashboard</a>
      <?php endif; ?>
    </div>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
