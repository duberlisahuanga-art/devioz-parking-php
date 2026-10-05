<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['reservation_id'] ?? 0);

    run_post_action(function () use ($action, $id): void {
        if ($id < 1) throw new RuntimeException('Reserva inválida.');
        match ($action) {
            'checkin' => ReservationController::checkin($id),
            'checkout' => ReservationController::checkout($id),
            'approve_cancel' => ReservationController::approveCancellation($id, $_POST['note'] ?? null),
            'reject_cancel' => ReservationController::rejectCancellation($id, (string) ($_POST['note'] ?? '')),
            default => throw new RuntimeException('Acción no válida.'),
        };
    }, 'Operación realizada correctamente.', '/frontend/admin/reservas.php');
}

$reservations = ReservationController::index();
render_page_start('Reservas', 'reservas');
?>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Reservas mensuales</h2><p>Control de ingreso, salida y solicitudes de cancelación</p></div><span class="badge text-bg-primary"><?= count($reservations) ?> registros</span></div>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead><tr><th>#</th><th>Conductor</th><th>Plaza / vehículo</th><th>Periodo</th><th>Estado</th><th>Pago</th><th>Cancelación</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (!$reservations): ?>
                <tr><td colspan="8" class="text-center text-secondary py-5">No hay reservas registradas.</td></tr>
            <?php endif; ?>
            <?php foreach ($reservations as $r): ?>
                <tr>
                    <td>#<?= h($r['id']) ?></td>
                    <td><strong><?= h($r['user_name']) ?></strong><div class="small text-secondary"><?= h($r['user_email']) ?></div></td>
                    <td><strong><?= h($r['space_code']) ?></strong><div class="small text-secondary"><?= h($r['vehicle_plate']) ?></div></td>
                    <td><?= h(format_date($r['starts_on'])) ?><div class="small text-secondary">hasta <?= h(format_date($r['ends_on'])) ?></div></td>
                    <td><span class="badge <?= h(badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                    <td><span class="badge <?= h(badge_class($r['payment_status'])) ?>"><?= h(status_label($r['payment_status'])) ?></span><div class="small mt-1"><?= h(money($r['amount'])) ?></div></td>
                    <td>
                        <span class="badge <?= h(badge_class($r['cancellation_status'])) ?>"><?= h(status_label($r['cancellation_status'])) ?></span>
                        <?php if (!empty($r['cancellation_reason'])): ?><div class="small text-secondary mt-1"><?= h($r['cancellation_reason']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($r['status'] === 'confirmed' && $r['cancellation_status'] !== 'pending'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-success">Ingreso</button></form>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'active'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-outline-danger">Salida</button></form>
                            <?php endif; ?>
                            <?php if ($r['cancellation_status'] === 'pending' && $r['status'] !== 'active'): ?>
                                <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><input class="form-control form-control-sm" name="note" placeholder="Nota"><button name="action" value="approve_cancel" class="btn btn-sm btn-success">Aprobar</button><button name="action" value="reject_cancel" class="btn btn-sm btn-danger">Rechazar</button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_page_end(); ?>
