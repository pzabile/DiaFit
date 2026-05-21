<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    header('Location: /checkin'); exit;
}

$week = max(1, (int)($_POST['week_number'] ?? 1));
$avgGlucose   = ($_POST['avg_glucose']   ?? '') !== '' ? (int) $_POST['avg_glucose']   : null;

// Accept either weight_lbs (new) or weight_kg (legacy). Store as kg.
$weightKg = null;
if (($_POST['weight_lbs'] ?? '') !== '') {
    $weightKg = round(((float) $_POST['weight_lbs']) / 2.20462, 1);
} elseif (($_POST['weight_kg'] ?? '') !== '') {
    $weightKg = (float) $_POST['weight_kg'];
}

$energyRating = ($_POST['energy_rating'] ?? '') !== '' ? max(0, min(10, (int)$_POST['energy_rating'])) : null;
$wins      = trim($_POST['wins'] ?? '');
$struggles = trim($_POST['struggles'] ?? '');
$content   = trim($_POST['content'] ?? '');

db_insert(
    'INSERT INTO weekly_notes (lead_id, week_number, content, wins, struggles, avg_glucose, weight_kg, energy_rating)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [$me['id'], $week, $content, $wins, $struggles, $avgGlucose, $weightKg, $energyRating]
);

// Ping the owner so they can review and reply.
require_once __DIR__ . '/includes/telegram.php';
try {
    $name = $me['first_name'] ?: $me['email'];
    tg_send_message(
        '<b>🗓️ Weekly check-in</b>' . "\n" .
        '<b>From:</b> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($me['email']) . ")\n" .
        '<b>Week:</b> ' . (int) $week . "\n" .
        ($avgGlucose   !== null ? '<b>Avg glucose:</b> ' . (int) $avgGlucose . " mg/dL\n" : '') .
        ($weightKg     !== null ? '<b>Weight:</b> '  . $weightKg . " kg\n" : '') .
        ($energyRating !== null ? '<b>Energy:</b> '  . (int) $energyRating . "/10\n" : '') .
        ($wins      !== '' ? "\n<b>Wins.</b> "      . htmlspecialchars(mb_strimwidth($wins, 0, 300, '…')) : '') .
        ($struggles !== '' ? "\n<b>Struggles.</b> " . htmlspecialchars(mb_strimwidth($struggles, 0, 300, '…')) : '') .
        "\n\nOpen: " . rtrim(cfg('site_url'), '/') . '/admin/member?id=' . (int) $me['id']
    );
} catch (Throwable $ex) { error_log('weekly tg: ' . $ex->getMessage()); }

$_SESSION['flash'] = "Week {$week} check-in saved. Your coach will review it.";
header('Location: /checkin');
