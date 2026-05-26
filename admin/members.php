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
           l.program_path, l.created_at,
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
          <p class="muted" style="margin:0;max-width:60ch">Click any row to open the member profile — conversation, logs, health context and program.</p>
        </div>
        <div class="row" style="gap:10px">
          <a href="/admin/members?<?= $q ? 'q='.urlencode($q).'&' : '' ?>export=csv" class="btn">Export CSV</a>
          <a href="/admin/new_member" class="btn pri">+ Create member</a>
        </div>
      </div>

      <!-- Toolbar: filter pills -->
      <div class="table-toolbar">
        <div class="left">
          <div class="filter-pills" id="filterPills">
            <button class="chip ink" data-filter="all" onclick="filterRows('all',this)">All · <?= count($rows) ?></button>
            <button class="chip" data-filter="active" onclick="filterRows('active',this)">Active</button>
            <button class="chip" data-filter="new" onclick="filterRows('new',this)">New</button>
            <button class="chip" data-filter="at_risk" onclick="filterRows('at_risk',this)">At risk</button>
            <button class="chip" data-filter="inactive" onclick="filterRows('inactive',this)">Inactive</button>
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
              $planEnd = !empty($r['started_at']) ? strtotime($r['started_at'] . ' +' . $planDays . ' days') : null;
              $isInactive = $planEnd && $planEnd <= time();
              if ($isInactive)   { $statusLbl = 'Inactive'; $statusCls = 'muted-chip'; $filterStatus = 'inactive'; }
              elseif ($isNew)    { $statusLbl = 'New'; $statusCls = 'sage'; $filterStatus = 'new'; }
              elseif ($atRisk)   { $statusLbl = 'At risk · '.$daysSince.'d no log'; $statusCls = 'coral'; $filterStatus = 'at_risk'; }
              elseif ($isWaiting){ $statusLbl = 'Needs reply'; $statusCls = 'amber'; $filterStatus = 'active'; }
              else               { $statusLbl = 'On track'; $statusCls = 'sage'; $filterStatus = 'active'; }
            ?>
              <tr onclick="openMemberDrawer(<?= (int)$r['id'] ?>)" style="cursor:pointer" data-status="<?= $filterStatus ?>">
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

<!-- ===== MEMBER DRAWER ===== -->
<div class="drawer-scrim" id="drawerScrim" onclick="closeMemberDrawer()"></div>
<div class="drawer" id="memberDrawer" role="dialog" aria-modal="true">
  <div class="drawer-head" id="drawerHead">
    <div class="av" id="drawerAv">??</div>
    <div>
      <div class="name" id="drawerName">Loading…</div>
      <div class="sub" id="drawerSub"></div>
    </div>
    <button class="close" onclick="closeMemberDrawer()" title="Close">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <div class="dr-loading">Loading member data…</div>
  </div>
</div>

<script>
function filterRows(f, btn) {
  document.querySelectorAll('#filterPills .chip').forEach(function(b){ b.classList.remove('ink'); });
  btn.classList.add('ink');
  document.querySelectorAll('#memberTbl tbody tr[data-status]').forEach(function(row){
    row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
  });
}

var _drawerCache = {};

function openMemberDrawer(id) {
  document.body.classList.add('drawer-open');
  document.getElementById('drawerName').textContent = 'Loading…';
  document.getElementById('drawerSub').textContent = '';
  document.getElementById('drawerAv').textContent = '??';
  document.getElementById('drawerAv').className = 'av';
  document.getElementById('drawerBody').innerHTML = '<div class="dr-loading">Loading member data…</div>';

  if (_drawerCache[id]) { _populateDrawer(_drawerCache[id]); return; }

  fetch('/admin/member_data?id=' + id)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { document.getElementById('drawerBody').innerHTML = '<div class="dr-loading" style="color:var(--coral)">Failed to load member data.</div>'; return; }
      _drawerCache[id] = d;
      _populateDrawer(d);
    })
    .catch(function(){
      document.getElementById('drawerBody').innerHTML = '<div class="dr-loading" style="color:var(--coral)">Network error.</div>';
    });
}

function closeMemberDrawer() {
  document.body.classList.remove('drawer-open');
}

document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMemberDrawer(); });

function _esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function _fmtTime(iso){
  if (!iso) return '—';
  var d = new Date(iso.replace(' ','T'));
  var h = d.getHours(), m = d.getMinutes();
  return (h%12||12)+':'+(m<10?'0':'')+m+(h<12?' AM':' PM');
}

