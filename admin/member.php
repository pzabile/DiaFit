<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/uploads.php';
require __DIR__ . '/../includes/mailer.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? db_get('SELECT * FROM leads WHERE id = ?', [$id]) : null;
if (!$lead) { http_response_code(404); echo 'Not found'; exit; }

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'program') {
        try {
            $path = save_admin_program_pdf($_FILES['program'] ?? [], $id);
            db_exec('UPDATE leads SET program_path = ?, updated_at = NOW() WHERE id = ?', [$path, $id]);
            $_SESSION['flash'] = 'Program PDF uploaded.';
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Upload failed: ' . $ex->getMessage();
        }
        header('Location: /admin/member?id=' . $id); exit;
    }

    if ($action === 'send_reset') {
        try {
            $res = create_password_reset_token($lead['email']);
            if ($res) {
                $url = rtrim(cfg('site_url'), '/') . '/reset?token=' . $res['token'];
                send_email(
                    $lead['email'],
                    $lead['first_name'] ?: 'there',
                    'DiaFitus — reset your password',
                    password_reset_email_html($lead['first_name'], $url)
                );
                $_SESSION['flash'] = 'Password reset email sent to ' . $lead['email'] . '.';
            }
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not send reset email: ' . $ex->getMessage();
        }
        header('Location: /admin/member?id=' . $id); exit;
    }

    if ($action === 'change_plan') {
        $planDays  = (int) ($_POST['plan_days'] ?? 84);
        $resetDate = !empty($_POST['reset_start']) ? 1 : 0;
        $allowed   = [7, 28, 84];
        if (!in_array($planDays, $allowed, true)) $planDays = 84;
        try {
            if ($resetDate) {
                db_exec('UPDATE leads SET plan_days = ?, started_at = CURDATE(), updated_at = NOW() WHERE id = ?', [$planDays, $id]);
                $effectiveStart = date('Y-m-d');
            } else {
                db_exec('UPDATE leads SET plan_days = ?, updated_at = NOW() WHERE id = ?', [$planDays, $id]);
                $effectiveStart = $lead['started_at'] ?: '';
            }

            $emailNote = '';
            try {
                send_email(
                    $lead['email'],
                    $lead['first_name'] ?: 'there',
                    'Your DiaFitus plan has been updated',
                    plan_changed_email_html($lead['first_name'] ?: 'there', $planDays, $effectiveStart, $lead['email'])
                );
                $emailNote = ' Notification email sent.';
            } catch (Throwable $emailEx) {
                error_log('plan change email: ' . $emailEx->getMessage());
                $emailNote = ' (Email notification failed.)';
            }

            $_SESSION['flash'] = 'Plan updated to ' . $planDays . ' days' . ($resetDate ? ' — start date reset to today.' : '.') . $emailNote;
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not update plan: ' . $ex->getMessage();
        }
        header('Location: /admin/member?id=' . $id); exit;
    }
}

$lead = db_get('SELECT * FROM leads WHERE id = ?', [$id]); // reload after possible update

$answers       = json_decode($lead['answers_json'] ?? '{}', true) ?: [];
$weeks         = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$id]);
$logs          = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC, created_at DESC LIMIT 90', [$id]);
$meals         = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 60', [$id]);
$publicNotes   = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 ORDER BY created_at ASC', [$id]);
$privateNotes  = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 1 ORDER BY created_at DESC', [$id]);

// Group public notes by target so we can render comments inline.
$generalNotes = [];
$targetIndex  = [];
$replyIndex   = [];
foreach ($publicNotes as $n) {
    if ($n['parent_id']) { $replyIndex[(int)$n['parent_id']][] = $n; continue; }
    if ($n['target_type'] && $n['target_id']) {
        $targetIndex[$n['target_type']][(int)$n['target_id']][] = $n;
    } else {
        $generalNotes[] = $n;
    }
}

