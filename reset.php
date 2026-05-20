<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$lead  = $token ? find_lead_by_reset_token($token) : null;
$error = '';
$done  = false;

if (!$lead) {
    $invalid = true;
} else {
    $invalid = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

$pageTitle = 'Set a new password — DiaFitus';
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
        <h1>This reset link doesn't work</h1>
        <p class="sub">It may have expired (links last 1 hour) or already been used. Start a new one — they're free.</p>
        <a href="/forgot" class="btn btn-primary btn-xl">Request a new link</a>
      <?php elseif ($done): ?>
        <span class="pill green">All set</span>
        <h1>Your password has been updated.</h1>
        <p class="sub">You can sign in with the new password right now.</p>
        <a href="/login" class="btn btn-primary btn-xl">Go to sign in</a>
      <?php else: ?>
        <span class="pill green">Member password reset</span>
        <h1>Choose a new password</h1>
        <p class="sub">For <strong><?= e($lead['email']) ?></strong>. Minimum 8 characters.</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form" autocomplete="off">
          <?= csrf_input() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>" />
          <label>New password
            <input type="password" name="new" required minlength="8" autofocus autocomplete="new-password" />
          </label>
          <label>Confirm new password
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password" />
          </label>
          <button type="submit" class="btn btn-primary btn-xl">Update password</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
