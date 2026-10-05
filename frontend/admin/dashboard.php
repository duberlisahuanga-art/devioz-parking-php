<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$data = AdminController::overview();
$metrics = $data['metrics'];
$summary = $data['spaces'];
$spaces = Space::allDetailed();

render_page_start('Dashboard administrativo', 'dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-grid-3x3-gap"></i></div><div><span>Plazas totales</span><strong><?= h($metrics['total_spaces']) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-car-front"></i></div><div><span>Ocupadas</span><strong><?= h($summary['occupied'] ?? 0) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-calendar-check"></i></div><div><span>Reservas activas</span><strong><?= h($metrics['active_reservations']) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-percent"></i></div><div><span>Disponibilidad</span><strong><?= h($metrics['availability']) ?>%</strong></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel-card">
            <div class="panel-heading">
                <div><h2>Mapa de espacios</h2><p>Vista operativa de las 40 plazas</p></div>
                <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/frontend/admin/espacios.php')) ?>">Ver detalle</a>
            </div>
            <div class="parking-map">
                <?php foreach ($spaces as $space): ?>
                    <div class="parking-space status-<?= h($space['status']) ?>" title="<?= h($space['code']) ?> · <?= h(status_label($space['status'])) ?>">
                        <i class="bi bi-car-front-fill"></i>
                        <span><?= h($space['code']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="map-legend mt-3">
                <span><i class="legend-dot available"></i>Disponible</span>
                <span><i class="legend-dot reserved"></i>Reservada</span>
                <span><i class="legend-dot occupied"></i>Ocupada</span>
                <span><i class="legend-dot maintenance"></i>Mantenimiento</span>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-heading"><div><h2>Resumen</h2><p>Estado actual del estacionamiento</p></div></div>
            <div class="summary-list">
                <div><span>Disponibles</span><strong><?= h($summary['available'] ?? 0) ?></strong></div>
                <div><span>Reservadas</span><strong><?= h($summary['reserved'] ?? 0) ?></strong></div>
                <div><span>Ocupadas</span><strong><?= h($summary['occupied'] ?? 0) ?></strong></div>
                <div><span>Mantenimiento</span><strong><?= h($summary['maintenance'] ?? 0) ?></strong></div>
            </div>
            <hr>
            <a class="btn btn-primary w-100" href="<?= h(app_url('/frontend/admin/reservas.php')) ?>">Gestionar reservas</a>
        </div>
    </div>
</div>
<?php render_page_end(); ?>
