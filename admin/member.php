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

    if ($action === 'add_plan_item') {
        $planDate = $_POST['plan_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planDate)) $planDate = date('Y-m-d');
        $itemText = trim($_POST['item_text'] ?? '');
        if ($itemText) {
            try {
                db_insert('INSERT INTO daily_plan_items (lead_id, plan_date, item_text, sort_order, created_at) VALUES (?, ?, ?, 0, NOW())',
                    [$id, $planDate, $itemText]);
                $_SESSION['flash'] = 'Plan item added.';
            } catch (Throwable $ex) {
                $_SESSION['flash'] = 'Could not add plan item: ' . $ex->getMessage();
            }
        }
        header('Location: /admin/member?id=' . $id . '&plan_date=' . $planDate); exit;
    }

    if ($action === 'delete_plan_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            try {
                db_exec('DELETE FROM daily_plan_items WHERE id = ? AND lead_id = ?', [$itemId, $id]);
            } catch (Throwable $ignored) {}
        }
        $planDate = $_POST['plan_date'] ?? date('Y-m-d');
        header('Location: /admin/member?id=' . $id . '&plan_date=' . $planDate); exit;
    }

    if ($action === 'program') {
        $weekNum = max(1, (int) ($_POST['week_num'] ?? 1));
        $title   = trim($_POST['program_title'] ?? '') ?: null;
        try {
            $path = save_admin_program_pdf($_FILES['program'] ?? [], $id);
            $existingProg = db_get('SELECT id FROM member_programs WHERE lead_id = ? AND week_number = ?', [$id, $weekNum]);
            if ($existingProg) {
                db_exec('UPDATE member_programs SET file_path = ?, title = ?, created_at = NOW() WHERE id = ?',
                    [$path, $title, (int)$existingProg['id']]);
            } else {
                db_insert('INSERT INTO member_programs (lead_id, week_number, title, file_path, created_at) VALUES (?, ?, ?, ?, NOW())',
                    [$id, $weekNum, $title, $path]);
            }
            if ($weekNum === 1) {
                db_exec('UPDATE leads SET program_path = ?, updated_at = NOW() WHERE id = ?', [$path, $id]);
            }
            $_SESSION['flash'] = "Week {$weekNum} program PDF uploaded.";
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
        $startDate = trim($_POST['start_date'] ?? '');
        $allowed   = [7, 28, 84];
        if (!in_array($planDays, $allowed, true)) $planDays = 84;
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = '';
        try {
            if ($startDate) {
                db_exec('UPDATE leads SET plan_days = ?, started_at = ?, updated_at = NOW() WHERE id = ?', [$planDays, $startDate, $id]);
                $effectiveStart = $startDate;
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

    if ($action === 'set_motivation') {
        $body = trim($_POST['motivation_note'] ?? '');
        try {
            db_exec('UPDATE leads SET motivation_note = ?, updated_at = NOW() WHERE id = ?', [$body ?: null, $id]);
            $_SESSION['flash'] = 'Motivation note saved.';
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not save note: ' . $ex->getMessage();
        }
        header('Location: /admin/member?id=' . $id); exit;
    }

    if ($action === 'set_program_targets') {
        $targets = trim($_POST['targets'] ?? '');
        $targetWeek = max(1, (int)($_POST['target_week'] ?? 1));
        try {
            $mpRow = db_get('SELECT id FROM member_programs WHERE lead_id = ? AND week_number = ?', [$id, $targetWeek]);
            if ($mpRow) {
                db_exec('UPDATE member_programs SET program_targets = ? WHERE id = ?', [$targets ?: null, (int)$mpRow['id']]);
            } else {
                // Create a placeholder row for this week if it doesn't exist
                db_insert('INSERT INTO member_programs (lead_id, week_number, program_targets, created_at) VALUES (?, ?, ?, NOW())', [$id, $targetWeek, $targets ?: null]);
            }
            $_SESSION['flash'] = "Week {$targetWeek} targets saved.";
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not save targets: ' . $ex->getMessage();
        }
        header('Location: /admin/member?id=' . $id . '&targets_week=' . $targetWeek); exit;
    }

    if ($action === 'comment_meal') {
        $mealId  = (int)($_POST['meal_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($mealId) {
            try {
                db_exec('UPDATE meal_photos SET admin_comment = ? WHERE id = ? AND lead_id = ?', [$comment ?: null, $mealId, $id]);
                if (!empty($_POST['_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json'))) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                $_SESSION['flash'] = 'Comment saved.';
            } catch (Throwable $ex) {
                if (!empty($_POST['_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json'))) {
                    http_response_code(500);
                    echo json_encode(['ok' => false]);
                    exit;
                }
                $_SESSION['flash'] = 'Could not save comment.';
            }
        }
        header('Location: /admin/member?id=' . $id); exit;
    }

    if ($action === 'set_daily_message') {
        $msgDate = $_POST['message_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $msgDate)) $msgDate = date('Y-m-d');
        $body = trim($_POST['daily_message'] ?? '');
        try {
            if ($body) {
                $existing = db_get('SELECT id FROM member_daily_messages WHERE lead_id = ? AND message_date = ?', [$id, $msgDate]);
                if ($existing) {
                    db_exec('UPDATE member_daily_messages SET body = ? WHERE id = ?', [$body, (int)$existing['id']]);
                } else {
                    db_insert('INSERT INTO member_daily_messages (lead_id, message_date, body, created_at) VALUES (?, ?, ?, NOW())', [$id, $msgDate, $body]);
                }
                $_SESSION['flash'] = "Daily message set for {$msgDate}.";
            } else {
                db_exec('DELETE FROM member_daily_messages WHERE lead_id = ? AND message_date = ?', [$id, $msgDate]);
                $_SESSION['flash'] = "Daily message cleared for {$msgDate}.";
            }
        } catch (Throwable $ex) {
            $_SESSION['flash'] = 'Could not save message: ' . $ex->getMessage();
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

$memberPrograms = [];
try {
    $memberPrograms = db_all('SELECT * FROM member_programs WHERE lead_id = ? ORDER BY week_number ASC', [$id]);
} catch (Throwable $ignored) {}

// Daily plan items
$planDate = $_GET['plan_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planDate)) $planDate = date('Y-m-d');
$planItems = [];
try {
    $planItems = db_all('SELECT * FROM daily_plan_items WHERE lead_id = ? AND plan_date = ? ORDER BY sort_order ASC, id ASC', [$id, $planDate]);
} catch (Throwable $ignored) {}

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

// Glucose trend chart data for admin view
$glucTrend = [];
try {
    $glucTrend = db_all(
        'SELECT log_date, bs_before, bs_after FROM daily_logs WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY log_date ASC',
        [$id]
    );
} catch (Throwable $ignored) {}

// Compute SVG chart points (last 14 entries max)
$glucTrendSlice = array_slice($glucTrend, -14);
$admChartPts = [];
if ($glucTrendSlice) {
    $n = count($glucTrendSlice);
    $xStep = $n > 1 ? 740 / ($n - 1) : 0;
    foreach ($glucTrendSlice as $i => $row) {
        $vals = array_filter([(int)($row['bs_before'] ?? 0), (int)($row['bs_after'] ?? 0)]);
        if ($vals) {
            $avg = array_sum($vals) / count($vals);
            $minG = 60; $maxG = 220;
            $y = round(140 - (($avg - $minG) / ($maxG - $minG)) * 120, 1);
            $y = max(10, min(140, $y));
            $admChartPts[] = ['x' => round(40 + $i * $xStep, 1), 'y' => $y, 'val' => round($avg), 'date' => $row['log_date']];
        }
    }
}

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
          <div style="color:var(--muted);font-size:11.5px;margin-top:4px"><?= ucfirst($prog['label']) ?> <?= $prog['current'] ?> of <?= $prog['total'] ?>-<?= $prog['label'] ?> plan</div>
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
              <!-- Password reset -->
              <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Password reset</div>
                <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Send a reset link to the member's email. Valid for 1 hour.</p>
                <form method="post" onsubmit="return confirm('Send reset to <?= e(addslashes($lead['email'])) ?>?')">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="send_reset" />
                  <button class="btn" style="width:100%;justify-content:center">📧 Send reset email</button>
                </form>
              </div>
              <!-- Change plan -->
              <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Change plan</div>
                <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Current: <strong><?= (int)($lead['plan_days'] ?? 84) ?> days</strong> · Started: <strong><?= $lead['started_at'] ? date('M j, Y', strtotime($lead['started_at'])) : '—' ?></strong></p>
                <form method="post" style="display:flex;flex-direction:column;gap:8px">
                  <?= $csrf ?>
                  <input type="hidden" name="action" value="change_plan" />
                  <select name="plan_days" class="field-sel">
                    <option value="7"  <?= ($lead['plan_days'] ?? 84) ==  7 ? 'selected' : '' ?>>7-day jump-start</option>
                    <option value="28" <?= ($lead['plan_days'] ?? 84) == 28 ? 'selected' : '' ?>>4-week (28-day)</option>
                    <option value="84" <?= ($lead['plan_days'] ?? 84) == 84 ? 'selected' : '' ?>>12-week (84-day)</option>
                  </select>
                  <div style="display:flex;gap:8px;align-items:center">
                    <label style="font-size:12px;color:var(--ink-2);white-space:nowrap">Start date:</label>
                    <input type="date" name="start_date" value="<?= e($lead['started_at'] ?? '') ?>" class="field-inp" style="flex:1" />
                  </div>
                  <p style="color:var(--muted);font-size:11px;margin:0;line-height:1.4">Set the start date to control which week/day the member sees. Move it forward to restart, back to extend.</p>
                  <button class="btn pri" style="justify-content:center">Update plan</button>
                </form>
              </div>
            </div>
            <!-- Daily coach message -->
            <?php
            $dailyMsgDate = date('Y-m-d');
            $existingDailyMsg = null;
            try {
                $existingDailyMsg = db_get('SELECT body FROM member_daily_messages WHERE lead_id = ? AND message_date = ?', [$id, $dailyMsgDate]);
            } catch (Throwable $ignored) {}
            ?>
            <div style="margin-top:18px;border-top:1px dashed var(--line);padding-top:16px">
              <div style="font-size:13px;font-weight:600;margin-bottom:4px">Daily message · "Note from your coach" card</div>
              <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">Shows on their Today page as "A note from your coach". Leave blank to show a rotating motivational quote instead.</p>
              <form method="post" style="display:flex;flex-direction:column;gap:8px">
                <?= $csrf ?>
                <input type="hidden" name="action" value="set_daily_message" />
                <div style="display:flex;gap:8px;align-items:center">
                  <input type="date" name="message_date" value="<?= e($dailyMsgDate) ?>" class="field-inp" style="width:auto" />
                  <span style="font-size:12px;color:var(--muted)"><?= date('l') ?></span>
                </div>
                <textarea name="daily_message" class="field-inp" rows="2" placeholder="e.g. Great work on yesterday's session — keep the momentum going today!" style="resize:vertical;width:100%"><?= e($existingDailyMsg['body'] ?? '') ?></textarea>
                <button class="btn sm" style="align-self:flex-end">Save message</button>
              </form>
            </div>
            <!-- Motivation note -->
            <div style="margin-top:18px;border-top:1px dashed var(--line);padding-top:16px">
              <div style="font-size:13px;font-weight:600;margin-bottom:4px">Motivation note · pinned on their Today page</div>
              <p style="color:var(--muted);font-size:12px;margin:0 0 10px;line-height:1.5">A personal quote or nudge. Always visible (separate from daily message).</p>
              <form method="post" style="display:flex;flex-direction:column;gap:8px">
                <?= $csrf ?>
                <input type="hidden" name="action" value="set_motivation" />
                <textarea name="motivation_note" class="field-inp" rows="2" placeholder="e.g. Small consistent steps beat one perfect day." style="resize:vertical;width:100%"><?= e($lead['motivation_note'] ?? '') ?></textarea>
                <button class="btn sm" style="align-self:flex-end">Save note</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Today's plan card (admin sets tasks for the member) -->
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Daily</div>
              <h3 class="h3">Today's plan for <?= e($lead['first_name'] ?: 'member') ?></h3>
            </div>
          </div>
          <div class="body" style="padding:14px 20px">
            <!-- Date picker -->
            <form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
              <input type="hidden" name="id" value="<?= $id ?>" />
              <input type="date" name="plan_date" value="<?= e($planDate) ?>" class="field-inp" style="width:auto" onchange="this.form.submit()" />
              <span style="font-size:12.5px;color:var(--muted)"><?= date('l', strtotime($planDate)) ?></span>
            </form>
            <!-- Existing plan items -->
            <?php if ($planItems): ?>
              <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px">
                <?php foreach ($planItems as $pi): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border:1px solid var(--line);border-radius:9px;background:<?= $pi['is_done'] ? 'var(--sage-tint)' : 'var(--card)' ?>">
                  <span style="font-size:13px;<?= $pi['is_done'] ? 'text-decoration:line-through;color:var(--muted)' : '' ?>"><?= e($pi['item_text']) ?></span>
                  <div style="display:flex;align-items:center;gap:6px">
                    <?php if ($pi['is_done']): ?><span class="chip sage" style="font-size:10.5px">Done ✓</span><?php endif; ?>
                    <form method="post" action="" style="display:inline">
                      <?= $csrf ?>
                      <input type="hidden" name="action" value="delete_plan_item" />
                      <input type="hidden" name="lead_id" value="<?= $id ?>" />
                      <input type="hidden" name="item_id" value="<?= (int)$pi['id'] ?>" />
                      <input type="hidden" name="plan_date" value="<?= e($planDate) ?>" />
                      <button type="submit" style="font-size:11px;color:var(--coral);padding:2px 6px;border-radius:5px" onclick="return confirm('Delete this item?')">✕</button>
                    </form>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="color:var(--muted);font-size:13px;margin-bottom:12px">No plan items for this date. Add some below.</div>
            <?php endif; ?>
            <!-- Add new item -->
            <form method="post" action="" style="display:flex;gap:8px;align-items:center">
              <?= $csrf ?>
              <input type="hidden" name="action" value="add_plan_item" />
              <input type="hidden" name="plan_date" value="<?= e($planDate) ?>" />
              <input type="text" name="item_text" class="field-inp" placeholder="e.g. 30 min walk · Log glucose before dinner · Take Metformin" required style="flex:1" />
              <button class="btn pri sm">Add</button>
            </form>
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
              <a href="<?= e($lead['program_path']) ?>" target="_blank" class="btn sm">Open PDF →</a>
            <?php endif; ?>
          </div>
          <div class="body">
            <?php if ($memberPrograms): ?>
              <div style="margin-bottom:14px">
                <?php foreach ($memberPrograms as $mp): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line);font-size:13px">
                  <span><strong>Week <?= (int)$mp['week_number'] ?></strong><?= $mp['title'] ? ' · ' . e($mp['title']) : '' ?></span>
                  <a href="<?= e($mp['file_path']) ?>" target="_blank" class="btn sm">Open PDF →</a>
                </div>
                <?php endforeach; ?>
              </div>
            <?php elseif (!$lead['program_path']): ?>
              <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;background:var(--bg);border-radius:10px;margin-bottom:14px">No programs uploaded yet.</div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
              <?= $csrf ?>
              <input type="hidden" name="action" value="program" />
              <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                <select name="week_num" class="field-sel" style="width:auto;flex-shrink:0">
                  <?php for ($w = 1; $w <= $prog['total']; $w++): ?>
                  <option value="<?= $w ?>"<?= in_array($w, array_column($memberPrograms, 'week_number')) ? ' style="color:var(--sage-2);font-weight:600"' : '' ?>>Week <?= $w ?><?= in_array($w, array_column($memberPrograms, 'week_number')) ? ' ✓' : '' ?></option>
                  <?php endfor; ?>
                </select>
                <input type="text" name="program_title" class="field-inp" style="flex:1;min-width:140px" placeholder="Title (optional)" />
              </div>
              <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <input type="file" name="program" accept="application/pdf" required style="font-size:13px;flex:1;min-width:0" />
                <button class="btn pri">Upload PDF</button>
              </div>
            </form>
            <?php
$targetsWeek = max(1, min($prog['total'], (int)($_GET['targets_week'] ?? 1)));
$targetsRow = null;
try {
    $targetsRow = db_get('SELECT program_targets FROM member_programs WHERE lead_id = ? AND week_number = ?', [$id, $targetsWeek]);
} catch (Throwable $ignored) {}
$targetsText = $targetsRow['program_targets'] ?? '';
?>
<div style="margin-top:14px;border-top:1px dashed var(--line);padding-top:14px">
  <label class="field-lbl" style="margin-bottom:6px;display:block">Weekly targets / notes (shown on member's Program page)</label>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
    <?php for ($w = 1; $w <= $prog['total']; $w++): ?>
    <a href="?id=<?= $id ?>&targets_week=<?= $w ?>" class="chip<?= $w === $targetsWeek ? ' ink' : '' ?>" style="font-size:11.5px;cursor:pointer">Week <?= $w ?></a>
    <?php endfor; ?>
  </div>
  <form method="post">
    <?= $csrf ?>
    <input type="hidden" name="action" value="set_program_targets" />
    <input type="hidden" name="target_week" value="<?= $targetsWeek ?>" />
    <textarea name="targets" class="field-inp" rows="3" placeholder="e.g. ≥80% time in range · 5 sessions · glucose before meals: aim 90–120 mg/dL" style="resize:vertical;width:100%;margin-bottom:8px"><?= e($targetsText) ?></textarea>
    <button class="btn sm">Save Week <?= $targetsWeek ?> targets</button>
  </form>
</div>
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
        <div class="card">
          <div class="head">
            <div>
              <div class="eyebrow" style="margin-bottom:3px">Messages</div>
              <h3 class="h3">Conversation</h3>
            </div>
            <a href="/admin/inbox?thread=<?= $id ?>" class="btn sm">Full inbox →</a>
          </div>
          <div id="convoBubbles" style="padding:16px 20px;max-height:380px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;background:linear-gradient(180deg,#FFFDF7,#FAF7F0)">
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
          <div style="padding:14px 20px;border-top:1px solid var(--line)" id="msgSendBar">
            <div style="display:flex;gap:8px;align-items:flex-end">
              <textarea id="msgBody" placeholder="Send a message…" rows="2"
                style="flex:1;border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13.5px;background:#fff;resize:none;outline:none;min-height:40px"></textarea>
              <button class="btn pri sm" id="msgSendBtn" onclick="adminSendMsg()">Send</button>
            </div>
            <div id="msgStatus" style="font-size:11.5px;color:var(--muted);margin-top:6px"></div>
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
          <?php foreach ($logs as $li => $l): ?>
          <tr style="cursor:pointer" onclick="toggleLogDetail(<?= (int)$l['id'] ?>)" title="Click to expand">
            <td style="font-family:'JetBrains Mono',monospace;font-size:12px"><?= e($l['log_date']) ?> <span style="font-size:10px;color:var(--muted)">▸</span></td>
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
          <tr id="log-detail-<?= (int)$l['id'] ?>" style="display:none;background:var(--sage-tint)">
            <td colspan="6" style="padding:14px 18px">
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;font-size:12.5px">
                <?php if ($l['workout']): ?>
                <div><div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:3px">Workout</div><div><?= nl2br(e($l['workout'])) ?></div></div>
                <?php endif; ?>
                <?php if ($l['food_before']): ?>
                <div><div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:3px">Food before</div><div><?= nl2br(e($l['food_before'])) ?></div></div>
                <?php endif; ?>
                <?php if ($l['food_after']): ?>
                <div><div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:3px">Food after</div><div><?= nl2br(e($l['food_after'])) ?></div></div>
                <?php endif; ?>
                <?php if ($l['notes']): ?>
                <div style="grid-column:1/-1"><div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:3px">Notes for coach</div><div><?= nl2br(e($l['notes'])) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($l['workout_journal'])): ?>
                <div style="grid-column:1/-1"><div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--sage-2);margin-bottom:3px">Training journal</div><div style="font-style:italic"><?= nl2br(e($l['workout_journal'])) ?></div></div>
                <?php endif; ?>
                <?php if (!$l['workout'] && !$l['food_before'] && !$l['food_after'] && !$l['notes'] && empty($l['workout_journal'])): ?>
                <div style="color:var(--muted)">No additional notes for this entry.</div>
                <?php endif; ?>
                <div style="grid-column:1/-1;margin-top:6px">
                  <a href="/portal/log?date=<?= urlencode($l['log_date']) ?>" target="_blank" class="btn sm">View member's log →</a>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Glucose trend for admin -->
    <?php if ($admChartPts): ?>
    <div class="section-title">Glucose trend · last 30 days</div>
    <div class="card" style="margin-bottom:20px;padding:20px">
      <div style="display:flex;gap:24px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
        <div>
          <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600">7-day avg</div>
          <div style="font-family:'Instrument Serif',serif;font-size:36px;letter-spacing:-.02em"><?= $avgGlucose ? $avgGlucose . ' <span style="font-size:14px;color:var(--muted)">mg/dL</span>' : '—' ?></div>
        </div>
        <?php
        $admReadings = [];
        $admIn = 0; $admLow = 0; $admHigh = 0;
        foreach ($glucTrend as $gr) {
            foreach (['bs_before','bs_after'] as $col) {
                if (!empty($gr[$col])) {
                    $v = (int)$gr[$col]; $admReadings[] = $v;
                    if ($v < 70) $admLow++;
                    elseif ($v > 180) $admHigh++;
                    else $admIn++;
                }
            }
        }
        $admTotal = count($admReadings);
        $admTir = $admTotal ? round($admIn / $admTotal * 100) : 0;
        ?>
        <div>
          <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600">Time in range (70–180)</div>
          <div style="font-family:'Instrument Serif',serif;font-size:36px;letter-spacing:-.02em;color:<?= $admTir >= 80 ? 'var(--sage-2)' : ($admTir >= 60 ? 'var(--amber)' : 'var(--coral)') ?>"><?= $admTir ?>%</div>
        </div>
        <div style="font-size:12.5px;color:var(--muted)">
          <?= $admTotal ?> readings &middot; <?= $admLow ?> low &middot; <?= $admHigh ?> high
        </div>
      </div>
      <svg viewBox="0 0 800 160" style="width:100%;height:auto;display:block">
        <!-- Target band 70-180 -->
        <?php
        function adm_glucose_y($g) {
            $minG=60;$maxG=220;$minY=10;$maxY=140;
            $g=max($minG,min($maxG,(int)$g));
            return round($maxY-(($g-$minG)/($maxG-$minG))*($maxY-$minY),1);
        }
        ?>
        <rect x="40" y="<?= adm_glucose_y(180) ?>" width="750" height="<?= adm_glucose_y(70)-adm_glucose_y(180) ?>" fill="#E6EFE6" opacity=".5"/>
        <line x1="40" y1="<?= adm_glucose_y(180) ?>" x2="790" y2="<?= adm_glucose_y(180) ?>" stroke="#9CC9A8" stroke-dasharray="3 4" stroke-width="1"/>
        <line x1="40" y1="<?= adm_glucose_y(70) ?>" x2="790" y2="<?= adm_glucose_y(70) ?>" stroke="#9CC9A8" stroke-dasharray="3 4" stroke-width="1"/>
        <!-- Gridlines -->
        <line x1="40" y1="<?= adm_glucose_y(200) ?>" x2="790" y2="<?= adm_glucose_y(200) ?>" stroke="#E2DCCD" stroke-width="1"/>
        <line x1="40" y1="<?= adm_glucose_y(120) ?>" x2="790" y2="<?= adm_glucose_y(120) ?>" stroke="#E2DCCD" stroke-width="1"/>
        <!-- Y labels -->
        <text x="2" y="<?= adm_glucose_y(200)+4 ?>" font-family="JetBrains Mono" font-size="9" fill="#9AA197">200</text>
        <text x="2" y="<?= adm_glucose_y(180)+4 ?>" font-family="JetBrains Mono" font-size="9" fill="#9CC9A8">180</text>
        <text x="2" y="<?= adm_glucose_y(70)+4 ?>" font-family="JetBrains Mono" font-size="9" fill="#9CC9A8">70</text>
        <!-- Chart line -->
        <?php
        $admPts = array_map(fn($p) => $p['x'].','.$p['y'], $admChartPts);
        $admPath = 'M' . implode(' L', $admPts);
        $admFirst = $admChartPts[0]; $admLast = $admChartPts[count($admChartPts)-1];
        $admArea = $admPath . " L{$admLast['x']},150 L{$admFirst['x']},150 Z";
        ?>
        <path d="<?= e($admArea) ?>" fill="#4A8A68" opacity=".08"/>
        <path d="<?= e($admPath) ?>" fill="none" stroke="#4A8A68" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <?php foreach ($admChartPts as $i => $pt): ?>
          <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="<?= $i === count($admChartPts)-1 ? 4 : 3 ?>" fill="<?= ($pt['val'] < 70 || $pt['val'] > 180) ? '#C66B5B' : '#4A8A68' ?>"/>
        <?php endforeach; ?>
        <!-- X labels -->
        <?php foreach ($admChartPts as $i => $pt): if ($i % 2 === 0): ?>
          <text x="<?= $pt['x'] ?>" y="157" font-family="JetBrains Mono" font-size="8" fill="#9AA197" text-anchor="middle"><?= strtoupper(date('MD', strtotime($pt['date']))) ?></text>
        <?php endif; endforeach; ?>
      </svg>
    </div>
    <?php endif; ?>

    <!-- Meal photos -->
    <?php if ($meals): ?>
    <div class="section-title">Meal photos (<?= count($meals) ?>)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:20px">
      <?php foreach ($meals as $m): ?>
      <div style="border-radius:12px;overflow:hidden;border:1px solid var(--line);background:var(--card);cursor:pointer" onclick="openMealModal(<?= (int)$m['id'] ?>, '<?= e(addslashes($m['file_path'])) ?>', '<?= e(addslashes($m['meal_type'] ?: 'Meal')) ?>', '<?= e(substr($m['created_at'], 0, 16)) ?>', <?= json_encode($m['admin_comment'] ?? '') ?>)">
        <img src="/<?= e($m['file_path']) ?>" alt="" loading="lazy" style="width:100%;height:120px;object-fit:cover;display:block" />
        <div style="padding:8px 10px;font-size:11.5px">
          <div style="font-weight:600"><?= e($m['meal_type'] ?: 'Meal') ?></div>
          <div style="color:var(--muted)"><?= substr($m['created_at'], 0, 10) ?></div>
          <?php if (!empty($m['admin_comment'])): ?>
            <div style="color:var(--sage-2);font-size:10.5px;margin-top:3px">💬 Commented</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Meal photo modal -->
    <div id="mealModal" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(27,32,28,.55);backdrop-filter:blur(2px);align-items:center;justify-content:center" onclick="if(event.target===this)closeMealModal()">
      <div style="background:var(--card);border-radius:18px;width:min(560px,92vw);max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lift)">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--line)">
          <div>
            <div id="mealModalType" style="font-weight:600;font-size:15px"></div>
            <div id="mealModalDate" style="color:var(--muted);font-size:12px;margin-top:2px"></div>
          </div>
          <button onclick="closeMealModal()" style="width:32px;height:32px;border-radius:8px;background:var(--bg-2);display:grid;place-items:center;color:var(--muted)">&times;</button>
        </div>
        <div style="padding:0">
          <img id="mealModalImg" src="" alt="" style="width:100%;display:block;max-height:400px;object-fit:contain;background:#f5f3eb" />
        </div>
        <div style="padding:16px 20px;border-top:1px solid var(--line)">
          <label style="font-size:12px;font-weight:600;color:var(--ink-2);display:block;margin-bottom:6px">Coach comment</label>
          <textarea id="mealCommentInput" rows="2" style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:13px;background:var(--bg);resize:vertical;outline:none" placeholder="Add a comment about this meal…"></textarea>
          <input type="hidden" id="mealCommentId" value="" />
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
            <span id="mealCommentStatus" style="font-size:12px;color:var(--sage-2)"></span>
            <button class="btn pri" style="font-size:12.5px;padding:8px 14px" onclick="saveMealComment()">Save comment</button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /view -->
</main>
</div>
<script>
const MEMBER_CSRF = <?= json_encode(csrf_token()) ?>;
const MEMBER_ID = <?= (int)$id ?>;

async function adminSendMsg() {
  const body = document.getElementById('msgBody').value.trim();
  if (!body) return;
  const btn = document.getElementById('msgSendBtn');
  const status = document.getElementById('msgStatus');
  btn.disabled = true; btn.textContent = 'Sending…';
  try {
    const fd = new FormData();
    fd.append('action', 'add_public');
    fd.append('lead_id', MEMBER_ID);
    fd.append('body', body);
    fd.append('csrf', MEMBER_CSRF);
    fd.append('_ajax', '1');
    const res = await fetch('/admin/note_action', { method: 'POST', body: fd });
    const text = await res.text();
    if (res.ok) {
      document.getElementById('msgBody').value = '';
      status.textContent = 'Sent ✓';
      // Append bubble optimistically
      const wrap = document.getElementById('convoBubbles');
      if (wrap) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
        const div = document.createElement('div');
        div.className = 'note-bubble me';
        div.innerHTML = body.replace(/\n/g,'<br>') + '<span class="t">just now</span>';
        wrap.appendChild(div);
        wrap.scrollTop = wrap.scrollHeight;
      }
      setTimeout(() => { status.textContent = ''; }, 3000);
    } else {
      status.textContent = 'Send failed — try again';
    }
  } catch(e) {
    status.textContent = 'Network error';
  }
  btn.disabled = false; btn.textContent = 'Send';
}

document.getElementById('msgBody')?.addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') adminSendMsg();
});

function toggleLogDetail(id) {
  var row = document.getElementById('log-detail-' + id);
  if (!row) return;
  row.style.display = row.style.display === 'none' ? '' : 'none';
}

function openMealModal(id, path, type, date, comment) {
  document.getElementById('mealModalImg').src = '/' + path;
  document.getElementById('mealModalType').textContent = type;
  document.getElementById('mealModalDate').textContent = date;
  document.getElementById('mealCommentInput').value = comment || '';
  document.getElementById('mealCommentId').value = id;
  document.getElementById('mealCommentStatus').textContent = '';
  document.getElementById('mealModal').style.display = 'flex';
}
function closeMealModal() {
  document.getElementById('mealModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMealModal(); });

async function saveMealComment() {
  const mealId = document.getElementById('mealCommentId').value;
  const comment = document.getElementById('mealCommentInput').value.trim();
  const status = document.getElementById('mealCommentStatus');
  try {
    const fd = new FormData();
    fd.append('action', 'comment_meal');
    fd.append('meal_id', mealId);
    fd.append('comment', comment);
    fd.append('csrf', MEMBER_CSRF);
    const res = await fetch('/admin/member?id=' + MEMBER_ID, { method: 'POST', body: fd });
    if (res.ok) {
      status.textContent = 'Saved ✓';
      setTimeout(() => { status.textContent = ''; }, 3000);
    } else {
      status.textContent = 'Failed — try again';
    }
  } catch(e) {
    status.textContent = 'Network error';
  }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
