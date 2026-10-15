<?php

declare(strict_types=1);

final class NotificationService
{
    public static function send(int $userId, string $type, string $title, string $message, string $path, ?string $eventKey = null): void
    {
        $preferences = UserPreference::forUser($userId);
        if ((int) ($preferences[$type] ?? 0) === 1) Notification::create($userId, $type, $title, $message, $path, $eventKey);
    }

    public static function admins(string $type, string $title, string $message, string $path, string $eventKey): void
    {
        $stmt = db()->prepare("SELECT id FROM users WHERE role=:role AND status=:status");
        $stmt->execute(['role' => 'admin', 'status' => 'active']);
        foreach ($stmt->fetchAll() as $user) self::send((int) $user['id'], $type, $title, $message, $path, $eventKey);
    }
}
