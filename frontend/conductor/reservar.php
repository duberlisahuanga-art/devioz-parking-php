<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    run_post_action(function (): void { ReservationController::store($_POST); }, 'Reserva mensual creada correctamente.', '/frontend/conductor/reservas.php');
}

$data = ReservationController::createData();
$available = array_values(array_filter($data['spaces'], fn(array $s): bool => $s['status'] === 'available'));
render_page_start('Reservar plaza', 'reservar');
?>
<div class="row g-4">
    <div class="col-xl-5"><div class="panel-card"><div class="panel-heading"><div><h2>Nueva mensualidad</h2><p><?= h($data['parking']['name']) ?></p></div><span class="price-pill"><?= h(money($data['monthly_price'])) ?>/mes</span></div>
        <?php if (!$data['vehicles']): ?><div class="alert alert-warning">Primero registra un vehículo en <a href="<?= h(app_url('/frontend/conductor/vehiculos.php')) ?>">Mis vehículos</a>.</div><?php elseif (!$available): ?><div class="alert alert-warning">En este momento no hay plazas disponibles.</div><?php else: ?>
        <form method="post" class="vstack gap-3"><?= csrf_field() ?><div><label class="form-label">Vehículo</label><select class="form-select" name="vehicle_id" required><?php foreach ($data['vehicles'] as $v): ?><option value="<?= h($v['id']) ?>"><?= h($v['plate']) ?> · <?= h(trim(($v['brand'] ?? '').' '.($v['model'] ?? ''))) ?></option><?php endforeach; ?></select></div><div><label class="form-label">Fecha de inicio</label><input class="form-control" type="date" name="starts_on" min="<?= h(date('Y-m-d')) ?>" value="<?= h(date('Y-m-d')) ?>" required></div><div><label class="form-label">Plaza</label><select class="form-select" name="space_id" required><?php foreach ($available as $s): ?><option value="<?= h($s['id']) ?>"><?= h($s['code']) ?> · <?= h(ucfirst($s['type'])) ?></option><?php endforeach; ?></select></div><div class="reservation-summary"><div><span>Plan</span><strong>Mensual</strong></div><div><span>Precio</span><strong><?= h(money($data['monthly_price'])) ?></strong></div><div><span>Pago</span><strong>Yape</strong></div></div><button class="btn btn-primary btn-lg">Crear reserva</button></form>
        <?php endif; ?>
    </div>
    <div class="col-xl-7"><div class="panel-card"><div class="panel-heading"><div><h2>Plazas disponibles</h2><p>Selecciona una plaza libre</p></div><span class="badge text-bg-success"><?= count($available) ?> libres</span></div><div class="parking-map"><?php foreach ($data['spaces'] as $space): ?><div class="parking-space status-<?= h($space['status']) ?>"><i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span></div><?php endforeach; ?></div></div></div>
</div>
<?php render_page_end(); ?>
