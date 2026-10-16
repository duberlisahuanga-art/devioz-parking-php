<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../backend/services/AuditArchive.php';
require_once __DIR__ . '/../backend/models/AuditLog.php';
$cutoff = $argv[1] ?? '';
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $cutoff, new DateTimeZone('America/Lima'));
if (!$date || $date->format('Y-m-d') !== $cutoff || $cutoff > date('Y-m-d')) {
    throw new RuntimeException('Indica una fecha de corte válida, no futura.');
}
$connection = new PDO('mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
    . ';dbname=' . AppConfig::get('DB_DATABASE', 'devioz_parking') . ';charset=utf8mb4',
    getenv('DB_MAINTENANCE_USERNAME') ?: (AppConfig::production() ? '' : 'root'),
    getenv('DB_MAINTENANCE_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$connection->exec("SET time_zone = '-05:00'");
$maxId = (int) $connection->query('SELECT COALESCE(MAX(id),0) FROM audit_logs')->fetchColumn();
$count = $connection->prepare('SELECT COUNT(*) FROM audit_logs WHERE created_at < :cutoff AND id <= :max_id');
$count->execute(['cutoff'=>$cutoff . ' 00:00:00', 'max_id'=>$maxId]);
echo 'Corte Lima: ' . $cutoff . ' 00:00:00; candidatos: ' . $count->fetchColumn() . PHP_EOL;
if (!in_array('--apply', $argv, true)) {
    exit("Vista previa; no se eliminaron eventos.\n");
}
$directory = AppConfig::get('AUDIT_BACKUP_DIR', AppConfig::storage() . '/audit-backups');
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Directorio de recuperación no disponible.');
}
$path = $directory . '/audit-before-prune-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.jsonl';
$key = AppConfig::get('AUDIT_BACKUP_KEY');
$backedUp = AuditArchive::backup($connection, $path, $key);
if (AuditArchive::verify($connection, $path, $key) !== $backedUp) {
    throw new RuntimeException('La restauración del respaldo no coincide.');
}
$mirror = AppConfig::get('AUDIT_BACKUP_MIRROR_DIR');
if ($mirror !== '') {
    AuditArchive::mirror($path, $mirror);
    AuditArchive::mirror($path . '.manifest.json', $mirror);
}
$connection->beginTransaction();
try {
    $lock = $connection->prepare('SELECT id FROM audit_logs WHERE created_at < :cutoff AND id <= :max_id FOR UPDATE');
    $parameters = ['cutoff'=>$cutoff . ' 00:00:00', 'max_id'=>$maxId];
    $lock->execute($parameters);
    $expected = count($lock->fetchAll());
    $delete = $connection->prepare('DELETE FROM audit_logs WHERE created_at < :cutoff AND id <= :max_id');
    $delete->execute($parameters);
    $removed = $delete->rowCount();
    if ($removed !== $expected) {
        throw new RuntimeException('Cantidad eliminada inesperada.');
    }
    if ($removed > 0) {
        AuditLog::create(['action'=>'Limpieza manual', 'description'=>'Limpieza de eventos anteriores a ' . $cutoff . ' (Lima).',
            'metadata'=>json_encode(['module'=>'Auditoria', 'actor'=>null, 'cutoff'=>$cutoff, 'removed'=>$removed,
                'backup'=>basename($path)], JSON_THROW_ON_ERROR)], $connection);
    }
    $connection->commit();
} catch (Throwable $error) {
    if ($connection->inTransaction()) $connection->rollBack();
    throw $error;
}
$count->execute($parameters);
$remaining = (int) $connection->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
echo json_encode(['deleted'=>$removed, 'remaining'=>$remaining, 'old_remaining'=>(int)$count->fetchColumn(),
    'backup'=>basename($path), 'backed_up'=>$backedUp], JSON_THROW_ON_ERROR) . PHP_EOL;
