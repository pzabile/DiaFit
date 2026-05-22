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
        header('Location: /account'); exit;
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
            header('Location: /account'); exit;
        }
    }
}

$me = lead_find_by_id($me['id']);

$pageTitle = 'My account — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'account';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <div style="margin-bottom:28px">
      <p class="eyebrow sage">Settings</p>
      <h1 class="h2 serif" style="margin-top:4px">My profile &amp; account</h1>
    </div>

    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error" style="margin-bottom:20px"><?= e($error) ?></div><?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:20px">
      <!-- Profile -->
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px">
        <div style="font-weight:700;font-size:15px;margin-bottom:20px">Profile</div>
        <form method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="profile" />
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              First name
              <input type="text" name="first_name" value="<?= e($me['first_name']) ?>" required style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Email
              <input type="email" value="<?= e($me['email']) ?>" disabled style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg-2);font-size:13.5px;color:var(--muted)" />
            </label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Phone
              <input type="tel" name="phone" value="<?= e($me['phone']) ?>" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Date of birth
              <input type="date" name="dob" value="<?= e($me['dob']) ?>" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
          </div>
          <button type="submit" style="background:var(--ink);color:#F4F1E9;padding:10px 22px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer">Save profile</button>
        </form>
      </div>

      <!-- Password -->
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px">
        <div style="font-weight:700;font-size:15px;margin-bottom:20px">Change password</div>
        <form method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="password" />
          <div style="margin-bottom:14px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Current password
              <input type="password" name="current" required style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              New password
              <input type="password" name="new" minlength="8" required style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Confirm new password
              <input type="password" name="confirm" minlength="8" required style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
          </div>
          <button type="submit" style="background:var(--ink);color:#F4F1E9;padding:10px 22px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer">Update password</button>
        </form>
      </div>

      <!-- Plan info -->
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px">
        <div style="font-weight:700;font-size:15px;margin-bottom:14px">Membership</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
          <div>
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">Plan</div>
            <div style="font-weight:600;font-size:14px"><?= $me['plan_days'] ? $me['plan_days'] . '-day program' : '—' ?></div>
          </div>
          <div>
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">Started</div>
            <div style="font-weight:600;font-size:14px"><?= $me['started_at'] ? e(date('M j, Y', strtotime($me['started_at']))) : '—' ?></div>
          </div>
          <div>
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">Status</div>
            <div style="font-weight:600;font-size:14px"><?= $me['paid'] ? 'Active member' : 'Lead' ?></div>
          </div>
        </div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
          <a href="mailto:<?= e(cfg('support_email')) ?>?subject=Account%20help%20%E2%80%94%20<?= rawurlencode($me['email']) ?>" style="font-size:13px;color:var(--sage-2);font-weight:600">Need help with your account? Email us →</a>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
