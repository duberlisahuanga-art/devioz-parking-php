<?php

declare(strict_types=1);

final class ReservationController
{
    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
    }

    public static function entryBlockReason(array $reservation): ?string
    {
        if ($reservation['status'] !== 'confirmed') {
            return 'La reserva no está disponible para registrar ingreso.';
        }
        if ($reservation['cancellation_status'] === 'pending') {
            return 'Primero resuelve la solicitud de cancelación.';
        }
        $today = self::today();
        if ($reservation['starts_on'] > $today) {
            return 'El periodo contratado todavía no ha comenzado.';
        }
        if ($reservation['ends_on'] < $today) {
            return 'El periodo contratado ya venció. No se permiten nuevos ingresos.';
        }
        if ($reservation['payment_status'] !== 'paid') {
            return $reservation['payment_status'] === 'pending'
                ? 'El comprobante está en revisión. Primero aprueba el pago.'
                : 'El pago debe estar aprobado antes de registrar el ingreso.';
        }
        return null;
    }

    public static function index(): array
    {
        $user = DriverContext::managed() ?? require_auth();
        ReservationLifecycleService::run();
        $history = ($_GET['view'] ?? '') === 'history';
        return ($user['role'] ?? '') === 'admin'
            ? Reservation::all($history)
            : Reservation::forUser((int) $user['id'], true, $history);
    }

    public static function adminData(array $input): array
    {
        require_admin();
        // Expiry is global maintenance: filters only affect the following read.
        ReservationLifecycleService::run();
        $filters = ReservationFilters::fromInput($input);
        $spaces = Space::filterOptions();
        if (
            $filters['space_id'] !== ''
            && !in_array($filters['space_id'], array_map('intval', array_column($spaces, 'id')), true)
        ) {
            $filters['errors'][] = 'La plaza seleccionada no existe. Elige una plaza válida.';
            $filters['space_id'] = '';
        }
        $result = $filters['errors'] === [] ? Reservation::searchForAdmin($filters)
            : ['reservations' => [], 'total' => 0, 'page' => 1];
        $filters['page'] = $result['page'];
        return [...$result, 'filters' => $filters, 'spaces' => $spaces];
    }

    public static function createData(): array
    {
        $user = DriverContext::user();
        ReservationLifecycleService::run();
        $parking = Parking::devioz();
        if (!$parking) {
            throw new ValidationException('No se encontro el estacionamiento Devioz.');
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
        $user = DriverContext::user();
        ReservationLifecycleService::run();
        $spaceId = (int) ($data['space_id'] ?? 0);
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $startsOn = trim((string) ($data['starts_on'] ?? ''));

        if ($spaceId < 1 || $vehicleId < 1 || $startsOn === '') {
            throw new ValidationException('Completa plaza, vehiculo y fecha de inicio.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startsOn);
        $today = new DateTimeImmutable('today');
        if (!$start || $start->format('Y-m-d') !== $startsOn || $start < $today) {
            throw new ValidationException('La fecha de inicio debe ser valida y no puede estar en el pasado.');
        }

        $endsOn = monthly_end_date($startsOn);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM spaces WHERE id = :id AND status='available' FOR UPDATE");
            $stmt->execute(['id' => $spaceId]);
            $space = $stmt->fetch();
            if (!$space) {
                throw new ValidationException('La plaza seleccionada ya no se encuentra disponible.');
            }

            $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
            if (!$vehicle) {
                AuditService::denied('reservation_vehicle_owner_required');
                throw new ValidationException('El vehiculo seleccionado no te pertenece.');
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
            NotificationService::admins('reservations', 'Nueva reserva', "Se registró la reserva mensual #{$reservationId}.", '/frontend/admin/reservas.php', "reservation:{$reservationId}:created");
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
            $pdo->commit();



            return $reservationId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function renew(int $reservationId): int
    {
        $user = DriverContext::user();
        ReservationLifecycleService::run();
        return transaction(function () use ($reservationId, $user): int {
            $previous = Reservation::find($reservationId, true);
            if (!$previous || (int) $previous['user_id'] !== (int) $user['id']) {
                AuditService::denied('renewal_reservation_owner_required');
                throw new ValidationException('Reserva no encontrada.');
            }
            $existing = db()->prepare('SELECT id FROM reservations WHERE renewed_from_id=:id LIMIT 1');
            $existing->execute(['id' => $reservationId]);
            if ($renewed = $existing->fetchColumn()) {
                return (int) $renewed;
            }
            if ($previous['status'] !== 'expired' || $previous['cancellation_status'] === 'pending') {
                throw new ValidationException('Renueva al finalizar la mensualidad y registrar la salida del vehículo.');
            }
            $stmt = db()->prepare('SELECT * FROM spaces WHERE id=:id FOR UPDATE');
            $stmt->execute(['id' => $previous['space_id']]);
            $space = $stmt->fetch();
            $other = db()->prepare(
                "SELECT id FROM reservations WHERE space_id=:space AND status IN ('confirmed','active') LIMIT 1 FOR UPDATE"
            );
            $other->execute(['space' => $previous['space_id']]);
            if (!$space || $space['status'] !== 'available' || $other->fetchColumn()) {
                throw new ValidationException('La plaza ya no está disponible. Elige otra desde Reservar plaza.');
            }
            if (!Vehicle::findOwned((int) $previous['vehicle_id'], (int) $user['id'])) {
                AuditService::denied('renewal_vehicle_owner_required');
                throw new ValidationException('El vehículo ya no pertenece a tu cuenta.');
            }
            $today = self::today();
            $id = Reservation::create([
                'user_id' => $user['id'], 'parking_id' => $previous['parking_id'],
                'space_id' => $previous['space_id'], 'vehicle_id' => $previous['vehicle_id'],
                'starts_on' => $today, 'ends_on' => monthly_end_date($today),
                'status' => 'confirmed', 'amount' => MONTHLY_PRICE, 'currency' => 'PEN',
                'payment_status' => 'unpaid', 'cancellation_status' => 'none',
            ]);
            $link = db()->prepare('UPDATE reservations SET renewed_from_id=:previous WHERE id=:id');
            $link->execute(['previous' => $reservationId, 'id' => $id]);
            Space::setStatus((int) $previous['space_id'], 'reserved');
            AuditService::log(
                'Reservas',
                'Renovada',
                'Nueva mensualidad a partir de la reserva #' . $reservationId,
                'Reservation',
                $id,
                $space['code']
            );
            NotificationService::send(
                (int) $user['id'],
                'reservations',
                'Renovación creada',
                'La reserva #' . $id . ' requiere un nuevo pago aprobado antes de ingresar.',
                '/frontend/conductor/reservas.php',
                'renewal:' . $reservationId
            );
            return $id;
        });
    }

    public static function checkin(int $reservationId): void
    {
        require_admin();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation) {
                throw new ValidationException('Reserva no encontrada.');
            }
            $reason = self::entryBlockReason($reservation);
            if ($reason !== null) {
                throw new ValidationException($reason);
            }
            Reservation::update($reservationId, [
                'status' => 'active',
                'check_in_at' => date('Y-m-d H:i:s'),
                'check_out_at' => null,
            ]);
            Space::setStatus((int) $reservation['space_id'], 'occupied');
            NotificationService::send((int) $reservation['user_id'], 'reservations', 'Ingreso registrado', "Se registró el ingreso de tu reserva #{$reservationId}.", '/frontend/conductor/reservas.php');
            AuditService::log('Reservas', 'Ingreso', "Ingreso registrado en la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function checkout(int $reservationId): void
    {
        require_admin();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation || $reservation['status'] !== 'active') {
                throw new ValidationException('El vehículo no se encuentra registrado dentro del estacionamiento.');
            }
            $expired = $reservation['ends_on'] < self::today();
            Reservation::update($reservationId, [
                'status' => $expired ? 'expired' : 'confirmed',
                'check_out_at' => date('Y-m-d H:i:s'),
            ]);
            Space::setStatus((int) $reservation['space_id'], $expired ? 'available' : 'reserved');
            NotificationService::send((int) $reservation['user_id'], 'reservations', 'Salida registrada', "Se registró la salida de tu reserva #{$reservationId}.", '/frontend/conductor/reservas.php');
            $detail = $expired ? 'Mensualidad vencida; plaza liberada.' : 'La plaza permanece reservada por la mensualidad.';
            AuditService::log('Reservas', 'Salida', "Salida registrada de la plaza {$reservation['space_code']}. {$detail}", 'Reservation', $reservationId, $reservation['space_code']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function closeExpired(int $reservationId): void
    {
        require_admin();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation || $reservation['status'] !== 'confirmed' || $reservation['ends_on'] >= self::today()) {
                throw new ValidationException('Solo se puede cerrar una mensualidad vencida con el vehículo fuera.');
            }
            if ($reservation['cancellation_status'] === 'pending') {
                throw new ValidationException('Primero resuelve la solicitud de cancelación.');
            }
            Reservation::update($reservationId, ['status' => 'expired']);
            Space::setStatus((int) $reservation['space_id'], 'available');
            AuditService::log('Reservas', 'Vencimiento', 'Mensualidad vencida cerrada; plaza liberada.', 'Reservation', $reservationId, $reservation['space_code']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function requestCancellation(int $reservationId, string $reason): void
    {
        $user = DriverContext::user();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id']) {
                AuditService::denied('cancellation_reservation_owner_required');
                throw new ValidationException('No puedes solicitar la cancelacion de esta reserva.');
            }
            if ($reservation['status'] !== 'confirmed') {
                throw new ValidationException('No puedes solicitar la cancelacion en el estado actual.');
            }
            if ($reservation['cancellation_status'] === 'pending') {
                throw new ValidationException('Ya existe una solicitud de cancelacion pendiente.');
            }

            $reason = clean_string($reason, 1000);
            if (mb_strlen($reason) < 5) {
                throw new ValidationException('Indica un motivo de al menos 5 caracteres.');
            }

            Reservation::update($reservationId, [
                'cancellation_status' => 'pending',
                'cancellation_reason' => $reason,
                'cancellation_requested_at' => date('Y-m-d H:i:s'),
                'cancellation_reviewed_at' => null,
                'cancellation_reviewed_by' => null,
                'cancellation_review_note' => null,
            ]);
            NotificationService::admins('pending_actions', 'Cancelación pendiente', "La reserva #{$reservationId} tiene una solicitud de cancelación para revisar.", '/frontend/admin/reservas.php', "cancellation:{$reservationId}:" . date('YmdHis'));
            AuditService::log('Reservas', 'Cancelacion solicitada', "Se solicito la cancelacion de la mensualidad de la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['reason' => $reason]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function approveCancellation(int $reservationId, ?string $note = null): void
    {
        $admin = require_admin();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation) {
                throw new ValidationException('Reserva no encontrada.');
            }
            if ($reservation['cancellation_status'] !== 'pending') {
                throw new ValidationException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
            }
            if ($reservation['status'] === 'active') {
                throw new ValidationException('No se puede aprobar la cancelacion mientras el vehiculo este dentro.');
            }

            $pending = db()->prepare("UPDATE payments SET status='rejected',updated_at=NOW() WHERE reservation_id=:id AND status='pending'");
            $pending->execute(['id' => $reservationId]);
            if ($reservation['payment_status'] === 'pending') {
                Reservation::update($reservationId, ['payment_status' => 'unpaid']);
            }
            Reservation::update($reservationId, [
                'status' => $reservation['status'] === 'expired' ? 'expired' : 'cancelled',
                'cancellation_status' => 'approved',
                'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
                'cancellation_reviewed_by' => (int) $admin['id'],
                'cancellation_review_note' => $note !== null ? clean_string($note, 1000) : null,
            ]);
            if ($reservation['status'] !== 'expired') {
                Space::setStatus((int) $reservation['space_id'], 'available');
            }
            NotificationService::send((int) $reservation['user_id'], 'pending_actions', 'Cancelación aprobada', "Se aprobó la cancelación de tu reserva #{$reservationId}.", '/frontend/conductor/reservas.php', "cancellation:{$reservationId}:approved:" . $reservation['cancellation_requested_at']);
            AuditService::log('Reservas', 'Cancelacion aprobada', "Cancelacion aprobada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function rejectCancellation(int $reservationId, string $note): void
    {
        $admin = require_admin();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $reservation = Reservation::find($reservationId, true);
            if (!$reservation) {
                throw new ValidationException('Reserva no encontrada.');
            }
            if ($reservation['cancellation_status'] !== 'pending') {
                throw new ValidationException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
            }

            $note = clean_string($note, 1000);
            if (mb_strlen($note) < 5) {
                throw new ValidationException('La nota debe tener al menos 5 caracteres.');
            }

            Reservation::update($reservationId, [
                'cancellation_status' => 'rejected',
                'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
                'cancellation_reviewed_by' => (int) $admin['id'],
                'cancellation_review_note' => $note,
            ]);
            NotificationService::send((int) $reservation['user_id'], 'pending_actions', 'Cancelación rechazada', "Se rechazó la cancelación de tu reserva #{$reservationId}. Consulta la nota de revisión en Mis reservas.", '/frontend/conductor/reservas.php', "cancellation:{$reservationId}:rejected:" . $reservation['cancellation_requested_at']);
            AuditService::log('Reservas', 'Cancelacion rechazada', "Cancelacion rechazada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['note' => $note]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
