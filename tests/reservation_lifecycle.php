<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$database = 'devioz_lifecycle_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$user = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$admin = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function lifecycleCheck(bool $value, string $name): void
{
    global $checks;
    if (!$value) throw new RuntimeException('FALLO: ' . $name);
    $checks++;
    echo 'OK: ' . $name . PHP_EOL;
}
function lifecycleRejected(callable $action, string $name): void
{
    try {
        $action();
    } catch (ValidationException $error) {
        lifecycleCheck(true, $name);
        return;
    }
    throw new RuntimeException('No se rechazó: ' . $name);
}
function lifecycleSeed(int $spaceId, string $status, string $end, string $cancellation = 'none'): int
{
    $space = db()->prepare('INSERT INTO spaces(id,parking_id,code,status) VALUES(?,1,?,?)');
    $space->execute([$spaceId, 'P-' . $spaceId, $status === 'active' ? 'occupied' : 'reserved']);
    return Reservation::create(['user_id' => 2, 'parking_id' => 1, 'space_id' => $spaceId,
        'vehicle_id' => 1, 'starts_on' => '2020-01-01', 'ends_on' => $end, 'status' => $status,
        'amount' => 100, 'currency' => 'PEN', 'payment_status' => 'paid', 'cancellation_status' => $cancellation]);
}
function lifecycleRace(string $mode, int $id = 0): array
{
    $jobs = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/reservation-lifecycle-worker.php', $mode, (string) $id],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('No se pudo crear el proceso de prueba.');
        fclose($pipes[0]);
        $jobs[] = [$process, $pipes];
    }
    $results = [];
    foreach ($jobs as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker falló: ' . $err);
        $results[] = $out;
    }
    return $results;
}
try {
    $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    foreach (['finance_expenses.sql', 'account_settings_notifications.sql',
        'migrations/20261014_payment_integrity.sql', 'migrations/20261015_reservation_renewals.sql'] as $path) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $path)));
    }
    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $password] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    foreach (['admin', 'driver', 'other'] as $role) {
        User::create(['name' => 'Prueba ' . $role, 'email' => $role . '@lifecycle.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => $role === 'admin' ? 'admin' : 'driver',
            'status' => 'active']);
    }
    db()->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'test','Prueba');
        INSERT INTO vehicles(id,user_id,plate,type) VALUES(1,2,'MOT-123','moto')");
    $today = ReservationController::today();
    $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    $soon = (new DateTimeImmutable($today))->modify('+3 days')->format('Y-m-d');
    $outside = lifecycleSeed(1, 'confirmed', $yesterday);
    $inside = lifecycleSeed(2, 'active', $yesterday);
    $lastDay = lifecycleSeed(3, 'confirmed', $today);
    $reminder = lifecycleSeed(4, 'confirmed', $soon);
    $pendingCancellation = lifecycleSeed(5, 'confirmed', $yesterday, 'pending');
    $payment = Payment::create(['reservation_id' => $outside, 'amount' => 100, 'method' => 'yape',
        'provider' => 'manual', 'provider_tx_id' => '123', 'voucher_path' => null, 'status' => 'paid']);
    $first = ReservationLifecycleService::run();
    lifecycleCheck($first['closed'] === 2, 'cierre automático de vencidas con vehículo fuera');
    lifecycleCheck(Reservation::find($outside)['status'] === 'expired' && Space::find(1)['status'] === 'available',
        'liberación de plaza sin eliminar la reserva');
    lifecycleCheck(Reservation::find($inside)['status'] === 'active' && Space::find(2)['status'] === 'occupied',
        'vehículo dentro conserva ocupación después del vencimiento');
    lifecycleCheck(Reservation::find($lastDay)['status'] === 'confirmed', 'último día sigue incluido');
    lifecycleCheck(Payment::find($payment)['status'] === 'paid', 'historial de pago aprobado conservado');
    lifecycleCheck(!PaymentController::eligible(Reservation::find($outside)), 'mensualidad cerrada no admite pagos nuevos');
    $notifications = (int) db()->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    lifecycleCheck($notifications >= 8, 'avisos de cierre, vencimiento próximo y vehículo dentro');
    lifecycleCheck(ReservationLifecycleService::run()['closed'] === 0
        && (int) db()->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === $notifications,
        'ejecuciones repetidas no duplican cierres ni avisos');
    $_SESSION['user_id'] = 1;
    ReservationController::checkout($inside);
    lifecycleCheck(Reservation::find($inside)['status'] === 'expired' && Space::find(2)['status'] === 'available',
        'salida después del vencimiento libera la plaza');
    lifecycleCheck(count(Reservation::all(true)) === 3 && count(Reservation::all(false)) === 2,
        'historial separado de mensualidades en curso');
    $_SESSION['user_id'] = 3;
    lifecycleRejected(fn() => ReservationController::renew($outside), 'otro conductor no puede renovar');
    $_SESSION['user_id'] = 2;
    $renewal = ReservationController::renew($outside);
    $new = Reservation::find($renewal);
    lifecycleCheck((int) $new['renewed_from_id'] === $outside && $new['payment_status'] === 'unpaid'
        && $new['starts_on'] === $today && $new['ends_on'] === monthly_end_date($today),
        'renovación crea una mensualidad nueva desde hoy y exige otro pago');
    lifecycleCheck(Space::find(1)['status'] === 'reserved' && Reservation::find($outside)['status'] === 'expired',
        'renovación conserva el historial y reserva la plaza');
    lifecycleCheck(ReservationController::renew($outside) === $renewal, 'doble envío de renovación es idempotente');
    lifecycleCheck(ReservationController::entryBlockReason($new) !== null, 'renovación sin pago no permite ingreso');
    lifecycleRejected(fn() => ReservationController::renew($lastDay), 'no se renueva una mensualidad aún vigente');
    // An old cancellation must never free a space reassigned after automatic expiry.
    Space::setStatus(5, 'occupied');
    $_SESSION['user_id'] = 1;
    ReservationController::approveCancellation($pendingCancellation);
    lifecycleCheck(Space::find(5)['status'] === 'occupied'
        && Reservation::find($pendingCancellation)['status'] === 'expired', 'revisión tardía no libera una plaza reasignada');
    $rollback = lifecycleSeed(6, 'confirmed', $yesterday);
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        ReservationLifecycleService::run();
        throw new LogicException('Debió fallar la auditoría.');
    } catch (PDOException $error) {
        lifecycleCheck(Reservation::find($rollback)['status'] === 'confirmed' && Space::find(6)['status'] === 'reserved',
            'fallo de auditoría revierte cierre y liberación');
    } finally {
        db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs');
    }
    lifecycleRace('expire');
    lifecycleCheck(Reservation::find($rollback)['status'] === 'expired', 'dos procesos cierran una reserva sin conflictos');
    $audit = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_id=? AND action='Vencimiento automático'");
    $audit->execute([$rollback]);
    lifecycleCheck((int) $audit->fetchColumn() === 1, 'concurrencia no duplica la auditoría');
    $renewResults = lifecycleRace('renew', $rollback);
    lifecycleCheck($renewResults[0] === $renewResults[1], 'dos renovaciones concurrentes generan una sola mensualidad');
    echo $checks . ' comprobaciones correctas.' . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
