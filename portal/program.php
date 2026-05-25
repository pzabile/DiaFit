<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me     = require_member();
$leadId = (int)$me['id'];
$prog   = member_program_info($me);

// Load all uploaded programs from member_programs table (graceful fallback)
$programs = [];
try {
    $programs = db_all(
        'SELECT id, week_number, title, file_path, program_targets, created_at
         FROM member_programs WHERE lead_id = ? ORDER BY week_number ASC',
        [$leadId]
    );
} catch (Throwable $ignored) {}

// Fallback: if table doesn't exist yet or empty, use leads.program_path as week 1
if (!$programs && !empty($me['program_path'])) {
    $programs = [[
        'id'              => 0,
        'week_number'     => 1,
        'title'           => null,
        'file_path'       => $me['program_path'],
        'program_targets' => null,
        'created_at'      => $me['started_at'] ?? '',
    ]];
}

$hasProgram = !empty($programs);

// Sessions this week from daily_logs
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));
$weekLogs  = db_all(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date BETWEEN ? AND ? ORDER BY log_date ASC',
    [$leadId, $weekStart, $weekEnd]
);

// Goals from answers_json
$answers = [];
if (!empty($me['answers_json'])) {
    $answers = json_decode($me['answers_json'], true) ?: [];
}

// Which week program to show by default (current week, or latest uploaded)
$currentWeek = $prog['current'];
$activeProgram = null;
foreach ($programs as $p) {
    if ((int)$p['week_number'] === $currentWeek) { $activeProgram = $p; break; }
}
if (!$activeProgram && $programs) {
    // Show the highest week that's <= current, or just the latest
    foreach (array_reverse($programs) as $p) {
        if ((int)$p['week_number'] <= $currentWeek) { $activeProgram = $p; break; }
    }
    if (!$activeProgram) $activeProgram = $programs[0];
}

