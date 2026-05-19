<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/pdf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$allowed = [
    'diabetes_type', 'gender', 'age', 'weight', 'motivation',
    'doctor_recommended', 'exercise_history', 'side_effects',
    'goals', 'location', 'days_per_week', 'minutes_per_day', 'email',
];
$clean = [];
foreach ($allowed as $k) {
    if (!array_key_exists($k, $data)) continue;
    $v = $data[$k];
    $clean[$k] = is_array($v)
        ? array_values(array_map(fn($x) => substr((string)$x, 0, 80), $v))
        : substr((string)$v, 0, 200);
}

$_SESSION['answers'] = $clean;
$email = $clean['email'] ?? '';

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $leadId = lead_upsert_from_assessment($email, '', '', $clean);
        $_SESSION['lead_id'] = $leadId;
    } catch (Throwable $ex) {
        error_log('lead upsert failed: ' . $ex->getMessage());
    }
}

try {
    $tmpPdf = sys_get_temp_dir() . '/diafitus_lead_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
    build_lead_pdf($tmpPdf, $clean);

    $lines = ['<b>DiaFitus — new questionnaire submission</b>'];
    $lines[] = 'Time: ' . date('Y-m-d H:i:s');
    foreach ($clean as $k => $v) {
        $val = is_array($v) ? implode(', ', $v) : $v;
        $lines[] = '<b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ':</b> ' . htmlspecialchars($val);
    }
    tg_send_message(implode("\n", $lines));
    tg_send_document($tmpPdf, 'DiaFitus questionnaire (PDF)');
    @unlink($tmpPdf);
} catch (Throwable $ex) {
    error_log('submit_quiz dispatch error: ' . $ex->getMessage());
}

echo json_encode(['ok' => true, 'redirect' => 'offer']);
