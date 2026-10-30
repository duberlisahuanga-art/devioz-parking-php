<?php

declare(strict_types=1);

final class CameraBridgeException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 503)
    {
        parent::__construct($message);
    }
}

final class CameraBridgeService
{
    public static function configuration(): array
    {
        $path = AppConfig::get('CAMERA_BRIDGE_CONFIG', AppConfig::storage() . '/go2rtc/private.json');
        try {
            $raw = is_file($path) ? file_get_contents($path) : false;
            $configuration = $raw === false ? null : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $configuration = null;
        }
        if (
            !is_array($configuration)
            || !is_string($configuration['api']['listen'] ?? null)
            || !preg_match('/^127\.0\.0\.1:([1-9][0-9]{0,4})$/D', $configuration['api']['listen'] ?? '', $match)
            || (int) $match[1] > 65535
            || !is_string($configuration['api']['username'] ?? null)
            || !is_string($configuration['api']['password'] ?? null)
            || strlen($configuration['api']['password']) < 16
            || ($configuration['api']['local_auth'] ?? false) !== true
        ) {
            throw new CameraBridgeException('Falta configurar el acceso privado al DVR con el operador.');
        }
        return $configuration;
    }

    public static function configured(): bool
    {
        try {
            self::configuration();
            return true;
        } catch (CameraBridgeException) {
            return false;
        }
    }

    private static function request(string $method, string $path, ?string $body = null): string
    {
        $configuration = self::configuration();
        if (!extension_loaded('curl')) {
            throw new CameraBridgeException('El servidor necesita habilitar cURL para conectar las cámaras.');
        }
        $handle = curl_init('http://' . $configuration['api']['listen'] . $path);
        $response = '';
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $configuration['api']['username'] . ':' . $configuration['api']['password'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/sdp', 'Accept: application/sdp, application/json'],
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_TIMEOUT_MS => $method === 'GET' ? 2000 : 8000,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 131072) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($success === false || $status === 0) {
            throw new CameraBridgeException('El servicio de cámaras no responde. Revisa que el puente esté iniciado.');
        }
        if ($status < 200 || $status >= 300) {
            // Never forward upstream errors: they can contain the full RTSP URL and credentials.
            if (str_contains(strtolower($response), '401') || str_contains(strtolower($response), 'unauthorized')) {
                throw new CameraBridgeException('El DVR rechazó el acceso. Revisa las credenciales y permisos de vídeo.', 502);
            }
            throw new CameraBridgeException('No se recibió vídeo del canal. Revisa conexión, canal y codificación H.264 del DVR.', 502);
        }
        return $response;
    }

    public static function status(): array
    {
        require_admin();
        try {
            $configuration = self::configuration();
            $response = json_decode(self::request('GET', '/api'), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($response) || !is_string($response['version'] ?? null)) {
                throw new CameraBridgeException('El servicio de cámaras devolvió una respuesta inesperada.');
            }
            return ['configured' => true, 'running' => true, 'version' => $response['version'],
                'dvr' => $configuration['devioz']['dvr_host'] ?? '',
                'message' => 'Puente disponible. La imagen de cada canal confirma la recepción de vídeo.'];
        } catch (Throwable $error) {
            return ['configured' => self::configured(), 'running' => false,
                'message' => $error instanceof CameraBridgeException ? $error->getMessage() : 'No se pudo comprobar el servicio de cámaras.'];
        }
    }

    public static function source(array $camera, string $quality): string
    {
        if (
            ($camera['status'] ?? '') !== 'active' || ($camera['stream_protocol'] ?? '') !== 'webrtc'
            || !in_array((int) ($camera['channel_number'] ?? 0), [1, 2, 3, 4], true)
            || !in_array($quality, ['sub', 'main'], true)
        ) {
            throw new ValidationException('Elige una cámara activa con WebRTC y una calidad válida.');
        }
        return 'dvr_ch' . (int) $camera['channel_number'] . '_' . $quality;
    }

    public static function validateOffer(string $sdp): void
    {
        if (
            strlen($sdp) > 65536 || !str_starts_with($sdp, "v=0\r\n") || str_contains($sdp, "\0")
            || preg_match_all('/^m=/m', $sdp) !== 1 || !preg_match('/^m=video /m', $sdp)
            || !preg_match('/^a=recvonly\r?$/m', $sdp)
            || preg_match('/^a=(?:sendrecv|sendonly)\r?$/m', $sdp)
        ) {
            throw new ValidationException('La solicitud de vídeo no es válida. Vuelve a conectar el canal.');
        }
    }

    public static function signal(int $cameraId, string $quality, string $sdp): array
    {
        require_admin();
        self::validateOffer($sdp);
        $camera = Camera::find($cameraId);
        if (!$camera) {
            throw new ValidationException('Cámara no encontrada.');
        }
        $source = self::source($camera, $quality);
        $configuration = self::configuration();
        if (!array_key_exists($source, $configuration['streams'] ?? [])) {
            throw new CameraBridgeException('Este canal no está configurado en el puente de cámaras.');
        }
        $answer = self::request('POST', '/api/webrtc?src=' . rawurlencode($source), $sdp);
        if (strlen($answer) > 65536 || !str_starts_with($answer, 'v=0')) {
            throw new CameraBridgeException('No se pudo negociar el vídeo del canal.', 502);
        }
        return ['type' => 'answer', 'sdp' => $answer];
    }

    public static function enableDvr(int $parkingId): void
    {
        require_admin();
        self::configuration();
        if (!Parking::find($parkingId)) {
            throw new ValidationException('Selecciona un estacionamiento válido.');
        }
        transaction(static function () use ($parkingId): void {
            db()->prepare('SELECT id FROM parkings WHERE id=? FOR UPDATE')->execute([$parkingId]);
            for ($channel = 1; $channel <= 4; $channel++) {
                $query = db()->prepare('SELECT * FROM cameras WHERE parking_id=? AND channel_number=? FOR UPDATE');
                $query->execute([$parkingId, $channel]);
                $previous = $query->fetch();
                $values = [
                    'parking_id' => $parkingId, 'name' => 'Canal ' . $channel, 'coverage' => null,
                    'location' => null, 'status' => 'active', 'channel_number' => $channel,
                    'brightness' => 100, 'contrast' => 100, 'saturation' => 100,
                    'osd_datetime' => 1, 'osd_camera_name' => 1,
                    ...($previous ?: []),
                    'stream_url' => null, 'stream_protocol' => 'webrtc', 'preferred_quality' => 'sub',
                    'rtsp_path' => '/Streaming/Channels/' . $channel . '02',
                ];
                $id = $previous ? (int) $previous['id'] : Camera::create($values);
                if ($previous) {
                    Camera::update($id, $values);
                }
                AuditService::log('Camaras', 'DVR configurado', 'Canal preparado para visualización WebRTC.',
                    'Camera', $id, $values['name'], ['channel' => $channel, 'quality' => 'sub']);
            }
        });
    }
}
