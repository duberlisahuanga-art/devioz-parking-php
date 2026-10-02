<?php

declare(strict_types=1);

final class Camera
{
    public static function all(): array
    {
        return db()->query('SELECT * FROM cameras ORDER BY id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cameras WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO cameras (parking_id, name, stream_url, coverage, status, created_at, updated_at)
             VALUES (:parking_id, :name, :stream_url, :coverage, :status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE cameras
             SET parking_id=:parking_id, name=:name, stream_url=:stream_url,
                 coverage=:coverage, status=:status, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute($data + ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM cameras WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
