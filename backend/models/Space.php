<?php

declare(strict_types=1);

final class Space
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM spaces WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forParking(int $parkingId): array
    {
        $stmt = db()->prepare('SELECT * FROM spaces WHERE parking_id = :parking_id ORDER BY floor, code');
        $stmt->execute(['parking_id' => $parkingId]);
        return $stmt->fetchAll();
    }

    public static function availableForParking(int $parkingId): array
    {
        $stmt = db()->prepare("SELECT * FROM spaces WHERE parking_id = :parking_id AND status='available' ORDER BY floor, code");
        $stmt->execute(['parking_id' => $parkingId]);
        return $stmt->fetchAll();
    }

   public static function allDetailed(): array
{
    $sql = "
        SELECT
            s.*,

            p.name AS parking_name,

            r.id AS reservation_id,
            r.status AS reservation_status,
            r.starts_on,
            r.ends_on,
            r.payment_status,
            r.amount,

            u.name AS driver_name,
            u.email AS driver_email,
            u.phone AS driver_phone,

            v.plate AS vehicle_plate,
            v.brand AS vehicle_brand,
            v.model AS vehicle_model,
            v.color AS vehicle_color

        FROM spaces s

        JOIN parkings p
            ON p.id = s.parking_id

        LEFT JOIN reservations r
            ON r.space_id = s.id
            AND r.status IN ('confirmed', 'active')

        LEFT JOIN users u
            ON u.id = r.user_id

        LEFT JOIN vehicles v
            ON v.id = r.vehicle_id

        ORDER BY s.code
    ";

    return db()->query($sql)->fetchAll();
}

    public static function stats(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(status='available') AS available,
                    SUM(status='reserved') AS reserved,
                    SUM(status='occupied') AS occupied,
                    SUM(status='maintenance') AS maintenance
             FROM spaces"
        )->fetch();
        return $row ?: [];
    }

    public static function setStatus(int $id, string $status): void
    {
        $allowed = ['available', 'reserved', 'occupied', 'maintenance'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Estado de plaza invalido.');
        }

        $stmt = db()->prepare('UPDATE spaces SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
