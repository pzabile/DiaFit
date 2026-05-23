<?php
/**
 * POST /api/meal_upload (multipart/form-data)
 * Upload a meal photo and save metadata.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = require_member();
$leadId = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

$csrf = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'csrf']);
    exit;
}

$caption  = trim($_POST['caption']   ?? '') ?: null;
$mealType = trim($_POST['meal_type'] ?? 'Meal');
$eatenAt  = null;
if (!empty($_POST['eaten_at'])) {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['eaten_at']);
    if ($dt) $eatenAt = $dt->format('Y-m-d H:i:s');
}
if (!$eatenAt) $eatenAt = date('Y-m-d H:i:s');

$filePath = null;

if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/heic'];
    $mime    = mime_content_type($_FILES['file']['tmp_name']);

    if (!in_array($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'invalid_file_type']);
        exit;
    }

    $maxSize = 10 * 1024 * 1024; // 10 MB
    if ($_FILES['file']['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'file_too_large']);
        exit;
    }

    $ext  = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $dir  = __DIR__ . "/../assets/clients/uploads/{$leadId}";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['file']['name']));
    $dest     = $dir . '/' . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'upload_failed']);
        exit;
    }

    $filePath = "assets/clients/uploads/{$leadId}/{$filename}";
}

$id = db_insert(
    'INSERT INTO meal_photos (lead_id, file_path, meal_type, caption, eaten_at, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())',
    [$leadId, $filePath ?? '', $mealType, $caption, $eatenAt]
);

echo json_encode(['ok' => true, 'id' => $id, 'file_path' => $filePath]);
