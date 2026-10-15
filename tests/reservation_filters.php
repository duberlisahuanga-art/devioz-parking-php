<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$database = 'devioz_filters_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$admin = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function filterCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FALLO: ' . $message);
    $checks++;
    echo 'OK: ' . $message . PHP_EOL;
}
function filterResult(array $input): array
{
    return Reservation::searchForAdmin(ReservationFilters::fromInput($input));
}
try {
    $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    foreach (['finance_expenses.sql', 'account_settings_notifications.sql', 'migrations/20261014_payment_integrity.sql',
        'migrations/20261015_reservation_renewals.sql'] as $path) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $path)));
    }
    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    foreach ([['Admin', 'admin'], ['Ana Pérez', 'driver'], ['Luis Conductor', 'driver']] as $i => [$name, $role]) {
        User::create(['name' => $name, 'email' => 'user' . $i . '@filters.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => $role, 'status' => 'active']);
    }
    db()->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'test','Prueba');
        INSERT INTO vehicles(id,user_id,plate,type) VALUES(1,2,'MOT-123','moto'),(2,3,'CAR-123','car')");
    $today = ReservationController::today();
    $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
    $before = (new DateTimeImmutable($today))->modify('-1 month')->format('Y-m-d');
    $cases = [
        [1, 'active', 'paid', $today, $tomorrow, 'none'],
        [2, 'confirmed', 'pending', $tomorrow, $tomorrow, 'none'],
        [1, 'confirmed', 'unpaid', $today, $today, 'none'],
        [2, 'active', 'paid', $before, $yesterday, 'none'],
        [1, 'confirmed', 'paid', $before, $yesterday, 'none'],
        [2, 'confirmed', 'paid', $yesterday, $tomorrow, 'pending'],
        [2, 'cancelled', 'paid', $yesterday, $tomorrow, 'approved'],
        [1, 'expired', 'paid', $before, $yesterday, 'rejected'],
    ];
    $ids = [];
    foreach ($cases as $index => [$vehicle, $status, $payment, $start, $end, $cancel]) {
        $spaceId = $index + 1;
        $space = db()->prepare('INSERT INTO spaces(id,parking_id,code,status) VALUES(?,1,?,?)');
        $space->execute([$spaceId, 'P-' . $spaceId, $status === 'active' ? 'occupied' : 'reserved']);
        $ids[] = Reservation::create(['user_id' => $vehicle === 1 ? 2 : 3, 'parking_id' => 1,
            'space_id' => $spaceId, 'vehicle_id' => $vehicle, 'starts_on' => $start, 'ends_on' => $end,
            'status' => $status, 'payment_status' => $payment, 'amount' => 100, 'currency' => 'PEN', 'cancellation_status' => $cancel]);
    }
    for ($i = 0; $i < 57; $i++) {
        Reservation::create(['user_id' => 2, 'parking_id' => 1, 'space_id' => 8, 'vehicle_id' => 1,
            'starts_on' => $before, 'ends_on' => $yesterday, 'status' => 'expired', 'payment_status' => 'paid',
            'amount' => 100, 'currency' => 'PEN', 'cancellation_status' => 'none']);
    }
    filterCheck(filterResult([])['total'] === 6, 'vista en curso no mezcla historial');
    filterCheck(filterResult(['search' => 'ana pérez'])['total'] === 3, 'búsqueda de conductor sin distinguir mayúsculas');
    filterCheck(filterResult(['search' => 'USER1@filters.invalid'])['total'] === 3, 'búsqueda por correo');
    filterCheck(filterResult(['search' => 'mot123'])['total'] === 3, 'búsqueda de placa con o sin guion');
    $exact = filterResult(['search' => '#' . $ids[1]]);
    filterCheck($exact['total'] === 1 && (int) $exact['reservations'][0]['id'] === $ids[1], 'búsqueda por número de reserva');
    filterCheck(filterResult(['payment' => 'pending'])['total'] === 1, 'filtro de pago pendiente');
    filterCheck(filterResult(['cancellation' => 'pending'])['total'] === 1, 'filtro de cancelación pendiente');
    $combined = filterResult(['space_id' => 1, 'vehicle_type' => 'moto', 'status' => 'active', 'payment' => 'paid',
        'cancellation' => 'none', 'validity' => 'current', 'date_from' => $today, 'date_to' => $today]);
    filterCheck($combined['total'] === 1 && (int) $combined['reservations'][0]['id'] === $ids[0], 'todos los filtros se combinan');
    filterCheck(filterResult(['vehicle_type' => 'car', 'space_id' => 1])['total'] === 0, 'combinación sin coincidencias');
    filterCheck(filterResult(['validity' => 'future'])['total'] === 1, 'periodos por comenzar');
    filterCheck(filterResult(['validity' => 'current'])['total'] === 3, 'vigencia incluye el primer y último día');
    filterCheck(filterResult(['validity' => 'expired'])['total'] === 2, 'periodos vencidos por fecha');
    filterCheck(filterResult(['date_from' => $today, 'date_to' => $today])['total'] === 2, 'rango inclusivo de fecha de inicio');
    filterCheck(filterResult(['view' => 'history', 'status' => 'cancelled'])['total'] === 1, 'estado del historial');
    $first = filterResult(['view' => 'history']);
    $second = filterResult(['view' => 'history', 'page' => 2]);
    filterCheck($first['total'] === 59 && $second['total'] === 59 && count($first['reservations']) === 51
        && count($second['reservations']) === 9, 'contador completo y paginación de más de 50 coincidencias');
    filterCheck(count(array_unique(array_merge(array_column(array_slice($first['reservations'], 0, 50), 'id'),
        array_column($second['reservations'], 'id')))) === 59, 'páginas sin perder ni repetir reservas');
    filterCheck(filterResult(['view' => 'history', 'page' => 1000])['page'] === 2,
        'página fuera de rango se ajusta a la última disponible');
    foreach (["' OR 1=1 --", '%', '_'] as $query) {
        filterCheck(filterResult(['search' => $query])['total'] === 0, 'texto literal y SQL parametrizado: ' . $query);
    }
    $invalid = ReservationFilters::fromInput(['status' => ['active'], 'payment' => "paid' OR 1=1", 'page' => '-5']);
    filterCheck($invalid['status'] === '' && $invalid['payment'] === '' && $invalid['page'] === 1, 'parámetros inválidos normalizados');
    filterCheck(count(ReservationFilters::fromInput(['date_from' => '2026-02-30'])['errors']) === 1, 'fecha imposible detectada');
    filterCheck(count(ReservationFilters::fromInput(['date_from' => $tomorrow, 'date_to' => $today])['errors']) === 1,
        'rango invertido detectado');
    parse_str(ReservationFilters::query(ReservationFilters::fromInput(['vehicle_type' => 'moto', 'status' => 'active', 'page' => 2]),
        'history'), $tab);
    filterCheck($tab['vehicle_type'] === 'moto' && !isset($tab['status'], $tab['page']),
        'cambiar de pestaña conserva filtros compatibles y reinicia página');
    $_SESSION['user_id'] = 1;
    $filtered = ReservationController::adminData(['space_id' => 1, 'vehicle_type' => 'moto']);
    filterCheck($filtered['total'] === 1 && Reservation::find($ids[4])['status'] === 'expired'
        && Space::find(5)['status'] === 'available', 'el cierre automático incluye reservas ocultas por los filtros');
    filterCheck(ReservationController::adminData(['date_from' => $tomorrow, 'date_to' => $today])['reservations'] === [],
        'rango inválido no ejecuta una búsqueda sin límites');
    $badSpace = ReservationController::adminData(['space_id' => 999999]);
    filterCheck($badSpace['reservations'] === [] && count($badSpace['filters']['errors']) === 1,
        'plaza inexistente muestra error sin convertirla en consulta de todas las plazas');
    $_SESSION['user_id'] = 2;
    try {
        ReservationController::adminData([]);
        throw new LogicException('El conductor no debe consultar reservas del administrador.');
    } catch (RuntimeException $error) {
        if ($error instanceof LogicException) throw $error;
        filterCheck(http_response_code() === 403, 'filtros administrativos requieren rol administrador');
    }
    echo $checks . ' comprobaciones correctas.' . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
