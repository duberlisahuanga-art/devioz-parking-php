<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Helpers visuales
|--------------------------------------------------------------------------
*/

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
        'active',
        'paid',
        'available',
        'approved' => 'text-bg-success',

        'confirmed',
        'reserved',
        'pending' => 'text-bg-warning',

        'occupied' => 'text-bg-danger',

        'cancelled',
        'rejected',
        'inactive' => 'text-bg-secondary',

        'maintenance',
        'expired',
        'unpaid' => 'text-bg-dark',

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

/*
|--------------------------------------------------------------------------
| Datos visuales del usuario
|--------------------------------------------------------------------------
*/

function role_label(string $role): string
{
    return $role === 'admin'
        ? 'Administrador'
        : 'Conductor';
}

function role_icon(string $role): string
{
    return $role === 'admin'
        ? 'bi-shield-check'
        : 'bi-person-badge';
}

function user_initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name) ?: [];

    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(
            mb_substr($part, 0, 1)
        );
    }

    return $initials !== ''
        ? $initials
        : 'U';
}

/*
|--------------------------------------------------------------------------
| Mensajes Flash
|--------------------------------------------------------------------------
*/

function flash_message(): string
{
    $flash = consume_flash();

    if (!$flash) {
        return '';
    }

    $type = in_array(
        ($flash['type'] ?? ''),
        ['success', 'danger', 'warning', 'info'],
        true
    )
        ? $flash['type']
        : 'info';

    $icon = match ($type) {
        'success' => 'bi-check-circle-fill',
        'danger' => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default => 'bi-info-circle-fill',
    };

    return '
        <div class="alert alert-'
        . h($type)
        . ' alert-dismissible fade show app-alert shadow-sm"
             role="alert">

            <div class="d-flex align-items-center gap-2">
                <i class="bi '
                . h($icon)
                . '"></i>

                <span>'
                . h($flash['message'] ?? '')
                . '</span>
            </div>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar">
            </button>

        </div>
    ';
}

/*
|--------------------------------------------------------------------------
| Menú lateral
|--------------------------------------------------------------------------
*/

