<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
header('Cache-Control: no-store');
$user = current_user();
if (!$user || $user['status'] !== 'active') {
    json_response(['error' => 'Inicia sesión nuevamente para ver las cámaras.'], 401);
}
if ($user['role'] !== 'admin') {
    AuditService::denied('camera_stream_admin_required');
    json_response(['error' => 'Sólo el administrador puede ver las cámaras.'], 403);
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'connectivity') {
        RateLimiter::consume('camera-connectivity:' . $user['id'] . ':' . request_ip(), 60, 60);
        session_write_close();
        json_response(CameraIceService::forAdmin());
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'status') {
        session_write_close();
        json_response(CameraBridgeService::status());
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        json_response(['error' => 'Método no permitido.'], 405);
    }
    $raw = file_get_contents('php://input', false, null, 0, 70001);
    if ($raw === false || strlen($raw) > 70000) {
        json_response(['error' => 'La solicitud de vídeo es demasiado grande.'], 413);
    }
    $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !is_string($data['csrf_token'] ?? null)) {
        json_response(['error' => 'Solicitud inválida.'], 422);
    }
    try {
        verify_csrf($data['csrf_token']);
    } catch (ValidationException) {
        json_response(['error' => 'La sesión cambió. Recarga la página para conectar las cámaras.'], 403);
    }
    $cameraId = filter_var($data['camera_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$cameraId || !is_string($data['sdp'] ?? null) || !is_string($data['quality'] ?? null)) {
        json_response(['error' => 'Selecciona un canal y una calidad válidos.'], 422);
    }
    RateLimiter::consume('camera-signal:' . $user['id'] . ':' . request_ip(), 60, 60);
    session_write_close();
    json_response(CameraBridgeService::signal($cameraId, $data['quality'], $data['sdp']));
} catch (JsonException) {
    json_response(['error' => 'Solicitud de vídeo inválida.'], 422);
} catch (CameraBridgeException $error) {
    json_response(['error' => $error->getMessage()], $error->status);
} catch (RateLimitException) {
    json_response(['error' => 'Espera un momento antes de volver a conectar las cámaras.'], 429);
} catch (ValidationException $error) {
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable) {
    json_response(['error' => 'No se pudo conectar el canal. Revisa el diagnóstico de cámaras.'], 500);
}
