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
$threads = db_all("
    SELECT l.id, l.first_name, l.email, l.plan_days, l.started_at,
           cn.body AS last_msg, cn.from_member AS last_from,
           cn.created_at AS last_msg_at,
           (cn.from_member = 1 AND NOT EXISTS (
               SELECT 1 FROM coach_notes cn2
               WHERE cn2.lead_id = l.id AND cn2.from_member = 0
                 AND cn2.created_at > cn.created_at
           )) AS is_waiting,
           TIMESTAMPDIFF(MINUTE, cn.created_at, NOW()) AS waiting_min,
           (SELECT COUNT(*) FROM coach_notes cn3
            WHERE cn3.lead_id = l.id AND cn3.from_member = 1
              AND cn3.created_at > COALESCE(
                  (SELECT MAX(created_at) FROM coach_notes WHERE lead_id = l.id AND from_member = 0), '2000-01-01'
              )
           ) AS unread_count
    FROM leads l
    JOIN coach_notes cn ON cn.id = (SELECT MAX(id) FROM coach_notes WHERE lead_id = l.id)
    WHERE l.paid = 1
    ORDER BY cn.created_at DESC
    LIMIT 50
");

// Pre-select thread from URL
$selectedThreadId = (int)($_GET['thread'] ?? 0);
if (!$selectedThreadId && $threads) {
    $selectedThreadId = (int)$threads[0]['id'];
}

// Load conversation for selected thread
$conversation = [];
$selectedLead = null;
if ($selectedThreadId) {
    $selectedLead = db_get('SELECT * FROM leads WHERE id = ? AND paid = 1', [$selectedThreadId]);
    if ($selectedLead) {
        $conversation = db_all('SELECT * FROM coach_notes WHERE lead_id = ? ORDER BY created_at ASC', [$selectedThreadId]);
    }
}

// Counts for sidebar
$waitingCount = count(array_filter($threads, fn($t) => $t['is_waiting']));
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];

$lastId = $conversation ? (int)end($conversation)['id'] : 0;

