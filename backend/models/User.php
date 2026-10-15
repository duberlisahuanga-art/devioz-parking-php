<?php

declare(strict_types=1);

final class User
{
    public static function updateProfile(int $id, string $name, string $email, ?string $phone): void
    {
        $stmt = db()->prepare('UPDATE users SET name=:name,email=:email,phone=:phone,updated_at=NOW() WHERE id=:id');
        $stmt->execute(['id' => $id, 'name' => $name, 'email' => $email, 'phone' => $phone]);
    }

    public static function changePassword(int $id, string $previousHash, string $newHash): bool
    {
        $stmt = db()->prepare('UPDATE users SET password=:new_hash,updated_at=NOW() WHERE id=:id AND password=:previous_hash');
        $stmt->execute(['id' => $id, 'new_hash' => $newHash, 'previous_hash' => $previousHash]);
        return $stmt->rowCount() === 1;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function all(string $search = '', string $role = '', string $status = ''): array
    {
        $sql = 'SELECT u.*,
                    (SELECT COUNT(*) FROM vehicles v WHERE v.user_id = u.id) AS vehicles_count,
                    (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.id) AS reservations_count
                FROM users u WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (u.name LIKE :search1 OR u.email LIKE :search2)';
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }
        if (in_array($role, ['admin', 'driver'], true)) {
            $sql .= ' AND u.role = :role';
            $params['role'] = $role;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY u.created_at DESC, u.id DESC LIMIT 51 OFFSET ' . page_offset();
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    public static function create(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO users
        (
            name,
            email,
            password,
            role,
            status,
            created_at,
            updated_at
        )
        VALUES
        (
            :name,
            :email,
            :password,
            :role,
            :status,
            NOW(),
            NOW()
        )'
    );

    $stmt->execute([
        'name' => $data['name'],
        'email' => strtolower(trim((string) $data['email'])),
        'password' => $data['password'],
        'role' => $data['role'],
        'status' => $data['status'],
    ]);

    return (int) db()->lastInsertId();
}

    public static function summary(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(role='admin') AS admins,
                    SUM(role='driver') AS drivers,
                    SUM(status='active') AS active_users,
                    SUM(status='inactive') AS inactive_users
             FROM users"
        )->fetch();

        return $row ?: [];
    }
}
