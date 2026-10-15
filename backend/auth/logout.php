<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Usa el botón Cerrar sesión.');
}
try {
    verify_csrf();
    AuthController::logout();
} catch (Throwable $e) {
    http_response_code(403);
    exit(htmlspecialchars(public_error_message($e), ENT_QUOTES, 'UTF-8'));
}
redirect_to('/frontend/index.php');
