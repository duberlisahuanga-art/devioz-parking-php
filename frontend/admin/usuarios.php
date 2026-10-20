<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$currentAdmin = require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    run_post_action(function (): void {
        if (($_POST['action'] ?? '') !== 'change_status') throw new ValidationException('Acción inválida.');
        AdminController::changeUserStatus($_POST);
    }, 'Estado de usuario actualizado.', '/frontend/admin/usuarios.php?' . http_build_query(array_filter(
        array_intersect_key($_GET,array_flip(['search','role','status','page'])), 'is_scalar'
    )));
}

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
    <p class="text-secondary">Desactivar bloquea el acceso y cierra las sesiones anteriores. Sus vehículos, reservas y pagos se conservan; las reservas continúan su flujo normal.</p>
    <form class="row g-2 mb-4" method="get"><div class="col-md-6"><input class="form-control" name="search" value="<?= h($_GET['search'] ?? '') ?>" placeholder="Buscar por nombre o correo"></div><div class="col-md-2"><select class="form-select" name="role"><option value="">Todos los roles</option><option value="admin" <?= ($_GET['role']??'')==='admin'?'selected':'' ?>>Administrador</option><option value="driver" <?= ($_GET['role']??'')==='driver'?'selected':'' ?>>Conductor</option></select></div><div class="col-md-2"><select class="form-select" name="status"><option value="">Todos</option><option value="active" <?= ($_GET['status']??'')==='active'?'selected':'' ?>>Activos</option><option value="inactive" <?= ($_GET['status']??'')==='inactive'?'selected':'' ?>>Inactivos</option></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div></form>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Teléfono</th><th>Vehículos</th><th>Reservas</th><th>Registro</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($users as $u): ?><tr><td><strong><?= h($u['name']) ?></strong><div class="small text-secondary"><?= h($u['email']) ?></div></td><td><span class="badge text-bg-primary"><?= h($u['role']==='admin'?'Administrador':'Conductor') ?></span></td><td><span class="badge <?= h(badge_class($u['status'])) ?>"><?= h(status_label($u['status'])) ?></span></td><td><?= h($u['phone'] ?: '—') ?></td><td><?= h($u['vehicles_count']) ?></td><td><?= h($u['reservations_count']) ?></td><td><?= h(format_datetime($u['created_at'])) ?></td>
            <td>
            <?php if ($u['role'] === 'driver' && $u['status'] === 'active'): ?>
                <a class="btn btn-outline-primary btn-sm mb-2 text-nowrap" data-manage-driver="<?= h($u['id']) ?>" href="<?= h(app_url('/frontend/conductor/dashboard.php?managed_driver_id=' . (int) $u['id'])) ?>">Gestionar conductor</a>
            <?php elseif ($u['role'] === 'driver'): ?>
                <p class="small text-secondary mb-2">Activa su cuenta para gestionar.</p>
            <?php endif; ?>
            <?php if ((int)$u['id'] === (int)$currentAdmin['id']): ?>
                <span class="small text-secondary">Tu cuenta</span>
            <?php else: $deactivate = $u['status'] === 'active'; ?>
                <details><summary class="text-nowrap"><?= $deactivate ? 'Desactivar usuario' : 'Activar usuario' ?></summary>
                    <form method="post" class="vstack gap-2 mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                        <input type="hidden" name="status" value="<?= $deactivate ? 'inactive' : 'active' ?>">
                        <p class="small mb-0"><?= h(($deactivate ? '¿Desactivar el acceso de ' : '¿Activar el acceso de ') . $u['name'] . '?') ?></p>
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?= $deactivate ? 'Confirmar desactivación' : 'Confirmar activación' ?></button>
                    </form>
                </details>
            <?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_pagination($pageHasNext); render_page_end(); ?>
