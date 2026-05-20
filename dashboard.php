<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$weekNumber = member_week_number($me);
$programLength = 12;
$weekProgress = min(100, (int) round(($weekNumber / $programLength) * 100));

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number ASC, created_at ASC', [$me['id']]);
$dailyLogs   = db_all('SELECT * FROM daily_logs   WHERE lead_id = ? ORDER BY created_at DESC LIMIT 5', [$me['id']]);
$coachNotes  = db_all('SELECT * FROM coach_notes  WHERE lead_id = ? ORDER BY created_at DESC LIMIT 3', [$me['id']]);

function sparkline_svg(array $values, $width = 240, $height = 60, $color = '#16a36a', $fill = '#d6f0e1') {
    $vals = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));
    if (count($vals) < 2) {
        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" class="spark">
          <text x="' . ($width/2) . '" y="' . ($height/2 + 4) . '" text-anchor="middle" fill="#8a8f8b" font-size="11" font-family="Inter, Arial">Not enough data yet</text>
        </svg>';
    }
    $min = min($vals); $max = max($vals);
    if ($max === $min) $max = $min + 1;
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

$latest = end($weeklyNotes) ?: null;
$logsThisWeek = (int) db_get('SELECT COUNT(*) c FROM daily_logs WHERE lead_id = ? AND created_at >= NOW() - INTERVAL 7 DAY', [$me['id']])['c'];

$pageTitle = 'Dashboard — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'overview';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

  <section class="hero-card">
    <div class="hero-text">
      <p class="kicker">Welcome back</p>
      <h1>Hi <?= e($me['first_name'] ?: 'there') ?> — week <span class="accent"><?= (int) $weekNumber ?></span> of your journey.</h1>
      <p class="lede">You're <?= $weekProgress ?>% through your <?= $programLength ?>-week program. Keep showing up — your future self is watching.</p>
      <div class="hero-progress">
        <div class="progress-track"><div class="progress-bar" style="width: <?= $weekProgress ?>%"></div></div>
        <div class="progress-marks"><span>Week 1</span><span>Week <?= $programLength ?></span></div>
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

  <!-- Quick actions -->
  <section class="quick-actions">
    <a href="/checkin" class="qa">
      <span class="qa-icon">🗓️</span>
      <strong>Weekly check-in</strong>
      <small>Tell your coach how the week went</small>
    </a>
    <a href="/logs" class="qa">
      <span class="qa-icon">📓</span>
      <strong>Log today</strong>
      <small><?= $logsThisWeek ?> log<?= $logsThisWeek === 1 ? '' : 's' ?> this week</small>
    </a>
    <a href="/meals" class="qa">
      <span class="qa-icon">🍽️</span>
      <strong>Upload a meal</strong>
      <small>Snap what you ate</small>
    </a>
    <a href="/program" class="qa">
      <span class="qa-icon">📄</span>
      <strong>My program</strong>
      <small><?= !empty($me['program_path']) ? 'Open the PDF' : 'Being built — 24h' ?></small>
    </a>
  </section>

  <!-- Stats -->
  <section class="stats-grid">
    <div class="stat-card">
      <div class="stat-head">
        <span class="stat-label">Avg blood glucose</span>
        <?php $g = $latest['avg_glucose'] ?? null; ?>
        <span class="stat-pill <?= $g && $g < 140 ? 'good' : ($g >= 180 ? 'warn' : '') ?>">
          <?= $g ? (int) $g . ' mg/dL' : '—' ?>
        </span>
      </div>
      <?= sparkline_svg($glucoseSeries, 240, 60, '#16a36a', '#d6f0e1') ?>
      <span class="stat-meta">last <?= count($glucoseSeries) ?: 0 ?> weekly check-ins</span>
    </div>
    <div class="stat-card">
      <div class="stat-head">
        <span class="stat-label">Weight</span>
        <?php $wKg = $latest['weight_kg'] ?? null;
              $wLb = $wKg ? round($wKg * 2.20462, 1) : null; ?>
        <span class="stat-pill"><?= $wLb ? e($wLb) . ' lbs' : '—' ?></span>
      </div>
      <?= sparkline_svg($weightSeries, 240, 60, '#0d7d4f', '#d6f0e1') ?>
      <span class="stat-meta"><?= $wKg ? e($wKg) . ' kg' : 'no entries yet' ?></span>
    </div>
    <div class="stat-card">
      <div class="stat-head">
        <span class="stat-label">Energy</span>
        <span class="stat-pill"><?= isset($latest['energy_rating']) && $latest['energy_rating'] !== null ? (int) $latest['energy_rating'] . ' / 10' : '—' ?></span>
      </div>
      <?= sparkline_svg($energySeries, 240, 60, '#f0a830', '#fff5e5') ?>
      <span class="stat-meta">self-rated 0–10</span>
    </div>
    <div class="stat-card">
      <div class="stat-head">
        <span class="stat-label">Daily check-ins (7d)</span>
        <span class="stat-pill good"><?= (int) $logsThisWeek ?></span>
      </div>
      <div class="stat-big">
        <strong><?= (int) $logsThisWeek ?></strong>
        <span>this week</span>
      </div>
      <span class="stat-meta">log as many as you like, any day</span>
    </div>
  </section>

  <?php if ($coachNotes): ?>
  <section class="card big notes-card">
    <div class="notes-head">
      <div>
        <p class="kicker">Latest from your coach</p>
        <h2>You've got new feedback</h2>
      </div>
      <a href="/program" class="link">See all →</a>
    </div>
    <div class="coach-stream">
      <?php foreach ($coachNotes as $cn): ?>
        <article class="coach-bubble <?= e($cn['kind']) ?>">
          <header>
            <span class="kind-tag <?= e($cn['kind']) ?>"><?= e(ucfirst($cn['kind'])) ?></span>
            <small><?= e(date('M j · g:ia', strtotime($cn['created_at']))) ?><?= $cn['week_number'] ? ' · wk ' . (int)$cn['week_number'] : '' ?></small>
          </header>
          <p><?= nl2br(e($cn['body'])) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($dailyLogs): ?>
  <section class="card big">
    <div class="card-head">
      <div>
        <p class="kicker">Recent activity</p>
        <h2>Your last 5 check-ins</h2>
      </div>
      <a href="/logs" class="link">See all →</a>
    </div>
    <div class="log-list">
      <?php foreach ($dailyLogs as $l): ?>
        <div class="log-entry">
          <h4><?= e($l['log_date']) ?> · <?= e($l['feeling']) ?> · trained: <?= e($l['trained']) ?><?= $l['train_where'] ? ' (' . e($l['train_where']) . ')' : '' ?></h4>
          <small>Glucose <?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?> · Soreness <?= (int)$l['soreness'] ?>/10</small>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <p class="dash-disclaimer">
    DiaFitus suggestions are not medical advice. Always consult your doctor about readings, medications and symptoms.
  </p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
