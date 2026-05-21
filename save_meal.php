<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/uploads.php';

$me = require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    header('Location: dashboard'); exit;
}

try {
    $publicPath = save_uploaded_image($_FILES['photo'] ?? [], $me['id'], 'meals');
    db_insert(
        'INSERT INTO meal_photos (lead_id, file_path, meal_type, caption, eaten_at)
         VALUES (?, ?, ?, ?, NOW())',
        [
            $me['id'],
            $publicPath,
            substr(trim($_POST['meal_type'] ?? ''), 0, 40),
            substr(trim($_POST['caption']   ?? ''), 0, 500),
        ]
    );
    $_SESSION['flash'] = 'Meal photo uploaded.';
    require_once __DIR__ . '/includes/telegram.php';
    try {
        $name = $me['first_name'] ?: $me['email'];
        tg_send_message(
            '<b>🍽️ Meal photo uploaded</b>' . "\n" .
            '<b>From:</b> ' . htmlspecialchars($name) . " (" . htmlspecialchars($me['email']) . ")\n" .
            '<b>Meal:</b> ' . htmlspecialchars($_POST['meal_type'] ?? '') . "\n" .
            '<b>Caption:</b> ' . htmlspecialchars($_POST['caption'] ?? '') . "\n\n" .
            'Open: ' . rtrim(cfg('site_url'), '/') . '/admin/member?id=' . (int) $me['id']
        );
    } catch (Throwable $ex) { error_log('meal tg: ' . $ex->getMessage()); }
} catch (Throwable $ex) {
    $_SESSION['flash'] = 'Could not upload photo: ' . $ex->getMessage();
}

header('Location: /meals');
