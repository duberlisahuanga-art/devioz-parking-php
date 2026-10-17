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
    foreach (['finance_expenses.sql', 'account_settings_notifications.sql', 'migrations/20261014_payment_integrity.sql', 'migrations/20261015_reservation_renewals.sql', 'migrations/20261015_password_recovery.sql'] as $path) {
        $admin->exec(str_replace('devioz_parking', $database, file_get_contents($root . '/database/' . $path)));
    }
    putenv('DB_HOST=' . $host);
    putenv('DB_DATABASE=' . $database);
    putenv('DB_USERNAME=' . $username);
    putenv('DB_PASSWORD=' . $password);
    $testStorage = $root . '/backend/storage/' . $database . '-integration';
    putenv('APP_STORAGE=' . $testStorage);
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
    // Audit guarantees: historical actor, rejected permissions and atomic writes.
    $actorId = User::create(['name'=>'Actor original','email'=>'actor@integration.invalid',
        'password'=>password_hash('Testing123!', PASSWORD_DEFAULT),'role'=>'driver','status'=>'active']);
    $_SESSION['user_id'] = $actorId;
    $eventId = AuditService::log('Prueba','Historico','Evento de identidad');
    $today = date('Y-m-d');
    check(count(AuditLog::all(filters: ['module'=>'Prueba','user_id'=>(string)$actorId,
        'from'=>$today,'to'=>$today,'action'=>'Historico'])) === 1, 'filtros combinados incluyen día completo');
    check(AuditLog::all(filters: ['module'=>'Prueba','entity_type'=>'Payment','entity_id'=>'99999']) === [],
        'filtros de entidad excluyen eventos ajenos');
    rejected(fn() => AuditLog::all(filters: ['from'=>'2026-02-30']), 'fecha imposible rechazada');
    rejected(fn() => AuditLog::all(filters: ['from'=>'2026-10-16','to'=>'2026-10-15']), 'rango invertido rechazado');
    rejected(fn() => AuditLog::all(filters: ['user_id'=>'1 OR 1=1']), 'identificador SQL rechazado');
    rejected(fn() => AuditLog::all(filters: ['module'=>['Prueba']]), 'filtro no escalar rechazado');
    User::updateProfile($actorId, 'Actor cambiado', 'changed@integration.invalid', null);
    $history = AuditLog::all('Historico', 'actor@integration.invalid');
    check(count($history) === 1 && $history[0]['user_name'] === 'Actor original', 'identidad y búsqueda conservan perfil histórico');
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$actorId]);
    $history = AuditLog::all('Historico');
    check(count(AuditLog::all(filters: ['user_id'=>(string)$actorId])) === 1, 'filtro de actor funciona tras eliminar cuenta');
    check($history[0]['user_id'] === null && $history[0]['user_email'] === 'actor@integration.invalid', 'identidad sobrevive a eliminación de cuenta');
    $_SESSION['user_id'] = 2;
    try {
        require_admin();
        check(false, 'conductor no puede consultar auditoría');
    } catch (RuntimeException $e) {
        check(http_response_code() === 403, 'permiso admin rechazado');
        http_response_code(200);
    }
    $security = AuditLog::all('Acceso rechazado');
    check(json_decode($security[0]['metadata'], true)['reason'] === 'admin_required', 'rechazo de permisos registrado');
    unset($_SESSION['user_id']);
    rejected(fn() => AuthController::login(['email'=>'missing@integration.invalid','password'=>'NeverLogThis123!']), 'login incorrecto rechazado');
    $security = AuditLog::all('Acceso rechazado');
    check(!str_contains($security[0]['metadata'], 'NeverLogThis') &&
        json_decode($security[0]['metadata'], true)['reason'] === 'invalid_credentials', 'fallo de login sin contraseña en auditoría');
    $_SESSION['user_id'] = 2;
    $vehicle = VehicleController::store(['plate'=>'TST-901','brand'=>'Prueba','model'=>'Prueba','type'=>'moto']);
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        try {
            VehicleController::store(['plate'=>'TST-902','brand'=>'Prueba','model'=>'Prueba','type'=>'moto']);
            check(false, 'alta de vehículo debe fallar sin auditoría');
        } catch (PDOException $e) {
            check(!Vehicle::plateExists('TST-902'), 'fallo de auditoría revierte alta de vehículo');
        }
        try {
            VehicleController::destroy($vehicle);
            check(false, 'baja de vehículo debe fallar sin auditoría');
        } catch (PDOException $e) {
            check(Vehicle::find($vehicle) !== null, 'fallo de auditoría revierte baja de vehículo');
        }
        unset($_SESSION['user_id']);
        try {
            AuthController::register(['name'=>'Rollback','email'=>'rollback@integration.invalid',
                'password'=>'Testing123!','password_confirmation'=>'Testing123!']);
            check(false, 'registro debe fallar sin auditoría');
        } catch (PDOException $e) {
            check(User::findByEmail('rollback@integration.invalid') === null && current_user_id() === null,
                'fallo de auditoría revierte cuenta y no inicia sesión');
        }
    } finally {
        db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs');
    }
    $_SESSION['user_id'] = 1;
    AuditService::log('Prueba detalle', 'Actualizada', 'Cambio sintético', 'Expense', 999, 'EG-999', [
        'reason'=>'<script>audit_test</script>',
        'before'=>['amount'=>'10.00', 'voucher_path'=>'secret_fixture_path'],
        'after'=>['amount'=>'20.00', 'voucher_path'=>'secret_fixture_path'],
        'password'=>'secret_fixture_password',
    ]);
    $_GET = ['module'=>'Prueba detalle'];
    ob_start();
    require $root . '/frontend/admin/auditoria.php';
    $auditHtml = ob_get_clean();
    check(str_contains($auditHtml, '10.00') && str_contains($auditHtml, '20.00'), 'detalle muestra antes y después');
    check(str_contains($auditHtml, '&lt;script&gt;audit_test&lt;/script&gt;') && !str_contains($auditHtml, '<script>audit_test'),
        'detalle escapa contenido HTML');
    check(!str_contains($auditHtml, 'secret_fixture_path') && !str_contains($auditHtml, 'secret_fixture_password'),
        'detalle excluye rutas y metadatos no permitidos');
    $_GET = [];
    $_SESSION['user_id'] = 3;
    rejected(fn() => ReservationController::requestCancellation($id, 'Prueba de rechazo'),
        'cancelación ajena rechazada');
    $denials = AuditLog::all('Acceso rechazado');
    check(json_decode($denials[0]['metadata'], true)['reason'] === 'cancellation_reservation_owner_required',
        'rechazo específico sobrevive al rollback');
    rejected(fn() => ReservationController::renew($id), 'renovación ajena rechazada');
    $denials = AuditLog::all('Acceso rechazado');
    check(json_decode($denials[0]['metadata'], true)['reason'] === 'renewal_reservation_owner_required',
        'renovación rechazada conserva auditoría');
    $fallbackStorage = $root . '/backend/storage/audit-fallback-test-' . bin2hex(random_bytes(6));
    $previousStorage = getenv('APP_STORAGE');
    putenv('APP_STORAGE=' . $fallbackStorage);
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        AuditService::denied('fallback_test');
        $fallbackFile = $fallbackStorage . '/audit-fallback/security-' . date('Y-m-d') . '.jsonl';
        $fallbackData = json_decode(file_get_contents($fallbackFile), true, 512, JSON_THROW_ON_ERROR);
        check($fallbackData['reason'] === 'fallback_test', 'contingencia conserva rechazo si la tabla no está disponible');
    } finally {
        db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs');
        putenv($previousStorage === false ? 'APP_STORAGE' : 'APP_STORAGE=' . $previousStorage);
        if (isset($fallbackFile) && is_file($fallbackFile)) unlink($fallbackFile);
        if (is_dir($fallbackStorage . '/audit-fallback')) rmdir($fallbackStorage . '/audit-fallback');
        if (is_dir($fallbackStorage)) rmdir($fallbackStorage);
    }
    require_once $root . '/backend/services/AuditPermissions.php';
    require_once $root . '/backend/services/AuditArchive.php';
    $limitedName = 'devioz_audit_test_' . bin2hex(random_bytes(6));
    $limitedPassword = bin2hex(random_bytes(32));
    $limitedAccount = $admin->quote($limitedName) . "@'127.0.0.1'";
    $admin->exec('CREATE USER ' . $limitedAccount . ' IDENTIFIED BY ' . $admin->quote($limitedPassword));
    try {
        AuditPermissions::grant($admin, $database, $limitedAccount);
        $limited = new PDO('mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4',
            $limitedName, $limitedPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        AuditPermissions::verify($limited);
        check(true, 'cuenta limitada permite consulta/inserción y bloquea modificación/borrado');
    } finally {
        $admin->exec('DROP USER ' . $limitedAccount);
    }
    $archive = $root . '/backend/storage/audit-test-' . bin2hex(random_bytes(6)) . '.jsonl';
    $archiveKey = bin2hex(random_bytes(32));
    $mirrorDirectory = $root . '/backend/storage/audit-mirror-test-' . bin2hex(random_bytes(6));
    try {
        $archived = AuditArchive::backup(db(), $archive, $archiveKey);
        check($archived > 0 && AuditArchive::verify(db(), $archive, $archiveKey) === $archived,
            'respaldo restaura todos los campos y verifica HMAC');
        AuditArchive::mirror($archive, $mirrorDirectory);
        AuditArchive::mirror($archive . '.manifest.json', $mirrorDirectory);
        check(AuditArchive::verify(db(), $mirrorDirectory . '/' . basename($archive), $archiveKey) === $archived,
            'segunda copia se restaura y coincide con el original');
        file_put_contents($archive, "{}\n", FILE_APPEND);
        try {
            AuditArchive::verify(db(), $archive, $archiveKey);
            check(false, 'respaldo alterado debe rechazarse');
        } catch (RuntimeException $error) {
            check(true, 'respaldo alterado rechazado');
        }
    } finally {
        if (is_file($archive)) unlink($archive);
        if (is_file($archive . '.manifest.json')) unlink($archive . '.manifest.json');
        foreach ([basename($archive), basename($archive) . '.manifest.json'] as $copyName) {
            if (is_file($mirrorDirectory . '/' . $copyName)) unlink($mirrorDirectory . '/' . $copyName);
        }
        if (is_dir($mirrorDirectory)) rmdir($mirrorDirectory);
    }
    // Exercise the maintenance command only against this disposable database.
    $retentionDirectory = $root . '/backend/storage/audit-retention-test-' . bin2hex(random_bytes(6));
    mkdir($retentionDirectory, 0700, true);
    $oldId = AuditLog::create(['action'=>'Prueba antigua','description'=>'Evento sintético']);
    $boundaryId = AuditLog::create(['action'=>'Prueba límite','description'=>'Evento sintético']);
    db()->prepare('UPDATE audit_logs SET created_at=? WHERE id=?')->execute(['2026-10-10 23:59:59', $oldId]);
    db()->prepare('UPDATE audit_logs SET created_at=? WHERE id=?')->execute(['2026-10-11 00:00:00', $boundaryId]);
    try {
        $environment = array_merge(getenv(), ['AUDIT_BACKUP_DIR'=>$retentionDirectory,
            'AUDIT_BACKUP_MIRROR_DIR'=>'', 'AUDIT_BACKUP_KEY'=>bin2hex(random_bytes(32)),
            'DB_MAINTENANCE_USERNAME'=>$username, 'DB_MAINTENANCE_PASSWORD'=>$password]);
        $process = proc_open([PHP_BINARY, $root . '/tools/prune-audit.php', '2026-10-11', '--apply'],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root, $environment);
        if (!is_resource($process)) throw new RuntimeException('No se inició prueba de limpieza.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'limpieza termina tras respaldar y restaurar en base aislada');
        $findAudit = db()->prepare('SELECT COUNT(*) FROM audit_logs WHERE id=?');
        $findAudit->execute([$oldId]);
        check((int)$findAudit->fetchColumn() === 0, 'limpieza elimina evento anterior al corte');
        $findAudit->execute([$boundaryId]);
        check((int)$findAudit->fetchColumn() === 1, 'limpieza conserva exactamente el inicio del primer día');
    } finally {
        foreach (glob($retentionDirectory . '/audit-before-prune-*') ?: [] as $file) unlink($file);
        rmdir($retentionDirectory);
    }
    $recoverId = User::create(['name'=>'Recuperación sintética','email'=>'recovery@integration.invalid',
        'password'=>password_hash('Before123!', PASSWORD_DEFAULT),'role'=>'driver','status'=>'active']);
    $_SESSION['user_id'] = $recoverId;
    $_SESSION['session_version'] = 0;
    check(current_user() !== null, 'sesión previa a recuperación válida');
    PasswordRecoveryService::request('recovery@integration.invalid');
    $queuedCipher = db()->query('SELECT encrypted_token FROM password_resets WHERE user_id=' . $recoverId)->fetchColumn();
    $mailToken = null;
    check(PasswordRecoveryService::deliver(function (string $email, string $token) use (&$mailToken): void {
        if ($email !== 'recovery@integration.invalid') throw new RuntimeException('Destinatario incorrecto');
        $mailToken = $token;
    }) === 1, 'cola entrega enlace al correo de la cuenta');
    $resetRow = db()->query('SELECT * FROM password_resets WHERE user_id=' . $recoverId)->fetch();
    check(is_string($mailToken) && $resetRow['token_hash'] === hash('sha256',$mailToken)
        && $resetRow['encrypted_token'] === null, 'token almacenado como hash y eliminado de la cola tras enviar');
    check(!str_contains(base64_decode($queuedCipher),$mailToken), 'cola pendiente cifra el token recuperable');
    rejected(fn() => PasswordRecoveryService::reset($mailToken,'After123!','Different123!'), 'confirmación incorrecta no consume enlace');
    PasswordRecoveryService::reset($mailToken,'After123!','After123!');
    check(password_verify('After123!',User::find($recoverId)['password']), 'enlace cambia contraseña sin conocer la anterior');
    rejected(fn() => PasswordRecoveryService::reset($mailToken,'Again123!','Again123!'), 'enlace usado no se puede reutilizar');
    $_SESSION['user_id'] = 3;
    current_user();
    $_SESSION['user_id'] = $recoverId;
    check(current_user() === null && current_user_id() === null, 'sesiones anteriores revocadas tras recuperación');
    PasswordRecoveryService::request('recovery@integration.invalid');
    $expiredToken = null;
    PasswordRecoveryService::deliver(function (string $email,string $token) use (&$expiredToken): void { $expiredToken=$token; });
    db()->exec('UPDATE password_resets SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_id=' . $recoverId);
    rejected(fn() => PasswordRecoveryService::reset($expiredToken,'After456!','After456!'), 'enlace vencido rechazado');
    PasswordRecoveryService::request('recovery@integration.invalid');
    $freshToken = null;
    PasswordRecoveryService::deliver(function (string $email,string $token) use (&$freshToken): void { $freshToken=$token; });
    rejected(fn() => PasswordRecoveryService::reset($expiredToken,'After456!','After456!'), 'solicitud nueva invalida enlace anterior');
    $beforeFailure = User::find($recoverId)['password'];
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        try {
            PasswordRecoveryService::reset($freshToken,'After789!','After789!');
            check(false,'fallo de auditoría debe revertir recuperación');
        } catch (PDOException $error) {
            check(User::find($recoverId)['password'] === $beforeFailure, 'fallo de auditoría revierte contraseña');
        }
    } finally {
        db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs');
    }
    PasswordRecoveryService::reset($freshToken,'After789!','After789!');
    check(password_verify('After789!',User::find($recoverId)['password']), 'enlace sigue válido tras rollback');
    PasswordRecoveryService::request('nobody@integration.invalid');
    check((int)db()->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 0, 'correo inexistente no genera enlace');
    User::create(['name'=>'Cuenta inactiva','email'=>'inactive@integration.invalid',
        'password'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'driver','status'=>'inactive']);
    PasswordRecoveryService::request('inactive@integration.invalid');
    check((int)db()->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 0, 'solicitud sin cuenta activa no crea enlace');
    $otherRecoveryId = User::create(['name'=>'Cola sintética','email'=>'queue@integration.invalid',
        'password'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'driver','status'=>'active']);
    PasswordRecoveryService::request('queue@integration.invalid');
    PasswordRecoveryService::deliver(static function (): void { throw new RuntimeException('Fallo sintético SMTP'); });
    $failedMail = db()->query('SELECT delivery_attempts,encrypted_token FROM password_resets WHERE user_id=' . $otherRecoveryId)->fetch();
    check((int)$failedMail['delivery_attempts'] === 1 && $failedMail['encrypted_token'] !== null,
        'fallo SMTP conserva cola cifrada para reintento limitado');
    $changedEmailToken = null;
    PasswordRecoveryService::deliver(function (string $email,string $token) use (&$changedEmailToken): void { $changedEmailToken=$token; });
    db()->prepare('UPDATE users SET email=? WHERE id=?')->execute(['changed-queue@integration.invalid',$otherRecoveryId]);
    rejected(fn() => PasswordRecoveryService::reset($changedEmailToken,'After789!','After789!'), 'cambio de correo invalida enlace anterior');
    try {
        for ($attempt = 0; $attempt < 4; $attempt++) PasswordRecoveryService::request('limited@integration.invalid');
        check(false,'solicitudes repetidas deben limitarse');
    } catch (RateLimitException $error) {
        check(true,'solicitudes repetidas limitadas sin bloquear la cuenta');
    }
    $_SESSION['user_id'] = 3;
    $_SESSION['session_version'] = 0;
    try {
        AdminController::changeUserStatus(['user_id'=>'2','status'=>'inactive']);
        check(false,'conductor no administra usuarios');
    } catch (RuntimeException $error) {
        check(http_response_code() === 403 && User::find(2)['status'] === 'active', 'conductor rechazado sin cambiar usuario');
        http_response_code(200);
    }
    $_SESSION['user_id'] = 1;
    rejected(fn() => AdminController::changeUserStatus(['user_id'=>'1','status'=>'inactive']), 'administrador no se desactiva a sí mismo');
    rejected(fn() => AdminController::changeUserStatus(['user_id'=>'2','status'=>'deleted']), 'estado fuera de lista permitido rechazado');
    rejected(fn() => AdminController::changeUserStatus(['user_id'=>'99999999','status'=>'inactive']), 'usuario inexistente rechazado');
    $businessCounts = [];
    foreach (['vehicles','reservations','payments'] as $table) $businessCounts[$table] = db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    db()->prepare('INSERT INTO password_resets(user_id,token_hash,email,password_fingerprint,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')
        ->execute([2,hash('sha256','synthetic-status-token'),'driver@integration.invalid',hash('sha256',User::find(2)['password'])]);
    AdminController::changeUserStatus(['user_id'=>'2','status'=>'inactive']);
    check(User::find(2)['status'] === 'inactive' && (int)User::find(2)['session_version'] === 1, 'desactivación revoca sesiones');
    check((int)db()->query('SELECT COUNT(*) FROM password_resets WHERE user_id=2')->fetchColumn() === 0, 'desactivación elimina recuperación pendiente');
    $statusEvents = db()->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='User' AND entity_id=2 AND action='Usuario desactivado'")->fetchColumn();
    AdminController::changeUserStatus(['user_id'=>'2','status'=>'inactive']);
    check(db()->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='User' AND entity_id=2 AND action='Usuario desactivado'")->fetchColumn() === $statusEvents,
        'reintento de desactivación no duplica auditoría ni versión');
    rejected(fn() => AuthController::login(['email'=>'driver@integration.invalid','password'=>'Testing123!']), 'usuario inactivo no inicia sesión');
    AdminController::changeUserStatus(['user_id'=>'2','status'=>'active']);
    check(User::find(2)['status'] === 'active' && (int)User::find(2)['session_version'] === 2, 'reactivación conserva revocación de sesiones antiguas');
    foreach ($businessCounts as $table=>$count) check(db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === $count,
        'activar/desactivar conserva ' . $table);
    db()->exec('RENAME TABLE audit_logs TO audit_logs_unavailable');
    try {
        try {
            AdminController::changeUserStatus(['user_id'=>'2','status'=>'inactive']);
            check(false,'fallo de auditoría debe revertir estado');
        } catch (PDOException $error) {
            check(User::find(2)['status'] === 'active' && (int)User::find(2)['session_version'] === 2,
                'fallo de auditoría revierte estado y revocación');
        }
    } finally { db()->exec('RENAME TABLE audit_logs_unavailable TO audit_logs'); }
    $secondAdmin = User::create(['name'=>'Segundo admin','email'=>'second-admin@integration.invalid',
        'password'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'admin','status'=>'active']);
    AdminController::changeUserStatus(['user_id'=>(string)$secondAdmin,'status'=>'inactive']);
    check(User::find($secondAdmin)['status'] === 'inactive' && User::find(1)['status'] === 'active', 'se puede desactivar otro administrador conservando uno activo');
    $_SESSION['user_id'] = 2;
    check(current_user() === null, 'sesión antigua no revive tras reactivar la cuenta');
    $thirdAdmin = User::create(['name'=>'Tercer admin','email'=>'third-admin@integration.invalid',
        'password'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'admin','status'=>'active']);
    db()->exec("UPDATE users SET status='inactive' WHERE id=1");
    db()->prepare("UPDATE users SET status='active',session_version=0 WHERE id=?")->execute([$secondAdmin]);
    try {
        $jobs = [];
        $start = (string)(microtime(true)+1.0);
        foreach ([[$secondAdmin,$thirdAdmin],[$thirdAdmin,$secondAdmin]] as [$actorId,$targetId]) {
            $process = proc_open([PHP_BINARY,$root . '/tests/user-status-worker.php',(string)$actorId,(string)$targetId,$start],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
            if (!is_resource($process)) throw new RuntimeException('No se inició carrera de administradores.');
            fclose($pipes[0]);
            $jobs[] = [$process,$pipes];
        }
        $outcomes = [];
        foreach ($jobs as [$process,$pipes]) {
            $outcomes[] = trim(stream_get_contents($pipes[1]));
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($process) !== 0) throw new RuntimeException('Falló carrera de administradores.');
        }
        sort($outcomes);
        check($outcomes === ['ACCEPTED','DENIED'], 'dos administradores no pueden desactivarse mutuamente en paralelo');
        check((int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn() === 1,
            'concurrencia conserva exactamente un administrador activo');
    } finally { db()->exec("UPDATE users SET status='active' WHERE id=1"); }
    $_SESSION['user_id'] = 1;
    $_SESSION['session_version'] = 0;
    echo $passed . " comprobaciones de integración correctas.\n";
} finally {
    // El nombre se genera aquí, no proviene del usuario ni de DB_DATABASE.
    if (preg_match('/^devioz_test_[a-f0-9]{12}$/D', $database) !== 1) {
        throw new RuntimeException('Nombre de limpieza inválido.');
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
    if (isset($testStorage)) {
        foreach (glob($testStorage . '/rate-limits/*.json') ?: [] as $file) unlink($file);
        if (is_dir($testStorage . '/rate-limits')) rmdir($testStorage . '/rate-limits');
        if (is_dir($testStorage)) rmdir($testStorage);
    }
}
