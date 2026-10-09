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
    public static function register(array $data): void
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

    if ($name === '') {
        throw new RuntimeException('Ingresa tu nombre.');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ingresa un correo válido.');
    }

    if (User::findByEmail($email)) {
        throw new RuntimeException('El correo ya se encuentra registrado.');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException(
            'La contraseña debe tener al menos 8 caracteres.'
        );
    }

    if ($password !== $passwordConfirmation) {
        throw new RuntimeException(
            'Las contraseñas no coinciden.'
        );
    }

    $userId = User::create([
        'name' => $name,
        'email' => $email,
        'password' => password_hash(
            $password,
            PASSWORD_DEFAULT
        ),
        'role' => 'driver',
        'status' => 'active',
    ]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;

    AuditService::log(
        'Autenticacion',
        'Registro',
        'Registro de conductor correcto.',
        'User',
        $userId,
        $email
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
