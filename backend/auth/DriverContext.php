<?php

declare(strict_types=1);

/** Keeps the business owner separate from the authenticated administrator. */
final class DriverContext
{
    private static ?array $selected = null;
    private static ?int $actorId = null;

    public static function managed(): ?array
    {
        self::$selected = null;
        self::$actorId = null;
        if (!array_key_exists('managed_driver_id', $_GET)) {
            return null;
        }

        $admin = require_admin();
        $id = filter_var($_GET['managed_driver_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $driver = $id === false ? null : User::find($id);
        if (!$driver || $driver['role'] !== 'driver') {
            AuditService::denied('managed_driver_not_found');
            http_response_code(404);
            throw new ValidationException('Conductor no encontrado. Vuelve a Usuarios y selecciona un conductor.');
        }
        if ($driver['status'] !== 'active') {
            AuditService::denied('managed_driver_inactive');
            http_response_code(403);
            throw new ValidationException('El conductor está inactivo. Activa su cuenta en Usuarios para gestionarlo.');
        }

        self::$selected = $driver;
        self::$actorId = (int) $admin['id'];
        return $driver;
    }

    public static function user(): array
    {
        return self::managed() ?? require_driver();
    }

    /** Metadata is available only after an explicit, validated management selection. */
    public static function metadata(): ?array
    {
        if (self::$selected === null || self::$actorId !== current_user_id()) {
            return null;
        }
        return [
            'id' => (int) self::$selected['id'],
            'name' => self::$selected['name'],
        ];
    }

    public static function path(string $path): string
    {
        $driver = self::metadata();
        if ($driver === null || !str_starts_with($path, '/frontend/conductor/')) {
            return $path;
        }
        [$pathname, $query] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($query, $parameters);
        $parameters['managed_driver_id'] = $driver['id'];
        return $pathname . '?' . http_build_query($parameters);
    }

    public static function url(string $path): string
    {
        return app_url(self::path($path));
    }
}
