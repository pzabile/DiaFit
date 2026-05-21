<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(400); echo 'bad request'; exit;
}

$action = $_POST['action'] ?? '';
$leadId = (int) ($_POST['lead_id'] ?? 0);

if (!$leadId) { http_response_code(400); echo 'missing lead'; exit; }

if ($action === 'add_public') {
    $body = trim($_POST['body'] ?? '');
    $kind = substr(trim($_POST['kind'] ?? 'note'), 0, 40) ?: 'note';
    $week = ($_POST['week'] ?? '') !== '' ? (int) $_POST['week'] : null;
    if ($body !== '') {
        db_insert(
            'INSERT INTO coach_notes (lead_id, week_number, body, kind, is_private, from_member) VALUES (?, ?, ?, ?, 0, 0)',
            [$leadId, $week, $body, $kind]
        );
    }
} elseif ($action === 'add_private') {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        db_insert(
            'INSERT INTO coach_notes (lead_id, body, kind, is_private, from_member) VALUES (?, ?, ?, 1, 0)',
            [$leadId, $body, 'admin_note']
        );
    }
} elseif ($action === 'comment_target') {
    $body = trim($_POST['body'] ?? '');
    $targetType = substr(trim($_POST['target_type'] ?? ''), 0, 20);
    $targetId   = (int) ($_POST['target_id'] ?? 0);
    if (!in_array($targetType, ['weekly_note', 'daily_log'], true) || !$targetId) {
        http_response_code(400); echo 'bad target'; exit;
    }
    if ($body !== '') {
        db_insert(
            'INSERT INTO coach_notes (lead_id, body, kind, is_private, from_member, target_type, target_id)
             VALUES (?, ?, ?, 0, 0, ?, ?)',
            [$leadId, $body, 'comment', $targetType, $targetId]
        );
    }
} elseif ($action === 'reply') {
    $body = trim($_POST['body'] ?? '');
    $parentId = (int) ($_POST['parent_id'] ?? 0);
    if ($body !== '' && $parentId) {
        // copy targeting from parent so the reply sits in the same thread
        $parent = db_get('SELECT * FROM coach_notes WHERE id = ? AND lead_id = ?', [$parentId, $leadId]);
        db_insert(
            'INSERT INTO coach_notes (lead_id, body, kind, is_private, from_member, target_type, target_id, parent_id)
             VALUES (?, ?, "reply", 0, 0, ?, ?, ?)',
            [$leadId, $body, $parent['target_type'] ?? null, $parent['target_id'] ?? null, $parentId]
        );
    }
} elseif ($action === 'delete') {
    $noteId = (int) ($_POST['note_id'] ?? 0);
    if ($noteId) {
        db_exec('DELETE FROM coach_notes WHERE id = ? AND lead_id = ?', [$noteId, $leadId]);
        // also remove replies that thread off it
        db_exec('DELETE FROM coach_notes WHERE parent_id = ? AND lead_id = ?', [$noteId, $leadId]);
    }
}

header('Location: /admin/member?id=' . $leadId);
