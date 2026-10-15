<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/controllers/ReservationController.php';

$today = ReservationController::today();
$date = new DateTimeImmutable($today);
$yesterday = $date->modify('-1 day')->format('Y-m-d');
$tomorrow = $date->modify('+1 day')->format('Y-m-d');
$base = [
    'status' => 'confirmed',
    'cancellation_status' => 'none',
    'payment_status' => 'paid',
    'starts_on' => $today,
    'ends_on' => $tomorrow,
];
$cases = [
    ['Ingreso pagado y vigente', [], true],
    ['Sin pago', ['payment_status' => 'unpaid'], false],
    ['Pago en revisión', ['payment_status' => 'pending'], false],
    ['Inicio futuro', ['starts_on' => $tomorrow], false],
    ['Periodo vencido', ['ends_on' => $yesterday], false],
    ['Último día incluido', ['ends_on' => $today], true],
    ['Cancelación pendiente', ['cancellation_status' => 'pending'], false],
    ['Cancelación rechazada', ['cancellation_status' => 'rejected'], true],
    ['Vehículo ya dentro', ['status' => 'active'], false],
    ['Reserva cancelada', ['status' => 'cancelled'], false],
    ['Reserva cerrada', ['status' => 'expired'], false],
];
foreach ($cases as [$name, $changes, $allowed]) {
    $reason = ReservationController::entryBlockReason(array_replace($base, $changes));
    if (($reason === null) !== $allowed) {
        throw new RuntimeException('Fallo: ' . $name);
    }
    echo 'OK: ' . $name . PHP_EOL;
}
echo count($cases) . ' casos correctos.' . PHP_EOL;
