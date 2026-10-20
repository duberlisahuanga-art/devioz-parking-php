<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);
$database = 'devioz_admin_test_' . bin2hex(random_bytes(6));
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$username = getenv('TEST_DB_USERNAME') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: '';
$connection = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $username, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function adminCreationCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $checks++;
    echo 'OK: ' . $name . PHP_EOL;
}
function adminCreationRejected(array $input, string $name): void
{
    try {
        AdminController::createAdministrator($input);
    } catch (RuntimeException) {
        adminCreationCheck(true, $name);
        return;
    }
    throw new RuntimeException('No se rechazó: ' . $name);
}
try {
    $connection->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/sql/schema.sql')));
    $connection->exec(file_get_contents($root . '/database/migrations/20261015_password_recovery.sql'));
    foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password] as $key => $value) {
        putenv($key . '=' . $value);
    }
    require $root . '/backend/bootstrap.php';
    restore_exception_handler();
    foreach (['admin', 'driver', 'second'] as $role) {
        User::create(['name' => $role, 'email' => $role . '@administrator.invalid',
            'password' => password_hash('Testing123!', PASSWORD_DEFAULT),
            'role' => $role === 'driver' ? 'driver' : 'admin', 'status' => 'active']);
    }
    $_SESSION['user_id'] = 1;
    $_SESSION['session_version'] = 0;
    $valid = ['name' => 'Nuevo administrador', 'email' => 'nuevo@administrator.invalid',
        'password' => 'Testing456!', 'password_confirmation' => 'Testing456!'];
    $before = (int) User::summary()['total'];
    foreach ([['name' => ''], ['name' => str_repeat('a', 151)], ['name' => ['invalid']], ['email' => 'incorrecto'],
        ['email' => str_repeat('a', 151) . '@example.com'], ['password' => 'short'],
        ['password' => str_repeat('a', 73)], ['password_confirmation' => 'different']] as $invalid) {
        adminCreationRejected([...$valid, ...$invalid], 'entrada inválida rechazada');
    }
    adminCreationCheck((int) User::summary()['total'] === $before, 'validaciones no crean cuentas');
    $id = AdminController::createAdministrator([...$valid, 'role' => 'driver', 'status' => 'inactive']);
    $created = User::find($id);
    adminCreationCheck($created['role'] === 'admin' && $created['status'] === 'active',
        'rol administrador completo y estado activo fijados en servidor');
    adminCreationCheck($created['password'] !== $valid['password'] && password_verify($valid['password'], $created['password']),
        'contraseña almacenada como hash válido');
    adminCreationCheck(current_user_id() === 1, 'crear cuenta mantiene la sesión del administrador autor');
    $audit = db()->query("SELECT user_id,metadata FROM audit_logs WHERE action='Administrador creado' ORDER BY id DESC LIMIT 1")->fetch();
    $metadata = json_decode($audit['metadata'], true, 512, JSON_THROW_ON_ERROR);
    adminCreationCheck((int) $audit['user_id'] === 1 && $metadata['actor']['id'] === 1, 'auditoría identifica al creador');
    adminCreationCheck(!str_contains($audit['metadata'], $valid['password'])
        && !str_contains($audit['metadata'], $created['password']), 'auditoría no contiene contraseña ni hash');
    adminCreationRejected([...$valid, 'email' => strtoupper($valid['email'])], 'correo duplicado rechazado sin distinguir mayúsculas');
    $_SESSION['user_id'] = 2;
    adminCreationRejected([...$valid, 'email' => 'forbidden@administrator.invalid'], 'conductor no puede crear administrador');
    $_SESSION['user_id'] = 1;
    // Simulate revocation after current_user() was cached: transaction must recheck the actor.
    current_user();
    db()->exec("UPDATE users SET status='inactive',session_version=1 WHERE id=1");
    adminCreationRejected([...$valid, 'email' => 'revoked@administrator.invalid'], 'sesión revocada rechazada dentro de la transacción');
    db()->exec("UPDATE users SET status='active',session_version=0 WHERE id=1");

    db()->exec("CREATE TRIGGER fail_admin_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'");
    adminCreationRejected([...$valid, 'email' => 'rollback@administrator.invalid'], 'fallo de auditoría aborta la creación');
    adminCreationCheck(User::findByEmail('rollback@administrator.invalid') === null, 'rollback no deja una cuenta sin auditar');
    db()->exec('DROP TRIGGER fail_admin_audit');

    $jobs = [];
    foreach ([1, 3] as $actorId) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/administrator-creation-worker.php', (string) $actorId],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $jobs[] = [$process, $pipes];
    }
    $results = [];
    foreach ($jobs as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Worker falló: ' . $errors);
        }
    }
    sort($results);
    adminCreationCheck($results === ['created', 'duplicate'], 'creaciones simultáneas producen una sola cuenta');
    $raceUser = User::findByEmail('race@administrator.invalid');
    $count = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE action='Administrador creado' AND entity_id=?");
    $count->execute([$raceUser['id']]);
    adminCreationCheck((int) $count->fetchColumn() === 1, 'creación simultánea deja un único evento de auditoría');
    echo $checks . " comprobaciones correctas.\n";
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $connection->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}
