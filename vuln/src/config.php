<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

session_name('PULSE_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => false,
    'samesite' => 'Lax',
]);
session_start();

const APP_NAME = 'Pulse';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'db';
    $name = getenv('DB_NAME') ?: 'devfest_vuln';
    $user = getenv('DB_USER') ?: 'devfest';
    $pass = getenv('DB_PASS') ?: 'devfest';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}
