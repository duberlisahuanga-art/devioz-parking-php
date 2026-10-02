<?php

declare(strict_types=1);

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? null) !== 'admin') {
        http_response_code(403);
        throw new RuntimeException('Solo el administrador puede realizar esta accion.');
    }
    return $user;
}

function require_driver(): array
{
    $user = require_auth();
    if (($user['role'] ?? null) !== 'driver') {
        http_response_code(403);
        throw new RuntimeException('Esta accion solo esta disponible para conductores.');
    }
    return $user;
}

function require_reservation_owner(array $reservation): array
{
    $user = require_auth();
    if (($user['role'] ?? null) === 'admin') {
        return $user;
    }

    if ((int) $reservation['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        throw new RuntimeException('No tienes permiso para acceder a esta reserva.');
    }

    return $user;
}
