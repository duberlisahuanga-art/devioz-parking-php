<?php

declare(strict_types=1);

final class AuditLog
{
    public static function create(array $data, ?PDO $connection = null): int
    {
        $connection ??= db();
        $stmt = $connection->prepare(
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
        return (int) $connection->lastInsertId();
    }

    public static function filters(array $input): array
    {
        $filters = [];
        foreach (['action', 'search', 'module', 'entity_type', 'user_id', 'entity_id', 'from', 'to'] as $key) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || mb_strlen($value) > 255) {
                throw new ValidationException('Filtro de auditoría inválido.');
            }
            $filters[$key] = trim($value);
        }
        foreach (['user_id', 'entity_id'] as $key) {
            if ($filters[$key] !== '' && (filter_var($filters[$key], FILTER_VALIDATE_INT) === false || (int) $filters[$key] < 1)) {
                throw new ValidationException('Los identificadores deben ser enteros positivos.');
            }
        }
        foreach (['from', 'to'] as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key], new DateTimeZone('America/Lima'));
            if ($filters[$key] !== '' && (!$date || $date->format('Y-m-d') !== $filters[$key] || $filters[$key] < '1000-01-01' || $filters[$key] > '9999-12-30')) {
                throw new ValidationException('Fecha de auditoría inválida.');
            }
        }
        if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
            throw new ValidationException('La fecha inicial debe ser anterior o igual a la final.');
        }
        return $filters;
    }

    public static function all(string $action = '', string $search = '', array $filters = []): array
    {
        $filters = self::filters(['action' => $action, 'search' => $search, ...$filters]);
        $action = $filters['action'];
        $search = $filters['search'];
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1";
        $params = [];
        foreach (['entity_type', 'entity_id'] as $key) {
            if ($filters[$key] !== '') {
                $sql .= ' AND a.' . $key . ' = :' . $key;
                $params[$key] = $filters[$key];
            }
        }
        foreach (['module' => '$.module', 'user_id' => '$.actor.id'] as $key => $path) {
            if ($filters[$key] !== '') {
                $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(a.metadata), a.metadata, '{}'), '$path')) = :$key";
                $params[$key] = $filters[$key];
            }
        }
        if ($filters['from'] !== '') {
            $sql .= ' AND a.created_at >= :from_date';
            $params['from_date'] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $sql .= ' AND a.created_at < :until_date';
            $params['until_date'] = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        }

        if ($action !== '') {
            $sql .= ' AND a.action = :action';
            $params['action'] = $action;
        }
        if ($search !== '') {
            $sql .= " AND (a.description LIKE :search1
                OR JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(a.metadata), a.metadata, '{}'), '$.actor.name')) LIKE :search2
                OR JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(a.metadata), a.metadata, '{}'), '$.actor.email')) LIKE :search3)";
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT 51 OFFSET ' . page_offset();
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        foreach ($logs as &$log) {
            $metadata = json_decode((string) $log['metadata'], true);
            if (is_array($metadata) && array_key_exists('actor', $metadata)) {
                $log['user_name'] = $metadata['actor']['name'] ?? 'Sistema / no autenticado';
                $log['user_email'] = $metadata['actor']['email'] ?? '';
            } else {
                $log['user_name'] = 'Identidad histórica no disponible';
                $log['user_email'] = '';
            }
        }
        unset($log);
        return $logs;
    }
}
