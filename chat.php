<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();
$leadId = (int)$me['id'];

/* ── handle message send ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check($_POST['csrf'] ?? '') && !empty(trim($_POST['body'] ?? ''))) {
        $body = trim($_POST['body']);
        db()->prepare("INSERT INTO coach_notes (lead_id, body, from_member, created_at) VALUES (?, ?, 1, NOW())")
            ->execute([$leadId, $body]);
    }
    header('Location: /chat'); exit;
}

/* ── load conversation ── */
$conversation = db_all(
    "SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id=? ORDER BY created_at ASC LIMIT 200",
    [$leadId]
);

/* unread: last message is from coach */
$hasUnread = false;
$lastPreview = 'Start the conversation →';
if ($conversation) {
    $last = end($conversation);
    $hasUnread = (int)$last['from_member'] === 0;
    $lastPreview = mb_strimwidth($last['body'], 0, 50, '…');
}

$brandName = cfg('brand_name') ?: 'DiaFitus';
$pageTitle = 'Coach — ' . $brandName;
$bodyClass = 'portal-page';
$activeTab = 'chat';
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

      <!-- ── Left: thread list ── -->
      <div class="coach-list">
        <div style="padding:14px;border-bottom:1px solid var(--line)">
          <input style="width:100%;border:1px solid var(--line);border-radius:9px;padding:8px 12px;font-size:13px;background:var(--card);outline:none" placeholder="Search messages…" oninput="filterBubbles(this.value)" />
        </div>

        <!-- Coach thread -->
        <div class="thread active">
          <div class="av" style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#9CC9A8,#3B6E54);display:grid;place-items:center;color:#fff;font-weight:600;font-size:12px;flex-shrink:0">MR</div>
          <div class="grow">
            <div class="thread-top">
              <span class="thread-name">Your coach</span>
              <?php if ($conversation): ?>
              <span class="thread-when"><?= date('g:i A', strtotime(end($conversation)['created_at'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="thread-preview"><?= e($lastPreview) ?></div>
          </div>
          <?php if ($hasUnread): ?><span class="thread-ud">1</span><?php endif; ?>
        </div>

        <!-- Office hours footer -->
        <div style="padding:16px;border-top:1px dashed var(--line);margin-top:auto">
          <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:600;color:var(--muted);margin-bottom:8px">Office hours</div>
          <div style="font-size:12.5px;color:var(--ink-2)">Mon–Sun · 7am – 11pm</div>
          <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Avg reply ~ 38 min</div>
        </div>
      </div>

      <!-- ── Center: chat pane ── -->
      <div class="chat-pane">

        <div class="chat-head">
          <div class="who">
            <div class="av">MR</div>
            <div>
              <div class="name"><?= e(cfg('brand_name') ?: 'Your Coach') ?></div>
              <div class="role">Diafitus coach · CDE, NASM-CPT</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <div class="status"><span class="pip"></span>Active</div>
            <button class="icon-btn" title="Schedule a call" style="width:36px;height:36px;border-radius:10px;background:var(--card);border:1px solid var(--line);display:grid;place-items:center;color:var(--ink-2)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.12.91.34 1.79.66 2.62a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.46-1.23a2 2 0 012.11-.45c.83.32 1.71.54 2.62.66A2 2 0 0122 16.92z"/></svg>
            </button>
          </div>
        </div>

        <div class="msgs" id="msgArea">
          <?php if (!$conversation): ?>
            <div class="chat-empty">
              <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>
              <p>No messages yet. Ask your coach anything about your plan, fueling, glucose, or schedule.</p>
            </div>
          <?php else:
            $lastDate = '';
            foreach ($conversation as $msg):
              $msgDate = date('l, F j', strtotime($msg['created_at']));
              $msgTime = date('g:i A', strtotime($msg['created_at']));
              $isMember = (int)$msg['from_member'] === 1;
              if ($msgDate !== $lastDate):
                $lastDate = $msgDate;
          ?>
            <div class="date-sep">— <?= e($msgDate) ?> —</div>
          <?php endif; ?>
            <div class="bubble <?= $isMember ? 'me' : 'them' ?>">
              <?= nl2br(e($msg['body'])) ?>
              <span class="time"><?= $msgTime ?></span>
            </div>
          <?php endforeach; endif; ?>
          <div id="bottom"></div>
        </div>

        <form method="post" class="composer" id="chatForm">
          <?= csrf_input() ?>
          <div class="tools">
            <button type="button" title="Attach photo">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-10 9"/></svg>
            </button>
            <button type="button" title="Voice memo">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 12a7 7 0 0014 0M12 19v3"/></svg>
            </button>
          </div>
          <textarea name="body" id="msgInput" placeholder="Message your coach — about your plan, fueling, glucose, schedule…" required></textarea>
          <button type="submit" class="send">Send <span style="font-family:'JetBrains Mono',monospace;font-size:11px;background:rgba(244,241,233,.18);padding:3px 6px;border-radius:5px">⏎</span></button>
        </form>

      </div>
    </div><!-- /coach-shell -->

    <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:16px;max-width:64ch;margin-left:auto;margin-right:auto;line-height:1.5">
      DiaFitus is fitness and lifestyle coaching. We are not your doctor. For chest pain, severe hypoglycemia, vision loss, or any other emergency, call your local emergency number immediately.
    </p>
  </div>

</main>
</div>

<script>
/* Scroll to bottom on load */
(function(){
  var a = document.getElementById('msgArea');
  if (a) a.scrollTop = a.scrollHeight;
})();

/* Send on Enter (not Shift+Enter) */
document.getElementById('msgInput').addEventListener('keydown', function(e){
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    var v = this.value.trim();
    if (v) document.getElementById('chatForm').submit();
  }
});

/* Basic search filter */
function filterBubbles(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#msgArea .bubble').forEach(function(b){
    b.style.display = (!q || b.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
