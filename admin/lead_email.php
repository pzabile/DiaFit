<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';
require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$leadId = (int)($_POST['lead_id'] ?? 0);
if (!$leadId) {
    echo json_encode(['ok' => false, 'error' => 'Missing lead_id']);
    exit;
}

$lead = db_get('SELECT * FROM leads WHERE id = ? AND paid = 0 LIMIT 1', [$leadId]);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'Lead not found']);
    exit;
}

if (!empty($lead['opted_out'])) {
    echo json_encode(['ok' => false, 'error' => 'This lead has opted out of marketing emails.']);
    exit;
}

$name     = $lead['first_name'] ?: '';
$email    = $lead['email'];
$answers  = json_decode($lead['answers_json'] ?? '{}', true) ?: [];
$diabType = $answers['diabetes_type'] ?? '';
$goals    = $answers['goals'] ?? [];

try {
    $sent = send_email(
        $email,
        $name ?: 'there',
        'Your personalized diabetes plan is waiting — exclusive 20% off inside',
        lead_followup_email_html($name, $email, $diabType, $goals)
    );
    if ($sent) {
        db_exec('UPDATE leads SET email_sent_count = COALESCE(email_sent_count, 0) + 1 WHERE id = ?', [$leadId]);
        $newCount = (int)(db_get('SELECT email_sent_count FROM leads WHERE id = ?', [$leadId])['email_sent_count'] ?? 1);
        echo json_encode(['ok' => true, 'sent_count' => $newCount]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'mail() returned false — check SMTP config']);
    }
} catch (Throwable $ex) {
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
