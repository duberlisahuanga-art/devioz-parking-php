<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/services/AuditPermissions.php';
if (AppConfig::production()) {
    throw new RuntimeException('Este instalador sólo configura desarrollo local.');
}
$target = __DIR__ . '/../backend/config/local.php';
if (is_file($target)) {
    exit("La configuración local ya existe; no se reemplazó.\n");
}
$pdo = new PDO('mysql:host=127.0.0.1;dbname=devioz_parking;charset=utf8mb4',
    getenv('DB_ADMIN_USERNAME') ?: 'root', getenv('DB_ADMIN_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'devioz_app_' . bin2hex(random_bytes(3));
$password = bin2hex(random_bytes(32));
$account = $pdo->quote($name) . "@'127.0.0.1'";
$pdo->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $pdo->quote($password));
AuditPermissions::grant($pdo, 'devioz_parking', $account);
$values = ['APP_ENV' => 'development', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
    'DB_DATABASE' => 'devioz_parking', 'DB_USERNAME' => $name, 'DB_PASSWORD' => $password,
    'APP_SECRET' => bin2hex(random_bytes(32)), 'APP_BASE_URL' => '/devioz-parking-php'];
if (file_put_contents($target, "<?php\nreturn " . var_export($values, true) . ";\n", LOCK_EX) === false) {
    $pdo->exec('DROP USER ' . $account);
    throw new RuntimeException('No se pudo guardar la configuración.');
}
echo "Usuario de aplicación limitado y configuración privada creados. No se imprimen credenciales.\n";
