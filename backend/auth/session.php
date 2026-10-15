<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => AppConfig::production() || ($_SERVER['HTTPS'] ?? '') === 'on',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    static $cache = null;
    static $loadedId = null;

    $id = current_user_id();
    if ($id === null) {
        return null;
    }

    if ($cache === null || $loadedId !== $id) {
        $cache = User::find($id);
        $loadedId = $id;
    }

    return $cache ?: null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function is_driver(): bool
{
    return (current_user()['role'] ?? null) === 'driver';
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('/frontend/login.php');
    }

    if (($user['status'] ?? 'inactive') !== 'active') {
        session_unset();
        session_destroy();
        redirect_to('/frontend/login.php');
    }

    return $user;
}

function require_guest(): void
{
    if (is_authenticated()) {
        redirect_to('/frontend/index.php');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consume_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(?string $token = null): void
{
    $token ??= $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new ValidationException('Token CSRF invalido.');
    }
}
