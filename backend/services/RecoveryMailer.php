<?php

declare(strict_types=1);

final class RecoveryMailer
{
    public static function url(string $token): string
    {
        $base = rtrim(AppConfig::get('PASSWORD_RESET_BASE_URL'), '/');
        $parts = parse_url($base);
        if (
            !$parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            || ($parts['scheme'] !== 'https' && !in_array($parts['host'], ['localhost', '127.0.0.1'], true))
        ) {
            throw new RuntimeException('PASSWORD_RESET_BASE_URL debe ser HTTPS o localhost para pruebas.');
        }
        return $base . '/frontend/restablecer-password.php?token=' . rawurlencode($token);
    }

    public static function send(string $email, string $token): void
    {
        $host = AppConfig::get('SMTP_HOST');
        $sender = AppConfig::get('SMTP_FROM');
        if ($host === '' || !filter_var($sender, FILTER_VALIDATE_EMAIL) || AppConfig::get('SMTP_PASSWORD') === '') {
            throw new RuntimeException('Configura SMTP antes de enviar recuperaciones.');
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) AppConfig::get('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = AppConfig::get('SMTP_USERNAME');
        $mail->Password = AppConfig::get('SMTP_PASSWORD');
        $security = AppConfig::get('SMTP_ENCRYPTION', 'tls');
        if (!in_array($security, ['tls', 'ssl'], true)) {
            throw new RuntimeException('SMTP requiere TLS o SSL.');
        }
        $mail->SMTPSecure = $security;
        $mail->Timeout = 10;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($sender, 'DEVIOZ Parking');
        $mail->addAddress($email);
        $mail->Subject = 'Recupera tu contraseña de DEVIOZ Parking';
        $mail->Body = "Recibimos una solicitud para recuperar tu cuenta.\n\n"
            . "Abre este enlace para elegir una contraseña nueva:\n" . self::url($token)
            . "\n\nEl enlace vence 30 minutos después de solicitarlo y sólo puede usarse una vez."
            . "\nSi no lo solicitaste, ignora este mensaje. Tu contraseña no ha cambiado.";
        $mail->send();
    }
}
