<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$logDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
    ? $_GET['date']
    : date('Y-m-d');

// Load a specific entry if ?id=X is in URL, otherwise new entry
$editId = (int)($_GET['id'] ?? 0);
$existing = $editId
    ? db_get('SELECT * FROM daily_logs WHERE id = ? AND lead_id = ?', [$editId, $leadId])
    : null;

// All entries for this date
$dateEntries = db_all(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date = ? ORDER BY created_at ASC',
    [$leadId, $logDate]
);

// Yesterday's log for the sidebar
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdayLog = db_get(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date = ?',
    [$leadId, $yesterday]
);

// Streak
$streakDays = 0;
$checkDate  = new DateTime('today');
for ($i = 0; $i < 365; $i++) {
    $d = $checkDate->format('Y-m-d');
    $ex = db_get('SELECT id FROM daily_logs WHERE lead_id = ? AND log_date = ?', [$leadId, $d]);
    if (!$ex) break;
    $streakDays++;
    $checkDate->modify('-1 day');
}

$pageTitle  = 'Daily Log — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'log';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Daily log</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <a href="/portal/today" class="btn sm">← Back to Today</a>
    </div>
  </header>

  <section class="view">
    <div id="logSuccessBanner" style="display:none;background:var(--sage-tint);border:1px solid var(--sage-tint-2);border-radius:14px;padding:12px 18px;margin-bottom:16px;align-items:center;gap:10px;color:var(--sage-3)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
      <span><strong>Saved!</strong> Your check-in was logged. You can log another entry or go back to <a href="/portal/today" style="color:var(--sage-2);font-weight:600">Today →</a></span>
    </div>
    <div class="eyebrow"><?= date('l, M j', strtotime($logDate)) ?> &middot; daily check-in</div>
    <h1 class="h1" style="max-width:18ch">How did <em>today</em> treat you?</h1>
    <p class="muted" style="margin:0 0 20px;max-width:60ch">Takes about 90 seconds. Everything is optional — log what you have.</p>

    <div class="log-grid">
      <!-- Main form card -->
      <div class="card">
        <div class="body" style="padding:6px 24px 18px">
          <?php if ($editId && $existing): ?>
          <div style="background:var(--amber-tint);border:1px solid #E8D4AC;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#7C5215;display:flex;justify-content:space-between;align-items:center">
            <span>Editing entry saved at <?= date('g:i A', strtotime($existing['created_at'])) ?></span>
            <a href="/portal/log?date=<?= e($logDate) ?>" class="btn sm">New entry</a>
          </div>
          <?php endif; ?>
          <form id="logForm" autocomplete="off">
            <?= csrf_input() ?>
            <input type="hidden" name="log_date" value="<?= e($logDate) ?>">
            <input type="hidden" name="id" id="entryId" value="<?= $editId ?: 0 ?>">

            <!-- 01 Feeling -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">01</span><h2>Feeling</h2></div>
                <span class="muted" style="font-size:12px">Tap one</span>
              </div>
              <div class="mood-row" id="moodRow">
                <?php
                $moods = [
                    ['rough', 'Rough', 'Low energy', '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6 13c1-1.2 2.3-1.8 4-1.8s3 .6 4 1.8" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg>'],
                    ['ok',    'Okay',  'Steady',     '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6.5 12.5h7" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg>'],
                    ['good',  'Good',  'Strong, focused', '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6 11c.8 1.2 2.2 2 4 2s3.2-.8 4-2" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg>'],
                    ['great', 'Great', 'PR-day energy', '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M5.5 10.5c1 2 2.6 3 4.5 3s3.5-1 4.5-3" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><path d="M6 7.5l1.5 1M14 7.5l-1.5 1" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/></svg>'],
                ];
                $currentFeeling = $existing['feeling'] ?? '';
                foreach ($moods as $m):
                    $pressed = ($currentFeeling === $m[0]) ? 'true' : 'false';
                ?>
                <button type="button" class="mood" data-mood="<?= $m[0] ?>" aria-pressed="<?= $pressed ?>">
                  <span class="face"><?= $m[3] ?></span>
                  <div><div class="lbl"><?= $m[1] ?></div><div class="sub"><?= $m[2] ?></div></div>
                </button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="feeling" id="feelingInput" value="<?= e($existing['feeling'] ?? '') ?>">
            </div>

            <!-- 02 Training -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">02</span><h2>Training</h2></div>
                <span class="muted" style="font-size:12px">Rest days count too</span>
              </div>
              <div class="row" style="margin-bottom:12px;gap:12px;flex-wrap:wrap">
                <?php $trained = $existing['trained'] ?? 'Yes'; ?>
                <div class="toggle">
                  <button type="button" id="trainYes" class="<?= $trained === 'Yes' ? 'on' : '' ?>">Trained</button>
                  <button type="button" id="trainNo" class="<?= $trained !== 'Yes' ? 'on' : '' ?>">Rest day</button>
                </div>
                <div class="chips" id="locChips">
                  <?php
                  $locs = ['Gym','Home','Outdoors','Studio'];
                  $curLoc = $existing['train_where'] ?? 'Gym';
                  foreach ($locs as $loc):
                  ?>
                    <button type="button" class="chip" aria-pressed="<?= $curLoc === $loc ? 'true' : 'false' ?>"><?= e($loc) ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <input type="hidden" name="trained" id="trainedInput" value="<?= e($trained) ?>">
              <input type="hidden" name="train_where" id="trainWhereInput" value="<?= e($existing['train_where'] ?? 'Gym') ?>">

              <div id="trainingFields">
                <label class="field-lbl">What did you do?</label>
                <textarea id="exercise" name="workout" class="ta" placeholder="e.g. Bench 4×8 @ 135 · DB press 3×10 · Cable fly 3×12"><?= e($existing['workout'] ?? '') ?></textarea>
                <div class="hint">
                  <button type="button">+ Upper push</button>
                  <button type="button">+ Lower body</button>
                  <button type="button">+ 30 min Z2</button>
                  <button type="button">+ Yoga 45m</button>
                </div>

                <div style="margin-top:14px">
                  <div class="row" style="justify-content:space-between">
                    <label class="field-lbl" style="margin:0">Muscle soreness</label>
                    <span class="mono" style="color:var(--sage-2);font-weight:500;font-size:12px" id="soreVal">0 / 10 · none</span>
                  </div>
                  <div class="dots" id="soreDots"></div>
                  <div class="dots-meta"><span>None</span><span>Manageable</span><span>Wrecked</span></div>
                  <input type="hidden" name="soreness" id="sorenessInput" value="<?= (int)($existing['soreness'] ?? 0) ?>">
                </div>
              </div>
            </div>

            <!-- 03 Glucose -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">03</span><h2>Glucose</h2></div>
                <span class="muted" style="font-size:12px">Before &amp; after</span>
              </div>
              <div class="gluc-grid">
                <div>
                  <label class="field-lbl">Before training</label>
                  <div class="num-input">
                    <input class="input" id="gBefore" name="bs_before" type="number" value="<?= e($existing['bs_before'] ?? '') ?>" placeholder="—">
                    <span class="unit">mg/dL</span>
                  </div>
                </div>
                <div class="gluc-arrow">
                  <svg width="22" height="22" viewBox="0 0 28 28" fill="none"><path d="M5 14h17m0 0l-5-5m5 5l-5 5" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                  <label class="field-lbl">After training</label>
                  <div class="num-input">
                    <input class="input" id="gAfter" name="bs_after" type="number" value="<?= e($existing['bs_after'] ?? '') ?>" placeholder="—">
                    <span class="unit">mg/dL</span>
                  </div>
                </div>
                <div class="delta-box" id="delta">
                  <div class="lbl">Change</div>
                  <div class="val" id="deltaVal"><?= $existing['bs_before'] && $existing['bs_after'] ? (($existing['bs_after'] - $existing['bs_before'] >= 0 ? '+' : '−') . abs($existing['bs_after'] - $existing['bs_before'])) : '—' ?></div>
                </div>
              </div>
              <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:12px">
                <span style="font-size:11.5px;color:var(--muted);align-self:center;margin-right:4px">Trend:</span>
                <?php
                $trends = ['Stable','Rising slowly','Falling slowly','Spike','Crash'];
                $curTrend = $existing['bs_trend'] ?? 'Stable';
                foreach ($trends as $t):
                ?>
                  <button type="button" class="chip trend-chip" aria-pressed="<?= $curTrend === $t ? 'true' : 'false' ?>"><?= e($t) ?></button>
                <?php endforeach; ?>
                <input type="hidden" name="bs_trend" id="bsTrendInput" value="<?= e($curTrend) ?>">
              </div>
            </div>

            <!-- 04 Food -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">04</span><h2>What fueled you</h2></div>
                <a href="/portal/meals" class="btn sm">+ Add photo</a>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="field-lbl">Before training</label>
                  <textarea class="ta" name="food_before" placeholder="e.g. oats + berries, black coffee"><?= e($existing['food_before'] ?? '') ?></textarea>
                  <div class="hint">
                    <button type="button">+ Oats &amp; berries</button>
                    <button type="button">+ Toast &amp; PB</button>
                    <button type="button">+ Just coffee</button>
                  </div>
                </div>
                <div>
                  <label class="field-lbl">After training</label>
                  <textarea class="ta" name="food_after" placeholder="e.g. chicken, rice, salad"><?= e($existing['food_after'] ?? '') ?></textarea>
                  <div class="hint">
                    <button type="button">+ Chicken + rice</button>
                    <button type="button">+ Protein shake</button>
                    <button type="button">+ Eggs &amp; greens</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- 05 Notes -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">05</span><h2>Notes for coach</h2></div>
                <span class="muted" style="font-size:12px">Optional</span>
              </div>
              <textarea class="ta" name="notes" placeholder="Energy, sleep, stress, medication, life stuff…"><?= e($existing['notes'] ?? '') ?></textarea>
              <div class="hint">
                <button type="button">+ Slept 7h</button>
                <button type="button">+ Stress: high</button>
                <button type="button">+ Forgot Metformin</button>
                <button type="button">+ Hot outside</button>
              </div>
            </div>

            <!-- 06 Workout journal -->
            <div class="section">
              <div class="section-head">
                <div class="section-title"><span class="ix">06</span><h2>Training journal</h2></div>
                <span class="muted" style="font-size:12px">Optional · just for you</span>
              </div>
              <textarea class="ta" name="workout_journal" placeholder="What did you enjoy? What was hard? Exercises you want to try, notes to yourself…" style="min-height:110px"><?= e($existing['workout_journal'] ?? '') ?></textarea>
              <div class="hint">
                <button type="button">+ Loved the deadlifts</button>
                <button type="button">+ Shoulders felt weak</button>
                <button type="button">+ Want to try incline bench</button>
                <button type="button">+ Best session in weeks</button>
              </div>
            </div>
          </form>
        </div>

        <!-- Save bar -->
        <div id="saveBar" style="padding:16px 24px;background:linear-gradient(180deg,#FFFDF7,#F4F1E9);border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;border-radius:0 0 var(--r-lg) var(--r-lg)">
          <div style="display:flex;gap:8px;align-items:center;color:var(--muted);font-size:12.5px">
            <span id="savePip" style="width:7px;height:7px;border-radius:50%;background:var(--line-2);box-shadow:0 0 0 3px var(--bg-2)"></span>
            <span id="saveStatus">Not saved yet</span>
          </div>
          <button type="button" class="btn pri" id="submitBtn" onclick="submitLog()">Log today's check-in <span class="k">⏎</span></button>
        </div>
      </div>

      <!-- Sidebar -->
      <div style="display:flex;flex-direction:column;gap:18px">
        <!-- Streak card (dark) -->
        <div class="card dark">
          <div class="body">
            <div class="eyebrow" style="color:#9CC9A8">Current streak</div>
            <div style="display:flex;align-items:flex-end;gap:14px;margin-top:8px">
              <div>
                <div style="font-family:'Instrument Serif',serif;font-size:62px;line-height:.95;letter-spacing:-.02em"><?= $streakDays ?><em style="font-style:italic;color:var(--amber)">d</em></div>
                <div style="color:#9CA399;font-size:13px;margin-top:4px">
                  <?php if ($streakDays === 0): ?>
                    Start your streak today
                  <?php else: ?>
                    Keep it going — log by 11:59 PM
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Yesterday at a glance -->
        <?php if ($yesterdayLog): ?>
        <div class="card dark">
          <div class="body">
            <div class="eyebrow" style="color:#9CC9A8">Yesterday at a glance</div>
            <h3 class="h3" style="color:#fff;margin-top:6px"><?= e($yesterdayLog['feeling'] ?? 'Logged') ?> day.</h3>
            <p style="color:#B5C7BC;margin:8px 0 14px;font-size:13px">
              Glucose <?= $yesterdayLog['bs_before'] ? $yesterdayLog['bs_before'].' mg/dL' : '—' ?> → <?= $yesterdayLog['bs_after'] ? $yesterdayLog['bs_after'].' mg/dL' : '—' ?>
              <?php if ($yesterdayLog['soreness'] !== null): ?> · soreness <?= (int)$yesterdayLog['soreness'] ?>/10<?php endif; ?>
            </p>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php if ($yesterdayLog['trained'] === 'Yes'): ?>
                <span class="chip" style="background:transparent;border-color:#3B6E54;color:#9CC9A8">Trained</span>
              <?php else: ?>
                <span class="chip" style="background:transparent;border-color:#3B6E54;color:#9CC9A8">Rest day</span>
              <?php endif; ?>
              <?php if ($yesterdayLog['bs_before'] && $yesterdayLog['bs_after']):
                    $delta = $yesterdayLog['bs_after'] - $yesterdayLog['bs_before'];
                    $sign = $delta >= 0 ? '+' : '−';
              ?>
                <span class="chip" style="background:transparent;border-color:#3B6E54;color:#9CC9A8">Δ <?= $sign . abs($delta) ?> mg/dL</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="card">
          <div class="head"><div><div class="eyebrow">Why it matters</div><h3 class="h3" style="margin-top:4px">Today's data unlocks…</h3></div></div>
          <div class="body">
            <div style="display:flex;flex-direction:column;gap:14px">
              <div class="row" style="gap:12px">
                <div class="dot-ic" style="width:36px;height:36px;background:var(--sage-tint);color:var(--sage-2);border-radius:10px;display:grid;place-items:center">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-5 4 4 8-9"/></svg>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13.5px">Sunday's trend report</div>
                  <div class="muted" style="font-size:12px">Patterns by day-of-week, meal, training type</div>
                </div>
              </div>
              <div class="row" style="gap:12px">
                <div class="dot-ic" style="width:36px;height:36px;background:var(--amber-tint);color:#7C5215;border-radius:10px;display:grid;place-items:center">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13.5px">Better program tuning</div>
                  <div class="muted" style="font-size:12px">Your coach adjusts next week based on what you log</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Past entries for this date -->
    <div style="margin-top:28px">
      <div class="eyebrow" style="margin-bottom:10px">Saved check-ins · <?= date('M j', strtotime($logDate)) ?></div>
      <div id="entriesList">
        <?php if ($dateEntries): ?>
          <?php foreach ($dateEntries as $ent): ?>
            <?php
            $badges = [];
            if ($ent['feeling']) $badges[] = '<span class="chip sage" style="font-size:11px">' . e($ent['feeling']) . '</span>';
            if ($ent['trained']) $badges[] = '<span class="chip" style="font-size:11px">' . ($ent['trained']==='Yes'?'Trained':'Rest') . '</span>';
            if ($ent['bs_before']) $badges[] = '<span class="chip" style="font-size:11px">' . (int)$ent['bs_before'] . ' mg/dL</span>';
            ?>
            <div style="border:1px solid var(--line);border-radius:12px;padding:12px 16px;background:var(--card);display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px<?= $ent['id'] == $editId ? ';border-color:var(--sage);background:var(--sage-tint)' : '' ?>">
              <div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px"><?= $badges ? implode('', $badges) : '<span style="color:var(--muted);font-size:12px">No details</span>' ?></div>
                <div style="font-size:11.5px;color:var(--muted)">Saved at <?= date('g:i A', strtotime($ent['created_at'])) ?><?= $ent['notes'] ? ' · has notes' : '' ?></div>
              </div>
              <a href="/portal/log?date=<?= e($logDate) ?>&id=<?= (int)$ent['id'] ?>" class="btn sm">Edit →</a>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="color:var(--muted);text-align:center;padding:20px;font-size:13px">No entries yet for this date.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const LOG_DATE = <?= json_encode($logDate) ?>;
