<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pw    = (string) ($_POST['password'] ?? '');
        if (!$email || !$pw) {
            $error = 'Please enter your email and password.';
        } else {
            try {
                $lead = login_lead($email, $pw);
            } catch (Throwable $ex) {
                error_log('login error: ' . $ex->getMessage());
                $lead = false;
                $error = 'The login system is temporarily unavailable. Please try again in a moment.';
            }
            if (!$error) {
                if ($lead && !empty($lead['paid'])) {
                    header('Location: /dashboard'); exit;
                }
                if ($lead && empty($lead['paid'])) {
                    $error = 'Your account exists but has not been activated yet. If you recently paid, email <a href="mailto:' . e(cfg('support_email')) . '" style="color:inherit;font-weight:600;">' . e(cfg('support_email')) . '</a> and we\'ll activate it right away.';
                } else {
                    $error = 'Email or password incorrect. If you just paid, check your welcome email for the "Create my account" link to set your password.';
                }
            }
        }
    }
}

$pageTitle = 'Sign in — DiaFitus';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <a href="/" class="btn btn-ghost">Back to site</a>
  </header>

  <main class="auth-main">
    <div class="auth-card">
      <span class="pill green">Member login</span>
      <h1>Sign in to your dashboard</h1>
      <p class="sub">Enter the email you used at checkout and the password you set after payment.</p>

      <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

      <form method="post" class="form" autocomplete="on">
        <?= csrf_input() ?>
        <label>Email
          <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" />
        </label>
        <label>Password
          <input type="password" name="password" required />
        </label>
        <button type="submit" class="btn btn-primary btn-xl">Sign in →</button>
        <p class="micro"><a href="/forgot">Forgot your password?</a></p>
        <p class="micro">Don't have an account yet? <a href="/questionnaire">Take the free assessment</a>.</p>
      </form>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
