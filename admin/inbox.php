<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

/* ─── helpers ─── */
function ibx_initials(string $name, string $email): string {
    $s = trim($name);
    if ($s) {
        $p = preg_split('/\s+/', $s);
        if (count($p) >= 2) return strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1));
        return strtoupper(mb_substr($s,0,2));
    }
    return strtoupper(mb_substr($email,0,2));
}
function ibx_av_color(string $s): string {
    $colors = ['','sage','plum','coral','sky'];
    return $colors[abs(crc32($s)) % count($colors)];
}
function ibx_waiting_label(int $min): string {
    if ($min < 60) return $min . 'm';
    $h = intdiv($min,60); $m = $min % 60;
    if ($h < 24) return $m > 0 ? $h.'h '.$m.'m' : $h.'h';
    return intdiv($h,24).'d';
}
function ibx_waiting_cls(int $min): string {
    if ($min > 120) return 'coral';
    if ($min > 60)  return 'amber';
    return 'sage';
}
function ibx_plan_label(int $days): string {
    if ($days <= 7)  return '7-day';
    if ($days <= 28) return '4-wk';
    return '12-wk';
}

/* ─── handle reply submission ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lead_id']) && !empty($_POST['body'])) {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $flashError = 'Session expired.';
    } else {
        $leadId = (int)$_POST['lead_id'];
        $body   = trim($_POST['body']);
        if ($body) {
            db()->prepare("INSERT INTO coach_notes (lead_id, body, from_member, created_at) VALUES (?, ?, 0, NOW())")
                ->execute([$leadId, $body]);
        }
        header('Location: /admin/inbox?thread='.$leadId);
        exit;
    }
}

/* ─── data ─── */
$filter    = $_GET['f'] ?? 'waiting';   // waiting | all
$threadId  = (int)($_GET['thread'] ?? 0);

/* Thread list: conversations involving members (last message only) */
$threadList = db_all("
    SELECT l.id, l.first_name, l.email, l.plan_days, l.started_at,
           cn.body AS last_msg, cn.created_at AS last_msg_at,
           cn.from_member,
           TIMESTAMPDIFF(MINUTE, cn.created_at, NOW()) AS age_min,
           (
             SELECT COUNT(*) FROM coach_notes cn2
             WHERE cn2.lead_id = l.id AND cn2.from_member = 1
           ) AS total_msgs
    FROM leads l
    JOIN coach_notes cn ON cn.id = (SELECT MAX(id) FROM coach_notes WHERE lead_id = l.id)
    WHERE l.paid = 1
    ORDER BY cn.created_at DESC
    LIMIT 60
");

$waitingList = array_filter($threadList, fn($r) =>
    $r['from_member'] == 1 && !(false) /* last msg from member = waiting */
);

/* Use 'from_member' flag on the last message to determine if waiting */
$waitingList = array_filter($threadList, fn($r) => (int)$r['from_member'] === 1);
$waitingCount = count($waitingList);

if ($filter === 'waiting') {
    $displayList = array_values($waitingList);
    // Sort waiting by oldest first
    usort($displayList, fn($a,$b) => strcmp($a['last_msg_at'],$b['last_msg_at']));
} else {
    $displayList = array_values($threadList);
}

/* Active thread data */
$activeThread  = null;
$conversation  = [];
$activeMember  = null;

if ($threadId > 0) {
    $activeMember = db_get("SELECT id, first_name, email, plan_days, started_at, last_login_at FROM leads WHERE id=? AND paid=1", [$threadId]);
    if ($activeMember) {
        $conversation = db_all("SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id=? ORDER BY created_at ASC LIMIT 100", [$threadId]);
        // Find this thread in list
        foreach ($displayList as $t) {
            if ((int)$t['id'] === $threadId) { $activeThread = $t; break; }
        }
        if (!$activeThread) {
            // Thread might not be in filtered list; load it anyway
            $activeThread = db_get("SELECT l.id, l.first_name, l.email, l.plan_days, l.started_at, cn.from_member, cn.created_at AS last_msg_at, TIMESTAMPDIFF(MINUTE,cn.created_at,NOW()) AS age_min FROM leads l JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id) WHERE l.id=?", [$threadId]);
        }
    }
}

/* Auto-select first waiting if none chosen */
if (!$threadId && $displayList) {
    $first = $displayList[0];
    header('Location: /admin/inbox?thread='.(int)$first['id'].'&f='.$filter);
    exit;
}

$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];

