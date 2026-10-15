<?php

declare(strict_types=1);

final class Notification
{
    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT id,type,title,message,action_url,is_read,created_at,read_at FROM notifications
            WHERE user_id=:id ORDER BY is_read,id DESC LIMIT 50');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:id AND is_read=0');
        $stmt->execute(['id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $userId, int $notificationId): bool
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE id=:notification AND user_id=:user');
        $stmt->execute(['notification' => $notificationId, 'user' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function readCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:id AND is_read=1');
        $stmt->execute(['id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function deleteRead(int $userId): int
    {
        $stmt = db()->prepare('DELETE FROM notifications WHERE user_id=:id AND is_read=1');
        $stmt->execute(['id' => $userId]);
        return $stmt->rowCount();
    }

    public static function markAllRead(int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read=1,read_at=NOW() WHERE user_id=:id AND is_read=0');
        $stmt->execute(['id' => $userId]);
    }

    public static function safeActionUrl(?string $path): ?string
    {
        // Only allow the known account and module routes, never arbitrary URLs.
        if (!$path || !preg_match('~^/frontend/(?:configuracion\.php|admin/(?:reservas|pagos)\.php|conductor/(?:reservas|pagos)\.php)$~D', $path)) return null;
        return app_url($path);
    }

    public static function create(int $userId, string $type, string $title, string $message, string $path, ?string $eventKey = null): void
    {
        if (!array_key_exists($type, UserPreference::DEFAULTS) || !self::safeActionUrl($path)) throw new InvalidArgumentException('Notificación inválida.');
        $stmt = db()->prepare('INSERT INTO notifications (user_id,type,title,message,action_url,event_key)
            VALUES (:user,:type,:title,:message,:path,:event)
            ON DUPLICATE KEY UPDATE id=id');
        $stmt->execute(['user' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'path' => $path, 'event' => $eventKey]);
    }
}
