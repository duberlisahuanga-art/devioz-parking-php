<?php

declare(strict_types=1);

final class AppConfig
{
    private static ?array $local = null;

    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (self::$local === null) {
            $path = __DIR__ . '/local.php';
            self::$local = is_file($path) && getenv('APP_ENV') !== 'production' ? require $path : [];
        }
        return (string) (self::$local[$key] ?? $default);
    }

    public static function production(): bool
    {
        return self::get('APP_ENV', 'development') === 'production';
    }

    public static function isHttps(): bool
    {
        return RequestSecurity::isHttps($_SERVER, self::get('TRUSTED_PROXY_IPS'));
    }

    public static function storage(): string
    {
        return self::get('APP_STORAGE', dirname(__DIR__) . '/storage');
    }
}

require_once __DIR__ . '/../services/RequestSecurity.php';

date_default_timezone_set('America/Lima');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (AppConfig::production()) {
        if (!AppConfig::isHttps()) {
            http_response_code(503);
            exit('El servicio requiere HTTPS.');
        }
        header('Strict-Transport-Security: max-age=31536000');
    }
}
