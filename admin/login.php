<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = (string) ($_POST['password'] ?? '');
        if (login_admin($u, $p)) {
            header('Location: /admin/'); exit;
        }
        $error = 'Invalid credentials.';
        usleep(400 * 1000);
    }
}

$pageTitle = 'Admin login — DiaFitus';
$bodyClass = 'auth-page';
require __DIR__ . '/../includes/header.php';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus · admin</span>
    </a>
  </header>
  <main class="auth-main">
    <div class="auth-card">
      <h1>Admin sign in</h1>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_input() ?>
        <label>Username<input type="text" name="username" required autofocus /></label>
        <label>Password<input type="password" name="password" required /></label>
        <button type="submit" class="btn btn-primary btn-xl">Sign in</button>
      </form>
    </div>
  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
