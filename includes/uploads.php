<?php
require_once __DIR__ . '/bootstrap.php';

function uploads_dir() {
    $dir = cfg('uploads.dir');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n");
    }
    return $dir;
}

function save_uploaded_image(array $file, $leadId, $subdir = 'meals') {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No file uploaded.');
    }
    $maxBytes = ((int) cfg('uploads.max_mb', 8)) * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File too large.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = cfg('uploads.mime', []);
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Unsupported image type.');
    }
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
    $ext = $extMap[$mime] ?? 'bin';

    $folder = uploads_dir() . '/' . $subdir . '/' . (int) $leadId;
    if (!is_dir($folder)) @mkdir($folder, 0755, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $folder . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save upload.');
    }
    @chmod($dest, 0644);

    return cfg('uploads.public') . '/' . $subdir . '/' . (int) $leadId . '/' . $name;
}

function save_admin_program_pdf(array $file, $leadId) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No file uploaded.');
    }
    if ($file['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('Program PDF must be under 20 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['application/pdf'], true)) {
        throw new RuntimeException('Program must be a PDF.');
    }
    $folder = uploads_dir() . '/programs/' . (int) $leadId;
    if (!is_dir($folder)) @mkdir($folder, 0755, true);
    $name = 'program_' . date('Ymd_His') . '.pdf';
    $dest = $folder . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save program PDF.');
    }
    @chmod($dest, 0644);
    return cfg('uploads.public') . '/programs/' . (int) $leadId . '/' . $name;
}
