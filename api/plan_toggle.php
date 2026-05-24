<?php
/**
 * POST /api/plan_toggle
 * Toggle a daily plan item done state for the authenticated member.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = require_member();
$leadId = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = ($raw && strpos($raw, '{') === 0) ? (json_decode($raw, true) ?: []) : $_POST;

$csrf = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'csrf']);
    exit;
}

$itemId = (int)($data['id'] ?? 0);
$done   = (int)($data['done'] ?? 0);

if (!$itemId) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_id']);
    exit;
}

// Verify item belongs to this member
$item = db_get('SELECT id, is_done FROM daily_plan_items WHERE id = ? AND lead_id = ?', [$itemId, $leadId]);
if (!$item) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not_found']);
    exit;
}

db_exec('UPDATE daily_plan_items SET is_done = ? WHERE id = ?', [$done ? 1 : 0, $itemId]);

echo json_encode(['ok' => true, 'id' => $itemId, 'done' => (int)($done ? 1 : 0)]);
