<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$weekNumber = member_week_number($me);
$programLength = 12; // 12-week default cycle for the progress ring.
$weekProgress = min(100, (int) round(($weekNumber / $programLength) * 100));

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number ASC, created_at ASC', [$me['id']]);
$weeklyDesc  = array_reverse($weeklyNotes);
$dailyLogs   = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC LIMIT 30', [$me['id']]);
$mealPhotos  = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 24', [$me['id']]);
$coachNotes  = db_all('SELECT * FROM coach_notes WHERE lead_id = ? ORDER BY created_at DESC LIMIT 10', [$me['id']]);

// ---------- Build little sparkline data points ----------
function sparkline_svg(array $values, $width = 220, $height = 56, $color = '#16a36a', $fill = '#d6f0e1') {
    $vals = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));
    if (count($vals) < 2) {
        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" class="spark">
          <text x="' . ($width/2) . '" y="' . ($height/2 + 4) . '" text-anchor="middle" fill="#8a8f8b" font-size="11" font-family="Inter, Arial">Not enough data yet</text>
        </svg>';
    }
    $min = min($vals); $max = max($vals);
    if ($max === $min) { $max = $min + 1; }
    $step = $width / max(1, (count($vals) - 1));
    $points = []; $pathD = '';
    foreach ($vals as $i => $v) {
        $x = $i * $step;
        $y = $height - 6 - (($v - $min) / ($max - $min)) * ($height - 12);
        $points[] = [$x, $y];
        $pathD .= ($i === 0 ? 'M' : ' L') . sprintf('%.1f %.1f', $x, $y);
    }
    $area = 'M0 ' . $height . ' ';
    foreach ($points as $p) $area .= 'L' . sprintf('%.1f %.1f', $p[0], $p[1]) . ' ';
    $area .= 'L' . $width . ' ' . $height . ' Z';
    $last = end($points);
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" class="spark">'
        . '<path d="' . $area . '" fill="' . $fill . '" opacity=".55"/>'
        . '<path d="' . $pathD . '" fill="none" stroke="' . $color . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="3.5" fill="' . $color . '"/>'
        . '</svg>';
}

$glucoseSeries = array_map(fn($w) => (int)$w['avg_glucose'],   $weeklyNotes);
$weightSeries  = array_map(fn($w) => (float)$w['weight_kg'],   $weeklyNotes);
$energySeries  = array_map(fn($w) => (int)$w['energy_rating'], $weeklyNotes);

// 7-day workout completion
$logsThisWeek = 0; $logsLastWeek = 0;
foreach ($dailyLogs as $l) {
    $d = strtotime($l['log_date']);
    if ($d >= strtotime('-7 days')) $logsThisWeek++;
    elseif ($d >= strtotime('-14 days')) $logsLastWeek++;
}
$workoutDelta = $logsThisWeek - $logsLastWeek;

$latestWeekly = $weeklyDesc[0] ?? null;
$latestGlucose = $latestWeekly['avg_glucose'] ?? null;
$latestWeight  = $latestWeekly['weight_kg']   ?? null;
$latestEnergy  = $latestWeekly['energy_rating'] ?? null;

