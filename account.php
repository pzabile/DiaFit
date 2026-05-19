<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $dob       = trim($_POST['dob'] ?? '');
        $dobValid  = $dob && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : null;
        db_exec(
            'UPDATE leads SET first_name = ?, phone = ?, dob = ?, updated_at = NOW() WHERE id = ?',
            [$firstName, $phone, $dobValid, $me['id']]
        );
        $_SESSION['flash'] = 'Profile updated.';
        header('Location: account'); exit;
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $new     = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (!password_verify($current, $me['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            db_exec('UPDATE leads SET password_hash = ? WHERE id = ?',
                [password_hash($new, PASSWORD_BCRYPT), $me['id']]);
            $_SESSION['flash'] = 'Password updated.';
            header('Location: account'); exit;
        }
    }
}

$me = lead_find_by_id($me['id']); // refresh

$pageTitle = 'My account — DiaFitus';
$bodyClass = 'account-page';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav slim">
    <a href="dashboard" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <a href="dashboard" class="btn btn-ghost">Back to dashboard</a>
  </header>

  <main class="account-main">
    <h1>My account</h1>
    <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <section class="card big">
      <h2>Profile</h2>
      <form method="post" class="form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="profile" />
        <div class="grid-2">
          <label>First name<input type="text" name="first_name" value="<?= e($me['first_name']) ?>" required /></label>
          <label>Email<input type="email" value="<?= e($me['email']) ?>" disabled /></label>
        </div>
        <div class="grid-2">
          <label>Phone<input type="tel" name="phone" value="<?= e($me['phone']) ?>" /></label>
          <label>Date of birth<input type="date" name="dob" value="<?= e($me['dob']) ?>" /></label>
        </div>
        <button type="submit" class="btn btn-primary">Save profile</button>
      </form>
    </section>

    <section class="card big">
      <h2>Change password</h2>
      <form method="post" class="form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="password" />
        <label>Current password<input type="password" name="current" required /></label>
        <div class="grid-2">
          <label>New password<input type="password" name="new" minlength="8" required /></label>
          <label>Confirm new password<input type="password" name="confirm" minlength="8" required /></label>
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </section>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
