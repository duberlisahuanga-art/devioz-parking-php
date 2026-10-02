#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"

if [ ! -f "$ROOT/backend/config/conexion.php" ]; then
  echo "ERROR: Ejecuta este script desde la raiz de devioz-parking-php."
  echo "No se encontro backend/config/conexion.php"
  exit 1
fi

mkdir -p \
  backend/auth \
  backend/controllers \
  backend/models \
  backend/services \
  backend/uploads/comprobantes

rm -f \
  backend/auth/.gitkeep \
  backend/controllers/.gitkeep \
  backend/models/.gitkeep

cat > backend/bootstrap.php <<'PHP'
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
PHP

cat > backend/auth/session.php <<'PHP'
<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
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
        throw new RuntimeException('Token CSRF invalido.');
    }
}
PHP

cat > backend/auth/permissions.php <<'PHP'
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
PHP

cat > backend/auth/login.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/frontend/login.php');
}

try {
    verify_csrf();
    AuthController::login($_POST);
    $user = current_user();

    if (($user['role'] ?? '') === 'admin') {
        redirect_to('/frontend/admin/dashboard.php');
    }

    redirect_to('/frontend/conductor/dashboard.php');
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
    redirect_to('/frontend/login.php');
}
PHP

cat > backend/auth/logout.php <<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

AuthController::logout();
redirect_to('/frontend/login.php');
PHP

cat > backend/models/User.php <<'PHP'
<?php

declare(strict_types=1);

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function all(string $search = '', string $role = '', string $status = ''): array
    {
        $sql = 'SELECT u.*,
                    (SELECT COUNT(*) FROM vehicles v WHERE v.user_id = u.id) AS vehicles_count,
                    (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.id) AS reservations_count
                FROM users u WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (u.name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if (in_array($role, ['admin', 'driver'], true)) {
            $sql .= ' AND u.role = :role';
            $params['role'] = $role;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY u.created_at DESC, u.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function summary(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(role='admin') AS admins,
                    SUM(role='driver') AS drivers,
                    SUM(status='active') AS active_users,
                    SUM(status='inactive') AS inactive_users
             FROM users"
        )->fetch();

        return $row ?: [];
    }
}
PHP

cat > backend/models/Parking.php <<'PHP'
<?php

declare(strict_types=1);

final class Parking
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM parkings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function devioz(): ?array
    {
        $stmt = db()->query("SELECT * FROM parkings WHERE slug = 'estacionamiento-devioz' LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public static function allWithStats(): array
    {
        $sql = "SELECT p.*,
                       COUNT(s.id) AS spaces_count,
                       SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) AS occupied_spaces_count,
                       SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) AS available_spaces_count,
                       SUM(CASE WHEN s.status='reserved' THEN 1 ELSE 0 END) AS reserved_spaces_count
                FROM parkings p
                LEFT JOIN spaces s ON s.parking_id = p.id
                GROUP BY p.id
                ORDER BY p.name";
        return db()->query($sql)->fetchAll();
    }
}
PHP

cat > backend/models/Space.php <<'PHP'
<?php

declare(strict_types=1);