$pageTitle = 'Inbox — DiaFit Admin';
$bodyClass = 'admin-page';
$activeTab = 'inbox';
require __DIR__ . '/../includes/header.php';
?>
<style>
/* Ensure convo pane fills height and msgs scrolls, composer stays visible */
.convo { overflow: hidden; }
.msgs { min-height: 0; }
</style>
<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
  <main class="main">
    <header class="topbar">
      <div class="crumb">
        <span class="dot"></span>
        <span>Admin</span>
        <span style="color:var(--line-2)">›</span>
        <b>Inbox</b>
      </div>
      <div class="top-actions">
        <div class="search-box">
          <input id="inboxSearch" placeholder="Search members, emails, plans…"/>
          <span class="k">⌘K</span>
        </div>
        <button class="icon-btn has-dot" title="Notifications">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16v-5a6 6 0 10-12 0v5l-2 3h16l-2-3z"/><path d="M10 21a2 2 0 004 0"/></svg>
        </button>
      </div>
    </header>

    <div class="view" style="padding:0;height:calc(100vh - 66px)">
      <div class="inbox-shell">

        <!-- THREADS PANE -->
        <div class="threads">
          <div class="threads-head">
            <h3>Inbox <span class="num"><?= $waitingCount ?> waiting · <?= count($threads) ?> total</span></h3>
            <div class="threads-filter">
              <button class="on" data-filter="all">All</button>
              <button data-filter="waiting">Waiting</button>
              <button data-filter="unread">Unread</button>
            </div>
          </div>
          <div class="threads-list" id="threadsList">
            <?php foreach ($threads as $t):
              $initials = adm_initials($t['first_name']??'', $t['email']);
              $avColor = adm_av_color($t['email']);
              $isWaiting = (bool)$t['is_waiting'];
              $isActive = (int)$t['id'] === $selectedThreadId;
              $planDays = (int)($t['plan_days']??84);
              $planLbl = $planDays<=28 ? '4-wk' : ($planDays<=56 ? '8-wk' : '12-wk');
            ?>
              <div class="thread <?= $isActive ? 'active' : '' ?> <?= $isWaiting ? 'unread' : '' ?>"
                   data-thread-id="<?= (int)$t['id'] ?>"
                   data-waiting="<?= $isWaiting ? '1' : '0' ?>"
                   data-unread="<?= $t['unread_count'] > 0 ? '1' : '0' ?>"
                   onclick="selectThread(<?= (int)$t['id'] ?>)">
                <div class="av <?= $avColor ?>"><?= e($initials) ?></div>
                <div class="grow">
                  <div class="top">
                    <span class="nm"><?= e($t['first_name'] ?: explode('@',$t['email'])[0]) ?></span>
                    <span class="when"><?= e(date('g:i A', strtotime($t['last_msg_at']))) ?></span>
                  </div>
                  <div class="preview"><?= e(mb_strimwidth($t['last_msg'],0,70,'…')) ?></div>
                  <div class="tags">
                    <span class="chip"><?= e($planLbl) ?></span>
                    <?php if ($isWaiting): $wmin=(int)$t['waiting_min']; $wcls=adm_waiting_cls($wmin); ?>
                      <span class="waiting <?= $wcls ?>"><?= adm_waiting_label($wmin) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- CONVERSATION PANE -->
        <div class="convo" id="convoPane">
          <a href="/admin/inbox" class="mob-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            All conversations
          </a>
          <?php if ($selectedLead):
            $prog = member_program_info($selectedLead);
            $avColor = adm_av_color($selectedLead['email']);
            $initials = adm_initials($selectedLead['first_name']??'', $selectedLead['email']);
            $avgGluc = db_get('SELECT AVG(bs_before) avg FROM daily_logs WHERE lead_id=? AND created_at>=NOW()-INTERVAL 7 DAY', [$selectedThreadId]);
            $lastLog = db_get('SELECT created_at FROM daily_logs WHERE lead_id=? ORDER BY created_at DESC LIMIT 1', [$selectedThreadId]);
          ?>
            <div class="convo-head">
              <div class="who">
                <div class="av <?= $avColor ?>" id="convoAv"><?= e($initials) ?></div>
                <div>
                  <div class="name" id="convoName"><?= e($selectedLead['first_name'] ?: explode('@',$selectedLead['email'])[0]) ?></div>
                  <div class="role">Member · <?= e($selectedLead['email']) ?></div>
                </div>
              </div>
              <div class="actions">
                <?php
                  $thisThr = array_values(array_filter($threads, fn($t) => (int)$t['id'] === $selectedThreadId));
                  if ($thisThr && $thisThr[0]['is_waiting']): $wmin=(int)$thisThr[0]['waiting_min']; ?>
                    <span class="chip <?= adm_waiting_cls($wmin) ?> lg">Waiting · <?= adm_waiting_label($wmin) ?></span>
                  <?php endif; ?>
                <button class="btn sm" onclick="openMemberDrawer(<?= (int)$selectedThreadId ?>)">Open profile →</button>
                <button class="icon-btn">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                </button>
              </div>
            </div>

            <div class="member-bar">
              <span>Plan · <b><?= e($prog['total'].'-'.$prog['label']) ?></b></span>
              <span class="sep"></span>
              <span>Week · <b><?= e($prog['current'].' / '.$prog['total']) ?></b></span>
              <span class="sep"></span>
              <span>Last log · <b><?= $lastLog ? e(date('M j · g:i A', strtotime($lastLog['created_at']))) : '—' ?></b></span>
              <?php if ($avgGluc && $avgGluc['avg']): ?>
                <span class="sep"></span>
                <span>Avg glucose 7d · <b><?= round($avgGluc['avg']) ?> mg/dL</b></span>
              <?php endif; ?>
            </div>

            <div class="msgs" id="msgs">
              <?php
              $prevDate = null;
              foreach ($conversation as $msg):
                $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                if ($msgDate !== $prevDate):
                  $prevDate = $msgDate;
                  $dateLabel = date('Ymd') === $msgDate ? 'Today' : (date('Ymd', strtotime('-1 day')) === $msgDate ? 'Yesterday' : date('M j', strtotime($msg['created_at'])));
              ?>
                <div class="date-sep"><span><?= e($dateLabel) ?></span></div>
              <?php endif; ?>
                <div class="bubble <?= $msg['from_member'] ? 'them' : 'me' ?>">
                  <?php if (!empty($msg['image_path'])): ?>
                    <img style="max-width:100%;border-radius:10px;display:block;cursor:pointer;margin-bottom:4px" src="/<?= e($msg['image_path']) ?>" alt="Attached image" onclick="window.open(this.src,'_blank')">
                  <?php endif; ?>
                  <?php if ($msg['body']): ?><?= nl2br(e($msg['body'])) ?><?php endif; ?>
                  <span class="time"><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="templates">
              <div class="templates-row">
                <button class="tpl sage">+ Acknowledge &amp; reassure</button>
                <button class="tpl">+ Pre-workout fueling tip</button>
                <button class="tpl">+ Post-meal walk reminder</button>
                <button class="tpl">+ Schedule a call</button>
                <button class="tpl">+ Hypo response protocol</button>
                <button class="tpl">+ Travel adjustment</button>
              </div>
            </div>

            <div class="composer">
              <div class="tools">
                <button title="Attach">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12l-9.5 9.5a4.5 4.5 0 11-6.4-6.4L14 6a3 3 0 014.2 4.2l-9 9a1.5 1.5 0 11-2.1-2.1L15 9"/></svg>
                </button>
              </div>
              <textarea id="composer" placeholder="Reply to <?= e($selectedLead['first_name'] ?: 'member') ?>…" rows="2"></textarea>
              <button class="send" id="sendBtn">Send <span class="kbd">⌘⏎</span></button>
            </div>
          <?php else: ?>
            <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:14px">Select a conversation</div>
          <?php endif; ?>
        </div>

        <!-- CTX PANE -->
        <aside class="ctx-pane" id="ctxPane">
          <?php if ($selectedLead):
            $answers = json_decode($selectedLead['answers_json']??'{}', true) ?: [];
          ?>
            <div class="ctx-card">
              <h4>Member at a glance</h4>
              <div class="row"><span class="muted">Plan</span><span class="v"><?= e($prog['total'].'-'.$prog['label']) ?></span></div>
              <div class="row"><span class="muted">Started</span><span class="v"><?= $selectedLead['started_at'] ? e(date('M j, Y', strtotime($selectedLead['started_at']))) : '—' ?></span></div>
              <div class="row"><span class="muted">Week</span><span class="v"><?= e($prog['current'].' / '.$prog['total']) ?></span></div>
              <?php if (!empty($answers['a1c'])): ?>
                <div class="row"><span class="muted">A1C</span><span class="v mono"><?= e($answers['a1c']) ?></span></div>
              <?php endif; ?>
            </div>
            <div class="ctx-card">
              <h4>Recent activity</h4>
              <?php $recentLogs = db_all('SELECT * FROM daily_logs WHERE lead_id=? ORDER BY created_at DESC LIMIT 3', [$selectedThreadId]); ?>
              <div style="font-size:12.5px;line-height:1.6">
                <?php foreach ($recentLogs as $l): ?>
                  <div style="padding:5px 0;border-bottom:1px dashed var(--line)">
                    <b><?= e(date('M j · g:i A', strtotime($l['created_at']))) ?></b> · <?= e($l['feeling']?:'—') ?><?= $l['bs_before'] ? ', glucose '.$l['bs_before'] : '' ?>
                  </div>
                <?php endforeach; ?>
                <?php if (!$recentLogs): ?><div style="color:var(--muted)">No logs yet</div><?php endif; ?>
              </div>
            </div>
            <div class="ctx-card">
              <h4>Quick actions</h4>
              <a href="/admin/members" class="btn sm" style="width:100%;margin-bottom:6px;justify-content:flex-start;display:flex">View profile →</a>
              <button class="btn sm" style="width:100%;justify-content:flex-start">Mark resolved</button>
            </div>
          <?php endif; ?>
        </aside>

      </div><!-- /inbox-shell -->
    </div>
  </main>
