<?php

declare(strict_types=1);

final class NotificationController
{
    public static function index(): array
    {
        $user = require_auth();
        $items = Notification::forUser((int) $user['id']);
        foreach ($items as &$item) $item['action_url'] = Notification::safeActionUrl($item['action_url']);
        unset($item);
        return [
            'items' => $items,
            'unread_count' => Notification::unreadCount((int) $user['id']),
            'read_count' => Notification::readCount((int) $user['id']),
        ];
    }

    public static function mark(array $data): int
    {
        $user = require_auth();
        verify_csrf();
        if (($data['action'] ?? '') === 'clear_read') {
            return Notification::deleteRead((int) $user['id']);
        }
        if (($data['action'] ?? '') === 'mark_all') {
            Notification::markAllRead((int) $user['id']);
        } elseif (($data['action'] ?? '') === 'mark_read') {
            $id = filter_var($data['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new ValidationException('Notificación inválida.');
            Notification::markRead((int) $user['id'], $id);
        } else throw new ValidationException('Acción inválida.');
        return 0;
    }
}
