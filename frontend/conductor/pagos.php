<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    run_post_action(function () use ($reservationId): void { PaymentController::submit($reservationId, $_POST, $_FILES); }, 'Voucher enviado. El pago quedó pendiente de validación.', '/frontend/conductor/pagos.php');
}

$reservations = Reservation::forUser((int) $user['id']);
$stmt = db()->prepare("SELECT py.*, s.code AS space_code FROM payments py JOIN reservations r ON r.id=py.reservation_id JOIN spaces s ON s.id=r.space_id WHERE r.user_id=:user_id ORDER BY py.created_at DESC");
$stmt->execute(['user_id' => $user['id']]);
$payments = $stmt->fetchAll();
$selected = (int) ($_GET['reservation_id'] ?? 0);
render_page_start('Pagos Yape', 'pagos');
?>
<div class="row g-4"><div class="col-xl-5"><div class="panel-card"><div class="panel-heading"><div><h2>Enviar comprobante</h2><p>Mensualidad de S/ 100 mediante Yape</p></div></div>
    <div class="yape-box mb-3"><i class="bi bi-phone-fill"></i><div><strong>Pago por Yape</strong><span>Realiza el pago y adjunta tu comprobante.</span></div></div>
    <form method="post" enctype="multipart/form-data" class="vstack gap-3"><?= csrf_field() ?><input type="hidden" name="method" value="yape"><div><label class="form-label">Reserva sin pagar</label><select class="form-select" name="reservation_id" required><option value="">Seleccionar</option><?php foreach ($reservations as $r): ?><?php if ($r['payment_status']==='unpaid' && !in_array($r['status'],['cancelled','expired'],true)): ?><option value="<?= h($r['id']) ?>" <?= $selected===(int)$r['id']?'selected':'' ?>>#<?= h($r['id']) ?> · <?= h($r['space_code']) ?> · <?= h(money($r['amount'])) ?></option><?php endif; ?><?php endforeach; ?></select></div><div><label class="form-label">N.º de operación Yape</label><input class="form-control" name="provider_tx_id" required maxlength="100" placeholder="Ej. 12345678"></div><div><label class="form-label">Comprobante</label><input class="form-control" type="file" name="voucher" required accept="image/jpeg,image/png,image/webp,application/pdf"><div class="form-text">JPG, PNG, WEBP o PDF · máximo 5 MB.</div></div><button class="btn btn-primary btn-lg">Enviar para validación</button></form>
</div></div><div class="col-xl-7"><div class="panel-card"><div class="panel-heading"><div><h2>Historial de pagos</h2><p>Comprobantes enviados</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Reserva</th><th>Monto</th><th>Operación</th><th>Estado</th></tr></thead><tbody><?php if (!$payments): ?><tr><td colspan="5" class="text-center text-secondary py-5">Aún no has enviado pagos.</td></tr><?php endif; ?><?php foreach ($payments as $p): ?><tr><td><?= h(format_datetime($p['created_at'])) ?></td><td>#<?= h($p['reservation_id']) ?><div class="small text-secondary"><?= h($p['space_code']) ?></div></td><td><?= h(money($p['amount'])) ?></td><td><?= h($p['provider_tx_id']) ?></td><td><span class="badge <?= h(badge_class($p['status'])) ?>"><?= h(status_label($p['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php render_page_end(); ?>