$pageTitle = 'My Dashboard — DiaFitus';
$bodyClass = 'dashboard premium';
require __DIR__ . '/includes/header.php';
?>
  <aside class="side">
    <a class="brand" href="/dashboard">
      <span class="logo-dot"></span>
      <div>
        <span class="brand-name">DiaFitus</span>
        <span class="sub-tag">member portal</span>
      </div>
    </a>
    <nav class="side-nav">
      <a class="active" href="#overview"><span>📍</span>Overview</a>
      <a href="#program"><span>🏋️</span>My program</a>
      <a href="#weekly"><span>🗓️</span>This week</a>
      <a href="#meals"><span>🍽️</span>Meal photos</a>
      <a href="#daily"><span>📓</span>Daily logs</a>
      <a href="#timeline"><span>📈</span>Progress</a>
      <a href="<?= e(cfg('telegram.member_link')) ?>" target="_blank"><span>💬</span>Chat with us</a>
      <a href="/account"><span>⚙️</span>Account</a>
    </nav>
    <div class="side-foot">
      <div class="side-user">
        <div class="avatar"><?= e(strtoupper(substr($me['first_name'] ?? 'M', 0, 1))) ?></div>
        <div>
          <strong><?= e($me['first_name'] ?? 'Member') ?></strong>
          <small><?= e($me['email']) ?></small>
        </div>
      </div>
      <a class="logout-link" href="/logout">Log out →</a>
    </div>
  </aside>

  <main class="dash-main">
    <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

    <!-- HERO -->
    <section class="hero-card" id="overview">
      <div class="hero-text">
        <p class="kicker">Welcome back</p>
        <h1>Hi <?= e($me['first_name'] ?: 'there') ?> — welcome to <span class="accent">week <?= (int) $weekNumber ?></span>.</h1>
        <p class="lede">You're <?= $weekProgress ?>% through your <?= $programLength ?>-week journey. Keep going — the next win is closer than you think.</p>
        <div class="hero-progress" role="progressbar" aria-valuenow="<?= $weekProgress ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-track"><div class="progress-bar" style="width: <?= $weekProgress ?>%"></div></div>
          <div class="progress-marks">
            <span>Week 1</span><span>Week <?= $programLength ?></span>
          </div>
        </div>
      </div>
      <div class="hero-ring">
        <svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
          <circle cx="60" cy="60" r="50" stroke="rgba(255,255,255,.18)" stroke-width="10" fill="none"/>
          <circle cx="60" cy="60" r="50" stroke="#fff" stroke-width="10" fill="none"
                  stroke-dasharray="<?= 314 * ($weekProgress / 100) ?> 314"
                  stroke-linecap="round" transform="rotate(-90 60 60)"/>
        </svg>
        <div class="ring-label">
          <strong><?= (int) $weekNumber ?></strong>
          <small>of <?= $programLength ?></small>
        </div>
      </div>
    </section>

    <!-- STATS -->
    <section class="stats-grid">
      <div class="stat-card">
        <div class="stat-head">
          <span class="stat-label">Avg blood glucose</span>
          <span class="stat-pill <?= $latestGlucose && $latestGlucose < 140 ? 'good' : ($latestGlucose >= 180 ? 'warn' : '') ?>">
            <?= $latestGlucose ? (int) $latestGlucose . ' mg/dL' : '—' ?>
          </span>
        </div>
        <?= sparkline_svg($glucoseSeries, 240, 60, '#16a36a', '#d6f0e1') ?>
        <span class="stat-meta">last <?= count($glucoseSeries) ?: 0 ?> weekly check-ins</span>
      </div>
      <div class="stat-card">
        <div class="stat-head">
          <span class="stat-label">Weight</span>
          <span class="stat-pill"><?= $latestWeight ? e($latestWeight) . ' kg' : '—' ?></span>
        </div>
        <?= sparkline_svg($weightSeries, 240, 60, '#0d7d4f', '#d6f0e1') ?>
        <span class="stat-meta">last <?= count($weightSeries) ?: 0 ?> weekly check-ins</span>
      </div>
      <div class="stat-card">
        <div class="stat-head">
          <span class="stat-label">Energy</span>
          <span class="stat-pill"><?= $latestEnergy !== null ? (int) $latestEnergy . ' / 10' : '—' ?></span>
        </div>
        <?= sparkline_svg($energySeries, 240, 60, '#f0a830', '#fff5e5') ?>
        <span class="stat-meta">self-rated 0–10</span>
      </div>
      <div class="stat-card">
        <div class="stat-head">
          <span class="stat-label">Workouts this week</span>
          <span class="stat-pill good"><?= (int) $logsThisWeek ?></span>
        </div>
        <div class="stat-big">
          <strong><?= (int) $logsThisWeek ?></strong>
          <span><?= $workoutDelta >= 0 ? '+' : '' ?><?= (int) $workoutDelta ?> vs last week</span>
        </div>
        <span class="stat-meta">based on your daily logs</span>
      </div>
    </section>

    <!-- COACH NOTES — prominent -->
    <?php if ($coachNotes): ?>
    <section class="card big notes-card" id="coach">
      <div class="notes-head">
        <div>
          <p class="kicker">Latest from your coach</p>
          <h2>Notes &amp; feedback</h2>
        </div>
        <span class="chip green"><?= count($coachNotes) ?> note<?= count($coachNotes) === 1 ? '' : 's' ?></span>
      </div>
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
    </section>
    <?php endif; ?>

    <!-- PROGRAM -->
    <section class="card big" id="program">
      <div class="card-head">
        <div>
          <p class="kicker">Your program</p>
          <h2>Personalized plan</h2>
        </div>
        <?php if (!empty($me['program_path'])): ?>
          <a href="<?= e($me['program_path']) ?>" target="_blank" class="btn btn-primary">📄 Open PDF</a>
        <?php endif; ?>
      </div>
      <?php if (!empty($me['program_path'])): ?>
        <p class="muted">Your coach has uploaded a personalized plan. Open it anytime and refer back as you train.</p>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">⏳</div>
          <strong>Your program is being built.</strong>
          <p>Our team is reviewing your assessment and tailoring a 12-week plan to your diabetes type, schedule and equipment. It usually appears here within 24 hours of payment. We'll message you the moment it's ready.</p>
        </div>
      <?php endif; ?>
    </section>

    <!-- WEEKLY CHECK-IN -->
    <section class="card big" id="weekly">
      <div class="card-head">
        <div>
          <p class="kicker">Week <?= (int) $weekNumber ?> check-in</p>
          <h2>How are you doing?</h2>
        </div>
        <span class="chip">~2 min</span>
      </div>
      <p class="muted">Leave a check-in so your coach can adjust your program. Every week tells a story.</p>
      <form method="post" action="/save_weekly" class="form premium-form" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <input type="hidden" name="week_number" value="<?= (int) $weekNumber ?>" />
        <div class="grid-3">
          <label>Avg blood glucose (mg/dL)<input type="number" name="avg_glucose" min="40" max="500" placeholder="e.g. 118" /></label>
          <label>Weight<input type="number" step="0.1" name="weight_kg" min="30" max="300" placeholder="e.g. 84.2 kg" /></label>
          <label>Energy (0–10)<input type="number" name="energy_rating" min="0" max="10" placeholder="e.g. 7" /></label>
        </div>
        <label>Wins this week
          <textarea name="wins" placeholder="What went well? Workouts you completed, hypos avoided, meals on track…"></textarea>
        </label>
        <label>Struggles
          <textarea name="struggles" placeholder="What was hard? Sugar spikes, soreness, time, motivation…"></textarea>
        </label>
        <label>Anything else
          <textarea name="content" placeholder="Anything you want your coach to know — symptoms, medication changes, life events."></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-lg">Save week <?= (int) $weekNumber ?> check-in</button>
      </form>
    </section>

    <!-- TIMELINE -->
    <section class="card big" id="timeline">
      <div class="card-head">
        <div>
          <p class="kicker">Progress</p>
          <h2>Your weekly journey</h2>
        </div>
        <span class="chip"><?= count($weeklyNotes) ?> check-in<?= count($weeklyNotes) === 1 ? '' : 's' ?></span>
      </div>
      <?php if (!$weeklyNotes): ?>
        <div class="empty-state">
          <div class="empty-icon">📈</div>
          <strong>No weekly check-ins yet.</strong>
          <p>Submit your first weekly check-in above and your progress timeline will appear here.</p>
        </div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($weeklyDesc as $w): ?>
            <article class="tl-week">
              <div class="tl-badge">W<?= (int) $w['week_number'] ?></div>
              <div class="tl-body">
                <header>
                  <strong>Week <?= (int) $w['week_number'] ?></strong>
                  <small><?= e(date('M j, Y', strtotime($w['created_at']))) ?></small>
                </header>
                <div class="tl-chips">
                  <?php if ($w['avg_glucose']):   ?><span class="chip">🩸 <?= (int)$w['avg_glucose'] ?> mg/dL</span><?php endif; ?>
                  <?php if ($w['weight_kg']):     ?><span class="chip">⚖️ <?= e($w['weight_kg']) ?> kg</span><?php endif; ?>
                  <?php if ($w['energy_rating'] !== null): ?><span class="chip">⚡ <?= (int)$w['energy_rating'] ?>/10</span><?php endif; ?>
                </div>
                <?php if ($w['wins']):      ?><p><strong>Wins.</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
                <?php if ($w['struggles']): ?><p><strong>Struggles.</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
                <?php if ($w['content']):   ?><p class="muted"><?= nl2br(e($w['content'])) ?></p><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- MEAL PHOTOS -->
    <section class="card big" id="meals">
      <div class="card-head">
        <div>
          <p class="kicker">Nutrition</p>
          <h2>Meal photos</h2>
        </div>
        <span class="chip"><?= count($mealPhotos) ?> shared</span>
      </div>
      <p class="muted">Snap what you eat. Your coach uses these to calibrate your nutrition plan.</p>
      <form method="post" action="/save_meal" class="form inline-form" enctype="multipart/form-data">
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

    <!-- DAILY LOG -->
    <section class="card big" id="daily">
      <div class="card-head">
        <div>
          <p class="kicker">Day by day</p>
          <h2>Daily log</h2>
        </div>
        <span class="chip"><?= count($dailyLogs) ?> recent</span>
      </div>
      <p class="muted">Track everything in one place — your coach reviews it weekly.</p>
      <form method="post" action="/save_log" class="log-form premium-form">
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

    <p class="dash-disclaimer">
      Reminder: DiaFitus suggestions are not medical advice. Always consult your doctor about your readings, medications and any symptoms.
    </p>
  </main>

</body>
</html>
