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
    <title>Devioz Parking</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(app_url('/frontend/assets/favicon.png?v=' . filemtime(__DIR__ . '/assets/favicon.png')), ENT_QUOTES, 'UTF-8') ?>">
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
                    <label class="form-label" for="loginEmail">Correo electrónico</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input class="form-control" id="loginEmail" type="email" name="email" required autocomplete="email" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div>
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit">Ingresar</button>
                <a href="<?= htmlspecialchars(app_url('/frontend/recuperar-password.php'), ENT_QUOTES, 'UTF-8') ?>">Olvidé mi contraseña</a>
            </form>


        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars(app_url('/frontend/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
