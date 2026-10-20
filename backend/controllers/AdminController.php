<?php

declare(strict_types=1);

final class AdminController
{
    public static function createAdministrator(array $input): int
    {
        $admin = require_admin();
        foreach (['name', 'email', 'password', 'password_confirmation'] as $field) {
            if (!is_string($input[$field] ?? null)) {
                throw new ValidationException('Completa nombre, correo y ambas contraseñas.');
            }
        }
        $name = trim($input['name']);
        $email = strtolower(trim($input['email']));
        if (
            $name === '' || !mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name) > 150 || str_contains($name, "\0")
        ) {
            throw new ValidationException('Ingresa un nombre válido de hasta 150 caracteres.');
        }
        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Ingresa un correo electrónico válido de hasta 150 caracteres.');
        }
        validate_password($input['password']);
        if ($input['password'] !== $input['password_confirmation']) {
            throw new ValidationException('Las contraseñas no coinciden. Vuelve a escribirlas.');
        }
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        try {
            return transaction(function () use ($admin, $name, $email, $hash): int {
                $query = db()->prepare('SELECT role,status,session_version FROM users WHERE id=:id FOR UPDATE');
                $query->execute(['id' => $admin['id']]);
                $actor = $query->fetch();
                if (
                    !$actor || $actor['role'] !== 'admin' || $actor['status'] !== 'active'
                    || (int) $actor['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)
                ) {
                    throw new ValidationException('Tu sesión ya no permite crear administradores. Inicia sesión nuevamente.');
                }
                if (User::findByEmail($email)) {
                    throw new ValidationException('El correo ya está registrado. Utiliza otro correo electrónico.');
                }
                $id = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hash,
                    'role' => 'admin',
                    'status' => 'active',
                ]);
                AuditService::log(
                    'Usuarios',
                    'Administrador creado',
                    'Se creó una cuenta con acceso administrativo completo.',
                    'User',
                    $id,
                    $email,
                    ['created_user' => ['name' => $name, 'role' => 'admin', 'status' => 'active']],
                    actor: $admin
                );
                return $id;
            });
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException('El correo ya está registrado. Utiliza otro correo electrónico.');
            }
            throw $error;
        }
    }

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
        ReservationLifecycleService::run();
        return [
            'spaces' => Space::allDetailed(),
            'summary' => Space::stats(),
        ];
    }
    public static function changeUserStatus(array $input): void
    {
        $admin = require_admin();
        $id = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? null;
        if (!$id || $id < 1 || !in_array($status, ['active','inactive'], true)) {
            throw new ValidationException('Usuario o estado inválido.');
        }
        if ($id === (int)$admin['id']) {
            throw new ValidationException('No puedes cambiar el estado de tu propia cuenta.');
        }
        transaction(function () use ($admin, $id, $status): void {
            // Serialize admin decisions and recheck the acting account after acquiring locks.
            $admins = db()->query("SELECT id,status,session_version FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll();
            $actor = null;
            $activeAdmins = 0;
            foreach ($admins as $row) {
                if ($row['status'] === 'active') {
                    $activeAdmins++;
                }
                if ((int)$row['id'] === (int)$admin['id']) {
                    $actor = $row;
                }
            }
            if (
                !$actor || $actor['status'] !== 'active'
                || (int)$actor['session_version'] !== (int)($_SESSION['session_version'] ?? 0)
            ) {
                throw new ValidationException('Tu sesión ya no permite administrar usuarios. Inicia sesión nuevamente.');
            }
            $query = db()->prepare('SELECT id,role,status FROM users WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            $target = $query->fetch();
            if (!$target) {
                throw new ValidationException('Usuario no encontrado.');
            }
            if ($target['status'] === $status) {
                return;
            }
            if ($target['role'] === 'admin' && $status === 'inactive' && $activeAdmins <= 1) {
                throw new ValidationException('Debe permanecer al menos un administrador activo.');
            }
            User::setStatus($id, $status);
            db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$id]);
            AuditService::log(
                'Usuarios',
                $status === 'active' ? 'Usuario activado' : 'Usuario desactivado',
                $status === 'active' ? 'Acceso del usuario habilitado.' : 'Acceso del usuario deshabilitado; sesiones revocadas.',
                'User',
                $id,
                metadata:['before' => ['status' => $target['status']], 'after' => ['status' => $status]],
                actor:$admin
            );
        });
    }
    public static function payments(array $filters = []): array
    {
        require_admin();

        return [
        'payments' => Payment::allForAdmin($filters),
        'summary' => Payment::adminSummary(),
        'drivers' => Payment::driversWithPayments(),
        ];
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
            filters: $filters
        );
    }

    public static function dashboardForCurrentUser(): array
    {
        $user = DriverContext::managed() ?? require_auth();
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
