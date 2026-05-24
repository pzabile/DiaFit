<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$waiting = 0;
$preview = [];
try {
    $waiting = (int)(db_get("
        SELECT COUNT(DISTINCT l.id) c FROM leads l
        JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)
        WHERE l.paid=1 AND cn.from_member=1
          AND NOT EXISTS (SELECT 1 FROM coach_notes cn2 WHERE cn2.lead_id=l.id AND cn2.from_member=0 AND cn2.created_at > cn.created_at)
    ")['c'] ?? 0);

    $preview = db_all("
        SELECT l.id, l.first_name, l.email, l.plan_days,
               cn.body AS last_msg, TIMESTAMPDIFF(MINUTE, cn.created_at, NOW()) AS waiting_min
        FROM leads l
        JOIN coach_notes cn ON cn.id=(SELECT MAX(id) FROM coach_notes WHERE lead_id=l.id)
        WHERE l.paid=1 AND cn.from_member=1
          AND NOT EXISTS (SELECT 1 FROM coach_notes cn2 WHERE cn2.lead_id=l.id AND cn2.from_member=0 AND cn2.created_at > cn.created_at)
        ORDER BY cn.created_at ASC LIMIT 5
    ");
} catch (Throwable $ignored) {}

echo json_encode(['waiting' => $waiting, 'preview' => $preview]);
