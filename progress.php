<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number ASC, created_at ASC', [$me['id']]);

function bigchart_svg(array $values, $labels, $width = 700, $height = 200, $color = '#4A8A68', $fill = '#E6EFE6', $unit = '') {
    $vals = array_values(array_filter($values, fn($v) => $v !== null && $v !== '' && $v !== 0));
    if (count($vals) < 2) {
        return '<div style="padding:40px;text-align:center;color:#7A8278;font-size:13.5px">Submit two or more weekly check-ins to see this chart.</div>';
    }
    $padL = 42; $padR = 16; $padT = 16; $padB = 30;
    $innerW = $width - $padL - $padR;
    $innerH = $height - $padT - $padB;
    $min = min($vals); $max = max($vals);
    if ($max === $min) $max = $min + 1;
    $step = $innerW / max(1, (count($vals) - 1));
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $padL + $i * $step;
        $y = $padT + ($innerH - (($v - $min) / ($max - $min)) * $innerH);
        $pts[] = [$x, $y, $v];
    }
    $path = '';
    foreach ($pts as $i => $p) $path .= ($i === 0 ? 'M' : ' L') . sprintf('%.1f %.1f', $p[0], $p[1]);
    $area = 'M' . sprintf('%.1f %.1f', $pts[0][0], $padT + $innerH) . ' ';
    foreach ($pts as $p) $area .= 'L' . sprintf('%.1f %.1f', $p[0], $p[1]) . ' ';
    $area .= 'L' . sprintf('%.1f %.1f', end($pts)[0], $padT + $innerH) . ' Z';
    $ticks = '';
    for ($i = 0; $i <= 3; $i++) {
        $tv = $min + (($max - $min) * (1 - $i / 3));
        $ty = $padT + ($innerH * ($i / 3));
        $ticks .= '<line x1="' . $padL . '" x2="' . ($width - $padR) . '" y1="' . $ty . '" y2="' . $ty . '" stroke="#E2DCCD" stroke-dasharray="3 3"/>';
        $ticks .= '<text x="' . ($padL - 6) . '" y="' . ($ty + 4) . '" font-family="Plus Jakarta Sans, Arial" font-size="10" fill="#7A8278" text-anchor="end">' . round($tv, 1) . '</text>';
    }
    $xLabels = '';
    foreach ($pts as $i => $p) {
        if ($i === 0 || $i === count($pts) - 1 || count($pts) <= 6) {
            $xLabels .= '<text x="' . $p[0] . '" y="' . ($padT + $innerH + 18) . '" font-family="Plus Jakarta Sans, Arial" font-size="10" fill="#7A8278" text-anchor="middle">W' . ($labels[$i] ?? ($i + 1)) . '</text>';
        }
    }
    $dots = '';
    foreach ($pts as $p) {
        $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="3.5" fill="' . $color . '"/>';
    }
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">'
        . $ticks
        . '<path d="' . $area . '" fill="' . $fill . '" opacity=".6"/>'
        . '<path d="' . $path . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . $dots
        . $xLabels
        . '</svg>';
}

$weekLabels = array_map(fn($w) => (int) $w['week_number'], $weeklyNotes);
$glucose    = array_map(fn($w) => (int) $w['avg_glucose'], $weeklyNotes);
$weightLbs  = array_map(fn($w) => $w['weight_kg'] ? round((float)$w['weight_kg'] * 2.20462, 1) : null, $weeklyNotes);
$energy     = array_map(fn($w) => (int) $w['energy_rating'], $weeklyNotes);

$latest = end($weeklyNotes) ?: null;
$first  = reset($weeklyNotes) ?: null;

