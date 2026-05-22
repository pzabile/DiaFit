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
$rows = db_all("SELECT id, first_name, email, phone, answers_json, created_at FROM leads WHERE {$where} ORDER BY id DESC LIMIT 500", $params);

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
      <div class="table-toolbar">
        <div class="left">
          <form method="get">
            <div class="toolbar-search">
              <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone…" autocomplete="off">
            </div>
          </form>
          <?php if ($q): ?>
            <a href="/admin/leads" class="chip">✕ Clear</a>
          <?php endif; ?>
          <div class="filter-pills">
            <span class="chip coral">🔥 Hot (&lt;12h)</span>
            <span class="chip amber">🌤 Warm (&lt;48h)</span>
            <span class="chip">Cool</span>
          </div>
        </div>
        <span class="muted" style="font-size:12.5px"><?= count($rows) ?> lead<?= count($rows) !== 1 ? 's' : '' ?></span>
      </div>

      <div class="card" style="overflow:hidden">
        <table class="tbl">
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
              $initials  = lead_initials($r['first_name']??'', $r['email']);
              $avColor   = lead_av_color($r['email']);
              [$heatCls, $heatLbl] = lead_heat($r['created_at']);
              $diabType  = $a['diabetes_type'] ?? '—';
              $goals     = is_array($a['goals']??null) ? implode(', ', array_slice($a['goals'],0,2)) : '—';
              $submittedAt = date('M j, Y · g:ia', strtotime($r['created_at']));
            ?>
              <tr>
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
                  <div style="display:flex;gap:6px;align-items:center">
                    <a href="mailto:<?= e($r['email']) ?>" class="btn sm">Send email</a>
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
