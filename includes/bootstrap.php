<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$CONFIG = require __DIR__ . '/../config.php';

function cfg($path, $default = null) {
    global $CONFIG;
    $parts = explode('.', $path);
    $cur = $CONFIG;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }
    return $cur;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function answers() {
    return $_SESSION['answers'] ?? [];
}

function user_session() {
    return $_SESSION['user'] ?? [];
}
