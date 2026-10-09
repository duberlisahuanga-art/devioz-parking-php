<?php

declare(strict_types=1);

final class Payment
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT
                py.*,
                r.user_id,
                r.space_id,
                r.vehicle_id,
                r.payment_status AS reservation_payment_status,

                s.code AS space_code,

                v.plate AS vehicle_plate,

                u.name AS user_name,
                u.email AS user_email

             FROM payments py

             JOIN reservations r
                ON r.id = py.reservation_id

             JOIN spaces s
                ON s.id = r.space_id

             JOIN vehicles v
                ON v.id = r.vehicle_id

             JOIN users u
                ON u.id = r.user_id

             WHERE py.id = :id

             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
        ]);

        return $stmt->fetch() ?: null;
    }


    /*
    |--------------------------------------------------------------------------
    | Pagos pendientes
    |--------------------------------------------------------------------------
    |
    | Se mantiene este método porque actualmente es utilizado por otras
    | partes del sistema.
    |
    */

    public static function pending(): array
    {
        $sql = "
            SELECT
                py.*,

                r.user_id,

                s.code AS space_code,

                v.plate AS vehicle_plate,

                u.name AS user_name,
                u.email AS user_email

            FROM payments py

            JOIN reservations r
                ON r.id = py.reservation_id

            JOIN spaces s
                ON s.id = r.space_id

            JOIN vehicles v
                ON v.id = r.vehicle_id

            JOIN users u
                ON u.id = r.user_id

            WHERE py.status = 'pending'

            ORDER BY
                py.created_at DESC,
                py.id DESC
        ";

        return db()->query($sql)->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Listado administrativo de pagos
    |--------------------------------------------------------------------------
    */

    public static function allForAdmin(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $userId = (int) ($filters['user_id'] ?? 0);

        $sql = "
            SELECT
                py.*,

                r.user_id,
                r.status AS reservation_status,
                r.starts_on,
                r.ends_on,

                s.code AS space_code,

                v.plate AS vehicle_plate,
                v.brand AS vehicle_brand,
                v.model AS vehicle_model,

                u.name AS user_name,
                u.email AS user_email,
                u.phone AS user_phone

            FROM payments py

            JOIN reservations r
                ON r.id = py.reservation_id

            JOIN spaces s
                ON s.id = r.space_id

            JOIN vehicles v
                ON v.id = r.vehicle_id

            JOIN users u
                ON u.id = r.user_id

            WHERE 1 = 1
        ";

        $params = [];

        if ($status !== '') {
            $sql .= "
                AND py.status = :status
            ";

            $params['status'] = $status;
        }

        if ($userId > 0) {
            $sql .= "
                AND r.user_id = :user_id
            ";

            $params['user_id'] = $userId;
        }

        if ($search !== '') {
            $sql .= "
                AND (
                    u.name LIKE :search
                    OR u.email LIKE :search
                    OR v.plate LIKE :search
                    OR s.code LIKE :search
                    OR py.provider_tx_id LIKE :search
                    OR CAST(py.id AS CHAR) LIKE :search
                    OR CAST(py.reservation_id AS CHAR) LIKE :search
                )
            ";

            $params['search'] = '%' . $search . '%';
        }

        $sql .= "
            ORDER BY
                py.created_at DESC,
                py.id DESC
        ";

        $stmt = db()->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Conductores con pagos
    |--------------------------------------------------------------------------
    */

    public static function driversWithPayments(): array
    {
        $sql = "
            SELECT DISTINCT
                u.id,
                u.name,
                u.email

            FROM payments py

            JOIN reservations r
                ON r.id = py.reservation_id

            JOIN users u
                ON u.id = r.user_id

            ORDER BY
                u.name ASC,
                u.id ASC
        ";

        return db()->query($sql)->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Resumen administrativo
    |--------------------------------------------------------------------------
    */

    public static function adminSummary(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'pending' THEN 1
                        ELSE 0
                    END
                ) AS pending,

                SUM(
                    CASE
                        WHEN status = 'paid' THEN 1
                        ELSE 0
                    END
                ) AS paid,

                SUM(
                    CASE
                        WHEN status = 'rejected' THEN 1
                        ELSE 0
                    END
                ) AS rejected,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'paid' THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS paid_total

            FROM payments
        ";

        $row = db()->query($sql)->fetch();

        return $row ?: [
            'total' => 0,
            'pending' => 0,
            'paid' => 0,
            'rejected' => 0,
            'paid_total' => 0,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Crear pago
    |--------------------------------------------------------------------------
    */

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO payments
             (
                reservation_id,
                amount,
                method,
                provider,
                provider_tx_id,
                voucher_path,
                status,
                created_at,
                updated_at
             )
             VALUES
             (
                :reservation_id,
                :amount,
                :method,
                :provider,
                :provider_tx_id,
                :voucher_path,
                :status,
                NOW(),
                NOW()
             )'
        );

        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }


    /*
    |--------------------------------------------------------------------------
    | Cambiar estado del pago
    |--------------------------------------------------------------------------
    */

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'paid', 'rejected'], true)) {
            throw new InvalidArgumentException(
                'Estado de pago invalido.'
            );
        }

        $stmt = db()->prepare(
            'UPDATE payments
             SET status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'status' => $status,
            'id' => $id,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Total pagado por conductor
    |--------------------------------------------------------------------------
    */

    public static function paidTotalForUser(int $userId): float
    {
        $stmt = db()->prepare(
            "SELECT
                COALESCE(SUM(py.amount), 0)

             FROM payments py

             JOIN reservations r
                ON r.id = py.reservation_id

             WHERE r.user_id = :user_id
               AND py.status = 'paid'"
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (float) $stmt->fetchColumn();
    }
}