$pageTitle = 'Progress — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'progress';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <div style="margin-bottom:28px">
      <p class="eyebrow sage">Trends</p>
      <h1 class="h2 serif" style="margin-top:4px">Your weekly progress</h1>
      <p style="color:var(--muted);font-size:13.5px;margin-top:6px">Data from your weekly check-ins. <?= count($weeklyNotes) ?> week<?= count($weeklyNotes) === 1 ? '' : 's' ?> logged.</p>
    </div>

    <?php if (count($weeklyNotes) >= 2): ?>
    <!-- Summary tiles -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:28px">
      <?php
        $gStart = $first['avg_glucose'] ?? null;
        $gEnd   = $latest['avg_glucose'] ?? null;
        $gDelta = ($gStart && $gEnd) ? $gEnd - $gStart : null;
        $wStart = $first['weight_kg'] ?? null;
        $wEnd   = $latest['weight_kg'] ?? null;
        $wDeltaLb = ($wStart && $wEnd) ? round(($wEnd - $wStart) * 2.20462, 1) : null;
        $eEnd   = $latest['energy_rating'] ?? null;
      ?>
      <div style="background:var(--card);border-radius:var(--r);border:1px solid var(--line);padding:18px">
        <div style="font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:6px">Glucose change</div>
        <div style="font-size:22px;font-weight:700;color:<?= $gDelta === null ? 'var(--ink)' : ($gDelta <= 0 ? 'var(--sage-2)' : 'var(--coral)') ?>">
          <?= $gDelta !== null ? ($gDelta > 0 ? '+' : '') . $gDelta . ' mg/dL' : '—' ?>
        </div>
        <div style="font-size:11.5px;color:var(--muted);margin-top:4px">First vs latest check-in</div>
      </div>
      <div style="background:var(--card);border-radius:var(--r);border:1px solid var(--line);padding:18px">
        <div style="font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:6px">Weight change</div>
        <div style="font-size:22px;font-weight:700;color:var(--ink)">
          <?= $wDeltaLb !== null ? ($wDeltaLb > 0 ? '+' : '') . $wDeltaLb . ' lbs' : '—' ?>
        </div>
        <div style="font-size:11.5px;color:var(--muted);margin-top:4px">First vs latest check-in</div>
      </div>
      <div style="background:var(--card);border-radius:var(--r);border:1px solid var(--line);padding:18px">
        <div style="font-size:11.5px;color:var(--muted);font-weight:600;margin-bottom:6px">Current energy</div>
        <div style="font-size:22px;font-weight:700;color:var(--amber)"><?= $eEnd !== null ? $eEnd . '/10' : '—' ?></div>
        <div style="font-size:11.5px;color:var(--muted);margin-top:4px">Self-rated, latest week</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Charts -->
    <div style="display:flex;flex-direction:column;gap:20px">
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:20px 22px">
        <div style="font-weight:700;font-size:14.5px;margin-bottom:4px">Average blood glucose <span style="font-size:12px;color:var(--muted);font-weight:400">mg/dL</span></div>
        <?= bigchart_svg($glucose, $weekLabels, 700, 200, '#4A8A68', '#E6EFE6') ?>
      </div>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:20px 22px">
        <div style="font-weight:700;font-size:14.5px;margin-bottom:4px">Weight <span style="font-size:12px;color:var(--muted);font-weight:400">lbs</span></div>
        <?= bigchart_svg($weightLbs, $weekLabels, 700, 200, '#3B6E54', '#D8E6D9') ?>
      </div>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:20px 22px">
        <div style="font-weight:700;font-size:14.5px;margin-bottom:4px">Energy <span style="font-size:12px;color:var(--muted);font-weight:400">0–10</span></div>
        <?= bigchart_svg($energy, $weekLabels, 700, 200, '#C68A2E', '#F5E9D2') ?>
      </div>
    </div>

    <?php if (!$weeklyNotes): ?>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:48px 24px;text-align:center">
        <div style="font-size:32px;margin-bottom:12px">📈</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">No data yet</div>
        <p style="color:var(--muted);font-size:13.5px;margin:0 0 20px">Complete your first weekly check-in to see your trends here.</p>
        <a href="/checkin" style="display:inline-block;background:var(--ink);color:#F4F1E9;padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:600">Go to weekly check-in</a>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
