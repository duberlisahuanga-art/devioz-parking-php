<?php

declare(strict_types=1);

final class PaymentController
{
    public static function eligible(array $r): bool
    {
        return in_array($r['status'], ['confirmed', 'active'], true)
            && $r['cancellation_status'] !== 'pending' && $r['ends_on'] >= ReservationController::today();
    }

    public static function submit(int $reservationId, array $data, array $files): int
    {
        $user = require_driver();
        $code = $data['provider_tx_id'] ?? null;
        if (($data['method'] ?? '') !== 'yape' || !is_string($code) || preg_match('/^\d{3}$/D', $code) !== 1) {
            throw new ValidationException('Selecciona Yape e ingresa un código de seguridad de 3 dígitos.');
        }
        $file = $files['voucher'] ?? null;
        if (
            !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
        ) {
            throw new ValidationException('Adjunta un comprobante válido.');
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new ValidationException('El comprobante debe tener como máximo 5 MB.');
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new ValidationException('Formato de comprobante no permitido.');
        }
        $destination = null;
        try {
            return transaction(function () use ($reservationId, $user, $file, $allowed, $mime, $code, &$destination): int {
                $r = Reservation::find($reservationId, true);
                if (!$r || (int) $r['user_id'] !== (int) $user['id']) {
                    AuditService::denied('payment_reservation_owner_required');
                    throw new ValidationException('No tienes permiso para pagar esta reserva.');
                }
                if (!self::eligible($r) || $r['payment_status'] !== 'unpaid') {
                    throw new ValidationException('La reserva no admite un nuevo pago.');
                }
                $active = db()->prepare("SELECT id FROM payments WHERE reservation_id=:id AND status IN ('pending','paid') LIMIT 1 FOR UPDATE");
                $active->execute(['id' => $reservationId]);
                if ($active->fetch()) {
                    throw new ValidationException('Ya existe un pago activo para esta reserva.');
                }
                $directory = __DIR__ . '/../uploads/comprobantes';
                if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                    throw new RuntimeException('No se pudo crear el almacenamiento.');
                }
                $name = sprintf('reserva_%d_%s.%s', $reservationId, bin2hex(random_bytes(16)), $allowed[$mime]);
                $destination = $directory . '/' . $name;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException('No se pudo almacenar el comprobante.');
                }
                $id = Payment::create(['reservation_id' => $reservationId, 'amount' => $r['amount'],
                    'method' => 'yape', 'provider' => 'manual', 'provider_tx_id' => $code,
                    'voucher_path' => 'backend/uploads/comprobantes/' . $name, 'status' => 'pending']);
                Reservation::update($reservationId, ['payment_status' => 'pending']);
                AuditService::log('Pagos', 'Enviado', "Comprobante enviado para la reserva #{$reservationId}.", 'Payment', $id, "PAY-{$id}");
                NotificationService::admins('payments', 'Comprobante pendiente', "El pago #{$id} requiere revisión.", '/frontend/admin/pagos.php', "payment:{$id}:submitted");
                return $id;
            });
        } catch (Throwable $error) {
            if ($destination !== null && is_file($destination)) {
                unlink($destination);
            }
            throw $error;
        }
    }

    public static function confirm(int $id): void
    {
        self::review($id, 'paid');
    }

    public static function reject(int $id): void
    {
        self::review($id, 'rejected');
    }

    private static function review(int $id, string $status): void
    {
        require_admin();
        $initial = Payment::find($id);
        if (!$initial) {
            throw new ValidationException('Pago no encontrado.');
        }
        transaction(function () use ($id, $initial, $status): void {
            $r = Reservation::find((int) $initial['reservation_id'], true);
            $query = db()->prepare('SELECT * FROM payments WHERE id=:id FOR UPDATE');
            $query->execute(['id' => $id]);
            $p = $query->fetch();
            if (!$r || !$p) {
                throw new ValidationException('Pago no encontrado.');
            }
            if ($p['status'] === $status) {
                return;
            }
            if ($p['status'] !== 'pending') {
                throw new ValidationException('El pago ya fue procesado.');
            }
            if ($status === 'paid' && !self::eligible($r)) {
                throw new ValidationException('La reserva no permite aprobar este pago.');
            }
            Payment::setStatus($id, $status);
            Reservation::update((int) $r['id'], ['payment_status' => $status === 'paid' ? 'paid' : 'unpaid']);
            $label = $status === 'paid' ? 'aprobado' : 'rechazado';
            AuditService::log('Pagos', ucfirst($label), "Pago #{$id} {$label}.", 'Payment', $id, "PAY-{$id}");
            NotificationService::send((int) $r['user_id'], 'payments', 'Pago ' . $label, "Tu pago #{$id} fue {$label}.", '/frontend/conductor/pagos.php', "payment:{$id}:{$status}");
        });
    }
}
