<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
require_auth();
header('Cache-Control: no-store');
try {
    $deletedCount = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $deletedCount = NotificationController::mark($_POST);
    elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error' => 'Método no permitido.'], 405);
    json_response(NotificationController::index() + ['deleted_count' => $deletedCount]);
} catch (PDOException $e) {
    json_response(['error' => 'No se pudieron cargar las notificaciones.'], 500);
} catch (RuntimeException $e) {
    json_response(['error' => 'La solicitud no es válida.'], 400);
}
