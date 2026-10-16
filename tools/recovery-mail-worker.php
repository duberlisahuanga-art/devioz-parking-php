<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/bootstrap.php';
if (AppConfig::get('SMTP_PASSWORD') === '' || AppConfig::get('SMTP_HOST') === '') {
    exit("Pendiente: configurar SMTP privado. No se procesó la cola.\n");
}
RecoveryMailer::url(str_repeat('0', 64));
echo PasswordRecoveryService::deliver([RecoveryMailer::class,'send']) . " correos de recuperación enviados.\n";
