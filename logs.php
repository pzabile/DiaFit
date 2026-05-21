<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$dailyLogs = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC, created_at DESC LIMIT 90', [$me['id']]);

// Pull every comment/reply on daily logs so we can render them inline.
$ids = array_column($dailyLogs, 'id');
$commentsByLog = [];
$repliesByParent = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $cs = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND target_type = "daily_log" AND target_id IN (' . $in . ') ORDER BY created_at ASC',
        array_merge([$me['id']], $ids));
    foreach ($cs as $c) {
        if ($c['parent_id']) $repliesByParent[(int)$c['parent_id']][] = $c;
        else $commentsByLog[(int)$c['target_id']][] = $c;
    }
}

$grouped = [];
foreach ($dailyLogs as $l) {
    $grouped[$l['log_date']][] = $l;
}

$pageTitle = 'Daily check-ins — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'logs';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
$csrf = csrf_input();
?>
<main class="dash-main">
  <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

  <header class="page-head">
    <div>
      <p class="kicker">Daily check-ins</p>
      <h1>How are you doing today?</h1>
    </div>
    <span class="chip">Add as many as you want, any day</span>
  </header>

  <!-- Quick-add form (collapsible on mobile for first impression) -->
  <section class="card big checkin-form-card">
    <details open>
      <summary>
        <strong>+ New check-in</strong>
        <small class="muted">workout, glucose, food, notes</small>
      </summary>
      <form method="post" action="/save_log" class="log-form premium-form">
        <?= $csrf ?>
        <div class="grid-2">
          <label>Date<input type="date" name="log_date" required value="<?= date('Y-m-d') ?>" /></label>
          <label>How did you feel?
            <select name="feeling">
              <option>Great</option><option>Good</option><option>Okay</option><option>Tired</option><option>Bad</option>
            </select>
          </label>
        </div>
        <fieldset>
          <legend>Workout</legend>
          <div class="grid-2">
            <label>Did you train?
              <select name="trained"><option>Yes</option><option>No</option><option>Partial</option></select>
            </label>
            <label>Where?
              <select name="train_where"><option>Gym</option><option>Home</option><option>Outdoors</option></select>
            </label>
          </div>
          <label>What did you do?<textarea name="workout" placeholder="e.g. Squats 4x8 @ 135 lbs, lat pulldown 3x10, 15m incline walk"></textarea></label>
          <label>Muscle soreness (0–10)<input type="range" min="0" max="10" name="soreness" value="0" oninput="this.nextElementSibling.textContent=this.value" /><output>0</output></label>
        </fieldset>
        <fieldset>
          <legend>Blood sugar</legend>
          <div class="grid-3">
            <label>Before (mg/dL)<input type="number" name="bs_before" min="40" max="500" placeholder="120" /></label>
            <label>After (mg/dL)<input type="number" name="bs_after" min="40" max="500" placeholder="105" /></label>
            <label>Trend
              <select name="bs_trend"><option>Stable</option><option>Increased</option><option>Decreased</option><option>Hypo event</option><option>Hyper event</option></select>
            </label>
          </div>
        </fieldset>
        <fieldset>
          <legend>Food</legend>
          <label>Before training<input type="text" name="food_before" placeholder="e.g. oatmeal + banana" /></label>
          <label>After training<input type="text" name="food_after" placeholder="e.g. chicken, rice, salad" /></label>
        </fieldset>
        <label>Observations<textarea name="notes" placeholder="Energy, mood, symptoms, sleep, medication changes…"></textarea></label>
        <button type="submit" class="btn btn-primary btn-lg">Save check-in</button>
      </form>
    </details>
  </section>

  <!-- History grouped by day -->
  <?php if (!$dailyLogs): ?>
    <section class="card big">
      <div class="empty-state">
        <div class="empty-icon">📓</div>
        <strong>No check-ins yet.</strong>
        <p>Add your first one above and we'll start building your story.</p>
      </div>
    </section>
  <?php else: ?>
    <section class="card big">
      <div class="card-head">
        <div>
          <p class="kicker">History</p>
          <h2>Your check-ins</h2>
        </div>
        <span class="chip"><?= count($dailyLogs) ?> total</span>
      </div>
      <div class="log-by-day">
        <?php foreach ($grouped as $date => $items): ?>
          <div class="day-group">
            <div class="day-label">
              <strong><?= e(date('l, M j, Y', strtotime($date))) ?></strong>
              <span class="chip"><?= count($items) ?> entr<?= count($items) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <?php foreach ($items as $l): ?>
              <article class="log-entry-card">
                <header>
                  <span class="time"><?= e(date('g:ia', strtotime($l['created_at']))) ?></span>
                  <span class="chip"><?= e($l['feeling']) ?></span>
                  <span class="chip">trained: <?= e($l['trained']) ?><?= $l['train_where'] ? ' · ' . e($l['train_where']) : '' ?></span>
                </header>
                <div class="log-metrics">
                  <?php if ($l['bs_before'] || $l['bs_after']): ?>
                    <div><span class="muted">Glucose</span><strong><?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?></strong><small><?= e($l['bs_trend']) ?></small></div>
                  <?php endif; ?>
                  <div><span class="muted">Soreness</span><strong><?= (int)$l['soreness'] ?>/10</strong></div>
                </div>
                <?php if ($l['workout']):     ?><p><strong>Workout.</strong> <?= nl2br(e($l['workout'])) ?></p><?php endif; ?>
                <?php if ($l['food_before']): ?><p><strong>Before.</strong> <?= e($l['food_before']) ?></p><?php endif; ?>
                <?php if ($l['food_after']):  ?><p><strong>After.</strong>  <?= e($l['food_after']) ?></p><?php endif; ?>
                <?php if ($l['notes']):       ?><p class="muted"><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>

                <?php $comments = $commentsByLog[$l['id']] ?? []; ?>
                <?php if ($comments): ?>
                  <div class="inline-comments">
                    <?php foreach ($comments as $c):
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
                          <input type="hidden" name="redirect"  value="/logs" />
                          <input type="text" name="body" placeholder="Reply…" maxlength="2000" required />
                          <button class="btn btn-ghost btn-sm">Reply</button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
