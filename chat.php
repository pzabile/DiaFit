<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$tgLink   = cfg('telegram.member_link', '#');
$supEmail = cfg('support_email');

$pageTitle = 'Chat with us — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'chat';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <header class="page-head">
    <div>
      <p class="kicker">Support</p>
      <h1>Chat with us — 24 / 7</h1>
    </div>
  </header>

  <section class="contact-grid">
    <a class="contact-card tg" href="<?= e($tgLink) ?>" target="_blank" rel="noopener">
      <div class="contact-icon">
        <svg viewBox="0 0 24 24" width="48" height="48" xmlns="http://www.w3.org/2000/svg">
          <path fill="#fff" d="M9.04 15.39l-.39 4.45c.56 0 .8-.24 1.09-.53l2.62-2.5 5.43 3.97c1 .55 1.71.26 1.96-.92l3.55-16.61c.34-1.55-.56-2.16-1.51-1.8L1.36 9.74c-1.5.58-1.48 1.42-.26 1.8l5.06 1.58 11.74-7.4c.55-.37 1.06-.16.64.21z"/>
        </svg>
      </div>
      <div>
        <strong>Message us on Telegram</strong>
        <p>Open the chat in the Telegram app. Coaches reply 7 days a week, day and night.</p>
        <span class="link">Open Telegram →</span>
      </div>
    </a>

    <a class="contact-card mail" href="mailto:<?= e($supEmail) ?>?subject=DiaFitus%20member%20%E2%80%94%20<?= rawurlencode($me['email']) ?>">
      <div class="contact-icon mail">
        <svg viewBox="0 0 24 24" width="44" height="44" xmlns="http://www.w3.org/2000/svg">
          <path fill="#fff" d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
        </svg>
      </div>
      <div>
        <strong>Email us</strong>
        <p>Prefer email? Write to <span class="mono"><?= e($supEmail) ?></span>. We answer within a few hours.</p>
        <span class="link">Open email →</span>
      </div>
    </a>
  </section>

  <section class="card big">
    <h2>What to message us about</h2>
    <ul class="check-list">
      <li>Questions about your program, technique, or replacements</li>
      <li>Pre- and post-workout fueling questions</li>
      <li>Unusual blood-sugar swings, hypos, hypers</li>
      <li>Medication changes from your doctor</li>
      <li>Schedule conflicts — we'll adjust the week</li>
      <li>Anything else — we'd rather hear it than not</li>
    </ul>
    <p class="muted" style="margin-top:1rem">
      DiaFitus is fitness and lifestyle coaching. We are not your doctor. For chest pain, severe hypoglycemia, vision loss, or any other emergency, call your local emergency number immediately.
    </p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
