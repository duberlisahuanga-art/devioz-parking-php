<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/services/AuditPermissions.php';
if (AppConfig::production()) {
    throw new RuntimeException('Usa el procedimiento de despliegue de producción.');
}
$host = AppConfig::get('DB_HOST', '127.0.0.1');
$port = AppConfig::get('DB_PORT', '3306');
$database = AppConfig::get('DB_DATABASE', 'devioz_parking');
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$current = new PDO($dsn, AppConfig::get('DB_USERNAME', 'root'), AppConfig::get('DB_PASSWORD'), $options);
try {
    AuditPermissions::verify($current);
    exit("OK: cuenta actual permite SELECT/INSERT y rechaza UPDATE/DELETE sobre auditoría.\n");
} catch (RuntimeException $error) {
    if (!in_array('--apply', $argv, true)) {
        exit("Cuenta pendiente de restricción. Ejecutar con --apply para crear y verificar una cuenta nueva.\n");
    }
}
if (getenv('DB_USERNAME') !== false || getenv('DB_PASSWORD') !== false) {
    throw new RuntimeException('Las variables de entorno prevalecen sobre local.php. Ajusta el despliegue explícitamente.');
}
$admin = new PDO($dsn, getenv('DB_ADMIN_USERNAME') ?: 'root', getenv('DB_ADMIN_PASSWORD') ?: '', $options);
$name = 'devioz_app_' . bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(32));
$account = $admin->quote($name) . "@'127.0.0.1'";
$target = __DIR__ . '/../backend/config/local.php';
$values = is_file($target) ? require $target : [];
$directory = AppConfig::storage() . '/audit-recovery';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo crear recuperación.');
}
$backup = $directory . '/local-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.php';
if (file_put_contents($backup, is_file($target) ? file_get_contents($target) : "<?php\nreturn [];\n", LOCK_EX) === false) {
    throw new RuntimeException('No se pudo respaldar configuración.');
}
$admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($password));
$temporary = null;
try {
    AuditPermissions::grant($admin, $database, $account);
    $candidate = new PDO($dsn, $name, $password, $options);
    AuditPermissions::verify($candidate);
    $values = [...$values, 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database,
        'DB_USERNAME' => $name, 'DB_PASSWORD' => $password,
        'AUDIT_BACKUP_KEY' => $values['AUDIT_BACKUP_KEY'] ?? bin2hex(random_bytes(32))];
    $temporary = tempnam(dirname($target), 'audit-');
    if (
        $temporary === false || file_put_contents($temporary, "<?php\nreturn " . var_export($values, true) . ";\n", LOCK_EX) === false
        || !rename($temporary, $target)
    ) {
        throw new RuntimeException('No se pudo activar configuración.');
    }
} catch (Throwable $error) {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
    $admin->exec('DROP USER ' . $account);
    throw $error;
}
echo "OK: cuenta nueva verificada y activada. Cuenta anterior conservada para recuperación.\n";
echo 'Respaldo privado: ' . basename($backup) . PHP_EOL;
