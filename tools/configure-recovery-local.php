<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
$sender = $argv[1] ?? '';
if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Indica el correo remitente.');
if (AppConfig::production()) {
    throw new RuntimeException('Configurador sólo local.');
}
$username = getenv('DB_MIGRATION_USERNAME') ?: 'root';
$password = getenv('DB_MIGRATION_PASSWORD') ?: '';
$database = AppConfig::get('DB_DATABASE', 'devioz_parking');
if (preg_match('/^[a-zA-Z0-9_]+$/D', $database) !== 1) {
    throw new RuntimeException('Base inválida.');
}
$pdo = new PDO('mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
    . ';dbname=' . $database . ';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = '20261015_password_recovery.sql';
$file = __DIR__ . '/../database/migrations/' . $version;
$check = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');
$check->execute([$version]);
$previous = $check->fetchColumn();
$hash = hash_file('sha256', $file);
if ($previous !== false && $previous !== $hash) {
    throw new RuntimeException('Checksum de migración diferente.');
}
if ($previous === false) {
    $directory = AppConfig::storage() . '/recovery-migration';
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $backup = $directory . '/before-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
    $environment = array_merge(getenv(), ['MYSQL_PWD' => $password]);
    $process = proc_open(
        ['C:/xampp/mysql/bin/mysqldump.exe','--host=' . AppConfig::get('DB_HOST', '127.0.0.1'),
        '--port=' . AppConfig::get('DB_PORT', '3306'),'--user=' . $username,'--single-transaction','--result-file=' . $backup,$database],
        [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],
        $pipes,
        dirname(__DIR__),
        $environment
    );
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo respaldar.');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Falló respaldo; migración no aplicada.');
    }
    $restore = 'devioz_recovery_check_' . bin2hex(random_bytes(6));
    $pdo->exec('CREATE DATABASE `' . $restore . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo->exec('USE `' . $restore . '`');
        $pdo->exec(file_get_contents($backup));
        foreach (['users','reservations','payments','audit_logs'] as $table) {
            if (
                $pdo->query('SELECT COUNT(*) FROM `' . $restore . '`.`' . $table . '`')->fetchColumn()
                !== $pdo->query('SELECT COUNT(*) FROM `' . $database . '`.`' . $table . '`')->fetchColumn()
            ) {
                throw new RuntimeException('Restauración no coincide; migración detenida.');
            }
        }
    } finally {
        $pdo->exec('USE `' . $database . '`');
        $pdo->exec('DROP DATABASE `' . $restore . '`');
    }
    $pdo->exec(file_get_contents($file));
    $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)')->execute([$version,$hash]);
    echo "Respaldo completo restaurado y migración aditiva aplicada.\n";
}
$account = $pdo->quote(AppConfig::get('DB_USERNAME')) . "@'127.0.0.1'";
$pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `' . $database . '`.password_resets TO ' . $account);
$target = __DIR__ . '/../backend/config/local.php';
$values = require $target;
$values += ['PASSWORD_RESET_BASE_URL' => 'http://localhost/devioz-parking-php',
    'SMTP_HOST' => 'smtp.gmail.com','SMTP_PORT' => '587','SMTP_ENCRYPTION' => 'tls',
    'SMTP_USERNAME' => $sender,'SMTP_FROM' => $sender,'SMTP_PASSWORD' => ''];
$temporary = tempnam(dirname($target), 'recovery-');
if (
    $temporary === false || file_put_contents($temporary, "<?php\nreturn " . var_export($values, true) . ";\n", LOCK_EX) === false
    || !rename($temporary, $target)
) {
    throw new RuntimeException('No se pudo guardar configuración.');
}
echo "Gmail preparado. Falta contraseña de aplicación privada; no se enviaron correos.\n";
