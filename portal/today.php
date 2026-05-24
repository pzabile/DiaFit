<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$prog = member_program_info($me);

// Today's daily log
$todayLog = db_get(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date = CURDATE() LIMIT 1',
    [$leadId]
);

// Latest weekly note
$latestWeekly = db_get(
    'SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC LIMIT 1',
    [$leadId]
);

// Latest coach note (from coach)
$latestCoach = db_get(
    'SELECT * FROM coach_notes WHERE lead_id = ? AND from_member = 0 AND is_private = 0 ORDER BY created_at DESC LIMIT 1',
    [$leadId]
);

// Coach motivation note
$motivationNote = null;
try {
    $motivationNote = db_get('SELECT motivation_note FROM leads WHERE id = ?', [$leadId])['motivation_note'] ?? null;
    if (!$motivationNote) $motivationNote = null;
} catch (Throwable $ignored) {}

// Recent 5 daily logs
$recentLogs = db_all(
    'SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC LIMIT 5',
    [$leadId]
);

// Today's plan items from admin
$todayPlanItems = [];
try {
    $todayPlanItems = db_all(
        'SELECT * FROM daily_plan_items WHERE lead_id = ? AND plan_date = CURDATE() ORDER BY sort_order ASC, id ASC',
        [$leadId]
    );
} catch (Throwable $ignored) {}

// 7-day avg glucose
$avgGlucose7d = null;
$glucData = db_all(
    'SELECT bs_before, bs_after FROM daily_logs WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
    [$leadId]
);
if ($glucData) {
    $vals = [];
    foreach ($glucData as $row) {
        if ($row['bs_before']) $vals[] = (int)$row['bs_before'];
        if ($row['bs_after'])  $vals[] = (int)$row['bs_after'];
    }
    if ($vals) $avgGlucose7d = round(array_sum($vals) / count($vals));
}

// Streak: count consecutive days backwards from today using a single query
$streakDays = 0;
$streakRows = db_all(
    'SELECT log_date FROM daily_logs WHERE lead_id = ? AND log_date <= CURDATE()
     ORDER BY log_date DESC LIMIT 365',
    [$leadId]
);
$checkDate = new DateTime('today');
foreach ($streakRows as $sRow) {
    $d = new DateTime($sRow['log_date']);
    if ($d->format('Y-m-d') !== $checkDate->format('Y-m-d')) break;
    $streakDays++;
    $checkDate->modify('-1 day');
}

// Current week's 7 days (Mon–Sun)
$today = new DateTime('today');
$dayOfWeek = (int)$today->format('N'); // 1=Mon 7=Sun
$weekStart = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $weekStart)->modify("+{$i} days");
    $dStr = $d->format('Y-m-d');
    $logged = db_get('SELECT id FROM daily_logs WHERE lead_id = ? AND log_date = ?', [$leadId, $dStr]);
    $weekDays[] = [
        'dow'    => strtoupper($d->format('D')),
        'day'    => $d->format('j'),
        'date'   => $dStr,
        'logged' => (bool)$logged,
        'today'  => $dStr === $today->format('Y-m-d'),
        'future' => $dStr > $today->format('Y-m-d'),
    ];
}

// Time of day greeting
$hour = (int)date('G');
if ($hour < 12) $timeOfDay = 'morning';
elseif ($hour < 17) $timeOfDay = 'afternoon';
else $timeOfDay = 'evening';

// Circumference for ring SVG
$circumference = 2 * M_PI * 56; // r=56 → ~351.86
$dashOffset    = $circumference * (1 - $prog['pct'] / 100);
$dashArray     = round($circumference * $prog['pct'] / 100, 1) . ' ' . round($circumference * (1 - $prog['pct'] / 100), 1);

