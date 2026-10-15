<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
$user = getenv('DB_MIGRATION_USERNAME') ?: (AppConfig::production() ? '' : 'root');
if ($user === '') {
    throw new RuntimeException('Falta DB_MIGRATION_USERNAME.');
}
$pdo = new PDO('mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
    . ';dbname=' . AppConfig::get('DB_DATABASE', 'devioz_parking') . ';charset=utf8mb4', $user,
    getenv('DB_MIGRATION_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(150) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$lock = $pdo->query("SELECT GET_LOCK('devioz_migrations',10)")->fetchColumn();
if ((int) $lock !== 1) {
    throw new RuntimeException('Otra migración está en ejecución.');
}
try {
    if (in_array('--adopt-existing', $argv, true)) {
        foreach (['users','spaces','reservations','payments','cameras','expenses','notifications','user_preferences'] as $table) {
            $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        }
        $pdo->query('SELECT channel_number FROM cameras LIMIT 1');
        foreach (['sql/schema.sql','finance_expenses.sql','cameras_hmi.sql','account_settings_notifications.sql'] as $path) {
            $hash = hash_file('sha256', __DIR__ . '/../database/' . $path);
            $insert = $pdo->prepare('INSERT IGNORE INTO schema_migrations(version,checksum) VALUES (?,?)');
            $insert->execute(['baseline:' . $path, $hash]);
        }
        echo "Esquema existente identificado; SQL histórico no reejecutado.\n";
    }
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') ?: [] as $file) {
        $version = basename($file);
        $hash = hash_file('sha256', $file);
        $query = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?');
        $query->execute([$version]);
        $previous = $query->fetchColumn();
        if ($previous !== false) {
            if (!hash_equals($previous, $hash)) {
                throw new RuntimeException('Cambió una migración ya aplicada: ' . $version);
            }
            continue;
        }
        $duplicates = $pdo->query("SELECT reservation_id FROM payments WHERE status IN ('pending','paid') GROUP BY reservation_id HAVING COUNT(*)>1 LIMIT 1")->fetch();
        if ($duplicates) {
            throw new RuntimeException('Existen pagos activos duplicados. Resolver antes de migrar.');
        }
        $pdo->exec(file_get_contents($file));
        $insert = $pdo->prepare('INSERT INTO schema_migrations(version,checksum) VALUES (?,?)');
        $insert->execute([$version,$hash]);
        echo 'Aplicada: ' . $version . PHP_EOL;
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('devioz_migrations')");
}
