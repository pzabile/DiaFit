<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$prog = member_program_info($me);
$weekNum = $prog['current'];

// Load existing weekly note for this week
$existing = db_get(
    'SELECT * FROM weekly_notes WHERE lead_id = ? AND week_number = ?',
    [$leadId, $weekNum]
);

// Auto-stats for this week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

$logsThisWeek = db_all(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date BETWEEN ? AND ?',
    [$leadId, $weekStart, $weekEnd]
);
$sessionsLogged = count(array_filter($logsThisWeek, fn($l) => $l['trained'] === 'Yes'));
$totalLogs = count($logsThisWeek);

// Avg glucose this week
$glucVals = [];
foreach ($logsThisWeek as $l) {
    if ($l['bs_before']) $glucVals[] = (int)$l['bs_before'];
    if ($l['bs_after'])  $glucVals[] = (int)$l['bs_after'];
}
$avgGlucWeek = $glucVals ? round(array_sum($glucVals) / count($glucVals)) : null;

// Time in range
$inRange = 0; $total = count($glucVals);
foreach ($glucVals as $v) { if ($v >= 70 && $v <= 140) $inRange++; }
$tirPct = $total ? round($inRange / $total * 100) : null;

$pageTitle  = 'Weekly Review — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'weekly';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Weekly review</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <a href="/portal/today" class="btn sm">← Back to Today</a>
    </div>
  </header>

  <section class="view">
    <div class="eyebrow">Week <?= $weekNum ?> &middot; review</div>
    <h1 class="h1">How was your <em>week <?= $weekNum ?></em>?</h1>
    <p class="muted" style="margin:0 0 20px;max-width:60ch">Tell your coach in your words. They use this — plus your daily logs — to tune next week.</p>

    <div class="log-grid">
      <div class="card">
        <div class="body" style="padding:6px 24px 18px">
          <form id="weeklyForm" method="post" action="/api/weekly_save">
            <?= csrf_input() ?>
            <input type="hidden" name="week_number" value="<?= $weekNum ?>">

            <!-- 01 Numbers -->
            <div class="section">
              <div class="section-head"><div class="section-title"><span class="ix">01</span><h2>Numbers</h2></div></div>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <div>
                  <label class="field-lbl">Avg glucose</label>
                  <div class="num-input">
                    <input class="input" type="number" name="avg_glucose" placeholder="—" value="<?= e($existing['avg_glucose'] ?? $avgGlucWeek ?? '') ?>">
                    <span class="unit">mg/dL</span>
                  </div>
                </div>
                <div>
                  <label class="field-lbl">Weight</label>
                  <div class="num-input">
                    <input class="input" type="number" step="0.1" name="weight_kg" placeholder="—" value="<?= e($existing['weight_kg'] ?? '') ?>">
                    <span class="unit">kg</span>
                  </div>
                </div>
                <div>
                  <label class="field-lbl">Energy</label>
                  <div class="num-input">
                    <input class="input" type="number" name="energy_rating" min="0" max="10" placeholder="0–10" value="<?= e($existing['energy_rating'] ?? '') ?>">
                    <span class="unit">/10</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 02 What worked -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">02</span><h2>What worked</h2></div>
                <span class="chip sage">be specific</span>
              </div>
              <textarea class="ta" name="wins" placeholder="Workouts you completed, meals on track, hypos avoided, walks after lunch…" style="min-height:96px"><?= e($existing['wins'] ?? '') ?></textarea>
              <div class="hint">
                <button type="button">+ Walks helped</button>
                <button type="button">+ Hit all sessions</button>
                <button type="button">+ Slept 7h+ most nights</button>
              </div>
            </div>

            <!-- 03 Struggles -->
            <div class="section">
              <div class="section-head"><div class="section-title"><span class="ix">03</span><h2>Where it got hard</h2></div></div>
              <textarea class="ta" name="struggles" placeholder="Spikes, soreness, motivation, time, social events…" style="min-height:96px"><?= e($existing['struggles'] ?? '') ?></textarea>
              <div class="hint">
                <button type="button">+ Friday dinner spike</button>
                <button type="button">+ Tight on time Tue</button>
                <button type="button">+ Lower back tight</button>
              </div>
            </div>

            <!-- 04 One thing for coach -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">04</span><h2>One thing for your coach</h2></div>
                <span class="muted" style="font-size:12px">Optional</span>
              </div>
              <textarea class="ta" name="content" placeholder="A question, a request, something you want to try next week…" style="min-height:80px"><?= e($existing['content'] ?? '') ?></textarea>
            </div>
          </form>
        </div>

        <div style="padding:16px 24px;background:linear-gradient(180deg,#FFFDF7,#F4F1E9);border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;border-radius:0 0 var(--r-lg) var(--r-lg)">
          <span class="muted" style="font-size:12.5px">Week <?= $weekNum ?> of <?= $prog['total'] ?> &middot; closes Sunday 11:59 PM</span>
          <button type="button" class="btn pri" id="submitWeekly">Submit week <?= $weekNum ?> review</button>
        </div>
      </div>

      <!-- Auto-stats sidebar -->
      <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card">
          <div class="head"><div><div class="eyebrow">From your logs</div><h3 class="h3" style="margin-top:4px">Week <?= $weekNum ?> auto-summary</h3></div></div>
          <div class="body">
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Sessions completed</div>
              <div style="text-align:right" class="val"><?= $sessionsLogged ?> / 5</div>
            </div>
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Daily check-ins</div>
              <div style="text-align:right" class="val"><?= $totalLogs ?> / 7</div>
            </div>
            <?php if ($tirPct !== null): ?>
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Time in range</div>
              <div style="text-align:right" class="val"><?= $tirPct ?>%</div>
            </div>
            <?php endif; ?>
            <?php if ($avgGlucWeek !== null): ?>
            <div class="goal-row" style="padding:10px 0">
              <div class="lbl">Avg glucose</div>
              <div style="text-align:right" class="val"><?= $avgGlucWeek ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>
</div>

<script>
// Hint chips
document.querySelectorAll('.hint').forEach(h => {
  h.addEventListener('click', e => {
    const btn = e.target.closest('button'); if (!btn) return;
    const ta = h.previousElementSibling && h.previousElementSibling.tagName === 'TEXTAREA'
      ? h.previousElementSibling
      : h.closest('.section')?.querySelector('textarea');
    if (!ta) return;
    const txt = btn.textContent.replace(/^\+\s*/,'');
    ta.value = ta.value ? (ta.value.replace(/[\s,]+$/,'') + ', ' + txt) : txt;
    ta.focus();
  });
});

document.getElementById('submitWeekly').addEventListener('click', async () => {
  const form = document.getElementById('weeklyForm');
  const data = {};
  new FormData(form).forEach((v,k) => { data[k] = v; });
  try {
    const res = await fetch('/api/weekly_save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf },
      body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.ok) window.location.href = '/portal/today?reviewed=1';
    else alert('Save failed: ' + (json.error || 'unknown error'));
  } catch(e) {
    alert('Network error — please try again');
  }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
