<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/uploads.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$lead = $id ? db_get('SELECT * FROM leads WHERE id = ?', [$id]) : null;
if (!$lead) { http_response_code(404); echo 'Not found'; exit; }

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

// --- Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'note') {
        $body = trim($_POST['body'] ?? '');
        $kind = substr(trim($_POST['kind'] ?? 'note'), 0, 40);
        $week = ($_POST['week'] ?? '') !== '' ? (int) $_POST['week'] : null;
        if ($body !== '') {
            db_insert('INSERT INTO coach_notes (lead_id, week_number, body, kind) VALUES (?, ?, ?, ?)',
                [$id, $week, $body, $kind ?: 'note']);
            $_SESSION['flash'] = 'Note added.';
        }
    } elseif ($action === 'admin_notes') {
        db_exec('UPDATE leads SET admin_notes = ?, updated_at = NOW() WHERE id = ?',
            [trim($_POST['admin_notes'] ?? ''), $id]);
        $_SESSION['flash'] = 'Admin notes saved.';
    } elseif ($action === 'program') {
        try {
            $path = save_admin_program_pdf($_FILES['program'] ?? [], $id);
            db_exec('UPDATE leads SET program_path = ?, updated_at = NOW() WHERE id = ?', [$path, $id]);
            $_SESSION['flash'] = 'Program PDF uploaded.';
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Upload failed: ' . $ex->getMessage();
        }
    }
    header('Location: /admin/member?id=' . $id); exit;
}

$answers = json_decode($lead['answers_json'] ?? '{}', true) ?: [];
$weeks   = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$id]);
$logs    = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC LIMIT 60', [$id]);
$meals   = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 40', [$id]);
$notes   = db_all('SELECT * FROM coach_notes WHERE lead_id = ? ORDER BY created_at DESC', [$id]);

$pageTitle = 'Member · ' . ($lead['first_name'] ?: $lead['email']);
$bodyClass = 'admin-page';
$activeTab = $lead['paid'] ? 'members' : 'leads';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
?>
  <main class="admin-main">
    <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

    <header class="admin-page-head">
      <div>
        <p class="kicker"><?= $lead['paid'] ? 'Paid member' : 'Lead (no payment yet)' ?></p>
        <h1><?= e($lead['first_name'] ?: '—') ?> — <?= e($lead['email']) ?></h1>
        <p class="muted">Phone <?= e($lead['phone'] ?: '—') ?> · DOB <?= e($lead['dob'] ?: '—') ?> · Started <?= e($lead['started_at'] ?: '—') ?> · Last login <?= e($lead['last_login_at'] ?: '—') ?></p>
      </div>
      <a href="/admin/<?= $lead['paid'] ? 'members' : 'leads' ?>" class="btn btn-ghost">← Back</a>
    </header>

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
      <h2>Upload personalized program (PDF)</h2>
      <?php if ($lead['program_path']): ?>
        <p>Current program: <a href="<?= e($lead['program_path']) ?>" target="_blank">view PDF</a></p>
      <?php else: ?>
        <p class="muted">No program uploaded yet.</p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="form inline-form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="program" />
        <input type="file" name="program" accept="application/pdf" required />
        <button class="btn btn-primary">Upload program</button>
      </form>
    </section>

    <section class="card big">
      <h2>Leave a note for this member</h2>
      <p class="muted">Members see these in their dashboard. Use them for motivation, weekly feedback or changes to their plan.</p>
      <form method="post" class="form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="note" />
        <div class="grid-2">
          <label>Type
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

      <?php if ($notes): ?>
        <h3 style="margin-top:1.5rem">Note history</h3>
        <div class="coach-stream admin">
          <?php foreach ($notes as $n): ?>
            <div class="coach-bubble <?= e($n['kind']) ?>">
              <small><?= e($n['created_at']) ?><?= $n['week_number'] ? ' · week ' . (int)$n['week_number'] : '' ?> · <?= e($n['kind']) ?></small>
              <p><?= nl2br(e($n['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card big">
      <h2>Private admin notes</h2>
      <p class="muted">Only visible to you. Members never see this.</p>
      <form method="post" class="form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="admin_notes" />
        <textarea name="admin_notes" rows="4"><?= e($lead['admin_notes']) ?></textarea>
        <button class="btn btn-ghost">Save admin notes</button>
      </form>
    </section>

    <?php if ($weeks): ?>
    <section class="card big">
      <h2>Weekly check-ins from <?= e($lead['first_name'] ?: 'member') ?></h2>
      <?php foreach ($weeks as $w): ?>
        <details class="week-card" open>
          <summary>
            Week <?= (int) $w['week_number'] ?> — <?= e(substr($w['created_at'], 0, 10)) ?>
            <?php if ($w['avg_glucose']): ?><span class="chip">glucose <?= (int)$w['avg_glucose'] ?></span><?php endif; ?>
            <?php if ($w['weight_kg']):   ?><span class="chip"><?= e($w['weight_kg']) ?> kg</span><?php endif; ?>
            <?php if ($w['energy_rating'] !== null): ?><span class="chip">energy <?= (int)$w['energy_rating'] ?>/10</span><?php endif; ?>
          </summary>
          <?php if ($w['wins']):      ?><p><strong>Wins:</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
          <?php if ($w['struggles']): ?><p><strong>Struggles:</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
          <?php if ($w['content']):   ?><p><?= nl2br(e($w['content'])) ?></p><?php endif; ?>
        </details>
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

    <?php if ($logs): ?>
    <section class="card big">
      <h2>Daily logs</h2>
      <table class="data-table">
        <thead><tr><th>Date</th><th>Feeling</th><th>Trained</th><th>Glucose</th><th>Soreness</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= e($l['log_date']) ?></td>
              <td><?= e($l['feeling']) ?></td>
              <td><?= e($l['trained']) ?> <?= e($l['train_where'] ? '('.$l['train_where'].')' : '') ?></td>
              <td><?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?> · <?= e($l['bs_trend']) ?></td>
              <td><?= e($l['soreness']) ?>/10</td>
              <td>
                <?php if ($l['workout']): ?><div><?= e(mb_strimwidth($l['workout'], 0, 120, '…')) ?></div><?php endif; ?>
                <?php if ($l['notes']):   ?><div class="muted"><?= e(mb_strimwidth($l['notes'], 0, 120, '…')) ?></div><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php endif; ?>
  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
