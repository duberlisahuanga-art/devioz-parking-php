<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$state = json_decode(file_get_contents($root . '/backend/storage/browser-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^devioz_browser_[a-f0-9]{12}$/D', $state['database'])) exit(1);
foreach (['DB_DATABASE' => 'database', 'DB_HOST' => 'host', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'] as $key => $field) {
    putenv($key . '=' . $state[$field]);
}
require $root . '/backend/bootstrap.php';
restore_exception_handler();
$base = (int) ($argv[2] ?? 0);
if (!in_array($base, [100, 200], true)) exit(1);
if (($argv[1] ?? '') === 'seed') {
    $vehicle = db()->prepare("INSERT INTO vehicles(id,user_id,plate,brand,model,type) VALUES(?,2,?,'Honda','XR190L','moto')");
    $vehicle->execute([$base, 'MOT-' . $base]);
    $space = db()->prepare('INSERT INTO spaces(id,parking_id,code,status) VALUES(?,1,?,?)');
    $reservation = db()->prepare("INSERT INTO reservations(id,user_id,parking_id,space_id,vehicle_id,
        starts_on,ends_on,status,amount,payment_status) VALUES(?,2,1,?,?,CURDATE(),CURDATE(),?,100,'paid')");
    foreach ([0 => 'confirmed', 1 => 'active'] as $offset => $status) {
        $id = $base + $offset;
        $space->execute([$id, 'L' . $id, $status === 'active' ? 'occupied' : 'reserved']);
        $reservation->execute([$id, $id, $base, $status]);
    }
} elseif (($argv[1] ?? '') === 'age') {
    $stmt = db()->prepare('UPDATE reservations SET ends_on=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE id IN (?,?)');
    $stmt->execute([$base, $base + 1]);
    echo json_encode(ReservationLifecycleService::run(), JSON_THROW_ON_ERROR);
} else exit(1);
