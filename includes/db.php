<?php
require_once __DIR__ . '/bootstrap.php';

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = cfg('db.host');
    $name = cfg('db.name');
    $user = cfg('db.user');
    $pass = cfg('db.pass');
    $charset = cfg('db.charset', 'utf8mb4');

    if (strpos((string)$name, 'REPLACE_') === 0) {
        throw new RuntimeException('Database is not configured. Edit config.php.');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Auto-add plan_days column if missing (migration 003 may not have been run manually)
    try {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `plan_days` SMALLINT UNSIGNED NOT NULL DEFAULT 84 AFTER `started_at`");
    } catch (PDOException $ignored) {}

    // Email send tracking
    try {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `email_sent_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `plan_days`");
    } catch (PDOException $ignored) {}

    // CAN-SPAM opt-out
    try {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `opted_out` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_sent_count`");
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `opted_out_at` DATETIME NULL DEFAULT NULL AFTER `opted_out`");
    } catch (PDOException $ignored) {}

    // PayPal order tracking (stores pending order ID so capture works without session)
    try {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `paypal_order_id` VARCHAR(64) NULL DEFAULT NULL AFTER `opted_out_at`");
    } catch (PDOException $ignored) {}

    return $pdo;
}

function db_get($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetch();
}
function db_all($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll();
}
function db_exec($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->rowCount();
}
function db_insert($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return (int) db()->lastInsertId();
}
