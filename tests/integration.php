<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$database = 'devioz_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$admin = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$passed = 0;
function check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $passed++;
    echo 'OK: ' . $name . PHP_EOL;
}
function rejected(callable $call, string $name): void
{
    try {
        $call();
    } catch (ValidationException $e) {
        check(true, $name);
        return;
    }
    check(false, $name);
}
try {
    $schema = str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql'));
    $admin->exec($schema);
    foreach (['finance_expenses.sql', 'account_settings_notifications.sql', 'migrations/20261014_payment_integrity.sql', 'migrations/20261015_reservation_renewals.sql'] as $path) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $path)));
    }
    putenv('DB_HOST=' . $host);
    putenv('DB_DATABASE=' . $database);
    putenv('DB_USERNAME=' . $username);
    putenv('DB_PASSWORD=' . $password);
    require $root . '/backend/bootstrap.php';
    // Conservar errores como fallo del proceso para que CI no pueda mostrar verde.
    restore_exception_handler();
    foreach (['admin', 'driver', 'other'] as $role) {
        User::create(['name' => 'Prueba ' . $role, 'email' => $role . '@integration.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => $role === 'admin' ? 'admin' : 'driver', 'status' => 'active']);
    }
    db()->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'devioz','Prueba'); INSERT INTO spaces(id,parking_id,code) VALUES(1,1,'P01'); INSERT INTO vehicles(id,user_id,plate,type) VALUES(1,2,'ABC-123','car')");
    $_SESSION['user_id'] = 1;
    check(count(User::all('Prueba')) === 3, 'búsqueda PDO de usuarios');
    $seedHash = password_hash('Testing123!', PASSWORD_DEFAULT);
    for ($i = 0; $i < 52; $i++) {
        User::create(['name' => 'Paged ' . $i, 'email' => 'paged' . $i . '@integration.invalid',
            'password' => $seedHash, 'role' => 'driver', 'status' => 'active']);
    }
    check(count(User::all('Paged')) === 51, 'primera página acotada con indicador de continuación');
    $_GET['page'] = 2;
    check(count(User::all('Paged')) === 2, 'segunda página conserva filtros sin perder registros');
    check((int) User::summary()['total'] === 55, 'totales globales no truncados por paginación');
    unset($_GET['page']);
    check(Payment::allForAdmin(['search' => 'prueba']) === [], 'búsqueda PDO de pagos vacíos');
    check(AuditLog::all('', 'prueba') === [], 'búsqueda PDO de auditoría vacía');
    check(db()->query('SELECT @@session.time_zone')->fetchColumn() === '-05:00', 'zona horaria SQL');
    check(monthly_end_date('2024-01-31') === '2024-02-28', 'mensualidad en año bisiesto');
    check(monthly_end_date('2026-01-31') === '2026-02-27', 'mensualidad fin de mes');
    $_SESSION['user_id'] = 2;
    $id = ReservationController::store(['space_id'=>1,'vehicle_id'=>1,'starts_on'=>ReservationController::today()]);
    check(Reservation::find($id)['status'] === 'confirmed', 'reserva creada');
    rejected(fn() => ReservationController::store(['space_id'=>1,'vehicle_id'=>1,'starts_on'=>ReservationController::today()]), 'plaza no se reserva dos veces');
    $payment = Payment::create(['reservation_id'=>$id,'amount'=>'100.00','method'=>'yape','provider'=>'manual','provider_tx_id'=>'123','voucher_path'=>null,'status'=>'pending']);
    Reservation::update($id,['payment_status'=>'pending']);
    try {
        Payment::create(['reservation_id'=>$id,'amount'=>'100.00','method'=>'yape','provider'=>'manual','provider_tx_id'=>'456','voucher_path'=>null,'status'=>'pending']);
        check(false,'índice único de pagos activos');
    } catch (PDOException $e) {
        check($e->getCode() === '23000','índice único de pagos activos');
    }
    $_SESSION['user_id'] = 1;
    PaymentController::confirm($payment);
    PaymentController::confirm($payment);
    check(Payment::find($payment)['status'] === 'paid', 'aprobar pago y reintento idempotente');
    rejected(fn() => PaymentController::reject($payment), 'decisión contraria rechazada');
    check((float) Payment::financeSummary(null, '2099-01-01')['total'] === 100.0, 'ingresos sin duplicar');
    foreach (['car', 'moto'] as $vehicleType) {
        $vehicleUpdate = db()->prepare('UPDATE vehicles SET type=? WHERE id=1');
        $vehicleUpdate->execute([$vehicleType]);
        ReservationController::checkin($id);
        $mapSpace = Space::allDetailed()[0];
        check($mapSpace['vehicle_type'] === $vehicleType && $mapSpace['status'] === 'occupied',
            'croquis obtiene tipo del vehículo ocupante: ' . $vehicleType);
        ob_start();
        require $root . '/frontend/admin/espacios.php';
        $mapHtml = ob_get_clean();
        $document = new DOMDocument();
        @$document->loadHTML($mapHtml);
        $xpath = new DOMXPath($document);
        $occupied = '//button[contains(@class,"status-occupied")]';
        $motorcycles = $xpath->query($occupied . '//*[contains(@class,"vehicle-motorcycle-icon")]')->length;
        $cars = $xpath->query($occupied . '//i[contains(@class,"bi-car-front-fill")]')->length;
        check($vehicleType === 'moto' ? ($motorcycles === 1 && $cars === 0) : ($cars === 1 && $motorcycles === 0),
            'croquis muestra solo el icono correspondiente: ' . $vehicleType);
        ReservationController::checkout($id);
    }
    // Una auditoría que falla debe revertir el cambio financiero completo.
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        transaction(function () use ($id): void {
            Reservation::update($id,['status'=>'cancelled']);
            AuditService::log('Prueba','Prueba','Fallo esperado.');
        });
        check(false,'rollback por fallo de auditoría');
    } catch (PDOException $e) {
        check(Reservation::find($id)['status'] === 'confirmed','rollback por fallo de auditoría');
    } finally {
        db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs');
    }
    $_SESSION['user_id'] = 2;
    ReservationController::requestCancellation($id,'Cancelación de prueba');
    $_SESSION['user_id'] = 1;
    ReservationController::approveCancellation($id);
    check(Reservation::find($id)['status'] === 'cancelled', 'cancelación aprobada');
    check(!PaymentController::eligible(Reservation::find($id)), 'reserva cancelada no admite pagos');
    // Documentos sintéticos en archivos temporales; no se usan comprobantes reales.
    $filename = 'integration_' . bin2hex(random_bytes(8)) . '.pdf';
    $fixture = $root . '/backend/uploads/comprobantes/' . $filename;
    file_put_contents($fixture, "%PDF-1.4\n%%EOF\n");
    try {
        $q=db()->prepare('UPDATE payments SET voucher_path=? WHERE id=?');
        $q->execute(['backend/uploads/comprobantes/'.$filename,$payment]);
        check(VoucherService::authorizedPath(['id'=>2,'role'=>'driver'],'payment',$payment) === realpath($fixture),'propietario obtiene documento');
        check(VoucherService::authorizedPath(['id'=>3,'role'=>'driver'],'payment',$payment) === null,'otro conductor no obtiene documento');
        check(VoucherService::authorizedPath(['id'=>1,'role'=>'admin'],'payment',$payment) !== null,'admin obtiene documento');
        check(VoucherService::authorizedPath(['id'=>3,'role'=>'driver'],'expense',1) === null,'conductor no accede a egresos');
    } finally {
        unlink($fixture);
    }
    echo $passed . " comprobaciones de integración correctas.\n";
} finally {
    // El nombre se genera aquí, no proviene del usuario ni de DB_DATABASE.
    if (preg_match('/^devioz_test_[a-f0-9]{12}$/D', $database) !== 1) {
        throw new RuntimeException('Nombre de limpieza inválido.');
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
}