function render_note_card($n, $replies, $csrf) {
    $cls = $n['from_member'] ? 'from-member' : 'from-coach';
    $kindLabel = ucfirst(str_replace('_', ' ', $n['kind']));
    ?>
    <article class="coach-bubble <?= e($n['kind']) ?> <?= $cls ?>">
      <header>
        <span class="kind-tag <?= e($n['kind']) ?>"><?= e($n['from_member'] ? 'Member' : $kindLabel) ?></span>
        <small>
          <?= e(date('M j, Y · g:ia', strtotime($n['created_at']))) ?>
          <?= $n['week_number'] ? ' · Week ' . (int)$n['week_number'] : '' ?>
        </small>
        <form method="post" action="/admin/note_action" class="inline-del" onsubmit="return confirm('Delete this note and its replies?');">
          <?= $csrf ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="lead_id" value="<?= (int)$n['lead_id'] ?>" />
          <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>" />
          <button type="submit" class="del-btn" title="Delete">✕</button>
        </form>
      </header>
      <p><?= nl2br(e($n['body'])) ?></p>
      <?php if ($replies): ?>
        <div class="reply-thread">
          <?php foreach ($replies as $r):
            $rcls = $r['from_member'] ? 'from-member' : 'from-coach';
          ?>
            <div class="reply <?= $rcls ?>">
              <header>
                <strong><?= $r['from_member'] ? 'Member' : 'Coach' ?></strong>
                <small><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></small>
                <form method="post" action="/admin/note_action" class="inline-del" onsubmit="return confirm('Delete this reply?');">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="lead_id" value="<?= (int)$r['lead_id'] ?>" />
                  <input type="hidden" name="note_id" value="<?= (int)$r['id'] ?>" />
                  <button type="submit" class="del-btn" title="Delete">✕</button>
                </form>
              </header>
              <p><?= nl2br(e($r['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="post" action="/admin/note_action" class="reply-form">
        <?= $csrf ?>
        <input type="hidden" name="action"   value="reply" />
        <input type="hidden" name="lead_id"  value="<?= (int)$n['lead_id'] ?>" />
        <input type="hidden" name="parent_id" value="<?= (int)$n['id'] ?>" />
        <input type="text" name="body" placeholder="Reply to this thread…" maxlength="2000" required />
        <button type="submit" class="btn btn-ghost btn-sm">Reply</button>
      </form>
    </article>
    <?php
}

$pageTitle = 'Member · ' . ($lead['first_name'] ?: $lead['email']);
$bodyClass = 'admin-page';
$activeTab = $lead['paid'] ? 'members' : 'leads';
require __DIR__ . '/../includes/header.php';
$csrf = csrf_input();
$waitingCount = (int)db_get("SELECT COUNT(DISTINCT l.id) c FROM leads l JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id) WHERE l.paid=1 AND cn.from_member=1")['c'];
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];
?>
<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
  <main class="main">
  <div class="admin-main">
    <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

    <header class="admin-page-head">
      <div>
        <p class="kicker"><?= $lead['paid'] ? 'Paid member' : 'Lead (no payment yet)' ?></p>
        <h1><?= e($lead['first_name'] ?: '—') ?> — <?= e($lead['email']) ?></h1>
        <p class="muted">
          Phone <?= e($lead['phone'] ?: '—') ?> ·
          DOB <?= e($lead['dob'] ?: '—') ?> ·
          Started <?= e($lead['started_at'] ?: '—') ?> ·
          Last login <?= e($lead['last_login_at'] ?: '—') ?>
        </p>
      </div>
      <a href="/admin/<?= $lead['paid'] ? 'members' : 'leads' ?>" class="btn btn-ghost">← Back</a>
    </header>

    <!-- ========== Admin tools ========== -->
    <section class="card big">
      <h2>Admin tools</h2>
      <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">

        <!-- Send password reset -->
        <div style="flex:1;min-width:220px;">
          <h3 style="font-size:1rem;margin:0 0 .5rem;">Password reset</h3>
          <p class="muted" style="font-size:.88rem;margin:0 0 .75rem;">Sends a reset link to the member's email. Valid for 1 hour.</p>
          <form method="post" onsubmit="return confirm('Send password reset email to <?= e(addslashes($lead['email'])) ?>?');">
            <?= $csrf ?>
            <input type="hidden" name="action" value="send_reset" />
            <button class="btn btn-ghost">📧 Send reset email</button>
          </form>
        </div>

        <!-- Change plan -->
        <div style="flex:1;min-width:220px;">
          <h3 style="font-size:1rem;margin:0 0 .5rem;">Change plan</h3>
          <p class="muted" style="font-size:.88rem;margin:0 0 .75rem;">Current: <strong><?= (int)($lead['plan_days'] ?? 84) ?> days</strong>. Dashboard and progress update instantly.</p>
          <form method="post" style="display:flex;flex-direction:column;gap:.6rem;">
            <?= $csrf ?>
            <input type="hidden" name="action" value="change_plan" />
            <select name="plan_days" style="padding:.6rem .8rem;border-radius:10px;border:1px solid var(--line);background:#fff;font-size:.95rem;">
              <option value="7"  <?= ($lead['plan_days'] ?? 84) ==  7 ? 'selected' : '' ?>>7-day jump-start</option>
              <option value="28" <?= ($lead['plan_days'] ?? 84) == 28 ? 'selected' : '' ?>>28-day (4-week) reset</option>
              <option value="84" <?= ($lead['plan_days'] ?? 84) == 84 ? 'selected' : '' ?>>84-day (12-week) transformation</option>
            </select>
            <label style="font-size:.88rem;display:flex;align-items:center;gap:.5rem;">
              <input type="checkbox" name="reset_start" value="1" />
              Reset start date to today
            </label>
            <button class="btn btn-primary btn-sm" style="align-self:flex-start;">Update plan</button>
          </form>
        </div>

      </div>
    </section>

    <section class="card big">
      <h2>Questionnaire answers</h2>
      <?php if (!$answers): ?>
        <p class="muted">No questionnaire data on file.</p>
      <?php else: ?>
        <div class="kv-grid">
          <?php foreach ($answers as $k => $v): ?>
            <div><span class="muted"><?= e(ucwords(str_replace('_', ' ', $k))) ?></span><strong><?= e(is_array($v) ? implode(', ', $v) : $v) ?></strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($lead['paid']): ?>
    <section class="card big">
      <h2>Personalized program</h2>
      <?php if ($lead['program_path']): ?>
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
          <span>Current program:</span>
          <a href="<?= e($lead['program_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">⤓ Open / Download PDF</a>
        </div>
        <div class="pdf-viewer">
          <object data="<?= e($lead['program_path']) ?>#view=FitH&toolbar=1" type="application/pdf" class="pdf-frame">
            <iframe src="<?= e($lead['program_path']) ?>" class="pdf-frame" title="Member program PDF"></iframe>
            <p class="muted" style="padding:1rem">Browser cannot display inline. <a href="<?= e($lead['program_path']) ?>" target="_blank">Open in new tab</a>.</p>
          </object>
        </div>
      <?php else: ?>
        <p class="muted">No program uploaded yet.</p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="form inline-form">
        <?= $csrf ?>
        <input type="hidden" name="action" value="program" />
        <input type="file" name="program" accept="application/pdf" required />
        <button class="btn btn-primary">Upload program PDF</button>
      </form>
    </section>

    <!-- ========== Public coach notes ========== -->
    <section class="card big">
      <h2>Conversation with this member</h2>
      <p class="muted">These notes are visible to the member. They can reply.</p>

      <form method="post" action="/admin/note_action" class="form premium-form">
        <?= $csrf ?>
        <input type="hidden" name="action"  value="add_public" />
        <input type="hidden" name="lead_id" value="<?= $id ?>" />
        <div class="grid-2">
          <label>Kind
            <select name="kind">
              <option value="note">General note</option>
              <option value="motivation">Motivation</option>
              <option value="change">Plan change</option>
              <option value="weekly">Weekly review</option>
            </select>
          </label>
          <label>Week number (optional)<input type="number" name="week" min="1" max="200" /></label>
        </div>
        <label>Message<textarea name="body" required placeholder="What do you want them to know this week?"></textarea></label>
        <button class="btn btn-primary">Post note</button>
      </form>

      <?php if ($generalNotes): ?>
        <h3 style="margin-top:1.5rem">Thread</h3>
        <div class="coach-stream admin">
          <?php foreach ($generalNotes as $n): render_note_card($n, $replyIndex[$n['id']] ?? [], $csrf); endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" style="margin-top:1rem">No notes yet.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ========== Weekly check-ins (with inline comments) ========== -->
    <?php if ($weeks): ?>
    <section class="card big">
      <h2>Weekly check-ins from <?= e($lead['first_name'] ?: 'member') ?></h2>
      <?php foreach ($weeks as $w): ?>
        <?php $wLb = $w['weight_kg'] ? round($w['weight_kg'] * 2.20462, 1) : null; ?>
        <article class="checkin-card">
          <header>
            <strong>Week <?= (int) $w['week_number'] ?></strong>
            <small><?= e(date('M j, Y · g:ia', strtotime($w['created_at']))) ?></small>
            <span class="checkin-chips">
              <?php if ($w['avg_glucose']):    ?><span class="chip">🩸 <?= (int)$w['avg_glucose'] ?> mg/dL</span><?php endif; ?>
              <?php if ($wLb):                 ?><span class="chip">⚖️ <?= e($wLb) ?> lbs</span><?php endif; ?>
              <?php if ($w['energy_rating'] !== null): ?><span class="chip">⚡ <?= (int)$w['energy_rating'] ?>/10</span><?php endif; ?>
            </span>
          </header>
          <?php if ($w['wins']):      ?><p><strong>Wins.</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
          <?php if ($w['struggles']): ?><p><strong>Struggles.</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
          <?php if ($w['content']):   ?><p class="muted"><?= nl2br(e($w['content'])) ?></p><?php endif; ?>

          <?php
            $comments = $targetIndex['weekly_note'][$w['id']] ?? [];
            if ($comments):
          ?>
            <div class="checkin-comments">
              <?php foreach ($comments as $c): render_note_card($c, $replyIndex[$c['id']] ?? [], $csrf); endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="/admin/note_action" class="comment-form">
            <?= $csrf ?>
            <input type="hidden" name="action"      value="comment_target" />
            <input type="hidden" name="lead_id"     value="<?= $id ?>" />
            <input type="hidden" name="target_type" value="weekly_note" />
            <input type="hidden" name="target_id"   value="<?= (int)$w['id'] ?>" />
            <input type="text" name="body" placeholder="Comment on this week's check-in (member will see)…" maxlength="2000" required />
            <button type="submit" class="btn btn-ghost btn-sm">Comment</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- ========== Daily logs (with inline comments) ========== -->
    <?php if ($logs): ?>
    <section class="card big">
      <h2>Daily check-ins</h2>
      <?php foreach ($logs as $l): ?>
        <article class="checkin-card small">
          <header>
            <strong><?= e($l['log_date']) ?></strong>
            <small><?= e(date('g:ia', strtotime($l['created_at']))) ?></small>
            <span class="checkin-chips">
              <span class="chip"><?= e($l['feeling']) ?></span>
              <span class="chip">Trained: <?= e($l['trained']) ?><?= $l['train_where'] ? ' · ' . e($l['train_where']) : '' ?></span>
              <?php if ($l['bs_before'] || $l['bs_after']): ?>
                <span class="chip">Glucose <?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?></span>
              <?php endif; ?>
              <span class="chip">Soreness <?= (int)$l['soreness'] ?>/10</span>
            </span>
          </header>
          <?php if ($l['workout']): ?><p><strong>Workout.</strong> <?= nl2br(e($l['workout'])) ?></p><?php endif; ?>
          <?php if ($l['food_before']): ?><p><strong>Before.</strong> <?= e($l['food_before']) ?></p><?php endif; ?>
          <?php if ($l['food_after']):  ?><p><strong>After.</strong>  <?= e($l['food_after']) ?></p><?php endif; ?>
          <?php if ($l['notes']):  ?><p class="muted"><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>

          <?php $comments = $targetIndex['daily_log'][$l['id']] ?? []; if ($comments): ?>
            <div class="checkin-comments">
              <?php foreach ($comments as $c): render_note_card($c, $replyIndex[$c['id']] ?? [], $csrf); endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="/admin/note_action" class="comment-form">
            <?= $csrf ?>
            <input type="hidden" name="action"      value="comment_target" />
            <input type="hidden" name="lead_id"     value="<?= $id ?>" />
            <input type="hidden" name="target_type" value="daily_log" />
            <input type="hidden" name="target_id"   value="<?= (int)$l['id'] ?>" />
            <input type="text" name="body" placeholder="Comment on this check-in (member will see)…" maxlength="2000" required />
            <button type="submit" class="btn btn-ghost btn-sm">Comment</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($meals): ?>
    <section class="card big">
      <h2>Meal photos</h2>
      <div class="meal-grid">
        <?php foreach ($meals as $m): ?>
          <figure class="meal-tile">
            <img src="<?= e($m['file_path']) ?>" alt="" loading="lazy" />
            <figcaption>
              <strong><?= e($m['meal_type']) ?></strong>
              <span><?= e($m['caption']) ?></span>
              <small><?= e(substr($m['created_at'], 0, 16)) ?></small>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ========== Private admin notes ========== -->
    <section class="card big">
      <h2>Private admin notes</h2>
      <p class="muted">Only visible to you. Members never see these.</p>
      <form method="post" action="/admin/note_action" class="form premium-form">
        <?= $csrf ?>
        <input type="hidden" name="action"  value="add_private" />
        <input type="hidden" name="lead_id" value="<?= $id ?>" />
        <label>New private note<textarea name="body" required placeholder="Add a private note for yourself…"></textarea></label>
        <button class="btn btn-ghost">Add private note</button>
      </form>

      <?php if ($privateNotes): ?>
        <div class="private-notes">
          <?php foreach ($privateNotes as $n): ?>
            <div class="private-note">
              <header>
                <small><?= e(date('M j, Y · g:ia', strtotime($n['created_at']))) ?></small>
                <form method="post" action="/admin/note_action" class="inline-del" onsubmit="return confirm('Delete this private note?');">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="lead_id" value="<?= $id ?>" />
                  <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>" />
                  <button type="submit" class="del-btn" title="Delete">✕</button>
                </form>
              </header>
              <p><?= nl2br(e($n['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" style="margin-top:.5rem">No private notes yet.</p>
      <?php endif; ?>
    </section>
  </div><!-- /admin-main -->
  </main>
</div><!-- /app -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
