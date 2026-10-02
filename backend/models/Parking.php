<?php

declare(strict_types=1);

final class Parking
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM parkings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function devioz(): ?array
    {
        $stmt = db()->query("SELECT * FROM parkings WHERE slug = 'estacionamiento-devioz' LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public static function allWithStats(): array
    {
        $sql = "SELECT p.*,
                       COUNT(s.id) AS spaces_count,
                       SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) AS occupied_spaces_count,
                       SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) AS available_spaces_count,
                       SUM(CASE WHEN s.status='reserved' THEN 1 ELSE 0 END) AS reserved_spaces_count
                FROM parkings p
                LEFT JOIN spaces s ON s.parking_id = p.id
                GROUP BY p.id
                ORDER BY p.name";
        return db()->query($sql)->fetchAll();
    }
}
