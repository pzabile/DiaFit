<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$days = max(7, min(90, (int)($_GET['days'] ?? 30)));

// Last N daily logs
$glucLogs = db_all(
    'SELECT log_date, bs_before, bs_after, feeling, soreness, trained
     FROM daily_logs WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY log_date ASC',
    [$leadId, $days]
);

// Last 12 weekly notes
$weeklyNotes = db_all(
    'SELECT week_number, avg_glucose, weight_kg, energy_rating FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC LIMIT 12',
    [$leadId]
);
$weeklyNotes = array_reverse($weeklyNotes);

// Compute avg glucose, TIR, etc.
$allReadings = [];
$inRange = 0; $low = 0; $high = 0;
foreach ($glucLogs as $r) {
    foreach (['bs_before','bs_after'] as $col) {
        if ($r[$col] !== null && $r[$col] !== '') {
            $v = (int)$r[$col];
            $allReadings[] = $v;
            if ($v < 70) $low++;
            elseif ($v > 180) $high++;
            else $inRange++;
        }
    }
}
$totalReadings = count($allReadings);
$avgGlucose    = $totalReadings ? round(array_sum($allReadings) / $totalReadings) : null;
$tirPct        = $totalReadings ? round($inRange / $totalReadings * 100) : 0;
$lowPct        = $totalReadings ? round($low / $totalReadings * 100) : 0;
$highPct       = $totalReadings ? round($high / $totalReadings * 100) : 0;

// Build SVG chart data for main glucose chart (last 7 days)
// viewBox="0 0 800 240", plot area: x=40..780, y=20..220
// Glucose range: 60–200 → y=220..20
function glucoseToY($g) {
    $minG = 60; $maxG = 200;
    $minY = 20; $maxY = 220;
    $g = max($minG, min($maxG, (int)$g));
    return round($maxY - (($g - $minG) / ($maxG - $minG)) * ($maxY - $minY), 1);
}

$last7 = array_slice($glucLogs, -7);
$chartPoints = [];
$xLabels = [];
if (count($last7) > 0) {
    $n = count($last7);
    $xStep = $n > 1 ? 740 / ($n - 1) : 740;
    foreach ($last7 as $i => $row) {
        $x = round(40 + ($n > 1 ? $i * $xStep : 370), 1);
        $vals = [];
        if ($row['bs_before']) $vals[] = (int)$row['bs_before'];
        if ($row['bs_after'])  $vals[] = (int)$row['bs_after'];
        if ($vals) {
            $avg = array_sum($vals) / count($vals);
            $y = glucoseToY($avg);
            $chartPoints[] = ['x'=>$x,'y'=>$y,'date'=>$row['log_date'],'val'=>round($avg)];
            $xLabels[] = ['x'=>$x,'label'=>strtoupper(date('D j', strtotime($row['log_date'])))];
        }
    }
}

// Build SVG path from chart points
$linePath = '';
$areaPath = '';
if (count($chartPoints) >= 2) {
    $pts = array_map(fn($p) => $p['x'].','.$p['y'], $chartPoints);
    $linePath = 'M' . implode(' L', $pts);
    $first = $chartPoints[0];
    $last  = $chartPoints[count($chartPoints)-1];
    $areaPath = $linePath . " L{$last['x']},230 L{$first['x']},230 Z";
} elseif (count($chartPoints) === 1) {
    $p = $chartPoints[0];
    $linePath = "M{$p['x']},{$p['y']}";
}

// Weight chart points (viewBox="0 0 400 160")
// weight range: derive from data
$weightPoints = [];
if ($weeklyNotes) {
    $weights = array_filter(array_column($weeklyNotes, 'weight_kg'), fn($w) => $w !== null);
    if ($weights) {
        $minW = min($weights); $maxW = max($weights);
        $range = $maxW - $minW ?: 5;
        $n = count($weeklyNotes);
        $xStep = $n > 1 ? 380 / ($n - 1) : 380;
        $j = 0;
        foreach ($weeklyNotes as $wn) {
            if ($wn['weight_kg'] !== null) {
                $x = round($j * $xStep + 10, 1);
                $y = round(140 - (($wn['weight_kg'] - $minW) / $range) * 120, 1);
                $weightPoints[] = ['x'=>$x,'y'=>$y];
            }
            $j++;
        }
    }
}
$weightLinePath = '';
$weightAreaPath = '';
if (count($weightPoints) >= 2) {
    $pts = array_map(fn($p) => $p['x'].','.$p['y'], $weightPoints);
    $weightLinePath = 'M' . implode(' L', $pts);
    $first = $weightPoints[0]; $last = $weightPoints[count($weightPoints)-1];
    $weightAreaPath = $weightLinePath . " L{$last['x']},160 L{$first['x']},160 Z";
}

