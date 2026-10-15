<?php

declare(strict_types=1);

final class RateLimiter
{
    public static function consume(string $key, int $limit = 10, int $window = 900): void
    {
        $secret = AppConfig::get('APP_SECRET');
        if (strlen($secret) < 32) {
            throw new RuntimeException('APP_SECRET no configurado.');
        }
        $directory = AppConfig::storage() . '/rate-limits';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el almacenamiento de límites.');
        }
        $path = $directory . '/' . hash_hmac('sha256', $key, $secret) . '.json';
        $file = fopen($path, 'c+');
        if ($file === false || !flock($file, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el contador.');
        }
        try {
            $state = json_decode(stream_get_contents($file), true);
            $now = time();
            if (!is_array($state) || ($state['until'] ?? 0) <= $now) {
                $state = ['until' => $now + $window, 'count' => 0];
            }
            if ($state['count'] >= $limit) {
                if (PHP_SAPI !== 'cli') {
                    http_response_code(429);
                    header('Retry-After: ' . max(1, $state['until'] - $now));
                }
                throw new RateLimitException('Demasiados intentos. Espera unos minutos antes de volver a intentar.');
            }
            $state['count']++;
            rewind($file);
            ftruncate($file, 0);
            if (fwrite($file, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('No se pudo guardar el contador.');
            }
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
