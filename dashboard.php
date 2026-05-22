<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$prog          = member_program_info($me);
$weekNumber    = $prog['current'];
$programLength = $prog['total'];
$unitLabel     = $prog['label'];
$weekProgress  = $prog['pct'];

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number ASC, created_at ASC', [$me['id']]);
$dailyLogs   = db_all('SELECT * FROM daily_logs   WHERE lead_id = ? ORDER BY created_at DESC LIMIT 5', [$me['id']]);
$coachNotes  = db_all('SELECT * FROM coach_notes  WHERE lead_id = ? AND is_private = 0 AND from_member = 0 AND parent_id IS NULL ORDER BY created_at DESC LIMIT 3', [$me['id']]);

$latest      = end($weeklyNotes) ?: null;
$logsThisWeek = (int) db_get('SELECT COUNT(*) c FROM daily_logs WHERE lead_id = ? AND created_at >= NOW() - INTERVAL 7 DAY', [$me['id']])['c'];
$hasProgram  = !empty($me['program_path']);
$latestCoach = db_get('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND from_member = 0 ORDER BY created_at DESC LIMIT 1', [$me['id']]);

$pageTitle = 'Dashboard — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'overview';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?>
      <div class="alert success"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Dark hero card -->
    <section class="hero">
      <p class="eyebrow"><?= $unitLabel === 'week' ? 'Week' : ucfirst($unitLabel) ?> <?= (int) $weekNumber ?> of <?= (int) $programLength ?></p>
      <h1>Hi <?= e($me['first_name'] ?: 'there') ?>, <em>keep going.</em></h1>
      <p>You're <?= $weekProgress ?>% through your <?= $programLength ?>-<?= $unitLabel ?> program.</p>
      <div class="hero-row" style="margin-top:18px">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php $g = $latest['avg_glucose'] ?? null; if ($g): ?>
            <span style="background:rgba(255,255,255,.12);color:rgba(230,239,230,.9);border-radius:99px;padding:5px 12px;font-size:12.5px;font-weight:500">Glucose <?= (int)$g ?> mg/dL</span>
          <?php endif; ?>
          <span style="background:rgba(255,255,255,.12);color:rgba(230,239,230,.9);border-radius:99px;padding:5px 12px;font-size:12.5px;font-weight:500"><?= $logsThisWeek ?> log<?= $logsThisWeek !== 1 ? 's' : '' ?> this week</span>
          <?php if ($hasProgram): ?>
            <span style="background:rgba(74,138,104,.4);color:#9CC9A8;border-radius:99px;padding:5px 12px;font-size:12.5px;font-weight:500">Program ready</span>
          <?php endif; ?>
        </div>
        <div class="ring" style="justify-content:flex-start">
          <div class="ring-vis">
            <svg viewBox="0 0 130 130" xmlns="http://www.w3.org/2000/svg">
              <circle cx="65" cy="65" r="55" stroke="rgba(255,255,255,.15)" stroke-width="10" fill="none"/>
              <circle cx="65" cy="65" r="55" stroke="#9CC9A8" stroke-width="10" fill="none"
                      stroke-dasharray="<?= round(345.6 * ($weekProgress / 100), 1) ?> 345.6"
                      stroke-linecap="round" transform="rotate(-90 65 65)"/>
            </svg>
            <div class="label">
              <span class="num"><?= (int) $weekNumber ?></span>
              <span class="sub">of <?= (int) $programLength ?></span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- KPI strip -->
    <div class="kpi-grid" style="margin-bottom:28px">
      <div class="kpi-card">
        <div class="kpi-label">Avg glucose</div>
        <?php $g = $latest['avg_glucose'] ?? null; ?>
        <div class="kpi-val"><?= $g ? (int)$g : '—' ?><span class="kpi-unit"><?= $g ? 'mg/dL' : '' ?></span></div>
        <div class="kpi-sub">Last check-in</div>
      </div>
      <?php $wKg = $latest['weight_kg'] ?? null; $wLb = $wKg ? round($wKg * 2.20462, 1) : null; ?>
      <div class="kpi-card">
        <div class="kpi-label">Weight</div>
        <div class="kpi-val"><?= $wLb ?: '—' ?><span class="kpi-unit"><?= $wLb ? 'lbs' : '' ?></span></div>
        <div class="kpi-sub"><?= $wKg ? e($wKg) . ' kg' : 'Not recorded' ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Energy</div>
        <div class="kpi-val"><?= isset($latest['energy_rating']) && $latest['energy_rating'] !== null ? (int)$latest['energy_rating'] : '—' ?><span class="kpi-unit"><?= isset($latest['energy_rating']) && $latest['energy_rating'] !== null ? '/10' : '' ?></span></div>
        <div class="kpi-sub">Self-rated</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Daily logs (7d)</div>
        <div class="kpi-val"><?= $logsThisWeek ?></div>
        <div class="kpi-sub">This week</div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="plan-list" style="margin-bottom:28px">
      <a href="/checkin" class="plan-item">
        <div class="plan-check">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div>
          <div class="what">Weekly check-in</div>
          <div class="meta">Tell your coach how the week went</div>
        </div>
      </a>
      <a href="/logs" class="plan-item">
        <div class="plan-check">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4z"/></svg>
        </div>
        <div>
          <div class="what">Log today</div>
          <div class="meta"><?= $logsThisWeek ?> log<?= $logsThisWeek !== 1 ? 's' : '' ?> this week · add one more</div>
        </div>
      </a>
      <a href="/meals" class="plan-item">
        <div class="plan-check">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/></svg>
        </div>
        <div>
          <div class="what">Upload a meal</div>
          <div class="meta">Snap what you ate</div>
        </div>
      </a>
      <a href="/program" class="plan-item">
        <div class="plan-check">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/></svg>
        </div>
        <div>
          <div class="what">My program</div>
          <div class="meta"><?= $hasProgram ? 'Open your PDF plan' : 'Being built — 24h' ?></div>
        </div>
      </a>
    </div>

    <!-- Coach message -->
    <?php if ($latestCoach): ?>
    <div class="card" style="margin-bottom:28px;padding:20px 22px">
      <div class="coach-card">
        <div class="who">
          <div class="av">C</div>
          <div>
            <div class="name">Your coach</div>
            <div class="role">DiaFitus</div>
          </div>
          <a href="/program" style="margin-left:auto;font-size:12.5px;color:var(--sage-2);font-weight:600">See all →</a>
        </div>
        <p class="msg"><?= nl2br(e(substr($latestCoach['body'], 0, 280))) ?><?= strlen($latestCoach['body']) > 280 ? '…' : '' ?></p>
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)"><?= e(date('M j, Y · g:ia', strtotime($latestCoach['created_at']))) ?></div>
    </div>
    <?php endif; ?>

    <!-- Recent daily logs -->
    <?php if ($dailyLogs): ?>
    <div style="margin-bottom:28px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="eyebrow">Recent check-ins</div>
        <a href="/logs" style="font-size:12.5px;color:var(--sage-2);font-weight:600">See all →</a>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($dailyLogs as $l): ?>
          <div style="background:var(--card);border-radius:var(--r);padding:14px 16px;border:1px solid var(--line);display:flex;align-items:center;gap:14px">
            <div style="min-width:0;flex:1">
              <div style="font-weight:600;font-size:13.5px"><?= e(date('M j', strtotime($l['log_date']))) ?> — <?= e($l['feeling']) ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px">
                Glucose <?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?> · Soreness <?= (int)$l['soreness'] ?>/10<?= $l['trained'] === 'Yes' ? ' · trained' : '' ?>
              </div>
            </div>
            <a href="/logs" style="font-size:12px;color:var(--muted);flex-shrink:0">→</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <p style="font-size:11.5px;color:var(--muted);border-top:1px solid var(--line);padding-top:16px">
      DiaFitus suggestions are not medical advice. Always consult your doctor about readings, medications and symptoms.
    </p>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
