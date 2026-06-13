<?php
/**
 * AJAX endpoint for member ↔ coach chat.
 * GET  ?since=N   → JSON array of messages with id > N
 * POST body=...   → insert message, return new message JSON
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me     = require_member();
$leadId = (int)$me['id'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = $raw ? (json_decode($raw, true) ?: []) : $_POST;
    $body = trim($data['body'] ?? '');
    $csrf = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!csrf_check($csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    if (!$body) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'empty']);
        exit;
    }

    db()->prepare("INSERT INTO coach_notes (lead_id, body, from_member, created_at) VALUES (?, ?, 1, NOW())")
        ->execute([$leadId, $body]);
    $newId = (int)db()->lastInsertId();
    $msg   = db_get("SELECT id, body, from_member, created_at FROM coach_notes WHERE id = ?", [$newId]);

    echo json_encode(['ok' => true, 'message' => $msg]);

} else {
    /* Poll: return messages newer than ?since=N */
    $since = max(0, (int)($_GET['since'] ?? 0));
    $msgs  = db_all(
        "SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id = ? AND id > ? ORDER BY id ASC",
        [$leadId, $since]
    );
    $lastId = $msgs ? (int)end($msgs)['id'] : $since;
    echo json_encode(['ok' => true, 'messages' => $msgs, 'last_id' => $lastId]);
}
