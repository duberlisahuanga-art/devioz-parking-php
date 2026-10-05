<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$logs = AdminController::audit($_GET);
render_page_start('Auditoría', 'auditoria');
?>
<div class="panel-card">
    <form class="row g-2 mb-4" method="get"><div class="col-md-5"><input class="form-control" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Buscar descripción o usuario"></div><div class="col-md-5"><input class="form-control" name="action" value="<?= h($_GET['action'] ?? '') ?>" placeholder="Acción: Login, Creada, Enviado..."></div><div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div></form>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Descripción</th><th>IP</th></tr></thead><tbody>
        <?php if (!$logs): ?><tr><td colspan="6" class="text-center text-secondary py-5">No hay registros de auditoría.</td></tr><?php endif; ?>
        <?php foreach ($logs as $log): ?><tr><td><?= h(format_datetime($log['created_at'])) ?></td><td><?= h($log['user_name'] ?: 'Sistema') ?><div class="small text-secondary"><?= h($log['user_email'] ?: '') ?></div></td><td><span class="badge text-bg-light border"><?= h($log['action']) ?></span></td><td><?= h(($log['entity_type'] ?: '—') . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></td><td><?= h($log['description']) ?></td><td><?= h($log['ip_address'] ?: '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
