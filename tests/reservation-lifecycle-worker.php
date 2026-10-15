<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !preg_match('/^devioz_lifecycle_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '')) {
    exit(1);
}
require __DIR__ . '/../backend/bootstrap.php';
restore_exception_handler();
if (($argv[1] ?? '') === 'renew') {
    $_SESSION['user_id'] = 2;
    echo ReservationController::renew((int) ($argv[2] ?? 0));
} else {
    echo json_encode(ReservationLifecycleService::run(), JSON_THROW_ON_ERROR);
}
