<?php

declare(strict_types=1);

final class CameraIceService
{
    public static function forAdmin(): array
    {
        $user = require_admin();
        $path = AppConfig::get('CAMERA_ICE_CONFIG', AppConfig::storage() . '/go2rtc/ice.private.json');
        if (!is_file($path)) {
            return self::build([], (int) $user['id']);
        }
        try {
            $configuration = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($configuration)) {
                throw new RuntimeException('Invalid configuration');
            }
            return self::build($configuration, (int) $user['id']);
        } catch (Throwable) {
            throw new CameraBridgeException('Revisa la configuración privada de conexión remota con el operador.');
        }
    }

    public static function build(array $configuration, int $userId, ?int $now = null): array
    {
        $now ??= time();
        $servers = [];
        foreach (['stun_urls', 'turn_urls'] as $key) {
            $urls = $configuration[$key] ?? [];
            if (!is_array($urls) || count($urls) > 4) {
                throw new InvalidArgumentException('Invalid ICE URLs');
            }
            foreach ($urls as $url) {
                $scheme = $key === 'stun_urls' ? 'stun' : 'turns?';
                if (!is_string($url) || strlen($url) > 255
                    || !preg_match('~^' . $scheme . ':([a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::([0-9]{1,5}))?(?:\?transport=(udp|tcp))?$~D', $url, $match)
                    || (isset($match[2]) && $match[2] !== '' && ((int) $match[2] < 1 || (int) $match[2] > 65535))) {
                    throw new InvalidArgumentException('Invalid ICE URL');
                }
            }
            if ($key === 'stun_urls' && $urls !== []) {
                $servers[] = ['urls' => array_values($urls)];
            }
        }
        $ttl = $configuration['ttl_seconds'] ?? 600;
        $policy = $configuration['transport_policy'] ?? 'all';
        if (!is_int($ttl) || $ttl < 120 || $ttl > 3600 || !in_array($policy, ['all', 'relay'], true)) {
            throw new InvalidArgumentException('Invalid ICE policy');
        }
        $turnUrls = $configuration['turn_urls'] ?? [];
        if ($policy === 'relay' && $turnUrls === []) {
            throw new InvalidArgumentException('Relay requires TURN');
        }
        if ($turnUrls !== []) {
            $secret = $configuration['turn_shared_secret'] ?? '';
            if (!is_string($secret) || strlen($secret) < 32 || strlen($secret) > 4096 || $userId < 1) {
                throw new InvalidArgumentException('Invalid TURN secret');
            }
            $username = ($now + $ttl) . ':devioz-' . $userId;
            $servers[] = ['urls' => array_values($turnUrls), 'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true))];
        }
        return ['iceServers' => $servers, 'iceTransportPolicy' => $policy, 'expiresAt' => $now + $ttl];
    }
}
