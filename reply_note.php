<?php
// Member posts a reply or a new question for the coach on a coach note,
// a weekly check-in, or a daily log.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/telegram.php';

$me = require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    header('Location: /program'); exit;
}

$body = trim($_POST['body'] ?? '');
if ($body === '') {
    $_SESSION['flash'] = 'Please write something before sending.';
    header('Location: ' . ($_POST['redirect'] ?? '/program'));
    exit;
}

$parentId   = ($_POST['parent_id']   ?? '') !== '' ? (int) $_POST['parent_id']   : null;
$targetType = trim($_POST['target_type'] ?? '');
$targetId   = ($_POST['target_id']   ?? '') !== '' ? (int) $_POST['target_id']   : null;

// Validate target type and (when replying to a note) lead-ownership of the parent.
if ($parentId) {
    $parent = db_get('SELECT * FROM coach_notes WHERE id = ? AND lead_id = ?', [$parentId, $me['id']]);
    if (!$parent) { header('Location: /program'); exit; }
    $targetType = $parent['target_type'];
    $targetId   = $parent['target_id'];
}
if (!in_array($targetType, ['weekly_note', 'daily_log', '', null], true)) $targetType = '';

db_insert(
    'INSERT INTO coach_notes (lead_id, body, kind, is_private, from_member, target_type, target_id, parent_id)
     VALUES (?, ?, "member_msg", 0, 1, ?, ?, ?)',
    [$me['id'], $body, $targetType ?: null, $targetId, $parentId]
);

// Ping the owner on Telegram so they know to respond.
try {
    $name = $me['first_name'] ?: $me['email'];
    $context = $parentId ? 'reply'
              : ($targetType === 'weekly_note' ? 'comment on weekly check-in'
              : ($targetType === 'daily_log'  ? 'comment on daily log'
              : 'new message'));
    tg_send_message(
        '<b>💬 Member ' . $context . '</b>' . "\n" .
        '<b>From:</b> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($me['email']) . ")\n" .
        '<b>Message:</b> ' . htmlspecialchars(mb_strimwidth($body, 0, 400, '…')) . "\n\n" .
        'Open: ' . rtrim(cfg('site_url'), '/') . '/admin/member?id=' . (int) $me['id']
    );
} catch (Throwable $ex) {
    error_log('reply_note telegram: ' . $ex->getMessage());
}

$_SESSION['flash'] = 'Message sent — we usually reply within a few hours.';
header('Location: ' . ($_POST['redirect'] ?? '/program'));