$pageTitle = 'Inbox — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'inbox';
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
        <b>Inbox</b>
        <?php if ($waitingCount > 0): ?>
          <span class="chip coral" style="padding:2px 8px;font-size:11px"><?= $waitingCount ?> waiting</span>
        <?php endif; ?>
      </div>
    </header>

    <div style="padding:16px 36px 0;flex:1;display:flex;flex-direction:column;gap:0;min-height:0">
      <div class="inbox-shell">

        <!-- Thread list -->
        <div class="threads">
          <div class="threads-head">
            <h3>Inbox <span class="num"><?= $waitingCount ?> waiting · <?= count($threadList) ?> total</span></h3>
            <div class="threads-filter">
              <a href="/admin/inbox?f=waiting<?= $threadId ? '&thread='.$threadId : '' ?>" class="<?= $filter==='waiting' ? 'on' : '' ?>">Waiting</a>
              <a href="/admin/inbox?f=all<?= $threadId ? '&thread='.$threadId : '' ?>" class="<?= $filter==='all' ? 'on' : '' ?>">All</a>
            </div>
          </div>
          <div class="threads-list">
            <?php if (!$displayList): ?>
              <div style="padding:30px 16px;text-align:center;color:var(--muted);font-size:13px">
                <?= $filter==='waiting' ? 'No members waiting for a reply.' : 'No conversations yet.' ?>
              </div>
            <?php else: ?>
              <?php foreach ($displayList as $t):
                $initials = ibx_initials($t['first_name']??'', $t['email']);
                $avColor  = ibx_av_color($t['email']);
                $isActive = (int)$t['id'] === $threadId;
                $isWaiting = (int)$t['from_member'] === 1;
                $waitMin  = (int)$t['age_min'];
                $waitLbl  = ibx_waiting_label($waitMin);
                $waitCls  = ibx_waiting_cls($waitMin);
                $planDays = (int)($t['plan_days']??84);
                $planLbl  = ibx_plan_label($planDays);
                $msgTime  = date('g:ia', strtotime($t['last_msg_at']));
                $msgDay   = date('Ymd', strtotime($t['last_msg_at'])) !== date('Ymd') ? date('M j', strtotime($t['last_msg_at'])) : $msgTime;
              ?>
                <a href="/admin/inbox?thread=<?= (int)$t['id'] ?>&f=<?= urlencode($filter) ?>"
                   class="thread <?= $isActive ? 'active' : '' ?> <?= $isWaiting ? 'unread' : '' ?>">
                  <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                  <div>
                    <div class="top">
                      <span class="nm"><?= e($t['first_name'] ?: explode('@',$t['email'])[0]) ?></span>
                      <span class="when"><?= e($msgDay) ?></span>
                    </div>
                    <div class="preview"><?= e(mb_strimwidth($t['last_msg'],0,80,'…')) ?></div>
                    <div class="tags">
                      <span class="chip" style="padding:2px 7px;font-size:10.5px"><?= e($planLbl) ?></span>
                      <?php if ($isWaiting): ?>
                        <span class="waiting <?= $waitCls ?>"><?= e($waitLbl) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Conversation pane -->
        <div class="convo">
          <?php if (!$activeMember): ?>
            <div class="convo-empty">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>
              <p>Select a conversation to start replying.</p>
            </div>
          <?php else:
            $mbInitials = ibx_initials($activeMember['first_name']??'', $activeMember['email']);
            $mbAvColor  = ibx_av_color($activeMember['email']);
            $planDays   = (int)($activeMember['plan_days']??84);
            $planLbl    = ibx_plan_label($planDays);
            $planWeeks  = max(1, ceil($planDays/7));
            /* Week number */
            $weekNum = 1;
            if (!empty($activeMember['started_at'])) {
                $elapsed = floor((time() - strtotime($activeMember['started_at'])) / 86400);
                $weekNum = min($planWeeks, max(1, ceil(($elapsed+1)/7)));
            }
            $isWaiting = $activeThread && (int)($activeThread['from_member']??0) === 1;
            $waitMin   = $activeThread ? (int)$activeThread['age_min'] : 0;
          ?>
            <div class="convo-head">
              <div class="who">
                <div class="av <?= $mbAvColor ?>"><?= e($mbInitials) ?></div>
                <div>
                  <div class="name"><?= e($activeMember['first_name'] ?: explode('@',$activeMember['email'])[0]) ?></div>
                  <div class="role"><?= e($activeMember['email']) ?></div>
                </div>
              </div>
              <div class="actions">
                <?php if ($isWaiting): ?>
                  <span class="chip <?= ibx_waiting_cls($waitMin) ?> lg">Waiting · <?= ibx_waiting_label($waitMin) ?></span>
                <?php endif; ?>
                <a href="/admin/member?id=<?= (int)$activeMember['id'] ?>" class="btn sm">Open profile →</a>
              </div>
            </div>

            <div class="member-bar">
              <span>Plan · <b><?= e($planLbl) ?></b></span>
              <span class="sep"></span>
              <span>Week · <b><?= $weekNum ?> / <?= $planWeeks ?></b></span>
              <span class="sep"></span>
              <span>Started · <b><?= e($activeMember['started_at'] ? date('M j, Y', strtotime($activeMember['started_at'])) : 'not set') ?></b></span>
              <span class="sep"></span>
              <span>Last login · <b><?= e($activeMember['last_login_at'] ? date('M j', strtotime($activeMember['last_login_at'])) : 'never') ?></b></span>
            </div>

            <div class="msgs">
              <?php if (!$conversation): ?>
                <div class="empty">No messages yet. Start the conversation below.</div>
              <?php else:
                $lastDate = '';
                foreach ($conversation as $msg):
                  $msgDate = date('M j, Y', strtotime($msg['created_at']));
                  $msgTime = date('g:i A', strtotime($msg['created_at']));
                  $isMember = (int)$msg['from_member'] === 1;
                  if ($msgDate !== $lastDate):
                    $lastDate = $msgDate;
              ?>
                <div class="date-sep"><span><?= e($msgDate) ?></span></div>
              <?php endif; ?>
                <div class="bubble <?= $isMember ? 'them' : 'me' ?>">
                  <?= nl2br(e($msg['body'])) ?>
                  <span class="time"><?= $msgTime ?> · <?= $isMember ? e($activeMember['first_name'] ?: 'Member') : 'You' ?></span>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <div class="templates">
              <div class="templates-row">
                <button class="tpl" onclick="setTpl(this)">+ Acknowledge & reassure</button>
                <button class="tpl" onclick="setTpl(this)">+ Pre-workout fueling tip</button>
                <button class="tpl" onclick="setTpl(this)">+ Post-meal walk reminder</button>
                <button class="tpl" onclick="setTpl(this)">+ Schedule a call</button>
                <button class="tpl" onclick="setTpl(this)">+ Hypo response protocol</button>
                <button class="tpl" onclick="setTpl(this)">+ Travel adjustment</button>
              </div>
            </div>

            <form method="post" class="composer" id="replyForm">
              <?= csrf_input() ?>
              <input type="hidden" name="lead_id" value="<?= (int)$activeMember['id'] ?>">
              <textarea id="replyBody" name="body" placeholder="Reply to <?= e($activeMember['first_name'] ?: 'member') ?>…" rows="2" required></textarea>
              <button type="submit" class="send">Send</button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Context pane -->
        <?php if ($activeMember): ?>
        <aside class="ctx-pane">
          <div class="ctx-card">
            <h4>Member at a glance</h4>
            <div class="ctx-row"><span class="muted">Plan</span><span class="v"><?= e($planLbl) ?></span></div>
            <div class="ctx-row"><span class="muted">Started</span><span class="v"><?= e($activeMember['started_at'] ? date('M j, Y', strtotime($activeMember['started_at'])) : '—') ?></span></div>
            <div class="ctx-row"><span class="muted">Week</span><span class="v"><?= $weekNum ?> / <?= $planWeeks ?></span></div>
            <div class="ctx-row"><span class="muted">Last login</span><span class="v"><?= e($activeMember['last_login_at'] ? date('M j', strtotime($activeMember['last_login_at'])) : '—') ?></span></div>
            <div class="ctx-row"><span class="muted">Messages</span><span class="v"><?= count($conversation) ?></span></div>
          </div>
          <div class="ctx-card">
            <h4>Quick actions</h4>
            <a href="/admin/member?id=<?= (int)$activeMember['id'] ?>" class="btn" style="width:100%;margin-bottom:8px;justify-content:center">Full profile →</a>
            <a href="mailto:<?= e($activeMember['email']) ?>" class="btn" style="width:100%;justify-content:center">Send email</a>
          </div>
        </aside>
        <?php else: ?>
        <aside class="ctx-pane"></aside>
        <?php endif; ?>

      </div><!-- /inbox-shell -->
    </div>
  </main>
</div>
<script>
function setTpl(btn) {
  var label = btn.textContent.replace(/^\+\s*/,'');
  var ta = document.getElementById('replyBody');
  if (ta) { ta.focus(); if (!ta.value) ta.value = ''; }
}
/* Scroll msgs to bottom */
var msgs = document.querySelector('.msgs');
if (msgs) msgs.scrollTop = msgs.scrollHeight;
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
