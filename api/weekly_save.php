<?php
/**
 * POST /api/weekly_save
 * Upsert a weekly note for the authenticated member.
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

$raw  = file_get_contents('php://input');
$data = ($raw && strpos($raw, '{') === 0)
    ? (json_decode($raw, true) ?: [])
    : $_POST;

$csrf = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'csrf']);
    exit;
}

$weekNum     = max(1, (int)($data['week_number'] ?? 1));
$avgGlucose  = trim($data['avg_glucose']  ?? '') !== '' ? (int)$data['avg_glucose']  : null;
$weightKg    = trim($data['weight_kg']    ?? '') !== '' ? (float)$data['weight_kg']  : null;
$energyRating= trim($data['energy_rating']?? '') !== '' ? (int)$data['energy_rating'] : null;
$wins        = trim($data['wins']         ?? '') ?: null;
$struggles   = trim($data['struggles']    ?? '') ?: null;
$content     = trim($data['content']      ?? '') ?: null;

$existing = db_get(
    'SELECT id FROM weekly_notes WHERE lead_id = ? AND week_number = ?',
    [$leadId, $weekNum]
);

if ($existing) {
    db_exec(
        'UPDATE weekly_notes SET
            avg_glucose   = COALESCE(?, avg_glucose),
            weight_kg     = COALESCE(?, weight_kg),
            energy_rating = COALESCE(?, energy_rating),
            wins          = COALESCE(?, wins),
            struggles     = COALESCE(?, struggles),
            content       = COALESCE(?, content)
         WHERE id = ?',
        [$avgGlucose, $weightKg, $energyRating, $wins, $struggles, $content, (int)$existing['id']]
    );
    $id = (int)$existing['id'];
} else {
    $id = db_insert(
        'INSERT INTO weekly_notes (lead_id, week_number, avg_glucose, weight_kg, energy_rating, wins, struggles, content, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [$leadId, $weekNum, $avgGlucose, $weightKg, $energyRating, $wins, $struggles, $content]
    );
}

echo json_encode(['ok' => true, 'id' => $id]);
