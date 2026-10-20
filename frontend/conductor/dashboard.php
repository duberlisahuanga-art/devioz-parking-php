<?php
declare(strict_types=1);
require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = DriverContext::user();
$data = AdminController::dashboardForCurrentUser();
$today = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');
$reservations = Reservation::forUser((int) $user['id']);
$current = null;
foreach ($reservations as $reservation) {
    if (in_array($reservation['status'], ['active', 'confirmed'], true) && $reservation['starts_on'] <= $today && $reservation['ends_on'] >= $today) {
        $current = $reservation;
        break;
    }
}
$paymentStatsQuery = db()->prepare("SELECT COUNT(*) AS approved FROM payments p JOIN reservations r ON r.id = p.reservation_id WHERE r.user_id = :user_id AND p.status = 'paid'");
$paymentStatsQuery->execute(['user_id' => (int) $user['id']]);
$paidMonths = (int) $paymentStatsQuery->fetchColumn();
$recentPaymentsQuery = db()->prepare("SELECT p.id, p.amount, p.method, p.status, p.created_at FROM payments p JOIN reservations r ON r.id = p.reservation_id WHERE r.user_id = :user_id ORDER BY p.created_at DESC, p.id DESC LIMIT 5");
$recentPaymentsQuery->execute(['user_id' => (int) $user['id']]);
$recentPayments = $recentPaymentsQuery->fetchAll();
$spaces = array_values(array_filter($data['spaces'], static fn(array $s): bool => in_array($s['status'], ['available', 'reserved', 'occupied'], true)));
$vehicles = Vehicle::forUser((int) $user['id']);
$progress = 0;
$daysRemaining = null;
if ($current) {
    $start = new DateTimeImmutable($current['starts_on'], new DateTimeZone('America/Lima'));
    $end = new DateTimeImmutable($current['ends_on'], new DateTimeZone('America/Lima'));
    $now = new DateTimeImmutable($today, new DateTimeZone('America/Lima'));
    $duration = max(1, (int) $start->diff($end)->format('%a'));
    $elapsed = (int) $start->diff($now)->format('%r%a');
    $progress = (int) round(max(0, min(100, 100 * $elapsed / $duration)));
    $daysRemaining = (int) $now->diff($end)->format('%r%a');
}
$paymentLabels = ['paid' => 'Pagado', 'pending' => 'Pendiente', 'unpaid' => 'Sin pago', 'rejected' => 'Rechazado'];
$paymentLabel = $current ? ($paymentLabels[$current['payment_status']] ?? status_label($current['payment_status'])) : 'Sin pago';
$vehicleDescription = $current ? (trim(($current['vehicle_brand'] ?? '') . ' ' . ($current['vehicle_model'] ?? '')) ?: 'Marca y modelo no registrados') : 'Sin vehículo asociado';
$lastReservation = $reservations[0] ?? null;
$expiryNote = $current ? ($daysRemaining === 0 ? 'Vence hoy' : $daysRemaining . ' días restantes') : ($lastReservation && $lastReservation['ends_on'] < $today ? 'Última reserva vencida' : 'Sin reserva vigente');
$paymentTone = $current && $current['payment_status'] === 'paid' ? 'good' : 'warn';
render_page_start('Mi dashboard', 'dashboard');
?>
<div class="dashboard-v3">
    <div class="section-intro"><div><h2>Mi resumen</h2><p>Consulta tu reserva, pagos, vehículo y disponibilidad de plazas.</p></div><div class="intro-actions">
        <a class="dashboard-button soft" href="<?= h(DriverContext::url('/frontend/conductor/pagos.php')) ?>"><i class="bi bi-phone"></i> Ver pagos</a>
        <a class="dashboard-button primary" href="<?= h(DriverContext::url('/frontend/conductor/reservar.php')) ?>"><i class="bi bi-plus-circle"></i> Reservar plaza</a>
    </div></div>
    <div class="kpis">
        <?php foreach ([
            ['Mi plaza', $current['space_code'] ?? 'Sin reserva', 'bi-p-circle', $current ? 'Reserva activa' : 'Consulta disponibilidad', ''],
            ['Estado de pago', $paymentLabel, 'bi-check-circle', $current ? 'Mensualidad vigente' : 'Sin mensualidad vigente', $paymentTone],
            ['Próximo vencimiento', $current ? format_date($current['ends_on']) : '—', 'bi-calendar-event', $expiryNote, ''],
            ['Vehículo', $current['vehicle_plate'] ?? 'Sin vehículo', 'bi-car-front', $vehicleDescription, ''],
            ['Mensualidades pagadas', $paidMonths, 'bi-receipt', 'Pagos aprobados en tu historial', ''],
            ['Precio mensual', $current ? money((float) $current['amount']) : '—', 'bi-cash', $current ? 'Plan actual' : 'Sin reserva vigente', ''],
        ] as [$label, $value, $icon, $foot, $tone]): ?>
        <div class="kpi"><div class="kpi-head"><span class="kpi-label"><?= h($label) ?></span><div class="kpi-icon"><i class="bi <?= h($icon) ?>"></i></div></div><div class="kpi-value <?= h($tone) ?>"><?= h($value) ?></div><div class="kpi-foot"><?= h($foot) ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="driver-layout">
        <div class="driver-main">
            <div class="card reservation-card">
                <div class="reservation-top"><div><span class="reservation-eyebrow">RESERVA ACTIVA</span><h3><?= $current ? 'Plaza ' . h($current['space_code']) : 'Sin reserva vigente' ?></h3></div><?php if ($current): ?><span class="reservation-badge">Vigente</span><?php endif; ?></div>
                <?php if ($current): ?>
                <div class="reservation-meta">
                    <?php foreach (['Inicio' => format_date($current['starts_on']), 'Vence' => format_date($current['ends_on']), 'Estado' => status_label($current['status']), 'Pago' => $paymentLabel] as $label => $value): ?>
                    <div class="meta"><span><?= h($label) ?></span><strong><?= h($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <div class="period-progress"><div><span>Vigencia del período</span><strong><?= h($progress) ?>%</strong></div><div class="progress-shell" role="progressbar" aria-label="Porcentaje transcurrido de la reserva" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= h($progress) ?>"><div class="period-progress-bar" style="width:<?= h($progress) ?>%"></div></div></div>
                <?php else: ?><p class="empty-state"><?= h($expiryNote) ?>. Solicita una plaza o consulta tus reservas.</p><a class="dashboard-button soft" href="<?= h(DriverContext::url('/frontend/conductor/reservas.php')) ?>">Ver mis reservas</a><?php endif; ?>
            </div>
            <div class="card" data-dashboard-map>
                <div class="card-head"><div><h3>Disponibilidad de plazas</h3><p>Consulta el estado actual del estacionamiento</p></div></div><div class="card-body">
                    <div class="space-filter-bar" role="group" aria-label="Filtrar plazas por estado">
                        <?php foreach (['all' => 'Todas', 'available' => 'Libres', 'reserved' => 'Reservadas', 'occupied' => 'Ocupadas'] as $status => $label): ?>
                        <button type="button" class="filter-btn <?= $status === 'all' ? 'active' : '' ?>" data-dashboard-filter="<?= h($status) ?>" aria-pressed="<?= $status === 'all' ? 'true' : 'false' ?>"><?= h($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-grid">
                        <?php foreach ($spaces as $space): $labels = ['available' => ['Libre', 'bi-check-circle'], 'reserved' => ['Reservada', 'bi-calendar-check'], 'occupied' => ['Ocupada', 'bi-car-front-fill']]; [$label, $icon] = $labels[$space['status']]; ?>
                        <div class="space-card <?= h($space['status']) ?>" data-dashboard-space="<?= h($space['status']) ?>" title="<?= h($space['code'] . ' · ' . $label) ?>"><i class="bi <?= h($icon) ?>"></i><strong><?= h($space['code']) ?></strong><span><?= h($label) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="empty-state" data-dashboard-empty <?= $spaces ? 'hidden' : '' ?> role="status">No hay plazas con este estado.</p>
                </div>
            </div>
            <div class="card"><div class="card-head"><div><h3>Mi vehículo</h3><p>Vehículo asociado a tu reserva</p></div><a class="dashboard-button soft" href="<?= h(DriverContext::url('/frontend/conductor/vehiculos.php')) ?>">Gestionar</a></div>
                <div class="card-body vehicle-box"><div class="vehicle-icon"><i class="bi bi-car-front-fill"></i></div><div><strong><?= h($current['vehicle_plate'] ?? 'Sin vehículo asociado') ?></strong><p><?= h($vehicleDescription) ?><?= $current ? ' · ' . h($current['vehicle_color'] ?: 'Color no registrado') : '' ?></p></div></div>
            </div>
        </div>
        <div class="driver-side">
            <div class="card"><div class="card-head"><div><h3>Acciones rápidas</h3><p>Accesos más utilizados</p></div></div><div class="card-body quick-grid">
                <?php foreach (['reservar' => ['Reservar', 'bi-plus-circle'], 'pagos' => ['Pagar', 'bi-phone'], 'reservas' => ['Reservas', 'bi-calendar-check'], 'vehiculos' => ['Vehículos', 'bi-car-front']] as $route => [$label, $icon]): ?>
                <a class="quick" href="<?= h(DriverContext::url('/frontend/conductor/' . $route . '.php')) ?>"><i class="bi <?= h($icon) ?>"></i><?= h($label) ?></a>
                <?php endforeach; ?>
            </div></div>
            <div class="card"><div class="card-head"><div><h3>Estado de tu cuenta</h3><p>Resumen personal</p></div></div><div class="card-body">
                <div class="alert-row"><span class="alert-dot <?= $current && $current['payment_status'] === 'paid' && !$data['unpaid_reservations'] ? 'green' : 'amber' ?>"></span><div><b><?= $data['unpaid_reservations'] ? 'Mensualidades por pagar' : ($current && $current['payment_status'] === 'paid' ? 'Pago al día' : ($current ? h($paymentLabel) : 'Sin pago vigente')) ?></b><small><?= h($data['unpaid_reservations']) ?> reservas sin pagar en tu historial.</small></div></div>
                <div class="alert-row"><span class="alert-dot <?= $current ? 'green' : 'amber' ?>"></span><div><b><?= $current ? 'Reserva vigente' : 'Sin reserva vigente' ?></b><small><?= $current ? 'Tu reserva vence el ' . h(format_date($current['ends_on'])) . '.' : 'Consulta disponibilidad para reservar.' ?></small></div></div>
                <div class="alert-row"><span class="alert-dot blue"></span><div><b><?= $vehicles ? 'Vehículo registrado' : 'Sin vehículos registrados' ?></b><small><?= $current ? h($current['vehicle_plate']) . ' asociado a tu reserva.' : ($vehicles ? 'Puedes seleccionar un vehículo al reservar.' : 'Registra un vehículo para reservar.') ?></small></div></div>
            </div></div>
            <div class="card"><div class="card-head"><div><h3>Últimos pagos</h3><p>Comprobantes recientes</p></div><a class="dashboard-button soft" href="<?= h(DriverContext::url('/frontend/conductor/pagos.php')) ?>">Ver todos</a></div><div class="card-body">
                <?php if (!$recentPayments): ?><p class="empty-state">Todavía no tienes pagos registrados.</p><?php endif; ?>
                <?php foreach ($recentPayments as $payment): ?><div class="activity-row"><time class="activity-time" datetime="<?= h($payment['created_at']) ?>" title="<?= h(format_datetime($payment['created_at'])) ?>"><?= h((new DateTimeImmutable($payment['created_at']))->format('d/m')) ?></time><div><b><?= h(money((float) $payment['amount'])) ?> · <?= h(ucfirst($payment['method'])) ?></b><small class="<?= $payment['status'] === 'paid' ? 'good' : ($payment['status'] === 'rejected' ? 'bad' : 'warn') ?>"><?= h(status_label($payment['status'])) ?></small></div></div><?php endforeach; ?>
            </div></div>
        </div>
    </div>
</div>
<?php render_page_end(); ?>