// Energy/soreness chart
$energyPoints = [];
$sorenessPoints = [];
$energyLogs = array_slice($glucLogs, -14);
if ($energyLogs) {
    $n = count($energyLogs);
    $xStep = $n > 1 ? 380 / ($n - 1) : 380;
    foreach ($energyLogs as $i => $row) {
        $x = round($i * $xStep + 10, 1);
        // We don't have energy in daily_logs, use feeling as proxy
        $feelMap = ['rough'=>2,'ok'=>5,'good'=>7,'great'=>10];
        $energyVal = $feelMap[$row['feeling'] ?? ''] ?? null;
        if ($energyVal !== null) {
            $y = round(140 - ($energyVal / 10) * 120, 1);
            $energyPoints[] = ['x'=>$x,'y'=>$y];
        }
        if ($row['soreness'] !== null) {
            $y = round(140 - ((int)$row['soreness'] / 10) * 120, 1);
            $sorenessPoints[] = ['x'=>$x,'y'=>$y];
        }
    }
}
$energyLinePath   = count($energyPoints)   >= 2 ? 'M' . implode(' L', array_map(fn($p) => $p['x'].','.$p['y'], $energyPoints))   : '';
$sorenessLinePath = count($sorenessPoints) >= 2 ? 'M' . implode(' L', array_map(fn($p) => $p['x'].','.$p['y'], $sorenessPoints)) : '';

// Training heatmap (last 12 weeks, Mon-Sun)
// Build a 7x12 grid
$hmRows = ['M'=>[], 'T'=>[], 'W'=>[], 'Th'=>[], 'F'=>[], 'Sa'=>[], 'Su'=>[]];
$dayMap = [1=>'M',2=>'T',3=>'W',4=>'Th',5=>'F',6=>'Sa',7=>'Su'];
// Get all logs with training data going back 84 days
$trainLogs = db_all(
    'SELECT log_date, trained FROM daily_logs WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 84 DAY) ORDER BY log_date ASC',
    [$leadId]
);
$trainByDate = [];
foreach ($trainLogs as $tl) {
    $trainByDate[$tl['log_date']] = $tl['trained'] === 'Yes' ? 3 : 1;
}
// 12 weeks back, Mon to Sun
$weekStartCur = new DateTime('monday this week');
for ($wk = 11; $wk >= 0; $wk--) {
    $ws = (clone $weekStartCur)->modify("-{$wk} weeks");
    foreach ([1,2,3,4,5,6,7] as $dow) {
        $d = (clone $ws)->modify('+' . ($dow-1) . ' days')->format('Y-m-d');
        $dayKey = $dayMap[$dow];
        $hmRows[$dayKey][] = $trainByDate[$d] ?? 0;
    }
}

// Auto-insights
$insights = [];
if ($avgGlucose !== null) {
    if ($avgGlucose < 100) {
        $insights[] = ['icon'=>'↓','type'=>'sage','ttl'=>"Excellent glucose control — avg {$avgGlucose} mg/dL",'sub'=>'Well within target range. Keep the current pattern.'];
    } elseif ($avgGlucose > 140) {
        $insights[] = ['icon'=>'↑','type'=>'amber','ttl'=>"Avg glucose above target at {$avgGlucose} mg/dL",'sub'=>'Consider more post-meal walks and reviewing carb timing.'];
    } else {
        $insights[] = ['icon'=>'✓','type'=>'sage','ttl'=>"Avg glucose in target range at {$avgGlucose} mg/dL",'sub'=>'Solid baseline. Look for patterns on high-carb days.'];
    }
}
$trainedDays = count(array_filter($trainLogs, fn($l) => $l['trained'] === 'Yes'));
$totalDays   = count($trainLogs);
if ($totalDays > 0) {
    $trainPct = round($trainedDays / $totalDays * 100);
    $insights[] = ['icon'=>'⚡','type'=>'flat','ttl'=>"Trained {$trainPct}% of logged days",'sub'=>"{$trainedDays} sessions in {$totalDays} logged days."];
}
if ($tirPct > 0) {
    $tirType = $tirPct >= 80 ? 'sage' : ($tirPct >= 60 ? 'amber' : 'coral');
    $insights[] = ['icon'=>'%','type'=>$tirType,'ttl'=>"{$tirPct}% of readings in range (70–180 mg/dL)",'sub'=>"{$inRange} in range · {$low} low · {$high} high out of {$totalReadings} readings."];
}

