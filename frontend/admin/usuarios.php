<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$data = AdminController::users($_GET);
$users = $data['users'];
$summary = $data['summary'];
$pageHasNext = count($users) > 50;
$users = array_slice($users,0,50);
render_page_start('Usuarios', 'usuarios');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="mini-stat"><span>Total</span><strong><?= h($summary['total'] ?? 0) ?></strong></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><span>Administradores</span><strong><?= h($summary['admins'] ?? 0) ?></strong></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><span>Conductores</span><strong><?= h($summary['drivers'] ?? 0) ?></strong></div></div>
    <div class="col-6 col-lg-3"><div class="mini-stat"><span>Activos</span><strong><?= h($summary['active_users'] ?? 0) ?></strong></div></div>
</div>
<div class="panel-card">
    <form class="row g-2 mb-4" method="get"><div class="col-md-6"><input class="form-control" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Buscar por nombre o correo"></div><div class="col-md-2"><select class="form-select" name="role"><option value="">Todos los roles</option><option value="admin" <?= ($_GET['role']??'')==='admin'?'selected':'' ?>>Administrador</option><option value="driver" <?= ($_GET['role']??'')==='driver'?'selected':'' ?>>Conductor</option></select></div><div class="col-md-2"><select class="form-select" name="status"><option value="">Todos</option><option value="active" <?= ($_GET['status']??'')==='active'?'selected':'' ?>>Activos</option><option value="inactive" <?= ($_GET['status']??'')==='inactive'?'selected':'' ?>>Inactivos</option></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div></form>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Teléfono</th><th>Vehículos</th><th>Reservas</th><th>Registro</th></tr></thead><tbody>
        <?php foreach ($users as $u): ?><tr><td><strong><?= h($u['name']) ?></strong><div class="small text-secondary"><?= h($u['email']) ?></div></td><td><span class="badge text-bg-primary"><?= h($u['role']==='admin'?'Administrador':'Conductor') ?></span></td><td><span class="badge <?= h(badge_class($u['status'])) ?>"><?= h(status_label($u['status'])) ?></span></td><td><?= h($u['phone'] ?: '—') ?></td><td><?= h($u['vehicles_count']) ?></td><td><?= h($u['reservations_count']) ?></td><td><?= h(format_datetime($u['created_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_pagination($pageHasNext); render_page_end(); ?>
