<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$lead  = $token ? find_lead_by_reset_token($token) : null;
$error = '';
$done  = false;

$invalid = !$lead;

if ($lead && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please reload and try again.';
    } else {
        $new = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'The two passwords do not match.';
        } else {
            consume_password_reset($lead['id'], $new);
            $done = true;
        }
    }
}

$pageTitle = 'Create your account — DiaFitus';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <a href="/login" class="btn btn-ghost">Sign in</a>
  </header>

  <main class="auth-main">
    <div class="auth-card">
      <?php if ($invalid): ?>
        <span class="pill" style="background:#fde2de;color:#a3261c;">Link invalid</span>
        <h1>This setup link doesn't work</h1>
        <p class="sub">It may have expired (links last 7 days) or already been used. Email <a href="mailto:<?= e(cfg('support_email')) ?>"><?= e(cfg('support_email')) ?></a> and we'll send a fresh one.</p>
      <?php elseif ($done): ?>
        <span class="pill green">Account ready</span>
        <h1>You're all set.</h1>
        <p class="sub">Your password has been saved. Sign in to access your dashboard.</p>
        <a href="/login" class="btn btn-primary btn-xl">Sign in</a>
      <?php else: ?>
        <span class="pill green">Create your account</span>
        <h1>Welcome to DiaFitus.</h1>
        <p class="sub">For <strong><?= e($lead['email']) ?></strong>. Choose a password (minimum 8 characters) — you'll use this with your email to sign in.</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form" autocomplete="off">
          <?= csrf_input() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>" />
          <label>Choose a password
            <input type="password" name="new" required minlength="8" autofocus autocomplete="new-password" />
          </label>
          <label>Confirm password
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password" />
          </label>
          <button type="submit" class="btn btn-primary btn-xl">Create my account</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
