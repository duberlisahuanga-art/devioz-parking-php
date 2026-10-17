<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

$root = dirname(__DIR__);
$database = 'devioz_test_library_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '3306';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$admin = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
    $username, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$databaseCreated = false;
$passed = 0;

function libraryCheck(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $label);
    }
    $passed++;
    echo 'OK: ' . $label . PHP_EOL;
}

function libraryRejected(callable $call, string $label): void
{
    try {
        $call();
    } catch (ValidationException $error) {
        libraryCheck(true, $label);
        return;
    }
    libraryCheck(false, $label);
}

function libraryId(int $number): string
{
    return 'bbbb' . str_pad(dechex($number), 28, '0', STR_PAD_LEFT);
}

try {
    $admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $databaseCreated = true;
    $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    foreach (['account_settings_notifications.sql', 'migrations/20261015_password_recovery.sql',
        'migrations/20261016_camera_recordings.sql'] as $migration) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $migration)));
    }
    $insertUser = $admin->prepare('INSERT INTO users(id,name,email,password,role,status) VALUES(?,?,?,?,?,?)');
    foreach ([1 => 'admin', 2 => 'driver', 3 => 'admin'] as $id => $role) {
        $insertUser->execute([$id, 'Prueba biblioteca ' . $id, 'library' . $id . '@integration.invalid',
            password_hash('Testing123!', PASSWORD_DEFAULT), $role, 'active']);
    }
    $admin->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'library-one','Biblioteca uno'),(2,'library-two','Biblioteca dos'),(3,'library-empty','Biblioteca vacía')");
    $insert = $admin->prepare('INSERT INTO camera_recordings(public_id,parking_id,created_by,source_kind,format,state,duration_ms,byte_size,sha256,started_at,ended_at,created_at,updated_at) VALUES(?,?,1,\'webcam\',\'webm\',?,?,?,?,?,?,?,?)');
    $seed = static function (int $number, string $startedAt, int $duration = 30000, int $parking = 1, bool $ready = true) use ($insert): void {
        $start = new DateTimeImmutable($startedAt, new DateTimeZone('America/Lima'));
        $end = $start->modify('+' . $duration . ' milliseconds')->format('Y-m-d H:i:s.v');
        $id = libraryId($number);
        $insert->execute([$id, $parking, $ready ? 'ready' : 'pending', $ready ? $duration : null,
            $ready ? 128 : 0, $ready ? hash('sha256', $id) : null, $start->format('Y-m-d H:i:s.v'),
            $ready ? $end : null, $startedAt, $startedAt]);
    };
    for ($index = 1; $index <= 25; $index++) {
        $seed($index, sprintf('2026-10-03 08:%02d:00', $index - 1));
    }
    $seed(26, '2026-10-03 08:59:30', 60000);
    $seed(27, '2026-10-04 09:15:00', 60000);
    $seed(28, '2026-10-04 23:59:30', 60000);
    $seed(29, '2026-10-05 08:00:00', 60000);
    $seed(30, '2026-10-03 08:30:00', 30000, 1, false);
    $seed(31, '2026-10-03 08:30:00', 30000, 2);

    // Reuse the existing test account, as tests/integration.php does. Do not change
    // SQL users/grants or repair MariaDB's internal privilege tables from a test.
    // The local limited-account attempt was blocked by an Aria checksum error.
    putenv('APP_ENV=testing');
    putenv('DB_HOST=' . $host);
    putenv('DB_PORT=' . $port);
    putenv('DB_DATABASE=' . $database);
    putenv('DB_USERNAME=' . $username);
    putenv('DB_PASSWORD=' . $password);
    putenv('APP_STORAGE=' . $root . '/backend/storage/' . $database);
    putenv('CAMERA_RECORDINGS_PATH=' . $root . '/backend/storage/' . $database . '-recordings');
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    $_SESSION['user_id'] = 1;

    libraryCheck(db()->query('SELECT DATABASE()')->fetchColumn() === $database,
        'servicio conectado exclusivamente a la base de prueba generada');
    echo 'AVISO: esta ejecución no valida privilegios SQL mínimos; usa la cuenta de pruebas existente.' . PHP_EOL;

    $calendar = CameraRecordingService::calendar(1, '2026-10');
    libraryCheck($calendar['month'] === '2026-10' && $calendar['timezone'] === 'America/Lima', 'calendario utiliza el mes y la zona de Lima');
    libraryCheck(array_column($calendar['days'], 'count', 'date') === ['2026-10-03' => 26, '2026-10-04' => 2, '2026-10-05' => 2], 'conteos de días completos incluyen cruces y excluyen pendientes y otro estacionamiento');
    libraryCheck($calendar['latest_date'] === '2026-10-05', 'último día con grabaciones disponible');
    libraryCheck(CameraRecordingService::calendar(1, '2026-09')['days'] === [], 'mes sin grabaciones conserva calendario vacío');
    libraryCheck(CameraRecordingService::calendar(3, '2026-10')['latest_date'] === null, 'estacionamiento vacío no inventa un último día');
    $first = CameraRecordingService::library(1, '2026-10-03', null, '1');
    $second = CameraRecordingService::library(1, '2026-10-03', null, '2');
    libraryCheck($first['total'] === 26 && count($first['items']) === 20 && $first['has_more'] === true, 'primera página acotada conserva el total de 26 grabaciones');
    libraryCheck($second['total'] === 26 && count($second['items']) === 6 && $second['has_more'] === false, 'segunda página ofrece las seis grabaciones restantes');
    libraryCheck(array_column([...$first['items'], ...$second['items']], 'id') === array_map(libraryId(...), range(1, 26)), 'paginación cronológica no pierde ni duplica grabaciones');
    libraryCheck(count($first['hours']) === 24 && array_column($first['hours'], 'hour') === range(0, 23), 'selector incluye las 24 horas del día');
    $hours = array_column($first['hours'], 'count', 'hour');
    libraryCheck($hours[8] === 26 && $hours[9] === 1 && $hours[7] === 0, 'conteos por hora abarcan todas las páginas y el cruce de hora');
    $hourNine = CameraRecordingService::library(1, '2026-10-03', '09');
    libraryCheck($hourNine['total'] === 1 && $hourNine['items'][0]['id'] === libraryId(26), 'filtro 09 incluye un vídeo que comenzó a las 08:59:30');
    libraryCheck($hourNine['hour'] === 9 && $hourNine['hours'] === $first['hours'] && $hourNine['day_total'] === 26, 'filtrar una hora conserva los conteos del día completo');
    $midnight = CameraRecordingService::library(1, '2026-10-05', '00');
    libraryCheck($midnight['total'] === 1 && $midnight['items'][0]['id'] === libraryId(28), 'vídeo que cruza medianoche aparece en la hora cero del siguiente día');
    libraryCheck((new DateTimeImmutable($midnight['items'][0]['started_at']))->format('Y-m-d H:i:s P') === '2026-10-04 23:59:30 -05:00', 'fecha del vídeo conserva el inicio real y el desplazamiento de Lima');
    libraryCheck(CameraRecordingService::library(1, '2026-10-03', '07')['total'] === 0, 'hora vacía devuelve una lista vacía');
    libraryCheck(CameraRecordingService::library(2, '2026-10-03')['total'] === 1, 'consulta está aislada por estacionamiento');
    libraryCheck(CameraRecordingService::library(1, '2026-10-03', null, '3')['items'] === [], 'página fuera de los resultados no repite la última página');

    // Exact hour/day boundaries use [start, end), never a closed end interval.
    $seed(32, '2026-10-03 07:59:00', 60000);
    $seed(33, '2026-10-03 09:00:00', 60000);
    $seed(34, '2026-10-05 23:59:00', 60000);
    $boundaryEight = CameraRecordingService::library(1, '2026-10-03', '08');
    libraryCheck($boundaryEight['total'] === 26, 'fin exacto a las 08:00 y comienzo exacto a las 09:00 quedan fuera de la hora ocho');
    libraryCheck(CameraRecordingService::library(1, '2026-10-03', '09')['total'] === 2, 'inicio exacto a las nueve pertenece a la hora nueve');
    libraryCheck(CameraRecordingService::calendar(1, '2026-10')['latest_date'] === '2026-10-05', 'fin exacto a medianoche no crea un día adicional');
    libraryCheck(CameraRecordingService::library(1, '2026-10-06')['total'] === 0, 'fin exacto a medianoche no pertenece al día siguiente');
    $seed(35, '2026-10-03 08:00:00');
    $ties = CameraRecordingService::library(1, '2026-10-03', '08');
    libraryCheck(array_column(array_slice($ties['items'], 0, 2), 'id') === [libraryId(1), libraryId(35)], 'mismos inicios mantienen un orden estable por identificador interno');
    $seed(36, '2026-10-31 23:59:30', 60000);
    libraryCheck(array_column(CameraRecordingService::calendar(1, '2026-11')['days'], 'count', 'date') === ['2026-11-01' => 1], 'cruce de mes figura en el calendario del mes siguiente');

    foreach (['2026-13', '2026-2', '0000-01', '2026-10-01', '2026-10 OR 1=1'] as $month) {
        libraryRejected(fn() => CameraRecordingService::calendar(1, $month), 'rechaza mes inválido: ' . $month);
    }
    foreach (['2026-02-30', '2026-10-32', '2026-2-03', '0000-01-01', '2026-10-03 00:00:00'] as $date) {
        libraryRejected(fn() => CameraRecordingService::library(1, $date), 'rechaza fecha inválida: ' . $date);
    }
    foreach (['24', '-1', '1 OR 1=1', '08:00', ''] as $hour) {
        libraryRejected(fn() => CameraRecordingService::library(1, '2026-10-03', $hour), 'rechaza hora inválida: ' . $hour);
    }
    foreach (['0', '-1', '01', '1.5', '100001', '1 OR 1=1'] as $page) {
        libraryRejected(fn() => CameraRecordingService::library(1, '2026-10-03', null, $page), 'rechaza página inválida: ' . $page);
    }
    libraryRejected(fn() => CameraRecordingService::calendar(999999, '2026-10'), 'rechaza estacionamiento inexistente');
    $_SESSION['user_id'] = 3;
    libraryCheck(CameraRecordingService::library(1, '2026-10-03')['total'] === 29, 'otro administrador autorizado puede consultar la biblioteca');
    $_SESSION['user_id'] = 2;
    foreach (['calendario' => fn() => CameraRecordingService::calendar(1, '2026-10'),
        'biblioteca' => fn() => CameraRecordingService::library(1, '2026-10-03')] as $label => $call) {
        try {
            $call();
            libraryCheck(false, 'conductor no consulta ' . $label);
        } catch (RuntimeException $error) {
            libraryCheck(http_response_code() === 403, 'conductor recibe rechazo 403 para ' . $label);
            http_response_code(200);
        }
    }
    echo $passed . ' comprobaciones de biblioteca correctas.' . PHP_EOL;
} finally {
    // The name is generated here and validated before cleanup; no operational DB is accepted.
    if (preg_match('/^devioz_test_library_[a-f0-9]{12}$/D', $database) !== 1) {
        throw new RuntimeException('Destino de limpieza de pruebas inválido.');
    }
    if ($databaseCreated) {
        $admin->exec('DROP DATABASE `' . $database . '`');
    }
}