</div>

<!-- MEMBER DRAWER -->
<div class="drawer-scrim" id="drawerScrim" onclick="closeMemberDrawer()"></div>
<aside class="drawer" id="memberDrawer">
  <div class="drawer-head">
    <div class="who">
      <div class="av" id="drAv">?</div>
      <div>
        <h2 class="name" id="drName">Loading…</h2>
        <div class="sub" id="drSub"></div>
      </div>
    </div>
    <div class="row" style="gap:8px">
      <button class="icon-btn" onclick="closeMemberDrawer()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
  <div class="drawer-body" id="drawerBody"><div class="dr-loading">Loading…</div></div>
</aside>

<script>
const CSRF = '<?= csrf_token() ?>';
const currentThreadId = <?= (int)$selectedThreadId ?>;
let lastMsgId = <?= $lastId ?>;
let pollTimer = null;

// Thread selection
function selectThread(id) {
  location.href = '/admin/inbox?thread=' + id;
}

// Real-time polling (only when a thread is selected)
function startPoll() {
  if (!currentThreadId) return;
  if (pollTimer) return;
  pollTimer = setInterval(async () => {
    if (document.hidden) return;
    try {
      const r = await fetch('/admin/inbox_api?thread=' + currentThreadId + '&since=' + lastMsgId);
      if (!r.ok) return;
      const d = await r.json();
      if (d.ok && d.messages && d.messages.length) {
        d.messages.forEach(m => {
          // Skip messages we already rendered (our own sent messages have already been appended)
          if (document.querySelector('.bubble[data-id="' + m.id + '"]')) return;
          appendMessage(m);
        });
        lastMsgId = d.last_id;
      }
    } catch(e) { console.warn('poll error', e); }
  }, 3000);
}

