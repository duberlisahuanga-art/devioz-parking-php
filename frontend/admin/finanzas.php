<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$stats = db()->query("SELECT
    COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid_total,
    COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending_total,
    SUM(status='paid') AS paid_count,
    SUM(status='pending') AS pending_count,
    SUM(status='rejected') AS rejected_count
    FROM payments")->fetch() ?: [];
$recent = db()->query("SELECT py.*, u.name AS user_name, s.code AS space_code FROM payments py JOIN reservations r ON r.id=py.reservation_id JOIN users u ON u.id=r.user_id JOIN spaces s ON s.id=r.space_id ORDER BY py.created_at DESC LIMIT 20")->fetchAll();

render_page_start('Finanzas', 'finanzas');
?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Esta vista resume los ingresos por pagos registrados. El módulo de egresos y movimientos financieros se implementará como bloque independiente.</div>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="metric-card"><div class="metric-icon"><i class="bi bi-cash-stack"></i></div><div><span>Ingresos aprobados</span><strong><?= h(money($stats['paid_total'] ?? 0)) ?></strong></div></div></div><div class="col-md-4"><div class="metric-card"><div class="metric-icon"><i class="bi bi-hourglass-split"></i></div><div><span>Pendiente de validar</span><strong><?= h(money($stats['pending_total'] ?? 0)) ?></strong></div></div></div><div class="col-md-4"><div class="metric-card"><div class="metric-icon"><i class="bi bi-check2-circle"></i></div><div><span>Pagos aprobados</span><strong><?= h($stats['paid_count'] ?? 0) ?></strong></div></div></div></div>
<div class="panel-card"><div class="panel-heading"><div><h2>Movimientos de pagos</h2><p>Últimos registros procesados por Yape</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Conductor</th><th>Plaza</th><th>Monto</th><th>Método</th><th>Estado</th></tr></thead><tbody><?php if (!$recent): ?><tr><td colspan="6" class="text-center text-secondary py-5">Aún no hay movimientos.</td></tr><?php endif; ?><?php foreach ($recent as $p): ?><tr><td><?= h(format_datetime($p['created_at'])) ?></td><td><?= h($p['user_name']) ?></td><td><?= h($p['space_code']) ?></td><td><?= h(money($p['amount'])) ?></td><td><?= h(strtoupper($p['method'])) ?></td><td><span class="badge <?= h(badge_class($p['status'])) ?>"><?= h(status_label($p['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php render_page_end(); ?>
