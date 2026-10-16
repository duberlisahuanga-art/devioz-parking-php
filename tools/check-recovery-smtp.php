<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
if (AppConfig::get('SMTP_PASSWORD') === '') {
    fwrite(STDERR, "Falta configurar secreto SMTP.\n");
    exit(1);
}
if (AppConfig::get('SMTP_HOST') === 'smtp.gmail.com' && strlen(AppConfig::get('SMTP_PASSWORD')) !== 16) {
    fwrite(STDERR, 'La clave Gmail guardada tiene ' . strlen(AppConfig::get('SMTP_PASSWORD')) . " caracteres; se esperan 16. No se muestra su contenido.\n");
    exit(1);
}
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->isSMTP();
$mail->Host = AppConfig::get('SMTP_HOST');
$mail->Port = (int) AppConfig::get('SMTP_PORT', '587');
$mail->SMTPAuth = true;
$mail->Username = AppConfig::get('SMTP_USERNAME');
$mail->Password = AppConfig::get('SMTP_PASSWORD');
$mail->SMTPSecure = AppConfig::get('SMTP_ENCRYPTION', 'tls');
$mail->Timeout = 15;
$smtpCode = '';
$tlsFailure = false;
$mail->SMTPDebug = 2;
$mail->Debugoutput = static function (string $line, int $level) use (&$smtpCode, &$tlsFailure): void {
    // Inspect only diagnostic categories; never print or persist the SMTP transcript.
    if (preg_match('/SERVER -> CLIENT: (53[45])\b/', $line, $match)) $smtpCode = $match[1];
    if (stripos($line, 'certificate') !== false || stripos($line, 'crypto') !== false) $tlsFailure = true;
};
try {
    if (!$mail->smtpConnect()) {
        throw new RuntimeException('SMTP rechazado');
    }
    $mail->smtpClose();
    echo "OK: conexión TLS y autenticación SMTP aceptadas; no se enviaron mensajes.\n";
} catch (Throwable $exception) {
    $error = $mail->getSMTPInstance()->getError();
    $diagnostic = strtolower($exception->getMessage() . ' ' . $mail->ErrorInfo . ' ' . json_encode($error));
    $code = $smtpCode ?: preg_replace('/[^0-9.]/', '', (string) ($error['smtp_code'] ?? ''));
    $category = match (true) {
        $tlsFailure, str_contains($diagnostic, 'certificate'), str_contains($diagnostic, 'crypto') => 'Certificados TLS de PHP/XAMPP',
        $code === '535', $code === '534' => 'Credenciales o autorización de Gmail',
        str_contains($diagnostic, 'connect'), str_contains($diagnostic, 'timed out') => 'Conexión o TLS',
        default => 'Autenticación SMTP',
    };
    fwrite(STDERR, 'Fallo: ' . $category . '; código SMTP: ' . ($code ?: 'no disponible') . ". No se muestran credenciales.\n");
    exit(1);
}
