<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$tgLink   = cfg('telegram.member_link', '#');
$supEmail = cfg('support_email');

$pageTitle = 'Chat with us — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'chat';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <div style="margin-bottom:28px">
      <p class="eyebrow sage">Support</p>
      <h1 class="h2 serif" style="margin-top:4px">Chat with us — 24 / 7</h1>
      <p style="color:var(--muted);font-size:13.5px;margin-top:6px">We reply 7 days a week via Telegram or email.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px">
      <a href="<?= e($tgLink) ?>" target="_blank" rel="noopener" style="display:flex;gap:16px;align-items:flex-start;background:var(--card);border:1px solid var(--line);border-radius:var(--r-lg);padding:24px;transition:.12s">
        <div style="width:48px;height:48px;border-radius:12px;background:#27A7E7;display:grid;place-items:center;flex-shrink:0">
          <svg viewBox="0 0 24 24" width="26" height="26" xmlns="http://www.w3.org/2000/svg">
            <path fill="#fff" d="M9.04 15.39l-.39 4.45c.56 0 .8-.24 1.09-.53l2.62-2.5 5.43 3.97c1 .55 1.71.26 1.96-.92l3.55-16.61c.34-1.55-.56-2.16-1.51-1.8L1.36 9.74c-1.5.58-1.48 1.42-.26 1.8l5.06 1.58 11.74-7.4c.55-.37 1.06-.16.64.21z"/>
          </svg>
        </div>
        <div>
          <div style="font-weight:700;font-size:14.5px;margin-bottom:6px">Message us on Telegram</div>
          <p style="font-size:13px;color:var(--muted);margin:0 0 12px;line-height:1.5">Open the chat in the Telegram app. Coaches reply 7 days a week, day and night.</p>
          <span style="font-size:13px;font-weight:600;color:var(--sky)">Open Telegram →</span>
        </div>
      </a>

      <a href="mailto:<?= e($supEmail) ?>?subject=DiaFitus%20member%20%E2%80%94%20<?= rawurlencode($me['email']) ?>" style="display:flex;gap:16px;align-items:flex-start;background:var(--card);border:1px solid var(--line);border-radius:var(--r-lg);padding:24px;transition:.12s">
        <div style="width:48px;height:48px;border-radius:12px;background:var(--sage-3);display:grid;place-items:center;flex-shrink:0">
          <svg viewBox="0 0 24 24" width="24" height="24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#fff" d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
          </svg>
        </div>
        <div>
          <div style="font-weight:700;font-size:14.5px;margin-bottom:6px">Email us</div>
          <p style="font-size:13px;color:var(--muted);margin:0 0 12px;line-height:1.5">Prefer email? Write to <span class="mono" style="font-size:12.5px"><?= e($supEmail) ?></span>. We answer within a few hours.</p>
          <span style="font-size:13px;font-weight:600;color:var(--sage-2)">Open email →</span>
        </div>
      </a>
    </div>

    <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px">
      <div style="font-weight:700;font-size:15px;margin-bottom:16px">What to message us about</div>
      <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px">
        <?php foreach ([
          'Questions about your program, technique, or exercise replacements',
          'Pre- and post-workout fueling questions',
          'Unusual blood-sugar swings, hypos, or hyper events',
          'Medication changes from your doctor',
          'Schedule conflicts — we\'ll adjust the week',
          'Anything else — we\'d rather hear it than not',
        ] as $item): ?>
          <li style="display:flex;align-items:flex-start;gap:10px;font-size:13.5px">
            <span style="width:18px;height:18px;border-radius:50%;background:var(--sage-tint);color:var(--sage-2);display:grid;place-items:center;flex-shrink:0;margin-top:1px">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <?= e($item) ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p style="color:var(--muted);font-size:12.5px;margin:16px 0 0;padding-top:16px;border-top:1px solid var(--line)">
        DiaFitus is fitness and lifestyle coaching. We are not your doctor. For chest pain, severe hypoglycemia, vision loss, or any other emergency, call your local emergency number immediately.
      </p>
    </div>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
