<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/services/AuditPermissions.php';
if (AppConfig::production()) throw new RuntimeException('Herramienta sólo para XAMPP local.');
$host = AppConfig::get('DB_HOST', '127.0.0.1');
$port = AppConfig::get('DB_PORT', '3306');
$database = AppConfig::get('DB_DATABASE', 'devioz_parking');
$user = AppConfig::get('DB_USERNAME');
$password = AppConfig::get('DB_PASSWORD');
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || preg_match('/^devioz_app_[a-f0-9]+$/D', $user) !== 1
    || strlen($password) < 32 || preg_match('/^[a-zA-Z0-9_]+$/D', $database) !== 1) {
    throw new RuntimeException('La configuración no identifica una cuenta local dedicada válida.');
}
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
$admin = new PDO($dsn, getenv('DB_ADMIN_USERNAME') ?: 'root', getenv('DB_ADMIN_PASSWORD') ?: '', $options);
$query = $admin->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?');
$query->execute([$user]);
if ((int)$query->fetchColumn() !== 0) {
    throw new RuntimeException('La cuenta existe; no se modificaron sus credenciales ni permisos. Ejecuta el diagnóstico.');
}
if (!in_array('--apply', $argv, true)) {
    exit("Cuenta dedicada ausente. Usar --apply para recrearla con los valores privados existentes.\n");
}
$account = $admin->quote($user) . "@'127.0.0.1'";
$admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($password));
try {
    AuditPermissions::grant($admin, $database, $account);
    $connection = new PDO($dsn . ';dbname=' . $database, $user, $password, $options);
    AuditPermissions::verify($connection);
    $connection->query('SELECT user_id FROM password_resets LIMIT 1');
} catch (Throwable $error) {
    $admin->exec('DROP USER ' . $account);
    throw $error;
}
echo "OK: cuenta local recreada y conexión verificada. Auditoría conserva SELECT/INSERT; UPDATE/DELETE rechazados.\n";
