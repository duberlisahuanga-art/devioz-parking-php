<?php

declare(strict_types=1);

final class CameraController
{
    public static function index(): array
    {
        require_admin();
        return array_map([self::class, 'publicData'], Camera::all());
    }

    // Legacy unsafe values stay in the database, but never reach HTML/JSON.
    public static function publicData(array $camera): array
    {
        // Verified legacy CP850 decoding artifact: repair presentation only.
        // Keep the stored value intact; unrelated or invalid conversions are ignored.
        if (str_contains((string) ($camera['name'] ?? ''), '├')) {
            $decoded = @iconv('UTF-8', 'CP850', (string) $camera['name']);
            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                $camera['name'] = $decoded;
            }
        }
        if (!self::safeWebEndpoint((string) ($camera['stream_url'] ?? ''))) {
            $camera['stream_url'] = '';
        }
        if (!self::safeRtspPath((string) ($camera['rtsp_path'] ?? ''))) {
            $camera['rtsp_path'] = '';
        }
        return $camera;
    }

    public static function safeWebEndpoint(string $url): bool
    {
        if ($url === '') return true;
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|20|7f)/i', $url)) return false;
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return false;
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) return false;
        // Only the published endpoint is accepted, never embedded DVR credentials.
        $decoded = rawurldecode($url);
        return !preg_match('/(?:rtsp:|@|(?:password|passwd|pass|pwd|user|contrase(?:ña|na)|username|usuario)\s*[=:])/iu', $decoded);
    }

    public static function safeRtspPath(string $path): bool
    {
        return $path === '' || preg_match('~^/Streaming/Channels/[1-4]0[12]$~D', $path) === 1;
    }

    private static function validate(array $data, ?int $cameraId = null): array
    {
        $result = [];
        foreach (['name' => 150, 'coverage' => 255, 'location' => 150, 'rtsp_path' => 255, 'stream_url' => 500] as $field => $limit) {
            if (isset($data[$field]) && !is_string($data[$field])) throw new RuntimeException('Formato de texto inválido.');
            $value = trim($data[$field] ?? '');
            if (mb_strlen($value) > $limit) throw new RuntimeException("El campo {$field} supera {$limit} caracteres.");
            $result[$field] = $value === '' && $field !== 'name' ? null : $value;
        }
        if ($result['name'] === '') throw new RuntimeException('El nombre de la cámara es obligatorio.');
        $parking = filter_var($data['parking_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$parking || !Parking::find($parking)) throw new RuntimeException('Estacionamiento inválido.');
        $result['parking_id'] = $parking;
        foreach (['channel_number' => [1, 4], 'brightness' => [0, 200], 'contrast' => [0, 200], 'saturation' => [0, 200]] as $field => [$min, $max]) {
            $value = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value === null || $value < $min || $value > $max) throw new RuntimeException("Valor inválido para {$field}.");
            $result[$field] = $value;
        }
        foreach (['status' => ['active', 'inactive'], 'stream_protocol' => ['hls', 'webrtc'], 'preferred_quality' => ['sub', 'main']] as $field => $allowed) {
            if (!in_array($data[$field] ?? null, $allowed, true)) throw new RuntimeException("Valor inválido para {$field}.");
            $result[$field] = $data[$field];
        }
        foreach (['osd_datetime', 'osd_camera_name'] as $field) {
            $value = $data[$field] ?? '0';
            if (!in_array($value, ['0', '1', 0, 1], true)) throw new RuntimeException('Valor OSD inválido.');
            $result[$field] = (int) $value;
        }
        if (!self::safeWebEndpoint($result['stream_url'] ?? '')) throw new RuntimeException('Utiliza un endpoint HTTP/HTTPS sin credenciales.');
        if (!self::safeRtspPath($result['rtsp_path'] ?? '')) throw new RuntimeException('Ruta interna inválida. Usa /Streaming/Channels/X01 o X02.');
        if ($result['rtsp_path'] !== null && (int) substr($result['rtsp_path'], -3, 1) !== $result['channel_number']) {
            throw new RuntimeException('La ruta interna debe corresponder al canal físico.');
        }
        $existing = Camera::findByChannel($parking, $result['channel_number']);
        if ($existing && (int) $existing['id'] !== $cameraId) throw new RuntimeException('Ese canal ya está configurado en el estacionamiento.');
        return $result;
    }

    private static function persist(callable $action): mixed
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $result = $action();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException) {
                throw new RuntimeException($e->getCode() === '23000' ? 'El canal ya está ocupado o la operación tiene referencias asociadas.' : 'No se pudo guardar la cámara.');
            }
            throw $e;
        }
    }

    public static function store(array $data): int
    {
        require_admin();
        $values = self::validate($data);
        return self::persist(function () use ($values): int {
            $id = Camera::create($values);
            AuditService::log('Camaras', 'Creada', 'Cámara creada.', 'Camera', $id, $values['name']);
            return $id;
        });
    }

    public static function update(int $cameraId, array $data): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) throw new RuntimeException('Cámara no encontrada.');
        $values = self::validate($data, $cameraId);
        self::persist(function () use ($cameraId, $values): void {
            Camera::update($cameraId, $values);
            AuditService::log('Camaras', 'Actualizada', 'Cámara actualizada.', 'Camera', $cameraId, $values['name']);
        });
    }

    public static function destroy(int $cameraId): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) throw new RuntimeException('Cámara no encontrada.');
        self::persist(function () use ($cameraId, $camera): void {
            Camera::delete($cameraId);
            AuditService::log('Camaras', 'Eliminada', 'Cámara eliminada.', 'Camera', $cameraId, $camera['name']);
        });
    }
}
