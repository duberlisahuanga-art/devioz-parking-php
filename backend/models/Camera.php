<?php

declare(strict_types=1);

final class Camera
{
    private const FIELDS = ['parking_id', 'name', 'stream_url', 'coverage', 'status',
        'channel_number', 'location', 'rtsp_path', 'stream_protocol', 'preferred_quality',
        'brightness', 'contrast', 'saturation', 'osd_datetime', 'osd_camera_name'];

    public static function all(): array
    {
        return db()->query('SELECT * FROM cameras ORDER BY parking_id, channel_number, id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cameras WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByChannel(int $parkingId, int $channel): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cameras WHERE parking_id = :parking AND channel_number = :channel LIMIT 1');
        $stmt->execute(['parking' => $parkingId, 'channel' => $channel]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $fields = implode(', ', self::FIELDS);
        $values = ':' . implode(', :', self::FIELDS);
        $stmt = db()->prepare("INSERT INTO cameras ($fields, created_at, updated_at) VALUES ($values, NOW(), NOW())");
        $stmt->execute(array_intersect_key($data, array_flip(self::FIELDS)));
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $assignments = implode(', ', array_map(static fn(string $field): string => "$field=:$field", self::FIELDS));
        $stmt = db()->prepare("UPDATE cameras SET $assignments, updated_at=NOW() WHERE id=:id");
        $stmt->execute(array_intersect_key($data, array_flip(self::FIELDS)) + ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM cameras WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
