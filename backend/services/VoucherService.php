<?php

declare(strict_types=1);

final class VoucherService
{
    public static function url(string $type, int $id): string
    {
        return app_url('/frontend/comprobante.php?type=' . rawurlencode($type) . '&id=' . $id);
    }

    public static function authorizedPath(array $user, string $type, int $id): ?string
    {
        if ($type === 'payment') {
            $record = Payment::find($id);
            if (!$record || ($user['role'] !== 'admin' && (int) $record['user_id'] !== (int) $user['id'])) {
                AuditService::denied('payment_voucher_owner_required');
                return null;
            }
        } elseif ($type === 'expense' && $user['role'] === 'admin') {
            $record = Expense::find($id);
            if (!$record) {
                return null;
            }
        } else {
            AuditService::denied('voucher_type_or_role_denied');
            return null;
        }
        $path = $record['voucher_path'] ?? '';
        if (!is_string($path) || preg_match('~^backend/uploads/(comprobantes|egresos)/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp|pdf)$~D', $path) !== 1) {
            return null;
        }
        $root = realpath(__DIR__ . '/../uploads');
        $resolved = realpath(dirname(__DIR__, 2) . '/' . $path);
        return $root !== false && $resolved !== false && is_file($resolved)
            && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) ? $resolved : null;
    }
}
