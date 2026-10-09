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
