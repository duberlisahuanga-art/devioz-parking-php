<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$database = 'devioz_management_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$admin = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function managementCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $checks++;
    echo 'OK: ' . $name . PHP_EOL;
}
function managementRejected(callable $action, string $name): void
{
    try {
        $action();
    } catch (RuntimeException $error) {
        managementCheck(true, $name);
        return;
    }
    throw new RuntimeException('No se rechazó: ' . $name);
}
try {
    $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    foreach (['finance_expenses.sql', 'account_settings_notifications.sql', 'migrations/20261014_payment_integrity.sql',
        'migrations/20261015_reservation_renewals.sql', 'migrations/20261015_password_recovery.sql'] as $migration) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $migration)));
    }
    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    foreach (['admin', 'driver', 'other'] as $role) {
        User::create(['name' => 'Prueba ' . $role, 'email' => $role . '@management.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT),
            'role' => $role === 'admin' ? 'admin' : 'driver', 'status' => 'active']);
    }
    db()->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'estacionamiento-devioz','Prueba');
        INSERT INTO spaces(id,parking_id,code) VALUES(1,1,'P01'),(2,1,'P02'),(3,1,'P03');
        INSERT INTO vehicles(id,user_id,plate,type) VALUES(1,3,'OTH-123','moto')");
    $_SESSION['user_id'] = 1;
    $_SESSION['session_version'] = 0;
    managementRejected(fn() => DriverContext::user(), 'administrador sin selección no entra como conductor');
    foreach (['1', '999999', 'invalid', ['2']] as $invalid) {
        $_GET['managed_driver_id'] = $invalid;
        managementRejected(fn() => DriverContext::user(), 'selección inválida o no conductora rechazada');
    }
    $_GET['managed_driver_id'] = '2';
    $selected = DriverContext::user();
    managementCheck((int) $selected['id'] === 2 && current_user_id() === 1 && is_admin(),
        'propietario seleccionado y sesión administradora separados');
    managementCheck(DriverContext::path('/frontend/conductor/reservas.php?view=history')
        === '/frontend/conductor/reservas.php?view=history&managed_driver_id=2', 'filtros conservan la selección');
    managementCheck(DriverContext::path('/frontend/admin/usuarios.php') === '/frontend/admin/usuarios.php',
        'regreso a administración no lleva contexto');

    $vehicle = VehicleController::store(['plate' => 'MGT-123', 'type' => 'moto', 'brand' => 'Prueba', 'model' => 'Prueba']);
    managementCheck((int) Vehicle::findOwned($vehicle, 2)['user_id'] === 2, 'vehículo registrado para el conductor');
    managementCheck(count(VehicleController::index()) === 1, 'lista de vehículos excluye los de otros conductores');
    managementCheck(count(ReservationController::createData()['vehicles']) === 1, 'formulario usa vehículos del conductor');
    $reservation = ReservationController::store(['space_id' => 1, 'vehicle_id' => $vehicle,
        'starts_on' => ReservationController::today()]);
    $row = Reservation::find($reservation);
    managementCheck((int) $row['user_id'] === 2 && $row['payment_status'] === 'unpaid',
        'reserva creada para el conductor y pendiente de pago');
    managementCheck(Space::find(1)['status'] === 'reserved', 'plaza queda reservada');
    $audit = db()->query("SELECT user_id,metadata FROM audit_logs WHERE action='Creada' ORDER BY id DESC LIMIT 1")->fetch();
    $metadata = json_decode($audit['metadata'], true, 512, JSON_THROW_ON_ERROR);
    managementCheck((int) $audit['user_id'] === 1 && $metadata['actor']['id'] === 1
        && $metadata['managed_driver']['id'] === 2, 'auditoría conserva administrador autor y conductor propietario');
    managementRejected(fn() => ReservationController::store(['space_id' => 2, 'vehicle_id' => 1,
        'starts_on' => ReservationController::today()]), 'vehículo de otro conductor rechazado');
    managementCheck(Space::find(2)['status'] === 'available', 'rechazo conserva la plaza disponible');
    managementRejected(fn() => ReservationController::store(['space_id' => 1, 'vehicle_id' => $vehicle,
        'starts_on' => ReservationController::today()]), 'plaza ya reservada no se asigna otra vez');
    managementRejected(fn() => PaymentController::submit($reservation, ['method' => 'cash'], []),
        'efectivo continúa fuera del alcance');
    managementCheck(count(ReservationController::index()) === 1, 'reservas limitadas al conductor gestionado');

    $_GET['managed_driver_id'] = '3';
    managementCheck(count(ReservationController::index()) === 0 && count(VehicleController::index()) === 1,
        'cambiar selección no mezcla datos');
    $_GET['managed_driver_id'] = '2';
    ReservationController::requestCancellation($reservation, 'Solicitud de prueba');
    managementCheck(Reservation::find($reservation)['cancellation_status'] === 'pending',
        'cancelación conserva revisión administrativa');
    managementRejected(fn() => VehicleController::destroy($vehicle), 'vehículo con reserva vigente protegido');
    User::setStatus(2, 'inactive');
    managementRejected(fn() => DriverContext::user(), 'conductor desactivado bloqueado en la siguiente petición');
    db()->exec("UPDATE users SET status='active',session_version=0 WHERE id=2");

    $_SESSION['user_id'] = 3;
    managementRejected(fn() => DriverContext::user(), 'conductor no puede usar selección administrativa');
    unset($_GET['managed_driver_id']);
    managementCheck((int) DriverContext::user()['id'] === 3 && DriverContext::metadata() === null,
        'acceso normal del conductor conserva identidad y no hereda selección');
    $ordinary = VehicleController::store(['plate' => 'REG-123', 'type' => 'moto', 'brand' => 'Prueba', 'model' => 'Prueba']);
    managementCheck(Vehicle::findOwned($ordinary, 3) !== null, 'registro normal de vehículo sigue funcionando');
    echo $checks . " comprobaciones correctas.\n";
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
