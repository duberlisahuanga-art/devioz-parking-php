<?php

declare(strict_types=1);

final class Reservation
{
    private static function baseSelect(): string
    {
        return "SELECT r.*,
                       u.name AS user_name, u.email AS user_email,
                       p.name AS parking_name, p.address AS parking_address,
                       s.code AS space_code, s.type AS space_type,
                       v.plate AS vehicle_plate, v.brand AS vehicle_brand,
                       v.model AS vehicle_model, v.color AS vehicle_color
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                JOIN parkings p ON p.id = r.parking_id
                JOIN spaces s ON s.id = r.space_id
                JOIN vehicles v ON v.id = r.vehicle_id";
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE r.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE r.user_id = :user_id ORDER BY r.created_at DESC, r.id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return db()->query(self::baseSelect() . ' ORDER BY r.created_at DESC, r.id DESC')->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO reservations
             (user_id, parking_id, space_id, vehicle_id, starts_on, ends_on, check_in_at, check_out_at,
              status, amount, currency, payment_status, cancellation_status, created_at, updated_at)
             VALUES
             (:user_id, :parking_id, :space_id, :vehicle_id, :starts_on, :ends_on, NULL, NULL,
              :status, :amount, :currency, :payment_status, :cancellation_status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = [
            'check_in_at', 'check_out_at', 'status', 'payment_status',
            'cancellation_status', 'cancellation_reason', 'cancellation_requested_at',
            'cancellation_reviewed_at', 'cancellation_reviewed_by', 'cancellation_review_note',
        ];

        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $set[] = "$key = :$key";
            $params[$key] = $value;
        }

        if (!$set) {
            return;
        }

        $set[] = 'updated_at = NOW()';
        $stmt = db()->prepare('UPDATE reservations SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public static function countByStatus(string $status, ?int $userId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM reservations WHERE status = :status';
        $params = ['status' => $status];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function unpaidCount(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = :user_id AND payment_status = 'unpaid'");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