function _populateDrawer(d) {
  /* Head */
  var av = document.getElementById('drawerAv');
  av.textContent = d.initials;
  av.className = 'av' + (d.av_color ? ' ' + d.av_color : '');
  document.getElementById('drawerName').textContent = d.name;
  document.getElementById('drawerSub').textContent = d.email + (d.phone && d.phone !== 'no phone on file' ? ' · ' + d.phone : '');

  /* KPIs */
  var weekPct = d.plan_weeks > 0 ? Math.round((d.week_num / d.plan_weeks) * 100) : 0;
  var kpiHtml = '<div class="dr-kpi">'
    + '<div class="it"><div class="l">Plan</div><div class="v">' + _esc(d.plan_label) + '</div><div class="s">Week ' + d.week_num + ' of ' + d.plan_weeks + '</div></div>'
    + '<div class="it"><div class="l">Streak</div><div class="v">' + d.streak + '</div><div class="s">days logged</div></div>'
    + '<div class="it"><div class="l">Avg glucose</div><div class="v">' + (d.avg_glucose ? d.avg_glucose : '—') + '</div><div class="s">mg/dL · 7d</div></div>'
    + '<div class="it"><div class="l">Program</div><div class="v">' + (d.has_program ? '✓' : '—') + '</div><div class="s">' + (d.has_program ? 'Uploaded' : 'None yet') + '</div></div>'
    + '</div>';

  /* Progress bar */
  kpiHtml += '<div style="margin-bottom:18px"><div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--muted);margin-bottom:5px"><span>Program progress</span><span>Week ' + d.week_num + ' / ' + d.plan_weeks + '</span></div>'
    + '<div style="height:6px;border-radius:99px;background:var(--bg-3);overflow:hidden"><div style="height:100%;width:' + weekPct + '%;background:linear-gradient(90deg,var(--sage),#9CC9A8);border-radius:99px"></div></div></div>';

  /* Conversation */
  var convHtml = '<div class="dr-section"><h4>Recent messages</h4>';
  if (d.messages && d.messages.length) {
    convHtml += '<div class="dr-conv">';
    d.messages.forEach(function(msg){
      var cls = msg.from_member == 1 ? 'me' : 'them';
      var body = _esc(msg.body).replace(/\n/g,'<br>');
      convHtml += '<div class="bubble ' + cls + '">' + body + '<span class="time">' + _fmtTime(msg.created_at) + '</span></div>';
    });
    convHtml += '</div>';
  } else {
    convHtml += '<div style="color:var(--muted);font-size:13px;padding:14px;background:var(--bg);border-radius:12px;border:1px solid var(--line)">No messages yet.</div>';
  }
  convHtml += '</div>';

  /* Recent events */
  var eventsHtml = '<div class="dr-section"><h4>Recent activity</h4><div class="dr-events">';
  if (d.events && d.events.length) {
    d.events.forEach(function(ev){
      var chipCls = ev.chip_cls ? ' ' + ev.chip_cls : '';
      eventsHtml += '<div class="dr-event">'
        + '<div class="dt">' + _esc(ev.date || '').replace(/\d{4}-/, '').replace('-','/') + '</div>'
        + '<div><div class="lbl"><span class="chip' + chipCls + '" style="font-size:11px;padding:2px 8px">' + _esc(ev.label || '—') + '</span></div>'
        + (ev.notes ? '<div class="notes">' + _esc(ev.notes) + '</div>' : '')
        + '</div>'
        + '</div>';
    });
  } else {
    eventsHtml += '<div style="color:var(--muted);font-size:13px;padding:10px 0">No logs yet.</div>';
  }
  eventsHtml += '</div></div>';

  /* Health context */
  var h = d.health || {};
  var healthHtml = '<div class="dr-section"><h4>Health context</h4><div class="dr-health">'
    + '<div class="it"><div class="l">Diabetes type</div><div class="v">' + _esc(h.diabetes_type || '—') + '</div></div>'
    + '<div class="it"><div class="l">Last A1C</div><div class="v">' + _esc(h.a1c || '—') + '</div></div>'
    + '<div class="it"><div class="l">Medication</div><div class="v">' + _esc(h.medication || '—') + '</div></div>'
    + '<div class="it"><div class="l">CGM device</div><div class="v">' + _esc(h.cgm || '—') + '</div></div>'
    + '</div></div>';

  /* Actions */
  var actionsHtml = '<div class="dr-actions">'
    + '<a href="/admin/member?id=' + d.id + '" class="btn pri">Full profile →</a>'
    + '<a href="/admin/inbox?member=' + d.id + '" class="btn">Open in inbox</a>'
    + '<a href="/admin/programs?member=' + d.id + '" class="btn">Assign program</a>'
    + '</div>';

  document.getElementById('drawerBody').innerHTML = kpiHtml + convHtml + eventsHtml + healthHtml + actionsHtml;
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
