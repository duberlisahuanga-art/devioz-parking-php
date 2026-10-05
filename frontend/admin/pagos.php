<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['payment_id'] ?? 0);
    run_post_action(function () use ($action, $id): void {
        if ($id < 1) throw new RuntimeException('Pago inválido.');
        if ($action === 'confirm') AdminController::confirmPayment($id);
        elseif ($action === 'reject') AdminController::rejectPayment($id);
        else throw new RuntimeException('Acción no válida.');
    }, 'Pago procesado correctamente.', '/frontend/admin/pagos.php');
}

$payments = AdminController::payments();
render_page_start('Pagos Yape', 'pagos');
?>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Pagos pendientes</h2><p>Validación manual de comprobantes Yape</p></div><span class="badge text-bg-warning"><?= count($payments) ?> pendientes</span></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pago</th><th>Conductor</th><th>Reserva</th><th>Monto</th><th>Operación</th><th>Comprobante</th><th>Acción</th></tr></thead><tbody>
        <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-secondary py-5">No hay pagos pendientes.</td></tr><?php endif; ?>
        <?php foreach ($payments as $p): ?><tr><td>#<?= h($p['id']) ?><div class="small text-secondary"><?= h(format_datetime($p['created_at'])) ?></div></td><td><?= h($p['user_name']) ?><div class="small text-secondary"><?= h($p['user_email']) ?></div></td><td>Reserva #<?= h($p['reservation_id']) ?><div class="small text-secondary"><?= h($p['space_code']) ?> · <?= h($p['vehicle_plate']) ?></div></td><td><strong><?= h(money($p['amount'])) ?></strong></td><td><?= h($p['provider_tx_id'] ?: '—') ?></td><td><?php if ($p['voucher_path']): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h(app_url('/' . ltrim($p['voucher_path'], '/'))) ?>">Ver comprobante</a><?php else: ?>—<?php endif; ?></td><td><div class="d-flex gap-2"><form method="post"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= h($p['id']) ?>"><button class="btn btn-sm btn-success" name="action" value="confirm">Aprobar</button><button class="btn btn-sm btn-outline-danger" name="action" value="reject">Rechazar</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
