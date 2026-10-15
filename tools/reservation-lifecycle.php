<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$finished = false;
register_shutdown_function(static function () use (&$finished): void {
    if (!$finished) {
        fwrite(STDERR, "El mantenimiento no pudo inicializarse. Revisa PHP y MariaDB.\n");
        exit(1);
    }
});
require_once __DIR__ . '/../backend/bootstrap.php';
restore_exception_handler();
try {
    $result = json_encode(ReservationLifecycleService::run(), JSON_THROW_ON_ERROR);
    $logged = file_put_contents(
        AppConfig::storage() . '/reservation-lifecycle.log',
        date('c') . ' ' . $result . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    if ($logged === false) {
        throw new RuntimeException('No se pudo escribir el registro del mantenimiento.');
    }
    $finished = true;
    echo $result . PHP_EOL;
} catch (Throwable $error) {
    $finished = true;
    error_log('DEVIOZ lifecycle: ' . $error->getMessage());
    fwrite(STDERR, "No se pudo completar el mantenimiento de mensualidades. Revisa el registro de PHP.\n");
    exit(1);
}
