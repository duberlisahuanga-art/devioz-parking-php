<?php

declare(strict_types=1);

final class AuditService
{
    public static function log(
        string $module,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $reference = null,
        array $metadata = [],
        ?array $actor = null
    ): int {
        $user = $actor ?? current_user();
        $managedDriver = ($user['role'] ?? null) === 'admin' ? DriverContext::metadata() : null;
        if ($managedDriver !== null && empty($metadata['automatic'])) {
            $metadata['managed_driver'] = $managedDriver;
        }
        $metadata = [
            'module' => $module,
            'reference' => $reference,
            'user_agent' => request_user_agent(),
            ...$metadata,
            'actor' => $user === null ? null : [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ];

        return AuditLog::create([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'ip_address' => request_ip(),
        ]);
    }

    public static function denied(string $reason): void
    {
        // A separate connection keeps denials after rollback of a business transaction.
        $event = [
            'user_id' => null,
            'action' => 'Acceso rechazado',
            'description' => 'Acceso rechazado.',
            'ip_address' => request_ip(),
            'metadata' => null,
        ];
        try {
            $user = current_user();
            $event['metadata'] = json_encode([
                'module' => 'Seguridad', 'reason' => $reason,
                'actor' => $user === null ? null : array_intersect_key($user, array_flip(['id', 'name', 'email', 'role'])),
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            $connection = new PDO(
                'mysql:host=' . AppConfig::get('DB_HOST', '127.0.0.1') . ';port=' . AppConfig::get('DB_PORT', '3306')
                . ';dbname=' . AppConfig::get('DB_DATABASE', 'devioz_parking') . ';charset=utf8mb4',
                AppConfig::get('DB_USERNAME', 'root'),
                AppConfig::get('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 2]
            );
            $connection->exec("SET time_zone = '-05:00'");
            $connection->exec('SET SESSION innodb_lock_wait_timeout=2');
            // No FK to a user locked by the transaction being rejected; actor ID is in metadata.
            AuditLog::create($event, $connection);
        } catch (Throwable $error) {
            $directory = AppConfig::storage() . '/audit-fallback';
            try {
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new RuntimeException('Audit storage unavailable');
                }
                $line = json_encode(
                    ['created_at' => date(DATE_ATOM), 'reason' => $reason, 'event' => $event],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                ) . PHP_EOL;
                if (file_put_contents($directory . '/security-' . date('Y-m-d') . '.jsonl', $line, FILE_APPEND | LOCK_EX) === false) {
                    throw new RuntimeException('Audit fallback unavailable');
                }
                error_log('DEVIOZ security audit fallback used: ' . get_class($error));
            } catch (Throwable) {
                error_log('DEVIOZ SECURITY AUDIT STORAGE FAILURE: ' . $reason);
            }
        }
    }
}
