<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$rows = db_all("SELECT id, first_name, email, plan_days, program_path, started_at FROM leads WHERE paid=1 AND program_path IS NOT NULL AND program_path != '' ORDER BY id DESC LIMIT 200");

$waitingCount = (int)db_get("SELECT COUNT(DISTINCT l.id) c FROM leads l JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id) WHERE l.paid=1 AND cn.from_member=1")['c'];
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];

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
        <a href="/admin/members" class="btn">View members →</a>
      </div>
    </header>

    <div class="view">
      <div style="margin-bottom:18px">
        <div class="eyebrow">Content</div>
        <h2 style="font-family:'Instrument Serif',serif;font-size:34px;letter-spacing:-.02em;font-weight:400;margin:6px 0 4px">Uploaded <em style="font-style:italic;color:var(--sage-2)">programs</em></h2>
        <p class="muted" style="font-size:13.5px;margin:0">PDFs assigned to each member. Upload or replace from the member profile page.</p>
      </div>

      <?php if (!$rows): ?>
        <div class="card" style="padding:40px;text-align:center;color:var(--muted)">
          <div style="font-size:32px;margin-bottom:12px">📋</div>
          <p style="font-size:14px;margin:0">No programs uploaded yet. Open a member profile and upload their PDF plan.</p>
          <a href="/admin/members" class="btn pri" style="margin-top:16px;display:inline-flex">View members →</a>
        </div>
      <?php else: ?>
        <div class="card" style="overflow:hidden">
          <table class="tbl">
            <thead>
              <tr>
                <th>Member</th>
                <th>Plan</th>
                <th>Started</th>
                <th>Program file</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r):
                $planDays = (int)($r['plan_days']??84);
                $planLbl  = $planDays<=7 ? '7-day' : ($planDays<=28 ? '4-wk' : '12-wk');
                $planCls  = $planDays<=7 ? 'sky' : ($planDays<=28 ? 'amber' : 'sage');
                $filename = basename($r['program_path']??'');
                $started  = $r['started_at'] ? date('M j, Y', strtotime($r['started_at'])) : '—';
              ?>
                <tr onclick="location.href='/admin/member?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
                  <td>
                    <div class="name">
                      <div class="av"><?= strtoupper(mb_substr($r['first_name']??$r['email'],0,2)) ?></div>
                      <div>
                        <div style="font-weight:600"><?= e($r['first_name'] ?: '—') ?></div>
                        <div class="em"><?= e($r['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="chip <?= $planCls ?>"><?= e($planLbl) ?></span></td>
                  <td style="font-size:12.5px;color:var(--muted)"><?= e($started) ?></td>
                  <td style="font-size:12.5px;color:var(--muted);font-family:'JetBrains Mono',monospace"><?= e($filename) ?></td>
                  <td><span class="open">Open profile →</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
