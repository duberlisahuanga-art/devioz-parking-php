<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/frontend/login.php');
}

try {
    verify_csrf();
    AuthController::login($_POST);
    $user = current_user();

    if (($user['role'] ?? '') === 'admin') {
        redirect_to('/frontend/admin/dashboard.php');
    }

    redirect_to('/frontend/conductor/dashboard.php');
} catch (Throwable $e) {
    if ($e instanceof RateLimitException) {
        http_response_code(429);
        exit(htmlspecialchars(public_error_message($e), ENT_QUOTES, 'UTF-8'));
    }
    flash('danger', public_error_message($e));
    redirect_to('/frontend/login.php');
}
