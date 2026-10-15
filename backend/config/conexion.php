<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
$host = AppConfig::get('DB_HOST', '127.0.0.1');
$port = AppConfig::get('DB_PORT', '3306');
$database = AppConfig::get('DB_DATABASE', 'devioz_parking');
$username = AppConfig::get('DB_USERNAME', 'root');
$password = AppConfig::get('DB_PASSWORD');
if (AppConfig::production() && ($username === 'root' || $password === '' || strlen(AppConfig::get('APP_SECRET')) < 32)) {
    http_response_code(503);
    exit('Configuración de producción incompleta.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );
    $pdo->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    error_log('DEVIOZ DB connection: ' . $e->getCode());
    http_response_code(503);
    die(
        'Error de conexión con la base de datos.'
    );
}