const INITIAL_SORENESS = <?= (int)($existing['soreness'] ?? 0) ?>;

// Mood selection
const moodRow = document.getElementById('moodRow');
const feelingInput = document.getElementById('feelingInput');
moodRow.addEventListener('click', e => {
  const b = e.target.closest('.mood'); if (!b) return;
  moodRow.querySelectorAll('.mood').forEach(m => m.setAttribute('aria-pressed','false'));
  b.setAttribute('aria-pressed','true');
  feelingInput.value = b.dataset.mood;
});

// Train/Rest toggle
const ty = document.getElementById('trainYes'), tn = document.getElementById('trainNo');
const trainedInput = document.getElementById('trainedInput');
const trainingFields = document.getElementById('trainingFields');
function setTrained(v) {
  trainedInput.value = v;
  if (v === 'Yes') {
    ty.classList.add('on'); tn.classList.remove('on');
    trainingFields.style.display = '';
  } else {
    tn.classList.add('on'); ty.classList.remove('on');
    trainingFields.style.display = 'none';
  }
}
ty.onclick = () => setTrained('Yes');
tn.onclick = () => setTrained('No');
setTrained(trainedInput.value || 'Yes');

// Location chips
const locChips = document.getElementById('locChips');
const trainWhereInput = document.getElementById('trainWhereInput');
locChips.addEventListener('click', e => {
  const c = e.target.closest('.chip'); if (!c) return;
  locChips.querySelectorAll('.chip').forEach(x => x.setAttribute('aria-pressed','false'));
  c.setAttribute('aria-pressed','true');
  trainWhereInput.value = c.textContent.trim();
});

