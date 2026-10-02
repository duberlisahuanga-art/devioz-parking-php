<?php

declare(strict_types=1);

final class PaymentController
{
    public static function submit(int $reservationId, array $data, array $files): int
    {
        $user = require_driver();
        $reservation = Reservation::find($reservationId);
        if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('No tienes permiso para pagar esta reserva.');
        }
        if ($reservation['payment_status'] !== 'unpaid') {
            throw new RuntimeException('Esta reserva ya tiene un pago en revision o fue pagada.');
        }

        if (($data['method'] ?? '') !== 'yape') {
            throw new RuntimeException('Por ahora el metodo habilitado es Yape.');
        }

        $providerTxId = clean_string($data['provider_tx_id'] ?? '', 100);
        if ($providerTxId === '') {
            throw new RuntimeException('Ingresa el codigo u operacion de Yape.');
        }

        $file = $files['voucher'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Adjunta el comprobante de pago.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('El comprobante no puede superar 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Formato de comprobante no permitido.');
        }

        $dir = __DIR__ . '/../uploads/comprobantes';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de comprobantes.');
        }

        $filename = sprintf('reserva_%d_%s.%s', $reservationId, bin2hex(random_bytes(8)), $allowed[$mime]);
        $destination = $dir . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar el comprobante.');
        }

        $relativePath = 'backend/uploads/comprobantes/' . $filename;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $paymentId = Payment::create([
                'reservation_id' => $reservationId,
                'amount' => (float) $reservation['amount'],
                'method' => 'yape',
                'provider' => 'manual',
                'provider_tx_id' => $providerTxId,
                'voucher_path' => $relativePath,
                'status' => 'pending',
            ]);
            Reservation::update($reservationId, ['payment_status' => 'pending']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink($destination);
            throw $e;
        }

        AuditService::log('Pagos', 'Enviado', "Voucher enviado para la reserva #{$reservationId}.", 'Payment', $paymentId, "PAY-{$paymentId}", [
            'reservation_id' => $reservationId,
            'space' => $reservation['space_code'],
            'vehicle_plate' => $reservation['vehicle_plate'],
            'amount' => $reservation['amount'],
            'method' => 'yape',
            'provider_tx_id' => $providerTxId,
        ]);

        return $paymentId;
    }

    public static function confirm(int $paymentId): void
    {
        require_admin();
        $payment = Payment::find($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            throw new RuntimeException('Este pago ya fue procesado o no existe.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Payment::setStatus($paymentId, 'paid');
            Reservation::update((int) $payment['reservation_id'], ['payment_status' => 'paid']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Pagos', 'Aprobado', "Pago #{$paymentId} aprobado para la reserva #{$payment['reservation_id']}.", 'Payment', $paymentId, "PAY-{$paymentId}", [
            'space' => $payment['space_code'],
            'vehicle_plate' => $payment['vehicle_plate'],
            'amount' => $payment['amount'],
        ]);
    }

    public static function reject(int $paymentId): void
    {
        require_admin();
        $payment = Payment::find($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            throw new RuntimeException('Este pago ya fue procesado o no existe.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Payment::setStatus($paymentId, 'rejected');
            Reservation::update((int) $payment['reservation_id'], ['payment_status' => 'unpaid']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Pagos', 'Rechazado', "Pago #{$paymentId} rechazado para la reserva #{$payment['reservation_id']}.", 'Payment', $paymentId, "PAY-{$paymentId}");
    }
}
