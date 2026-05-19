<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$weekNumber = member_week_number($me);

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$me['id']]);
$dailyLogs   = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC LIMIT 30', [$me['id']]);
$mealPhotos  = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 24', [$me['id']]);
$coachNotes  = db_all('SELECT * FROM coach_notes WHERE lead_id = ? ORDER BY created_at DESC LIMIT 20', [$me['id']]);

$pageTitle = 'My Dashboard — DiaFitus';
$bodyClass = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
  <aside class="side">
    <div class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
      <span class="sub-tag">member portal</span>
    </div>
    <nav class="side-nav">
      <a class="active" href="#overview">📍 Overview</a>
      <a href="#program">🏋️ My program</a>
      <a href="#week">🗓️ This week</a>
      <a href="#meals">🍽️ Meal photos</a>
      <a href="#daily">📓 Daily logs</a>
      <a href="#coach">💬 From my coach</a>
      <a href="account">⚙️ Account</a>
      <a href="logout">↩️ Log out</a>
    </nav>
    <div class="side-foot">
      <div class="avatar"><?= e(strtoupper(substr($me['first_name'] ?? 'M', 0, 1))) ?></div>
      <div>
        <strong><?= e($me['first_name'] ?? 'Member') ?></strong>
        <small><?= e($me['email']) ?></small>
      </div>
    </div>
  </aside>

  <main class="dash-main">
    <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

    <header class="dash-header" id="overview">
      <div>
        <p class="kicker">Welcome back</p>
        <h1>Hi <?= e($me['first_name'] ?: 'there') ?> — week <?= (int) $weekNumber ?> of your journey.</h1>
      </div>
      <div class="dash-meta">
        <div><strong><?= count($dailyLogs) ?></strong><span>logs (last 30)</span></div>
        <div><strong><?= count($weeklyNotes) ?></strong><span>weekly notes</span></div>
        <div><strong><?= count($mealPhotos) ?></strong><span>meals shared</span></div>
      </div>
    </header>

    <section class="card-row">
      <div class="metric-card">
        <span class="metric-label">My program</span>
        <?php if (!empty($me['program_path'])): ?>
          <strong class="metric-value">Ready to view</strong>
          <a href="<?= e($me['program_path']) ?>" target="_blank" class="link">Open PDF →</a>
        <?php else: ?>
          <strong class="metric-value">Being built (24h)</strong>
          <span class="link muted">You'll be notified when ready</span>
        <?php endif; ?>
      </div>
      <div class="metric-card">
        <span class="metric-label">Support</span>
        <strong class="metric-value">24/7 — message us</strong>
        <a href="<?= e(cfg('telegram.member_link')) ?>" target="_blank" class="link">Open chat →</a>
      </div>
      <div class="metric-card">
        <span class="metric-label">Current week</span>
        <strong class="metric-value">Week <?= (int) $weekNumber ?></strong>
        <span class="link muted">Started <?= e($me['started_at'] ?: '—') ?></span>
      </div>
    </section>

    <section class="card big" id="program">
      <h2>My program</h2>
      <?php if (!empty($me['program_path'])): ?>
        <p class="muted">Your coach has uploaded a personalized plan. Open it any time and refer back as you train.</p>
        <a href="<?= e($me['program_path']) ?>" target="_blank" class="btn btn-primary">📄 Open my program (PDF)</a>
      <?php else: ?>
        <p class="muted">Our team is building your personalized program based on your assessment. It usually appears here within 24 hours of payment. We'll send you a message the moment it's ready.</p>
      <?php endif; ?>

      <?php if ($coachNotes): ?>
        <h3 style="margin-top:1.5rem">Notes from your coach</h3>
        <div class="coach-stream">
          <?php foreach ($coachNotes as $cn): ?>
            <div class="coach-bubble <?= e($cn['kind']) ?>">
              <small><?= e($cn['created_at']) ?><?= $cn['week_number'] ? ' · week ' . (int)$cn['week_number'] : '' ?></small>
              <p><?= nl2br(e($cn['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card big" id="week">
      <h2>Week <?= (int) $weekNumber ?> — how are you doing?</h2>
      <p class="muted">Leave a weekly check-in so your coach can adjust your program. Every week tells a story.</p>
      <form method="post" action="save_weekly" class="form" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <input type="hidden" name="week_number" value="<?= (int) $weekNumber ?>" />
        <div class="grid-3">
          <label>Avg blood glucose (mg/dL)<input type="number" name="avg_glucose" min="40" max="500" placeholder="e.g. 118" /></label>
          <label>Weight (kg)<input type="number" step="0.1" name="weight_kg" min="30" max="300" placeholder="e.g. 84.2" /></label>
          <label>Energy this week (0–10)<input type="number" name="energy_rating" min="0" max="10" placeholder="7" /></label>
        </div>
        <label>Wins this week
          <textarea name="wins" placeholder="What went well? Workouts you completed, hypos avoided, meals on track…"></textarea>
        </label>
        <label>Struggles
          <textarea name="struggles" placeholder="What was hard? Sugar spikes, soreness, time, motivation…"></textarea>
        </label>
        <label>Everything else
          <textarea name="content" placeholder="Anything you want your coach to know — symptoms, medication changes, life events."></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-lg">Save week <?= (int) $weekNumber ?> check-in</button>
      </form>

      <?php if ($weeklyNotes): ?>
        <h3 style="margin-top:2rem">Past weeks</h3>
        <?php foreach ($weeklyNotes as $w): ?>
          <details class="week-card">
            <summary>Week <?= (int) $w['week_number'] ?> — <?= e(substr($w['created_at'], 0, 10)) ?>
              <?php if ($w['avg_glucose']): ?><span class="chip">glucose <?= (int)$w['avg_glucose'] ?></span><?php endif; ?>
              <?php if ($w['weight_kg']):   ?><span class="chip"><?= e($w['weight_kg']) ?> kg</span><?php endif; ?>
              <?php if ($w['energy_rating'] !== null): ?><span class="chip">energy <?= (int)$w['energy_rating'] ?>/10</span><?php endif; ?>
            </summary>
            <?php if ($w['wins']):      ?><p><strong>Wins:</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
            <?php if ($w['struggles']): ?><p><strong>Struggles:</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
            <?php if ($w['content']):   ?><p><?= nl2br(e($w['content'])) ?></p><?php endif; ?>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card big" id="meals">
      <h2>Meal photos</h2>
      <p class="muted">Snap what you eat. Your coach uses these to calibrate your nutrition plan.</p>
      <form method="post" action="save_meal" class="form inline-form" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="grid-3">
          <label>Photo<input type="file" name="photo" accept="image/*" required /></label>
          <label>Meal
            <select name="meal_type">
              <option>Breakfast</option><option>Lunch</option><option>Dinner</option>
              <option>Snack</option><option>Pre-workout</option><option>Post-workout</option>
            </select>
          </label>
          <label>What was it?<input type="text" name="caption" maxlength="500" placeholder="e.g. oats + berries" /></label>
        </div>
        <button type="submit" class="btn btn-primary">Upload meal</button>
      </form>

      <?php if ($mealPhotos): ?>
        <div class="meal-grid">
          <?php foreach ($mealPhotos as $m): ?>
            <figure class="meal-tile">
              <img src="<?= e($m['file_path']) ?>" alt="" loading="lazy" />
              <figcaption>
                <strong><?= e($m['meal_type'] ?: 'Meal') ?></strong>
                <span><?= e($m['caption']) ?></span>
                <small><?= e(substr($m['created_at'], 0, 16)) ?></small>
              </figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card big" id="daily">
      <h2>Daily log</h2>
      <p class="muted">Track everything in one place — your coach reviews it weekly.</p>
      <form method="post" action="save_log" class="log-form">
        <?= csrf_input() ?>
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
            <label>Did you train today?
              <select name="trained"><option>Yes</option><option>No</option><option>Partial</option></select>
            </label>
            <label>Where?
              <select name="train_where"><option>Gym</option><option>Home</option><option>Outdoors</option></select>
            </label>
          </div>
          <label>What did you do?<textarea name="workout" placeholder="e.g. Squats 4x8 @ 60kg, lat pulldown 3x10, 15m incline walk"></textarea></label>
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
        <button type="submit" class="btn btn-primary btn-lg">Save today's log</button>
      </form>

      <?php if ($dailyLogs): ?>
        <div class="log-list">
          <?php foreach ($dailyLogs as $l): ?>
            <div class="log-entry">
              <h4><?= e($l['log_date']) ?> — felt <?= e($l['feeling']) ?>, trained: <?= e($l['trained']) ?><?= $l['train_where'] ? ' (' . e($l['train_where']) . ')' : '' ?></h4>
              <small>Glucose <?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?> (<?= e($l['bs_trend'] ?: '—') ?>). Soreness <?= (int)$l['soreness'] ?>/10.</small>
              <?php if ($l['workout']): ?><p style="margin:.4rem 0 0"><?= nl2br(e($l['workout'])) ?></p><?php endif; ?>
              <?php if ($l['notes']):   ?><p class="muted" style="margin:.25rem 0 0;font-size:.9rem"><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <p class="muted dash-disclaimer">
      Reminder: DiaFitus suggestions are not medical advice. Always consult your doctor about your readings, medications and any symptoms.
    </p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
