<?php

declare(strict_types=1);

final class Expense
{
    public const CATEGORIES = ['Mantenimiento', 'Limpieza', 'Personal', 'Otros'];
    public const METHODS = ['Yape', 'Efectivo', 'Transferencia bancaria'];

    private static function conditions(array $filters): array
    {
        $sql = ' WHERE 1=1';
        $params = [];
        foreach (['category', 'payment_method'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                $sql .= " AND e.$key = :$key";
                $params[$key] = $filters[$key];
            }
        }
        foreach (['start' => '>=', 'end' => '<'] as $key => $operator) {
            if (!empty($filters[$key])) {
                $sql .= " AND e.expense_datetime $operator :$key";
                $params[$key] = $filters[$key];
            }
        }
        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (e.concept LIKE :q1 OR e.description LIKE :q2 OR u.name LIKE :q3 OR e.other_detail LIKE :q4 OR CAST(e.id AS CHAR) LIKE :q5)';
            for ($i = 1; $i <= 5; $i++) $params['q' . $i] = '%' . $filters['search'] . '%';
        }
        return [$sql, $params];
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT e.*, u.name AS created_by_name FROM expenses e JOIN users u ON u.id=e.created_by WHERE e.id=:id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO expenses (category, concept, amount, expense_datetime, payment_method, description, other_detail, voucher_path, created_by) VALUES (:category, :concept, :amount, :expense_datetime, :payment_method, :description, :other_detail, :voucher_path, :created_by)');
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare('UPDATE expenses SET category=:category, concept=:concept, amount=:amount, expense_datetime=:expense_datetime, payment_method=:payment_method, description=:description, other_detail=:other_detail, voucher_path=:voucher_path, updated_at=NOW() WHERE id=:id');
        $stmt->execute([...$data, 'id' => $id]);
    }

    public static function all(array $filters = []): array
    {
        [$where, $params] = self::conditions($filters);
        $stmt = db()->prepare('SELECT e.*, u.name AS created_by_name FROM expenses e JOIN users u ON u.id=e.created_by' . $where . ' ORDER BY e.expense_datetime DESC, e.id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalForPeriod(?string $start, string $end): float
    {
        [$where, $params] = self::conditions(['start' => $start, 'end' => $end]);
        $stmt = db()->prepare('SELECT COALESCE(SUM(e.amount),0) FROM expenses e' . $where);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public static function totalsByCategory(string $start, string $end): array
    {
        $stmt = db()->prepare('SELECT category, SUM(amount) AS total FROM expenses WHERE expense_datetime>=:start AND expense_datetime<:end GROUP BY category');
        $stmt->execute(['start' => $start, 'end' => $end]);
        $totals = array_fill_keys(self::CATEGORIES, 0);
        foreach ($stmt->fetchAll() as $row) $totals[$row['category']] = (float) $row['total'];
        return $totals;
    }

    public static function recent(int $limit = 20, array $filters = []): array
    {
        return array_slice(self::all($filters), 0, max(0, $limit));
    }

    public static function count(array $filters = []): int
    {
        [$where, $params] = self::conditions($filters);
        $stmt = db()->prepare('SELECT COUNT(*) FROM expenses e JOIN users u ON u.id=e.created_by' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}