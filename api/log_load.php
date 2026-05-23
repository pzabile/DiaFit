<?php
/**
 * GET /api/log_load?date=Y-m-d
 * Returns the daily log for a given date (default today).
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = require_member();
$leadId = (int)$me['id'];

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$log = db_get(
    'SELECT * FROM daily_logs WHERE lead_id = ? AND log_date = ?',
    [$leadId, $date]
);

echo json_encode(['ok' => true, 'log' => $log ?: null]);
