#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"

if [ ! -f "$ROOT/backend/bootstrap.php" ]; then
  echo "ERROR: Ejecuta este script desde la raiz de devioz-parking-php."
  echo "No se encontro backend/bootstrap.php"
  exit 1
fi

mkdir -p \
  frontend/admin \
  frontend/conductor \
  frontend/includes \
  frontend/assets/css \
  frontend/assets/js \
  frontend/assets/img

rm -f \
  frontend/admin/.gitkeep \
  frontend/conductor/.gitkeep

cat > frontend/includes/layout.php <<'PHP'
<?php

declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $amount): string
{
    return 'S/ ' . number_format((float) $amount, 2, '.', ',');
}

function format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return h($value);
    }
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return h($value);
    }
}

function badge_class(string $status): string
{
    return match ($status) {
        'active', 'paid', 'available', 'approved' => 'text-bg-success',
        'confirmed', 'reserved', 'pending' => 'text-bg-warning',
        'occupied' => 'text-bg-danger',
        'cancelled', 'rejected', 'inactive' => 'text-bg-secondary',
        'maintenance', 'expired', 'unpaid' => 'text-bg-dark',
        default => 'text-bg-primary',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'active' => 'Activo',
        'confirmed' => 'Confirmada',
        'cancelled' => 'Cancelada',
        'expired' => 'Vencida',
        'available' => 'Disponible',
        'reserved' => 'Reservada',
        'occupied' => 'Ocupada',
        'maintenance' => 'Mantenimiento',
        'pending' => 'Pendiente',
        'paid' => 'Pagado',
        'unpaid' => 'Sin pagar',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
        'inactive' => 'Inactivo',
        'none' => 'Sin solicitud',
        default => ucfirst($status),
    };
}