final class Space
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM spaces WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forParking(int $parkingId): array
    {
        $stmt = db()->prepare('SELECT * FROM spaces WHERE parking_id = :parking_id ORDER BY floor, code');
        $stmt->execute(['parking_id' => $parkingId]);
        return $stmt->fetchAll();
    }

    public static function availableForParking(int $parkingId): array
    {
        $stmt = db()->prepare("SELECT * FROM spaces WHERE parking_id = :parking_id AND status='available' ORDER BY floor, code");
        $stmt->execute(['parking_id' => $parkingId]);
        return $stmt->fetchAll();
    }

    public static function allDetailed(): array
    {
        $sql = "SELECT s.*, p.name AS parking_name,
                       r.id AS reservation_id,
                       r.status AS reservation_status,
                       u.name AS driver_name,
                       v.plate AS vehicle_plate
                FROM spaces s
                JOIN parkings p ON p.id = s.parking_id
                LEFT JOIN reservations r ON r.space_id = s.id
                    AND r.status IN ('confirmed','active')
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN vehicles v ON v.id = r.vehicle_id
                ORDER BY s.code";
        return db()->query($sql)->fetchAll();
    }

    public static function stats(): array
    {
        $row = db()->query(
            "SELECT COUNT(*) AS total,
                    SUM(status='available') AS available,
                    SUM(status='reserved') AS reserved,
                    SUM(status='occupied') AS occupied,
                    SUM(status='maintenance') AS maintenance
             FROM spaces"
        )->fetch();
        return $row ?: [];
    }

    public static function setStatus(int $id, string $status): void
    {
        $allowed = ['available', 'reserved', 'occupied', 'maintenance'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Estado de plaza invalido.');
        }

        $stmt = db()->prepare('UPDATE spaces SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
PHP

cat > backend/models/Vehicle.php <<'PHP'
<?php

declare(strict_types=1);

final class Vehicle
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE user_id = :user_id ORDER BY created_at DESC, id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findOwned(int $id, int $userId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM vehicles WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function plateExists(string $plate): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM vehicles WHERE plate = :plate LIMIT 1');
        $stmt->execute(['plate' => $plate]);
        return (bool) $stmt->fetchColumn();
    }

    public static function create(int $userId, array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO vehicles (user_id, plate, brand, model, color, type, created_at, updated_at)
             VALUES (:user_id, :plate, :brand, :model, :color, :type, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'plate' => $data['plate'],
            'brand' => $data['brand'] ?: null,
            'model' => $data['model'] ?: null,
            'color' => $data['color'] ?: null,
            'type' => $data['type'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function hasOpenReservation(int $vehicleId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM reservations WHERE vehicle_id = :vehicle_id AND status IN ('confirmed','active') LIMIT 1");
        $stmt->execute(['vehicle_id' => $vehicleId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM vehicles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
PHP

cat > backend/models/Reservation.php <<'PHP'
<?php

declare(strict_types=1);

final class Reservation
{
    private static function baseSelect(): string
    {
        return "SELECT r.*,
                       u.name AS user_name, u.email AS user_email,
                       p.name AS parking_name, p.address AS parking_address,
                       s.code AS space_code, s.type AS space_type,
                       v.plate AS vehicle_plate, v.brand AS vehicle_brand,
                       v.model AS vehicle_model, v.color AS vehicle_color
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                JOIN parkings p ON p.id = r.parking_id
                JOIN spaces s ON s.id = r.space_id
                JOIN vehicles v ON v.id = r.vehicle_id";
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE r.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE r.user_id = :user_id ORDER BY r.created_at DESC, r.id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return db()->query(self::baseSelect() . ' ORDER BY r.created_at DESC, r.id DESC')->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO reservations
             (user_id, parking_id, space_id, vehicle_id, starts_on, ends_on, check_in_at, check_out_at,
              status, amount, currency, payment_status, cancellation_status, created_at, updated_at)
             VALUES
             (:user_id, :parking_id, :space_id, :vehicle_id, :starts_on, :ends_on, NULL, NULL,
              :status, :amount, :currency, :payment_status, :cancellation_status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = [
            'check_in_at', 'check_out_at', 'status', 'payment_status',
            'cancellation_status', 'cancellation_reason', 'cancellation_requested_at',
            'cancellation_reviewed_at', 'cancellation_reviewed_by', 'cancellation_review_note',
        ];

        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $set[] = "$key = :$key";
            $params[$key] = $value;
        }

        if (!$set) {
            return;
        }

        $set[] = 'updated_at = NOW()';
        $stmt = db()->prepare('UPDATE reservations SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public static function countByStatus(string $status, ?int $userId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM reservations WHERE status = :status';
        $params = ['status' => $status];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function unpaidCount(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = :user_id AND payment_status = 'unpaid'");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
PHP

cat > backend/models/Payment.php <<'PHP'
<?php

declare(strict_types=1);

final class Payment
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT py.*, r.user_id, r.space_id, r.vehicle_id, r.payment_status AS reservation_payment_status,
                    s.code AS space_code, v.plate AS vehicle_plate, u.name AS user_name, u.email AS user_email
             FROM payments py
             JOIN reservations r ON r.id = py.reservation_id
             JOIN spaces s ON s.id = r.space_id
             JOIN vehicles v ON v.id = r.vehicle_id
             JOIN users u ON u.id = r.user_id
             WHERE py.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function pending(): array
    {
        $sql = "SELECT py.*, r.user_id, s.code AS space_code, v.plate AS vehicle_plate,
                       u.name AS user_name, u.email AS user_email
                FROM payments py
                JOIN reservations r ON r.id = py.reservation_id
                JOIN spaces s ON s.id = r.space_id
                JOIN vehicles v ON v.id = r.vehicle_id
                JOIN users u ON u.id = r.user_id
                WHERE py.status='pending'
                ORDER BY py.created_at DESC, py.id DESC";
        return db()->query($sql)->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO payments
             (reservation_id, amount, method, provider, provider_tx_id, voucher_path, status, created_at, updated_at)
             VALUES
             (:reservation_id, :amount, :method, :provider, :provider_tx_id, :voucher_path, :status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'paid', 'rejected'], true)) {
            throw new InvalidArgumentException('Estado de pago invalido.');
        }
        $stmt = db()->prepare('UPDATE payments SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function paidTotalForUser(int $userId): float
    {
        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(py.amount), 0)
             FROM payments py
             JOIN reservations r ON r.id = py.reservation_id
             WHERE r.user_id = :user_id AND py.status='paid'"
        );
        $stmt->execute(['user_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }
}
PHP

cat > backend/models/Camera.php <<'PHP'
<?php

declare(strict_types=1);

final class Camera
{
    public static function all(): array
    {
        return db()->query('SELECT * FROM cameras ORDER BY id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cameras WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO cameras (parking_id, name, stream_url, coverage, status, created_at, updated_at)
             VALUES (:parking_id, :name, :stream_url, :coverage, :status, NOW(), NOW())'
        );
        $stmt->execute($data);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE cameras
             SET parking_id=:parking_id, name=:name, stream_url=:stream_url,
                 coverage=:coverage, status=:status, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute($data + ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM cameras WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
PHP

cat > backend/models/AuditLog.php <<'PHP'
<?php

declare(strict_types=1);

final class AuditLog
{
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs
             (user_id, action, entity_type, entity_id, description, metadata, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :metadata, :ip_address, NOW())'
        );
        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'description' => $data['description'],
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function all(string $action = '', string $search = ''): array
    {
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1";
        $params = [];

        if ($action !== '') {
            $sql .= ' AND a.action = :action';
            $params['action'] = $action;
        }
        if ($search !== '') {
            $sql .= ' AND (a.description LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
PHP

cat > backend/services/AuditService.php <<'PHP'
<?php

declare(strict_types=1);

final class AuditService
{
    public static function log(
        string $module,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $reference = null,
        array $metadata = []
    ): int {
        $user = current_user();
        $metadata = [
            'module' => $module,
            'reference' => $reference,
            'user_agent' => request_user_agent(),
            ...$metadata,
        ];

        return AuditLog::create([
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request_ip(),
        ]);
    }
}
PHP

cat > backend/controllers/AuthController.php <<'PHP'
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
PHP

cat > backend/controllers/VehicleController.php <<'PHP'
<?php

declare(strict_types=1);

final class VehicleController
{
    public static function index(): array
    {
        $user = require_auth();
        return Vehicle::forUser((int) $user['id']);
    }

    public static function store(array $data): int
    {
        $user = require_driver();

        $rawPlate = clean_string($data['plate'] ?? '', 20);
        if (!validate_peruvian_plate($rawPlate)) {
            throw new RuntimeException('Formato de placa peruana invalido. Ejemplo: ABC-123.');
        }

        $plate = normalize_plate($rawPlate);
        if (Vehicle::plateExists($plate)) {
            throw new RuntimeException('La placa ya se encuentra registrada.');
        }

        $type = (string) ($data['type'] ?? 'car');
        if (!in_array($type, ['car', 'moto'], true)) {
            throw new RuntimeException('Tipo de vehiculo invalido.');
        }

        $id = Vehicle::create((int) $user['id'], [
            'plate' => $plate,
            'brand' => clean_string($data['brand'] ?? '', 100),
            'model' => clean_string($data['model'] ?? '', 100),
            'color' => clean_string($data['color'] ?? '', 50),
            'type' => $type,
        ]);

        AuditService::log('Vehiculos', 'Creado', "Vehiculo {$plate} registrado.", 'Vehicle', $id, $plate);
        return $id;
    }

    public static function destroy(int $vehicleId): void
    {
        $user = require_driver();
        $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
        if (!$vehicle) {
            throw new RuntimeException('Vehiculo no encontrado.');
        }
        if (Vehicle::hasOpenReservation($vehicleId)) {
            throw new RuntimeException('No puedes eliminar un vehiculo con una reserva vigente.');
        }

        Vehicle::delete($vehicleId);
        AuditService::log('Vehiculos', 'Eliminado', "Vehiculo {$vehicle['plate']} eliminado.", 'Vehicle', $vehicleId, $vehicle['plate']);
    }
}
PHP

cat > backend/controllers/ReservationController.php <<'PHP'
<?php

declare(strict_types=1);

final class ReservationController
{
    public static function index(): array
    {
        $user = require_auth();
        return ($user['role'] ?? '') === 'admin'
            ? Reservation::all()
            : Reservation::forUser((int) $user['id']);
    }

    public static function createData(): array
    {
        $user = require_driver();
        $parking = Parking::devioz();
        if (!$parking) {
            throw new RuntimeException('No se encontro el estacionamiento Devioz.');
        }

        return [
            'parking' => $parking,
            'vehicles' => Vehicle::forUser((int) $user['id']),
            'spaces' => Space::forParking((int) $parking['id']),
            'monthly_price' => MONTHLY_PRICE,
        ];
    }

    public static function store(array $data): int
    {
        $user = require_driver();
        $spaceId = (int) ($data['space_id'] ?? 0);
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $startsOn = trim((string) ($data['starts_on'] ?? ''));

        if ($spaceId < 1 || $vehicleId < 1 || $startsOn === '') {
            throw new RuntimeException('Completa plaza, vehiculo y fecha de inicio.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startsOn);
        $today = new DateTimeImmutable('today');
        if (!$start || $start->format('Y-m-d') !== $startsOn || $start < $today) {
            throw new RuntimeException('La fecha de inicio debe ser valida y no puede estar en el pasado.');
        }

        $endsOn = monthly_end_date($startsOn);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM spaces WHERE id = :id AND status='available' FOR UPDATE");
            $stmt->execute(['id' => $spaceId]);
            $space = $stmt->fetch();
            if (!$space) {
                throw new RuntimeException('La plaza seleccionada ya no se encuentra disponible.');
            }

            $vehicle = Vehicle::findOwned($vehicleId, (int) $user['id']);
            if (!$vehicle) {
                throw new RuntimeException('El vehiculo seleccionado no te pertenece.');
            }

            $reservationId = Reservation::create([
                'user_id' => (int) $user['id'],
                'parking_id' => (int) $space['parking_id'],
                'space_id' => $spaceId,
                'vehicle_id' => $vehicleId,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'confirmed',
                'amount' => MONTHLY_PRICE,
                'currency' => 'PEN',
                'payment_status' => 'unpaid',
                'cancellation_status' => 'none',
            ]);

            Space::setStatus($spaceId, 'reserved');
            $pdo->commit();

            AuditService::log(
                'Reservas',
                'Creada',
                "Reserva mensual creada para la plaza {$space['code']}.",
                'Reservation',
                $reservationId,
                $space['code'],
                [
                    'vehicle_plate' => $vehicle['plate'],
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'monthly_price' => MONTHLY_PRICE,
                    'payment_status' => 'unpaid',
                ]
            );

            return $reservationId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function checkin(int $reservationId): void
    {
        require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] === 'pending') {
            throw new RuntimeException('No se puede registrar el ingreso mientras exista una cancelacion pendiente.');
        }
        if ($reservation['status'] !== 'confirmed') {
            throw new RuntimeException('La reserva no esta disponible para registrar ingreso.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'active',
                'check_in_at' => date('Y-m-d H:i:s'),
                'check_out_at' => null,
            ]);
            Space::setStatus((int) $reservation['space_id'], 'occupied');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Ingreso', "Ingreso registrado en la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function checkout(int $reservationId): void
    {
        require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['status'] !== 'active') {
            throw new RuntimeException('El vehiculo no se encuentra registrado dentro del estacionamiento.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'confirmed',
                'check_out_at' => date('Y-m-d H:i:s'),
            ]);
            Space::setStatus((int) $reservation['space_id'], 'reserved');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Salida', "Salida registrada de la plaza {$reservation['space_code']}. La plaza permanece reservada por la mensualidad.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function requestCancellation(int $reservationId, string $reason): void
    {
        $user = require_driver();
        $reservation = Reservation::find($reservationId);
        if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('No puedes solicitar la cancelacion de esta reserva.');
        }
        if ($reservation['status'] !== 'confirmed') {
            throw new RuntimeException('No puedes solicitar la cancelacion en el estado actual.');
        }
        if ($reservation['cancellation_status'] === 'pending') {
            throw new RuntimeException('Ya existe una solicitud de cancelacion pendiente.');
        }

        $reason = clean_string($reason, 1000);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Indica un motivo de al menos 5 caracteres.');
        }

        Reservation::update($reservationId, [
            'cancellation_status' => 'pending',
            'cancellation_reason' => $reason,
            'cancellation_requested_at' => date('Y-m-d H:i:s'),
            'cancellation_reviewed_at' => null,
            'cancellation_reviewed_by' => null,
            'cancellation_review_note' => null,
        ]);

        AuditService::log('Reservas', 'Cancelacion solicitada', "Se solicito la cancelacion de la mensualidad de la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['reason' => $reason]);
    }

    public static function approveCancellation(int $reservationId, ?string $note = null): void
    {
        $admin = require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] !== 'pending') {
            throw new RuntimeException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
        }
        if ($reservation['status'] === 'active') {
            throw new RuntimeException('No se puede aprobar la cancelacion mientras el vehiculo este dentro.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Reservation::update($reservationId, [
                'status' => 'cancelled',
                'cancellation_status' => 'approved',
                'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
                'cancellation_reviewed_by' => (int) $admin['id'],
                'cancellation_review_note' => $note !== null ? clean_string($note, 1000) : null,
            ]);
            Space::setStatus((int) $reservation['space_id'], 'available');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Reservas', 'Cancelacion aprobada', "Cancelacion aprobada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code']);
    }

    public static function rejectCancellation(int $reservationId, string $note): void
    {
        $admin = require_admin();
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }
        if ($reservation['cancellation_status'] !== 'pending') {
            throw new RuntimeException('Esta reserva no tiene una solicitud de cancelacion pendiente.');
        }

        $note = clean_string($note, 1000);
        if (mb_strlen($note) < 5) {
            throw new RuntimeException('La nota debe tener al menos 5 caracteres.');
        }

        Reservation::update($reservationId, [
            'cancellation_status' => 'rejected',
            'cancellation_reviewed_at' => date('Y-m-d H:i:s'),
            'cancellation_reviewed_by' => (int) $admin['id'],
            'cancellation_review_note' => $note,
        ]);

        AuditService::log('Reservas', 'Cancelacion rechazada', "Cancelacion rechazada para la plaza {$reservation['space_code']}.", 'Reservation', $reservationId, $reservation['space_code'], ['note' => $note]);
    }
}
PHP

cat > backend/controllers/PaymentController.php <<'PHP'
<?php

declare(strict_types=1);

final class PaymentController
{
    public static function submit(int $reservationId, array $data, array $files): int
    {
        $user = require_driver();
        $reservation = Reservation::find($reservationId);
        if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('No tienes permiso para pagar esta reserva.');
        }
        if ($reservation['payment_status'] !== 'unpaid') {
            throw new RuntimeException('Esta reserva ya tiene un pago en revision o fue pagada.');
        }

        if (($data['method'] ?? '') !== 'yape') {
            throw new RuntimeException('Por ahora el metodo habilitado es Yape.');
        }

        $providerTxId = clean_string($data['provider_tx_id'] ?? '', 100);
        if ($providerTxId === '') {
            throw new RuntimeException('Ingresa el codigo u operacion de Yape.');
        }

        $file = $files['voucher'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Adjunta el comprobante de pago.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('El comprobante no puede superar 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Formato de comprobante no permitido.');
        }

        $dir = __DIR__ . '/../uploads/comprobantes';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de comprobantes.');
        }

        $filename = sprintf('reserva_%d_%s.%s', $reservationId, bin2hex(random_bytes(8)), $allowed[$mime]);
        $destination = $dir . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar el comprobante.');
        }

        $relativePath = 'backend/uploads/comprobantes/' . $filename;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $paymentId = Payment::create([
                'reservation_id' => $reservationId,
                'amount' => (float) $reservation['amount'],
                'method' => 'yape',
                'provider' => 'manual',
                'provider_tx_id' => $providerTxId,
                'voucher_path' => $relativePath,
                'status' => 'pending',
            ]);
            Reservation::update($reservationId, ['payment_status' => 'pending']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink($destination);
            throw $e;
        }

        AuditService::log('Pagos', 'Enviado', "Voucher enviado para la reserva #{$reservationId}.", 'Payment', $paymentId, "PAY-{$paymentId}", [
            'reservation_id' => $reservationId,
            'space' => $reservation['space_code'],
            'vehicle_plate' => $reservation['vehicle_plate'],
            'amount' => $reservation['amount'],
            'method' => 'yape',
            'provider_tx_id' => $providerTxId,
        ]);

        return $paymentId;
    }

    public static function confirm(int $paymentId): void
    {
        require_admin();
        $payment = Payment::find($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            throw new RuntimeException('Este pago ya fue procesado o no existe.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Payment::setStatus($paymentId, 'paid');
            Reservation::update((int) $payment['reservation_id'], ['payment_status' => 'paid']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Pagos', 'Aprobado', "Pago #{$paymentId} aprobado para la reserva #{$payment['reservation_id']}.", 'Payment', $paymentId, "PAY-{$paymentId}", [
            'space' => $payment['space_code'],
            'vehicle_plate' => $payment['vehicle_plate'],
            'amount' => $payment['amount'],
        ]);
    }

    public static function reject(int $paymentId): void
    {
        require_admin();
        $payment = Payment::find($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            throw new RuntimeException('Este pago ya fue procesado o no existe.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Payment::setStatus($paymentId, 'rejected');
            Reservation::update((int) $payment['reservation_id'], ['payment_status' => 'unpaid']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuditService::log('Pagos', 'Rechazado', "Pago #{$paymentId} rechazado para la reserva #{$payment['reservation_id']}.", 'Payment', $paymentId, "PAY-{$paymentId}");
    }
}
PHP

cat > backend/controllers/CameraController.php <<'PHP'
<?php

declare(strict_types=1);

final class CameraController
{
    public static function index(): array
    {
        require_admin();
        return Camera::all();
    }

    public static function store(array $data): int
    {
        require_admin();
        $parkingId = (int) ($data['parking_id'] ?? 0);
        if (!Parking::find($parkingId)) {
            throw new RuntimeException('Estacionamiento invalido.');
        }

        $name = clean_string($data['name'] ?? '', 120);
        if ($name === '') {
            throw new RuntimeException('El nombre de la camara es obligatorio.');
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Estado de camara invalido.');
        }

        $id = Camera::create([
            'parking_id' => $parkingId,
            'name' => $name,
            'stream_url' => clean_string($data['stream_url'] ?? '', 500) ?: null,
            'coverage' => clean_string($data['coverage'] ?? '', 255) ?: null,
            'status' => $status,
        ]);

        AuditService::log('Camaras', 'Creada', "Camara {$name} creada.", 'Camera', $id, $name);
        return $id;
    }

    public static function update(int $cameraId, array $data): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) {
            throw new RuntimeException('Camara no encontrada.');
        }

        $parkingId = (int) ($data['parking_id'] ?? $camera['parking_id']);
        if (!Parking::find($parkingId)) {
            throw new RuntimeException('Estacionamiento invalido.');
        }

        $status = (string) ($data['status'] ?? $camera['status']);
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Estado de camara invalido.');
        }

        Camera::update($cameraId, [
            'parking_id' => $parkingId,
            'name' => clean_string($data['name'] ?? $camera['name'], 120),
            'stream_url' => clean_string($data['stream_url'] ?? ($camera['stream_url'] ?? ''), 500) ?: null,
            'coverage' => clean_string($data['coverage'] ?? ($camera['coverage'] ?? ''), 255) ?: null,
            'status' => $status,
        ]);

        AuditService::log('Camaras', 'Actualizada', "Camara #{$cameraId} actualizada.", 'Camera', $cameraId, (string) $camera['name']);
    }

    public static function destroy(int $cameraId): void
    {
        require_admin();
        $camera = Camera::find($cameraId);
        if (!$camera) {
            throw new RuntimeException('Camara no encontrada.');
        }
        Camera::delete($cameraId);
        AuditService::log('Camaras', 'Eliminada', "Camara {$camera['name']} eliminada.", 'Camera', $cameraId, (string) $camera['name']);
    }
}
PHP

cat > backend/controllers/AdminController.php <<'PHP'
<?php

declare(strict_types=1);

final class AdminController
{
    public static function overview(): array
    {
        require_admin();
        $spaces = Space::stats();
        $total = (int) ($spaces['total'] ?? 0);
        $available = (int) ($spaces['available'] ?? 0);

        return [
            'metrics' => [
                'total_spaces' => $total,
                'parkings' => count(Parking::allWithStats()),
                'availability' => $total > 0 ? round(($available / $total) * 100, 1) : 0,
                'active_reservations' => Reservation::countByStatus('active'),
            ],
            'spaces' => $spaces,
            'parkings' => Parking::allWithStats(),
            'users_summary' => User::summary(),
        ];
    }

    public static function users(array $filters = []): array
    {
        require_admin();
        return [
            'users' => User::all(
                trim((string) ($filters['search'] ?? '')),
                trim((string) ($filters['role'] ?? '')),
                trim((string) ($filters['status'] ?? ''))
            ),
            'summary' => User::summary(),
        ];
    }

    public static function spaces(): array
    {
        require_admin();
        return [
            'spaces' => Space::allDetailed(),
            'summary' => Space::stats(),
        ];
    }

    public static function payments(): array
    {
        require_admin();
        return Payment::pending();
    }

    public static function confirmPayment(int $paymentId): void
    {
        PaymentController::confirm($paymentId);
    }

    public static function rejectPayment(int $paymentId): void
    {
        PaymentController::reject($paymentId);
    }

    public static function audit(array $filters = []): array
    {
        require_admin();
        return AuditLog::all(
            trim((string) ($filters['action'] ?? '')),
            trim((string) ($filters['search'] ?? ''))
        );
    }

    public static function dashboardForCurrentUser(): array
    {
        $user = require_auth();
        $spaceStats = Space::stats();
        $total = (int) ($spaceStats['total'] ?? 0);
        $available = (int) ($spaceStats['available'] ?? 0);

        return [
            'total_spaces' => $total,
            'occupied' => (int) ($spaceStats['occupied'] ?? 0),
            'active_reservations' => Reservation::countByStatus('active', (int) $user['id']),
            'parkings' => count(Parking::allWithStats()),
            'availability' => $total > 0 ? round(($available / $total) * 100, 1) : 0,
            'balance' => 'S/ ' . number_format(Payment::paidTotalForUser((int) $user['id']), 2, '.', ','),
            'role' => $user['role'],
            'unpaid_reservations' => Reservation::unpaidCount((int) $user['id']),
            'spaces' => Parking::devioz() ? Space::forParking((int) Parking::devioz()['id']) : [],
            'parkings_list' => Parking::allWithStats(),
        ];
    }
}
PHP

# Validacion basica del backend generado.
echo ""
echo "Backend generado. Verificando sintaxis PHP..."

PHP_BIN="C:/xampp/php/php.exe"
if command -v "$PHP_BIN" >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    "$PHP_BIN" -l "$file"
  done < <(find backend -name '*.php' -print0)
elif command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    php -l "$file"
  done < <(find backend -name '*.php' -print0)
else
  echo "ADVERTENCIA: PHP CLI no fue encontrado. Ejecuta luego:"
  echo 'find backend -name "*.php" -exec C:/xampp/php/php.exe -l {} \;'
fi

echo ""
echo "=============================================="
echo "MIGRACION DEL BACKEND COMPLETADA"
echo "=============================================="
echo "Generado:"
echo "  - autenticacion y sesiones"
echo "  - permisos admin/driver"
echo "  - modelos PDO"
echo "  - reservas mensuales S/100"
echo "  - check-in / check-out"
echo "  - cancelaciones"
echo "  - vehiculos"
echo "  - pagos Yape y comprobantes"
echo "  - camaras de vigilancia"
echo "  - auditoria"
echo ""
echo "No se modifico backend/config/conexion.php"
echo "No se agregaron servicios adicionales."
echo ""
echo "Siguiente paso recomendado:"
echo "  git status --short"
