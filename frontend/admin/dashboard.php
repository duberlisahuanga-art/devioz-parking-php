<?php
declare(strict_types=1);
require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$data = AdminController::overview();
$summary = $data['spaces'];
$payments = Payment::adminSummary();
$today = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$monthStart = $today->modify('first day of this month')->setTime(0, 0);
$months = [];
$finance = null;
$financeUnavailable = false;
$reservationSeries = db()->prepare("SELECT COUNT(*) FROM reservations WHERE status IN ('active','confirmed','expired') AND starts_on < :end AND ends_on >= :start AND created_at < :cutoff");
for ($i = 5; $i >= 0; $i--) {
    $month = $monthStart->modify("-$i months");
    $filters = FinanceController::filters(['month' => $month->format('Y-m')]);
    $reservationSeries->execute(['start' => substr($filters['start'], 0, 10), 'end' => substr($filters['end'], 0, 10), 'cutoff' => $filters['end']]);
    $entry = ['label' => $month->format('m/Y'), 'reservations' => (int) $reservationSeries->fetchColumn(), 'income' => null, 'expenses' => null];
    if (!$financeUnavailable) {
        try {
            $entry['income'] = (float) Payment::financeSummary($filters['start'], $filters['end'])['total'];
            $entry['expenses'] = Expense::totalForPeriod($filters['start'], $filters['end']);
            if ($i === 0) {
                $previous = (float) Payment::financeSummary(null, $filters['start'])['total'] - Expense::totalForPeriod(null, $filters['start']);
                $finance = ['previous' => $previous, 'income' => $entry['income'], 'expenses' => $entry['expenses'], 'available' => $previous + $entry['income'] - $entry['expenses']];
            }
        } catch (PDOException $e) {
            error_log('Dashboard financiero: ' . $e->getMessage());
            $financeUnavailable = true;
        }
    }
    $months[] = $entry;
}
$financialMax = max(1, ...array_map(static fn(array $m): float => max((float) $m['income'], (float) $m['expenses']), $months));
$reservationsMax = max(1, ...array_column($months, 'reservations'));
$expiring = db()->prepare("SELECT COUNT(*) FROM reservations WHERE status IN ('active','confirmed') AND ends_on >= :today AND ends_on <= :end");
$expiring->execute(['today' => $today->format('Y-m-d'), 'end' => $today->modify('+7 days')->format('Y-m-d')]);
$expiringCount = (int) $expiring->fetchColumn();
$activity = db()->query("SELECT a.action, a.description, a.created_at,
    CASE WHEN JSON_CONTAINS_PATH(IF(JSON_VALID(a.metadata), a.metadata, '{}'), 'one', '$.actor')
        THEN COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.actor.name')), 'null'), 'Sistema / no autenticado')
        ELSE 'Identidad histórica no disponible' END AS user_name
    FROM audit_logs a ORDER BY a.created_at DESC, a.id DESC LIMIT 6")->fetchAll();
$totalSpaces = (int) $data['metrics']['total_spaces'];
$available = (int) ($summary['available'] ?? 0);
$reserved = (int) ($summary['reserved'] ?? 0);
$occupied = (int) ($summary['occupied'] ?? 0);
$distributionTotal = $totalSpaces;
$availablePercent = $distributionTotal > 0 ? 100 * $available / $distributionTotal : 0;
$reservedEnd = $distributionTotal > 0 ? 100 * ($available + $reserved) / $distributionTotal : 0;
$occupiedEnd = $distributionTotal > 0 ? 100 * ($available + $reserved + $occupied) / $distributionTotal : 0;
$donutBackground = $distributionTotal > 0
    ? "conic-gradient(#10b981 0 {$availablePercent}%, #f59e0b {$availablePercent}% {$reservedEnd}%, #ef4444 {$reservedEnd}% {$occupiedEnd}%, #e7edf5 {$occupiedEnd}% 100%)"
    : '#e7edf5';
$percent = static fn(int $count): string => ($totalSpaces > 0 ? round(100 * $count / $totalSpaces, 1) : 0) . '%';
$monthNames = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
$points = [];
foreach ($months as $index => $entry) {
    $points[] = (20 + $index * 136) . ',' . round(165 - 135 * $entry['reservations'] / $reservationsMax, 2);
}
$pendingAmount = (float) db()->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'pending'")->fetchColumn();
$operationMessage = $financeUnavailable ? 'Resumen financiero no disponible' : ($finance && $finance['available'] < 0 ? 'Revisar saldo financiero' : 'Consulta del estado operativo');
$operationDetail = $financeUnavailable ? 'Revisa el módulo Finanzas.' : ($finance && $finance['available'] < 0 ? 'El saldo disponible es negativo.' : $available . ' plazas disponibles para nuevas reservas.');
render_page_start('Dashboard administrativo', 'dashboard');
?>
<div class="dashboard-v3">
    <div class="section-intro">
        <div><h2>Resumen general</h2><p>Indicadores, tendencias y accesos clave para la operación del estacionamiento.</p></div>
        <div class="intro-actions">
            <a class="dashboard-button soft" href="<?= h(app_url('/frontend/admin/espacios.php')) ?>"><i class="bi bi-grid-3x3-gap"></i> Ver croquis</a>
            <a class="dashboard-button soft" href="<?= h(app_url('/frontend/admin/pagos.php')) ?>"><i class="bi bi-wallet2"></i> Revisar pagos</a>
            <a class="dashboard-button primary" href="<?= h(app_url('/frontend/admin/finanzas.php')) ?>"><i class="bi bi-graph-up-arrow"></i> Ver finanzas</a>
        </div>
    </div>
    <div class="kpis">
        <?php foreach ([
            ['Plazas totales', $totalSpaces, 'bi-grid-3x3-gap', 'Capacidad total', ''],
            ['Disponibles', $available, 'bi-check-circle', $percent($available) . ' disponible', 'good'],
            ['Ocupadas', $occupied, 'bi-car-front', $percent($occupied) . ' de ocupación', ''],
            ['Reservadas', $reserved, 'bi-calendar-check', 'Pendientes de ingreso', 'warn'],
            ['Pagos pendientes', $payments['pending'] ?? 0, 'bi-hourglass-split', money($pendingAmount) . ' por validar', 'warn'],
            ['Saldo disponible', $finance ? money($finance['available']) : 'No disponible', 'bi-cash-stack', 'Balance financiero actual', $finance && $finance['available'] < 0 ? 'bad' : ''],
        ] as [$label, $value, $icon, $foot, $tone]): ?>
        <div class="kpi"><div class="kpi-head"><span class="kpi-label"><?= h($label) ?></span><div class="kpi-icon"><i class="bi <?= h($icon) ?>"></i></div></div><div class="kpi-value <?= h($tone) ?>"><?= h($value) ?></div><div class="kpi-foot"><?= h($foot) ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="grid-analytics">
        <div class="card">
            <div class="card-head"><div><h3>Ingresos vs. egresos</h3><p>Comparativo de los últimos 6 meses</p></div><span class="period-pill">6 meses</span></div>
            <div class="card-body">
            <?php if ($financeUnavailable): ?><p class="empty-state">Datos financieros no disponibles. Revisa el módulo Finanzas.</p>
            <?php else: ?>
                <div class="chart-wrap" role="img" aria-label="Ingresos y egresos de los últimos seis meses; importes disponibles en el detalle del gráfico">
                    <span class="chart-gridline"></span><span class="chart-gridline"></span><span class="chart-gridline"></span>
                    <?php foreach ($months as $m): $monthIndex = (int) substr($m['label'], 0, 2) - 1; ?>
                    <div class="bar-col"><div class="bar-pair">
                        <div class="bar" style="height:<?= h(100 * $m['income'] / $financialMax) ?>%" title="<?= h($m['label'] . ' · Ingresos ' . money($m['income'])) ?>"></div>
                        <div class="bar out" style="height:<?= h(100 * $m['expenses'] / $financialMax) ?>%" title="<?= h($m['label'] . ' · Egresos ' . money($m['expenses'])) ?>"></div>
                    </div><span class="bar-label"><?= h($monthNames[$monthIndex]) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-legend"><span><i class="dot blue"></i>Ingresos</span><span><i class="dot red"></i>Egresos</span></div>
                <details class="chart-details"><summary>Ver importes por mes</summary><ul><?php foreach ($months as $m): ?><li><?= h($m['label']) ?> · Ingresos <?= h(money($m['income'])) ?> · Egresos <?= h(money($m['expenses'])) ?></li><?php endforeach; ?></ul></details>
            <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><div><h3>Estado actual de plazas</h3><p>Distribución de las <?= h($totalSpaces) ?> plazas</p></div></div>
            <div class="card-body"><div class="donut-wrap">
                <div class="donut" style="background:<?= h($donutBackground) ?>" role="img" aria-label="<?= h("Disponibles: $available, reservadas: $reserved, ocupadas: $occupied") ?>"><div class="donut-center"><strong><?= h($totalSpaces) ?></strong><span>plazas</span></div></div>
                <div class="legend-list">
                    <?php foreach ([['Disponibles', $available, 'green'], ['Reservadas', $reserved, 'amber'], ['Ocupadas', $occupied, 'red']] as [$label, $value, $tone]): ?>
                    <div class="legend-item"><span class="legend-left"><i class="dot <?= h($tone) ?>"></i><?= h($label) ?></span><strong><?= h($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div></div>
        </div>
    </div>
    <div class="grid-bottom">
        <div class="card">
            <div class="card-head"><div><h3>Evolución de reservas activas</h3><p>Mensualidades vigentes por mes</p></div><span class="period-pill">Tendencia</span></div>
            <div class="card-body line-chart">
                <svg viewBox="0 0 720 200" preserveAspectRatio="none" role="img" aria-labelledby="reservationTrendTitle reservationTrendDescription">
                    <title id="reservationTrendTitle">Mensualidades vigentes por mes</title>
                    <desc id="reservationTrendDescription"><?= h(implode('; ', array_map(static fn(array $m): string => $m['label'] . ': ' . $m['reservations'] . ' reservas', $months))) ?></desc>
                    <g stroke="#e7edf5" stroke-width="1"><line x1="0" y1="40" x2="720" y2="40"/><line x1="0" y1="85" x2="720" y2="85"/><line x1="0" y1="130" x2="720" y2="130"/></g>
                    <polygon fill="rgba(37,99,235,.08)" points="<?= h(implode(' ', $points)) ?> 700,165 20,165"/>
                    <polyline fill="none" stroke="#2563eb" stroke-width="4" stroke-linejoin="round" points="<?= h(implode(' ', $points)) ?>"/>
                    <?php foreach ($months as $index => $m): [$x, $y] = explode(',', $points[$index]); ?>
                    <circle cx="<?= h($x) ?>" cy="<?= h($y) ?>" r="5" fill="#2563eb"><title><?= h($m['label'] . ': ' . $m['reservations'] . ' reservas') ?></title></circle>
                    <text x="<?= h($x) ?>" y="<?= h(max(15, (float) $y - 12)) ?>" text-anchor="middle" fill="#2563eb" font-size="11"><?= h($m['reservations']) ?></text>
                    <text x="<?= h($x) ?>" y="190" text-anchor="middle" fill="#64748b" font-size="11"><?= h($monthNames[(int) substr($m['label'], 0, 2) - 1]) ?></text>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>
        <div class="stats-stack">
            <div class="card"><div class="card-head"><div><h3>Resumen financiero</h3><p>Mes actual · <?= h($monthStart->format('m/Y')) ?></p></div></div><div class="card-body balance-grid">
                <?php foreach (['income' => ['Ingresos', 'good'], 'expenses' => ['Egresos', 'bad'], 'available' => ['Saldo', '']] as $key => [$label, $tone]): ?>
                <div class="balance-box"><span><?= h($label) ?></span><strong class="<?= h($tone) ?>"><?= h($finance ? money($finance[$key]) : '—') ?></strong></div>
                <?php endforeach; ?>
            </div></div>
            <div class="card"><div class="card-head"><div><h3>Requiere atención</h3><p>Prioridades del día</p></div></div><div class="card-body">
                <a class="alert-row" href="<?= h(app_url('/frontend/admin/pagos.php?status=pending')) ?>"><span class="alert-dot amber"></span><div><b><?= h($payments['pending'] ?? 0) ?> pagos por validar</b><small>Comprobantes pendientes de revisión.</small></div></a>
                <a class="alert-row" href="<?= h(app_url('/frontend/admin/reservas.php')) ?>"><span class="alert-dot blue"></span><div><b><?= h($expiringCount) ?> reservas próximas a vencer</b><small>Vencimientos dentro de los próximos 7 días.</small></div></a>
                <div class="alert-row"><span class="alert-dot <?= $financeUnavailable || ($finance && $finance['available'] < 0) ? 'amber' : 'green' ?>"></span><div><b><?= h($operationMessage) ?></b><small><?= h($operationDetail) ?></small></div></div>
            </div></div>
        </div>
    </div>
    <div class="card monitoring-card">
        <div class="monitoring-main">
            <div class="card-head"><div><h3>Monitoreo de vigilancia</h3><p>Acceso general al sistema de cámaras del estacionamiento</p></div><a class="dashboard-button soft" href="<?= h(app_url('/frontend/admin/camaras.php')) ?>"><i class="bi bi-camera-video"></i> Ver monitoreo</a></div>
            <div class="monitoring-status"><i class="bi bi-broadcast-pin"></i><div><span>Estado general</span><strong>Módulo de vigilancia disponible</strong></div></div>
            <div class="monitoring-status"><i class="bi bi-shield-check"></i><div><span>Operación</span><strong>Consulta el estado de vigilancia en Cámaras</strong></div></div>
            <div class="monitoring-status"><i class="bi bi-eye"></i><div><span>Acceso</span><strong>Consulta el monitoreo desde el módulo Cámaras</strong></div></div>
        </div>
        <div class="monitoring-visual"><div><div class="monitoring-icon"><i class="bi bi-camera-video-fill"></i></div><strong>Vigilancia centralizada</strong><p>Vista general del sistema de monitoreo</p></div></div>
    </div>
    <div class="card activity-card"><div class="card-head"><div><h3>Actividad reciente</h3><p>Últimos movimientos del sistema</p></div></div><div class="card-body">
        <?php if (!$activity): ?><p class="empty-state">Todavía no hay movimientos registrados.</p><?php endif; ?>
        <?php foreach ($activity as $event): ?><div class="activity-row"><time class="activity-time" datetime="<?= h($event['created_at']) ?>"><?= h(format_datetime($event['created_at'])) ?></time><div><b><?= h(ucfirst(str_replace('_', ' ', $event['action']))) ?></b><small><?= h($event['description']) ?> · <?= h($event['user_name'] ?? 'Sistema') ?></small></div></div><?php endforeach; ?>
    </div></div>
</div>
<?php render_page_end(); ?>
