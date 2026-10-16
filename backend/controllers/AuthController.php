<?php

declare(strict_types=1);

final class AuthController
{
    public static function login(array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new ValidationException('Ingresa tu correo y contrasena.');
        }

        // Throttled requests stop before auditing to bound failed-login events.
        RateLimiter::consume('login-ip:' . request_ip(), 30);
        RateLimiter::consume('login-account-ip:' . request_ip() . ':' . $email);
        try {
            validate_password($password);
        } catch (ValidationException $error) {
            AuditService::denied('invalid_credentials');
            throw $error;
        }
        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password'])) {
            AuditService::denied('invalid_credentials');
            throw new ValidationException('Correo o contrasena incorrectos.');
        }

        if (($user['status'] ?? 'inactive') !== 'active') {
            AuditService::denied('inactive_account');
            throw new ValidationException('Tu cuenta se encuentra inactiva.');
        }

        AuditService::log(
            'Autenticacion',
            'Login',
            'Inicio de sesion correcto.',
            'User',
            (int) $user['id'],
            $user['email'],
            actor: $user
        );
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) ($user['session_version'] ?? 0);
    }
    public static function register(array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($name === '') {
            throw new ValidationException('Ingresa tu nombre.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Ingresa un correo válido.');
        }

        RateLimiter::consume('register-ip:' . request_ip(), 10, 3600);
        if (mb_strlen($name) > 150 || strlen($email) > 150) {
            throw new ValidationException('Nombre o correo demasiado largo.');
        }
        if (User::findByEmail($email)) {
            throw new ValidationException('El correo ya se encuentra registrado.');
        }

        validate_password($password);

        if ($password !== $passwordConfirmation) {
            throw new ValidationException(
                'Las contraseñas no coinciden.'
            );
        }

        $userId = transaction(function () use ($name, $email, $password): int {
            $userId = User::create([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'driver',
                'status' => 'active',
            ]);

            AuditService::log(
                'Autenticacion',
                'Registro',
                'Registro de conductor correcto.',
                'User',
                $userId,
                $email,
                actor: ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'driver']
            );
            return $userId;
        });
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_version'] = 0;
    }

    public static function logout(): void
    {
        if (is_authenticated()) {
            AuditService::log('Autenticacion', 'Logout', 'Cierre de sesion.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
