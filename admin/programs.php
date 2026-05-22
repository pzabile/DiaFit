<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$params = [1];
$where = 'l.paid = ?';
if ($q !== '') {
    $where .= ' AND (l.email LIKE ? OR l.first_name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

/* Members with programs */
$withProgram = db_all("
    SELECT l.id, l.first_name, l.email, l.started_at, l.plan_days, l.program_path,
           l.updated_at,
           (SELECT MAX(dl.log_date) FROM daily_logs dl WHERE dl.lead_id=l.id) AS last_log_date
    FROM leads l
    WHERE l.paid=1 AND l.program_path IS NOT NULL AND l.program_path != ''
    ORDER BY l.updated_at DESC
    LIMIT 200
", []);

/* Members without programs */
$withoutProgram = db_all("
    SELECT l.id, l.first_name, l.email, l.started_at, l.plan_days, l.created_at
    FROM leads l
    WHERE l.paid=1 AND (l.program_path IS NULL OR l.program_path = '')
    ORDER BY l.created_at DESC
    LIMIT 200
", []);

$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$programCount = count($withProgram);

function prog_plan_label(int $days): string {
    if ($days <= 7)  return '7-day';
    if ($days <= 28) return '4-wk';
    if ($days <= 56) return '8-wk';
    return '12-wk';
}
function prog_plan_weeks(int $days): int {
    return max(1, (int)ceil($days / 7));
}
function prog_week_num(string $startedAt, int $planWeeks): int {
    if (!$startedAt) return 1;
    $elapsed = floor((time() - strtotime($startedAt)) / 86400);
    return min($planWeeks, max(1, (int)ceil(($elapsed + 1) / 7)));
}
function prog_av_color(string $s): string {
    $c = ['','sage','plum','coral','sky'];
    return $c[abs(crc32($s)) % count($c)];
}

$pageTitle = 'Programs — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'programs';
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
        <b>Programs</b>
      </div>
      <div class="top-actions">
        <a href="/admin/programs_new" class="btn pri">+ New from PDF</a>
      </div>
    </header>

    <div class="view">

      <!-- Header -->
      <div class="row" style="justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:24px">
        <div>
          <div class="eyebrow plum">Published plans</div>
          <h1 class="h1">Member <em>programs</em>.</h1>
          <p class="muted" style="margin:0;max-width:60ch"><?= $programCount ?> of <?= $membersCount ?> members have a published plan. Click a card to open the member profile.</p>
        </div>
        <div class="row" style="gap:10px">
          <a href="/admin/programs_new" class="btn pri">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            New from PDF
          </a>
        </div>
      </div>

      <!-- KPI signal row -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
        <div class="card" style="padding:16px 18px">
          <div class="eyebrow" style="margin-bottom:6px">Total members</div>
          <div style="font-family:'Instrument Serif',serif;font-size:34px;letter-spacing:-.02em;line-height:1"><?= $membersCount ?></div>
          <div class="muted" style="font-size:12px;margin-top:4px">Active paid subscriptions</div>
        </div>
        <div class="card" style="padding:16px 18px">
          <div class="eyebrow sage" style="margin-bottom:6px">Programs assigned</div>
          <div style="font-family:'Instrument Serif',serif;font-size:34px;letter-spacing:-.02em;line-height:1;color:var(--sage-2)"><?= $programCount ?></div>
          <div class="muted" style="font-size:12px;margin-top:4px"><?= $membersCount > 0 ? round($programCount / $membersCount * 100) : 0 ?>% of members have a plan</div>
        </div>
        <div class="card" style="padding:16px 18px">
          <div class="eyebrow" style="color:var(--amber);margin-bottom:6px">Without program</div>
          <div style="font-family:'Instrument Serif',serif;font-size:34px;letter-spacing:-.02em;line-height:1;color:var(--amber)"><?= count($withoutProgram) ?></div>
          <div class="muted" style="font-size:12px;margin-top:4px">Members awaiting their plan</div>
        </div>
      </div>

      <?php if ($withProgram): ?>
      <div style="margin-bottom:10px">
        <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:var(--muted);margin-bottom:12px">Active programs</div>
        <div class="prog-grid">
          <?php foreach ($withProgram as $r):
            $planDays  = (int)($r['plan_days'] ?? 84);
            $planLbl   = prog_plan_label($planDays);
            $planWeeks = prog_plan_weeks($planDays);
            $weekNum   = prog_week_num($r['started_at'] ?? '', $planWeeks);
            $pct       = $planWeeks > 0 ? min(100, round($weekNum / $planWeeks * 100)) : 0;
            $avColor   = prog_av_color($r['email']);
            $name      = $r['first_name'] ?: explode('@', $r['email'])[0];
            $lastLog   = $r['last_log_date'] ?? '';
            $daysSince = $lastLog ? floor((time() - strtotime($lastLog)) / 86400) : 999;
            $lastLogLbl = $lastLog ? ($daysSince === 0 ? 'Today' : ($daysSince === 1 ? 'Yesterday' : $daysSince . 'd ago')) : '—';
            $fileName  = $r['program_path'] ? basename($r['program_path']) : '—';
          ?>
          <a href="/admin/member?id=<?= (int)$r['id'] ?>" class="prog-card">
            <div style="display:flex;align-items:center;gap:10px">
              <div class="tbl av <?= $avColor ?>" style="flex-shrink:0"><?= e(mb_strtoupper(mb_substr($name, 0, 2))) ?></div>
              <div>
                <div class="ttl"><?= e($name) ?></div>
                <div class="meta"><?= e($r['email']) ?></div>
              </div>
              <span class="chip sage" style="margin-left:auto"><?= e($planLbl) ?></span>
            </div>
            <div class="bar"><div class="fill" style="width:<?= $pct ?>%"></div></div>
            <div class="foot">
              <div>
                <div style="font-size:12px;color:var(--muted)">Week <?= $weekNum ?> of <?= $planWeeks ?> · <?= $pct ?>%</div>
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Last log: <?= e($lastLogLbl) ?></div>
              </div>
              <div>
                <div style="font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace;text-align:right;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($fileName) ?></div>
                <div style="font-size:11px;color:var(--sage-2);font-weight:600;text-align:right;margin-top:3px">View profile →</div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
          <a href="/admin/programs_new" class="prog-new-card">
            <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></div>
            <div class="ttl">New from PDF</div>
            <div class="sub">Upload and assign a plan to a member</div>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($withoutProgram): ?>
      <div style="margin-top:28px">
        <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:var(--amber);margin-bottom:12px">Waiting for a program (<?= count($withoutProgram) ?>)</div>
        <div class="card" style="overflow:hidden">
          <table class="tbl">
            <thead>
              <tr>
                <th>Member</th>
                <th>Plan</th>
                <th>Started</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($withoutProgram as $r):
                $planDays = (int)($r['plan_days'] ?? 84);
                $name     = $r['first_name'] ?: explode('@', $r['email'])[0];
                $avColor  = prog_av_color($r['email']);
              ?>
              <tr>
                <td>
                  <div class="name">
                    <div class="av <?= $avColor ?>"><?= e(mb_strtoupper(mb_substr($name, 0, 2))) ?></div>
                    <div>
                      <div style="font-weight:600"><?= e($name) ?></div>
                      <div class="em"><?= e($r['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="chip amber">No program</span></td>
                <td style="font-size:12.5px;color:var(--muted)"><?= $r['started_at'] ? e(date('M j, Y', strtotime($r['started_at']))) : '—' ?></td>
                <td>
                  <a href="/admin/programs_new?member=<?= (int)$r['id'] ?>" class="btn sm sage">Assign plan →</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$withProgram && !$withoutProgram): ?>
      <div style="text-align:center;padding:80px 0;color:var(--muted)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:14px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
        <p>No members yet. <a href="/admin/programs_new" style="color:var(--sage-2);font-weight:600">Upload a program →</a></p>
      </div>
      <?php endif; ?>

    </div><!-- /view -->
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
