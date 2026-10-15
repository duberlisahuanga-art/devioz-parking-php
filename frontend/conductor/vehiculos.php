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
<link rel="stylesheet" href="<?= h(app_url('/frontend/assets/css/vehicle-catalog.css')) ?>">
<div class="row g-4"><div class="col-xl-4"><div class="panel-card"><div class="panel-heading"><div><h2>Registrar vehículo</h2><p>Agrega un vehículo a tu cuenta</p></div></div><form method="post" class="vstack gap-3" data-vehicle-catalog="<?= h(app_url('/frontend/api/vehicle-catalog.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div>
        <label class="form-label" for="vehicle-type">Tipo</label>
        <select class="form-select" id="vehicle-type" name="type" aria-describedby="vehicle-catalog-hint">
            <option value="car">Auto</option><option value="moto">Moto</option>
        </select>
        <p class="vehicle-catalog-help mb-0" id="vehicle-catalog-hint">Marcas frecuentes en Perú primero. Selecciona la marca y su modelo; puedes buscar otras marcas escribiendo.</p>
    </div>
    <div>
        <label class="form-label" for="vehicle-brand">Marca</label>
        <div class="vehicle-autocomplete">
            <input class="form-control" id="vehicle-brand" name="brand" required placeholder="Toyota" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="vehicle-brand-list" aria-describedby="vehicle-brand-status">
            <ul class="vehicle-autocomplete-list" id="vehicle-brand-list" role="listbox" aria-label="Marcas sugeridas" hidden></ul>
        </div>
        <div class="vehicle-catalog-help" id="vehicle-brand-status" role="status" aria-live="polite"></div>
    </div>
    <div>
        <label class="form-label" for="vehicle-model">Modelo</label>
        <div class="vehicle-autocomplete">
            <input class="form-control" id="vehicle-model" name="model" required placeholder="Corolla" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="vehicle-model-list" aria-describedby="vehicle-model-hint vehicle-model-status">
            <ul class="vehicle-autocomplete-list" id="vehicle-model-list" role="listbox" aria-label="Modelos sugeridos" hidden></ul>
        </div>
        <div class="vehicle-catalog-help" id="vehicle-model-hint">Selecciona primero una marca para ver sus modelos.</div>
        <div class="vehicle-catalog-help" id="vehicle-model-status" role="status" aria-live="polite"></div>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="catalog_manual" value="1" id="vehicle-catalog-manual" aria-describedby="vehicle-manual-help">
        <label class="form-check-label" for="vehicle-catalog-manual">Mi vehículo no aparece en el catálogo</label>
        <p class="vehicle-catalog-help mb-0" id="vehicle-manual-help">Permite indicar una marca o modelo no listado. No permite combinar marcas con modelos de otra marca. Para motos no es necesario activarlo.</p>
    </div>
    <div><label class="form-label" for="vehicle-color">Color</label><input class="form-control" id="vehicle-color" name="color" placeholder="Blanco" maxlength="50"></div>
    <div><label class="form-label" for="vehicle-plate">Placa</label><input class="form-control text-uppercase" id="vehicle-plate" name="plate" required maxlength="7" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)?" placeholder="F3V-802" aria-describedby="vehicle-plate-help"><p class="vehicle-catalog-help mb-0" id="vehicle-plate-help">Letras y números, hasta 7 caracteres. Puedes incluir un guion.</p></div>
    <button class="btn btn-primary">Registrar vehículo</button>
</form></div></div><div class="col-xl-8"><div class="row g-3"><?php if (!$vehicles): ?><div class="col-12"><div class="panel-card text-center py-5 text-secondary">No tienes vehículos registrados.</div></div><?php endif; ?><?php foreach ($vehicles as $v): ?><div class="col-md-6"><div class="vehicle-card"><div class="vehicle-visual" aria-hidden="true">
    <?php if ($v['type'] === 'moto'): ?>
        <svg class="vehicle-motorcycle-icon" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
            <circle cx="5" cy="17" r="4"/>
            <circle cx="19" cy="17" r="4"/>
            <path d="M19 17 15.5 6H12M14 6h3M3 9h5l3 7H5l4-6h6M11 16h3l3-5"/>
            <path d="M9 9h4l2 3h-5z" fill="currentColor" stroke="none"/>
        </svg>
    <?php else: ?>
        <i class="bi bi-car-front-fill"></i>
    <?php endif; ?>
</div><div class="flex-grow-1"><h3 class="h5 mb-1"><?= h($v['plate']) ?></h3><div class="text-secondary small"><?= h(trim(($v['brand'] ?? '').' '.($v['model'] ?? '')) ?: 'Sin marca/modelo') ?></div><div class="small mt-2"><span class="me-3"><i class="bi bi-palette me-1"></i><?= h($v['color'] ?: '—') ?></span><span><i class="bi bi-tag me-1"></i><?= h($v['type']) ?></span></div></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" value="<?= h($v['id']) ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este vehículo?')"><i class="bi bi-trash"></i></button></form></div></div><?php endforeach; ?></div></div></div>
<script src="<?= h(app_url('/frontend/assets/js/vehicle-catalog.js')) ?>" defer></script>
<?php render_page_end(); ?>
