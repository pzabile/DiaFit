<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();
$leadId = (int)$me['id'];

/* Load initial conversation */
$conversation = db_all(
    "SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id=? ORDER BY created_at ASC LIMIT 200",
    [$leadId]
);

$lastId      = $conversation ? (int)end($conversation)['id'] : 0;
$hasUnread   = $conversation && (int)end($conversation)['from_member'] === 0;
$lastPreview = $conversation ? mb_strimwidth(end($conversation)['body'], 0, 50, '…') : 'Start the conversation →';

$csrfToken   = csrf_token(); // returns the CSRF token string

$brandName   = cfg('brand_name') ?: 'DiaFitus';
$pageTitle   = 'Coach — ' . $brandName;
$bodyClass   = 'portal-page';
$activeTab   = 'chat';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">

  <div style="padding:18px 36px 0">
    <div class="eyebrow">Support</div>
    <h1 class="h1" style="margin:6px 0 4px">Talk to <em>your coach</em>.</h1>
    <p style="color:var(--muted);margin:0 0 18px;max-width:60ch;font-size:13.5px">Real humans, usually replying within a few hours · 7 days a week. Not for emergencies — for chest pain or severe hypoglycemia, call emergency services immediately.</p>
  </div>

  <div style="padding:0 36px 60px">
    <div class="coach-shell">

      <!-- Left: thread list -->
      <div class="coach-list">
        <div style="padding:14px;border-bottom:1px solid var(--line)">
          <input style="width:100%;border:1px solid var(--line);border-radius:9px;padding:8px 12px;font-size:13px;background:var(--card);outline:none" placeholder="Search messages…" id="chatSearch" oninput="filterBubbles(this.value)" />
        </div>

        <div class="thread active" style="position:relative">
          <div class="av" style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#9CC9A8,#3B6E54);display:grid;place-items:center;color:#fff;font-weight:600;font-size:12px;flex-shrink:0">MR</div>
          <div class="grow">
            <div class="thread-top">
              <span class="thread-name">Your coach</span>
              <span class="thread-when" id="threadWhen"><?php if ($conversation) echo date('g:i A', strtotime(end($conversation)['created_at'])); ?></span>
            </div>
            <div class="thread-preview" id="threadPreview"><?= e($lastPreview) ?></div>
          </div>
          <?php if ($hasUnread): ?><span class="thread-ud" id="threadUd">1</span><?php else: ?><span class="thread-ud" id="threadUd" style="display:none">1</span><?php endif; ?>
        </div>

        <div style="padding:16px;border-top:1px dashed var(--line);margin-top:auto">
          <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:600;color:var(--muted);margin-bottom:8px">Office hours</div>
          <div style="font-size:12.5px;color:var(--ink-2)">Mon–Sun · 7am – 11pm</div>
          <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Avg reply ~ 38 min</div>
        </div>
      </div>

      <!-- Center: chat pane -->
      <div class="chat-pane">

        <div class="chat-head">
          <div class="who">
            <div class="av">MR</div>
            <div>
              <div class="name"><?= e($brandName) ?> coach</div>
              <div class="role">CDE, NASM-CPT · DiaFitus team</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <div class="status" id="chatStatus"><span class="pip"></span>Active</div>
            <button class="icon-btn" title="Schedule a call" style="width:36px;height:36px;border-radius:10px;background:var(--card);border:1px solid var(--line);display:grid;place-items:center;color:var(--ink-2)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.12.91.34 1.79.66 2.62a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.46-1.23a2 2 0 012.11-.45c.83.32 1.71.54 2.62.66A2 2 0 0122 16.92z"/></svg>
            </button>
          </div>
        </div>

        <div class="msgs" id="msgArea">
          <?php if (!$conversation): ?>
            <div class="chat-empty" id="emptyState">
              <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>
              <p>No messages yet. Ask your coach anything about your plan, fueling, glucose, or schedule.</p>
            </div>
          <?php else:
            $lastDate = '';
            foreach ($conversation as $msg):
              $msgDate = date('l, F j', strtotime($msg['created_at']));
              $msgTime = date('g:i A', strtotime($msg['created_at']));
              $isMember = (int)$msg['from_member'] === 1;
              if ($msgDate !== $lastDate): $lastDate = $msgDate; ?>
            <div class="date-sep">— <?= e($msgDate) ?> —</div>
          <?php endif; ?>
            <div class="bubble <?= $isMember ? 'me' : 'them' ?>" data-id="<?= (int)$msg['id'] ?>">
              <?= nl2br(e($msg['body'])) ?>
              <span class="time"><?= $msgTime ?></span>
            </div>
          <?php endforeach; endif; ?>
          <div id="msgBottom"></div>
        </div>

        <div class="composer" id="chatComposer">
          <div class="tools">
            <button type="button" title="Attach photo" onclick="alert('File attachments coming soon.')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-10 9"/></svg>
            </button>
            <button type="button" title="Voice memo" onclick="alert('Voice messages coming soon.')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 12a7 7 0 0014 0M12 19v3"/></svg>
            </button>
          </div>
          <textarea id="msgInput" placeholder="Message your coach — about your plan, fueling, glucose, schedule…"></textarea>
          <button class="send" id="sendBtn" onclick="sendMessage()">Send <span style="font-family:'JetBrains Mono',monospace;font-size:11px;background:rgba(244,241,233,.18);padding:3px 6px;border-radius:5px">⏎</span></button>
        </div>

      </div>
    </div><!-- /coach-shell -->

    <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:16px;max-width:64ch;margin-left:auto;margin-right:auto;line-height:1.5">
      DiaFitus is fitness and lifestyle coaching. We are not your doctor. For chest pain, severe hypoglycemia, vision loss, or any other emergency, call your local emergency number immediately.
    </p>
  </div>

