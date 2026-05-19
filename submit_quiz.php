<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/pdf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

// Whitelist + sanitize.
$allowed = [
    'diabetes_type', 'gender', 'age', 'weight', 'motivation',
    'doctor_recommended', 'exercise_history', 'side_effects',
    'goals', 'location', 'days_per_week', 'minutes_per_day', 'email',
];
$clean = [];
foreach ($allowed as $k) {
    if (!array_key_exists($k, $data)) continue;
    $v = $data[$k];
    if (is_array($v)) {
        $clean[$k] = array_values(array_map(fn($x) => substr((string)$x, 0, 80), $v));
    } else {
        $clean[$k] = substr((string)$v, 0, 200);
    }
}

$_SESSION['answers'] = $clean;
$_SESSION['answers_submitted_at'] = date('c');

// Build PDF + text and dispatch to Telegram (non-blocking errors).
try {
    $tmpPdf = sys_get_temp_dir() . '/diafitus_lead_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
    build_lead_pdf($tmpPdf, $clean);

    $textLines = ["<b>DiaFitus — New questionnaire submission</b>"];
    $textLines[] = "Time: " . date('Y-m-d H:i:s');
    foreach ($clean as $k => $v) {
        $val = is_array($v) ? implode(', ', $v) : $v;
        $textLines[] = "<b>" . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ":</b> " . htmlspecialchars($val);
    }
    tg_send_message(implode("\n", $textLines));
    tg_send_document($tmpPdf, 'DiaFitus questionnaire (PDF)');
    @unlink($tmpPdf);
} catch (Throwable $ex) {
    error_log('submit_quiz dispatch error: ' . $ex->getMessage());
}

echo json_encode(['ok' => true, 'redirect' => 'offer.php']);
