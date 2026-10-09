<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/frontend/index.php');
}

try {
    verify_csrf();

    AuthController::register($_POST);

    flash(
        'success',
        'Cuenta creada correctamente. Bienvenido a Devioz Parking.'
    );

    redirect_to(
        '/frontend/conductor/dashboard.php'
    );

} catch (Throwable $e) {

    flash(
        'danger',
        $e->getMessage()
    );

    redirect_to(
        '/frontend/index.php?auth=register'
    );
}