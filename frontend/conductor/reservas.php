<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['reservation_id'] ?? 0);
    $reason = (string) ($_POST['reason'] ?? '');
    run_post_action(function () use ($id, $reason): void { ReservationController::requestCancellation($id, $reason); }, 'Solicitud de cancelación enviada.', '/frontend/conductor/reservas.php');
}

$reservations = ReservationController::index();
render_page_start('Mis reservas', 'reservas');
?>
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4"><div><h2 class="h5 mb-1">Mensualidades</h2><p class="text-secondary mb-0">Cada reserva cubre un mes de estacionamiento.</p></div><a class="btn btn-primary" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>"><i class="bi bi-plus-circle me-1"></i>Nueva reserva</a></div>
<div class="row g-3">
<?php if (!$reservations): ?><div class="col-12"><div class="panel-card text-center py-5"><i class="bi bi-calendar-x display-5 text-secondary"></i><h3 class="h5 mt-3">Aún no tienes reservas</h3><a class="btn btn-primary mt-2" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>">Reservar ahora</a></div></div><?php endif; ?>
<?php foreach ($reservations as $r): ?><div class="col-xl-6"><div class="panel-card h-100"><div class="d-flex justify-content-between gap-3"><div><span class="small text-secondary">Reserva #<?= h($r['id']) ?></span><h3 class="h5 mb-1">Plaza <?= h($r['space_code']) ?></h3><div class="text-secondary"><?= h($r['vehicle_plate']) ?> · <?= h(trim(($r['vehicle_brand'] ?? '').' '.($r['vehicle_model'] ?? ''))) ?></div></div><span class="badge <?= h(badge_class($r['status'])) ?> align-self-start"><?= h(status_label($r['status'])) ?></span></div><hr><div class="reservation-grid"><div><span>Inicio</span><strong><?= h(format_date($r['starts_on'])) ?></strong></div><div><span>Fin</span><strong><?= h(format_date($r['ends_on'])) ?></strong></div><div><span>Monto</span><strong><?= h(money($r['amount'])) ?></strong></div><div><span>Pago</span><strong><?= h(status_label($r['payment_status'])) ?></strong></div></div>
    <div class="d-flex flex-wrap gap-2 mt-3"><?php if ($r['payment_status']==='unpaid' && in_array($r['status'],['confirmed','active'],true)): ?><a class="btn btn-sm btn-primary" href="<?= h(app_url('/frontend/conductor/pagos.php?reservation_id=' . $r['id'])) ?>">Pagar con Yape</a><?php endif; ?><?php if ($r['status']==='confirmed' && $r['cancellation_status']!=='pending'): ?><button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-<?= h($r['id']) ?>">Solicitar cancelación</button><?php endif; ?></div>
    <?php if ($r['cancellation_status']==='pending'): ?><div class="alert alert-warning mt-3 mb-0">Solicitud de cancelación pendiente de revisión.</div><?php elseif ($r['cancellation_status']==='rejected'): ?><div class="alert alert-secondary mt-3 mb-0">La solicitud fue rechazada. <?= h($r['cancellation_review_note'] ?? '') ?></div><?php endif; ?>
    <div class="collapse mt-3" id="cancel-<?= h($r['id']) ?>"><form method="post" class="border rounded p-3 bg-light"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><label class="form-label">Motivo</label><textarea class="form-control mb-2" name="reason" minlength="5" required placeholder="Indica por qué deseas cancelar"></textarea><button class="btn btn-danger btn-sm">Enviar solicitud</button></form></div>
</div></div><?php endforeach; ?>
</div>
<?php render_page_end(); ?>
