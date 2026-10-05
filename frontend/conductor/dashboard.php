<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();
$data = AdminController::dashboardForCurrentUser();
$user = current_user();
render_page_start('Mi dashboard', 'dashboard');
?>
<div class="welcome-banner mb-4"><div><p class="eyebrow mb-1">Hola, <?= h($user['name']) ?></p><h2>Tu estacionamiento, en un solo lugar</h2><p class="mb-0">Gestiona tu mensualidad, vehículo y pago Yape.</p></div><a class="btn btn-light" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>"><i class="bi bi-plus-circle me-1"></i>Reservar plaza</a></div>
<div class="row g-3 mb-4"><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-calendar-check"></i></div><div><span>Reservas activas</span><strong><?= h($data['active_reservations']) ?></strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-exclamation-circle"></i></div><div><span>Por pagar</span><strong><?= h($data['unpaid_reservations']) ?></strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-grid"></i></div><div><span>Disponibilidad</span><strong><?= h($data['availability']) ?>%</strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-wallet2"></i></div><div><span>Pagado</span><strong><?= h($data['balance']) ?></strong></div></div></div></div>
<div class="panel-card"><div class="panel-heading"><div><h2>Estado de las plazas</h2><p>Consulta visual del estacionamiento</p></div></div><div class="parking-map"><?php foreach ($data['spaces'] as $space): ?><div class="parking-space status-<?= h($space['status']) ?>"><i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span></div><?php endforeach; ?></div><div class="map-legend mt-3"><span><i class="legend-dot available"></i>Disponible</span><span><i class="legend-dot reserved"></i>Reservada</span><span><i class="legend-dot occupied"></i>Ocupada</span></div></div>
<?php render_page_end(); ?>