function flash_message(): string
{
    $flash = consume_flash();
    if (!$flash) {
        return '';
    }

    $type = in_array(($flash['type'] ?? ''), ['success', 'danger', 'warning', 'info'], true)
        ? $flash['type']
        : 'info';

    return '<div class="alert alert-' . h($type) . ' alert-dismissible fade show" role="alert">'
        . h($flash['message'] ?? '')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function menu_items(string $role): array
{
    if ($role === 'admin') {
        return [
            ['dashboard', 'bi-speedometer2', 'Dashboard', '/frontend/admin/dashboard.php'],
            ['reservas', 'bi-calendar-check', 'Reservas', '/frontend/admin/reservas.php'],
            ['espacios', 'bi-grid-3x3-gap', 'Espacios', '/frontend/admin/espacios.php'],
            ['vehiculos', 'bi-car-front', 'Vehículos', '/frontend/admin/vehiculos.php'],
            ['pagos', 'bi-wallet2', 'Pagos', '/frontend/admin/pagos.php'],
            ['camaras', 'bi-camera-video', 'Cámaras', '/frontend/admin/camaras.php'],
            ['usuarios', 'bi-people', 'Usuarios', '/frontend/admin/usuarios.php'],
            ['finanzas', 'bi-graph-up-arrow', 'Finanzas', '/frontend/admin/finanzas.php'],
            ['auditoria', 'bi-shield-check', 'Auditoría', '/frontend/admin/auditoria.php'],
        ];
    }

    return [
        ['dashboard', 'bi-speedometer2', 'Dashboard', '/frontend/conductor/dashboard.php'],
        ['reservar', 'bi-plus-circle', 'Reservar plaza', '/frontend/conductor/reservar.php'],
        ['reservas', 'bi-calendar-check', 'Mis reservas', '/frontend/conductor/reservas.php'],
        ['vehiculos', 'bi-car-front', 'Mis vehículos', '/frontend/conductor/vehiculos.php'],
        ['pagos', 'bi-phone', 'Pagos Yape', '/frontend/conductor/pagos.php'],
    ];
}

function render_page_start(string $title, string $active): void
{
    $user = require_auth();
    $role = (string) ($user['role'] ?? 'driver');
    $roleLabel = $role === 'admin' ? 'Administrador' : 'Conductor';
    $items = menu_items($role);
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | Devioz Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= h(app_url('/frontend/assets/css/styles.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand-wrap">
            <div class="brand-mark"><i class="bi bi-p-square-fill"></i></div>
            <div>
                <div class="brand-name">Devioz Parking</div>
                <div class="brand-subtitle"><?= h($roleLabel) ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($items as [$key, $icon, $label, $url]): ?>
                <a class="nav-item <?= $active === $key ? 'active' : '' ?>" href="<?= h(app_url($url)) ?>">
                    <i class="bi <?= h($icon) ?>"></i>
                    <span><?= h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="small text-white-50 mb-1">Sesión actual</div>
            <div class="fw-semibold text-white text-truncate"><?= h($user['name'] ?? '') ?></div>
            <div class="small text-white-50 text-truncate"><?= h($user['email'] ?? '') ?></div>
            <a class="btn btn-outline-light btn-sm w-100 mt-3" href="<?= h(app_url('/frontend/logout.php')) ?>">
                <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="btn btn-light d-lg-none" type="button" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="text-secondary small">Devioz Parking</div>
                <h1 class="h4 mb-0"><?= h($title) ?></h1>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="role-pill"><i class="bi bi-person-circle me-1"></i><?= h($roleLabel) ?></span>
            </div>
        </header>

        <section class="content-wrap">
            <?= flash_message() ?>
    <?php
}

function render_page_end(): void
{
    ?>
        </section>
        <footer class="page-footer">Devioz Parking · PHP + MariaDB + Bootstrap</footer>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= h(app_url('/frontend/assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}

function run_post_action(callable $action, string $success, string $redirect): never
{
    try {
        verify_csrf();
        $action();
        flash('success', $success);
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }

    redirect_to($redirect);
}
PHP

cat > frontend/index.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

if (!is_authenticated()) {
    redirect_to('/frontend/login.php');
}

if (is_admin()) {
    redirect_to('/frontend/admin/dashboard.php');
}

redirect_to('/frontend/conductor/dashboard.php');
PHP

cat > frontend/login.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
require_guest();
$flash = consume_flash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Devioz Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(app_url('/frontend/assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-hero d-none d-lg-flex">
        <div class="login-hero-content">
            <div class="hero-icon"><i class="bi bi-p-square-fill"></i></div>
            <h1>Devioz Parking</h1>
            <p>Gestión de estacionamiento mensual con reservas, control de ingreso y salida, pagos Yape y videovigilancia.</p>
            <div class="hero-features">
                <span><i class="bi bi-check-circle-fill"></i> 40 plazas</span>
                <span><i class="bi bi-check-circle-fill"></i> Mensualidad S/ 100</span>
                <span><i class="bi bi-check-circle-fill"></i> Administrador y conductor</span>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="d-lg-none text-center mb-4">
                <div class="hero-icon mx-auto"><i class="bi bi-p-square-fill"></i></div>
            </div>
            <p class="eyebrow">Bienvenido</p>
            <h2 class="mb-2">Inicia sesión</h2>
            <p class="text-secondary mb-4">Ingresa tus credenciales para acceder al sistema.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(app_url('/backend/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="vstack gap-3">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label">Correo electrónico</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input class="form-control" type="email" name="email" required autocomplete="email" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div>
                    <label class="form-label">Contraseña</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit">Ingresar</button>
            </form>

            <div class="demo-box mt-4">
                <div class="fw-semibold mb-2">Credenciales de demostración</div>
                <div class="small"><strong>Admin:</strong> admin@devioz.test / password</div>
                <div class="small"><strong>Conductor:</strong> driver@devioz.test / password</div>
            </div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars(app_url('/frontend/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
PHP

cat > frontend/logout.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
AuthController::logout();
redirect_to('/frontend/login.php');
PHP

cat > frontend/admin/dashboard.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$data = AdminController::overview();
$metrics = $data['metrics'];
$summary = $data['spaces'];
$spaces = Space::allDetailed();

render_page_start('Dashboard administrativo', 'dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-grid-3x3-gap"></i></div><div><span>Plazas totales</span><strong><?= h($metrics['total_spaces']) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-car-front"></i></div><div><span>Ocupadas</span><strong><?= h($summary['occupied'] ?? 0) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-calendar-check"></i></div><div><span>Reservas activas</span><strong><?= h($metrics['active_reservations']) ?></strong></div></div></div>
    <div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-percent"></i></div><div><span>Disponibilidad</span><strong><?= h($metrics['availability']) ?>%</strong></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel-card">
            <div class="panel-heading">
                <div><h2>Mapa de espacios</h2><p>Vista operativa de las 40 plazas</p></div>
                <a class="btn btn-outline-primary btn-sm" href="<?= h(app_url('/frontend/admin/espacios.php')) ?>">Ver detalle</a>
            </div>
            <div class="parking-map">
                <?php foreach ($spaces as $space): ?>
                    <div class="parking-space status-<?= h($space['status']) ?>" title="<?= h($space['code']) ?> · <?= h(status_label($space['status'])) ?>">
                        <i class="bi bi-car-front-fill"></i>
                        <span><?= h($space['code']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="map-legend mt-3">
                <span><i class="legend-dot available"></i>Disponible</span>
                <span><i class="legend-dot reserved"></i>Reservada</span>
                <span><i class="legend-dot occupied"></i>Ocupada</span>
                <span><i class="legend-dot maintenance"></i>Mantenimiento</span>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card h-100">
            <div class="panel-heading"><div><h2>Resumen</h2><p>Estado actual del estacionamiento</p></div></div>
            <div class="summary-list">
                <div><span>Disponibles</span><strong><?= h($summary['available'] ?? 0) ?></strong></div>
                <div><span>Reservadas</span><strong><?= h($summary['reserved'] ?? 0) ?></strong></div>
                <div><span>Ocupadas</span><strong><?= h($summary['occupied'] ?? 0) ?></strong></div>
                <div><span>Mantenimiento</span><strong><?= h($summary['maintenance'] ?? 0) ?></strong></div>
            </div>
            <hr>
            <a class="btn btn-primary w-100" href="<?= h(app_url('/frontend/admin/reservas.php')) ?>">Gestionar reservas</a>
        </div>
    </div>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/admin/reservas.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['reservation_id'] ?? 0);

    run_post_action(function () use ($action, $id): void {
        if ($id < 1) throw new RuntimeException('Reserva inválida.');
        match ($action) {
            'checkin' => ReservationController::checkin($id),
            'checkout' => ReservationController::checkout($id),
            'approve_cancel' => ReservationController::approveCancellation($id, $_POST['note'] ?? null),
            'reject_cancel' => ReservationController::rejectCancellation($id, (string) ($_POST['note'] ?? '')),
            default => throw new RuntimeException('Acción no válida.'),
        };
    }, 'Operación realizada correctamente.', '/frontend/admin/reservas.php');
}

$reservations = ReservationController::index();
render_page_start('Reservas', 'reservas');
?>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Reservas mensuales</h2><p>Control de ingreso, salida y solicitudes de cancelación</p></div><span class="badge text-bg-primary"><?= count($reservations) ?> registros</span></div>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead><tr><th>#</th><th>Conductor</th><th>Plaza / vehículo</th><th>Periodo</th><th>Estado</th><th>Pago</th><th>Cancelación</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (!$reservations): ?>
                <tr><td colspan="8" class="text-center text-secondary py-5">No hay reservas registradas.</td></tr>
            <?php endif; ?>
            <?php foreach ($reservations as $r): ?>
                <tr>
                    <td>#<?= h($r['id']) ?></td>
                    <td><strong><?= h($r['user_name']) ?></strong><div class="small text-secondary"><?= h($r['user_email']) ?></div></td>
                    <td><strong><?= h($r['space_code']) ?></strong><div class="small text-secondary"><?= h($r['vehicle_plate']) ?></div></td>
                    <td><?= h(format_date($r['starts_on'])) ?><div class="small text-secondary">hasta <?= h(format_date($r['ends_on'])) ?></div></td>
                    <td><span class="badge <?= h(badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                    <td><span class="badge <?= h(badge_class($r['payment_status'])) ?>"><?= h(status_label($r['payment_status'])) ?></span><div class="small mt-1"><?= h(money($r['amount'])) ?></div></td>
                    <td>
                        <span class="badge <?= h(badge_class($r['cancellation_status'])) ?>"><?= h(status_label($r['cancellation_status'])) ?></span>
                        <?php if (!empty($r['cancellation_reason'])): ?><div class="small text-secondary mt-1"><?= h($r['cancellation_reason']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($r['status'] === 'confirmed' && $r['cancellation_status'] !== 'pending'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-success">Ingreso</button></form>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'active'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><button class="btn btn-sm btn-outline-danger">Salida</button></form>
                            <?php endif; ?>
                            <?php if ($r['cancellation_status'] === 'pending' && $r['status'] !== 'active'): ?>
                                <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><input class="form-control form-control-sm" name="note" placeholder="Nota"><button name="action" value="approve_cancel" class="btn btn-sm btn-success">Aprobar</button><button name="action" value="reject_cancel" class="btn btn-sm btn-danger">Rechazar</button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/admin/espacios.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();
$data = AdminController::spaces();
$spaces = $data['spaces'];
$summary = $data['summary'];
render_page_start('Espacios', 'espacios');
?>
<div class="row g-3 mb-4">
    <?php foreach ([['Total','total'],['Disponibles','available'],['Reservadas','reserved'],['Ocupadas','occupied'],['Mantenimiento','maintenance']] as [$label,$key]): ?>
        <div class="col-6 col-lg"><div class="mini-stat"><span><?= h($label) ?></span><strong><?= h($summary[$key] ?? 0) ?></strong></div></div>
    <?php endforeach; ?>
</div>
<div class="panel-card mb-4">
    <div class="panel-heading"><div><h2>Croquis del estacionamiento</h2><p>Una sola cochera · 40 espacios · 3 filas</p></div></div>
    <div class="parking-map parking-map-large">
        <?php foreach ($spaces as $space): ?>
            <div class="parking-space status-<?= h($space['status']) ?>">
                <i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span>
                <?php if ($space['vehicle_plate']): ?><small><?= h($space['vehicle_plate']) ?></small><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="map-legend mt-3"><span><i class="legend-dot available"></i>Disponible</span><span><i class="legend-dot reserved"></i>Reservada</span><span><i class="legend-dot occupied"></i>Ocupada</span><span><i class="legend-dot maintenance"></i>Mantenimiento</span></div>
</div>
<div class="panel-card">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Plaza</th><th>Tipo</th><th>Estado</th><th>Conductor</th><th>Vehículo</th></tr></thead><tbody>
        <?php foreach ($spaces as $s): ?><tr><td><strong><?= h($s['code']) ?></strong></td><td><?= h($s['type']) ?></td><td><span class="badge <?= h(badge_class($s['status'])) ?>"><?= h(status_label($s['status'])) ?></span></td><td><?= h($s['driver_name'] ?: '—') ?></td><td><?= h($s['vehicle_plate'] ?: '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/admin/vehiculos.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$sql = "SELECT v.*, u.name AS user_name, u.email AS user_email,
        (SELECT COUNT(*) FROM reservations r WHERE r.vehicle_id=v.id AND r.status IN ('confirmed','active')) AS open_reservations
        FROM vehicles v JOIN users u ON u.id=v.user_id ORDER BY v.created_at DESC, v.id DESC";
$vehicles = db()->query($sql)->fetchAll();

render_page_start('Vehículos', 'vehiculos');
?>
<div class="panel-card">
    <div class="panel-heading"><div><h2>Vehículos registrados</h2><p>Listado general de vehículos de los conductores</p></div><span class="badge text-bg-primary"><?= count($vehicles) ?></span></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Placa</th><th>Marca / modelo</th><th>Color</th><th>Tipo</th><th>Conductor</th><th>Reserva vigente</th></tr></thead><tbody>
        <?php if (!$vehicles): ?><tr><td colspan="6" class="text-center text-secondary py-5">No hay vehículos registrados.</td></tr><?php endif; ?>
        <?php foreach ($vehicles as $v): ?><tr><td><strong><?= h($v['plate']) ?></strong></td><td><?= h(trim(($v['brand'] ?? '') . ' ' . ($v['model'] ?? '')) ?: '—') ?></td><td><?= h($v['color'] ?: '—') ?></td><td><?= h($v['type']) ?></td><td><?= h($v['user_name']) ?><div class="small text-secondary"><?= h($v['user_email']) ?></div></td><td><?= (int)$v['open_reservations'] > 0 ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-secondary">No</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/admin/pagos.php <<'PHP'
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
PHP

cat > frontend/admin/camaras.php <<'PHP'
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
PHP

cat > frontend/admin/usuarios.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$data = AdminController::users($_GET);
$users = $data['users'];
$summary = $data['summary'];
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
<?php render_page_end(); ?>
PHP

cat > frontend/admin/auditoria.php <<'PHP'
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
PHP

cat > frontend/admin/finanzas.php <<'PHP'
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
PHP

cat > frontend/conductor/dashboard.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();
$data = AdminController::dashboardForCurrentUser();
$user = current_user();
render_page_start('Mi dashboard', 'dashboard');
?>
<div class="welcome-banner mb-4"><div><p class="eyebrow mb-1">Hola, <?= h($user['name']) ?></p><h2>Tu estacionamiento, en un solo lugar</h2><p class="mb-0">Gestiona tu mensualidad, vehículo y pago Yape.</p></div><a class="btn btn-light" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>"><i class="bi bi-plus-circle me-1"></i>Reservar plaza</a></div>
<div class="row g-3 mb-4"><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-calendar-check"></i></div><div><span>Reservas activas</span><strong><?= h($data['active_reservations']) ?></strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-exclamation-circle"></i></div><div><span>Por pagar</span><strong><?= h($data['unpaid_reservations']) ?></strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-grid"></i></div><div><span>Disponibilidad</span><strong><?= h($data['availability']) ?>%</strong></div></div></div><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon"><i class="bi bi-wallet2"></i></div><div><span>Pagado</span><strong><?= h($data['balance']) ?></strong></div></div></div></div>
<div class="panel-card"><div class="panel-heading"><div><h2>Estado de las plazas</h2><p>Consulta visual del estacionamiento</p></div></div><div class="parking-map"><?php foreach ($data['spaces'] as $space): ?><div class="parking-space status-<?= h($space['status']) ?>"><i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span></div><?php endforeach; ?></div><div class="map-legend mt-3"><span><i class="legend-dot available"></i>Disponible</span><span><i class="legend-dot reserved"></i>Reservada</span><span><i class="legend-dot occupied"></i>Ocupada</span></div></div>
<?php render_page_end(); ?>
PHP

cat > frontend/conductor/reservar.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    run_post_action(function (): void { ReservationController::store($_POST); }, 'Reserva mensual creada correctamente.', '/frontend/conductor/reservas.php');
}

$data = ReservationController::createData();
$available = array_values(array_filter($data['spaces'], fn(array $s): bool => $s['status'] === 'available'));
render_page_start('Reservar plaza', 'reservar');
?>
<div class="row g-4">
    <div class="col-xl-5"><div class="panel-card"><div class="panel-heading"><div><h2>Nueva mensualidad</h2><p><?= h($data['parking']['name']) ?></p></div><span class="price-pill"><?= h(money($data['monthly_price'])) ?>/mes</span></div>
        <?php if (!$data['vehicles']): ?><div class="alert alert-warning">Primero registra un vehículo en <a href="<?= h(app_url('/frontend/conductor/vehiculos.php')) ?>">Mis vehículos</a>.</div><?php elseif (!$available): ?><div class="alert alert-warning">En este momento no hay plazas disponibles.</div><?php else: ?>
        <form method="post" class="vstack gap-3"><?= csrf_field() ?><div><label class="form-label">Vehículo</label><select class="form-select" name="vehicle_id" required><?php foreach ($data['vehicles'] as $v): ?><option value="<?= h($v['id']) ?>"><?= h($v['plate']) ?> · <?= h(trim(($v['brand'] ?? '').' '.($v['model'] ?? ''))) ?></option><?php endforeach; ?></select></div><div><label class="form-label">Fecha de inicio</label><input class="form-control" type="date" name="starts_on" min="<?= h(date('Y-m-d')) ?>" value="<?= h(date('Y-m-d')) ?>" required></div><div><label class="form-label">Plaza</label><select class="form-select" name="space_id" required><?php foreach ($available as $s): ?><option value="<?= h($s['id']) ?>"><?= h($s['code']) ?> · <?= h(ucfirst($s['type'])) ?></option><?php endforeach; ?></select></div><div class="reservation-summary"><div><span>Plan</span><strong>Mensual</strong></div><div><span>Precio</span><strong><?= h(money($data['monthly_price'])) ?></strong></div><div><span>Pago</span><strong>Yape</strong></div></div><button class="btn btn-primary btn-lg">Crear reserva</button></form>
        <?php endif; ?>
    </div>
    <div class="col-xl-7"><div class="panel-card"><div class="panel-heading"><div><h2>Plazas disponibles</h2><p>Selecciona una plaza libre</p></div><span class="badge text-bg-success"><?= count($available) ?> libres</span></div><div class="parking-map"><?php foreach ($data['spaces'] as $space): ?><div class="parking-space status-<?= h($space['status']) ?>"><i class="bi bi-car-front-fill"></i><span><?= h($space['code']) ?></span></div><?php endforeach; ?></div></div></div>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/conductor/reservas.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['reservation_id'] ?? 0);
    $reason = (string) ($_POST['reason'] ?? '');
    run_post_action(function () use ($id, $reason): void { ReservationController::requestCancellation($id, $reason); }, 'Solicitud de cancelación enviada.', '/frontend/conductor/reservas.php');
}

$reservations = ReservationController::index();
render_page_start('Mis reservas', 'reservas');
?>
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4"><div><h2 class="h5 mb-1">Mensualidades</h2><p class="text-secondary mb-0">Cada reserva cubre un mes de estacionamiento.</p></div><a class="btn btn-primary" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>"><i class="bi bi-plus-circle me-1"></i>Nueva reserva</a></div>
<div class="row g-3">
<?php if (!$reservations): ?><div class="col-12"><div class="panel-card text-center py-5"><i class="bi bi-calendar-x display-5 text-secondary"></i><h3 class="h5 mt-3">Aún no tienes reservas</h3><a class="btn btn-primary mt-2" href="<?= h(app_url('/frontend/conductor/reservar.php')) ?>">Reservar ahora</a></div></div><?php endif; ?>
<?php foreach ($reservations as $r): ?><div class="col-xl-6"><div class="panel-card h-100"><div class="d-flex justify-content-between gap-3"><div><span class="small text-secondary">Reserva #<?= h($r['id']) ?></span><h3 class="h5 mb-1">Plaza <?= h($r['space_code']) ?></h3><div class="text-secondary"><?= h($r['vehicle_plate']) ?> · <?= h(trim(($r['vehicle_brand'] ?? '').' '.($r['vehicle_model'] ?? ''))) ?></div></div><span class="badge <?= h(badge_class($r['status'])) ?> align-self-start"><?= h(status_label($r['status'])) ?></span></div><hr><div class="reservation-grid"><div><span>Inicio</span><strong><?= h(format_date($r['starts_on'])) ?></strong></div><div><span>Fin</span><strong><?= h(format_date($r['ends_on'])) ?></strong></div><div><span>Monto</span><strong><?= h(money($r['amount'])) ?></strong></div><div><span>Pago</span><strong><?= h(status_label($r['payment_status'])) ?></strong></div></div>
    <div class="d-flex flex-wrap gap-2 mt-3"><?php if ($r['payment_status']==='unpaid' && in_array($r['status'],['confirmed','active'],true)): ?><a class="btn btn-sm btn-primary" href="<?= h(app_url('/frontend/conductor/pagos.php?reservation_id=' . $r['id'])) ?>">Pagar con Yape</a><?php endif; ?><?php if ($r['status']==='confirmed' && $r['cancellation_status']!=='pending'): ?><button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-<?= h($r['id']) ?>">Solicitar cancelación</button><?php endif; ?></div>
    <?php if ($r['cancellation_status']==='pending'): ?><div class="alert alert-warning mt-3 mb-0">Solicitud de cancelación pendiente de revisión.</div><?php elseif ($r['cancellation_status']==='rejected'): ?><div class="alert alert-secondary mt-3 mb-0">La solicitud fue rechazada. <?= h($r['cancellation_review_note'] ?? '') ?></div><?php endif; ?>
    <div class="collapse mt-3" id="cancel-<?= h($r['id']) ?>"><form method="post" class="border rounded p-3 bg-light"><?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= h($r['id']) ?>"><label class="form-label">Motivo</label><textarea class="form-control mb-2" name="reason" minlength="5" required placeholder="Indica por qué deseas cancelar"></textarea><button class="btn btn-danger btn-sm">Enviar solicitud</button></form></div>
</div></div><?php endforeach; ?>
</div>
<?php render_page_end(); ?>
PHP

cat > frontend/conductor/vehiculos.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    run_post_action(function () use ($action): void {
        if ($action === 'create') VehicleController::store($_POST);
        elseif ($action === 'delete') VehicleController::destroy((int) ($_POST['vehicle_id'] ?? 0));
        else throw new RuntimeException('Acción no válida.');
    }, $action === 'delete' ? 'Vehículo eliminado.' : 'Vehículo registrado.', '/frontend/conductor/vehiculos.php');
}

$vehicles = VehicleController::index();
render_page_start('Mis vehículos', 'vehiculos');
?>
<div class="row g-4"><div class="col-xl-4"><div class="panel-card"><div class="panel-heading"><div><h2>Registrar vehículo</h2><p>Agrega un vehículo a tu cuenta</p></div></div><form method="post" class="vstack gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div><label class="form-label">Placa</label><input class="form-control text-uppercase" name="plate" required placeholder="ABC-123"></div><div><label class="form-label">Marca</label><input class="form-control" name="brand" placeholder="Toyota"></div><div><label class="form-label">Modelo</label><input class="form-control" name="model" placeholder="Corolla"></div><div><label class="form-label">Color</label><input class="form-control" name="color" placeholder="Blanco"></div><div><label class="form-label">Tipo</label><select class="form-select" name="type"><option value="car">Auto</option><option value="moto">Moto</option></select></div><button class="btn btn-primary">Registrar vehículo</button></form></div></div><div class="col-xl-8"><div class="row g-3"><?php if (!$vehicles): ?><div class="col-12"><div class="panel-card text-center py-5 text-secondary">No tienes vehículos registrados.</div></div><?php endif; ?><?php foreach ($vehicles as $v): ?><div class="col-md-6"><div class="vehicle-card"><div class="vehicle-visual"><i class="bi bi-car-front-fill"></i></div><div class="flex-grow-1"><h3 class="h5 mb-1"><?= h($v['plate']) ?></h3><div class="text-secondary small"><?= h(trim(($v['brand'] ?? '').' '.($v['model'] ?? '')) ?: 'Sin marca/modelo') ?></div><div class="small mt-2"><span class="me-3"><i class="bi bi-palette me-1"></i><?= h($v['color'] ?: '—') ?></span><span><i class="bi bi-tag me-1"></i><?= h($v['type']) ?></span></div></div><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" value="<?= h($v['id']) ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este vehículo?')"><i class="bi bi-trash"></i></button></form></div></div><?php endforeach; ?></div></div></div>
<?php render_page_end(); ?>
PHP

cat > frontend/conductor/pagos.php <<'PHP'
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
PHP

cat > frontend/assets/css/styles.css <<'CSS'
:root {
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --navy: #0f172a;
  --sidebar: #111827;
  --surface: #ffffff;
  --background: #f4f7fb;
  --border: #e5e7eb;
  --muted: #64748b;
}

* { box-sizing: border-box; }
body { margin: 0; background: var(--background); color: #172033; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
a { text-decoration: none; }

.app-shell { min-height: 100vh; display: flex; }
.sidebar { width: 260px; min-height: 100vh; position: fixed; inset: 0 auto 0 0; display: flex; flex-direction: column; background: linear-gradient(180deg, #0f172a 0%, #172554 100%); padding: 1.25rem; z-index: 1040; box-shadow: 12px 0 30px rgba(15,23,42,.08); }
.brand-wrap { display: flex; align-items: center; gap: .8rem; padding: .55rem .35rem 1.5rem; }
.brand-mark { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px; background: rgba(255,255,255,.12); color: white; font-size: 1.5rem; }
.brand-name { color: white; font-weight: 800; letter-spacing: -.02em; }
.brand-subtitle { color: #93c5fd; font-size: .78rem; }
.sidebar-nav { display: grid; gap: .35rem; }
.nav-item { display: flex; align-items: center; gap: .8rem; padding: .75rem .9rem; border-radius: 12px; color: #cbd5e1; font-weight: 600; font-size: .93rem; transition: .2s ease; }
.nav-item:hover { color: white; background: rgba(255,255,255,.08); }
.nav-item.active { color: white; background: linear-gradient(135deg, #2563eb, #3b82f6); box-shadow: 0 8px 20px rgba(37,99,235,.3); }
.sidebar-footer { margin-top: auto; border-top: 1px solid rgba(255,255,255,.12); padding-top: 1rem; }
.main-content { min-height: 100vh; flex: 1; margin-left: 260px; display: flex; flex-direction: column; }
.topbar { height: 76px; background: rgba(255,255,255,.94); backdrop-filter: blur(8px); display: flex; align-items: center; gap: 1rem; padding: 0 2rem; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1030; }
.role-pill { border: 1px solid var(--border); background: #fff; border-radius: 999px; padding: .5rem .8rem; font-size: .86rem; font-weight: 600; }
.content-wrap { padding: 2rem; flex: 1; }
.page-footer { padding: 1rem 2rem 1.5rem; color: var(--muted); font-size: .8rem; }

.panel-card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 1.25rem; box-shadow: 0 10px 30px rgba(15,23,42,.04); }
.panel-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; }
.panel-heading h2 { font-size: 1.05rem; margin: 0 0 .2rem; font-weight: 800; }
.panel-heading p { margin: 0; color: var(--muted); font-size: .87rem; }
.metric-card { height: 100%; display: flex; gap: 1rem; align-items: center; background: white; border: 1px solid var(--border); border-radius: 18px; padding: 1.2rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
.metric-icon { width: 48px; height: 48px; flex: 0 0 48px; display: grid; place-items: center; border-radius: 14px; background: #eff6ff; color: var(--primary); font-size: 1.25rem; }
.metric-card span, .mini-stat span { display: block; color: var(--muted); font-size: .8rem; }
.metric-card strong { display: block; font-size: 1.4rem; line-height: 1.2; margin-top: .2rem; }
.mini-stat { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 1rem; }
.mini-stat strong { display: block; font-size: 1.45rem; margin-top: .15rem; }

.parking-map { display: grid; grid-template-columns: repeat(10, minmax(55px, 1fr)); gap: .65rem; }
.parking-map-large { grid-template-columns: repeat(14, minmax(55px, 1fr)); }
.parking-space { min-height: 72px; border: 2px solid transparent; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; font-size: .78rem; font-weight: 800; transition: transform .15s ease; }
.parking-space:hover { transform: translateY(-2px); }
.parking-space i { font-size: 1.25rem; }
.parking-space small { font-size: .63rem; font-weight: 700; }
.status-available { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.status-reserved { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.status-occupied { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.status-maintenance { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.map-legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .8rem; color: var(--muted); }
.legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: .35rem; }
.legend-dot.available { background: #10b981; } .legend-dot.reserved { background: #f59e0b; } .legend-dot.occupied { background: #ef4444; } .legend-dot.maintenance { background: #64748b; }
.summary-list { display: grid; gap: .35rem; }
.summary-list > div { display: flex; justify-content: space-between; padding: .65rem 0; border-bottom: 1px dashed var(--border); }

.welcome-banner { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; color: white; padding: 1.6rem; border-radius: 20px; background: linear-gradient(135deg, #0f172a, #2563eb); box-shadow: 0 14px 35px rgba(37,99,235,.2); }
.welcome-banner h2 { font-size: 1.55rem; margin-bottom: .35rem; }
.eyebrow { text-transform: uppercase; letter-spacing: .12em; font-weight: 800; font-size: .72rem; color: #2563eb; }
.price-pill { padding: .5rem .8rem; background: #eff6ff; color: #1d4ed8; border-radius: 999px; font-weight: 800; font-size: .82rem; white-space: nowrap; }
.reservation-summary, .reservation-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .8rem; }
.reservation-summary > div, .reservation-grid > div { background: #f8fafc; border-radius: 12px; padding: .8rem; }
.reservation-summary span, .reservation-grid span { display: block; color: var(--muted); font-size: .75rem; }
.reservation-summary strong, .reservation-grid strong { display: block; margin-top: .2rem; }
.vehicle-card { height: 100%; display: flex; align-items: center; gap: 1rem; background: #fff; border: 1px solid var(--border); border-radius: 18px; padding: 1.15rem; }
.vehicle-visual { width: 58px; height: 58px; display: grid; place-items: center; background: #eff6ff; color: #2563eb; border-radius: 16px; font-size: 1.55rem; }
.yape-box { display: flex; align-items: center; gap: .9rem; padding: 1rem; border: 1px solid #e9d5ff; background: #faf5ff; border-radius: 14px; color: #6b21a8; }
.yape-box i { font-size: 1.6rem; } .yape-box span { display: block; font-size: .8rem; color: #7e22ce; }
.camera-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px; background: #eff6ff; color: #2563eb; font-size: 1.3rem; }
.table thead th { color: #64748b; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; border-bottom-width: 1px; white-space: nowrap; }
.table tbody td { font-size: .9rem; }

.login-body { min-height: 100vh; background: #eef3f9; }
.login-shell { min-height: 100vh; display: grid; grid-template-columns: 1.1fr .9fr; }
.login-hero { align-items: center; padding: 5rem; color: white; background: radial-gradient(circle at top right, rgba(59,130,246,.6), transparent 35%), linear-gradient(145deg,#0f172a,#1e3a8a); }
.login-hero-content { max-width: 620px; }
.login-hero h1 { font-size: clamp(2.6rem, 5vw, 5rem); font-weight: 900; letter-spacing: -.05em; }
.login-hero p { max-width: 560px; font-size: 1.08rem; color: #dbeafe; }
.hero-icon { width: 64px; height: 64px; display: grid; place-items: center; border-radius: 20px; background: rgba(255,255,255,.13); color: white; font-size: 2rem; margin-bottom: 1.5rem; }
.hero-features { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; color: #dbeafe; font-weight: 600; }
.login-panel { display: grid; place-items: center; padding: 2rem; }
.login-card { width: min(100%, 470px); background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 22px 60px rgba(15,23,42,.12); }
.demo-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 1rem; }

@media (max-width: 1199px) { .parking-map, .parking-map-large { grid-template-columns: repeat(8, minmax(52px,1fr)); } }
@media (max-width: 991px) {
  .sidebar { transform: translateX(-100%); transition: transform .2s ease; }
  .sidebar.show { transform: translateX(0); }
  .main-content { margin-left: 0; }
  .login-shell { grid-template-columns: 1fr; }
  .topbar { padding: 0 1rem; }
  .content-wrap { padding: 1rem; }
}
@media (max-width: 767px) {
  .parking-map, .parking-map-large { grid-template-columns: repeat(4, minmax(52px,1fr)); }
  .welcome-banner { align-items: flex-start; flex-direction: column; }
  .reservation-grid, .reservation-summary { grid-template-columns: 1fr 1fr; }
  .login-panel { padding: 1rem; }
  .login-card { padding: 1.35rem; border-radius: 18px; }
}
CSS

cat > frontend/assets/js/app.js <<'JS'
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (sidebar && toggle) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
        document.addEventListener('click', (event) => {
            if (window.innerWidth >= 992) return;
            if (!sidebar.classList.contains('show')) return;
            if (sidebar.contains(event.target) || toggle.contains(event.target)) return;
            sidebar.classList.remove('show');
        });
    }

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const selector = button.getAttribute('data-toggle-password');
            const input = document.querySelector(selector);
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            const icon = button.querySelector('i');
            if (icon) icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });
});
JS

# Verificacion de sintaxis PHP del frontend generado.
echo ""
echo "Frontend generado. Verificando sintaxis PHP..."

PHP_BIN="C:/xampp/php/php.exe"
if command -v "$PHP_BIN" >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    "$PHP_BIN" -l "$file"
  done < <(find frontend -name '*.php' -print0)
elif command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    php -l "$file"
  done < <(find frontend -name '*.php' -print0)
else
  echo "ADVERTENCIA: PHP CLI no fue encontrado. Ejecuta luego:"
  echo 'find frontend -name "*.php" -exec C:/xampp/php/php.exe -l {} \;'
fi

echo ""
echo "=============================================="
echo "MIGRACION DEL FRONTEND COMPLETADA"
echo "=============================================="
echo "Generado:"
echo "  - login y logout"
echo "  - dashboard administrador"
echo "  - reservas administrador"
echo "  - croquis de 40 espacios"
echo "  - vehiculos administrador"
echo "  - pagos Yape administrador"
echo "  - camaras"
echo "  - usuarios"
echo "  - auditoria"
echo "  - resumen financiero actual"
echo "  - dashboard conductor"
echo "  - reserva mensual"
echo "  - mis reservas"
echo "  - mis vehiculos"
echo "  - pagos Yape conductor"
echo "  - Bootstrap + CSS + JavaScript"
echo ""
echo "URL para probar:"
echo "  http://localhost/devioz-parking-php/frontend/login.php"
echo ""
echo "Siguiente paso recomendado:"
echo "  git status --short"
