<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../backend/config/app.php';
$host = AppConfig::get('DB_HOST', '127.0.0.1');
$port = AppConfig::get('DB_PORT', '3306');
$database = AppConfig::get('DB_DATABASE', 'devioz_parking');
$user = AppConfig::get('DB_USERNAME', 'root');
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
try {
    $app = new PDO($dsn . ';dbname=' . $database, $user, AppConfig::get('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    echo "Cuenta de aplicación: conexión aceptada.\n";
} catch (PDOException $error) {
    echo 'Cuenta de aplicación: error ' . ($error->errorInfo[1] ?? $error->getCode()) . PHP_EOL;
}
if (AppConfig::production()) exit;
try {
    $admin = new PDO($dsn, getenv('DB_ADMIN_USERNAME') ?: 'root', getenv('DB_ADMIN_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $query = $admin->prepare('SELECT Host,plugin FROM mysql.user WHERE User=?');
    $query->execute([$user]);
    echo 'Cuenta runtime dedicada: ' . (preg_match('/^devioz_app_[a-f0-9]+$/D', $user) === 1 ? 'sí' : 'no') . PHP_EOL;
    echo 'Entradas de cuenta: ' . json_encode($query->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR) . PHP_EOL;
    $query = $admin->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $query->execute([$database]);
    echo 'Base configurada presente: ' . ((int)$query->fetchColumn() === 1 ? 'sí' : 'no') . PHP_EOL;
} catch (PDOException $error) {
    echo 'Diagnóstico administrativo: error ' . ($error->errorInfo[1] ?? $error->getCode()) . PHP_EOL;
    exit(1);
}