function appendMessage(m) {
  const msgs = document.getElementById('msgs');
  if (!msgs) return;
  const div = document.createElement('div');
  div.className = 'bubble ' + (m.from_member ? 'them' : 'me');
  div.dataset.id = m.id;
  let html = '';
  if (m.image_path) {
    html += '<img style="max-width:100%;border-radius:10px;display:block;cursor:pointer;margin-bottom:4px" src="/' + escHtml(m.image_path) + '" alt="Attached image" onclick="window.open(this.src,\'_blank\')">';
  }
  if (m.body) html += escHtml(m.body).replace(/\n/g,'<br>');
  html += '<span class="time">' + escHtml(m.time||'') + '</span>';
  div.innerHTML = html;
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
}

async function sendMessage() {
  const ta = document.getElementById('composer');
  const body = ta ? ta.value.trim() : '';
  if (!body || !currentThreadId) return;
  const btn = document.getElementById('sendBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

  // Optimistically show the message immediately
  const tempId = 'temp_' + Date.now();
  const tempDiv = document.createElement('div');
  tempDiv.className = 'bubble me';
  tempDiv.dataset.id = tempId;
  tempDiv.style.opacity = '0.6';
  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
  tempDiv.innerHTML = escHtml(body).replace(/\n/g,'<br>') + '<span class="time">' + timeStr + '</span>';
  const msgs = document.getElementById('msgs');
  if (msgs) { msgs.appendChild(tempDiv); msgs.scrollTop = msgs.scrollHeight; }

  try {
    const r = await fetch('/admin/inbox_api', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
      body: JSON.stringify({thread: currentThreadId, body: body, csrf: CSRF})
    });
    const d = await r.json();
    if (d.ok && d.message) {
      // Replace temp bubble with confirmed one
      if (tempDiv.parentNode) tempDiv.remove();
      ta.value = '';
      ta.style.height = '';
      appendMessage(d.message);
      lastMsgId = d.message.id;
      const thr = document.querySelector('.thread[data-thread-id="' + currentThreadId + '"]');
      if (thr) {
        thr.classList.remove('unread');
        thr.dataset.waiting = '0';
        const w = thr.querySelector('.waiting');
        if (w) w.remove();
      }
    } else {
      // Failed — remove temp, restore text
      if (tempDiv.parentNode) tempDiv.remove();
      ta.value = body;
      console.error('Send failed:', d);
    }
  } catch(e) {
    if (tempDiv.parentNode) tempDiv.remove();
    ta.value = body;
    console.error('Send error:', e);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = 'Send <span class="kbd">⌘⏎</span>'; }
    if (ta) ta.focus();
  }
}

// Cmd+Enter / Ctrl+Enter to send
document.getElementById('composer')?.addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); sendMessage(); }
});
document.getElementById('sendBtn')?.addEventListener('click', sendMessage);

// Filter threads
document.querySelectorAll('.threads-filter button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.threads-filter button').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    const filter = btn.dataset.filter;
    document.querySelectorAll('.thread').forEach(t => {
      if (filter === 'all') t.style.display = '';
      else if (filter === 'waiting') t.style.display = t.dataset.waiting === '1' ? '' : 'none';
      else if (filter === 'unread') t.style.display = t.dataset.unread === '1' ? '' : 'none';
    });
  });
});

