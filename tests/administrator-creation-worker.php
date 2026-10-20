<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !preg_match('/^devioz_admin_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '')) {
    exit(1);
}
require dirname(__DIR__) . '/backend/bootstrap.php';
restore_exception_handler();
$_SESSION['user_id'] = (int) ($argv[1] ?? 0);
$_SESSION['session_version'] = 0;
try {
    AdminController::createAdministrator(['name' => 'Concurrente', 'email' => 'race@administrator.invalid',
        'password' => 'Testing456!', 'password_confirmation' => 'Testing456!']);
    echo 'created';
} catch (ValidationException $error) {
    if (!str_contains($error->getMessage(), 'correo ya está registrado')) {
        throw $error;
    }
    echo 'duplicate';
}
