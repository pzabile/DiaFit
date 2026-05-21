<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$weekNumber  = member_week_number($me);
$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$me['id']]);

// Inline coach comments per weekly check-in.
$ids = array_column($weeklyNotes, 'id');
$commentsByWeek = [];
$repliesByParent = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $cs = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND target_type = "weekly_note" AND target_id IN (' . $in . ') ORDER BY created_at ASC',
        array_merge([$me['id']], $ids));
    foreach ($cs as $c) {
        if ($c['parent_id']) $repliesByParent[(int)$c['parent_id']][] = $c;
        else $commentsByWeek[(int)$c['target_id']][] = $c;
    }
}
$csrf = csrf_input();

$pageTitle = 'Weekly check-in — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'checkin';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

  <header class="page-head">
    <div>
      <p class="kicker">Week <?= (int) $weekNumber ?></p>
      <h1>How was your week?</h1>
    </div>
    <span class="chip">~2 min</span>
  </header>

  <section class="card big">
    <p class="muted">Leave a check-in so your coach can adjust your program. The more you share, the better we can help.</p>
    <form method="post" action="/save_weekly" class="form premium-form" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="week_number" value="<?= (int) $weekNumber ?>" />
      <div class="grid-3">
        <label>Avg blood glucose<input type="number" name="avg_glucose" min="40" max="500" placeholder="e.g. 118 mg/dL" /></label>
        <label>Weight (lbs)
          <input type="number" step="0.1" name="weight_lbs" min="60" max="600" placeholder="e.g. 187" />
          <small class="muted-inline">(metric: 1 lb ≈ 0.45 kg)</small>
        </label>
        <label>Energy (0–10)<input type="number" name="energy_rating" min="0" max="10" placeholder="e.g. 7" /></label>
      </div>
      <label>Wins this week 🏆
        <textarea name="wins" placeholder="What went well? Workouts you completed, hypos avoided, meals on track…"></textarea>
      </label>
      <label>Struggles 😔
        <textarea name="struggles" placeholder="What was hard? Sugar spikes, soreness, time, motivation…"></textarea>
      </label>
      <label>Anything else
        <textarea name="content" placeholder="Symptoms, medication changes, life events, questions for your coach…"></textarea>
      </label>
      <button type="submit" class="btn btn-primary btn-lg">Save week <?= (int) $weekNumber ?> check-in</button>
    </form>
  </section>

  <?php if ($weeklyNotes): ?>
  <section class="card big">
    <div class="card-head">
      <div>
        <p class="kicker">History</p>
        <h2>Past weeks</h2>
      </div>
      <span class="chip"><?= count($weeklyNotes) ?> check-in<?= count($weeklyNotes) === 1 ? '' : 's' ?></span>
    </div>
    <div class="timeline">
      <?php foreach ($weeklyNotes as $w):
        $wLb = $w['weight_kg'] ? round($w['weight_kg'] * 2.20462, 1) : null;
      ?>
        <article class="tl-week">
          <div class="tl-badge">W<?= (int) $w['week_number'] ?></div>
          <div class="tl-body">
            <header>
              <strong>Week <?= (int) $w['week_number'] ?></strong>
              <small><?= e(date('M j, Y', strtotime($w['created_at']))) ?></small>
            </header>
            <div class="tl-chips">
              <?php if ($w['avg_glucose']):    ?><span class="chip">🩸 <?= (int)$w['avg_glucose'] ?> mg/dL</span><?php endif; ?>
              <?php if ($wLb):                 ?><span class="chip">⚖️ <?= e($wLb) ?> lbs</span><?php endif; ?>
              <?php if ($w['energy_rating'] !== null): ?><span class="chip">⚡ <?= (int)$w['energy_rating'] ?>/10</span><?php endif; ?>
            </div>
            <?php if ($w['wins']):      ?><p><strong>Wins.</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
            <?php if ($w['struggles']): ?><p><strong>Struggles.</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
            <?php if ($w['content']):   ?><p class="muted"><?= nl2br(e($w['content'])) ?></p><?php endif; ?>
            <?php $cmts = $commentsByWeek[$w['id']] ?? []; if ($cmts): ?>
              <div class="inline-comments">
                <?php foreach ($cmts as $c):
                  $replies = $repliesByParent[$c['id']] ?? [];
                ?>
                  <div class="coach-bubble <?= e($c['kind']) ?> from-coach mini">
                    <header>
                      <span class="kind-tag">Coach</span>
                      <small><?= e(date('M j, g:ia', strtotime($c['created_at']))) ?></small>
                    </header>
                    <p><?= nl2br(e($c['body'])) ?></p>
                    <?php foreach ($replies as $r): ?>
                      <div class="reply <?= $r['from_member'] ? 'from-member' : 'from-coach' ?>">
                        <header><strong><?= $r['from_member'] ? 'You' : 'Coach' ?></strong><small><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></small></header>
                        <p><?= nl2br(e($r['body'])) ?></p>
                      </div>
                    <?php endforeach; ?>
                    <form method="post" action="/reply_note" class="reply-form">
                      <?= $csrf ?>
                      <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>" />
                      <input type="hidden" name="redirect"  value="/checkin" />
                      <input type="text" name="body" placeholder="Reply…" maxlength="2000" required />
                      <button class="btn btn-ghost btn-sm">Reply</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
