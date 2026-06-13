<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

// Load all messages
$messages = db_all(
    'SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id = ? ORDER BY created_at ASC',
    [$leadId]
);

$lastId = $messages ? (int)end($messages)['id'] : 0;

// Last coach message preview
$lastCoachMsg = null;
foreach (array_reverse($messages) as $msg) {
    if ($msg['from_member'] == 0) { $lastCoachMsg = $msg; break; }
}

// Last member message time
$lastMemberMsg = null;
foreach (array_reverse($messages) as $msg) {
    if ($msg['from_member'] == 1) { $lastMemberMsg = $msg; break; }
}

// Count unread (coach msgs after last member msg)
$lastMemberId = $lastMemberMsg ? (int)$lastMemberMsg['id'] : 0;
$unread = 0;
foreach ($messages as $msg) {
    if ($msg['from_member'] == 0 && (int)$msg['id'] > $lastMemberId) $unread++;
}

// Group messages by date for date separators
$grouped = [];
foreach ($messages as $msg) {
    $d = date('Y-m-d', strtotime($msg['created_at']));
    $grouped[$d][] = $msg;
}

$pageTitle  = 'Coach — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'coach';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Coach</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <a href="/portal/log" class="quick-log">
        <span class="plus"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
        Quick log
      </a>
    </div>
  </header>

  <section class="view">
    <div class="eyebrow">Support</div>
    <h1 class="h1">Talk to <em>your coach</em>.</h1>
    <p class="muted" style="margin:0 0 18px;max-width:60ch">Real humans, usually replying within a few hours &middot; 7 days a week. Not for emergencies — for urgent symptoms, call your local emergency number.</p>

    <div class="coach-shell">
      <!-- Thread list -->
      <div class="coach-list">
        <div class="search"><input placeholder="Search messages…" oninput="filterMessages(this.value)"></div>
        <div class="thread active">
          <div class="av">C</div>
          <div class="grow">
            <div class="top">
              <span class="name">Your coach</span>
              <?php if ($lastCoachMsg): ?>
                <span class="when"><?= date('g:i A', strtotime($lastCoachMsg['created_at'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="preview"><?= $lastCoachMsg ? e(mb_substr($lastCoachMsg['body'], 0, 40)) . '…' : 'No messages yet' ?></div>
          </div>
          <?php if ($unread > 0): ?>
            <span class="ud"><?= $unread ?></span>
          <?php endif; ?>
        </div>

        <div style="padding:16px;border-top:1px dashed var(--line);margin-top:auto">
          <div class="muted" style="font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;font-weight:600;margin-bottom:8px">Office hours</div>
          <div style="font-size:12.5px;color:var(--ink-2)">Mon–Sun &middot; 7am – 11pm local</div>
          <div class="muted" style="font-size:11.5px;margin-top:2px">Avg reply ~ 38 min</div>
        </div>
      </div>

      <!-- Chat pane -->
      <div class="chat-pane">
        <div class="chat-head">
          <div class="who">
            <div class="av">C</div>
            <div>
              <div class="name">Your coach</div>
              <div class="role">DiaFit coach &middot; CDE</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <div class="status"><span class="pip"></span>Available</div>
          </div>
        </div>

        <div class="msgs" id="msgList">
          <?php
          $today = date('Y-m-d');
          $yesterday = date('Y-m-d', strtotime('-1 day'));
          foreach ($grouped as $date => $dayMsgs):
              if ($date === $today) $dateLabel = 'Today';
              elseif ($date === $yesterday) $dateLabel = 'Yesterday';
              else $dateLabel = date('l, M j', strtotime($date));
          ?>
            <div class="date-sep">— <?= e($dateLabel) ?> —</div>
            <?php foreach ($dayMsgs as $msg): ?>
              <div class="bubble <?= $msg['from_member'] ? 'me' : 'them' ?>" data-id="<?= (int)$msg['id'] ?>">
                <?= nl2br(e($msg['body'])) ?>
                <span class="time"><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>

          <?php if (!$messages): ?>
            <div style="text-align:center;padding:40px;color:var(--muted)">
              <div style="font-size:42px;margin-bottom:12px">💬</div>
              <div style="font-weight:600;font-size:15px;margin-bottom:6px">Start the conversation</div>
              <div style="font-size:13px">Ask about your program, fueling, glucose, or anything on your mind.</div>
            </div>
          <?php endif; ?>
        </div>

        <div class="composer">
          <div class="tools">
            <button type="button" title="Attach a photo">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-10 9"/></svg>
            </button>
          </div>
          <textarea id="msgInput" placeholder="Message your coach — about your plan, fueling, glucose, schedule…" rows="1"></textarea>
          <button class="send" id="sendBtn">Send <span class="k">⏎</span></button>
        </div>
      </div>
    </div>
  </section>
</main>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
let lastId = <?= $lastId ?>;
const msgList = document.getElementById('msgList');

function scrollToBottom() {
  msgList.scrollTop = msgList.scrollHeight;
}
scrollToBottom();

function addBubble(msg) {
  const isMe = msg.from_member == 1;
  const d = new Date(msg.created_at.replace(' ', 'T'));
  const time = d.toLocaleTimeString('en-US', {hour:'numeric',minute:'2-digit'});
  const div = document.createElement('div');
  div.className = 'bubble ' + (isMe ? 'me' : 'them');
  div.dataset.id = msg.id;
  div.innerHTML = msg.body.replace(/\n/g,'<br>') + `<span class="time">${time}</span>`;
  msgList.appendChild(div);
  scrollToBottom();
}

async function sendMessage() {
  const input = document.getElementById('msgInput');
  const body = input.value.trim();
  if (!body) return;
  input.value = '';
  input.disabled = true;
  try {
    const res = await fetch('/chat_api', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token': CSRF},
      body: JSON.stringify({body, csrf: CSRF})
    });
    const json = await res.json();
    if (json.ok) {
      addBubble(json.message);
      lastId = json.message.id;
    }
  } catch(e) {}
  input.disabled = false;
  input.focus();
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); sendMessage(); }
});

// Auto-resize textarea
document.getElementById('msgInput').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(120, this.scrollHeight) + 'px';
});

// Polling — every 8 seconds
let polling = true;
async function poll() {
  if (!polling) return;
  try {
    const res = await fetch('/chat_api?since=' + lastId);
    const json = await res.json();
    if (json.ok && json.messages && json.messages.length) {
      json.messages.forEach(msg => {
        if (msg.from_member == 0) addBubble(msg);
      });
      lastId = json.last_id;
    }
  } catch(e) {}
}
setInterval(poll, 8000);

// Pause when tab hidden
document.addEventListener('visibilitychange', () => {
  polling = !document.hidden;
  if (polling) poll();
});

function filterMessages(q) {
  const lq = q.toLowerCase();
  document.querySelectorAll('.bubble').forEach(b => {
    b.style.display = (!lq || b.textContent.toLowerCase().includes(lq)) ? '' : 'none';
  });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