</main>
</div>

<script>
(function(){
  var CSRF   = <?= json_encode($csrfToken) ?>;
  var lastId = <?= $lastId ?>;
  var area   = document.getElementById('msgArea');
  var input  = document.getElementById('msgInput');
  var sendBtn= document.getElementById('sendBtn');
  var threadPreview = document.getElementById('threadPreview');
  var threadWhen    = document.getElementById('threadWhen');
  var threadUd      = document.getElementById('threadUd');
  var emptyState    = document.getElementById('emptyState');

  /* Scroll to bottom */
  function scrollBottom(){ area.scrollTop = area.scrollHeight; }
  scrollBottom();

  /* Format time */
  function fmtTime(iso){
    var d = new Date(iso.replace(' ','T'));
    var h = d.getHours(), m = d.getMinutes();
    return (h%12||12)+':'+(m<10?'0':'')+m+(h<12?' AM':' PM');
  }
  function fmtDate(iso){
    var d = new Date(iso.replace(' ','T'));
    var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    return days[d.getDay()]+', '+months[d.getMonth()]+' '+d.getDate();
  }

  /* Render one bubble */
  function renderBubble(msg){
    var isMember = msg.from_member == 1;
    var cls = isMember ? 'me' : 'them';
    var t   = fmtTime(msg.created_at);
    var el  = document.createElement('div');
    el.className = 'bubble '+cls;
    el.dataset.id = msg.id;
    /* Escape body */
    var body = msg.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    el.innerHTML = body + '<span class="time">'+t+'</span>';
    return el;
  }

  /* Add date separator if needed */
  var lastSepDate = '';
  function ensureDateSep(dateStr){
    if (dateStr === lastSepDate) return;
    lastSepDate = dateStr;
    var el = document.createElement('div');
    el.className = 'date-sep';
    el.textContent = '— '+dateStr+' —';
    var bottom = document.getElementById('msgBottom');
    area.insertBefore(el, bottom);
  }

  /* Append new messages from poll */
  function appendMessages(msgs){
    if (!msgs || !msgs.length) return;
    var bottom = document.getElementById('msgBottom');
    msgs.forEach(function(msg){
      ensureDateSep(fmtDate(msg.created_at));
      var bubble = renderBubble(msg);
      area.insertBefore(bubble, bottom);
      lastId = Math.max(lastId, parseInt(msg.id));
    });
    /* Update sidebar preview */
    var last = msgs[msgs.length-1];
    threadPreview.textContent = last.body.substring(0,50)+(last.body.length>50?'…':'');
    threadWhen.textContent    = fmtTime(last.created_at);
    /* Unread dot if from coach */
    if (last.from_member == 0) threadUd.style.display = '';
    if (emptyState) emptyState.remove();
    scrollBottom();
  }

  /* Send message via AJAX */
  window.sendMessage = function(){
    var body = input.value.trim();
    if (!body || sendBtn.disabled) return;
    sendBtn.disabled = true;
    input.value = '';
    fetch('/chat_api', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({body:body, csrf:CSRF})
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok && d.message) {
        appendMessages([d.message]);
        /* Clear unread since we just replied */
        threadUd.style.display = 'none';
      }
    })
    .catch(function(){ /* silently ignore, user can retry */ })
    .finally(function(){ sendBtn.disabled = false; input.focus(); });
  };

  /* Enter to send (Shift+Enter = newline) */
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); sendMessage(); }
  });

  /* Poll for new messages every 8 seconds */
  function poll(){
    fetch('/chat_api?since='+lastId)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok && d.messages && d.messages.length) {
        appendMessages(d.messages);
      }
    })
    .catch(function(){});
  }
  var poller = setInterval(poll, 8000);

  /* Clean up on page hide */
  document.addEventListener('visibilitychange', function(){
    if (document.hidden) clearInterval(poller);
    else { poll(); poller = setInterval(poll, 8000); }
  });

  /* Search filter */
  window.filterBubbles = function(q){
    q = q.toLowerCase();
    document.querySelectorAll('#msgArea .bubble').forEach(function(b){
      b.style.display = (!q || b.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
  };
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
