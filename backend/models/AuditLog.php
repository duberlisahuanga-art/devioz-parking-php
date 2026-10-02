<?php

declare(strict_types=1);

final class AuditLog
{
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs
             (user_id, action, entity_type, entity_id, description, metadata, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :metadata, :ip_address, NOW())'
        );
        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'description' => $data['description'],
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function all(string $action = '', string $search = ''): array
    {
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1";
        $params = [];

        if ($action !== '') {
            $sql .= ' AND a.action = :action';
            $params['action'] = $action;
        }
        if ($search !== '') {
            $sql .= ' AND (a.description LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
