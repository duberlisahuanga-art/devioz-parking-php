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
                       v.model AS vehicle_model, v.color AS vehicle_color, v.type AS vehicle_type,
                       renewal.id AS renewal_id, renewal.status AS renewal_status
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                JOIN parkings p ON p.id = r.parking_id
                JOIN spaces s ON s.id = r.space_id
                JOIN vehicles v ON v.id = r.vehicle_id
                LEFT JOIN reservations renewal ON renewal.renewed_from_id = r.id";
    }

    public static function find(int $id, bool $forUpdate = false): ?array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE r.id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forUser(int $userId, bool $paginate = false, ?bool $history = null): array
    {
        $sql = self::baseSelect() . ' WHERE r.user_id = :user_id ORDER BY r.created_at DESC, r.id DESC';
        if ($history !== null) {
            $sql = self::baseSelect() . ' WHERE r.user_id = :user_id AND r.status '
                . ($history ? "IN ('expired','cancelled')" : "IN ('confirmed','active')")
                . ' ORDER BY r.created_at DESC, r.id DESC';
        }
        if ($paginate) {
            $sql .= ' LIMIT 51 OFFSET ' . page_offset();
        }
        $stmt = db()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(?bool $history = null): array
    {
        $filter = $history === null ? '' : ' WHERE r.status '
            . ($history ? "IN ('expired','cancelled')" : "IN ('confirmed','active')");
        return db()->query(self::baseSelect() . $filter
            . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 51 OFFSET ' . page_offset())->fetchAll();
    }

    public static function searchForAdmin(array $filters): array
    {
        if ($filters['errors'] !== []) {
            return ['reservations' => [], 'total' => 0, 'page' => 1];
        }
        [$where, $parameters] = self::adminWhere($filters);
        $count = db()->prepare('SELECT COUNT(*) FROM reservations r
            JOIN users u ON u.id=r.user_id JOIN spaces s ON s.id=r.space_id
            JOIN vehicles v ON v.id=r.vehicle_id' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $page = min((int) $filters['page'], max(1, (int) ceil($total / 50)));
        $offset = ($page - 1) * 50;
        $query = db()->prepare(self::baseSelect() . $where
            . ' ORDER BY r.created_at DESC, r.id DESC LIMIT 51 OFFSET ' . $offset);
        $query->execute($parameters);
        return ['reservations' => $query->fetchAll(), 'total' => $total, 'page' => $page];
    }

    private static function adminWhere(array $filters): array
    {
        $where = [$filters['view'] === 'history' ? "r.status IN ('expired','cancelled')"
            : "r.status IN ('confirmed','active')"];
        $parameters = [];
        foreach (
            ['status' => 'r.status', 'payment' => 'r.payment_status', 'vehicle_type' => 'v.type',
            'space_id' => 'r.space_id', 'cancellation' => 'r.cancellation_status'] as $key => $column
        ) {
            if ($filters[$key] !== '') {
                $where[] = $column . ' = :' . $key;
                $parameters[$key] = $filters[$key];
            }
        }
        if ($filters['search'] !== '') {
            $escape = static fn(string $value): string => '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $search = $filters['search'];
            $searchWhere = ["u.name LIKE :name ESCAPE '!'", "u.email LIKE :email ESCAPE '!'",
                "REPLACE(v.plate,'-','') LIKE :plate ESCAPE '!'"];
            $parameters['name'] = $escape($search);
            $parameters['email'] = $escape($search);
            $parameters['plate'] = $escape(str_replace('-', '', $search));
            if (preg_match('/^#?\d{1,20}$/D', $search)) {
                $searchWhere[] = 'r.id = :reservation_id';
                $parameters['reservation_id'] = ltrim($search, '#');
            }
            $where[] = '(' . implode(' OR ', $searchWhere) . ')';
        }
        $today = ReservationController::today();
        if ($filters['validity'] === 'future') {
            $where[] = 'r.starts_on > :today';
            $parameters['today'] = $today;
        } elseif ($filters['validity'] === 'expired') {
            $where[] = 'r.ends_on < :today';
            $parameters['today'] = $today;
        } elseif ($filters['validity'] === 'current') {
            $where[] = 'r.starts_on <= :today_start AND r.ends_on >= :today_end';
            $parameters['today_start'] = $today;
            $parameters['today_end'] = $today;
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if ($filters[$key] !== '') {
                $where[] = 'r.starts_on ' . $operator . ' :' . $key;
                $parameters[$key] = $filters[$key];
            }
        }
        return [' WHERE ' . implode(' AND ', $where), $parameters];
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
