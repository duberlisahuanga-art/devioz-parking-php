<?php

declare(strict_types=1);

final class AuthController
{
    public static function login(array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new RuntimeException('Ingresa tu correo y contrasena.');
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password'])) {
            throw new RuntimeException('Correo o contrasena incorrectos.');
        }

        if (($user['status'] ?? 'inactive') !== 'active') {
            throw new RuntimeException('Tu cuenta se encuentra inactiva.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        AuditService::log(
            'Autenticacion',
            'Login',
            'Inicio de sesion correcto.',
            'User',
            (int) $user['id'],
            $user['email']
        );
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
