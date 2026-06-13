<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$dailyLogs = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC, created_at DESC LIMIT 90', [$me['id']]);

$ids = array_column($dailyLogs, 'id');
$commentsByLog   = [];
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

// Streak calculation
$logDateSet = array_flip(array_unique(array_column($dailyLogs, 'log_date')));
$streak = 0;
$sd = new DateTime('today');
if (!isset($logDateSet[$sd->format('Y-m-d')])) {
    $sd->modify('-1 day');
}
while (isset($logDateSet[$sd->format('Y-m-d')])) {
    $streak++;
    $sd->modify('-1 day');
}

// Week dots (Mon–Sun of current week)
$weekDotLabels = ['M','T','W','T','F','S','S'];
$weekDots = [];
$monday = new DateTime('monday this week');
$todayStr = (new DateTime('today'))->format('Y-m-d');
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $ymd = $d->format('Y-m-d');
    $weekDots[] = [
        'label'  => $weekDotLabels[$i],
        'done'   => isset($logDateSet[$ymd]) && $ymd <= $todayStr,
        'today'  => $ymd === $todayStr,
        'future' => $ymd > $todayStr,
    ];
}

// Latest coach note for sidebar
$latestCoachNote = db_get('SELECT body, created_at FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND from_member = 0 ORDER BY created_at DESC LIMIT 1', [$me['id']]);

// Glucose delta insight (last 7 days)
$glucoseData = db_all('SELECT log_date, bs_before, bs_after FROM daily_logs WHERE lead_id = ? AND bs_before IS NOT NULL AND bs_after IS NOT NULL ORDER BY log_date DESC LIMIT 7', [$me['id']]);

$pageTitle = 'Daily check-ins — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'logs';
require __DIR__ . '/includes/header.php';
$csrf = csrf_input();
?>
<style>
/* Checkin-specific additions on top of portal-v2.css */
.ci-layout{display:grid;grid-template-columns:minmax(0,1.55fr) 340px;gap:24px;align-items:start;margin-bottom:36px}
@media(max-width:1100px){.ci-layout{grid-template-columns:1fr}}

.ci-main{background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:0 1px 0 rgba(27,32,28,.04), 0 8px 24px -12px rgba(27,32,28,.12);overflow:hidden}
.ci-head{padding:28px 32px 18px;border-bottom:1px solid var(--line);position:relative}
.ci-head .ci-eyebrow{font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:8px}
.ci-head .ci-eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--sage);box-shadow:0 0 0 4px var(--sage-tint)}
.ci-head h1{font-family:"Instrument Serif",serif;font-size:38px;line-height:1.05;letter-spacing:-.02em;margin:0 0 6px;font-weight:400}
.ci-head h1 em{font-style:italic;color:var(--sage-2)}
.ci-head p{margin:0;color:var(--muted);font-size:14px}
.ci-date-chip{position:absolute;right:32px;top:28px;display:flex;align-items:center;gap:10px;padding:8px 12px 8px 8px;border:1px solid var(--line);border-radius:999px;background:var(--bg);font-size:13px;font-weight:500;color:var(--ink-2)}
.ci-date-chip .cal{width:26px;height:26px;border-radius:8px;background:var(--card);border:1px solid var(--line);display:grid;place-items:center;font-family:"Instrument Serif",serif;font-size:14px;color:var(--sage-2)}
.ci-date-chip input[type=date]{border:0;background:transparent;font-weight:500;outline:none;font-size:13px;color:var(--ink-2);cursor:pointer}
.ci-progress{display:flex;gap:6px;margin-top:18px}
.ci-progress span{flex:1;height:4px;border-radius:99px;background:var(--line)}
.ci-progress span.on{background:var(--sage)}

.ci-sections{padding:6px 32px 24px}
.ci-section{padding:24px 0;border-bottom:1px dashed var(--line)}
.ci-section:last-child{border-bottom:0}
.ci-sec-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:14px}
.ci-sec-title{display:flex;align-items:center;gap:10px}
.ci-sec-title .ix{width:22px;height:22px;border-radius:6px;background:var(--bg);color:var(--ink-2);display:grid;place-items:center;font-size:11px;font-weight:700;letter-spacing:.02em;border:1px solid var(--line);flex-shrink:0}
.ci-sec-title h2{margin:0;font-size:17px;font-weight:600;letter-spacing:-.005em}
.ci-sec-help{color:var(--muted);font-size:13px}
.ci-sec-sub{color:var(--muted);font-size:13.5px;margin:-6px 0 14px;max-width:60ch}
label.ci-field{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--ink-2)}

