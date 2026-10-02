<?php

declare(strict_types=1);

final class Payment
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT py.*, r.user_id, r.space_id, r.vehicle_id, r.payment_status AS reservation_payment_status,
                    s.code AS space_code, v.plate AS vehicle_plate, u.name AS user_name, u.email AS user_email
             FROM payments py
             JOIN reservations r ON r.id = py.reservation_id
             JOIN spaces s ON s.id = r.space_id
             JOIN vehicles v ON v.id = r.vehicle_id
             JOIN users u ON u.id = r.user_id
             WHERE py.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function pending(): array
    {
        $sql = "SELECT py.*, r.user_id, s.code AS space_code, v.plate AS vehicle_plate,
                       u.name AS user_name, u.email AS user_email
                FROM payments py
                JOIN reservations r ON r.id = py.reservation_id
                JOIN spaces s ON s.id = r.space_id
                JOIN vehicles v ON v.id = r.vehicle_id
                JOIN users u ON u.id = r.user_id
                WHERE py.status='pending'
                ORDER BY py.created_at DESC, py.id DESC";
        return db()->query($sql)->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO payments
             (reservation_id, amount, method, provider, provider_tx_id, voucher_path, status, created_at, updated_at)
             VALUES
             (:reservation_id, :amount, :method, :provider, :provider_tx_id, :voucher_path, :status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'paid', 'rejected'], true)) {
            throw new InvalidArgumentException('Estado de pago invalido.');
        }
        $stmt = db()->prepare('UPDATE payments SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function paidTotalForUser(int $userId): float
    {
        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(py.amount), 0)
             FROM payments py
             JOIN reservations r ON r.id = py.reservation_id
             WHERE r.user_id = :user_id AND py.status='paid'"
        );
        $stmt->execute(['user_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }
}
