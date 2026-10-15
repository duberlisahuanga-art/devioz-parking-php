<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$returnQuery = ReservationFilters::query(ReservationFilters::fromInput($_GET));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['reservation_id'] ?? 0);

    run_post_action(function () use ($action, $id): void {
        if ($id < 1) throw new RuntimeException('Reserva inválida.');
        match ($action) {
            'checkin' => ReservationController::checkin($id),
            'checkout' => ReservationController::checkout($id),
            'close_expired' => ReservationController::closeExpired($id),
            'approve_cancel' => ReservationController::approveCancellation($id, $_POST['note'] ?? null),
            'reject_cancel' => ReservationController::rejectCancellation($id, (string) ($_POST['note'] ?? '')),
            default => throw new RuntimeException('Acción no válida.'),
        };
    }, 'Operación realizada correctamente.', '/frontend/admin/reservas.php?' . $returnQuery);
}

$data = ReservationController::adminData($_GET);
$filters = $data['filters'];
$reservations = $data['reservations'];
parse_str(ReservationFilters::query($filters), $_GET);
$pageHasNext = count($reservations) > 50;
$reservations = array_slice($reservations,0,50);
render_page_start('Reservas', 'reservas');
?>
<p class="small text-secondary" data-live-status role="status">Actualización automática cada 30 segundos.</p>
<nav class="d-flex gap-2 mb-3" aria-label="Estado de las reservas">
    <a class="btn btn-outline-primary" href="?<?= h(ReservationFilters::query($filters, 'current')) ?>" <?= $filters['view'] === 'current' ? 'aria-current="page"' : '' ?>>En curso</a>
    <a class="btn btn-outline-primary" href="?<?= h(ReservationFilters::query($filters, 'history')) ?>" <?= $filters['view'] === 'history' ? 'aria-current="page"' : '' ?>>Historial de mensualidades</a>
</nav>
<?php require __DIR__ . '/../includes/reservation-filters.php'; ?>
<div data-live-reservations>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Reservas mensuales</h2><p>Control de ingreso, salida y solicitudes de cancelación</p></div><span class="badge text-bg-primary" data-reservation-count><?= h($data['total']) ?> <?= $data['total'] === 1 ? 'coincidencia' : 'coincidencias' ?></span></div>
    <p class="small text-secondary">Las mensualidades vencidas se cierran automáticamente si el vehículo está fuera. Si sigue dentro, registra su salida para liberar la plaza. El ingreso requiere periodo vigente y pago aprobado.</p>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead><tr><th>#</th><th>Conductor</th><th>Plaza / vehículo</th><th>Periodo</th><th>Estado</th><th>Pago</th><th>Cancelación</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (!$reservations): ?>
                <tr><td colspan="8" class="text-center text-secondary py-5"><?= $filters['errors'] ? 'Corrige los filtros para consultar las reservas.' : 'No se encontraron reservas con estos filtros.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($reservations as $r): ?>
                <?php
                $entryReason = ReservationController::entryBlockReason($r);
                $periodExpired = $r['ends_on'] < ReservationController::today();
                $reservationLabel = match ($r['status']) {
                    'confirmed' => $periodExpired ? 'Periodo vencido · Vehículo fuera' : 'Plaza reservada · Vehículo fuera',
                    'active' => $periodExpired ? 'Mensualidad vencida · Pendiente de salida' : 'Vehículo dentro',
                    'expired' => 'Mensualidad finalizada',
                    default => status_label($r['status']),
                };
                ?>
                <tr>
                    <td>#<?= h($r['id']) ?></td>
                    <td><strong><?= h($r['user_name']) ?></strong><div class="small text-secondary"><?= h($r['user_email']) ?></div></td>
                    <td><strong><?= h($r['space_code']) ?></strong><div class="small text-secondary"><?= h($r['vehicle_plate']) ?> · <?= ($r['vehicle_type'] ?? 'car') === 'moto' ? 'Moto' : 'Auto' ?></div></td>
                    <td><?= h(format_date($r['starts_on'])) ?><div class="small text-secondary">hasta <?= h(format_date($r['ends_on'])) ?></div></td>
                    <td><span class="badge <?= h(badge_class($r['status'])) ?>"><?= h($reservationLabel) ?></span></td>
                    <td><span class="badge <?= h(badge_class($r['payment_status'])) ?>"><?= h(status_label($r['payment_status'])) ?></span><div class="small mt-1"><?= h(money($r['amount'])) ?></div></td>
                    <td>
                        <span class="badge <?= h(badge_class($r['cancellation_status'])) ?>"><?= h(status_label($r['cancellation_status'])) ?></span>
                        <?php if (!empty($r['cancellation_reason'])): ?><div class="small text-secondary mt-1"><?= h($r['cancellation_reason']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($r['status'] === 'confirmed'): ?>
                                <?php if ($entryReason === null): ?>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-success">Registrar ingreso</button></form>
                                <?php else: ?>
                                    <span class="small text-secondary"><?= h($entryReason) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'active'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-outline-danger">Registrar salida</button></form>
                                <span class="small text-secondary"><?= $periodExpired ? 'Al registrar la salida se libera la plaza porque la mensualidad venció.' : 'La salida conserva la plaza reservada durante la mensualidad.' ?></span>
                            <?php endif; ?>
                            <?php if ($r['cancellation_status'] === 'pending' && $r['status'] !== 'active'): ?>
                                <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><input class="form-control form-control-sm" name="note" placeholder="Nota"><button name="action" value="approve_cancel" class="btn btn-sm btn-success">Aprobar</button><button name="action" value="reject_cancel" class="btn btn-sm btn-danger">Rechazar</button></form>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'expired'): ?><span class="small text-secondary">Sin nuevos ingresos. Conservada en el historial.</span><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_pagination($pageHasNext); ?>
</div>
<script src="<?= h(app_url('/frontend/assets/js/parking-live.js')) ?>" defer></script>
<?php render_page_end(); ?>
