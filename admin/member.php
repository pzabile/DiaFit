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

    if ($action === 'resend_welcome') {
        try {
            $token    = create_account_setup_token($id, 168);
            $setupUrl = rtrim(cfg('site_url'), '/') . '/setup?token=' . $token;
            require_once __DIR__ . '/../includes/pdf.php';
            $sent = send_email(
                $lead['email'],
                $lead['first_name'] ?: 'there',
                'Welcome to DiaFitus — you\'re in 🎉',
                account_setup_email_html($lead['first_name'] ?: 'there', $setupUrl, $lead['email']),
                null,
                nutrition_guide_attachment()
            );
            $_SESSION['flash'] = $sent
                ? 'Welcome email resent to ' . $lead['email'] . ' with a fresh account setup link.'
                : 'Could not send email (check SMTP config). Lead is marked paid in the database.';
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Error: ' . $ex->getMessage();
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

    if ($action === 'add_private') {
        $body = trim($_POST['body'] ?? '');
        if ($body) {
            try {
                db_insert('INSERT INTO coach_notes (lead_id, body, kind, is_private, from_member) VALUES (?,?,?,1,0)',
                    [$id, $body, 'note']);
                $_SESSION['flash'] = 'Private note added.';
            } catch (Throwable $ex) {
                $_SESSION['flash'] = 'Could not add note: ' . $ex->getMessage();
            }
        }
        header('Location: /admin/member?id=' . $id); exit;
    }
}

$lead = db_get('SELECT * FROM leads WHERE id = ?', [$id]);

$answers      = json_decode($lead['answers_json'] ?? '{}', true) ?: [];
$weeks        = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$id]);
$logs         = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC, created_at DESC LIMIT 60', [$id]);
$meals        = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 20', [$id]);
$conversation = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 ORDER BY created_at ASC', [$id]);
$privateNotes = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 1 ORDER BY created_at DESC', [$id]);

// Member program info
$prog    = member_program_info($lead);
$planLbl = ($lead['plan_days'] ?? 84) <= 7 ? '7-day' : (($lead['plan_days'] ?? 84) <= 28 ? '4-wk' : (($lead['plan_days'] ?? 84) <= 56 ? '8-wk' : '12-wk'));

// KPI: streak
$streak = 0;
try {
    $streakRows = db_all("SELECT log_date FROM daily_logs WHERE lead_id=? AND log_date <= CURDATE() ORDER BY log_date DESC LIMIT 365", [$id]);
    $chk = new DateTime('today');
    foreach ($streakRows as $row) {
        $d = new DateTime($row['log_date']);
        if ($d->format('Y-m-d') !== $chk->format('Y-m-d')) break;
        $streak++; $chk->modify('-1 day');
    }
} catch (Throwable $ignored) {}

// KPI: avg glucose 7d
$avgGlucose = null;
try {
    $gluc = db_get("SELECT AVG((bs_before+bs_after)/2) v FROM daily_logs WHERE lead_id=? AND log_date >= CURDATE()-INTERVAL 7 DAY AND bs_before IS NOT NULL AND bs_after IS NOT NULL", [$id]);
    if ($gluc && $gluc['v']) $avgGlucose = (int)round($gluc['v']);
} catch (Throwable $ignored) {}

// KPI: last log
$lastLog = null;
try {
    $lastLog = db_get('SELECT log_date FROM daily_logs WHERE lead_id=? ORDER BY log_date DESC LIMIT 1', [$id]);
} catch (Throwable $ignored) {}
$lastLogLbl = '—';
if ($lastLog) {
    $daysSince = floor((time() - strtotime($lastLog['log_date'])) / 86400);
    $lastLogLbl = $daysSince === 0 ? 'Today' : ($daysSince === 1 ? 'Yesterday' : $daysSince . 'd ago');
}

// Avatar
function adm_mem_initials($name, $email) {
    $s = trim($name ?? '');
    if ($s) { $p = preg_split('/\s+/', $s); return count($p)>=2 ? strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1)) : strtoupper(mb_substr($s,0,2)); }
    return strtoupper(mb_substr($email ?? 'M', 0, 2));
}
function adm_mem_color($s) {
    $c = ['','sage','plum','coral','sky'];
    return $c[abs(crc32($s ?? '')) % count($c)];
}
$initials = adm_mem_initials($lead['first_name'], $lead['email']);
$avColor  = adm_mem_color($lead['email']);
$name     = $lead['first_name'] ?: explode('@', $lead['email'])[0];

