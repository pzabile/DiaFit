<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';
require_admin();

$flash = '';
$error = '';
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $firstName = trim($_POST['first_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $planDays  = (int) ($_POST['plan_days'] ?? 84);
    $sendEmail = !empty($_POST['send_email']);

    $allowed = [7, 28, 84];
    if (!in_array($planDays, $allowed, true)) $planDays = 84;

    if (!$firstName || !$email) {
        $error = 'First name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $existing = lead_find_by_email($email);
        if ($existing) {
            $error = 'A member with that email already exists. <a href="/admin/member?id=' . (int)$existing['id'] . '">Open their profile →</a>';
        } else {
            try {
                $id = db_insert(
                    'INSERT INTO leads (email, phone, first_name, paid, plan_days, started_at, created_at, updated_at)
                     VALUES (?, ?, ?, 1, ?, CURDATE(), NOW(), NOW())',
                    [$email, $phone, $firstName, $planDays]
                );

                if ($sendEmail) {
                    $token  = create_account_setup_token($id, 168);
                    $url    = rtrim(cfg('site_url'), '/') . '/setup?token=' . $token;
                    send_email(
                        $email, $firstName ?: 'there',
                        'Welcome to DiaFitus — create your account',
                        account_setup_email_html($firstName ?: 'there', $url)
                    );
                    $flash = "Member created and welcome email sent to {$email}.";
                } else {
                    $flash = "Member created. No email sent.";
                }

                $created = $id;
            } catch (Throwable $ex) {
                $error = 'Could not create member: ' . $ex->getMessage();
            }
        }
    }
}

$plans = cfg('plans');
$pageTitle = 'Create member — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'members';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
$csrf = csrf_input();
?>
  <main class="admin-main">

    <header class="admin-page-head">
      <div>
        <h1>Create new member</h1>
        <p class="muted">Manually add a paid member — useful for offline payments, transfers, or gifted plans.</p>
      </div>
      <a href="/admin/members" class="btn btn-ghost">← Back to members</a>
    </header>

    <?php if ($flash): ?>
      <div class="alert success">
        <?= e($flash) ?>
        <?php if ($created): ?>
          <a href="/admin/member?id=<?= (int)$created ?>" style="margin-left:.75rem;font-weight:600;">Open profile →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <section class="card big" style="max-width:560px;">
      <form method="post" class="form">
        <?= $csrf ?>

        <label>First name *
          <input type="text" name="first_name" required autofocus value="<?= e($_POST['first_name'] ?? '') ?>" />
        </label>

        <label>Email address *
          <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" />
        </label>

        <label>Phone (optional)
          <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" />
        </label>

        <label>Plan
          <select name="plan_days">
            <option value="7"  <?= ($_POST['plan_days'] ?? '84') === '7'  ? 'selected' : '' ?>>7-day jump-start</option>
            <option value="28" <?= ($_POST['plan_days'] ?? '84') === '28' ? 'selected' : '' ?>>28-day (4-week) reset</option>
            <option value="84" <?= ($_POST['plan_days'] ?? '84') === '84' ? 'selected' : '' ?>>84-day (12-week) transformation — recommended</option>
          </select>
        </label>

        <label style="display:flex;align-items:center;gap:.6rem;font-weight:normal;">
          <input type="checkbox" name="send_email" value="1" checked />
          Send welcome + account-setup email so they can set their password
        </label>

        <button class="btn btn-primary btn-xl">Create member</button>
      </form>
    </section>

  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
