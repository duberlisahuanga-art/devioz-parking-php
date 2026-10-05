<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$data = AdminController::spaces();
$spaces = $data['spaces'];
$summary = $data['summary'];
render_page_start('Espacios', 'espacios');
?>
<div class="row g-3 mb-4">
    <?php foreach ([['Total','total'],['Disponibles','available'],['Reservadas','reserved'],['Ocupadas','occupied'],['Mantenimiento','maintenance']] as [$label,$key]): ?>
        <div class="col-6 col-lg"><div class="mini-stat"><span><?= h($label) ?></span><strong><?= h($summary[$key] ?? 0) ?></strong></div></div>
    <?php endforeach; ?>
</div>
<div class="panel-card mb-4">
    <div class="panel-heading"><div><h2>Croquis del estacionamiento</h2><p>Una sola cochera · 40 espacios · 3 filas</p></div></div>
    <div class="parking-map parking-map-large">
        <?php foreach ($spaces as $space): ?>
            <div class="parking-space status-<?= h($space['status']) ?>">
                <i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span>
                <?php if ($space['vehicle_plate']): ?><small><?= h($space['vehicle_plate']) ?></small><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="map-legend mt-3"><span><i class="legend-dot available"></i>Disponible</span><span><i class="legend-dot reserved"></i>Reservada</span><span><i class="legend-dot occupied"></i>Ocupada</span><span><i class="legend-dot maintenance"></i>Mantenimiento</span></div>
</div>
<div class="panel-card">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Plaza</th><th>Tipo</th><th>Estado</th><th>Conductor</th><th>Vehículo</th></tr></thead><tbody>
        <?php foreach ($spaces as $s): ?><tr><td><strong><?= h($s['code']) ?></strong></td><td><?= h($s['type']) ?></td><td><span class="badge <?= h(badge_class($s['status'])) ?>"><?= h(status_label($s['status'])) ?></span></td><td><?= h($s['driver_name'] ?: '—') ?></td><td><?= h($s['vehicle_plate'] ?: '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
