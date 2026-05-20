<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mailer.php';

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter the email on your DiaFitus account.';
        } else {
            try {
                $res = create_password_reset_token($email);
                if ($res) {
                    $url = rtrim(cfg('site_url'), '/') . '/reset?token=' . $res['token'];
                    send_email(
                        $res['lead']['email'],
                        $res['lead']['first_name'] ?: 'there',
                        'DiaFitus — reset your password',
                        password_reset_email_html($res['lead']['first_name'], $url)
                    );
                }
            } catch (Throwable $ex) {
                error_log('forgot: ' . $ex->getMessage());
            }
            // Always show the same message, whether the email exists or not,
            // to prevent email enumeration.
            $sent = true;
        }
    }
}

$pageTitle = 'Reset password — DiaFitus';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <a href="/login" class="btn btn-ghost">Back to sign in</a>
  </header>

  <main class="auth-main">
    <div class="auth-card">
      <span class="pill green">Member password reset</span>
      <h1>Reset your password</h1>
      <?php if ($sent): ?>
        <div class="alert success">
          If an account exists for that email, we just sent a password-reset link.
          Check your inbox (and spam) in the next minute. The link expires in 1 hour.
        </div>
        <p class="muted">Didn't get it? Make sure you typed the email you used at checkout, then try again.</p>
        <a href="/login" class="btn btn-primary">Back to sign in</a>
      <?php else: ?>
        <p class="sub">Enter the email on your DiaFitus account and we'll send you a link to set a new password.</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form">
          <?= csrf_input() ?>
          <label>Email
            <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" />
          </label>
          <button type="submit" class="btn btn-primary btn-xl">Send reset link</button>
          <p class="micro"><a href="/login">Remembered it? Sign in instead</a></p>
        </form>
      <?php endif; ?>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
