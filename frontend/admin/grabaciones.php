<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

try {
    require_admin();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $input = $method === 'POST' ? $_POST : $_GET;
    $scalar = static function (string $key, string $default = '') use ($input): string {
        $value = $input[$key] ?? $default;
        if (!is_string($value)) {
            throw new ValidationException('La solicitud contiene un campo inválido.');
        }
        return $value;
    };
    if ($method === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        throw new CameraRecordingUploadTooLarge('El vídeo supera el tamaño que admite el servidor. Graba un fragmento más corto.');
    }
    $action = $scalar('action', $method === 'GET' ? 'list' : '');
    if (in_array($method, ['GET', 'HEAD'], true) && in_array($action, ['play', 'download'], true)) {
        CameraRecordingService::stream($scalar('id'), $action === 'download');
    }
    if ($method === 'GET' && ($action === 'list' || $action === 'calendar')) {
        $parking = filter_var($scalar('parking_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parking === false) {
            throw new ValidationException('Selecciona un estacionamiento válido.');
        }
        if ($action === 'calendar') {
            $result = CameraRecordingService::calendar($parking, array_key_exists('month', $_GET) ? $scalar('month') : null);
        } elseif (array_key_exists('date', $_GET)) {
            $result = CameraRecordingService::library(
                $parking,
                $scalar('date'),
                array_key_exists('hour', $_GET) ? $scalar('hour') : null,
                $scalar('page', '1')
            );
        } else {
            if (array_key_exists('hour', $_GET) || array_key_exists('page', $_GET)) {
                throw new ValidationException('Selecciona una fecha para consultar una hora o una página.');
            }
            $result = ['items' => CameraRecordingService::recent($parking)];
        }
        json_response($result + ['limits' => CameraRecordingService::limits(), 'csrf_token' => csrf_token()]);
    }
    if ($method !== 'POST') {
        header('Allow: GET, HEAD, POST');
        json_response(['error' => 'Esta acción no está disponible con el método solicitado.'], 405);
    }
    $csrf = $input['csrf_token'] ?? null;
    if (!is_string($csrf)) {
        throw new ValidationException('La sesión del formulario no es válida. Recarga la página y vuelve a intentarlo.');
    }
    verify_csrf($csrf);
    switch ($action) {
        case 'begin':
            $parking = filter_var($scalar('parking_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($parking === false) {
                throw new ValidationException('Selecciona un estacionamiento válido.');
            }
            json_response(['recording' => CameraRecordingService::begin($parking, $scalar('format')),
                'limits' => CameraRecordingService::limits()], 201);
        case 'save':
            $duration = filter_var($scalar('duration_ms'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($duration === false) {
                throw new ValidationException('La duración de la grabación no es válida.');
            }
            $file = $_FILES['recording'] ?? [];
            if (!is_array($file)) {
                throw new ValidationException('No se recibió un vídeo válido.');
            }
            json_response(['recording' => CameraRecordingService::save($scalar('id'), $file, $duration)]);
        case 'cancel':
            CameraRecordingService::cancel($scalar('id'));
            json_response(['cancelled' => true]);
        default:
            throw new ValidationException('La acción de grabación solicitada no existe.');
    }
} catch (CameraRecordingNotFound $error) {
    json_response(['error' => $error->getMessage()], 404);
} catch (CameraRecordingUploadTooLarge $error) {
    json_response(['error' => $error->getMessage()], 413);
} catch (ValidationException $error) {
    json_response(['error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    if (http_response_code() === 403) {
        json_response(['error' => 'Sólo el administrador puede acceder a las grabaciones.'], 403);
    }
    json_response(['error' => public_error_message($error)], 500);
}