// Soreness dots
const sd = document.getElementById('soreDots');
const sv = document.getElementById('soreVal');
const sorenessInput = document.getElementById('sorenessInput');
const labels = ['none','barely','slight','mild','noticeable','moderate','meaningful','high','strong','intense','wrecked'];
for (let i = 0; i <= 10; i++) {
  const b = document.createElement('button');
  b.type = 'button'; b.className = 'dot-btn'; b.textContent = i; b.dataset.v = i;
  sd.appendChild(b);
}
function setSore(v) {
  sd.querySelectorAll('.dot-btn').forEach(d => {
    const dv = +d.dataset.v;
    d.classList.toggle('active', dv === v);
    d.classList.toggle('in-range', dv < v);
  });
  sv.textContent = `${v} / 10 · ${labels[v]}`;
  sorenessInput.value = v;
}
sd.addEventListener('click', e => {
  const b = e.target.closest('.dot-btn'); if (!b) return;
  setSore(+b.dataset.v);
});
setSore(INITIAL_SORENESS);

// Glucose delta
const gb = document.getElementById('gBefore'), ga = document.getElementById('gAfter');
const dEl = document.getElementById('delta'), dv = document.getElementById('deltaVal');
function updateDelta() {
  const a = +gb.value, b = +ga.value;
  if (!a || !b) { dv.textContent = '—'; return; }
  const d = b - a;
  dv.textContent = (d > 0 ? '+' : d < 0 ? '−' : '±') + Math.abs(d);
}
gb.addEventListener('input', updateDelta);
ga.addEventListener('input', updateDelta);