$waitingCount = 0; $membersCount = 0; $leadsCount = 0;
try {
    $waitingCount = (int)(db_get("SELECT COUNT(DISTINCT l.id) c FROM leads l JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id) WHERE l.paid=1 AND cn.from_member=1")['c'] ?? 0);
    $membersCount = (int)(db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'] ?? 0);
    $leadsCount   = (int)(db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'] ?? 0);
} catch (Throwable $ignored) {}

$pageTitle = 'Member · ' . $name . ' — DiaFit Admin';
$bodyClass = 'admin-page';
$activeTab = $lead['paid'] ? 'members' : 'leads';
require __DIR__ . '/../includes/header.php';
$csrf = csrf_input();
?>
<style>
.mem-hero-av{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:17px;flex-shrink:0;color:#fff}
.mem-hero-av.sage{background:linear-gradient(135deg,#9CC9A8,#3B6E54)}
.mem-hero-av.plum{background:linear-gradient(135deg,#C5A8D6,#7D5A8A)}
.mem-hero-av.coral{background:linear-gradient(135deg,#E8B5AA,#C66B5B)}
.mem-hero-av.sky{background:linear-gradient(135deg,#A8C9E8,#5C8AA8)}
.mem-hero-av.default{background:linear-gradient(135deg,#D6C9A8,#A77F4C)}
.kpi-strip{display:flex;gap:0;border-top:1px solid var(--line);background:var(--bg)}
.kpi-strip .it{flex:1;padding:11px 18px;border-right:1px solid var(--line);font-size:12px;color:var(--muted)}
.kpi-strip .it:last-child{border-right:0}
.kpi-strip .it b{color:var(--ink);font-weight:700;font-size:13.5px;display:block;margin-top:1px}
.note-bubble{padding:10px 14px;border-radius:12px;font-size:13.5px;line-height:1.5;margin-bottom:8px;position:relative}
.note-bubble.me{background:var(--ink);color:#F4F1E9;border-bottom-right-radius:4px}
.note-bubble.them{background:var(--card);border:1px solid var(--line);border-bottom-left-radius:4px}
.note-bubble .t{font-size:10.5px;color:var(--muted);margin-top:4px;display:block;font-family:'JetBrains Mono',monospace}
.note-bubble.me .t{color:#9CC9A8}
.note-bubble .del-btn{position:absolute;top:6px;right:8px;font-size:10px;color:var(--coral);opacity:0;background:transparent;padding:2px 5px}
.note-bubble:hover .del-btn{opacity:1}
.log-row{display:grid;grid-template-columns:90px 1fr auto;gap:10px;padding:10px 0;border-bottom:1px dashed var(--line);align-items:start;font-size:13px}
.log-row:last-child{border-bottom:0}
.log-row .dt{font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--muted)}
.log-row .detail{color:var(--muted);font-size:12px;margin-top:2px}
.priv-note{background:var(--amber-tint);border:1px solid #E8D4AC;border-radius:12px;padding:12px 14px;margin-bottom:8px;font-size:13.5px;position:relative}
.priv-note .meta{font-size:11px;color:var(--amber);margin-bottom:5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
.priv-note .del-btn{position:absolute;top:8px;right:10px;font-size:10px;color:var(--coral);background:transparent;opacity:0;padding:2px 5px}
.priv-note:hover .del-btn{opacity:1}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.form-row{grid-template-columns:1fr}.kpi-strip{flex-wrap:wrap}.kpi-strip .it{border-right:0;border-bottom:1px solid var(--line)}}
.field-lbl{font-size:12px;font-weight:600;color:var(--ink-2);margin-bottom:5px;display:block}
.field-inp{width:100%;border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13.5px;background:#fff;outline:none}
.field-inp:focus{border-color:var(--sage);box-shadow:0 0 0 3px var(--sage-tint)}
.field-sel{width:100%;border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13.5px;background:#fff;outline:none;cursor:pointer}
.section-title{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);font-weight:600;margin:28px 0 12px}
</style>

<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span>Admin</span>
      <span style="color:var(--line-2)">›</span>
      <a href="/admin/<?= $lead['paid'] ? 'members' : 'leads' ?>" style="color:var(--muted)">
        <?= $lead['paid'] ? 'Members' : 'Leads' ?>
      </a>
      <span style="color:var(--line-2)">›</span>
      <b><?= e($name) ?></b>
    </div>
    <div class="top-actions">
      <a href="/admin/<?= $lead['paid'] ? 'members' : 'leads' ?>" class="btn">← Back</a>
      <a href="/admin/inbox?thread=<?= $id ?>" class="btn pri">Open in inbox →</a>
    </div>
  </header>

  <div class="view">

    <?php if ($flash): ?>
    <div style="background:var(--sage-tint);border:1px solid var(--sage-tint-2);border-radius:12px;padding:12px 18px;font-size:13.5px;color:var(--sage-3);margin-bottom:18px;display:flex;gap:10px;align-items:center">
      <span style="font-size:16px">✓</span> <?= e($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Profile hero card -->
    <div class="card" style="margin-bottom:20px">
      <div style="padding:22px 24px 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div style="display:flex;gap:16px;align-items:center">
          <div class="mem-hero-av <?= $avColor ?: 'default' ?>"><?= e($initials) ?></div>
          <div>
            <div class="eyebrow <?= $lead['paid'] ? 'sage' : 'coral' ?>">
              <?= $lead['paid'] ? 'Paid member' : 'Lead · not yet paid' ?>
            </div>
            <h1 class="h1" style="margin:2px 0 6px;font-size:32px"><?= e($name) ?></h1>
            <div style="display:flex;gap:12px;flex-wrap:wrap;color:var(--muted);font-size:12.5px">
              <span><?= e($lead['email']) ?></span>
              <?php if ($lead['phone']): ?><span>· <?= e($lead['phone']) ?></span><?php endif; ?>
              <?php if ($lead['dob']): ?><span>· DOB <?= e($lead['dob']) ?></span><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
              <span class="chip sage"><?= e($planLbl) ?> plan</span>
              <span class="chip">Week <?= $prog['current'] ?> / <?= $prog['total'] ?></span>
              <?php if ($lead['started_at']): ?><span class="chip">Started <?= date('M j, Y', strtotime($lead['started_at'])) ?></span><?php endif; ?>
            </div>
          </div>
        </div>
        <!-- Program progress -->
        <div style="min-width:180px;text-align:right">
          <div style="font-family:'Instrument Serif',serif;font-size:52px;line-height:1;letter-spacing:-.02em;color:var(--sage-2)"><?= $prog['current'] ?><span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;color:var(--muted)"> / <?= $prog['total'] ?></span></div>
          <div style="color:var(--muted);font-size:11.5px;margin-top:4px">Week of <?= $prog['total'] ?>-week plan</div>
          <div style="height:6px;border-radius:99px;background:var(--bg-3);overflow:hidden;margin-top:8px">
            <div style="height:100%;width:<?= $prog['pct'] ?>%;background:linear-gradient(90deg,var(--sage),#9CC9A8);border-radius:99px"></div>
          </div>
        </div>
      </div>
      <!-- KPI strip -->
      <div class="kpi-strip">
        <div class="it"><span>Streak</span><b><?= $streak ?>d</b></div>
        <div class="it"><span>Avg glucose · 7d</span><b><?= $avgGlucose ? $avgGlucose . ' mg/dL' : '—' ?></b></div>
        <div class="it"><span>Last check-in</span><b><?= e($lastLogLbl) ?></b></div>
        <div class="it"><span>Check-ins</span><b><?= count($logs) ?> logged</b></div>
        <div class="it"><span>Weeks submitted</span><b><?= count($weeks) ?></b></div>
      </div>
    </div>

    <!-- Two-col: Admin tools + conversation -->
    <div class="col2" style="margin-bottom:20px">

      <!-- Left: tools + program + answers -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Admin tools -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Admin</div>
              <h3 class="h3">Tools</h3>
            </div>
          </div>
          <div class="body" style="padding:18px 20px">
            <div class="form-row">
              <!-- Resend welcome -->
              <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Welcome email</div>
                <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Resend the welcome email with a fresh account-setup link (valid 7 days) and the nutrition PDF.</p>
                <form method="post" onsubmit="return confirm('Resend welcome email to <?= e(addslashes($lead['email'])) ?>?')">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="resend_welcome" />
                  <button class="btn pri" style="width:100%;justify-content:center">✉ Resend welcome email</button>
                </form>
              </div>
              <!-- Password reset -->
              <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Password reset</div>
                <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Send a password reset link. Valid for 1 hour.</p>
                <form method="post" onsubmit="return confirm('Send reset to <?= e(addslashes($lead['email'])) ?>?')">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="send_reset" />
                  <button class="btn" style="width:100%;justify-content:center">🔑 Send reset email</button>
                </form>
              </div>
              <!-- Change plan -->
              <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Change plan</div>
                <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Current: <strong><?= (int)($lead['plan_days'] ?? 84) ?> days</strong>. Dashboard updates instantly.</p>
                <form method="post" style="display:flex;flex-direction:column;gap:8px">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="change_plan" />
                  <select name="plan_days" class="field-sel">
                    <option value="7"  <?= ($lead['plan_days'] ?? 84) ==  7 ? 'selected' : '' ?>>7-day jump-start</option>
                    <option value="28" <?= ($lead['plan_days'] ?? 84) == 28 ? 'selected' : '' ?>>4-week (28-day)</option>
                    <option value="84" <?= ($lead['plan_days'] ?? 84) == 84 ? 'selected' : '' ?>>12-week (84-day)</option>
                  </select>
                  <label style="font-size:12px;display:flex;align-items:center;gap:7px;color:var(--ink-2)">
                    <input type="checkbox" name="reset_start" value="1" /> Reset start date to today
                  </label>
                  <button class="btn pri" style="justify-content:center">Update plan</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Program PDF -->
        <?php if ($lead['paid']): ?>
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Content</div>
              <h3 class="h3">Program PDF</h3>
            </div>
            <?php if ($lead['program_path']): ?>
              <a href="/<?= e($lead['program_path']) ?>" target="_blank" class="btn sm">Open PDF →</a>
            <?php endif; ?>
          </div>
          <div class="body">
            <?php if ($lead['program_path']): ?>
              <embed src="/<?= e($lead['program_path']) ?>" type="application/pdf" width="100%" height="340px" style="border-radius:10px;margin-bottom:14px" />
            <?php else: ?>
              <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;background:var(--bg);border-radius:10px;margin-bottom:14px">No program uploaded yet.</div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <?= $csrf ?>
              <input type="hidden" name="action" value="program" />
              <input type="file" name="program" accept="application/pdf" required style="font-size:13px;flex:1;min-width:0" />
              <button class="btn pri">Upload PDF</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- Questionnaire answers -->
        <?php if ($answers): ?>
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Intake</div>
              <h3 class="h3">Questionnaire answers</h3>
            </div>
            <span class="chip"><?= count($answers) ?></span>
          </div>
          <div class="body" style="padding:6px 20px 14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px">
              <?php foreach ($answers as $k => $v): ?>
              <div style="padding:8px 0;border-bottom:1px dashed var(--line);display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-size:13px">
                <span style="color:var(--muted)"><?= e(ucwords(str_replace('_', ' ', $k))) ?></span>
                <span style="font-weight:600;text-align:right;font-size:12.5px"><?= e(is_array($v) ? implode(', ', $v) : $v) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right: conversation -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card" style="position:sticky;top:24px">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Messages</div>
              <h3 class="h3">Conversation</h3>
            </div>
            <a href="/admin/inbox?thread=<?= $id ?>" class="btn sm">Full inbox →</a>
          </div>
          <div style="padding:16px 20px;max-height:380px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;background:linear-gradient(180deg,#FFFDF7,#FAF7F0)">
            <?php if ($conversation): ?>
              <?php foreach ($conversation as $msg): ?>
                <div class="note-bubble <?= $msg['from_member'] ? 'them' : 'me' ?>">
                  <?= nl2br(e($msg['body'])) ?>
                  <span class="t"><?= date('M j · g:ia', strtotime($msg['created_at'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px">No messages yet.</div>
            <?php endif; ?>
          </div>
          <div style="padding:14px 20px;border-top:1px solid var(--line)">
            <form method="post" action="/admin/note_action">
              <?= $csrf ?>
              <input type="hidden" name="action"  value="add_public" />
              <input type="hidden" name="lead_id" value="<?= $id ?>" />
              <div style="display:flex;gap:8px;align-items:flex-end">
                <textarea name="body" required placeholder="Send a message…" rows="2"
                  style="flex:1;border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13.5px;background:#fff;resize:none;outline:none;min-height:40px"></textarea>
                <button class="btn pri sm">Send</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Private admin notes -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px;color:var(--amber)">Private</div>
              <h3 class="h3">Admin notes</h3>
            </div>
            <span class="chip amber"><?= count($privateNotes) ?> notes</span>
          </div>
          <div class="body">
            <?php foreach ($privateNotes as $n): ?>
            <div class="priv-note">
              <div class="meta"><?= date('M j, Y · g:ia', strtotime($n['created_at'])) ?></div>
              <?= nl2br(e($n['body'])) ?>
              <form method="post" action="/admin/note_action" class="del-form" style="display:inline" onsubmit="return confirm('Delete this note?')">
                <?= $csrf ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="lead_id" value="<?= $id ?>" />
                <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>" />
                <button type="submit" class="del-btn">✕ Delete</button>
              </form>
            </div>
            <?php endforeach; ?>
            <?php if (!$privateNotes): ?><div style="color:var(--muted);font-size:13px;margin-bottom:12px">No private notes yet.</div><?php endif; ?>
            <form method="post" action="" style="margin-top:8px">
              <?= $csrf ?>
              <input type="hidden" name="action" value="add_private" />
              <div style="display:flex;gap:8px;align-items:flex-end">
                <textarea name="body" required placeholder="Add a private note (only you see this)…" rows="2"
                  style="flex:1;border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13.5px;background:#fff;resize:none;outline:none"></textarea>
                <button class="btn sm">Add note</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Weekly check-ins -->
    <?php if ($weeks): ?>
    <div class="section-title">Weekly check-ins (<?= count($weeks) ?>)</div>
    <div class="card" style="margin-bottom:20px;overflow:hidden">
      <div style="overflow-x:auto">
      <table class="tbl">
        <thead>
          <tr>
            <th>Week</th>
            <th>Date</th>
            <th>Glucose</th>
            <th>Weight</th>
            <th>Energy</th>
            <th>Wins</th>
            <th>Struggles</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($weeks as $w): ?>
          <?php $wLb = $w['weight_kg'] ? round($w['weight_kg'] * 2.20462, 1) : null; ?>
          <tr>
            <td style="font-weight:700">W<?= (int)$w['week_number'] ?></td>
            <td style="color:var(--muted);font-size:12px"><?= date('M j', strtotime($w['created_at'])) ?></td>
            <td><?= $w['avg_glucose'] ? '<span class="chip" style="font-size:11px">' . (int)$w['avg_glucose'] . ' mg/dL</span>' : '—' ?></td>
            <td><?= $wLb ? e($wLb) . ' lbs' : '—' ?></td>
            <td><?= $w['energy_rating'] !== null ? (int)$w['energy_rating'] . '/10' : '—' ?></td>
            <td style="font-size:12.5px;max-width:200px"><?= $w['wins'] ? e(mb_strimwidth($w['wins'], 0, 80, '…')) : '—' ?></td>
            <td style="font-size:12.5px;max-width:200px"><?= $w['struggles'] ? e(mb_strimwidth($w['struggles'], 0, 80, '…')) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Daily logs -->
    <?php if ($logs): ?>
    <div class="section-title">Daily check-ins · last <?= min(count($logs), 60) ?></div>
    <div class="card" style="margin-bottom:20px;overflow:hidden">
      <div style="overflow-x:auto">
      <table class="tbl">
        <thead>
          <tr>
            <th>Date</th>
            <th>Feeling</th>
            <th>Trained</th>
            <th>Glucose before</th>
            <th>Glucose after</th>
            <th>Soreness</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td style="font-family:'JetBrains Mono',monospace;font-size:12px"><?= e($l['log_date']) ?></td>
            <td>
              <?php if ($l['feeling']): ?>
                <span class="chip <?= $l['feeling'] === 'great' ? 'sage' : ($l['feeling'] === 'rough' ? 'coral' : '') ?>" style="font-size:11px"><?= e($l['feeling']) ?></span>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
            <td style="font-size:12.5px"><?= e($l['trained'] ?: '—') ?><?= $l['train_where'] ? ' · ' . e($l['train_where']) : '' ?></td>
            <td style="font-size:12.5px"><?= $l['bs_before'] ? $l['bs_before'] . ' mg/dL' : '—' ?></td>
            <td style="font-size:12.5px"><?= $l['bs_after'] ? $l['bs_after'] . ' mg/dL' : '—' ?></td>
            <td style="font-size:12.5px"><?= $l['soreness'] !== null ? (int)$l['soreness'] . '/10' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Meal photos -->
    <?php if ($meals): ?>
    <div class="section-title">Meal photos (<?= count($meals) ?>)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:20px">
      <?php foreach ($meals as $m): ?>
      <div style="border-radius:12px;overflow:hidden;border:1px solid var(--line);background:var(--card)">
        <img src="<?= e($m['file_path']) ?>" alt="" loading="lazy" style="width:100%;height:100px;object-fit:cover;display:block" />
        <div style="padding:8px 10px;font-size:11.5px">
          <div style="font-weight:600"><?= e($m['meal_type'] ?: 'Meal') ?></div>
          <div style="color:var(--muted)"><?= substr($m['created_at'], 0, 10) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /view -->
</main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
