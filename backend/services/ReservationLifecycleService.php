<?php

declare(strict_types=1);

final class ReservationLifecycleService
{
    public static function run(int $limit = 500): array
    {
        $pdo = db();
        if ($pdo->inTransaction()) {
            throw new LogicException('El mantenimiento debe ejecutarse fuera de una transacción.');
        }
        $lock = 'devioz_lifecycle_' . sha1((string) $pdo->query('SELECT DATABASE()')->fetchColumn());
        $stmt = $pdo->prepare('SELECT GET_LOCK(:name, 0)');
        $stmt->execute(['name' => $lock]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return ['closed' => 0, 'busy' => true];
        }
        $closed = 0;
        try {
            $today = ReservationController::today();
            $query = $pdo->prepare(
                "SELECT id FROM reservations WHERE status='confirmed' AND ends_on < :today
                 ORDER BY ends_on, id LIMIT :limit"
            );
            $query->bindValue('today', $today);
            $query->bindValue('limit', max(1, min(2000, $limit)), PDO::PARAM_INT);
            $query->execute();
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $closed += transaction(function () use ($id, $today): int {
                    $r = Reservation::find((int) $id, true);
                    if (!$r || $r['status'] !== 'confirmed' || $r['ends_on'] >= $today) {
                        return 0;
                    }
                    Reservation::update((int) $id, ['status' => 'expired']);
                    $other = db()->prepare(
                        "SELECT id FROM reservations WHERE space_id=:space AND id<>:id
                         AND status IN ('confirmed','active') LIMIT 1 FOR UPDATE"
                    );
                    $other->execute(['space' => $r['space_id'], 'id' => $id]);
                    $hasOther = (bool) $other->fetchColumn();
                    $space = Space::find((int) $r['space_id']);
                    // Never free an occupied/maintenance space or one assigned to another live reservation.
                    if (!$hasOther && $space && $space['status'] === 'reserved') {
                        Space::setStatus((int) $r['space_id'], 'available');
                    }
                    AuditService::log(
                        'Reservas',
                        'Vencimiento automático',
                        'Mensualidad cerrada por vencimiento; vehículo fuera.',
                        'Reservation',
                        (int) $id,
                        $r['space_code'],
                        ['automatic' => true]
                    );
                    self::notify(
                        $r,
                        'closed',
                        'Mensualidad finalizada',
                        'Tu mensualidad terminó. Puedes renovar si la plaza sigue disponible.'
                    );
                    return 1;
                });
            }
            // Reminders are deduplicated by event_key, even when the job runs every minute.
            $until = (new DateTimeImmutable($today))->modify('+3 days')->format('Y-m-d');
            $query = $pdo->prepare(
                "SELECT r.*, s.code AS space_code FROM reservations r JOIN spaces s ON s.id=r.space_id
                 WHERE r.status IN ('confirmed','active') AND r.ends_on<=:until"
            );
            $query->execute(['until' => $until]);
            foreach ($query->fetchAll() as $r) {
                if ($r['ends_on'] < $today && $r['status'] === 'active') {
                    self::notify(
                        $r,
                        'inside-expired',
                        'Mensualidad vencida · Pendiente de salida',
                        'La mensualidad venció y el vehículo sigue dentro. La plaza se libera al registrar su salida.'
                    );
                } elseif ($r['ends_on'] >= $today) {
                    self::notify(
                        $r,
                        'reminder',
                        'Tu mensualidad está por vencer',
                        'La mensualidad de la plaza ' . $r['space_code'] . ' termina el '
                        . (new DateTimeImmutable($r['ends_on']))->format('d/m/Y') . '.'
                    );
                }
            }
            return ['closed' => $closed, 'busy' => false];
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lock]);
        }
    }

    private static function notify(array $r, string $event, string $title, string $message): void
    {
        $key = 'lifecycle:' . $r['id'] . ':' . $event . ':' . $r['ends_on'];
        NotificationService::send(
            (int) $r['user_id'],
            'reservations',
            $title,
            $message,
            '/frontend/conductor/reservas.php',
            $key
        );
        NotificationService::admins(
            'reservations',
            $title,
            'Reserva #' . $r['id'] . ' · Plaza ' . $r['space_code'] . '. ' . $message,
            '/frontend/admin/reservas.php',
            $key
        );
    }
}
