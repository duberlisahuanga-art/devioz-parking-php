<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    run_post_action(function () use ($action): void {
        if ($action === 'create') VehicleController::store($_POST);
        elseif ($action === 'delete') VehicleController::destroy((int) ($_POST['vehicle_id'] ?? 0));
        else throw new RuntimeException('Acción no válida.');
    }, $action === 'delete' ? 'Vehículo eliminado.' : 'Vehículo registrado.', '/frontend/conductor/vehiculos.php');
}

$vehicles = VehicleController::index();
render_page_start('Mis vehículos', 'vehiculos');
?>
<div class="row g-4"><div class="col-xl-4"><div class="panel-card"><div class="panel-heading"><div><h2>Registrar vehículo</h2><p>Agrega un vehículo a tu cuenta</p></div></div><form method="post" class="vstack gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div><label class="form-label">Placa</label><input class="form-control text-uppercase" name="plate" required placeholder="ABC-123"></div><div><label class="form-label">Marca</label><input class="form-control" name="brand" placeholder="Toyota"></div><div><label class="form-label">Modelo</label><input class="form-control" name="model" placeholder="Corolla"></div><div><label class="form-label">Color</label><input class="form-control" name="color" placeholder="Blanco"></div><div><label class="form-label">Tipo</label><select class="form-select" name="type"><option value="car">Auto</option><option value="moto">Moto</option></select></div><button class="btn btn-primary">Registrar vehículo</button></form></div></div><div class="col-xl-8"><div class="row g-3"><?php if (!$vehicles): ?><div class="col-12"><div class="panel-card text-center py-5 text-secondary">No tienes vehículos registrados.</div></div><?php endif; ?><?php foreach ($vehicles as $v): ?><div class="col-md-6"><div class="vehicle-card"><div class="vehicle-visual"><i class="bi bi-car-front-fill"></i></div><div class="flex-grow-1"><h3 class="h5 mb-1"><?= h($v['plate']) ?></h3><div class="text-secondary small"><?= h(trim(($v['brand'] ?? '').' '.($v['model'] ?? '')) ?: 'Sin marca/modelo') ?></div><div class="small mt-2"><span class="me-3"><i class="bi bi-palette me-1"></i><?= h($v['color'] ?: '—') ?></span><span><i class="bi bi-tag me-1"></i><?= h($v['type']) ?></span></div></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" value="<?= h($v['id']) ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este vehículo?')"><i class="bi bi-trash"></i></button></form></div></div><?php endforeach; ?></div></div></div>
<?php render_page_end(); ?>
