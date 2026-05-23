<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

/* ── KPI counts ── */
$totalMembers   = (int)(db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'] ?? 0);
$totalLeads     = (int)(db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'] ?? 0);
$newThisWeek    = (int)(db_get("SELECT COUNT(*) c FROM leads WHERE paid=1 AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")['c'] ?? 0);
$newThisMonth   = (int)(db_get("SELECT COUNT(*) c FROM leads WHERE paid=1 AND created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY)")['c'] ?? 0);
$withProgram    = (int)(db_get("SELECT COUNT(*) c FROM leads WHERE paid=1 AND program_path IS NOT NULL AND program_path != ''")['c'] ?? 0);

/* Active this week: at least one log in last 7 days */
$activeThisWeek = 0;
try {
    $activeThisWeek = (int)(db_get("SELECT COUNT(DISTINCT lead_id) c FROM daily_logs WHERE log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)")['c'] ?? 0);
} catch (Throwable $ignored) {}

/* Logs this week */
$logsThisWeek = 0;
try {
    $logsThisWeek = (int)(db_get("SELECT COUNT(*) c FROM daily_logs WHERE log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)")['c'] ?? 0);
} catch (Throwable $ignored) {}

/* New member trend: signups by week for last 12 weeks */
$signupTrend = [];
try {
    $trendRows = db_all("
        SELECT YEARWEEK(created_at,1) AS yw,
               DATE_FORMAT(MIN(created_at),'%b %d') AS lbl,
               COUNT(*) AS cnt
        FROM leads WHERE paid=1 AND created_at >= DATE_SUB(NOW(),INTERVAL 12 WEEK)
        GROUP BY YEARWEEK(created_at,1)
        ORDER BY yw
    ");
    foreach ($trendRows as $t) {
        $signupTrend[] = ['label' => $t['lbl'], 'cnt' => (int)$t['cnt']];
    }
} catch (Throwable $ignored) {}

/* Plan distribution */
$planDist = [];
try {
    $planRows = db_all("SELECT plan_days, COUNT(*) cnt FROM leads WHERE paid=1 GROUP BY plan_days ORDER BY plan_days");
    foreach ($planRows as $r) {
        $d = (int)$r['plan_days'];
        $lbl = $d <= 7 ? '7-day' : ($d <= 28 ? '4-week' : ($d <= 56 ? '8-week' : '12-week'));
        $planDist[] = ['label' => $lbl, 'cnt' => (int)$r['cnt']];
    }
} catch (Throwable $ignored) {}

/* Cohort: where are members in their program */
$cohortDist = [];
try {
    $cohortRows = db_all("
        SELECT LEAST(CEIL((DATEDIFF(CURDATE(),started_at)+1)/7), CEIL(plan_days/7)) AS prog_week,
               COUNT(*) AS cnt
        FROM leads
        WHERE paid=1 AND started_at IS NOT NULL AND started_at != '0000-00-00'
        GROUP BY prog_week
        ORDER BY prog_week
        LIMIT 20
    ");
    foreach ($cohortRows as $r) {
        $cohortDist[(int)$r['prog_week']] = (int)$r['cnt'];
    }
} catch (Throwable $ignored) {}

/* Recent members */
$recentMembers = [];
try {
    $recentMembers = db_all("
        SELECT id, first_name, email, plan_days, started_at, created_at
        FROM leads WHERE paid=1
        ORDER BY created_at DESC
        LIMIT 10
    ");
} catch (Throwable $ignored) {}

/* Sidebar counts */
$waitingCount = 0;
$membersCount = $totalMembers;
$leadsCount   = $totalLeads;
try {
    $waitingCount = (int)(db_get("SELECT COUNT(DISTINCT l.id) c FROM leads l JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id) WHERE l.paid=1 AND cn.from_member=1")['c'] ?? 0);
} catch (Throwable $ignored) {}

/* Helper: mini SVG bar chart */
function bar_chart(array $data, string $color = '#4A8A68', int $w = 100, int $h = 48): string {
    if (!$data) return '';
    $max = max(array_column($data, 'cnt'));
    if (!$max) return '';
    $n   = count($data);
    $bw  = max(4, floor(($w - ($n - 1) * 2) / $n));
    $svg = '<svg width="100%" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">';
    foreach ($data as $i => $d) {
        $bh = max(2, (int)round($d['cnt'] / $max * ($h - 2)));
        $x  = $i * ($bw + 2);
        $y  = $h - $bh;
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '" rx="2" fill="' . $color . '"/>';
    }
    $svg .= '</svg>';
    return $svg;
}

$pageTitle = 'Stats — DiaFit Admin';
$bodyClass = 'admin-page';
$activeTab = 'stats';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
  <main class="main">
    <header class="topbar">
      <div class="crumb">
        <span class="dot"></span>
        <span>Admin</span>
        <span style="color:var(--line-2)">›</span>
        <b>Stats</b>
      </div>
      <div class="top-actions">
        <span style="font-size:12px;color:var(--muted)">Updated <?= date('g:i A') ?></span>
      </div>
    </header>

    <div class="view">

      <div style="margin-bottom:28px">
        <div class="eyebrow sage">Insights</div>
        <h1 class="h1">Member <em>stats</em>.</h1>
        <p class="muted" style="margin:0;max-width:60ch">Signups, engagement, cohort progress, and plan distribution. Refreshes on every page load.</p>
      </div>

      <!-- KPI grid -->
      <div class="kpi-grid" style="margin-bottom:18px">
        <div class="kpi">
          <div class="lbl">Total members</div>
          <div class="num"><?= $totalMembers ?></div>
          <div class="foot">Paid subscriptions</div>
        </div>
        <div class="kpi">
          <div class="lbl">New this week</div>
          <div class="num" style="color:var(--sage-2)"><?= $newThisWeek ?></div>
          <div class="foot sage"><?= $newThisMonth ?> this month</div>
        </div>
        <div class="kpi">
          <div class="lbl">Active this week</div>
          <div class="num"><?= $activeThisWeek ?></div>
          <div class="foot"><?= $totalMembers > 0 ? round($activeThisWeek / $totalMembers * 100) : 0 ?>% of members logged</div>
        </div>
        <div class="kpi">
          <div class="lbl">Logs this week</div>
          <div class="num"><?= $logsThisWeek ?></div>
          <div class="foot"><?= $activeThisWeek > 0 ? round($logsThisWeek / $activeThisWeek, 1) : '—' ?> avg per active</div>
        </div>
        <div class="kpi">
          <div class="lbl">With program</div>
          <div class="num"><?= $withProgram ?></div>
          <div class="foot"><?= $totalMembers > 0 ? round($withProgram / $totalMembers * 100) : 0 ?>% assigned</div>
        </div>
        <div class="kpi">
          <div class="lbl">Unpaid leads</div>
          <div class="num" style="color:var(--amber)"><?= $totalLeads ?></div>
          <div class="foot">Assessed, not converted</div>
        </div>
      </div>

      <!-- Charts row -->
      <div class="col2" style="margin-bottom:20px">

        <!-- Signup trend -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Growth</div>
              <h3 class="h3">New members · last 12 weeks</h3>
            </div>
          </div>
          <div class="body">
            <?php if ($signupTrend): ?>
              <div style="margin-bottom:10px">
                <?= bar_chart($signupTrend, '#4A8A68', 200, 60) ?>
              </div>
              <div style="display:flex;gap:0;overflow-x:auto">
                <?php foreach ($signupTrend as $t): ?>
                <div style="flex:1;min-width:32px;text-align:center;font-size:10px;color:var(--muted)">
                  <div style="font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--ink);font-size:13px"><?= $t['cnt'] ?></div>
                  <div><?= e($t['label']) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">No signup data yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Plan distribution -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Plans</div>
              <h3 class="h3">Plan distribution</h3>
            </div>
          </div>
          <div class="body">
            <?php if ($planDist): ?>
              <?php foreach ($planDist as $p): ?>
              <?php $pct = $totalMembers > 0 ? round($p['cnt'] / $totalMembers * 100) : 0; ?>
              <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                  <span style="font-weight:600"><?= e($p['label']) ?></span>
                  <span style="color:var(--muted)"><?= $p['cnt'] ?> members · <?= $pct ?>%</span>
                </div>
                <div style="height:8px;border-radius:99px;background:var(--bg-3);overflow:hidden">
                  <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--sage),#9CC9A8);border-radius:99px"></div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">No members yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Cohort distribution -->
      <?php if ($cohortDist): ?>
      <div class="card" style="margin-bottom:20px">
        <div class="head">
          <div>
            <div class="eyebrow" style="margin-bottom:3px">Cohort</div>
            <h3 class="h3">Where are members in their program?</h3>
          </div>
          <span class="chip"><?= array_sum($cohortDist) ?> placed</span>
        </div>
        <div class="body">
          <div style="display:flex;gap:6px;align-items:flex-end;height:80px;padding-bottom:0">
            <?php $maxCohort = max($cohortDist); ?>
            <?php for ($w = 1; $w <= max(array_keys($cohortDist)); $w++): ?>
            <?php $cnt = $cohortDist[$w] ?? 0; $ht = $maxCohort > 0 ? round($cnt / $maxCohort * 64) : 0; ?>
            <div style="flex:1;min-width:24px;display:flex;flex-direction:column;align-items:center;gap:3px">
              <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted)"><?= $cnt ?: '' ?></div>
              <div style="width:100%;height:<?= max(4, $ht) ?>px;background:<?= $cnt > 0 ? 'linear-gradient(180deg,var(--sage),var(--sage-2))' : 'var(--bg-3)' ?>;border-radius:4px 4px 2px 2px;min-height:4px"></div>
              <div style="font-size:10px;color:var(--muted)">W<?= $w ?></div>
            </div>
            <?php endfor; ?>
          </div>
          <div style="margin-top:12px;font-size:12px;color:var(--muted)">Each bar = number of members currently on that week of their program.</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Recent members -->
      <?php if ($recentMembers): ?>
      <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:var(--muted);margin-bottom:12px">Recent signups</div>
      <div class="card" style="overflow:hidden">
        <table class="tbl">
          <thead>
            <tr>
              <th>Member</th>
              <th>Plan</th>
              <th>Started</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentMembers as $r):
              $planDays = (int)($r['plan_days'] ?? 84);
              $planLbl  = $planDays <= 7 ? '7-day' : ($planDays <= 28 ? '4-wk' : ($planDays <= 56 ? '8-wk' : '12-wk'));
              $name     = $r['first_name'] ?: explode('@', $r['email'])[0];
              $colors   = ['','sage','plum','coral','sky'];
              $avColor  = $colors[abs(crc32($r['email'])) % count($colors)];
              $initials = strtoupper(mb_substr($name, 0, 2));
            ?>
            <tr onclick="location.href='/admin/member?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
              <td>
                <div class="name">
                  <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                  <div>
                    <div style="font-weight:600"><?= e($name) ?></div>
                    <div class="em"><?= e($r['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="chip"><?= e($planLbl) ?></span></td>
              <td style="font-size:12.5px;color:var(--muted)"><?= $r['started_at'] ? date('M j, Y', strtotime($r['started_at'])) : '—' ?></td>
              <td style="font-size:12.5px;color:var(--muted)"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
              <td><span class="open">View →</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div><!-- /view -->
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
