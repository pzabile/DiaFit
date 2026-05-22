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
    return '12-wk';
}
function mem_plan_cls(int $days): string {
    if ($days <= 7)  return 'sky';
    if ($days <= 28) return 'amber';
    return 'sage';
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = 'paid = 1';
if ($q !== '') {
    $where .= ' AND (email LIKE ? OR first_name LIKE ? OR phone LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}

$rows = db_all("
    SELECT l.id, l.first_name, l.email, l.phone, l.started_at, l.plan_days, l.program_path, l.last_login_at, l.created_at,
           (SELECT COUNT(*) FROM coach_notes cn WHERE cn.lead_id=l.id AND cn.from_member=1) AS msg_count,
           (SELECT MAX(cn2.id) FROM coach_notes cn2 WHERE cn2.lead_id=l.id) AS last_note_id,
           (SELECT cn3.from_member FROM coach_notes cn3 WHERE cn3.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)) AS last_from_member
    FROM leads l
    WHERE {$where}
    ORDER BY l.id DESC
    LIMIT 500
", $params);

/* Sidebar badge counts */
$waitingCount = 0;
foreach ($rows as $r) {
    if ((int)($r['last_from_member']??0) === 1) $waitingCount++;
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
        <a href="/admin/new_member" class="btn pri">+ Add member</a>
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
            <a href="/admin/members" class="chip">✕ Clear</a>
          <?php endif; ?>
        </div>
        <span class="muted" style="font-size:12.5px"><?= count($rows) ?> member<?= count($rows) !== 1 ? 's' : '' ?></span>
      </div>

      <div class="card" style="overflow:hidden">
        <table class="tbl">
          <thead>
            <tr>
              <th>Member</th>
              <th>Plan</th>
              <th>Started</th>
              <th>Ends</th>
              <th>Program</th>
              <th>Last login</th>
              <th>Messages</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:40px">
                <?= $q ? 'No members match that search.' : 'No paid members yet.' ?>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
              $planDays = (int)($r['plan_days']??84);
              $planLbl  = mem_plan_label($planDays);
              $planCls  = mem_plan_cls($planDays);
              $initials = mem_initials($r['first_name']??'', $r['email']);
              $avColor  = mem_av_color($r['email']);
              $endDate  = '—';
              if (!empty($r['started_at'])) {
                  try {
                      $d = new DateTime($r['started_at']);
                      $d->modify('+' . $planDays . ' days');
                      $endDate = $d->format('M j, Y');
                  } catch (Exception $ignored) {}
              }
              $hasProgram  = !empty($r['program_path']);
              $msgCount    = (int)($r['msg_count']??0);
              $isWaiting   = (int)($r['last_from_member']??0) === 1;
              $lastLoginAt = $r['last_login_at'] ? date('M j', strtotime($r['last_login_at'])) : '—';
              $startedAt   = $r['started_at'] ? date('M j, Y', strtotime($r['started_at'])) : '—';
            ?>
              <tr onclick="location.href='/admin/member?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
                <td>
                  <div class="name">
                    <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                    <div>
                      <div style="font-weight:600"><?= e($r['first_name'] ?: '—') ?></div>
                      <div class="em"><?= e($r['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="chip <?= $planCls ?>"><?= e($planLbl) ?></span></td>
                <td style="color:var(--muted);font-size:12.5px"><?= e($startedAt) ?></td>
                <td style="color:var(--muted);font-size:12.5px"><?= e($endDate) ?></td>
                <td>
                  <?php if ($hasProgram): ?>
                    <span class="chip sage">uploaded</span>
                  <?php else: ?>
                    <span class="chip" style="color:var(--muted)">pending</span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:12.5px"><?= e($lastLoginAt) ?></td>
                <td>
                  <?php if ($msgCount > 0): ?>
                    <span class="chip <?= $isWaiting ? 'coral' : '' ?>">
                      <?php if ($isWaiting): ?>⬤ <?php endif; ?><?= $msgCount ?>
                    </span>
                  <?php else: ?>
                    <span class="muted" style="font-size:12px">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isWaiting): ?>
                    <span class="chip coral">Waiting</span>
                  <?php elseif ($hasProgram): ?>
                    <span class="chip sage">On track</span>
                  <?php else: ?>
                    <span class="chip amber">No plan</span>
                  <?php endif; ?>
                </td>
                <td><span class="open">Open →</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