/* Mood pills */
.ci-mood-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
@media(max-width:700px){.ci-mood-row{grid-template-columns:1fr 1fr}}
.ci-mood{
  appearance:none;-webkit-appearance:none;
  border:1px solid var(--line);background:var(--card);
  border-radius:14px;padding:14px 12px 12px;text-align:left;
  display:flex;flex-direction:column;gap:10px;transition:.15s;cursor:pointer;
}
.ci-mood:hover{border-color:var(--line-2);transform:translateY(-1px)}
.ci-mood[aria-pressed="true"]{border-color:var(--sage);background:var(--sage-tint);box-shadow:inset 0 0 0 1px var(--sage)}
.ci-mood .face{width:34px;height:34px;border-radius:50%;background:var(--bg);display:grid;place-items:center;border:1px solid var(--line)}
.ci-mood[aria-pressed="true"] .face{background:#fff;border-color:var(--sage)}
.ci-mood .lbl{font-weight:600;font-size:14px}
.ci-mood .sub{color:var(--muted);font-size:11.5px;line-height:1.3}

/* Yes/No toggle */
.ci-toggle{display:inline-flex;background:var(--bg);border:1px solid var(--line);border-radius:999px;padding:4px;gap:2px}
.ci-toggle button{border:0;background:transparent;padding:7px 16px;border-radius:999px;font-weight:600;font-size:13.5px;color:var(--ink-2)}
.ci-toggle button.on{background:var(--ink);color:#F4F1E9}

/* Chips */
.ci-chips{display:flex;flex-wrap:wrap;gap:8px}
.ci-chip{
  border:1px solid var(--line);background:var(--card);border-radius:999px;
  padding:8px 14px;font-size:13px;font-weight:500;color:var(--ink-2);
  display:inline-flex;align-items:center;gap:6px;transition:.12s;cursor:pointer;
}
.ci-chip:hover{border-color:var(--line-2)}
.ci-chip[aria-pressed="true"]{background:var(--ink);border-color:var(--ink);color:#F4F1E9}

/* Textareas / inputs */
.ci-ta,.ci-input{
  width:100%;border:1px solid var(--line);background:var(--bg);
  border-radius:12px;padding:12px 14px;font-size:14px;color:var(--ink);outline:none;transition:.15s;
  font-family:inherit;
}
.ci-ta:focus,.ci-input:focus{background:#fff;border-color:var(--sage);box-shadow:0 0 0 4px var(--sage-tint)}
.ci-ta{min-height:84px;resize:vertical}
.ci-hint{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.ci-hint button{border:1px dashed var(--line-2);background:transparent;border-radius:8px;padding:5px 10px;font-size:12px;color:var(--muted);font-weight:500;cursor:pointer}
.ci-hint button:hover{color:var(--ink);border-color:var(--ink-2);border-style:solid}

/* Soreness dots */
.ci-dots{display:grid;grid-template-columns:repeat(11,1fr);gap:4px}
.ci-dot-btn{
  aspect-ratio:1;border:1px solid var(--line);background:var(--card);
  border-radius:8px;display:grid;place-items:center;font-size:12px;font-weight:600;color:var(--ink-2);
  transition:.12s;cursor:pointer;
}
.ci-dot-btn:hover{border-color:var(--ink-2)}
.ci-dot-btn.active{background:var(--ink);border-color:var(--ink);color:#F4F1E9}
.ci-dot-btn.in-range{background:var(--sage-tint);border-color:var(--sage-tint);color:var(--sage-2)}
.ci-dots-meta{display:flex;justify-content:space-between;color:var(--muted);font-size:11.5px;margin-top:6px}

/* Glucose */
.ci-gluc-grid{display:grid;grid-template-columns:1fr 50px 1fr 1fr;gap:14px;align-items:end}
@media(max-width:700px){.ci-gluc-grid{grid-template-columns:1fr 1fr}.ci-gluc-arrow{display:none}}
.ci-num-input{position:relative}
.ci-num-input .ci-input{font-family:"Instrument Serif",serif;font-size:32px;font-weight:400;letter-spacing:-.02em;padding:14px 52px 14px 14px;background:#fff}
.ci-num-input .unit{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:11px;font-weight:600;letter-spacing:.04em}
.ci-gluc-arrow{align-self:center;justify-self:center;padding-bottom:14px;color:var(--muted)}
.ci-delta{
  display:flex;flex-direction:column;justify-content:flex-end;padding:14px 14px 14px 16px;
  border:1px dashed var(--line-2);border-radius:12px;background:var(--bg);min-height:72px;
}
.ci-delta .lbl{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600}
.ci-delta .val{font-family:"Instrument Serif",serif;font-size:26px;letter-spacing:-.01em;margin-top:2px;color:var(--sage-2)}
.ci-delta.warn .val{color:#C66B5B}
.ci-delta.flat .val{color:var(--ink-2)}
.ci-trend-pills{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap}
.ci-trend-pills .ci-chip{padding:6px 12px;font-size:12.5px}

/* Food cards */
.ci-food-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:700px){.ci-food-grid{grid-template-columns:1fr}}
.ci-food-card{border:1px solid var(--line);border-radius:14px;padding:14px;background:#FCFAF4}
.ci-food-card .ttl{display:flex;align-items:center;gap:8px;font-weight:600;font-size:13.5px;margin-bottom:10px}
.ci-food-card .ttl .pip{width:8px;height:8px;border-radius:50%}
.ci-food-card .ttl.before .pip{background:var(--amber)}
.ci-food-card .ttl.after .pip{background:var(--sage)}
.ci-food-card .ci-ta{background:#fff;min-height:60px}

/* Save bar */
.ci-save-bar{
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  padding:18px 32px;background:linear-gradient(180deg,#FFFDF7,#F4F1E9);border-top:1px solid var(--line);flex-wrap:wrap;
}
.ci-save-meta{display:flex;gap:14px;align-items:center;color:var(--muted);font-size:13px}
.ci-save-meta .pip{width:8px;height:8px;border-radius:50%;background:var(--sage);box-shadow:0 0 0 4px var(--sage-tint)}
.ci-btn-ghost{border:1px solid var(--line);background:transparent;border-radius:999px;padding:10px 16px;font-weight:500;font-size:13.5px;color:var(--ink-2);cursor:pointer}
.ci-btn-ghost:hover{border-color:var(--line-2)}
.ci-btn-primary{
  background:var(--ink);color:#F4F1E9;border:0;border-radius:999px;padding:13px 22px;
  font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:10px;cursor:pointer;
  box-shadow:0 1px 0 rgba(0,0,0,.1),0 10px 20px -10px rgba(0,0,0,.4);
}
.ci-btn-primary:hover{background:var(--sage-2)}
.ci-btn-primary .k{font-family:"JetBrains Mono",monospace;font-size:11px;background:rgba(244,241,233,.18);padding:3px 6px;border-radius:5px}

/* Sidebar */
.ci-side{display:flex;flex-direction:column;gap:16px;position:sticky;top:20px}
@media(max-width:1100px){.ci-side{position:static}}
.ci-card{background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:0 1px 0 rgba(27,32,28,.04), 0 8px 24px -12px rgba(27,32,28,.12);padding:20px}
.ci-card.dark{background:#1B201C;color:#EDE8DC;border-color:#2A302B}
.ci-card.dark .ci-muted{color:#9CA399}

/* Streak */
.ci-streak{display:flex;align-items:flex-end;justify-content:space-between;gap:14px}
.ci-streak .num{font-family:"Instrument Serif",serif;font-size:62px;line-height:.95;letter-spacing:-.02em}
.ci-streak .num em{font-style:italic;color:var(--amber)}
.ci-streak .ttl{font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:600;color:#B5BBB1;margin-bottom:6px}
.ci-streak .sub{color:#9CA399;font-size:12.5px;margin-top:4px;line-height:1.4}
.ci-week-dots{display:flex;gap:6px;margin-top:14px}
.ci-week-dots div{flex:1;height:32px;border-radius:8px;background:rgba(255,255,255,.06);display:grid;place-items:center;font-size:10.5px;color:#9CA399;font-weight:500}
.ci-week-dots div.done{background:var(--sage);color:#fff}
.ci-week-dots div.today{background:transparent;border:1px dashed #4B5450;color:#EDE8DC}

/* Insight */
.ci-insight-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.ci-insight-head h3{margin:0;font-family:"Instrument Serif",serif;font-size:22px;font-weight:400;letter-spacing:-.01em}
.ci-insight-head .tag{font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:600;color:var(--sage-2);background:var(--sage-tint);padding:4px 8px;border-radius:6px}
.ci-insight p{margin:0 0 10px;color:var(--ink-2);font-size:13.5px;line-height:1.55}
.ci-insight p em{font-style:italic;color:var(--sage-2)}
.ci-mini-chart{margin-top:10px;border-top:1px dashed var(--line);padding-top:12px}
.ci-mini-chart .lbl{display:flex;justify-content:space-between;color:var(--muted);font-size:12px;font-weight:500;margin-bottom:6px}
.ci-bars{display:flex;align-items:flex-end;gap:6px;height:52px}
.ci-bars div{flex:1;background:var(--sage-tint);border-radius:4px 4px 2px 2px;position:relative}
.ci-bars div::after{content:attr(data-day);position:absolute;bottom:-16px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--muted)}
.ci-bars div.today{background:var(--sage)}
.ci-bars div.high{background:#F5DDD6}

/* Coach note */
.ci-note{background:linear-gradient(180deg,#FFFDF7,#F4F1E9);border:1px solid var(--line)}
.ci-note .who{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.ci-note .who .av{width:30px;height:30px;border-radius:50%;background:var(--ink);color:#F4F1E9;display:grid;place-items:center;font-size:11px;font-weight:700}
.ci-note .who .name{font-weight:600;font-size:13px}
.ci-note .who .ttl{color:var(--muted);font-size:11.5px}
.ci-note blockquote{margin:0;font-family:"Instrument Serif",serif;font-size:18px;line-height:1.35;font-style:italic;color:var(--ink-2);letter-spacing:-.005em}
.ci-note .sig{margin-top:10px;color:var(--muted);font-size:12px;display:flex;align-items:center;gap:6px}
.ci-note .sig::before{content:"";display:block;width:18px;height:1px;background:var(--muted)}

/* History section */
.ci-hist-entry{background:var(--card);border-radius:14px;border:1px solid var(--line);padding:16px 18px}
</style>

<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>

    <div style="margin-bottom:24px">
      <p class="eyebrow sage">Daily</p>
      <h1 class="h2 serif" style="margin-top:4px">How did <em style="color:var(--sage-2)">today</em> treat you?</h1>
      <p style="color:var(--muted);font-size:14px;margin-top:6px">A quick log of your training, glucose and meals. Future-you will thank you.</p>
    </div>

    <!-- ======= 2-column check-in layout ======= -->
    <div class="ci-layout">

      <!-- ======= MAIN FORM CARD ======= -->
      <div class="ci-main">
        <div class="ci-head">
          <div class="ci-eyebrow"><span class="dot"></span> New check-in · about 90 seconds</div>
          <h1>How did <em>today</em> treat you?</h1>
          <p>A quick log of your training, glucose and meals. Future-you will thank you.</p>

          <div class="ci-date-chip">
            <span class="cal"><?= date('j') ?></span>
            <input type="date" id="ci-date-input" value="<?= date('Y-m-d') ?>" aria-label="Log date" form="ci-form" name="log_date" />
          </div>

          <div class="ci-progress" aria-hidden="true" id="ci-progress">
            <span></span><span></span><span></span><span></span><span></span>
          </div>
        </div>

        <form id="ci-form" method="post" action="/save_log" class="ci-sections">
          <?= $csrf ?>
          <input type="hidden" name="log_date" id="ci-date-hidden" value="<?= date('Y-m-d') ?>" />
          <input type="hidden" name="feeling" id="ci-feeling" value="" />
          <input type="hidden" name="trained" id="ci-trained" value="Yes" />
          <input type="hidden" name="train_where" id="ci-where" value="" />
          <input type="hidden" name="soreness" id="ci-soreness-val" value="0" />
          <input type="hidden" name="bs_trend" id="ci-bs-trend" value="" />

          <!-- 1. MOOD -->
          <section class="ci-section">
            <div class="ci-sec-head">
              <div class="ci-sec-title"><span class="ix">01</span><h2>How are you feeling?</h2></div>
              <div class="ci-sec-help">Tap one. Trust your gut.</div>
            </div>
            <div class="ci-mood-row" id="ci-moodRow">
              <button type="button" class="ci-mood" data-mood="Rough">
                <span class="face"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6 13c1-1.2 2.3-1.8 4-1.8s3 .6 4 1.8" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg></span>
                <div><div class="lbl">Rough</div><div class="sub">Low energy, off pace</div></div>
              </button>
              <button type="button" class="ci-mood" data-mood="Okay">
                <span class="face"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6.5 12.5h7" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg></span>
                <div><div class="lbl">Okay</div><div class="sub">Steady, nothing to report</div></div>
              </button>
              <button type="button" class="ci-mood" data-mood="Good">
                <span class="face"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#7A8278" stroke-width="1.5"/><path d="M6 11c.8 1.2 2.2 2 4 2s3.2-.8 4-2" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="8" r="1" fill="#7A8278"/><circle cx="13" cy="8" r="1" fill="#7A8278"/></svg></span>
                <div><div class="lbl">Good</div><div class="sub">Strong, focused</div></div>
              </button>
              <button type="button" class="ci-mood" data-mood="Great" aria-pressed="true">
                <span class="face"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#3B6E54" stroke-width="1.5"/><path d="M5.5 10.5c1 2 2.6 3 4.5 3s3.5-1 4.5-3" stroke="#3B6E54" stroke-width="1.5" stroke-linecap="round"/><path d="M6 7.5l1.5 1M14 7.5l-1.5 1" stroke="#3B6E54" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                <div><div class="lbl">Great</div><div class="sub">PR-day energy</div></div>
              </button>
            </div>
          </section>

          <!-- 2. TRAINING -->
          <section class="ci-section">
            <div class="ci-sec-head">
              <div class="ci-sec-title"><span class="ix">02</span><h2>Training</h2></div>
              <div class="ci-sec-help">Rest days count too.</div>
            </div>

            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;flex-wrap:wrap">
              <div class="ci-toggle" role="group" aria-label="Did you train">
                <button type="button" class="on" id="ci-trainYes">Yes, I trained</button>
                <button type="button" id="ci-trainNo">Rest day</button>
              </div>
              <span style="color:var(--muted);font-size:13px">·</span>
              <div class="ci-chips" id="ci-locChips">
                <button type="button" class="ci-chip">Gym</button>
                <button type="button" class="ci-chip">Home</button>
                <button type="button" class="ci-chip">Outdoors</button>
                <button type="button" class="ci-chip">Studio</button>
                <button type="button" class="ci-chip">+ Other</button>
              </div>
            </div>

            <label class="ci-field" for="ci-workout">What did you do? <span style="color:var(--muted);font-weight:400">— sets, reps, distance, anything</span></label>
            <textarea id="ci-workout" name="workout" class="ci-ta" placeholder="e.g. Squats 4×8 @ 135 lb · Lat pulldown 3×10 · 15 min incline walk"></textarea>
            <div class="ci-hint">
              <button type="button">+ Lower body day</button>
              <button type="button">+ Upper push</button>
              <button type="button">+ 30 min zone 2</button>
              <button type="button">+ Yoga 45m</button>
            </div>

            <div style="margin-top:18px">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <label class="ci-field" style="margin:0">Muscle soreness</label>
                <span style="font-family:'JetBrains Mono',monospace;color:var(--sage-2);font-weight:500;font-size:13px" id="ci-soreVal">0 / 10 · none</span>
              </div>
              <div class="ci-dots" id="ci-soreDots"></div>
              <div class="ci-dots-meta"><span>None</span><span>Manageable</span><span>Wrecked</span></div>
            </div>
          </section>

          <!-- 3. BLOOD GLUCOSE -->
          <section class="ci-section">
            <div class="ci-sec-head">
              <div class="ci-sec-title"><span class="ix">03</span><h2>Blood glucose</h2></div>
              <div class="ci-sec-help">Before &amp; after your session</div>
            </div>
            <p class="ci-sec-sub">We'll calculate the delta and flag anything out of your usual range. Empty is fine — log what you have.</p>

            <div class="ci-gluc-grid">
              <div>
                <label class="ci-field">Before training</label>
                <div class="ci-num-input">
                  <input class="ci-input" id="ci-gBefore" name="bs_before" type="number" inputmode="numeric" placeholder="120" />
                  <span class="unit">mg/dL</span>
                </div>
              </div>
              <div class="ci-gluc-arrow" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><path d="M5 14h17m0 0l-5-5m5 5l-5 5" stroke="#7A8278" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <div>
                <label class="ci-field">After training</label>
                <div class="ci-num-input">
                  <input class="ci-input" id="ci-gAfter" name="bs_after" type="number" inputmode="numeric" placeholder="105" />
                  <span class="unit">mg/dL</span>
                </div>
              </div>
              <div class="ci-delta flat" id="ci-delta">
                <div class="lbl">Change</div>
                <div class="val" id="ci-deltaVal">—</div>
              </div>
            </div>

            <div class="ci-trend-pills" id="ci-trendPills" style="margin-top:14px">
              <span style="font-size:12.5px;color:var(--muted);align-self:center;margin-right:4px">Trend felt:</span>
              <button type="button" class="ci-chip">Stable</button>
              <button type="button" class="ci-chip">Rising slowly</button>
              <button type="button" class="ci-chip">Falling slowly</button>
              <button type="button" class="ci-chip">Spike</button>
              <button type="button" class="ci-chip">Crash</button>
            </div>
          </section>

          <!-- 4. FOOD -->
          <section class="ci-section">
            <div class="ci-sec-head">
              <div class="ci-sec-title"><span class="ix">04</span><h2>What fueled you</h2></div>
              <div class="ci-sec-help">Keep it loose — vibes count</div>
            </div>
            <div class="ci-food-grid">
              <div class="ci-food-card">
                <div class="ttl before"><span class="pip"></span> Before training</div>
                <textarea name="food_before" class="ci-ta" placeholder="e.g. oatmeal + banana, black coffee"></textarea>
                <div class="ci-hint">
                  <button type="button">+ Oats &amp; berries</button>
                  <button type="button">+ Toast &amp; PB</button>
                  <button type="button">+ Just coffee</button>
                </div>
              </div>
              <div class="ci-food-card">
                <div class="ttl after"><span class="pip"></span> After training</div>
                <textarea name="food_after" class="ci-ta" placeholder="e.g. chicken, rice, salad"></textarea>
                <div class="ci-hint">
                  <button type="button">+ Chicken + rice</button>
                  <button type="button">+ Protein shake</button>
                  <button type="button">+ Eggs &amp; greens</button>
                </div>
              </div>
            </div>
          </section>

          <!-- 5. NOTES -->
          <section class="ci-section">
            <div class="ci-sec-head">
              <div class="ci-sec-title"><span class="ix">05</span><h2>Anything else?</h2></div>
              <div class="ci-sec-help">Optional — but rich data later</div>
            </div>
            <textarea name="notes" class="ci-ta" style="min-height:96px" placeholder="Energy, mood, sleep, medication changes, stress, life stuff…"></textarea>
            <div class="ci-hint">
              <button type="button">+ Slept 7h</button>
              <button type="button">+ Stress: high</button>
              <button type="button">+ Forgot Metformin</button>
              <button type="button">+ Period day 2</button>
              <button type="button">+ Hot outside</button>
            </div>
          </section>

        </form>

        <!-- Save bar -->
        <div class="ci-save-bar">
          <div class="ci-save-meta">
            <span class="pip"></span>
            <span id="ci-touched-count">Fill in your check-in above</span>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button type="submit" form="ci-form" class="ci-btn-primary">
              Log today's check-in <span class="k">⏎</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ======= SIDEBAR ======= -->
      <aside class="ci-side">

        <!-- Streak card -->
        <div class="ci-card dark">
          <div class="ci-streak">
            <div>
              <div class="ttl">Current streak</div>
              <div class="num"><?= $streak ?><em>d</em></div>
              <div class="sub">
                <?php if ($streak === 0): ?>
                  Log today to start a streak!
                <?php elseif ($streak === 1): ?>
                  Great start. Log tomorrow to build!
                <?php else: ?>
                  Keep it going — <?= $streak ?> days and counting.
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="ci-week-dots" aria-hidden="true">
            <?php foreach ($weekDots as $dot): ?>
              <div class="<?= $dot['done'] ? 'done' : ($dot['today'] ? 'today' : '') ?>"><?= e($dot['label']) ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Insight card -->
        <div class="ci-card ci-insight">
          <div class="ci-insight-head">
            <h3>Today's read</h3>
            <?php if ($glucoseData): ?>
              <span class="tag">Glucose tracked</span>
            <?php else: ?>
              <span class="tag">Log to see</span>
            <?php endif; ?>
          </div>
          <?php if ($glucoseData): ?>
            <?php
            $deltas = [];
            foreach ($glucoseData as $g) {
                if ($g['bs_before'] && $g['bs_after']) {
                    $deltas[] = $g['bs_after'] - $g['bs_before'];
                }
            }
            $avgDelta = $deltas ? round(array_sum($deltas) / count($deltas)) : 0;
            ?>
            <p>Your average glucose change after training is <em><?= $avgDelta >= 0 ? '+' . $avgDelta : $avgDelta ?> mg/dL</em> over the last <?= count($glucoseData) ?> logged sessions.</p>
          <?php else: ?>
            <p>Log your blood glucose before and after your first session and we'll start tracking your trends here.</p>
          <?php endif; ?>

          <div class="ci-mini-chart">
            <div class="lbl"><span>Glucose delta · last 7 days</span><span style="font-family:'JetBrains Mono',monospace">mg/dL</span></div>
            <div class="ci-bars">
              <?php
              $dayAbbrMap = ['Sun'=>'S','Mon'=>'M','Tue'=>'T','Wed'=>'W','Thu'=>'T','Fri'=>'F','Sat'=>'S'];
              $maxAbs = 1;
              foreach ($glucoseData as $g) {
                  if ($g['bs_before'] && $g['bs_after']) {
                      $maxAbs = max($maxAbs, abs($g['bs_after'] - $g['bs_before']));
                  }
              }
              $glucByDate = [];
              foreach ($glucoseData as $g) {
                  $glucByDate[$g['log_date']] = ($g['bs_before'] && $g['bs_after']) ? abs($g['bs_after'] - $g['bs_before']) : 0;
              }
              for ($i = 6; $i >= 0; $i--):
                  $d = new DateTime("today -$i days");
                  $ymd = $d->format('Y-m-d');
                  $abbr = substr($d->format('D'), 0, 1);
                  $val = $glucByDate[$ymd] ?? 0;
                  $pct = $maxAbs > 0 ? max(8, round($val / $maxAbs * 100)) : 8;
                  $isToday = $i === 0;
              ?>
              <div class="<?= $isToday ? 'today' : ($val > 40 ? 'high' : '') ?>" style="height:<?= $pct ?>%" data-day="<?= $abbr ?>"></div>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <!-- Coach note card -->
        <div class="ci-card ci-note">
          <div class="who">
            <div class="av">D</div>
            <div>
              <div class="name">A note from your coach</div>
              <div class="ttl">From the Diafitus team</div>
            </div>
          </div>
          <blockquote>
            <?php if ($latestCoachNote): ?>
              "<?= e(mb_substr($latestCoachNote['body'], 0, 160)) ?><?= mb_strlen($latestCoachNote['body']) > 160 ? '…' : '' ?>"
            <?php else: ?>
              "Diabetes is logged. Fitness is felt. <em>Diafitus</em> is where you put it all down — and where it starts making sense."
            <?php endif; ?>
          </blockquote>
          <div class="sig">Your coach is reviewing your logs</div>
        </div>

      </aside>
    </div>

    <!-- ======= HISTORY ======= -->
    <?php if ($dailyLogs): ?>
    <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
      <div class="eyebrow">Previous check-ins</div>
      <span style="font-size:12px;color:var(--muted)"><?= count($dailyLogs) ?> entries</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:20px">
      <?php foreach ($grouped as $date => $items): ?>
        <div>
          <div style="font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:8px;letter-spacing:.02em"><?= e(date('l, M j, Y', strtotime($date))) ?></div>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($items as $l): ?>
              <article class="ci-hist-entry">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
                  <span style="font-size:12px;color:var(--muted)"><?= e(date('g:ia', strtotime($l['created_at']))) ?></span>
                  <?php if ($l['feeling']): ?><span class="chip" style="background:var(--sage-tint);color:var(--sage-2)"><?= e($l['feeling']) ?></span><?php endif; ?>
                  <?php if ($l['trained'] === 'Yes'): ?>
                    <span class="chip" style="background:#E8F1F8;color:#5C8AA8">Trained<?= $l['train_where'] ? ' · ' . e($l['train_where']) : '' ?></span>
                  <?php elseif ($l['trained'] === 'Partial'): ?>
                    <span class="chip" style="background:var(--amber-tint);color:var(--amber)">Partial workout</span>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:8px">
                  <?php if ($l['bs_before'] || $l['bs_after']): ?>
                    <div style="font-size:13px"><span style="color:var(--muted)">Glucose </span><strong><?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?></strong><?php if ($l['bs_before'] && $l['bs_after']): ?> <span style="font-size:11.5px;color:var(--muted)">(<?= ($l['bs_after'] - $l['bs_before'] >= 0 ? '+' : '') . ($l['bs_after'] - $l['bs_before']) ?>)</span><?php endif; ?></div>
                  <?php endif; ?>
                  <?php if ($l['soreness'] !== null): ?><div style="font-size:13px"><span style="color:var(--muted)">Soreness </span><strong><?= (int)$l['soreness'] ?>/10</strong></div><?php endif; ?>
                </div>
                <?php if ($l['workout']):     ?><p style="font-size:13px;margin:4px 0"><strong>Workout.</strong> <?= nl2br(e($l['workout'])) ?></p><?php endif; ?>
                <?php if ($l['food_before']): ?><p style="font-size:13px;margin:4px 0"><strong>Before.</strong> <?= e($l['food_before']) ?></p><?php endif; ?>
                <?php if ($l['food_after']):  ?><p style="font-size:13px;margin:4px 0"><strong>After.</strong> <?= e($l['food_after']) ?></p><?php endif; ?>
                <?php if ($l['notes']):       ?><p style="font-size:13px;color:var(--muted);margin:4px 0"><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>

                <?php $comments = $commentsByLog[$l['id']] ?? []; ?>
                <?php if ($comments): ?>
                  <div style="margin-top:12px;border-top:1px solid var(--line);padding-top:12px;display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($comments as $c):
                      $replies = $repliesByParent[$c['id']] ?? [];
                    ?>
                      <div style="background:var(--sage-tint);border-radius:9px;padding:12px 14px">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                          <span style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sage-2)">Coach</span>
                          <span style="font-size:11.5px;color:var(--muted)"><?= e(date('M j, g:ia', strtotime($c['created_at']))) ?></span>
                        </div>
                        <p style="font-size:13px;margin:0;color:var(--ink-2)"><?= nl2br(e($c['body'])) ?></p>
                        <?php foreach ($replies as $r): ?>
                          <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.4)">
                            <div style="font-size:11.5px;font-weight:600;margin-bottom:2px"><?= $r['from_member'] ? 'You' : 'Coach' ?> <span style="color:var(--muted);font-weight:400"><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></span></div>
                            <p style="font-size:13px;margin:0"><?= nl2br(e($r['body'])) ?></p>
                          </div>
                        <?php endforeach; ?>
                        <form method="post" action="/reply_note" style="display:flex;gap:8px;margin-top:10px">
                          <?= $csrf ?>
                          <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>" />
                          <input type="hidden" name="redirect" value="/logs" />
                          <input type="text" name="body" placeholder="Reply…" maxlength="2000" required style="flex:1;padding:7px 11px;border-radius:8px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.5);font-size:13px;font-family:inherit" />
                          <button type="submit" style="padding:7px 14px;border-radius:8px;border:1px solid var(--sage-2);background:var(--sage-2);color:#fff;font-size:13px;font-weight:600;cursor:pointer">Reply</button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</main>
</div>

<script>
(function(){
  var touchedSections = 0;

  // Mood toggle
  var moodRow = document.getElementById('ci-moodRow');
  var feelingInput = document.getElementById('ci-feeling');
  feelingInput.value = 'Great'; // default selected
  moodRow.addEventListener('click', function(e){
    var b = e.target.closest('.ci-mood'); if (!b) return;
    moodRow.querySelectorAll('.ci-mood').forEach(function(m){ m.setAttribute('aria-pressed','false'); });
    b.setAttribute('aria-pressed','true');
    feelingInput.value = b.dataset.mood;
    updateProgress();
  });

  // Train yes/no
  var ty = document.getElementById('ci-trainYes'), tn = document.getElementById('ci-trainNo');
  var trainedInput = document.getElementById('ci-trained');
  ty.onclick = function(){ ty.classList.add('on'); tn.classList.remove('on'); trainedInput.value='Yes'; };
  tn.onclick = function(){ tn.classList.add('on'); ty.classList.remove('on'); trainedInput.value='No'; };

  // Location chips
  var locChips = document.getElementById('ci-locChips');
  var whereInput = document.getElementById('ci-where');
  locChips.addEventListener('click', function(e){
    var c = e.target.closest('.ci-chip'); if (!c) return;
    locChips.querySelectorAll('.ci-chip').forEach(function(x){ x.setAttribute('aria-pressed','false'); });
    c.setAttribute('aria-pressed','true');
    whereInput.value = c.textContent.replace(/^\+\s*/,'').trim();
  });

  // Trend pills
  var trendPills = document.getElementById('ci-trendPills');
  var trendInput = document.getElementById('ci-bs-trend');
  trendPills.addEventListener('click', function(e){
    var c = e.target.closest('.ci-chip'); if (!c) return;
    trendPills.querySelectorAll('.ci-chip').forEach(function(x){ x.setAttribute('aria-pressed','false'); });
    c.setAttribute('aria-pressed','true');
    trendInput.value = c.textContent.trim();
  });

  // Soreness dots
  var sd = document.getElementById('ci-soreDots');
  var sv = document.getElementById('ci-soreVal');
  var soreInput = document.getElementById('ci-soreness-val');
  var soreLabels = ['none','barely','slight','mild','noticeable','moderate','meaningful','high','strong','intense','wrecked'];
  for (var i = 0; i <= 10; i++){
    (function(v){
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ci-dot-btn';
      b.textContent = v;
      b.dataset.v = v;
      sd.appendChild(b);
    })(i);
  }
  function setSore(v){
    sd.querySelectorAll('.ci-dot-btn').forEach(function(d){
      var dv = +d.dataset.v;
      d.classList.toggle('active', dv === v);
      d.classList.toggle('in-range', dv < v);
    });
    sv.textContent = v + ' / 10 · ' + soreLabels[v];
    soreInput.value = v;
  }
  sd.addEventListener('click', function(e){
    var b = e.target.closest('.ci-dot-btn'); if (!b) return;
    setSore(+b.dataset.v);
    updateProgress();
  });
  setSore(0);

  // Glucose delta
  var gb = document.getElementById('ci-gBefore'), ga = document.getElementById('ci-gAfter');
  var dEl = document.getElementById('ci-delta'), dv = document.getElementById('ci-deltaVal');
  function updateDelta(){
    var a = +gb.value, b = +ga.value;
    if (!a || !b){ dv.textContent = '—'; dEl.className='ci-delta flat'; return; }
    var d = b - a;
    dv.textContent = (d > 0 ? '+' : d < 0 ? '−' : '±') + Math.abs(d);
    dEl.className = 'ci-delta' + (Math.abs(d) > 40 ? ' warn' : d === 0 ? ' flat' : '');
    updateProgress();
  }
  gb.addEventListener('input', updateDelta);
  ga.addEventListener('input', updateDelta);

  // Date chip sync
  var dateInput = document.getElementById('ci-date-input');
  var dateHidden = document.getElementById('ci-date-hidden');
  var calNum = document.querySelector('.ci-date-chip .cal');
  dateInput.addEventListener('change', function(){
    dateHidden.value = dateInput.value;
    if (dateInput.value) {
      var d = new Date(dateInput.value + 'T00:00:00');
      calNum.textContent = d.getDate();
    }
  });
  // Remove duplicate date input (the hidden one handles it)
  dateInput.removeAttribute('name');

  // Hint chips append to nearest textarea
  document.querySelectorAll('.ci-hint').forEach(function(h){
    h.addEventListener('click', function(e){
      var btn = e.target.closest('button'); if (!btn) return;
      var ta = h.previousElementSibling && h.previousElementSibling.tagName === 'TEXTAREA'
        ? h.previousElementSibling
        : h.closest('.ci-food-card') ? h.closest('.ci-food-card').querySelector('textarea')
        : h.previousElementSibling;
      if (!ta || ta.tagName !== 'TEXTAREA') return;
      var txt = btn.textContent.replace(/^\+\s*/,'').trim();
      ta.value = ta.value ? (ta.value.replace(/[\s,]+$/,'') + ', ' + txt) : txt;
      ta.focus();
      updateProgress();
    });
  });

  // Progress bar
  var progressBar = document.getElementById('ci-progress');
  var touchedEl = document.getElementById('ci-touched-count');
  var filled = [false, false, false, false, false]; // mood, training, glucose, food, notes

  // Mark mood as filled (default Great is selected)
  filled[0] = true;

  document.getElementById('ci-workout').addEventListener('input', function(){ filled[1] = this.value.trim().length > 0; updateProgress(); });
  gb.addEventListener('input', function(){ filled[2] = gb.value || ga.value; updateProgress(); });
  ga.addEventListener('input', function(){ filled[2] = gb.value || ga.value; updateProgress(); });
  document.querySelector('[name="food_before"]').addEventListener('input', function(){ filled[3] = this.value.trim().length > 0; updateProgress(); });
  document.querySelector('[name="notes"]').addEventListener('input', function(){ filled[4] = this.value.trim().length > 0; updateProgress(); });

  function updateProgress(){
    var spans = progressBar.querySelectorAll('span');
    var count = filled.filter(Boolean).length;
    spans.forEach(function(s, i){ s.className = i < count ? 'on' : ''; });
    touchedEl.textContent = count + ' of 5 sections touched';
  }
  updateProgress();

})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
