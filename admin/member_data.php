<?php
/**
 * JSON API: member drawer data for admin.
 * GET ?id=X → member profile, last messages, recent logs, health context
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

// Override the HTML exception handler with a JSON one for this API endpoint
set_exception_handler(function(Throwable $ex) {
    error_log('member_data error: ' . $ex->getMessage());
    while (ob_get_level()) ob_end_clean();
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }

$m = db_get("SELECT id, first_name, email, phone, started_at, plan_days, program_path, created_at, answers_json FROM leads WHERE id=? AND paid=1", [$id]);
if (!$m) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }

$answers = json_decode($m['answers_json'] ?? '{}', true) ?: [];

/* Week info */
$planDays  = (int)($m['plan_days'] ?? 84);
$planWeeks = max(1, (int)ceil($planDays / 7));
$weekNum   = 1;
if (!empty($m['started_at'])) {
    $elapsed = floor((time() - strtotime($m['started_at'])) / 86400);
    $weekNum = min($planWeeks, max(1, (int)ceil(($elapsed + 1) / 7)));
}

/* Streak */
$streak = 0;
$logDates = db_all("SELECT log_date FROM daily_logs WHERE lead_id=? ORDER BY log_date DESC LIMIT 90", [$id]);
$checkDate = new DateTime('today');
foreach ($logDates as $row) {
    $d = new DateTime($row['log_date']);
    if ($d->format('Y-m-d') === $checkDate->format('Y-m-d')) {
        $streak++;
        $checkDate->modify('-1 day');
    } else {
        break;
    }
}

/* Avg glucose last 7 days */
$avgGlucose = null;
$gluc = db_get("SELECT AVG((bs_before+bs_after)/2) v FROM daily_logs WHERE lead_id=? AND log_date >= CURDATE()-INTERVAL 7 DAY AND bs_before IS NOT NULL", [$id]);
if ($gluc && $gluc['v']) $avgGlucose = (int)round($gluc['v']);

/* Last messages */
$rawMsgs = db_all("SELECT id, body, from_member, created_at FROM coach_notes WHERE lead_id=? ORDER BY id DESC LIMIT 10", [$id]);
$rawMsgs = array_reverse($rawMsgs);
$msgs = array_map(function($m) {
    $m['time'] = date('g:i A', strtotime($m['created_at']));
    return $m;
}, $rawMsgs);

/* Recent events (logs) — intentionally omit non-portable columns like `workout` */
$events = [];
try {
    $events = db_all("
        SELECT 'log' AS type, log_date AS date, created_at,
               COALESCE(feeling,'—') AS label,
               CONCAT_WS(' · ',
                 IF(trained='yes' OR trained='Yes', 'Training session', NULL),
                 IF(soreness IS NOT NULL, CONCAT('Soreness ',soreness,'/10'), NULL),
                 IF(bs_before IS NOT NULL AND bs_after IS NOT NULL,
                    CONCAT('Δ ',IF(bs_after>bs_before,'+',''),bs_after-bs_before,' mg/dL'), NULL)
               ) AS notes,
               CASE feeling WHEN 'great' THEN 'sage' WHEN 'rough' THEN 'coral' ELSE '' END AS chip_cls
        FROM daily_logs WHERE lead_id=? ORDER BY log_date DESC, created_at DESC LIMIT 5
    ", [$id]);
} catch (Throwable $ignored) {}

/* Health context from answers */
$health = [
    'diabetes_type' => $answers['diabetes_type'] ?? null,
    'a1c'           => $answers['a1c']           ?? null,
    'medication'    => $answers['medication']     ?? null,
    'cgm'           => $answers['cgm_device']     ?? null,
];

/* Initials + color */
function md_initials(string $name, string $email): string {
    $s = trim($name);
    if ($s) { $p = preg_split('/\s+/', $s); return count($p)>=2 ? strtoupper(mb_substr($p[0],0,1).mb_substr($p[1],0,1)) : strtoupper(mb_substr($s,0,2)); }
    return strtoupper(mb_substr($email,0,2));
}
function md_color(string $s): string {
    $c = ['','sage','plum','coral','sky']; return $c[abs(crc32($s)) % count($c)];
}

$planLbl = $planDays<=7 ? '7-day' : ($planDays<=28 ? '4-wk' : ($planDays<=56 ? '8-wk' : '12-wk'));

echo json_encode([
    'ok'         => true,
    'id'         => (int)$m['id'],
    'initials'   => md_initials($m['first_name']??'', $m['email']),
    'av_color'   => md_color($m['email']),
    'name'       => $m['first_name'] ?: explode('@', $m['email'])[0],
    'email'      => $m['email'],
    'phone'      => $m['phone'] ?: 'no phone on file',
    'plan_label' => $planLbl,
    'plan_weeks' => $planWeeks,
    'week_num'   => $weekNum,
    'started_at' => $m['started_at'] ? date('M j, Y', strtotime($m['started_at'])) : null,
    'streak'     => $streak,
    'avg_glucose'=> $avgGlucose,
    'has_program'=> !empty($m['program_path']),
    'messages'   => $msgs,
    'events'     => $events,
    'health'     => $health,
]);
