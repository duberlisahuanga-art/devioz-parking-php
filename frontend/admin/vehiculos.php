<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$sql = "SELECT v.*, u.name AS user_name, u.email AS user_email,
        (SELECT COUNT(*) FROM reservations r WHERE r.vehicle_id=v.id AND r.status IN ('confirmed','active')) AS open_reservations
        FROM vehicles v JOIN users u ON u.id=v.user_id ORDER BY v.created_at DESC, v.id DESC";
$vehicles = db()->query($sql)->fetchAll();

render_page_start('Vehículos', 'vehiculos');
?>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Vehículos registrados</h2><p>Listado general de vehículos de los conductores</p></div><span class="badge text-bg-primary"><?= count($vehicles) ?></span></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Placa</th><th>Marca / modelo</th><th>Color</th><th>Tipo</th><th>Conductor</th><th>Reserva vigente</th></tr></thead><tbody>
        <?php if (!$vehicles): ?><tr><td colspan="6" class="text-center text-secondary py-5">No hay vehículos registrados.</td></tr><?php endif; ?>
        <?php foreach ($vehicles as $v): ?><tr><td><strong><?= h($v['plate']) ?></strong></td><td><?= h(trim(($v['brand'] ?? '') . ' ' . ($v['model'] ?? '')) ?: '—') ?></td><td><?= h($v['color'] ?: '—') ?></td><td><?= h($v['type']) ?></td><td><?= h($v['user_name']) ?><div class="small text-secondary"><?= h($v['user_email']) ?></div></td><td><?= (int)$v['open_reservations'] > 0 ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
