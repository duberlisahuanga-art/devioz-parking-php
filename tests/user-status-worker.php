<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || preg_match('/^devioz_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '') !== 1) exit(1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';
restore_exception_handler();
$_SESSION['user_id'] = (int)($argv[1] ?? 0);
$_SESSION['session_version'] = 0;
require_admin();
$start = (float)($argv[3] ?? 0);
while (microtime(true) < $start) usleep(1000);
try {
    AdminController::changeUserStatus(['user_id'=>$argv[2] ?? '', 'status'=>'inactive']);
    echo 'ACCEPTED';
} catch (ValidationException) {
    echo 'DENIED';
}
