<?php

declare(strict_types=1);

require_once __DIR__ . '/config/conexion.php';

const APP_BASE_URL = '/devioz-parking-php';
const MONTHLY_PRICE = 100.00;

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function app_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return rtrim(APP_BASE_URL, '/') . ($path === '/' ? '' : $path);
}

function redirect_to(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_ip(): ?string
{
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function request_user_agent(): ?string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? null;
}

function monthly_end_date(string $startsOn): string
{
    $start = new DateTimeImmutable($startsOn . ' 00:00:00');
    $day = (int) $start->format('d');
    $nextMonth = $start->modify('first day of next month');
    $lastDay = (int) $nextMonth->format('t');
    $targetDay = min($day, $lastDay);

    $sameDayNextMonth = $nextMonth->setDate(
        (int) $nextMonth->format('Y'),
        (int) $nextMonth->format('m'),
        $targetDay
    );

    return $sameDayNextMonth->modify('-1 day')->format('Y-m-d');
}

function normalize_plate(string $plate): string
{
    $plate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($plate)) ?? '');
    if (strlen($plate) === 6) {
        return substr($plate, 0, 3) . '-' . substr($plate, 3, 3);
    }
    return $plate;
}

function validate_peruvian_plate(string $plate): bool
{
    return preg_match('/^[A-Z]{3}-?\d{3}$/', strtoupper(trim($plate))) === 1;
}

function clean_string(mixed $value, int $max = 255): string
{
    $value = trim((string) $value);
    return mb_substr($value, 0, $max);
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Parking.php';
require_once __DIR__ . '/models/Space.php';
require_once __DIR__ . '/models/Vehicle.php';
require_once __DIR__ . '/models/Reservation.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/models/Camera.php';
require_once __DIR__ . '/models/AuditLog.php';

require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/permissions.php';

require_once __DIR__ . '/services/AuditService.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/VehicleController.php';
require_once __DIR__ . '/controllers/ReservationController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/CameraController.php';
require_once __DIR__ . '/controllers/AdminController.php';
