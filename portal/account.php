<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$prog = member_program_info($me);

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    // Update profile fields
    $firstName = trim($_POST['first_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    if ($firstName) {
        db_exec(
            'UPDATE leads SET first_name = ?, email = COALESCE(NULLIF(?,?), email), phone = ?, updated_at = NOW() WHERE id = ?',
            [$firstName, $email, $me['email'], $phone, $leadId]
        );
        $flash = 'Changes saved.';
        $me = db_get('SELECT * FROM leads WHERE id = ?', [$leadId]);
    }
}

$pageTitle  = 'Account — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'account';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Account</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <a href="/portal/log" class="quick-log">
        <span class="plus"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
        Quick log
      </a>
    </div>
  </header>

  <section class="view">
    <div class="eyebrow">Account</div>
    <h1 class="h1">Your <em>settings</em>.</h1>
    <p class="muted" style="margin:0 0 18px;max-width:60ch">Profile, and how we reach you. Your data stays yours — export or delete anytime.</p>

    <?php if ($flash): ?>
      <div style="background:var(--sage-tint);color:var(--sage-2);border:1px solid var(--sage-tint-2);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-weight:600">
        <?= e($flash) ?>
      </div>
    <?php endif; ?>

    <div class="acct-grid">
      <div class="card">
        <div class="body" style="padding:6px 24px 18px">
          <form method="post" action="" id="acctForm">

            <div class="acct-section">
              <h4>Profile</h4>
              <div class="sub">How your coach sees you</div>
              <div class="field-grid">
                <div><label class="field-lbl">First name</label><input class="input" name="first_name" value="<?= e($me['first_name'] ?? '') ?>"></div>
                <div><label class="field-lbl">Email</label><input class="input" name="email" type="email" value="<?= e($me['email'] ?? '') ?>"></div>
                <div><label class="field-lbl">Phone</label><input class="input" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="+1 555 123 4567"></div>
                <div><label class="field-lbl">Date of birth</label><input class="input" type="date" name="dob" value="<?= e($me['dob'] ?? '') ?>"></div>
              </div>
            </div>

            <div class="acct-section">
              <h4>Program</h4>
              <div class="sub">Your current enrollment</div>
              <div style="display:flex;flex-direction:column;gap:6px">
                <div class="row" style="justify-content:space-between;padding:8px 0">
                  <span>Program</span>
                  <span style="font-weight:600"><?= $prog['total'] ?>-week plan</span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0">
                  <span>Started</span>
                  <span style="font-weight:600"><?= $me['started_at'] ? date('M j, Y', strtotime($me['started_at'])) : 'Not started' ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0">
                  <span>Current week</span>
                  <span style="font-weight:600">Week <?= $prog['current'] ?> of <?= $prog['total'] ?></span>
                </div>
                <div class="row" style="justify-content:space-between;padding:8px 0">
                  <span>Progress</span>
                  <span style="font-weight:600"><?= $prog['pct'] ?>% complete</span>
                </div>
              </div>
            </div>

            <?= csrf_input() ?>
            <input type="hidden" name="_method" value="save">
          </form>
        </div>
        <div style="padding:16px 24px;background:linear-gradient(180deg,#FFFDF7,#F4F1E9);border-top:1px solid var(--line);display:flex;justify-content:space-between;border-radius:0 0 var(--r-lg) var(--r-lg)">
          <a href="/portal/today" class="btn">Cancel</a>
          <button type="submit" form="acctForm" class="btn pri">Save changes</button>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card">
          <div class="head"><div><div class="eyebrow">Subscription</div><h3 class="h3" style="margin-top:4px">DiaFit &middot; <?= $prog['total'] ?>-week program</h3></div><span class="chip sage">Active</span></div>
          <div class="body">
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Plan</div>
              <div style="text-align:right"><div class="val">Coach Plus</div></div>
            </div>
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Started</div>
              <div style="text-align:right"><div class="val" style="font-size:16px"><?= $me['started_at'] ? date('M j, Y', strtotime($me['started_at'])) : '—' ?></div></div>
            </div>
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Ends</div>
              <div style="text-align:right">
                <div class="val" style="font-size:16px">
                  <?= ($me['started_at'] && $me['plan_days']) ? date('M j, Y', strtotime($me['started_at'] . ' +' . (int)$me['plan_days'] . ' days')) : '—' ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="head"><div><div class="eyebrow">Your data</div><h3 class="h3" style="margin-top:4px">Privacy controls</h3></div></div>
          <div class="body">
            <a href="/portal/coach" class="btn" style="width:100%;margin-bottom:8px;justify-content:space-between">
              Message your coach
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>
            <button class="btn" style="width:100%;justify-content:space-between;color:#8A3F30;border-color:#E7CAC1" onclick="if(confirm('Are you sure? This will log you out.')) window.location='/logout'">
              Log out of my account
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
