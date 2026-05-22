<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

function mem_initials(string $name, string $email): string {
    $s = trim($name);
    if ($s) {
        $p = preg_split('/\s+/', $s);
        if (count($p) >= 2) return strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1));
        return strtoupper(mb_substr($s,0,2));
    }
    return strtoupper(mb_substr($email,0,2));
}
function mem_av_color(string $s): string {
    $colors = ['','sage','plum','coral','sky'];
    return $colors[abs(crc32($s)) % count($colors)];
}
function mem_plan_label(int $days): string {
    if ($days <= 7)  return '7-day';
    if ($days <= 28) return '4-wk';
    if ($days <= 56) return '8-wk';
    return '12-wk';
}
function mem_plan_weeks(int $days): int {
    return max(1, (int)ceil($days / 7));
}
function mem_week_num(string $startedAt, int $planWeeks): int {
    if (!$startedAt) return 1;
    $elapsed = floor((time() - strtotime($startedAt)) / 86400);
    return min($planWeeks, max(1, (int)ceil(($elapsed + 1) / 7)));
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = 'l.paid = 1';
if ($q !== '') {
    $where .= ' AND (l.email LIKE ? OR l.first_name LIKE ? OR l.phone LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}

$rows = db_all("
    SELECT l.id, l.first_name, l.email, l.phone, l.started_at, l.plan_days,
           l.program_path, l.last_login_at, l.created_at,
           (SELECT COUNT(*) FROM coach_notes cn WHERE cn.lead_id=l.id AND cn.from_member=1) AS msg_count,
           (SELECT cn3.from_member FROM coach_notes cn3 WHERE cn3.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)) AS last_from_member,
           (SELECT MAX(dl.log_date) FROM daily_logs dl WHERE dl.lead_id=l.id) AS last_log_date
    FROM leads l
    WHERE {$where}
    ORDER BY l.id DESC
    LIMIT 500
", $params);

/* ── CSV export ── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="members-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Name','Email','Phone','Plan','Started','Plan weeks','Week #','Last log','Messages','Status']);
    foreach ($rows as $r) {
        $planDays  = (int)($r['plan_days'] ?? 84);
        $planWeeks = mem_plan_weeks($planDays);
        $weekNum   = mem_week_num($r['started_at'] ?? '', $planWeeks);
        $isWaiting = (int)($r['last_from_member'] ?? 0) === 1;
        $lastLog   = $r['last_log_date'] ?? '';
        $daysSince = $lastLog ? floor((time() - strtotime($lastLog)) / 86400) : 999;
        if (strtotime($r['created_at']) >= strtotime('-7 days')) $status = 'New';
        elseif ($daysSince >= 3) $status = 'At risk';
        elseif ($isWaiting) $status = 'Waiting';
        else $status = 'Active';
        fputcsv($out, [
            $r['id'],
            $r['first_name'] ?: '—',
            $r['email'],
            $r['phone'] ?: '—',
            mem_plan_label($planDays),
            $r['started_at'] ?: '—',
            $planWeeks,
            $weekNum,
            $lastLog ?: '—',
            (int)$r['msg_count'],
            $status,
        ]);
    }
    fclose($out);
    exit;
}

/* Sidebar badge counts */
$waitingCount = 0;
foreach ($rows as $r) {
    if ((int)($r['last_from_member'] ?? 0) === 1) $waitingCount++;
}
$membersCount = count($rows);
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];

$pageTitle = 'Members — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'members';
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
        <b>Paid members</b>
      </div>
      <div class="top-actions">
        <div class="search-box">
          <form method="get" style="display:contents">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search members, emails, plans…" autocomplete="off" />
          </form>
          <span class="k">⌘K</span>
        </div>
        <a href="/admin/new_member" class="btn pri">+ Add member</a>
      </div>
    </header>

    <div class="view">

      <!-- Header -->
      <div class="row" style="justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:18px">
        <div>
          <div class="eyebrow sage">Paid · active subscriptions</div>
          <h1 class="h1">Your <em>members</em>.</h1>
          <p class="muted" style="margin:0;max-width:60ch">Click any row to see their profile, conversation thread and program status.</p>
        </div>
        <div class="row" style="gap:10px">
          <a href="/admin/members?<?= $q ? 'q='.urlencode($q).'&' : '' ?>export=csv" class="btn">Export CSV</a>
          <a href="/admin/new_member" class="btn pri">+ Create member</a>
        </div>
      </div>

      <!-- Toolbar: search + filter pills -->
      <div class="table-toolbar">
        <div class="left">
          <div class="filter-pills" id="filterPills">
            <button class="chip ink" data-filter="all" onclick="filterRows('all',this)">All · <?= count($rows) ?></button>
            <button class="chip" data-filter="active" onclick="filterRows('active',this)">Active</button>
            <button class="chip" data-filter="new" onclick="filterRows('new',this)">New</button>
            <button class="chip" data-filter="at_risk" onclick="filterRows('at_risk',this)">At risk</button>
          </div>
        </div>
        <div class="muted" style="font-size:12px">Sorted by · most recent</div>
      </div>

      <div class="card" style="overflow:hidden">
        <table class="tbl" id="memberTbl">
          <thead>
            <tr>
              <th>Member</th>
              <th>Plan</th>
              <th>Week</th>
              <th>Last log</th>
              <th>Inbox</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">
                <?= $q ? 'No members match that search.' : 'No paid members yet.' ?>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
              $planDays  = (int)($r['plan_days'] ?? 84);
              $planLbl   = mem_plan_label($planDays);
              $planWeeks = mem_plan_weeks($planDays);
              $weekNum   = mem_week_num($r['started_at'] ?? '', $planWeeks);
              $initials  = mem_initials($r['first_name'] ?? '', $r['email']);
              $avColor   = mem_av_color($r['email']);
              $isWaiting = (int)($r['last_from_member'] ?? 0) === 1;
              $msgCount  = (int)($r['msg_count'] ?? 0);
              $lastLog   = $r['last_log_date'] ?? '';
              $daysSince = $lastLog ? floor((time() - strtotime($lastLog)) / 86400) : 999;
              $lastLogLbl = '—';
              if ($lastLog) {
                  if ($daysSince === 0) $lastLogLbl = 'Today';
                  elseif ($daysSince === 1) $lastLogLbl = 'Yesterday';
                  else $lastLogLbl = $daysSince . 'd ago';
              }
              /* Determine status */
              $isNew   = strtotime($r['created_at']) >= strtotime('-7 days');
              $atRisk  = $daysSince >= 3 && !$isNew;
              if ($isNew)        { $statusLbl = 'New'; $statusCls = 'sage'; $filterStatus = 'new'; }
              elseif ($atRisk)   { $statusLbl = 'At risk · '.$daysSince.'d no log'; $statusCls = 'coral'; $filterStatus = 'at_risk'; }
              elseif ($isWaiting){ $statusLbl = 'Needs reply'; $statusCls = 'amber'; $filterStatus = 'active'; }
              else               { $statusLbl = 'On track'; $statusCls = 'sage'; $filterStatus = 'active'; }
            ?>
              <tr onclick="location.href='/admin/member?id=<?= (int)$r['id'] ?>'" style="cursor:pointer" data-status="<?= $filterStatus ?>">
                <td>
                  <div class="name">
                    <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                    <div>
                      <div style="font-weight:600"><?= e($r['first_name'] ?: '—') ?></div>
                      <div class="em"><?= e($r['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="chip"><?= e($planLbl) ?></span></td>
                <td style="font-size:13px;color:var(--ink-2)"><?= $weekNum ?> / <?= $planWeeks ?></td>
                <td style="font-size:12.5px;color:var(--muted)"><?= e($lastLogLbl) ?></td>
                <td>
                  <?php if ($isWaiting): ?>
                    <span class="chip coral"><?= $msgCount ?> waiting</span>
                  <?php elseif ($msgCount > 0): ?>
                    <span class="chip"><?= $msgCount ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted);font-size:12px">0</span>
                  <?php endif; ?>
                </td>
                <td><span class="chip <?= $statusCls ?>"><?= e($statusLbl) ?></span></td>
                <td><span class="open">Open →</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- /card -->

    </div><!-- /view -->
  </main>
</div>

<script>
function filterRows(f, btn) {
  document.querySelectorAll('#filterPills .chip').forEach(function(b){
    b.classList.remove('ink');
  });
  btn.classList.add('ink');
  document.querySelectorAll('#memberTbl tbody tr[data-status]').forEach(function(row){
    row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
  });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
