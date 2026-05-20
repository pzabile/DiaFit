<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number ASC, created_at ASC', [$me['id']]);

function bigchart_svg(array $values, $labels, $width = 760, $height = 220, $color = '#16a36a', $fill = '#d6f0e1', $unit = '') {
    $vals = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));
    if (count($vals) < 2) {
        return '<div class="empty-state"><div class="empty-icon">📈</div><strong>Not enough data yet.</strong><p>Submit two or more weekly check-ins to see the chart.</p></div>';
    }
    $padL = 40; $padR = 16; $padT = 16; $padB = 32;
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

    // Y axis ticks
    $ticks = '';
    for ($i = 0; $i <= 3; $i++) {
        $tv = $min + (($max - $min) * (1 - $i / 3));
        $ty = $padT + ($innerH * ($i / 3));
        $ticks .= '<line x1="' . $padL . '" x2="' . ($width - $padR) . '" y1="' . $ty . '" y2="' . $ty . '" stroke="#e3e0d6" stroke-dasharray="3 3"/>';
        $ticks .= '<text x="' . ($padL - 6) . '" y="' . ($ty + 4) . '" font-family="Inter, Arial" font-size="10" fill="#8a8f8b" text-anchor="end">' . round($tv, 1) . '</text>';
    }
    // X labels
    $xLabels = '';
    foreach ($pts as $i => $p) {
        if ($i === 0 || $i === count($pts) - 1 || count($pts) <= 6) {
            $xLabels .= '<text x="' . $p[0] . '" y="' . ($padT + $innerH + 16) . '" font-family="Inter, Arial" font-size="10" fill="#8a8f8b" text-anchor="middle">W' . ($labels[$i] ?? ($i + 1)) . '</text>';
        }
    }
    // Dots
    $dots = '';
    foreach ($pts as $p) {
        $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="3.5" fill="' . $color . '"/>';
    }
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" class="bigchart">'
        . $ticks
        . '<path d="' . $area . '" fill="' . $fill . '" opacity=".55"/>'
        . '<path d="' . $path . '" fill="none" stroke="' . $color . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
        . $dots
        . $xLabels
        . '</svg>';
}

$weekLabels = array_map(fn($w) => (int) $w['week_number'], $weeklyNotes);
$glucose    = array_map(fn($w) => (int) $w['avg_glucose'], $weeklyNotes);
$weightLbs  = array_map(fn($w) => $w['weight_kg'] ? round((float)$w['weight_kg'] * 2.20462, 1) : null, $weeklyNotes);
$energy     = array_map(fn($w) => (int) $w['energy_rating'], $weeklyNotes);

$pageTitle = 'Progress — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'progress';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <header class="page-head">
    <div>
      <p class="kicker">Progress</p>
      <h1>Your weekly trends</h1>
    </div>
  </header>

  <section class="card big">
    <h2>Average blood glucose <span class="muted-inline">(mg/dL)</span></h2>
    <?= bigchart_svg($glucose, $weekLabels, 760, 220, '#16a36a', '#d6f0e1') ?>
  </section>

  <section class="card big">
    <h2>Weight <span class="muted-inline">(lbs)</span></h2>
    <?= bigchart_svg($weightLbs, $weekLabels, 760, 220, '#0d7d4f', '#d6f0e1') ?>
  </section>

  <section class="card big">
    <h2>Energy <span class="muted-inline">(0–10)</span></h2>
    <?= bigchart_svg($energy, $weekLabels, 760, 220, '#f0a830', '#fff5e5') ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
