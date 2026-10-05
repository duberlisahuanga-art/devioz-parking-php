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
