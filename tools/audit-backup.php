<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/services/AuditArchive.php';
$key = AppConfig::get('AUDIT_BACKUP_KEY');
if (strlen($key) < 32) {
    throw new RuntimeException('Falta AUDIT_BACKUP_KEY de al menos 32 bytes. Conservarla separada del respaldo.');
}
$connection = new PDO(
    'mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
    . ';dbname=' . AppConfig::get('DB_DATABASE', 'devioz_parking') . ';charset=utf8mb4',
    getenv('DB_MAINTENANCE_USERNAME') ?: AppConfig::get('DB_USERNAME', 'root'),
    getenv('DB_MAINTENANCE_USERNAME') ? (getenv('DB_MAINTENANCE_PASSWORD') ?: '') : AppConfig::get('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$connection->exec("SET time_zone = '-05:00'");
if (($argv[1] ?? '') === 'verify') {
    // Verification uses temporary-table privileges from a maintenance account.
    echo 'OK: ' . AuditArchive::verify($connection, $argv[2] ?? '', $key) . " eventos restaurados y comparados.\n";
    exit;
}
$directory = AppConfig::get('AUDIT_BACKUP_DIR', AppConfig::storage() . '/audit-backups');
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo crear directorio privado de respaldo.');
}
$path = $directory . '/audit-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.jsonl';
$count = AuditArchive::backup($connection, $path, $key);
$mirror = AppConfig::get('AUDIT_BACKUP_MIRROR_DIR');
if ($mirror !== '') {
    AuditArchive::mirror($path, $mirror);
    AuditArchive::mirror($path . '.manifest.json', $mirror);
}
// Preserve the file-based outage channel alongside the database snapshot.
foreach (glob(AppConfig::storage() . '/audit-fallback/security-*.jsonl') ?: [] as $fallback) {
    $copy = $directory . '/' . basename($path, '.jsonl') . '-' . basename($fallback);
    if (!copy($fallback, $copy) || file_put_contents($copy . '.hmac', hash_hmac_file('sha256', $copy, $key)) === false) {
        throw new RuntimeException('No se pudo respaldar el canal de contingencia.');
    }
    if ($mirror !== '') {
        AuditArchive::mirror($copy, $mirror);
        AuditArchive::mirror($copy . '.hmac', $mirror);
    }
}
if (file_put_contents($directory . '/last-success.json', json_encode([
    'created_at'=>date(DATE_ATOM), 'rows'=>$count, 'file'=>basename($path), 'mirror_verified'=>$mirror !== '',
], JSON_THROW_ON_ERROR), LOCK_EX) === false) {
    throw new RuntimeException('No se pudo registrar estado del respaldo.');
}
echo 'OK: ' . $count . ' eventos respaldados en ' . $path . PHP_EOL;
echo $mirror !== '' ? "OK: segunda copia y manifiesto verificados.\n" : "Sin destino de segunda copia configurado.\n";
echo "Conservación: sin borrado automático.\n";
