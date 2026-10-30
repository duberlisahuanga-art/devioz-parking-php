<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$database = 'devioz_bridge_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$connection = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$privateFile = $root . '/backend/storage/' . $database . '.json';
$process = null;
$checks = 0;
function bridgeCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $checks++;
    echo 'OK: ' . $name . PHP_EOL;
}
function bridgeReject(callable $action, string $name): void
{
    try {
        $action();
    } catch (RuntimeException) {
        bridgeCheck(true, $name);
        return;
    }
    throw new RuntimeException('No se rechazó: ' . $name);
}
try {
    $connection->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    $connection->exec(file_get_contents($root . '/database/migrations/20261015_password_recovery.sql'));
    $connection->exec("ALTER TABLE cameras ADD COLUMN channel_number TINYINT UNSIGNED NULL,
        ADD COLUMN location VARCHAR(150) NULL, ADD COLUMN rtsp_path VARCHAR(255) NULL,
        ADD COLUMN stream_protocol ENUM('hls','webrtc') DEFAULT 'hls',
        ADD COLUMN preferred_quality ENUM('sub','main') DEFAULT 'sub',
        ADD COLUMN brightness INT DEFAULT 100, ADD COLUMN contrast INT DEFAULT 100,
        ADD COLUMN saturation INT DEFAULT 100, ADD COLUMN osd_datetime TINYINT DEFAULT 1,
        ADD COLUMN osd_camera_name TINYINT DEFAULT 1");
    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password, 'CAMERA_BRIDGE_CONFIG' => $privateFile] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    foreach (['admin', 'driver'] as $role) {
        User::create(['name' => $role, 'email' => $role . '@bridge.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => $role, 'status' => 'active']);
    }
    db()->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'estacionamiento-devioz','Prueba')");
    $_SESSION['user_id'] = 1;
    $_SESSION['session_version'] = 0;
    bridgeCheck(!CameraBridgeService::configured(), 'configuración inexistente se informa sin exponer rutas');
    file_put_contents($privateFile, '{invalid');
    bridgeCheck(!CameraBridgeService::configured(), 'JSON dañado se informa como no configurado');
    $sdp = "v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\na=recvonly\r\n";
    CameraBridgeService::validateOffer($sdp);
    bridgeCheck(true, 'oferta de vídeo de sólo recepción aceptada');
    foreach (['invalid', str_replace('recvonly', 'sendonly', $sdp), str_replace('recvonly', 'sendrecv', $sdp),
        $sdp . "m=application 9 UDP/DTLS/SCTP webrtc-datachannel\r\n", $sdp . str_repeat('x', 65536)] as $invalid) {
        bridgeReject(fn() => CameraBridgeService::validateOffer($invalid), 'oferta inválida o de publicación rechazada');
    }
    $portProbe = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
    $address = stream_socket_get_name($portProbe, false);
    $port = (int) substr($address, strrpos($address, ':') + 1);
    fclose($portProbe);
    $configuration = ['api' => ['listen' => '127.0.0.1:' . $port, 'username' => 'test',
        'password' => str_repeat('x', 32), 'local_auth' => true], 'streams' => [], 'devioz' => ['dvr_host' => '192.168.1.100']];
    foreach ([1, 2, 3, 4] as $channel) {
        foreach (['sub', 'main'] as $quality) {
            $configuration['streams']['dvr_ch' . $channel . '_' . $quality] = ['rtsp://synthetic-user:synthetic-secret@192.168.1.100'];
        }
    }
    file_put_contents($privateFile, json_encode($configuration, JSON_THROW_ON_ERROR));
    bridgeCheck(CameraBridgeService::configured(), 'API privada de loopback aceptada');
    $remote = $configuration;
    $remote['api']['listen'] = '192.168.1.100:1984';
    file_put_contents($privateFile, json_encode($remote, JSON_THROW_ON_ERROR));
    bridgeCheck(!CameraBridgeService::configured(), 'API remota rechazada para impedir proxy arbitrario');
    file_put_contents($privateFile, json_encode($configuration, JSON_THROW_ON_ERROR));
    CameraBridgeService::enableDvr(1);
    $cameras = Camera::all();
    bridgeCheck(count($cameras) === 4, 'los cuatro canales se preparan sin migraciones');
    CameraBridgeService::enableDvr(1);
    bridgeCheck(count(Camera::all()) === 4, 'preparación repetida no duplica cámaras');
    bridgeCheck(CameraBridgeService::source($cameras[0], 'sub') === 'dvr_ch1_sub', 'fuente calculada en servidor por canal');
    bridgeReject(fn() => CameraBridgeService::source($cameras[0], 'rtsp://other'), 'calidad no permitida rechazada');
    bridgeReject(fn() => CameraBridgeService::source([...$cameras[0], 'status' => 'inactive'], 'sub'), 'cámara inactiva rechazada');
    bridgeReject(fn() => CameraBridgeService::source([...$cameras[0], 'channel_number' => 5], 'sub'), 'canal fuera del DVR rechazado');
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/camera-bridge-upstream.php'],
        [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']], $pipes);
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $ready = @fsockopen('127.0.0.1', $port, $errno, $message, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    try {
        CameraBridgeService::signal((int) $cameras[0]['id'], 'sub', $sdp);
        throw new LogicException('Se aceptó el error de prueba.');
    } catch (CameraBridgeException $error) {
        bridgeCheck(!str_contains($error->getMessage(), 'synthetic-secret') && !str_contains($error->getMessage(), 'rtsp://'),
            'errores del upstream no revelan RTSP ni credenciales');
    }
    $status = CameraBridgeService::status();
    bridgeCheck($status['configured'] && !$status['running'] && !str_contains(json_encode($status), 'synthetic-secret'),
        'diagnóstico de fallo controlado y sin secretos');
    $_SESSION['user_id'] = 2;
    bridgeReject(fn() => CameraBridgeService::enableDvr(1), 'conductor no puede configurar DVR');
    bridgeReject(fn() => CameraBridgeService::signal((int) $cameras[0]['id'], 'sub', $sdp), 'conductor no puede negociar vídeo');
    echo $checks . " comprobaciones correctas.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (is_file($privateFile)) { unlink($privateFile); }
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $connection->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
