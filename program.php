<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$allNotes = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 ORDER BY created_at ASC', [$me['id']]);

$generalNotes = [];
$targetIndex  = [];
$replyIndex   = [];
foreach ($allNotes as $n) {
    if ($n['parent_id']) { $replyIndex[(int)$n['parent_id']][] = $n; continue; }
    if ($n['target_type'] && $n['target_id']) {
        $targetIndex[$n['target_type']][(int)$n['target_id']][] = $n;
    } else {
        $generalNotes[] = $n;
    }
}
$generalNotes = array_reverse($generalNotes);

$pageTitle = 'My Program — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'program';
require __DIR__ . '/includes/header.php';
$csrf = csrf_input();
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px">
      <div>
        <p class="eyebrow sage">Your plan</p>
        <h1 class="h2 serif" style="margin-top:4px">My personalized program</h1>
      </div>
      <?php if (!empty($me['program_path'])): ?>
        <a href="<?= e($me['program_path']) ?>" target="_blank" style="background:var(--ink);color:#F4F1E9;padding:10px 18px;border-radius:10px;font-size:13.5px;font-weight:600;display:inline-flex;align-items:center;gap:8px;white-space:nowrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download PDF
        </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($me['program_path'])): ?>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);overflow:hidden;margin-bottom:28px">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
          <p style="color:var(--muted);font-size:13.5px;margin:0">Your coach uploaded a personalized plan tailored to your diabetes type, schedule and equipment.</p>
        </div>
        <div style="height:680px">
          <object data="<?= e($me['program_path']) ?>#view=FitH&toolbar=1" type="application/pdf" style="width:100%;height:100%">
            <iframe src="<?= e($me['program_path']) ?>" style="width:100%;height:100%;border:0" title="Your program PDF"></iframe>
          </object>
        </div>
      </div>
    <?php else: ?>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:48px 24px;text-align:center;margin-bottom:28px">
        <div style="font-size:32px;margin-bottom:12px">⏳</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:8px">Your program is being built</div>
        <p style="color:var(--muted);font-size:13.5px;max-width:420px;margin:0 auto 20px">Our team is reviewing your assessment and tailoring a plan to your diabetes type, schedule and equipment. It usually appears within 24 hours. We'll message you the moment it's ready.</p>
        <a href="/chat" style="display:inline-block;padding:10px 20px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-weight:600;color:var(--ink-2)">Message us if you have questions</a>
      </div>
    <?php endif; ?>

    <!-- Coach conversation -->
    <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div>
          <div class="eyebrow sage" style="margin-bottom:4px">Conversation</div>
          <div style="font-weight:700;font-size:15px">Notes &amp; messages</div>
        </div>
        <span style="background:var(--sage-tint);color:var(--sage-2);font-size:11px;font-weight:700;padding:3px 9px;border-radius:99px"><?= count($allNotes) ?> message<?= count($allNotes) === 1 ? '' : 's' ?></span>
      </div>

      <!-- New message form -->
      <form method="post" action="/reply_note" style="margin-bottom:20px">
        <?= $csrf ?>
        <input type="hidden" name="redirect" value="/program" />
        <label style="display:flex;flex-direction:column;gap:8px;font-size:13px;font-weight:500">
          Send your coach a message
          <textarea name="body" required rows="3" placeholder="Ask anything — about your plan, fueling, glucose, schedule…" style="padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
        </label>
        <button type="submit" style="margin-top:10px;background:var(--ink);color:#F4F1E9;padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer">Send message</button>
      </form>

      <?php if (!$generalNotes): ?>
        <p style="color:var(--muted);font-size:13.5px">No messages yet. Send the first one above — we usually reply within a few hours.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
          <?php foreach ($generalNotes as $n):
            $replies = $replyIndex[$n['id']] ?? [];
            $isMe = (bool)$n['from_member'];
          ?>
            <div style="<?= $isMe ? 'padding-left:24px' : '' ?>">
              <div style="background:<?= $isMe ? 'var(--bg-2)' : 'var(--sage-tint)' ?>;border-radius:10px;padding:14px 16px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                  <span style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:<?= $isMe ? 'var(--muted)' : 'var(--sage-2)' ?>"><?= $isMe ? 'You' : 'Coach' ?></span>
                  <span style="font-size:11.5px;color:var(--muted)"><?= e(date('M j, Y · g:ia', strtotime($n['created_at']))) ?><?= $n['week_number'] ? ' · Week ' . (int)$n['week_number'] : '' ?></span>
                </div>
                <p style="font-size:13.5px;margin:0;line-height:1.6;color:var(--ink-2)"><?= nl2br(e($n['body'])) ?></p>
              </div>
              <?php foreach ($replies as $r):
                $rIsMe = (bool)$r['from_member'];
              ?>
                <div style="margin-top:6px;margin-left:16px;background:<?= $rIsMe ? 'var(--bg-2)' : 'var(--sage-tint)' ?>;border-radius:9px;padding:10px 14px">
                  <div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:<?= $rIsMe ? 'var(--muted)' : 'var(--sage-2)' ?>;margin-bottom:4px"><?= $rIsMe ? 'You' : 'Coach' ?> <span style="font-weight:400;letter-spacing:0;text-transform:none;font-size:11.5px"><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></span></div>
                  <p style="font-size:13px;margin:0"><?= nl2br(e($r['body'])) ?></p>
                </div>
              <?php endforeach; ?>
              <form method="post" action="/reply_note" style="display:flex;gap:8px;margin-top:8px">
                <?= $csrf ?>
                <input type="hidden" name="parent_id" value="<?= (int)$n['id'] ?>" />
                <input type="hidden" name="redirect" value="/program" />
                <input type="text" name="body" placeholder="Reply…" maxlength="2000" required style="flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:var(--bg);font-size:13px" />
                <button type="submit" style="padding:8px 14px;border-radius:8px;border:1px solid var(--line);background:var(--bg);font-size:13px;font-weight:600;cursor:pointer">Reply</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
