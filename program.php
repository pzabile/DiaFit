<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$coachNotes = db_all('SELECT * FROM coach_notes WHERE lead_id = ? ORDER BY created_at DESC', [$me['id']]);

$pageTitle = 'My Program — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'program';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <header class="page-head">
    <div>
      <p class="kicker">Your plan</p>
      <h1>My personalized program</h1>
    </div>
    <?php if (!empty($me['program_path'])): ?>
      <a href="<?= e($me['program_path']) ?>" target="_blank" class="btn btn-primary">⤓ Download PDF</a>
    <?php endif; ?>
  </header>

  <?php if (!empty($me['program_path'])): ?>
    <section class="card big pdf-card">
      <p class="muted">Your coach uploaded a personalized plan tailored to your diabetes type, schedule and equipment. Refer back to it any time.</p>
      <div class="pdf-viewer">
        <object data="<?= e($me['program_path']) ?>#view=FitH&toolbar=1" type="application/pdf" class="pdf-frame">
          <iframe src="<?= e($me['program_path']) ?>" class="pdf-frame" title="Your program PDF"></iframe>
          <p class="muted" style="padding:1rem">
            Your browser cannot display the PDF inline.
            <a href="<?= e($me['program_path']) ?>" target="_blank">Open it in a new tab</a>.
          </p>
        </object>
      </div>
    </section>
  <?php else: ?>
    <section class="card big">
      <div class="empty-state">
        <div class="empty-icon">⏳</div>
        <strong>Your program is being built.</strong>
        <p>Our team is reviewing your assessment and tailoring a 12-week plan to your diabetes type, schedule and equipment. It usually appears here within 24 hours of payment. We'll message you the moment it's ready.</p>
        <a href="/chat" class="btn btn-ghost" style="margin-top:1rem">Message us if you have questions</a>
      </div>
    </section>
  <?php endif; ?>

  <section class="card big notes-card">
    <div class="notes-head">
      <div>
        <p class="kicker">From your coach</p>
        <h2>Notes &amp; feedback</h2>
      </div>
      <span class="chip green"><?= count($coachNotes) ?> note<?= count($coachNotes) === 1 ? '' : 's' ?></span>
    </div>
    <?php if (!$coachNotes): ?>
      <div class="empty-state">
        <div class="empty-icon">💬</div>
        <strong>No notes yet.</strong>
        <p>When your coach posts feedback, motivation, or plan changes, they'll appear here.</p>
      </div>
    <?php else: ?>
      <div class="coach-stream">
        <?php foreach ($coachNotes as $cn): ?>
          <article class="coach-bubble <?= e($cn['kind']) ?>">
            <header>
              <span class="kind-tag <?= e($cn['kind']) ?>"><?= e(ucfirst($cn['kind'])) ?></span>
              <small><?= e(date('M j, Y · g:ia', strtotime($cn['created_at']))) ?><?= $cn['week_number'] ? ' · week ' . (int)$cn['week_number'] : '' ?></small>
            </header>
            <p><?= nl2br(e($cn['body'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
