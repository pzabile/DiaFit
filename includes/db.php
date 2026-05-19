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
