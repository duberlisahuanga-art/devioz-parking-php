<?php

declare(strict_types=1);

final class RequestSecurity
{
    private static function trusted(array $server, string $trustedIps): bool
    {
        $remote = $server['REMOTE_ADDR'] ?? null;
        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false
            && in_array($remote, array_map('trim', explode(',', $trustedIps)), true);
    }

    public static function isHttps(array $server, string $trustedIps = ''): bool
    {
        if (($server['HTTPS'] ?? '') === 'on') {
            return true;
        }
        return self::trusted($server, $trustedIps)
            && ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function clientIp(array $server, string $trustedIps = ''): ?string
    {
        // Only a specifically trusted cloudflared peer may supply this header.
        $forwarded = $server['HTTP_CF_CONNECTING_IP'] ?? null;
        if (self::trusted($server, $trustedIps) && is_string($forwarded)
            && filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            return $forwarded;
        }
        $remote = $server['REMOTE_ADDR'] ?? null;
        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : null;
    }
}
