<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$CONFIG = require __DIR__ . '/../config.php';

// Error reporting — turn on a visible error page when ?debug=1 is on the
// URL, so the owner can diagnose 500s without SSH access. Otherwise we log
// silently and let the framework render a friendly fallback.
error_reporting(E_ALL);
if (isset($_GET['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

// Global handler so a thrown DB exception (or anything else) renders a
// readable page instead of an opaque 500.
set_exception_handler(function (Throwable $ex) {
    error_log('Uncaught: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString());
    http_response_code(500);
    $debug = isset($_GET['debug']);
    $msg   = htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8');
    $trace = htmlspecialchars($ex->getTraceAsString(), ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><meta charset='utf-8'><title>DiaFitus error</title>";
    echo "<style>body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#f7f5f0;color:#0f1a14;margin:0;padding:48px;line-height:1.5}";
    echo ".box{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e3e0d6;border-radius:18px;padding:32px;box-shadow:0 8px 24px rgba(15,26,20,.06)}";
    echo "h1{margin:0 0 .5rem;font-size:1.5rem}p{color:#4a5651}pre{background:#f7f5f0;padding:.85rem;border-radius:10px;font-size:.85rem;white-space:pre-wrap;word-break:break-word}</style>";
    echo "<div class='box'><h1>Something went wrong</h1>";
    echo "<p>We hit an unexpected error. Add <code>?debug=1</code> to the URL to see details if you're the site owner.</p>";
    if ($debug) {
        echo "<pre><strong>" . $msg . "</strong>\n\n" . $trace . "</pre>";
    }
    echo "</div>";
});

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
