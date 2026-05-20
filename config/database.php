<?php
declare(strict_types=1);

$DB_HOST = getenv('QUIZER_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('QUIZER_DB_NAME') ?: 'quizer';
$DB_USER = getenv('QUIZER_DB_USER') ?: 'root';
$DB_PASS = getenv('QUIZER_DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Update config/database.php or QUIZER_DB_* environment variables.');
}

