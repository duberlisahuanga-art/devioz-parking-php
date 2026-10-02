<?php

declare(strict_types=1);

final class Vehicle
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE user_id = :user_id ORDER BY created_at DESC, id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findOwned(int $id, int $userId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function plateExists(string $plate): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM vehicles WHERE plate = :plate LIMIT 1');
        $stmt->execute(['plate' => $plate]);
        return (bool) $stmt->fetchColumn();
    }

    public static function create(int $userId, array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO vehicles (user_id, plate, brand, model, color, type, created_at, updated_at)
             VALUES (:user_id, :plate, :brand, :model, :color, :type, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'plate' => $data['plate'],
            'brand' => $data['brand'] ?: null,
            'model' => $data['model'] ?: null,
            'color' => $data['color'] ?: null,
            'type' => $data['type'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function hasOpenReservation(int $vehicleId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM reservations WHERE vehicle_id = :vehicle_id AND status IN ('confirmed','active') LIMIT 1");
        $stmt->execute(['vehicle_id' => $vehicleId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
