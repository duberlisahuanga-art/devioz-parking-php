<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $cameraId = (int) ($_POST['camera_id'] ?? 0);
    run_post_action(function () use ($action, $cameraId): void {
        if ($action === 'create') CameraController::store($_POST);
        elseif ($action === 'update') CameraController::update($cameraId, $_POST);
        elseif ($action === 'delete') CameraController::destroy($cameraId);
        else throw new RuntimeException('Acción no válida.');
    }, 'Cámara actualizada correctamente.', '/frontend/admin/camaras.php');
}

$cameras = CameraController::index();
$parkings = Parking::allWithStats();
render_page_start('Cámaras', 'camaras');
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="panel-card sticky-xl-top" style="top:1rem">
            <div class="panel-heading"><div><h2>Nueva cámara</h2><p>Solo videovigilancia</p></div></div>
            <form method="post" class="vstack gap-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="create">
                <div><label class="form-label">Estacionamiento</label><select class="form-select" name="parking_id" required><?php foreach ($parkings as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label">Nombre</label><input class="form-control" name="name" required placeholder="Cámara Entrada"></div>
                <div><label class="form-label">Cobertura</label><input class="form-control" name="coverage" placeholder="Entrada principal"></div>
                <div><label class="form-label">URL de transmisión</label><input class="form-control" name="stream_url" placeholder="https://... o rtsp://..."></div>
                <div><label class="form-label">Estado</label><select class="form-select" name="status"><option value="active">Activa</option><option value="inactive">Inactiva</option></select></div>
                <button class="btn btn-primary">Registrar cámara</button>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="row g-3">
            <?php foreach ($cameras as $c): ?>
                <div class="col-md-6"><div class="panel-card h-100"><div class="d-flex justify-content-between gap-2"><div><div class="camera-icon"><i class="bi bi-camera-video"></i></div><h3 class="h5 mt-3 mb-1"><?= h($c['name']) ?></h3><p class="text-secondary small mb-2"><?= h($c['coverage'] ?: 'Sin cobertura indicada') ?></p></div><span class="badge <?= h(badge_class($c['status'])) ?> align-self-start"><?= h(status_label($c['status'])) ?></span></div>
                    <?php if ($c['stream_url']): ?><a class="small" target="_blank" href="<?= h($c['stream_url']) ?>">Abrir transmisión <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                    <hr><form method="post" class="vstack gap-2"><?= csrf_field() ?><input type="hidden" name="camera_id" value="<?= h($c['id']) ?>"><input type="hidden" name="parking_id" value="<?= h($c['parking_id']) ?>"><input class="form-control form-control-sm" name="name" value="<?= h($c['name']) ?>"><input class="form-control form-control-sm" name="coverage" value="<?= h($c['coverage']) ?>"><input class="form-control form-control-sm" name="stream_url" value="<?= h($c['stream_url']) ?>"><select class="form-select form-select-sm" name="status"><option value="active" <?= $c['status']==='active'?'selected':'' ?>>Activa</option><option value="inactive" <?= $c['status']==='inactive'?'selected':'' ?>>Inactiva</option></select><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-primary" name="action" value="update">Guardar</button><button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('¿Eliminar esta cámara?')">Eliminar</button></div></form>
                </div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php render_page_end(); ?>
