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
            $lead = login_lead($email, $pw);
            if ($lead && !empty($lead['paid'])) {
                header('Location: dashboard'); exit;
            }
            $error = 'Email or password incorrect. If you just paid, please use the password from your welcome email.';
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
      <p class="sub">Enter the email and password from your welcome message to access your program, weekly logs and support.</p>

      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="form" autocomplete="on">
        <?= csrf_input() ?>
        <label>Email or username
          <input type="text" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username" />
        </label>
        <label>Password
          <input type="password" name="password" required />
        </label>
        <button type="submit" class="btn btn-primary btn-xl">Sign in →</button>
        <p class="micro">Don't have an account yet? <a href="questionnaire">Take the free assessment</a>.</p>
      </form>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