// Trend chips
const bsTrendInput = document.getElementById('bsTrendInput');
document.querySelectorAll('.trend-chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('.trend-chip').forEach(c => c.setAttribute('aria-pressed','false'));
    chip.setAttribute('aria-pressed','true');
    bsTrendInput.value = chip.textContent.trim();
  });
});

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

// Auto-save
let lastSaved = null;
let autoSaveTimer = null;
let saveIntervalId = null;
const savePip = document.getElementById('savePip');
const saveStatus = document.getElementById('saveStatus');

function getFormData() {
  const form = document.getElementById('logForm');
  const data = {};
  new FormData(form).forEach((v, k) => { data[k] = v; });
  return data;
}

async function doSave(showLoading = false) {
  if (showLoading) saveStatus.textContent = 'Saving…';
  const data = getFormData();
  try {
    const res = await fetch('/api/log_save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.ok) {
      // After first INSERT, remember the id so subsequent saves UPDATE (not insert again)
      const entryIdEl = document.getElementById('entryId');
      if (entryIdEl && entryIdEl.value === '0' && json.id) {
        entryIdEl.value = json.id;
      }
      lastSaved = new Date();
      savePip.style.background = 'var(--sage)';
      savePip.style.boxShadow = '0 0 0 3px var(--sage-tint)';
      saveStatus.textContent = 'Saved · just now';
      return json;
    }
  } catch(e) {
    saveStatus.textContent = 'Save failed — try again';
  }
}