function menu_items(string $role): array
{
    if ($role === 'admin') {
        return [

            [
                'dashboard',
                'bi-speedometer2',
                'Dashboard',
                '/frontend/admin/dashboard.php',
                'Resumen general',
            ],

            [
                'reservas',
                'bi-calendar-check',
                'Reservas',
                '/frontend/admin/reservas.php',
                'Gestión de reservas',
            ],

            [
                'espacios',
                'bi-grid-3x3-gap',
                'Espacios',
                '/frontend/admin/espacios.php',
                'Control de plazas',
            ],

            [
                'vehiculos',
                'bi-car-front',
                'Vehículos',
                '/frontend/admin/vehiculos.php',
                'Vehículos registrados',
            ],

            [
                'pagos',
                'bi-wallet2',
                'Pagos',
                '/frontend/admin/pagos.php',
                'Pagos y comprobantes',
            ],

            [
                'camaras',
                'bi-camera-video',
                'Cámaras',
                '/frontend/admin/camaras.php',
                'Videovigilancia',
            ],

            [
                'usuarios',
                'bi-people',
                'Usuarios',
                '/frontend/admin/usuarios.php',
                'Conductores y administradores',
            ],

            [
                'finanzas',
                'bi-graph-up-arrow',
                'Finanzas',
                '/frontend/admin/finanzas.php',
                'Ingresos y egresos',
            ],

            [
                'auditoria',
                'bi-shield-check',
                'Auditoría',
                '/frontend/admin/auditoria.php',
                'Registro de actividad',
            ],
        ];
    }

    return [

        [
            'dashboard',
            'bi-speedometer2',
            'Dashboard',
            '/frontend/conductor/dashboard.php',
            'Mi resumen',
        ],

        [
            'reservar',
            'bi-plus-circle',
            'Reservar plaza',
            '/frontend/conductor/reservar.php',
            'Nueva mensualidad',
        ],

        [
            'reservas',
            'bi-calendar-check',
            'Mis reservas',
            '/frontend/conductor/reservas.php',
            'Historial y estado',
        ],

        [
            'vehiculos',
            'bi-car-front',
            'Mis vehículos',
            '/frontend/conductor/vehiculos.php',
            'Vehículos registrados',
        ],

        [
            'pagos',
            'bi-phone',
            'Pagos Yape',
            '/frontend/conductor/pagos.php',
            'Comprobantes y pagos',
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| Descripción de páginas
|--------------------------------------------------------------------------
*/

function page_description(string $active, string $role): string
{
    if ($role === 'admin') {
        return match ($active) {
            'dashboard' => 'Resumen operativo del estacionamiento.',
            'reservas' => 'Administra mensualidades, ingresos, salidas y cancelaciones.',
            'espacios' => 'Consulta y administra las 40 plazas del estacionamiento.',
            'vehiculos' => 'Visualiza los vehículos registrados en la plataforma.',
            'pagos' => 'Revisa comprobantes y pagos realizados mediante Yape.',
            'camaras' => 'Supervisa las cámaras de videovigilancia.',
            'usuarios' => 'Administra conductores y cuentas del sistema.',
            'finanzas' => 'Controla ingresos, egresos y movimientos financieros.',
            'auditoria' => 'Consulta el historial de acciones realizadas.',
            default => 'Gestión de Devioz Parking.',
        };
    }

    return match ($active) {
        'dashboard' => 'Consulta el estado de tu mensualidad y tu plaza.',
        'reservar' => 'Solicita una nueva plaza de estacionamiento.',
        'reservas' => 'Consulta tus reservas y mensualidades.',
        'vehiculos' => 'Administra tus vehículos registrados.',
        'pagos' => 'Realiza y consulta tus pagos mediante Yape.',
        default => 'Panel del conductor.',
    };
}

/*
|--------------------------------------------------------------------------
| Inicio del Layout
|--------------------------------------------------------------------------
*/

function render_page_start(
    string $title,
    string $active
): void {

    $user = require_auth();

    $role = (string) (
        $user['role'] ?? 'driver'
    );

    $roleLabel = role_label($role);

    $items = menu_items($role);

    $userName = (string) (
        $user['name'] ?? 'Usuario Devioz'
    );

    $userEmail = (string) (
        $user['email'] ?? ''
    );

    $initials = user_initials($userName);

    $description = page_description(
        $active,
        $role
    );

    ?>
<!doctype html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="Sistema de gestión de estacionamiento Devioz Parking"
    >

    <title>
        <?= h($title) ?> | Devioz Parking
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- CSS Devioz -->
    <link
        href="<?= h(
            app_url(
                '/frontend/assets/css/styles.css'
            )
        ) ?>"
        rel="stylesheet"
    >

</head>

<body>

<div class="app-shell">

    <!-- Overlay móvil -->
    <div
        class="sidebar-overlay"
        id="sidebarOverlay">
    </div>

    <!-- ==========================================================
         SIDEBAR
    =========================================================== -->

    <aside
        class="sidebar"
        id="sidebar">

        <!-- Marca -->
        <div class="brand-wrap">

            <div class="brand-mark">
                <i class="bi bi-p-square-fill"></i>
            </div>

            <div class="brand-text">

                <div class="brand-name">
                    Devioz Parking
                </div>

                <div class="brand-subtitle">
                    Gestión inteligente
                </div>

            </div>

            <button
                type="button"
                class="sidebar-collapse-toggle d-none d-lg-grid"
                id="sidebarCollapseToggle"
                aria-controls="sidebar"
                aria-label="Minimizar menú lateral"
                aria-expanded="true"
                title="Minimizar menú lateral">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>

            <button
                type="button"
                class="sidebar-close d-lg-none"
                id="sidebarClose"
                aria-label="Cerrar menú">

                <i class="bi bi-x-lg"></i>

            </button>

        </div>

        <!-- Navegación -->
        <div class="sidebar-section-label">
            MENÚ PRINCIPAL
        </div>

        <nav
            class="sidebar-nav"
            aria-label="Navegación principal">

            <?php foreach (
                $items
                as [
                    $key,
                    $icon,
                    $label,
                    $url,
                    $subtitle
                ]
            ): ?>

                <a
                    class="nav-item <?= $active === $key
                        ? 'active'
                        : '' ?>"
                    href="<?= h(
                        app_url($url)
                    ) ?>"
                    <?= $active === $key
                        ? 'aria-current="page"'
                        : '' ?>
                    title="<?= h($label) ?>"
                    aria-label="<?= h($label) ?>"
                >

                    <span class="nav-icon">

                        <i class="bi <?= h($icon) ?>"></i>

                    </span>

                    <span class="nav-item-content">

                        <span class="nav-item-title">
                            <?= h($label) ?>
                        </span>

                    </span>

                    <?php if ($active === $key): ?>

                        <span class="nav-active-indicator"></span>

                    <?php endif; ?>

                </a>

            <?php endforeach; ?>

        </nav>

        <!-- Pie sidebar -->
        <div class="sidebar-footer">

            <div class="sidebar-session">

                <div class="sidebar-session-icon">
                    <i class="bi bi-person-check"></i>
                </div>

                <div class="sidebar-session-info">

                    <span>
                        Sesión iniciada
                    </span>

                    <small
                        class="text-truncate"
                        title="<?= h($userEmail) ?>">

                        <?= h($userEmail) ?>

                    </small>

                </div>

            </div>

            <a
                class="btn btn-sidebar-logout"
                aria-label="Cerrar sesión"
                title="Cerrar sesión"
                href="<?= h(
                    app_url(
                        '/frontend/logout.php'
                    )
                ) ?>">

                <i class="bi bi-box-arrow-right"></i>

                <span>
                    Cerrar sesión
                </span>

            </a>

        </div>

    </aside>

    <!-- ==========================================================
         CONTENIDO
    =========================================================== -->

    <main class="main-content">

        <!-- Topbar -->
        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="btn topbar-menu-btn d-lg-none"
                    type="button"
                    id="sidebarToggle"
                    aria-label="Abrir menú"
                    aria-controls="sidebar"
                    aria-expanded="false">

                    <i class="bi bi-list"></i>

                </button>

                <div>

                    <div class="page-breadcrumb">

                        <i class="bi bi-house-door"></i>

                        <span>
                            Devioz Parking
                        </span>

                        <i class="bi bi-chevron-right"></i>

                        <span>
                            <?= h($roleLabel) ?>
                        </span>

                    </div>

                    <h1 class="page-title">
                        <?= h($title) ?>
                    </h1>

                    <p class="page-description">
                        <?= h($description) ?>
                    </p>

                </div>

            </div>

            <div class="topbar-actions">

                <!-- Usuario -->
                <div class="topbar-user">

                    <div class="topbar-avatar">
                        <?= h($initials) ?>
                    </div>

                    <div class="topbar-user-data d-none d-md-block">

                        <span class="topbar-user-name">
                            <?= h($userName) ?>
                        </span>

                        <span class="topbar-user-role">
                            <?= h($roleLabel) ?>
                        </span>

                    </div>

                </div>

            </div>

        </header>

        <!-- Área central -->
        <section class="content-wrap">

            <?= flash_message() ?>

    <?php
}

/*
|--------------------------------------------------------------------------
| Fin del Layout
|--------------------------------------------------------------------------
*/

function render_page_end(): void
{
    ?>

        </section>

        <footer class="page-footer">

            <div>

                <strong>
                    Devioz Parking
                </strong>

                <span class="footer-separator">
                    •
                </span>

                Sistema de Gestión de Estacionamiento

            </div>

            <div class="footer-tech">
                PHP 8 · MariaDB · Bootstrap
            </div>

        </footer>

    </main>

</div>

<!-- Bootstrap -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<!-- JS Devioz -->
<script
    src="<?= h(
        app_url(
            '/frontend/assets/js/app.js'
        )
    ) ?>">
</script>

</body>

</html>

    <?php
}

/*
|--------------------------------------------------------------------------
| Acción POST reutilizable
|--------------------------------------------------------------------------
*/

function run_post_action(
    callable $action,
    string $success,
    string $redirect
): never {

    try {

        verify_csrf();

        $action();

        flash(
            'success',
            $success
        );

    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );
    }

    redirect_to($redirect);
}