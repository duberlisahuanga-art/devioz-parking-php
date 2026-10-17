<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$error = null;
$logs = [];
try {
    $logs = AdminController::audit(AuditLog::filters($_GET));
} catch (ValidationException $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
}
$pageHasNext = count($logs) > 50;
$logs = array_slice($logs,0,50);
$advancedOpen = false;
foreach (['user_id', 'entity_type', 'entity_id'] as $key) {
    $advancedOpen = $advancedOpen || (is_string($_GET[$key] ?? null) && trim($_GET[$key]) !== '');
}
$fieldLabels = ['name'=>'Nombre', 'coverage'=>'Cobertura', 'location'=>'Ubicación', 'status'=>'Estado',
    'channel_number'=>'Canal', 'brightness'=>'Brillo', 'contrast'=>'Contraste', 'saturation'=>'Saturación',
    'category'=>'Categoría', 'concept'=>'Concepto', 'amount'=>'Monto', 'expense_datetime'=>'Fecha del egreso',
    'payment_method'=>'Medio de pago', 'description'=>'Descripción', 'other_detail'=>'Otro detalle'];
render_page_start('Auditoría', 'auditoria');
?>
<div class="panel-card">
    <p class="text-secondary">Consulta quién realizó una acción y cuándo. Las fechas corresponden a Lima, Perú.</p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form class="row g-3 mb-4" method="get" aria-label="Filtros de auditoría">
        <?php foreach (['search'=>'Usuario o descripción', 'action'=>'Acción', 'module'=>'Módulo',
            'from'=>'Desde', 'to'=>'Hasta'] as $key => $label): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="audit-<?= h($key) ?>"><?= h($label) ?></label>
            <input class="form-control" id="audit-<?= h($key) ?>" name="<?= h($key) ?>"
                type="<?= in_array($key, ['from','to'], true) ? 'date' : 'text' ?>"
                value="<?= h(is_string($_GET[$key] ?? null) ? $_GET[$key] : '') ?>">
        </div>
        <?php endforeach; ?>
        <div class="col-12">
            <details data-audit-filters <?= $advancedOpen ? 'open' : '' ?>>
                <summary>Filtros avanzados</summary>
                <p class="small text-secondary mt-2">Úsalos para buscar un registro por su identificador. El usuario corresponde al momento de la acción.</p>
                <div class="row g-3">
                    <?php foreach (['user_id'=>'Número de usuario', 'entity_type'=>'Tipo de registro', 'entity_id'=>'Número de registro'] as $key=>$label): ?>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="audit-<?= h($key) ?>"><?= h($label) ?></label>
                        <input class="form-control" id="audit-<?= h($key) ?>" name="<?= h($key) ?>" type="<?= $key === 'entity_type' ? 'text' : 'number' ?>"
                            <?= $key === 'entity_type' ? '' : 'min="1" step="1"' ?>
                            value="<?= h(is_string($_GET[$key] ?? null) ? $_GET[$key] : '') ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-outline-primary" href="<?= h(app_url('/frontend/admin/auditoria.php')) ?>">Limpiar filtros</a></div>
    </form>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Descripción</th></tr></thead><tbody>
        <?php if (!$logs): ?><tr><td colspan="4" class="text-center text-secondary py-5">No hay eventos con estos filtros.</td></tr><?php endif; ?>
        <?php foreach ($logs as $log):
            $meta = json_decode((string) $log['metadata'], true);
            $meta = is_array($meta) ? $meta : [];
            ?><tr><td><?= h(format_datetime($log['created_at'])) ?></td>
            <td><?= h($log['user_name']) ?><div class="small text-secondary"><?= h($log['user_email']) ?></div></td>
            <td><?= h($log['action']) ?></td>
            <td><?= h($log['description']) ?>
                <details class="mt-2" data-audit-event><summary>Ver detalle del evento #<?= h($log['id']) ?></summary>
                    <dl class="mt-2 text-break">
                        <dt>Registro relacionado</dt><dd><?= h(($log['entity_type'] ?: 'Sin registro relacionado') . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></dd>
                        <dt>Dirección IP de origen</dt><dd><?= h($log['ip_address'] ?: 'No registrada') ?></dd>
                    <?php foreach (['module'=>'Módulo','reference'=>'Referencia','reason'=>'Motivo','note'=>'Observación'] as $key=>$label): ?>
                        <?php if (isset($meta[$key]) && is_scalar($meta[$key])): ?>
                        <dt><?= h($label) ?></dt><dd><?= h($meta[$key]) ?></dd>
                        <?php endif; ?>
                    <?php endforeach; ?></dl>
                    <?php $before = is_array($meta['before'] ?? null) ? $meta['before'] : [];
                    $after = is_array($meta['after'] ?? null) ? $meta['after'] : [];
                    $changes = array_filter(['name','coverage','location','status','channel_number','brightness','contrast',
                        'saturation','category','concept','amount','expense_datetime','payment_method','description','other_detail'],
                        static fn(string $key): bool => (array_key_exists($key,$before) || array_key_exists($key,$after))
                            && ($before[$key] ?? null) !== ($after[$key] ?? null)); ?>
                    <?php if ($changes): ?><table class="table table-sm text-break"><caption>Cambios registrados</caption>
                        <thead><tr><th scope="col">Campo</th><th scope="col">Antes</th><th scope="col">Después</th></tr></thead>
                        <tbody><?php foreach ($changes as $key): ?><tr><th scope="row"><?= h($fieldLabels[$key] ?? $key) ?></th>
                            <td><?= h(is_scalar($before[$key] ?? null) ? $before[$key] : '—') ?></td>
                            <td><?= h(is_scalar($after[$key] ?? null) ? $after[$key] : '—') ?></td></tr><?php endforeach; ?></tbody></table>
                    <?php else: ?><p class="small text-secondary">Este evento no contiene cambios de campos disponibles.</p><?php endif; ?>
                </details>
            </td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_pagination($pageHasNext); render_page_end(); ?>
