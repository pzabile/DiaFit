<?php
/**
 * Admin inbox API — polling + send.
 *
 * GET  ?thread=lead_id&since=N  → new messages since id N
 * POST JSON {thread, body, csrf} → send coach reply
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/uploads.php';
require_admin();

set_exception_handler(function(Throwable $ex) {
    error_log('inbox_api error: ' . $ex->getMessage());
    while (ob_get_level()) ob_end_clean();
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];

/* ── GET: poll for new messages ── */
if ($method === 'GET') {
    $threadId = (int)($_GET['thread'] ?? 0);
    $since    = (int)($_GET['since'] ?? 0);

    if (!$threadId) {
        echo json_encode(['ok' => false, 'error' => 'missing thread']);
        exit;
    }

    $rows = db_all(
        'SELECT id, body, image_path, from_member, created_at
         FROM coach_notes
         WHERE lead_id = ? AND id > ?
         ORDER BY id ASC',
        [$threadId, $since]
    );

    $messages = [];
    foreach ($rows as $r) {
        $messages[] = [
            'id'          => (int)$r['id'],
            'body'        => $r['body'],
            'image_path'  => $r['image_path'] ?? null,
            'from_member' => (int)$r['from_member'],
            'time'        => date('g:i A', strtotime($r['created_at'])),
            'created_at'  => $r['created_at'],
        ];
    }

    $lastId = $messages ? (int)end($messages)['id'] : $since;

    echo json_encode(['ok' => true, 'messages' => $messages, 'last_id' => $lastId]);
    exit;
}

/* ── POST: send a coach reply, upload image, or resolve thread ── */
if ($method === 'POST') {
    $imagePath = null;

    // Multipart upload (FormData with image)
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!csrf_check($token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            exit;
        }
        $threadId = (int)($_POST['thread'] ?? 0);
        $body     = trim($_POST['body'] ?? '');

        $lead = db_get('SELECT id FROM leads WHERE id = ? AND paid = 1', [$threadId]);
        if (!$lead) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'lead not found']);
            exit;
        }

        try {
            $imagePath = save_uploaded_image($_FILES['image'], $threadId, 'chat');
        } catch (RuntimeException $ex) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
            exit;
        }

        if (!$body && !$imagePath) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'empty']);
            exit;
        }

        $newId = db_insert(
            'INSERT INTO coach_notes (lead_id, body, image_path, from_member, is_private, created_at)
             VALUES (?, ?, ?, 0, 0, NOW())',
            [$threadId, $body ?: '', $imagePath]
        );

        $now = date('Y-m-d H:i:s');
        echo json_encode([
            'ok'      => true,
            'message' => [
                'id'          => $newId,
                'body'        => $body ?: '',
                'image_path'  => $imagePath,
                'from_member' => 0,
                'time'        => date('g:i A'),
                'created_at'  => $now,
            ],
        ]);
        exit;
    }

    // JSON request (text message or resolve action)
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid json']);
        exit;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf'] ?? '');
    if (!csrf_check($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }

    // Resolve action — dismiss waiting notification
    if (($data['action'] ?? '') === 'resolve') {
        $threadId = (int)($data['thread'] ?? 0);
        if (!$threadId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'missing thread']);
            exit;
        }
        db_exec('UPDATE leads SET coach_dismissed_at = NOW() WHERE id = ? AND paid = 1', [$threadId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Send text message
    $threadId = (int)($data['thread'] ?? 0);
    $body     = trim((string)($data['body'] ?? ''));

    if (!$threadId || !$body) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing fields']);
        exit;
    }

    $lead = db_get('SELECT id FROM leads WHERE id = ? AND paid = 1', [$threadId]);
    if (!$lead) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'lead not found']);
        exit;
    }

    $newId = db_insert(
        'INSERT INTO coach_notes (lead_id, body, from_member, is_private, created_at)
         VALUES (?, ?, 0, 0, NOW())',
        [$threadId, $body]
    );

    $now = date('Y-m-d H:i:s');
    echo json_encode([
        'ok'      => true,
        'message' => [
            'id'          => $newId,
            'body'        => $body,
            'image_path'  => null,
            'from_member' => 0,
            'time'        => date('g:i A'),
            'created_at'  => $now,
        ],
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'method not allowed']);