// Update "N sec ago" display
setInterval(() => {
  if (lastSaved) {
    const secs = Math.round((new Date() - lastSaved) / 1000);
    if (secs < 60) saveStatus.textContent = `Saved · ${secs}s ago`;
    else saveStatus.textContent = `Saved · ${Math.round(secs/60)}m ago`;
  }
}, 5000);

// Auto-save every 30s — ONLY when editing an existing entry (id != 0)
setInterval(() => {
  if (document.getElementById('entryId').value !== '0') doSave();
}, 30000);

// Track unsaved changes; auto-save only when editing an existing entry
document.getElementById('logForm').addEventListener('input', () => {
  savePip.style.background = 'var(--amber)';
  savePip.style.boxShadow = '0 0 0 3px var(--amber-tint)';
  saveStatus.textContent = 'Unsaved changes';
  if (document.getElementById('entryId').value !== '0') {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => doSave(true), 2000);
  }
});

// Submit
async function submitLog() {
  const result = await doSave(true);
  if (result && result.ok) {
    // Show success banner
    const banner = document.getElementById('logSuccessBanner');
    if (banner) { banner.style.display = 'flex'; setTimeout(() => { banner.style.display = 'none'; }, 4000); }
    // Reset form to defaults
    resetLogForm();
    // Reload entries list
    refreshEntries(result.id);
  }
}

