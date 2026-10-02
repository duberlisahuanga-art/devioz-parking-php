<?php

declare(strict_types=1);

final class ReservationController
{
    public static function index(): array
    {
        $user = require_auth();
        return ($user['role'] ?? '') === 'admin'
            ? Reservation::all()
            : Reservation::forUser((int) $user['id']);
    }

    public static function createData(): array
    {
        $user = require_driver();
        $parking = Parking::devioz();
        if (!$parking) {
            throw new RuntimeException('No se encontro el estacionamiento Devioz.');
        }

        return [
            'parking' => $parking,
            'vehicles' => Vehicle::forUser((int) $user['id']),
            'spaces' => Space::forParking((int) $parking['id']),
            'monthly_price' => MONTHLY_PRICE,
        ];
    }

    public static function store(array $data): int
    {
        $user = require_driver();
        $spaceId = (int) ($data['space_id'] ?? 0);
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $startsOn = trim((string) ($data['starts_on'] ?? ''));

        if ($spaceId < 1 || $vehicleId < 1 || $startsOn === '') {
            throw new RuntimeException('Completa plaza, vehiculo y fecha de inicio.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startsOn);
        $today = new DateTimeImmutable('today');
        if (!$start || $start->format('Y-m-d') !== $startsOn || $start < $today) {
            throw new RuntimeException('La fecha de inicio debe ser valida y no puede estar en el pasado.');
        }

        $endsOn = monthly_end_date($startsOn);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM spaces WHERE id = :id AND status='available' FOR UPDATE");
            $stmt->execute(['id' => $spaceId]);
            $space = $stmt->fetch();
            if (!$space) {
                throw new RuntimeException('La plaza seleccionada ya no se encuentra disponible.');
            }

            $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
            if (!$vehicle) {
                throw new RuntimeException('El vehiculo seleccionado no te pertenece.');
            }

            $reservationId = Reservation::create([
                'user_id' => (int) $user['id'],
                'parking_id' => (int) $space['parking_id'],
                'space_id' => $spaceId,
                'vehicle_id' => $vehicleId,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'confirmed',
                'amount' => MONTHLY_PRICE,
                'currency' => 'PEN',
                'payment_status' => 'unpaid',
                'cancellation_status' => 'none',
            ]);

            Space::setStatus($spaceId, 'reserved');
            $pdo->commit();

            AuditService::log(
                'Reservas',
                'Creada',
                "Reserva mensual creada para la plaza {$space['code']}.",
                'Reservation',
                $reservationId,
                $space['code'],
                [
                    'vehicle_plate' => $vehicle['plate'],
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'monthly_price' => MONTHLY_PRICE,
                    'payment_status' => 'unpaid',
                ]
            );

            return $reservationId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function checkin(int $reservationId): void
    {
        require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] === 'pending') {
            throw new RuntimeException('No se puede registrar el ingreso mientras exista una cancelacion pendiente.');
        }
        if ($reservation['status'] !== 'confirmed') {
            throw new RuntimeException('La reserva no esta disponible para registrar ingreso.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'active',
                'check_in_at' => date('Y-m-d H:i:s'),
                'check_out_at' => null,
            ]);
            Space::setStatus((int) $reservation['space_id'], 'occupied');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Ingreso', "Ingreso registrado en la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function checkout(int $reservationId): void
    {
        require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['status'] !== 'active') {
            throw new RuntimeException('El vehiculo no se encuentra registrado dentro del estacionamiento.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'confirmed',
                'check_out_at' => date('Y-m-d H:i:s'),
            ]);
            Space::setStatus((int) $reservation['space_id'], 'reserved');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Salida', "Salida registrada de la plaza {$reservation['space_code']}. La plaza permanece reservada por la mensualidad.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function requestCancellation(int $reservationId, string $reason): void
    {
        $user = require_driver();
        $reservation = Reservation::find($reservationId);
        if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('No puedes solicitar la cancelacion de esta reserva.');
        }
        if ($reservation['status'] !== 'confirmed') {
            throw new RuntimeException('No puedes solicitar la cancelacion en el estado actual.');
        }
        if ($reservation['cancellation_status'] === 'pending') {
            throw new RuntimeException('Ya existe una solicitud de cancelacion pendiente.');
        }

        $reason = clean_string($reason, 1000);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Indica un motivo de al menos 5 caracteres.');
        }

        Reservation::update($reservationId, [
            'cancellation_status' => 'pending',
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => date('Y-m-d H:i:s'),
            'cancellation_reviewed_at' => null,
            'cancellation_reviewed_by' => null,
            'cancellation_review_note' => null,
        ]);

        AuditService::log('Reservas', 'Cancelacion solicitada', "Se solicito la cancelacion de la mensualidad de la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['reason' => $reason]);
    }

    public static function approveCancellation(int $reservationId, ?string $note = null): void
    {
        $admin = require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] !== 'pending') {
            throw new RuntimeException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
        }
        if ($reservation['status'] === 'active') {
            throw new RuntimeException('No se puede aprobar la cancelacion mientras el vehiculo este dentro.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'cancelled',
                'cancellation_status' => 'approved',
                'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
                'cancellation_reviewed_by' => (int) $admin['id'],
                'cancellation_review_note' => $note !== null ? clean_string($note, 1000) : null,
            ]);
            Space::setStatus((int) $reservation['space_id'], 'available');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Cancelacion aprobada', "Cancelacion aprobada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function rejectCancellation(int $reservationId, string $note): void
    {
        $admin = require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] !== 'pending') {
            throw new RuntimeException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
        }

        $note = clean_string($note, 1000);
        if (mb_strlen($note) < 5) {
            throw new RuntimeException('La nota debe tener al menos 5 caracteres.');
        }

        Reservation::update($reservationId, [
            'cancellation_status' => 'rejected',
            'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
            'cancellation_reviewed_by' => (int) $admin['id'],
            'cancellation_review_note' => $note,
        ]);

        AuditService::log('Reservas', 'Cancelacion rechazada', "Cancelacion rechazada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['note' => $note]);
    }
}
