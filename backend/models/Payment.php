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
                    u.name LIKE :search1
                    OR u.email LIKE :search2
                    OR v.plate LIKE :search3
                    OR s.code LIKE :search4
                    OR py.provider_tx_id LIKE :search5
                    OR CAST(py.id AS CHAR) LIKE :search6
                    OR CAST(py.reservation_id AS CHAR) LIKE :search7
                )
            ";

            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
            $params['search3'] = '%' . $search . '%';
            $params['search4'] = '%' . $search . '%';
            $params['search5'] = '%' . $search . '%';
            $params['search6'] = '%' . $search . '%';
            $params['search7'] = '%' . $search . '%';
        }

        $sql .= "
            ORDER BY
                py.created_at DESC,
                py.id DESC
        ";

        if (str_contains($sql, 'FROM payments py') && str_contains($sql, 'WHERE 1 = 1')) $sql .= ' LIMIT 51 OFFSET ' . page_offset();
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
             WHERE id = :id AND status = \'pending\''
        );

        $stmt->execute([
            'status' => $status,
            'id' => $id,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new ValidationException('El pago ya fue procesado.');
        }
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

    // La aprobación actualiza updated_at; el esquema no tiene paid_at.
    public static function financeSummary(?string $start, string $end): array
    {
        $sql = "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS count FROM payments WHERE status='paid' AND COALESCE(updated_at,created_at)<:end";
        $params = ['end' => $end];
        if ($start !== null) {
            $sql .= ' AND COALESCE(updated_at,created_at)>=:start';
            $params['start'] = $start;
        }
        if (str_contains($sql, 'FROM payments py') && str_contains($sql, 'WHERE 1 = 1')) $sql .= ' LIMIT 51 OFFSET ' . page_offset();
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public static function financePayments(array $filters): array
    {
        $sql = "SELECT py.*, COALESCE(py.updated_at,py.created_at) AS finance_datetime,
            u.name AS user_name, s.code AS space_code, v.plate AS vehicle_plate,
            v.brand AS vehicle_brand, v.model AS vehicle_model
            FROM payments py
            LEFT JOIN reservations r ON r.id=py.reservation_id
            LEFT JOIN users u ON u.id=r.user_id
            LEFT JOIN spaces s ON s.id=r.space_id
            LEFT JOIN vehicles v ON v.id=r.vehicle_id
            WHERE py.status='paid' AND COALESCE(py.updated_at,py.created_at)>=:start
            AND COALESCE(py.updated_at,py.created_at)<:end";
        $params = ['start' => $filters['start'], 'end' => $filters['end']];
        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (u.name LIKE :q1 OR v.plate LIKE :q2 OR CAST(py.reservation_id AS CHAR) LIKE :q3 OR py.provider_tx_id LIKE :q4 OR s.code LIKE :q5 OR CONCAT("Reserva #",py.reservation_id) LIKE :q6 OR "Pago aprobado" LIKE :q7)';
            for ($i=1; $i<=7; $i++) $params['q'.$i] = '%' . $filters['search'] . '%';
        }
        $stmt = db()->prepare($sql . ' ORDER BY finance_datetime DESC, py.id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
