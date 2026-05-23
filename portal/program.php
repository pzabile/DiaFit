<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$prog       = member_program_info($me);
$hasProgram = !empty($me['program_path']);

// Goals from answers_json
$answers = [];
if (!empty($me['answers_json'])) {
    $answers = json_decode($me['answers_json'], true) ?: [];
}

// Weekly notes for sessions card
$weekNotes = db_all(
    'SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC LIMIT 5',
    [$leadId]
);

// Sessions this week from daily_logs
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));
$weekLogs  = db_all(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date BETWEEN ? AND ? ORDER BY log_date ASC',
    [$leadId, $weekStart, $weekEnd]
);

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
        <a href="/portal/coach" class="btn sm">Message coach</a>
      </div>
    </div>

    <!-- Weeks rail -->
    <div class="weeks-rail">
      <?php for ($w = 1; $w <= $prog['total']; $w++): ?>
        <div class="week-pip<?= $w < $prog['current'] ? ' done' : ($w === $prog['current'] ? ' cur' : '') ?>"><?= $w ?></div>
      <?php endfor; ?>
    </div>

    <!-- Program PDF embed -->
    <div class="card" style="margin-top:18px">
      <div class="head">
        <div>
          <div class="eyebrow">Your plan document</div>
          <h3 class="h3" style="margin-top:4px">Week <?= $prog['current'] ?> program</h3>
        </div>
        <a href="/<?= e($me['program_path']) ?>" target="_blank" class="btn sm">Open PDF →</a>
      </div>
      <div class="body" style="padding:0">
        <embed src="/<?= e($me['program_path']) ?>" type="application/pdf" width="100%" height="700px" style="border-radius:0 0 var(--r-lg) var(--r-lg)" />
      </div>
    </div>

    <!-- Sessions + Goals cols -->
    <div class="program-cols">
      <!-- Sessions this week -->
      <div class="card">
        <div class="head">
          <div>
            <div class="eyebrow">Week <?= $prog['current'] ?> &middot; sessions</div>
            <h3 class="h3" style="margin-top:4px">This week's activity</h3>
          </div>
          <span class="chip"><?= count($weekLogs) ?> logged</span>
        </div>
        <div class="body">
          <?php if ($weekLogs): ?>
            <?php foreach ($weekLogs as $wl): ?>
              <div class="session<?= $wl['trained'] === 'Yes' ? ' done' : '' ?>">
                <div class="session-day">
                  <div class="d"><?= strtoupper(date('D', strtotime($wl['log_date']))) ?></div>
                  <div class="n"><?= date('j', strtotime($wl['log_date'])) ?></div>
                </div>
                <div>
                  <div class="ttl"><?= e($wl['workout'] ? substr($wl['workout'],0,60) . (strlen($wl['workout'])>60?'…':'') : ($wl['trained']==='Yes' ? 'Training session' : 'Rest day')) ?></div>
                  <div class="meta">
                    <?php if ($wl['feeling']): ?><span>Feeling: <?= e($wl['feeling']) ?></span><?php endif; ?>
                    <?php if ($wl['bs_before'] && $wl['bs_after']): ?>
                      <span>Glucose &Delta; <?= (($wl['bs_after']-$wl['bs_before']) >= 0 ? '+' : '') . ($wl['bs_after']-$wl['bs_before']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div><?= $wl['trained']==='Yes' ? '<span class="chip sage">✓ Done</span>' : '<span class="chip">Rest</span>' ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center;padding:20px;color:var(--muted)">No sessions logged this week yet. <a href="/portal/log" style="color:var(--sage-2);font-weight:600">Log today →</a></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Goals from answers -->
      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow">Week <?= $prog['current'] ?> &middot; targets</div>
              <h3 class="h3" style="margin-top:4px">What we're aiming for</h3>
            </div>
          </div>
          <div class="body">
            <div class="goal-row">
              <div><div class="lbl">Time in range (70–140)</div><div class="meta">Pre/post sessions</div></div>
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
        Your coach is personalizing your plan based on your intake assessment. You'll receive an email and notification when it's ready — usually within 24 hours.
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
