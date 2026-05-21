<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$allNotes = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 ORDER BY created_at ASC', [$me['id']]);

$generalNotes = [];
$targetIndex  = [];
$replyIndex   = [];
foreach ($allNotes as $n) {
    if ($n['parent_id']) { $replyIndex[(int)$n['parent_id']][] = $n; continue; }
    if ($n['target_type'] && $n['target_id']) {
        $targetIndex[$n['target_type']][(int)$n['target_id']][] = $n;
    } else {
        $generalNotes[] = $n;
    }
}
$generalNotes = array_reverse($generalNotes); // newest first

$pageTitle = 'My Program — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'program';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
$csrf = csrf_input();
?>
<main class="dash-main">
  <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

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
        <p class="kicker">Conversation with your coach</p>
        <h2>Notes &amp; messages</h2>
      </div>
      <span class="chip green"><?= count($allNotes) ?> message<?= count($allNotes) === 1 ? '' : 's' ?></span>
    </div>

    <!-- New message form -->
    <form method="post" action="/reply_note" class="form premium-form" style="margin-bottom:1.25rem">
      <?= $csrf ?>
      <input type="hidden" name="redirect" value="/program" />
      <label>Send your coach a message
        <textarea name="body" required placeholder="Ask anything — about your plan, fueling, glucose, schedule…"></textarea>
      </label>
      <button class="btn btn-primary">Send message</button>
    </form>

    <?php if (!$generalNotes): ?>
      <p class="muted">No messages yet. Send the first one above — we usually reply within a few hours.</p>
    <?php else: ?>
      <div class="coach-stream">
        <?php foreach ($generalNotes as $n):
          $replies = $replyIndex[$n['id']] ?? [];
          $cls = $n['from_member'] ? 'from-member' : 'from-coach';
          $author = $n['from_member'] ? 'You' : ucfirst(str_replace('_', ' ', $n['kind']));
        ?>
          <article class="coach-bubble <?= e($n['kind']) ?> <?= $cls ?>">
            <header>
              <span class="kind-tag <?= e($n['kind']) ?>"><?= e($author) ?></span>
              <small><?= e(date('M j, Y · g:ia', strtotime($n['created_at']))) ?><?= $n['week_number'] ? ' · Week ' . (int)$n['week_number'] : '' ?></small>
            </header>
            <p><?= nl2br(e($n['body'])) ?></p>
            <?php if ($replies): ?>
              <div class="reply-thread">
                <?php foreach ($replies as $r):
                  $rcls = $r['from_member'] ? 'from-member' : 'from-coach';
                ?>
                  <div class="reply <?= $rcls ?>">
                    <header>
                      <strong><?= $r['from_member'] ? 'You' : 'Coach' ?></strong>
                      <small><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></small>
                    </header>
                    <p><?= nl2br(e($r['body'])) ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <form method="post" action="/reply_note" class="reply-form">
              <?= $csrf ?>
              <input type="hidden" name="parent_id" value="<?= (int)$n['id'] ?>" />
              <input type="hidden" name="redirect"  value="/program" />
              <input type="text" name="body" placeholder="Reply…" maxlength="2000" required />
              <button type="submit" class="btn btn-ghost btn-sm">Reply</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