$pageTitle  = 'My Program — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'program';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">My program</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <a href="/portal/log" class="quick-log">
        <span class="plus"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
        Quick log
      </a>
    </div>
  </header>

  <section class="view">

    <?php if ($hasProgram): ?>

    <!-- Program head card -->
    <div class="program-head">
      <div>
        <div class="eyebrow sage">Your plan &middot; <?= $prog['total'] ?> weeks</div>
        <h1 class="h1">A program built around <em>your</em> glucose and your schedule.</h1>
        <p class="muted" style="max-width:62ch;margin:4px 0 0">
          Tailored for you after your intake assessment.
          <?= $me['started_at'] ? 'Started ' . date('M j', strtotime($me['started_at'])) . '.' : '' ?>
          We adjust every week based on your weekly review.
        </p>
        <div class="progress-bar"><div class="fill" style="width:<?= $prog['pct'] ?>%"></div></div>
        <div style="display:flex;justify-content:space-between;color:var(--muted);font-size:11.5px;margin-top:6px">
          <span>Week <?= $prog['current'] ?> <?= $me['started_at'] ? '· started ' . date('M j', strtotime($me['started_at'])) : '' ?></span>
          <span>Week <?= $prog['total'] ?><?= $me['started_at'] ? ' · ends ' . date('M j', strtotime($me['started_at'] . ' +' . ($me['plan_days'] ?? 84) . ' days')) : '' ?></span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
        <span class="chip sage">Active</span>
        <div class="kpi-num" style="font-size:64px;color:var(--sage-2)"><?= $prog['current'] ?><span style="font-family:'Plus Jakarta Sans';font-size:14px;color:var(--muted);margin-left:4px">/ <?= $prog['total'] ?></span></div>
      </div>
    </div>

    <!-- Weeks rail — clickable -->
    <div class="weeks-rail" id="weeksRail">
      <?php
      $uploadedWeeks = array_flip(array_column($programs, 'week_number'));
      for ($w = 1; $w <= $prog['total']; $w++):
        $hasPdf    = isset($uploadedWeeks[$w]);
        $isCurrent = $w === $prog['current'];
        $isDone    = $w < $prog['current'];
        $isActive  = $activeProgram && (int)$activeProgram['week_number'] === $w;
        $cls = $isDone ? ' done' : ($isCurrent ? ' cur' : '');
        if ($isActive) $cls .= ' selected';
      ?>
        <div class="week-pip<?= $cls ?>"
             style="<?= $hasPdf ? 'cursor:pointer;position:relative' : 'opacity:.45' ?>"
             <?= $hasPdf ? 'onclick="showWeek(' . $w . ')" title="View Week ' . $w . ' program"' : 'title="Week ' . $w . ' — not uploaded yet"' ?>><?= $w ?>
          <?php if ($hasPdf): ?><span style="position:absolute;top:-3px;right:-3px;width:6px;height:6px;border-radius:50%;background:var(--sage);border:1.5px solid var(--bg)"></span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <p class="muted" style="font-size:11.5px;margin:6px 0 20px">Weeks with a green dot have a PDF uploaded. Click to view.</p>

    <!-- Program PDFs (one card per uploaded week) -->
    <?php foreach ($programs as $p):
      $wNum  = (int)$p['week_number'];
      $title = $p['title'] ?: 'Week ' . $wNum . ' program';
      $isAct = $activeProgram && (int)$activeProgram['week_number'] === $wNum;
    ?>
    <div class="card program-week-card" id="week-card-<?= $wNum ?>" style="margin-top:18px;<?= $isAct ? '' : 'display:none' ?>">
      <div class="head">
        <div>
          <div class="eyebrow">Week <?= $wNum ?> · plan document</div>
          <h3 class="h3" style="margin-top:4px"><?= e($title) ?></h3>
        </div>
        <a href="<?= e($p['file_path']) ?>" target="_blank" class="btn sm">Open PDF →</a>
      </div>
      <div class="body" style="padding:0">
        <iframe src="<?= e($p['file_path']) ?>#view=FitH" width="100%" height="680px" style="border-radius:0 0 var(--r-lg) var(--r-lg);border:0;display:block" title="Week <?= $wNum ?> program PDF"></iframe>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Sessions + Goals -->
    <div class="program-cols" style="margin-top:24px">
      <div class="card">
        <div class="head">
          <div>
            <div class="eyebrow">Week <?= $prog['current'] ?> &middot; sessions</div>
            <h3 class="h3" style="margin-top:4px">This week's activity</h3>
          </div>
          <?php $trainedLogs = array_filter($weekLogs, fn($wl) => $wl['trained'] === 'Yes'); ?>
          <span class="chip"><?= count($trainedLogs) ?> trained</span>
        </div>
        <div class="body">
          <?php if ($trainedLogs): ?>
            <?php foreach ($trainedLogs as $wl): ?>
              <a href="/portal/log?date=<?= urlencode($wl['log_date']) ?>" style="text-decoration:none;color:inherit;display:block">
              <div class="session done" style="cursor:pointer">
                <div class="session-day">
                  <div class="d"><?= strtoupper(date('D', strtotime($wl['log_date']))) ?></div>
                  <div class="n"><?= date('j', strtotime($wl['log_date'])) ?></div>
                </div>
                <div>
                  <div class="ttl"><?= e($wl['workout'] ? substr($wl['workout'],0,60).(strlen($wl['workout'])>60?'…':'') : 'Training session') ?></div>
                  <div class="meta">
                    <?php if ($wl['feeling']): ?><span>Feeling: <?= e($wl['feeling']) ?></span><?php endif; ?>
                    <?php if ($wl['bs_before'] && $wl['bs_after']): ?>
                      <span>Glucose &Delta; <?= (($wl['bs_after']-$wl['bs_before']) >= 0 ? '+' : '').($wl['bs_after']-$wl['bs_before']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div><span class="chip sage">✓ Done</span></div>
              </div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center;padding:20px;color:var(--muted)">No training sessions logged this week yet. <a href="/portal/log" style="color:var(--sage-2);font-weight:600">Log today →</a></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow">Week <?= $prog['current'] ?> &middot; targets</div>
              <h3 class="h3" style="margin-top:4px">What we're aiming for</h3>
            </div>
          </div>
          <div class="body">
            <?php $weekTargets = $activeProgram['program_targets'] ?? null; ?>
            <?php if ($weekTargets): ?>
              <div style="white-space:pre-wrap;font-size:14px;line-height:1.6;color:var(--ink)"><?= e($weekTargets) ?></div>
              <?php if (!empty($answers['primary_goal'])): ?>
              <div class="goal-row" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
                <div><div class="lbl">Primary goal</div></div>
                <div style="text-align:right"><div class="val" style="font-size:14px"><?= e($answers['primary_goal']) ?></div></div>
              </div>
              <?php endif; ?>
            <?php else: // $weekTargets is null ?>
            <div class="goal-row">
              <div><div class="lbl">Time in range (70–180)</div><div class="meta">Pre/post sessions</div></div>
              <div style="text-align:right"><div class="val">&ge; 80%</div></div>
            </div>
            <div class="goal-row">
              <div><div class="lbl">Sessions completed</div><div class="meta">5 planned this week</div></div>
              <div style="text-align:right"><div class="val">5</div></div>
            </div>
            <div class="goal-row">
              <div><div class="lbl">Daily check-ins</div><div class="meta">Evening preferred</div></div>
              <div style="text-align:right"><div class="val">7</div></div>
            </div>
            <?php if (!empty($answers['primary_goal'])): ?>
            <div class="goal-row">
              <div><div class="lbl">Primary goal</div></div>
              <div style="text-align:right"><div class="val" style="font-size:14px"><?= e($answers['primary_goal']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Questions → Chat -->
        <div class="card" style="background:linear-gradient(135deg,var(--sage-tint),var(--bg));border:1px solid var(--sage-tint-2,#d8e6d9)">
          <div class="body" style="padding:20px 22px">
            <div class="eyebrow sage" style="margin-bottom:8px">Got questions?</div>
            <h3 class="h3" style="margin:0 0 8px">Talk to your coach.</h3>
            <p class="muted" style="font-size:13px;margin:0 0 16px;max-width:36ch">Questions about your program, workouts, glucose, or anything else — your coach is waiting.</p>
            <a href="/portal/coach" class="btn pri" style="display:inline-flex;align-items:center;gap:8px">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              Open Chat →
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- No program yet -->
    <div style="text-align:center;padding:80px 40px">
      <div style="font-size:64px;margin-bottom:20px">📋</div>
      <h1 class="h1">Your program is <em>being built.</em></h1>
      <p style="color:var(--muted);max-width:48ch;margin:12px auto 24px;font-size:15px">
        Your coach is personalizing your plan based on your intake assessment. You'll receive a notification when it's ready — usually within 24 hours.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="/portal/coach" class="btn pri">Message your coach</a>
        <a href="/portal/today" class="btn">Go to dashboard</a>
      </div>
    </div>
    <?php endif; ?>

  </section>
</main>
</div>

<script>
const programsByWeek = <?= json_encode(array_column($programs, null, 'week_number')) ?>;

function showWeek(week) {
  // Hide all week cards
  document.querySelectorAll('.program-week-card').forEach(function(c){ c.style.display='none'; });
  // Show requested week
  var card = document.getElementById('week-card-' + week);
  if (card) { card.style.display=''; card.scrollIntoView({behavior:'smooth', block:'start'}); }
  // Update rail active state
  document.querySelectorAll('.week-pip').forEach(function(p){ p.classList.remove('selected'); });
  var pip = document.querySelectorAll('.week-pip')[week - 1];
  if (pip) pip.classList.add('selected');
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
