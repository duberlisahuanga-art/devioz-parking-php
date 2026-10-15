<?php

declare(strict_types=1);

final class UserPreference
{
    public const DEFAULTS = ['reservations' => 1, 'payments' => 1, 'pending_actions' => 1, 'system_notices' => 1];

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT reservations, payments, pending_actions, system_notices FROM user_preferences WHERE user_id=:id');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: self::DEFAULTS;
    }

    public static function save(int $userId, array $preferences): void
    {
        $stmt = db()->prepare('INSERT INTO user_preferences (user_id,reservations,payments,pending_actions,system_notices)
            VALUES (:id,:reservations,:payments,:pending_actions,:system_notices)
            ON DUPLICATE KEY UPDATE reservations=VALUES(reservations), payments=VALUES(payments),
            pending_actions=VALUES(pending_actions), system_notices=VALUES(system_notices)');
        $stmt->execute(['id' => $userId] + array_intersect_key($preferences, self::DEFAULTS));
    }
}
