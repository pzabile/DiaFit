<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

/* ─── helpers ─── */
function adm_initials(string $name, string $email): string {
    $s = trim($name);
    if ($s) {
        $p = preg_split('/\s+/', $s);
        if (count($p) >= 2) return strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1));
        return strtoupper(mb_substr($s,0,2));
    }
    return strtoupper(mb_substr($email,0,2));
}
function adm_av_color(string $s): string {
    $colors = ['','sage','plum','coral','sky'];
    return $colors[abs(crc32($s)) % count($colors)];
}
function adm_waiting_label(int $min): string {
    if ($min < 60) return $min . 'm';
    $h = intdiv($min,60); $m = $min % 60;
    if ($h < 24) return $m > 0 ? $h.'h '.$m.'m' : $h.'h';
    return intdiv($h,24).'d';
}
function adm_waiting_cls(int $min): string {
    if ($min > 120) return 'coral';
    if ($min > 60)  return 'amber';
    return 'sage';
}

/* ─── data ─── */
$dbError = null;
$stats = ['leads'=>0,'members'=>0,'members_7d'=>0,'leads_7d'=>0,'logs_today'=>0,'weeklies_7d'=>0];
$waitingRows = [];
$activity = [];
$hotLeads = 0;

try {
    $stats['leads']       = (int)db_get('SELECT COUNT(*) c FROM leads')['c'];
    $stats['members']     = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
    $stats['members_7d']  = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1 AND created_at >= NOW()-INTERVAL 7 DAY')['c'];
    $stats['leads_7d']    = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0 AND created_at >= NOW()-INTERVAL 7 DAY')['c'];
    $stats['logs_today']  = (int)db_get('SELECT COUNT(*) c FROM daily_logs WHERE log_date=CURDATE()')['c'];
    $stats['weeklies_7d'] = (int)db_get('SELECT COUNT(*) c FROM weekly_notes WHERE created_at >= NOW()-INTERVAL 7 DAY')['c'];

    $conversionRate = $stats['leads'] > 0 ? round(100 * $stats['members'] / $stats['leads'], 1) : 0;
    $mrr = $stats['members'] * (int)cfg('price_today');
    $hotLeads = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0 AND created_at >= CURDATE()')['c'];

    /* Waiting conversations (last msg from member, no admin reply after) */
    $waitingRows = db_all("
        SELECT l.id, l.first_name, l.email, l.plan_days, l.started_at,
               cn.body AS last_msg, cn.created_at AS last_msg_at,
               TIMESTAMPDIFF(MINUTE, cn.created_at, NOW()) AS waiting_min
        FROM leads l
        JOIN coach_notes cn ON cn.id = (SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)
        WHERE l.paid=1
          AND cn.from_member=1
          AND NOT EXISTS (
            SELECT 1 FROM coach_notes cn2
            WHERE cn2.lead_id=l.id AND cn2.from_member=0 AND cn2.created_at > cn.created_at
          )
        ORDER BY cn.created_at ASC
        LIMIT 20
    ");

    /* Activity stream */
    $rMsg     = db_all("SELECT cn.lead_id, cn.body, cn.created_at, l.first_name, l.email FROM coach_notes cn JOIN leads l ON l.id=cn.lead_id WHERE cn.from_member=1 AND cn.created_at >= NOW()-INTERVAL 7 DAY ORDER BY cn.created_at DESC LIMIT 8");
    $rWeekly  = db_all("SELECT wn.lead_id, wn.week_number, wn.created_at, l.first_name, l.email FROM weekly_notes wn JOIN leads l ON l.id=wn.lead_id WHERE wn.created_at >= NOW()-INTERVAL 7 DAY ORDER BY wn.created_at DESC LIMIT 6");
    $rDaily   = db_all("SELECT dl.lead_id, dl.log_date, dl.created_at, l.first_name, l.email FROM daily_logs dl JOIN leads l ON l.id=dl.lead_id WHERE dl.created_at >= NOW()-INTERVAL 7 DAY ORDER BY dl.created_at DESC LIMIT 6");

    foreach ($rMsg    as $r) $activity[] = ['t'=>$r['created_at'],'tag'=>'support','tag_cls'=>'amber','lead_id'=>$r['lead_id'],'who'=>$r['first_name']?:$r['email'],'text'=>'sent a message: '.mb_strimwidth($r['body'],0,70,'…')];
    foreach ($rWeekly as $r) $activity[] = ['t'=>$r['created_at'],'tag'=>'review','tag_cls'=>'','lead_id'=>$r['lead_id'],'who'=>$r['first_name']?:$r['email'],'text'=>'submitted week '.(int)$r['week_number'].' review'];
    foreach ($rDaily  as $r) $activity[] = ['t'=>$r['created_at'],'tag'=>'log','tag_cls'=>'','lead_id'=>$r['lead_id'],'who'=>$r['first_name']?:$r['email'],'text'=>'logged a daily check-in ('.$r['log_date'].')'];
    usort($activity, fn($a,$b) => strcmp($b['t'],$a['t']));
    $activity = array_slice($activity,0,8);

} catch (Throwable $ex) {
    $dbError = $ex->getMessage();
}

$waitingCount = count($waitingRows);
$membersCount = $stats['members'];
$leadsCount   = $stats['leads'] - $stats['members'];

/* Oldest waiting */
$oldestWho = '';
$oldestMin = 0;
if ($waitingRows) {
    $first = $waitingRows[0];
    $oldestWho = $first['first_name'] ?: explode('@',$first['email'])[0];
    $oldestMin = (int)$first['waiting_min'];
}

$pageTitle = 'Overview — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'home';
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
        <b>Overview</b>
      </div>
      <div class="top-actions">
        <?php if ($waitingCount > 0): ?>
          <a href="/admin/inbox" class="icon-btn has-dot" title="<?= $waitingCount ?> waiting for reply">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>
          </a>
        <?php endif; ?>
        <a href="/admin/new_member" class="btn pri">+ Add member</a>
      </div>
    </header>

    <div class="view">

      <?php if ($dbError): ?>
        <div style="background:#FDE8E6;border:1px solid #F5C0BA;border-radius:12px;padding:16px 20px;margin-bottom:20px;color:#7D2018;font-size:13.5px">
          <strong>Database not ready.</strong> Update <code>config.php</code> with your MySQL credentials and run <code>schema.sql</code> in phpMyAdmin.<br>
          <small style="opacity:.7"><?= e($dbError) ?></small>
        </div>
      <?php endif; ?>

      <!-- Hero -->
      <div class="over-hero">
        <div class="inner">
          <div>
            <div class="eyebrow"><?= date('l, M j · g:i A') ?></div>
            <?php if ($waitingCount > 0): ?>
              <h1><?= $waitingCount ?> member<?= $waitingCount !== 1 ? 's' : '' ?> <?php if ($waitingCount === 1): ?>is<?php else: ?>are<?php endif; ?> <em>waiting</em> to hear back.</h1>
              <p>Inbox is the top priority<?php if ($oldestWho): ?> — <?= e($oldestWho) ?>'s been waiting <?= adm_waiting_label($oldestMin) ?><?php endif; ?>. <?= $hotLeads > 0 ? $hotLeads.' lead'.($hotLeads!==1?'s':'').' from today are still warm.' : '' ?></p>
            <?php else: ?>
              <h1>All caught up — <em>great work.</em></h1>
              <p>No members are waiting on a reply right now.<?= $hotLeads > 0 ? ' '.$hotLeads.' new lead'.($hotLeads!==1?'s':'').' from today to follow up on.' : '' ?></p>
            <?php endif; ?>
            <div class="over-actions">
              <a href="/admin/inbox" class="btn pri">Open inbox →</a>
              <a href="/admin/members" class="btn">View members</a>
            </div>
          </div>
          <div class="atn-stack">
            <div class="atn-item">
              <div class="ic coral">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>
              </div>
              <div class="grow">
                <div class="ttl">Waiting on reply</div>
                <div class="sub"><?= $oldestWho ? 'Oldest · '.e($oldestWho).' · '.adm_waiting_label($oldestMin) : 'No pending replies' ?></div>
              </div>
              <div class="num"><?= $waitingCount ?></div>
            </div>
            <div class="atn-item">
              <div class="ic amber">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h13l3 3v13H4z"/><path d="M4 9h16"/></svg>
              </div>
              <div class="grow">
                <div class="ttl">Hot leads · unpaid</div>
                <div class="sub">Took the assessment today</div>
              </div>
              <div class="num"><?= $hotLeads ?></div>
            </div>
            <div class="atn-item">
              <div class="ic">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
              </div>
              <div class="grow">
                <div class="ttl">New members this week</div>
                <div class="sub">vs last 7 days</div>
              </div>
              <div class="num"><?= $stats['members_7d'] ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Grid -->
      <div class="kpi-grid">
        <div class="kpi">
          <div class="lbl">Paid members</div>
          <div class="num"><?= $stats['members'] ?><small>active</small></div>
          <div class="foot sage">+<?= $stats['members_7d'] ?> this week</div>
        </div>
        <div class="kpi">
          <div class="lbl">Est. MRR</div>
          <div class="num">$<?= number_format($mrr) ?></div>
          <div class="foot sage">at $<?= (int)cfg('price_today') ?>/mo</div>
        </div>
        <div class="kpi">
          <div class="lbl">Lead conversion</div>
          <div class="num"><?= $conversionRate ?><small>%</small></div>
          <div class="foot">all-time</div>
        </div>
        <div class="kpi">
          <div class="lbl">Inbox · waiting</div>
          <div class="num"><?= $waitingCount ?><small><?= $waitingCount === 1 ? 'member' : 'members' ?></small></div>
          <?php if ($waitingCount > 0): ?>
            <div class="foot coral">needs attention</div>
          <?php else: ?>
            <div class="foot sage">all clear</div>
          <?php endif; ?>
        </div>
        <div class="kpi">
          <div class="lbl">Logs · today</div>
          <div class="num"><?= $stats['logs_today'] ?></div>
          <div class="foot">daily check-ins</div>
        </div>
        <div class="kpi">
          <div class="lbl">Weekly reviews · 7d</div>
          <div class="num"><?= $stats['weeklies_7d'] ?></div>
          <div class="foot">check-ins submitted</div>
        </div>
      </div>

      <!-- Two col: inbox preview + activity -->
      <div class="col2">

        <!-- Inbox preview -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow">Awaiting your reply</div>
              <h3 class="h3" style="margin-top:4px">Inbox preview</h3>
            </div>
            <a href="/admin/inbox" class="btn sm">Open inbox →</a>
          </div>
          <div class="body" style="padding:0">
            <?php if (!$waitingRows): ?>
              <div style="padding:30px;text-align:center;color:var(--muted);font-size:13px">
                <div style="font-size:28px;margin-bottom:8px">✓</div>
                All caught up — no members waiting.
              </div>
            <?php else: ?>
              <table class="tbl">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Preview</th>
                    <th>Plan</th>
                    <th>Waiting</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($waitingRows,0,5) as $r):
                    $initials = adm_initials($r['first_name']??'', $r['email']);
                    $avColor  = adm_av_color($r['email']);
                    $planDays = (int)($r['plan_days']??84);
                    $planLbl  = $planDays<=7 ? '7-day' : ($planDays<=28 ? '4-wk' : '12-wk');
                    $waitMin  = (int)$r['waiting_min'];
                    $waitLbl  = adm_waiting_label($waitMin);
                    $waitCls  = adm_waiting_cls($waitMin);
                  ?>
                    <tr onclick="location.href='/admin/inbox?thread=<?= (int)$r['id'] ?>'" style="cursor:pointer">
                      <td>
                        <div class="name">
                          <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                          <div>
                            <div style="font-weight:600"><?= e($r['first_name'] ?: explode('@',$r['email'])[0]) ?></div>
                            <div class="em"><?= e($r['email']) ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="em" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        "<?= e(mb_strimwidth($r['last_msg'],0,80,'…')) ?>"
                      </td>
                      <td><span class="chip"><?= e($planLbl) ?></span></td>
                      <td><span class="chip <?= $waitCls ?>"><?= e($waitLbl) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Activity stream -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow">Last 7 days</div>
              <h3 class="h3" style="margin-top:4px">Activity stream</h3>
            </div>
          </div>
          <div class="body" style="padding:6px 16px 12px">
            <?php if (!$activity): ?>
              <div style="padding:20px 0;text-align:center;color:var(--muted);font-size:13px">No activity yet.</div>
            <?php else: ?>
              <div style="font-size:12.5px">
                <?php foreach ($activity as $a):
                  $when = date('g:ia', strtotime($a['t']));
                  $day  = date('Ymd', strtotime($a['t'])) === date('Ymd') ? 'Today' : date('M j', strtotime($a['t']));
                ?>
                  <div style="padding:9px 0;border-bottom:1px dashed var(--line);display:flex;gap:10px;align-items:center">
                    <span class="chip <?= $a['tag_cls'] ?>" style="padding:2px 8px;font-size:10.5px"><?= e($a['tag']) ?></span>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                      <a href="/admin/member?id=<?= (int)$a['lead_id'] ?>" style="font-weight:600;color:var(--sage-2)"><?= e($a['who']) ?></a>
                      <?= e($a['text']) ?>
                    </span>
                    <span class="mono muted" style="font-size:11px;white-space:nowrap"><?= $day !== 'Today' ? $day : $when ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /col2 -->

    </div><!-- /view -->
  </main>
</div>
<script>
// Live updates: poll every 12s for new waiting messages
(function(){
  const badge = document.querySelector('.nav a[href="/admin/inbox"] .badge');

  async function pollCounts() {
    try {
      const r = await fetch('/admin/live_counts');
      const d = await r.json();
      const w = d.waiting || 0;

      // Update inbox badge in sidebar
      if (badge) { badge.textContent = w; badge.style.display = w > 0 ? '' : 'none'; }

      // Update hero headline
      const heroH1 = document.querySelector('.over-hero h1');
      if (heroH1) {
        if (w > 0) heroH1.innerHTML = w + ' member' + (w !== 1 ? 's' : '') + ' ' + (w === 1 ? 'is' : 'are') + ' <em>waiting</em> to hear back.';
        else heroH1.innerHTML = 'All caught up &mdash; <em>great work.</em>';
      }

      // Update first KPI box (waiting count)
      const waitKpi = document.querySelector('.kpi-grid .kpi:nth-child(4) .num');
      if (waitKpi) waitKpi.innerHTML = w + '<small>' + (w === 1 ? 'member' : 'members') + '</small>';
    } catch(e) {}
  }

  setInterval(pollCounts, 12000);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
