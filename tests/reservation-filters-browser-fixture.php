<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$state = json_decode(file_get_contents($root . '/backend/storage/browser-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^devioz_browser_[a-f0-9]{12}$/D', $state['database'])) exit(1);
$base = (int) ($argv[1] ?? 0);
if (!in_array($base, [1000, 2000], true)) exit(1);
foreach (['DB_DATABASE' => 'database', 'DB_HOST' => 'host', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'] as $key => $field) {
    putenv($key . '=' . $state[$field]);
}
require $root . '/backend/bootstrap.php';
restore_exception_handler();
$plate = 'FLT-' . ($base === 1000 ? '100' : '200');
$vehicle = db()->prepare("INSERT INTO vehicles(id,user_id,plate,brand,model,color,type) VALUES(?,2,?,'Honda','XR190L','Rojo','moto')");
$vehicle->execute([$base, $plate]);
$space = db()->prepare("INSERT INTO spaces(id,parking_id,code,status) VALUES(?,1,?,'reserved')");
$space->execute([$base, 'F' . $base]);
$historical = db()->prepare("INSERT INTO reservations(id,user_id,parking_id,space_id,vehicle_id,starts_on,ends_on,
    status,payment_status,cancellation_status,amount) VALUES(?,2,1,?,?,DATE_SUB(CURDATE(),INTERVAL 1 YEAR),
    DATE_SUB(CURDATE(),INTERVAL 11 MONTH),?,'paid',?,100)");
for ($i = 0; $i < 57; $i++) {
    $historical->execute([$base + $i, $base, $base, $i < 50 ? 'expired' : 'cancelled', $i < 50 ? 'none' : 'approved']);
}
$current = db()->prepare("INSERT INTO reservations(id,user_id,parking_id,space_id,vehicle_id,starts_on,ends_on,
    status,payment_status,amount) VALUES(?,2,1,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 MONTH),'confirmed','paid',100)");
$current->execute([$base + 100, $base, $base]);
echo json_encode(['today' => ReservationController::today(),
    'yesterday' => (new DateTimeImmutable(ReservationController::today()))->modify('-1 day')->format('Y-m-d')], JSON_THROW_ON_ERROR);
