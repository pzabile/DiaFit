<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$leadId  = (int)($_POST['lead_id'] ?? 0);
$newState = (int)(!empty($_POST['opted_out'])); // 1 = opt out, 0 = re-subscribe

if (!$leadId) {
    echo json_encode(['ok' => false, 'error' => 'Missing lead_id']);
    exit;
}

$lead = db_get('SELECT id FROM leads WHERE id = ? AND paid = 0 LIMIT 1', [$leadId]);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'Lead not found']);
    exit;
}

if ($newState) {
    db_exec('UPDATE leads SET opted_out = 1, opted_out_at = NOW() WHERE id = ?', [$leadId]);
} else {
    db_exec('UPDATE leads SET opted_out = 0, opted_out_at = NULL WHERE id = ?', [$leadId]);
}

echo json_encode(['ok' => true, 'opted_out' => (bool)$newState]);