function resetLogForm() {
  // Reset feeling
  document.querySelectorAll('.mood').forEach(m => m.setAttribute('aria-pressed','false'));
  document.getElementById('feelingInput').value = '';
  // Reset trained to Yes
  setTrained('Yes');
  // Reset location to Gym
  document.querySelectorAll('#locChips .chip').forEach((c,i) => c.setAttribute('aria-pressed', i===0?'true':'false'));
  document.getElementById('trainWhereInput').value = 'Gym';
  // Reset workout textarea
  const ta = document.getElementById('exercise');
  if (ta) ta.value = '';
  // Reset soreness
  setSore(0);
  // Reset glucose
  const gb = document.getElementById('gBefore'), ga = document.getElementById('gAfter');
  if (gb) gb.value = ''; if (ga) ga.value = '';
  document.getElementById('deltaVal').textContent = '—';
  // Reset trend chips
  document.querySelectorAll('.trend-chip').forEach((c,i) => c.setAttribute('aria-pressed', i===0?'true':'false'));
  document.getElementById('bsTrendInput').value = 'Stable';
  // Reset all textareas
  document.querySelectorAll('#logForm textarea').forEach(ta => {
    if (ta.name !== 'workout') ta.value = '';
  });
  // Clear the hidden id and workout textarea
  document.getElementById('exercise').value = '';
  document.querySelectorAll('#logForm textarea').forEach(ta => { ta.value = ''; });
  // Reset entry id to 0 (new entry)
  document.getElementById('entryId').value = '0';
  // Update save status
  document.getElementById('savePip').style.background = 'var(--line-2)';
  document.getElementById('savePip').style.boxShadow = '0 0 0 3px var(--bg-2)';
  document.getElementById('saveStatus').textContent = 'Not saved yet';
  lastSaved = null;
}

function refreshEntries(newId) {
  fetch('/api/log_entries?date=' + encodeURIComponent(LOG_DATE))
    .then(r => r.json())
    .then(data => {
      if (data.ok && data.entries) renderEntries(data.entries);
    });
}

function renderEntries(entries) {
  var list = document.getElementById('entriesList');
  if (!list) return;
  if (!entries.length) { list.innerHTML = '<div style="color:var(--muted);text-align:center;padding:20px;font-size:13px">No entries yet for this date.</div>'; return; }
  list.innerHTML = entries.map(function(e) {
    var dt = new Date(e.created_at.replace(' ','T'));
    var time = dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
    var badges = [];
    if (e.feeling) badges.push('<span class="chip sage" style="font-size:11px">' + e.feeling + '</span>');
    if (e.trained) badges.push('<span class="chip" style="font-size:11px">' + (e.trained==='Yes'?'Trained':'Rest') + '</span>');
    if (e.bs_before) badges.push('<span class="chip" style="font-size:11px">' + e.bs_before + ' mg/dL</span>');
    return '<div style="border:1px solid var(--line);border-radius:12px;padding:12px 16px;background:var(--card);display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px">'
      + '<div><div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px">' + (badges.join('') || '<span style="color:var(--muted);font-size:12px">No details</span>') + '</div>'
      + '<div style="font-size:11.5px;color:var(--muted)">Saved at ' + time + (e.notes ? ' · has notes' : '') + '</div></div>'
      + '<a href="/portal/log?date=' + e.log_date + '&id=' + e.id + '" class="btn sm">Edit →</a>'
      + '</div>';
  }).join('');
}

// Keyboard shortcut
document.addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') submitLog();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
