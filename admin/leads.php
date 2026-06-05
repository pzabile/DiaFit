<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

function lead_initials(string $name, string $email): string {
    $s = trim($name);
    if ($s) {
        $p = preg_split('/\s+/', $s);
        if (count($p) >= 2) return strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1));
        return strtoupper(mb_substr($s,0,2));
    }
    return strtoupper(mb_substr($email,0,2));
}
function lead_av_color(string $s): string {
    $colors = ['','sage','plum','coral','sky'];
    return $colors[abs(crc32($s)) % count($colors)];
}
function lead_heat(string $createdAt): array {
    $ageH = (time() - strtotime($createdAt)) / 3600;
    if ($ageH < 12) return ['coral', 'Hot'];
    if ($ageH < 48) return ['amber', 'Warm'];
    return ['', 'Cool'];
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = 'paid = 0';
if ($q !== '') {
    $where .= ' AND (email LIKE ? OR first_name LIKE ? OR phone LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$rows = db_all("SELECT id, first_name, email, phone, answers_json, created_at,
    COALESCE(email_sent_count,0) AS email_sent_count,
    COALESCE(opted_out,0) AS opted_out
    FROM leads WHERE {$where} ORDER BY id DESC LIMIT 500", $params);

/* ── CSV export ── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Email','Name','Phone','Diabetes type','Goals','Submitted','Heat']);
    foreach ($rows as $r) {
        $a = json_decode($r['answers_json']??'{}', true) ?: [];
        [$heatCls, $heatLbl] = lead_heat($r['created_at']);
        fputcsv($out, [
            $r['id'], $r['email'], $r['first_name']??'', $r['phone']??'',
            $a['diabetes_type']??'',
            is_array($a['goals']??null) ? implode(', ', $a['goals']) : '',
            $r['created_at'], $heatLbl,
        ]);
    }
    fclose($out);
    exit;
}

/* Sidebar badge counts */
$waitingCount = (int)db_get("
    SELECT COUNT(DISTINCT l.id) c FROM leads l
    JOIN coach_notes cn ON cn.id = (SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)
    WHERE l.paid=1 AND cn.from_member=1
")['c'];
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = count($rows);

$pageTitle = 'Leads — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'leads';
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
        <b>Leads (unpaid)</b>
      </div>
    </header>

    <div class="view">

      <!-- Header -->
      <div class="row" style="justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:14px;margin-bottom:18px">
        <div>
          <div class="eyebrow" style="color:var(--plum)">Assessments without payment</div>
          <h1 class="h1">Warm <em>leads</em>.</h1>
          <p class="muted" style="margin:0;max-width:60ch">Took the assessment but haven't subscribed yet. Reach out within 24 hours — that's when conversion peaks.</p>
        </div>
        <div class="row" style="gap:10px">
          <a href="/admin/leads?<?= $q ? 'q='.urlencode($q).'&' : '' ?>export=csv" class="btn">Export CSV</a>
          <button class="btn pri" onclick="location.href='/admin/leads'">+ Send follow-ups</button>
        </div>
      </div>

      <div class="table-toolbar">
        <div class="left">
          <form method="get" style="display:contents">
            <div class="toolbar-search">
              <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search leads…" autocomplete="off">
            </div>
          </form>
          <?php if ($q): ?>
            <a href="/admin/leads" class="chip">✕ Clear</a>
          <?php endif; ?>
          <div class="filter-pills" id="leadFilter">
            <button class="chip ink" onclick="filterLeads('all',this)">All · <?= count($rows) ?></button>
            <button class="chip coral" onclick="filterLeads('hot',this)">Hot (&lt;12h)</button>
            <button class="chip amber" onclick="filterLeads('warm',this)">Warm (&lt;48h)</button>
            <button class="chip" onclick="filterLeads('cool',this)">Cool</button>
          </div>
        </div>
        <span class="muted" style="font-size:12px">Sorted by · most recent submission</span>
      </div>

      <div class="card" style="overflow:hidden">
        <table class="tbl" id="leadTbl">
          <thead>
            <tr>
              <th>Lead</th>
              <th>Heat</th>
              <th>Type</th>
              <th>Goal</th>
              <th>Phone</th>
              <th>Submitted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">
                <?= $q ? 'No leads match that search.' : 'No leads yet.' ?>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
              $a = json_decode($r['answers_json']??'{}', true) ?: [];
              $initials    = lead_initials($r['first_name']??'', $r['email']);
              $avColor     = lead_av_color($r['email']);
              [$heatCls, $heatLbl] = lead_heat($r['created_at']);
              $diabType    = $a['diabetes_type'] ?? '—';
              $goals       = is_array($a['goals']??null) ? implode(', ', array_slice($a['goals'],0,2)) : '—';
              $submittedAt = date('M j, Y · g:ia', strtotime($r['created_at']));
              $heatFilter  = strtolower($heatLbl);
              $sentCount   = (int)$r['email_sent_count'];
              $isOptedOut  = !empty($r['opted_out']);
            ?>
              <tr data-heat="<?= $heatFilter ?>">
                <td>
                  <div class="name">
                    <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                    <div>
                      <div style="font-weight:600"><?= e($r['first_name'] ?: '—') ?></div>
                      <div class="em"><?= e($r['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="chip <?= $heatCls ?>"><?= $heatLbl ?></span></td>
                <td style="font-size:12.5px;color:var(--ink-2)"><?= e($diabType) ?></td>
                <td style="font-size:12.5px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($goals) ?></td>
                <td style="font-size:12.5px;color:var(--muted)"><?= e($r['phone']??'—') ?></td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= e($submittedAt) ?></td>
                <td>
                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <?php if ($isOptedOut): ?>
                      <span style="font-size:11px;padding:3px 8px;border-radius:99px;background:#fee2e2;color:#b91c1c;font-weight:600">Opted out</span>
                      <button class="btn sm" onclick="optOutLead(this,<?= (int)$r['id'] ?>,0)" title="Re-subscribe">↩ Re-sub</button>
                    <?php else: ?>
                      <button class="btn sm" onclick="sendLeadEmail(this,<?= (int)$r['id'] ?>,<?= $sentCount ?>)">
                        <?= $sentCount > 0 ? 'Resend (' . $sentCount . '×)' : 'Send email' ?>
                      </button>
                      <button class="btn sm" onclick="optOutLead(this,<?= (int)$r['id'] ?>,1)"
                        style="padding:4px 8px;background:transparent;color:var(--muted);border:1px solid var(--line)"
                        title="Mark as opted out">⊘</button>
                    <?php endif; ?>
                    <a href="/admin/member?id=<?= (int)$r['id'] ?>" class="btn sm sage">Convert →</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script>
function filterLeads(f, btn) {
  document.querySelectorAll('#leadFilter .chip').forEach(function(b){ b.classList.remove('ink'); });
  btn.classList.add('ink');
  document.querySelectorAll('#leadTbl tbody tr[data-heat]').forEach(function(row){
    row.style.display = (f === 'all' || row.dataset.heat === f) ? '' : 'none';
  });
}
function sendLeadEmail(btn, leadId, prevCount) {
  btn.disabled = true;
  btn.textContent = 'Sending…';
  var fd = new FormData();
  fd.append('lead_id', leadId);
  fetch('/admin/lead_email', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        var n = d.sent_count || (prevCount + 1);
        btn.textContent = '✓ Sent (' + n + '×)';
        btn.style.background = 'var(--sage)';
        btn.style.color = '#fff';
        btn.onclick = function(){ return sendLeadEmail(btn, leadId, n); };
        btn.disabled = false;
      } else {
        btn.textContent = prevCount > 0 ? 'Resend (' + prevCount + '×)' : 'Send email';
        btn.disabled = false;
        alert('Error: ' + (d.error || 'Unknown error'));
      }
    })
    .catch(function(){ btn.textContent = 'Error'; btn.disabled = false; });
}
function optOutLead(btn, leadId, newState) {
  btn.disabled = true;
  var fd = new FormData();
  fd.append('lead_id', leadId);
  fd.append('opted_out', newState);
  fetch('/admin/lead_optout', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) { location.reload(); }
      else { btn.disabled = false; alert('Error: ' + (d.error || 'Unknown')); }
    })
    .catch(function(){ btn.disabled = false; });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
