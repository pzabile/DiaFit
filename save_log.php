<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    header('Location: dashboard'); exit;
}

$date = $_POST['log_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$bsBefore = ($_POST['bs_before'] ?? '') !== '' ? (int) $_POST['bs_before'] : null;
$bsAfter  = ($_POST['bs_after']  ?? '') !== '' ? (int) $_POST['bs_after']  : null;
$soreness = ($_POST['soreness']  ?? '') !== '' ? max(0, min(10, (int)$_POST['soreness'])) : null;

db_insert(
    'INSERT INTO daily_logs
      (lead_id, log_date, feeling, trained, train_where, workout, soreness,
       bs_before, bs_after, bs_trend, food_before, food_after, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $me['id'], $date,
        substr(trim($_POST['feeling'] ?? ''), 0, 20),
        substr(trim($_POST['trained'] ?? ''), 0, 20),
        substr(trim($_POST['train_where'] ?? ''), 0, 40),
        trim($_POST['workout'] ?? ''),
        $soreness,
        $bsBefore, $bsAfter,
        substr(trim($_POST['bs_trend'] ?? ''), 0, 20),
        substr(trim($_POST['food_before'] ?? ''), 0, 255),
        substr(trim($_POST['food_after']  ?? ''), 0, 255),
        trim($_POST['notes'] ?? ''),
    ]
);

$_SESSION['flash'] = 'Check-in saved.';
header('Location: /logs');
