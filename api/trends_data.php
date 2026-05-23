<?php
/**
 * GET /api/trends_data?days=30
 * Returns glucose, weekly note, and training data for charts.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = require_member();
$leadId = (int)$me['id'];

$days = max(7, min(365, (int)($_GET['days'] ?? 30)));

$glucose = db_all(
    'SELECT log_date as date,
            NULLIF(bs_before, 0) as before_val,
            NULLIF(bs_after, 0) as after_val
     FROM daily_logs
     WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY log_date ASC',
    [$leadId, $days]
);

// Rename keys to match spec
$glucoseOut = array_map(fn($r) => [
    'date'   => $r['date'],
    'before' => $r['before_val'] ? (int)$r['before_val'] : null,
    'after'  => $r['after_val']  ? (int)$r['after_val']  : null,
], $glucose);

$weekly = db_all(
    'SELECT week_number, avg_glucose, weight_kg, energy_rating
     FROM weekly_notes
     WHERE lead_id = ?
     ORDER BY week_number ASC
     LIMIT 12',
    [$leadId]
);

$training = db_all(
    'SELECT log_date as date, trained
     FROM daily_logs
     WHERE lead_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     ORDER BY log_date ASC',
    [$leadId, $days]
);

echo json_encode([
    'ok'       => true,
    'glucose'  => $glucoseOut,
    'weekly'   => $weekly,
    'training' => $training,
]);
