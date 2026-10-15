<?php

declare(strict_types=1);

final class SettingsController
{
    private static function text(array $data, string $field): string
    {
        if (!is_string($data[$field] ?? null)) throw new ValidationException('Formato de campo inválido.');
        return $data[$field];
    }

    private static function save(callable $action): void
    {
        try {
            transaction($action);
        } catch (Throwable $e) {
            if ($e instanceof PDOException) throw new ValidationException($e->getCode() === '23000' ? 'El correo ya está registrado o los datos no son válidos.' : 'No se pudieron guardar los cambios.');
            throw $e;
        }
    }

    public static function profile(array $data): void
    {
        $user = require_auth();
        $name = trim(self::text($data, 'name'));
        $email = strtolower(trim(self::text($data, 'email')));
        $phone = self::text($data, 'phone');
        if ($name === '' || mb_strlen($name) > 150) throw new ValidationException('El nombre es obligatorio y admite hasta 150 caracteres.');
        if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Ingresa un correo válido de hasta 150 caracteres.');
        if ($phone !== '' && preg_match('/^[0-9]{9}$/D', $phone) !== 1) throw new ValidationException('El celular debe contener exactamente 9 dígitos, sin espacios ni símbolos.');
        $other = User::findByEmail($email);
        if ($other && (int) $other['id'] !== (int) $user['id']) throw new ValidationException('El correo ya está registrado.');
        self::save(function () use ($user, $name, $email, $phone): void {
            User::updateProfile((int) $user['id'], $name, $email, $phone === '' ? null : $phone);
            AuditService::log('Configuracion', 'Perfil actualizado', 'Datos de perfil actualizados.', 'User', (int) $user['id']);
        });
    }

    public static function password(array $data): void
    {
        $user = require_auth();
        $current = self::text($data, 'current_password');
        $new = self::text($data, 'new_password');
        $confirmation = self::text($data, 'password_confirmation');
        $stored = User::find((int) $user['id']);
        if (!$stored || !password_verify($current, $stored['password'])) throw new ValidationException('La contraseña actual no es correcta.');
        // PASSWORD_DEFAULT currently uses bcrypt: prevent silent truncation at 72 bytes.
        validate_password($new);
        if ($new !== $confirmation) throw new ValidationException('La confirmación no coincide.');
        $hash = password_hash($new, PASSWORD_DEFAULT);
        self::save(function () use ($user, $stored, $hash): void {
            if (!User::changePassword((int) $user['id'], $stored['password'], $hash)) throw new ValidationException('La contraseña cambió durante la operación. Vuelve a intentarlo.');
            AuditService::log('Configuracion', 'Contraseña actualizada', 'Contraseña actualizada.', 'User', (int) $user['id']);
            NotificationService::send((int) $user['id'], 'system_notices', 'Contraseña actualizada', 'Se cambió la contraseña de tu cuenta.', '/frontend/configuracion.php');
        });
    }

    public static function preferences(array $data): void
    {
        $user = require_auth();
        $values = [];
        foreach (UserPreference::DEFAULTS as $key => $default) {
            $value = $data[$key] ?? '0';
            if (!in_array($value, ['0', '1', 0, 1], true)) throw new ValidationException('Preferencia inválida.');
            $values[$key] = (int) $value;
        }
        self::save(function () use ($user, $values): void {
            UserPreference::save((int) $user['id'], $values);
            AuditService::log('Configuracion', 'Preferencias actualizadas', 'Preferencias de notificación actualizadas.', 'User', (int) $user['id']);
        });
    }
}