$pageTitle  = 'Today — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'today';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Today</span>
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
    <?php if (!empty($_GET['logged'])): ?>
    <div style="background:var(--sage-tint);border:1px solid var(--sage-tint-2);border-radius:14px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;color:var(--sage-3)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
      <span><strong>Check-in logged!</strong> Your coach can see it. Keep the streak going.</span>
    </div>
    <?php elseif (!empty($_GET['reviewed'])): ?>
    <div style="background:var(--amber-tint);border:1px solid #E8D4AC;border-radius:14px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;color:#7C5215">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
      <span><strong>Weekly review submitted!</strong> Your coach will read it before next week's program.</span>
    </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="hero">
      <div class="hero-row">
        <div>
          <div class="eyebrow" style="color:#9CC9A8">Welcome back</div>
          <h1>Good <?= e($timeOfDay) ?>, <em><?= e($me['first_name']) ?></em>.</h1>
          <p>Day <?= $prog['current_day'] ?> of your <?= $prog['total_days'] ?>-day plan.
          <?php if ($avgGlucose7d): ?>
            Your 7-day average glucose is <?= $avgGlucose7d ?> mg/dL — keep logging to see your patterns.
          <?php else: ?>
            Log your first check-in to start tracking your progress.
          <?php endif; ?>
          </p>
          <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
            <a href="/portal/log" class="btn pri">Log today's check-in <span class="k">⏎</span></a>
            <a href="/portal/program" class="btn" style="background:transparent;color:#E6EFE6;border-color:#3B6E54">Open today's session →</a>
          </div>
        </div>
        <div class="ring">
          <div class="ring-vis">
            <svg width="130" height="130" viewBox="0 0 130 130">
              <circle cx="65" cy="65" r="56" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="8"/>
              <circle cx="65" cy="65" r="56" fill="none" stroke="#9CC9A8" stroke-width="8" stroke-linecap="round"
                      stroke-dasharray="<?= $dashArray ?>" />
            </svg>
            <div class="label">
              <div class="num"><?= $prog['current_day'] ?><span style="font-size:18px;color:#9CC9A8">/<?= $prog['total_days'] ?></span></div>
              <div class="sub">Day</div>
            </div>
          </div>
          <div class="ring-stat">
            <div><strong><?= $prog['pct'] ?>%</strong> through</div>
            <div>Your program</div>
            <?php if ($prog['current'] < $prog['total']): ?>
            <div style="color:#fff;font-weight:600">Day <?= min($prog['total_days'], $prog['current_day'] + 21) ?> · re-test</div>
            <?php else: ?>
            <div style="color:#9CC9A8;font-weight:600">Final week!</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI Grid -->
    <div class="kpi-grid">
      <!-- Avg glucose -->
      <div class="kpi">
        <div class="lbl">
          <span>Avg glucose &middot; 7d</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v6m0 0a4 4 0 00-4 4c0 3 4 8 4 8s4-5 4-8a4 4 0 00-4-4z"/></svg>
        </div>
        <div class="val">
          <span class="kpi-num"><?= $avgGlucose7d ?? '—' ?></span>
          <?php if ($avgGlucose7d): ?><span class="kpi-unit">mg/dL</span><?php endif; ?>
        </div>
        <div class="foot">Target band 80–140</div>
      </div>

      <!-- Weight -->
      <div class="kpi">
        <div class="lbl"><span>Weight</span></div>
        <div class="val">
          <?php if ($latestWeekly && $latestWeekly['weight_kg']): ?>
            <span class="kpi-num"><?= round($latestWeekly['weight_kg'] * 2.20462, 1) ?></span>
            <span class="kpi-unit">lbs</span>
          <?php else: ?>
            <span class="kpi-num">—</span>
          <?php endif; ?>
        </div>
        <div class="foot">From latest weekly review</div>
      </div>

      <!-- Energy -->
      <div class="kpi">
        <div class="lbl"><span>Energy</span></div>
        <div class="val">
          <?php if ($latestWeekly && $latestWeekly['energy_rating'] !== null): ?>
            <span class="kpi-num"><?= (int)$latestWeekly['energy_rating'] ?></span>
            <span class="kpi-unit">/10</span>
          <?php else: ?>
            <span class="kpi-num">—</span>
          <?php endif; ?>
        </div>
        <div class="foot">Self-rated, latest week</div>
      </div>

      <!-- Streak -->
      <div class="kpi">
        <div class="lbl"><span>Streak</span></div>
        <div class="val">
          <span class="kpi-num"><?= $streakDays ?></span>
          <span class="kpi-unit">days</span>
          <?php if ($streakDays > 0): ?>
            <span class="delta-chip up" style="margin-left:auto">🔥</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:4px;height:36px;align-items:flex-end;margin-top:10px">
          <?php foreach (array_slice($weekDays, 0, 7) as $wd): ?>
            <div style="flex:1;height:<?= $wd['logged'] ? '80%' : ($wd['today'] ? '40%' : '20%') ?>;background:<?= $wd['logged'] ? 'var(--sage)' : ($wd['today'] ? 'var(--sage-tint)' : 'var(--line)') ?>;border-radius:3px<?= $wd['today'] && !$wd['logged'] ? ';border:1px dashed var(--sage)' : '' ?>"></div>
          <?php endforeach; ?>
        </div>
        <div class="foot">This week's check-ins</div>
      </div>
    </div>

    <!-- Daily plan from coach -->
    <?php if ($todayPlanItems): ?>
    <div class="card" style="margin-top:18px">
      <div class="head">
        <div>
          <div class="eyebrow sage">From your coach</div>
          <h3 class="h3" style="margin-top:4px">Today's plan</h3>
        </div>
        <span class="chip"><?= count(array_filter($todayPlanItems, fn($i) => $i['is_done'])) ?> / <?= count($todayPlanItems) ?> done</span>
      </div>
      <div class="body" style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($todayPlanItems as $pi): ?>
        <div class="plan-check-item" id="pci-<?= (int)$pi['id'] ?>" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid <?= $pi['is_done'] ? 'var(--sage-tint-2)' : 'var(--line)' ?>;border-radius:11px;background:<?= $pi['is_done'] ? 'var(--sage-tint)' : 'var(--card)' ?>;cursor:pointer;transition:.15s" onclick="togglePlanItem(<?= (int)$pi['id'] ?>, <?= $pi['is_done'] ? 0 : 1 ?>)">
          <div style="width:22px;height:22px;border-radius:50%;border:1.5px solid <?= $pi['is_done'] ? 'var(--sage)' : 'var(--line-2)' ?>;background:<?= $pi['is_done'] ? 'var(--sage)' : '#fff' ?>;display:grid;place-items:center;flex-shrink:0;color:#fff;font-size:11px">
            <?php if ($pi['is_done']): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg><?php endif; ?>
          </div>
          <span style="font-size:13.5px;font-weight:500;<?= $pi['is_done'] ? 'text-decoration:line-through;color:var(--muted)' : '' ?>"><?= e($pi['item_text']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Today grid -->
    <div class="today-grid">
      <!-- Left: Today's plan / log status -->
      <div class="card">
        <div class="head">
          <div>
            <div class="eyebrow sage">Today's plan</div>
            <h2 class="h2" style="margin-top:4px"><?= date('l') ?> &middot; Day <?= $prog['current_day'] ?></h2>
          </div>
          <a href="/portal/log" class="btn sm">Log check-in</a>
        </div>
        <div class="body">
          <?php if ($todayLog): ?>
            <div class="plan-list">
              <div class="plan-item done">
                <div class="plan-check">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                </div>
                <div>
                  <div class="what">Daily check-in logged</div>
                  <div class="meta">Feeling <?= e($todayLog['feeling'] ?? '—') ?> · <?= $todayLog['trained'] === 'Yes' ? 'Trained' : 'Rest day' ?></div>
                </div>
                <div class="time"><?= date('g:i A', strtotime($todayLog['created_at'])) ?></div>
              </div>
              <?php if ($todayLog['bs_before'] || $todayLog['bs_after']): ?>
              <div class="plan-item done">
                <div class="plan-check">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                </div>
                <div>
                  <div class="what">Glucose logged</div>
                  <div class="meta">Before <?= $todayLog['bs_before'] ? $todayLog['bs_before'] . ' mg/dL' : '—' ?> · After <?= $todayLog['bs_after'] ? $todayLog['bs_after'] . ' mg/dL' : '—' ?></div>
                </div>
              </div>
              <?php endif; ?>
              <!-- weekly review not shown in daily plan -->
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:24px 0">
              <div style="font-size:42px;margin-bottom:10px">📋</div>
              <div style="font-weight:600;font-size:15px;margin-bottom:6px">No check-in yet today</div>
              <div style="color:var(--muted);font-size:13px;margin-bottom:18px">Takes about 90 seconds. Everything's optional.</div>
              <a href="/portal/log" class="btn pri">Start today's log <span class="k">⏎</span></a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:18px">
        <!-- Week strip -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow">This week</div>
              <h3 class="h3" style="margin-top:4px"><?= $weekStart->format('M j') ?> — <?= (clone $weekStart)->modify('+6 days')->format('M j') ?></h3>
            </div>
            <a href="/portal/program" class="btn sm">Program →</a>
          </div>
          <div class="body">
            <div class="week-strip">
              <?php foreach ($weekDays as $wd): ?>
                <div class="day-col<?= $wd['today'] ? ' today' : ($wd['future'] ? ' future' : '') ?>">
                  <div class="dow"><?= $wd['dow'] ?></div>
                  <div class="dnum"><?= $wd['day'] ?></div>
                  <div class="pips">
                    <div class="pip<?= $wd['logged'] ? ' on' : '' ?>"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Motivation note from coach (personal, set in admin/member) -->
        <?php if ($motivationNote): ?>
        <div class="card" style="background:linear-gradient(135deg,var(--sage-3),#1a3527);border:1px solid #2A4738;color:#E6EFE6">
          <div class="body" style="padding:20px 22px">
            <div class="eyebrow" style="color:#9CC9A8;margin-bottom:10px">Your coach · just for you</div>
            <div style="font-family:'Instrument Serif',serif;font-size:22px;line-height:1.35;letter-spacing:-.005em;font-style:italic;color:#E6EFE6">"<?= e($motivationNote) ?>"</div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Coach card -->
        <?php if ($latestCoach): ?>
        <div class="card coach-card">
          <div class="head">
            <div class="eyebrow sage">A note from your coach</div>
            <span class="chip sage">unread</span>
          </div>
          <div class="body">
            <div class="who">
              <div class="av">C</div>
              <div>
                <div class="name">Your coach</div>
                <div class="role">Diafitus coach</div>
              </div>
            </div>
            <div class="msg">"<?= e(mb_substr($latestCoach['body'], 0, 220)) ?><?= mb_strlen($latestCoach['body']) > 220 ? '…' : '' ?>"</div>
            <div style="margin-top:14px;display:flex;gap:10px">
              <a href="/portal/coach" class="btn sm pri">Reply</a>
              <a href="/portal/coach" class="btn sm">View all</a>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="card">
          <div class="head"><div class="eyebrow sage">Coach</div></div>
          <div class="body" style="text-align:center;padding:20px">
            <div style="color:var(--muted);font-size:13px;margin-bottom:12px">No messages yet</div>
            <a href="/portal/coach" class="btn sm pri">Message your coach</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent activity -->
    <?php if ($recentLogs): ?>
    <div class="card" style="margin-top:18px">
      <div class="head">
        <div>
          <div class="eyebrow">Recent activity</div>
          <h3 class="h3" style="margin-top:4px">Last check-ins</h3>
        </div>
        <a href="/portal/log" class="btn sm">View all</a>
      </div>
      <div class="body" style="padding-top:6px">
        <?php foreach ($recentLogs as $log): ?>
          <div class="note-row">
            <div class="left">
              <div class="dot-ic" style="background:<?= $log['trained']==='Yes' ? 'var(--sage-tint)' : 'var(--bg-2)' ?>;color:<?= $log['trained']==='Yes' ? 'var(--sage-2)' : 'var(--ink-2)' ?>">
                <?php if ($log['trained']==='Yes'): ?>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4l3 16M14 4l3 16M3 9h18M3 15h18"/></svg>
                <?php else: ?>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <?php endif; ?>
              </div>
              <div>
                <div class="ttl"><?= e(date('M j', strtotime($log['log_date']))) ?> — <?= e($log['feeling'] ?? 'logged') ?>
                  <?php if ($log['bs_before']): ?><span class="chip sage" style="margin-left:6px"><?= (int)$log['bs_before'] ?> mg/dL</span><?php endif; ?>
                </div>
                <div class="sub"><?= $log['trained']==='Yes' ? 'Trained' : 'Rest day' ?><?= $log['soreness'] !== null ? ' · Soreness '.(int)$log['soreness'].'/10' : '' ?></div>
              </div>
            </div>
            <div class="when"><?= $log['log_date'] === date('Y-m-d') ? 'Today' : (date('Y-m-d', strtotime('-1 day')) === $log['log_date'] ? 'Yesterday' : date('M j', strtotime($log['log_date']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </section>
</main>
</div>
<script>
const PLAN_CSRF = <?= json_encode(csrf_token()) ?>;
async function togglePlanItem(id, done) {
  var el = document.getElementById('pci-' + id);
  try {
    var res = await fetch('/api/plan_toggle', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':PLAN_CSRF},
      body: JSON.stringify({id: id, done: done, csrf: PLAN_CSRF})
    });
    var data = await res.json();
    if (data.ok) {
      // Reload to show updated state
      location.reload();
    }
  } catch(e) {}
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
