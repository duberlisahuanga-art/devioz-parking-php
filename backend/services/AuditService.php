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
        array $metadata = []
    ): int {
        $user = current_user();
        $metadata = [
            'module' => $module,
            'reference' => $reference,
            'user_agent' => request_user_agent(),
            ...$metadata,
        ];

        return AuditLog::create([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request_ip(),
        ]);
    }
}