// Templates → append to composer
document.querySelectorAll('.tpl').forEach(t => t.addEventListener('click', () => {
  const ta = document.getElementById('composer');
  if (!ta) return;
  const text = t.textContent.replace(/^\+\s*/,'') + ' — ';
  ta.value = (ta.value ? ta.value + ' ' : '') + text;
  ta.focus();
}));

// Member drawer
const _drawerCache = {};
function openMemberDrawer(id) {
  document.body.classList.add('drawer-open');
  const body = document.getElementById('drawerBody');
  body.innerHTML = '<div class="dr-loading">Loading…</div>';
  if (_drawerCache[id]) { _populateDrawer(_drawerCache[id]); return; }
  fetch('/admin/member_data?id=' + id).then(r => r.json()).then(d => {
    _drawerCache[id] = d;
    _populateDrawer(d);
  });
}
function closeMemberDrawer() { document.body.classList.remove('drawer-open'); }
function _populateDrawer(d) {
  document.getElementById('drAv').textContent = d.initials;
  document.getElementById('drAv').className = 'av' + (d.av_color ? ' '+d.av_color : '');
  document.getElementById('drName').textContent = d.name;
  document.getElementById('drSub').innerHTML = escHtml(d.email) + ' · ' + escHtml(d.phone||'');
  document.getElementById('drawerBody').innerHTML =
    '<section>' +
    '<h4>Week ' + d.week_num + ' / ' + d.plan_weeks + ' · ' + escHtml(d.plan_label) + '</h4>' +
    '<div class="dr-kpi">' +
    '<div class="it"><div class="l">Week</div><div class="v">' + d.week_num + '<span style="font-family:Plus Jakarta Sans;font-size:13px;color:var(--muted)"> / ' + d.plan_weeks + '</span></div><div class="s">' + escHtml(d.started_at||'') + '</div></div>' +
    '<div class="it"><div class="l">Streak</div><div class="v">' + d.streak + 'd</div></div>' +
    '<div class="it"><div class="l">Avg glucose</div><div class="v">' + (d.avg_glucose||'—') + '<span style="font-family:Plus Jakarta Sans;font-size:11px;color:var(--muted)"> mg/dL</span></div></div>' +
    '<div class="it"><div class="l">Program</div><div class="v">' + (d.has_program?'✓':'—') + '</div></div>' +
    '</div></section>' +
    '<section>' +
    '<h4>Conversation</h4>' +
    '<div class="dr-conv">' + (d.messages||[]).map(m => '<div class="bubble ' + (m.from_member?'them':'me') + '">' + escHtml(m.body) + '<span class="time">' + escHtml(m.time||'') + '</span></div>').join('') + '</div>' +
    '</section>' +
    '<section>' +
    '<h4>Health context</h4>' +
    '<div class="dr-events">' +
    (d.health && d.health.diabetes_type ? '<div class="dr-event" style="grid-template-columns:1fr auto"><div class="nm">Diabetes type</div><span>' + escHtml(d.health.diabetes_type) + '</span></div>' : '') +
    (d.health && d.health.a1c ? '<div class="dr-event" style="grid-template-columns:1fr auto"><div class="nm">A1C</div><span class="mono">' + escHtml(d.health.a1c) + '</span></div>' : '') +
    (d.health && d.health.medication ? '<div class="dr-event" style="grid-template-columns:1fr auto"><div class="nm">Medication</div><span>' + escHtml(d.health.medication) + '</span></div>' : '') +
    '</div></section>' +
    '<section style="display:flex;gap:8px;flex-wrap:wrap">' +
    '<a href="/admin/inbox?thread=' + d.id + '" class="btn pri">Reply in inbox</a>' +
    '<button class="btn" onclick="closeMemberDrawer()">Close</button>' +
    '</section>';
}
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
document.addEventListener('keydown', ev => { if(ev.key==='Escape') closeMemberDrawer(); });

// Scroll to bottom on load
window.addEventListener('load', () => {
  const msgs = document.getElementById('msgs');
  if (msgs) msgs.scrollTop = msgs.scrollHeight;
  startPoll();
  // Mobile inbox: if a thread is selected, switch to convo view
  if (currentThreadId && window.innerWidth <= 900) {
    document.querySelector('.inbox-shell')?.classList.add('mob-convo');
  }
});
document.addEventListener('visibilitychange', () => {
  if (document.hidden) { clearInterval(pollTimer); pollTimer=null; }
  else startPoll();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
