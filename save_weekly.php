<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$me = require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    header('Location: dashboard'); exit;
}

$week = max(1, (int)($_POST['week_number'] ?? 1));
$avgGlucose   = ($_POST['avg_glucose']   ?? '') !== '' ? (int) $_POST['avg_glucose']   : null;
$weightKg     = ($_POST['weight_kg']     ?? '') !== '' ? (float) $_POST['weight_kg']   : null;
$energyRating = ($_POST['energy_rating'] ?? '') !== '' ? max(0, min(10, (int)$_POST['energy_rating'])) : null;
$wins      = trim($_POST['wins'] ?? '');
$struggles = trim($_POST['struggles'] ?? '');
$content   = trim($_POST['content'] ?? '');

db_insert(
    'INSERT INTO weekly_notes (lead_id, week_number, content, wins, struggles, avg_glucose, weight_kg, energy_rating)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [$me['id'], $week, $content, $wins, $struggles, $avgGlucose, $weightKg, $energyRating]
);

$_SESSION['flash'] = "Week {$week} check-in saved. Your coach will review it.";
header('Location: dashboard#week');
