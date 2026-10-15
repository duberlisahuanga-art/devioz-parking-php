<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/models/VehicleCatalog.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user || ($user['status'] ?? '') !== 'active') {
    json_response(['error' => 'Inicia sesión para consultar el catálogo.'], 401);
}
require_auth();
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Método no permitido.'], 405);
}

$query = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'car';
$kind = $_GET['kind'] ?? 'makes';
if (
    !is_string($query) || mb_strlen($query) > 100 || !is_string($type)
    || !in_array($type, ['car', 'moto'], true) || !in_array($kind, ['makes', 'models'], true)
) {
    json_response(['error' => 'Parámetros inválidos.'], 422);
}
$makeId = filter_var($_GET['make_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($kind === 'models' && $makeId === false) {
    json_response(['error' => 'Marca inválida.'], 422);
}

try {
    $items = $type === 'moto' ? [] : ($kind === 'makes'
        ? VehicleCatalog::searchMakes(trim($query), $type)
        : VehicleCatalog::searchModels((int) $makeId, trim($query)));
    echo json_encode(['items' => $items], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('DEVIOZ vehicle catalog: ' . $error->getCode());
    json_response(['error' => 'Catálogo no disponible. Puedes escribir marca y modelo manualmente.'], 503);
}