$pageTitle  = 'Trends — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'trends';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Trends</span>
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
    <div class="row" style="align-items:flex-end;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:14px">
      <div>
        <div class="eyebrow">Progress</div>
        <h1 class="h1">The story your <em>data</em> is telling.</h1>
        <p class="muted" style="margin:0;max-width:60ch">Patterns get clearer as you log more days.</p>
      </div>
      <div class="trend-tabs">
        <a href="?days=7"  class="chip<?= $days===7  ? '" aria-pressed="true' : '' ?>">7d</a>
        <a href="?days=30" class="chip<?= $days===30 ? '" aria-pressed="true' : '' ?>">30d</a>
        <a href="?days=90" class="chip<?= $days===90 ? '" aria-pressed="true' : '' ?>">90d</a>
      </div>
    </div>

    <!-- Big glucose chart -->
    <div class="chart-card" style="margin-bottom:18px">
      <div class="ch-head">
        <div>
          <h3>
            <span style="width:8px;height:8px;border-radius:50%;background:var(--sage);display:inline-block"></span>
            Glucose
          </h3>
          <div class="ch-sub">Time in range 70–180 mg/dL &middot; last <?= $days ?> days</div>
          <div class="ch-stats">
            <span class="ch-num"><?= $avgGlucose ?? '—' ?></span>
            <?php if ($avgGlucose): ?><span class="kpi-unit">mg/dL avg</span><?php endif; ?>
            <?php if ($tirPct > 0): ?>
              <span class="chip sage" style="margin-left:6px"><?= $tirPct ?>% in range</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($chartPoints): ?>
      <svg class="chart" viewBox="0 0 800 240" preserveAspectRatio="none">
        <!-- Target band 70–180: y=glucoseToY(70)=..glucoseToY(180) -->
        <rect x="40" y="<?= glucoseToY(180) ?>" width="740" height="<?= glucoseToY(70) - glucoseToY(180) ?>" fill="#E6EFE6" opacity=".55"/>
        <line x1="40" y1="<?= glucoseToY(180) ?>" x2="780" y2="<?= glucoseToY(180) ?>" stroke="#9CC9A8" stroke-dasharray="3 4" stroke-width="1"/>
        <line x1="40" y1="<?= glucoseToY(70)  ?>" x2="780" y2="<?= glucoseToY(70)  ?>" stroke="#9CC9A8" stroke-dasharray="3 4" stroke-width="1"/>
        <!-- Gridlines -->
        <g stroke="#E2DCCD" stroke-width="1">
          <line x1="40" y1="<?= glucoseToY(200) ?>" x2="780" y2="<?= glucoseToY(200) ?>"/>
          <line x1="40" y1="<?= glucoseToY(160) ?>" x2="780" y2="<?= glucoseToY(160) ?>"/>
          <line x1="40" y1="<?= glucoseToY(120) ?>" x2="780" y2="<?= glucoseToY(120) ?>"/>
          <line x1="40" y1="<?= glucoseToY(80)  ?>" x2="780" y2="<?= glucoseToY(80)  ?>"/>
        </g>
        <!-- Y labels -->
        <g font-family="JetBrains Mono" font-size="10" fill="#9AA197">
          <text x="2" y="<?= glucoseToY(200)+4 ?>">200</text>
          <text x="2" y="<?= glucoseToY(160)+4 ?>">160</text>
          <text x="2" y="<?= glucoseToY(180)+4 ?>">180</text>
          <text x="2" y="<?= glucoseToY(80)+4 ?>">80</text>
        </g>
        <!-- X labels -->
        <g font-family="JetBrains Mono" font-size="10" fill="#9AA197" text-anchor="middle">
          <?php foreach ($xLabels as $xl): ?>
            <text x="<?= $xl['x'] ?>" y="235"><?= e($xl['label']) ?></text>
          <?php endforeach; ?>
        </g>
        <!-- Area + line -->
        <?php if ($areaPath): ?>
        <path d="<?= e($areaPath) ?>" fill="#4A8A68" opacity=".09"/>
        <?php endif; ?>
        <path d="<?= e($linePath) ?>" fill="none" stroke="#4A8A68" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <!-- Data points -->
        <?php foreach ($chartPoints as $i => $pt): ?>
          <?php $isLast = $i === count($chartPoints)-1; ?>
          <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="<?= $isLast ? '5' : '3' ?>" fill="<?= ($pt['val'] < 70 || $pt['val'] > 180) ? '#C66B5B' : '#4A8A68' ?>"/>
        <?php endforeach; ?>
      </svg>
      <div style="display:flex;gap:18px;justify-content:flex-start;font-size:11.5px;color:var(--muted);margin-top:8px;flex-wrap:wrap">
        <span><span style="display:inline-block;width:14px;height:6px;background:#E6EFE6;vertical-align:middle;margin-right:6px;border-radius:2px"></span>Target band (70–180)</span>
        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#C66B5B;vertical-align:middle;margin-right:6px"></span>Out-of-range reading</span>
      </div>
      <?php else: ?>
        <div style="padding:40px;text-align:center;color:var(--muted)">No glucose data yet. <a href="/portal/log" style="color:var(--sage-2);font-weight:600">Log your first check-in →</a></div>
      <?php endif; ?>
    </div>

    <div class="chart-grid">
      <!-- Time in range -->
      <div class="chart-card">
        <div class="ch-head">
          <div>
            <h3>Time in range</h3>
            <div class="ch-sub">% of readings in each band</div>
            <div class="ch-stats">
              <span class="ch-num"><?= $tirPct ?><span style="font-size:18px;color:var(--muted)">%</span></span>
            </div>
          </div>
        </div>
        <?php if ($totalReadings): ?>
        <div class="tir-bar">
          <div class="tir-low" style="width:<?= $lowPct ?>%"></div>
          <div class="tir-mid" style="width:<?= $tirPct ?>%"></div>
          <div class="tir-high" style="width:<?= $highPct ?>%"></div>
        </div>
        <div class="tir-legend">
          <div class="row" style="justify-content:space-between"><span><span class="sw" style="background:var(--coral)"></span>Low &lt; 70</span><span class="mono"><?= $lowPct ?>% &middot; <?= $low ?> readings</span></div>
          <div class="row" style="justify-content:space-between"><span><span class="sw" style="background:var(--sage)"></span>In range 70–180</span><span class="mono"><?= $tirPct ?>% &middot; <?= $inRange ?> readings</span></div>
          <div class="row" style="justify-content:space-between"><span><span class="sw" style="background:var(--amber)"></span>High &gt; 180</span><span class="mono"><?= $highPct ?>% &middot; <?= $high ?> readings</span></div>
        </div>
        <?php else: ?>
          <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">No readings yet</div>
        <?php endif; ?>
      </div>

      <!-- Weight chart -->
      <div class="chart-card">
        <div class="ch-head">
          <div>
            <h3>
              <span style="width:8px;height:8px;border-radius:50%;background:var(--amber);display:inline-block"></span>
              Weight
            </h3>
            <div class="ch-sub">From weekly reviews &middot; kg</div>
            <div class="ch-stats">
              <?php if ($weeklyNotes && !empty(array_filter(array_column($weeklyNotes,'weight_kg')))): ?>
                <?php $latestWeight = null; foreach (array_reverse($weeklyNotes) as $wn) { if ($wn['weight_kg']) { $latestWeight = $wn['weight_kg']; break; } } ?>
                <span class="ch-num"><?= $latestWeight ?></span><span class="kpi-unit">kg</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($weightLinePath): ?>
        <svg class="chart" viewBox="0 0 400 160" preserveAspectRatio="none">
          <g stroke="#E2DCCD" stroke-width="1">
            <line x1="0" y1="40" x2="400" y2="40"/><line x1="0" y1="80" x2="400" y2="80"/><line x1="0" y1="120" x2="400" y2="120"/>
          </g>
          <path d="<?= e($weightAreaPath) ?>" fill="#C68A2E" opacity=".12"/>
          <path d="<?= e($weightLinePath) ?>" fill="none" stroke="#C68A2E" stroke-width="2" stroke-linecap="round"/>
          <?php $lp = end($weightPoints); ?>
          <circle cx="<?= $lp['x'] ?>" cy="<?= $lp['y'] ?>" r="4" fill="#C68A2E"/>
        </svg>
        <?php else: ?>
          <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">Log weight in weekly reviews</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="chart-grid" style="margin-top:18px">
      <!-- Training heatmap -->
      <div class="chart-card">
        <div class="ch-head">
          <div>
            <h3>Training consistency</h3>
            <div class="ch-sub">Last 12 weeks &middot; Mon–Sun</div>
            <div class="ch-stats">
              <?php $avgPerWk = $totalDays > 0 ? round($trainedDays / 12, 1) : 0; ?>
              <span class="ch-num"><?= $avgPerWk ?></span><span class="kpi-unit">avg / wk</span>
            </div>
          </div>
        </div>
        <div style="margin-top:8px">
          <?php foreach ($hmRows as $dayKey => $arr): ?>
          <div class="heatmap-row">
            <div class="lbl"><?= $dayKey ?></div>
            <div class="heatmap">
              <?php foreach ($arr as $v): ?>
                <div data-v="<?= (int)$v ?>"></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:12px;font-size:11px;color:var(--muted);margin-top:10px;align-items:center">
          <span>Less</span>
          <span style="width:10px;height:10px;border-radius:2px;background:var(--bg-2);display:inline-block"></span>
          <span style="width:10px;height:10px;border-radius:2px;background:#D8E6D9;display:inline-block"></span>
          <span style="width:10px;height:10px;border-radius:2px;background:#A8CBB4;display:inline-block"></span>
          <span style="width:10px;height:10px;border-radius:2px;background:var(--sage);display:inline-block"></span>
          <span>More</span>
        </div>
      </div>

      <!-- Energy & soreness -->
      <div class="chart-card">
        <div class="ch-head">
          <div>
            <h3>
              <span style="width:8px;height:8px;border-radius:50%;background:var(--sky);display:inline-block"></span>
              Energy &amp; soreness
            </h3>
            <div class="ch-sub">From daily check-ins &middot; 0–10 scale</div>
          </div>
          <div class="chips">
            <span class="chip" style="background:transparent;border:0;color:var(--muted)"><span style="width:8px;height:8px;border-radius:50%;background:var(--sky);display:inline-block;margin-right:6px"></span>Energy</span>
            <span class="chip" style="background:transparent;border:0;color:var(--muted)"><span style="width:8px;height:8px;border-radius:50%;background:var(--coral);display:inline-block;margin-right:6px"></span>Soreness</span>
          </div>
        </div>
        <?php if ($energyLinePath || $sorenessLinePath): ?>
        <svg class="chart" viewBox="0 0 400 160" preserveAspectRatio="none">
          <g stroke="#E2DCCD" stroke-width="1">
            <line x1="0" y1="40" x2="400" y2="40"/><line x1="0" y1="80" x2="400" y2="80"/><line x1="0" y1="120" x2="400" y2="120"/>
          </g>
          <?php if ($energyLinePath): ?>
          <path d="<?= e($energyLinePath) ?>" fill="none" stroke="#5C8AA8" stroke-width="2" stroke-linecap="round"/>
          <?php endif; ?>
          <?php if ($sorenessLinePath): ?>
          <path d="<?= e($sorenessLinePath) ?>" fill="none" stroke="#C66B5B" stroke-width="2" stroke-linecap="round" stroke-dasharray="3 3"/>
          <?php endif; ?>
        </svg>
        <?php else: ?>
          <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">Log mood and soreness in daily check-ins</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Insights -->
    <?php if ($insights): ?>
    <div class="card" style="margin-top:18px">
      <div class="head"><div><div class="eyebrow sage">Patterns we noticed</div><h3 class="h3" style="margin-top:4px"><?= count($insights) ?> insights from your data</h3></div></div>
      <div class="body">
        <?php foreach ($insights as $ins):
          $dotStyle = match($ins['type']) {
            'sage'  => 'background:var(--sage-tint);color:var(--sage-2)',
            'amber' => 'background:var(--amber-tint);color:#7C5215',
            'coral' => 'background:var(--coral-tint);color:#8A3F30',
            default => 'background:var(--bg-2);color:var(--ink-2)'
          };
          $chipClass = match($ins['type']) {
            'sage'  => 'sage', 'amber' => 'amber', 'coral' => 'coral', default => ''
          };
        ?>
        <div class="note-row">
          <div class="left">
            <div class="dot-ic" style="<?= $dotStyle ?>"><?= $ins['icon'] ?></div>
            <div>
              <div class="ttl"><?= e($ins['ttl']) ?></div>
              <div class="sub"><?= e($ins['sub']) ?></div>
            </div>
          </div>
          <?php if ($chipClass): ?><span class="chip <?= $chipClass ?>">data</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </section>
</main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
