<?php
/**
 * POST /api/log_save
 * Upsert a daily log entry for the authenticated member.
 * Accepts JSON body or form POST.
 * CSRF: X-CSRF-Token header or csrf body field.
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

// Parse body
$raw  = file_get_contents('php://input');
$data = ($raw && strpos($raw, '{') === 0)
    ? (json_decode($raw, true) ?: [])
    : $_POST;

// CSRF
$csrf = $data['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'csrf']);
    exit;
}

// Validate date
$logDate = $data['log_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
    $logDate = date('Y-m-d');
}

// Sanitize fields
function nullable($v) { $v = trim((string)$v); return $v === '' ? null : $v; }
function nullableInt($v) { $v = trim((string)$v); return $v === '' ? null : (int)$v; }

$feeling    = nullable($data['feeling']    ?? '');
$trained    = nullable($data['trained']    ?? '');
$trainWhere = nullable($data['train_where'] ?? '');
$workout    = nullable($data['workout']    ?? '');
$soreness   = nullableInt($data['soreness'] ?? '');
$bsBefore   = nullableInt($data['bs_before'] ?? '');
$bsAfter    = nullableInt($data['bs_after']  ?? '');
$bsTrend    = nullable($data['bs_trend']   ?? '');
$foodBefore = nullable($data['food_before'] ?? '');
$foodAfter  = nullable($data['food_after']  ?? '');
$notes          = nullable($data['notes']           ?? '');
$workoutJournal = nullable($data['workout_journal'] ?? '');

$entryId = (int)($data['id'] ?? 0);

if ($entryId > 0) {
    // Edit existing entry (verify it belongs to this member)
    $existing = db_get('SELECT id FROM daily_logs WHERE id = ? AND lead_id = ?', [$entryId, $leadId]);
    if ($existing) {
        db_exec(
            'UPDATE daily_logs SET
                feeling = COALESCE(?, feeling),
                trained = COALESCE(?, trained),
                train_where = COALESCE(?, train_where),
                workout = COALESCE(?, workout),
                soreness = COALESCE(?, soreness),
                bs_before = COALESCE(?, bs_before),
                bs_after = COALESCE(?, bs_after),
                bs_trend = COALESCE(?, bs_trend),
                food_before = COALESCE(?, food_before),
                food_after = COALESCE(?, food_after),
                notes = COALESCE(?, notes),
                workout_journal = COALESCE(?, workout_journal)
             WHERE id = ?',
            [$feeling, $trained, $trainWhere, $workout, $soreness,
             $bsBefore, $bsAfter, $bsTrend, $foodBefore, $foodAfter,
             $notes, $workoutJournal, $entryId]
        );
        $id = $entryId;
    } else {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'not_found']);
        exit;
    }
} else {
    // New entry — always INSERT (multiple per day allowed)
    $id = db_insert(
        'INSERT INTO daily_logs
            (lead_id, log_date, feeling, trained, train_where, workout, soreness,
             bs_before, bs_after, bs_trend, food_before, food_after, notes, workout_journal, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [$leadId, $logDate, $feeling, $trained, $trainWhere, $workout, $soreness,
         $bsBefore, $bsAfter, $bsTrend, $foodBefore, $foodAfter, $notes, $workoutJournal]
    );
}

echo json_encode(['ok' => true, 'id' => $id]);
