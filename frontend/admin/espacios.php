<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();

$data = AdminController::spaces();

$spaces = $data['spaces'] ?? [];
$summary = $data['summary'] ?? [];

/*
|--------------------------------------------------------------------------
| Separar las 40 plazas en 3 filas
|--------------------------------------------------------------------------
|
| Fila 1: P-01 a P-13
| Fila 2: P-14 a P-26
| Fila 3: P-27 a P-40
|
*/

$row1 = array_slice($spaces, 0, 13);
$row2 = array_slice($spaces, 13, 13);
$row3 = array_slice($spaces, 26, 14);

/*
|--------------------------------------------------------------------------
| Contadores
|--------------------------------------------------------------------------
*/

$total = (int) ($summary['total'] ?? count($spaces));
$available = (int) ($summary['available'] ?? 0);
$reserved = (int) ($summary['reserved'] ?? 0);
$occupied = (int) ($summary['occupied'] ?? 0);
$maintenance = (int) ($summary['maintenance'] ?? 0);

render_page_start(
    'Espacios / Croquis',
    'espacios'
);
?>
<p class="small text-secondary" data-live-parking data-live-status role="status">Actualización automática cada 30 segundos.</p>
<script src="<?= h(app_url('/frontend/assets/js/parking-live.js')) ?>" defer></script>

<!-- =========================================================
     ENCABEZADO
========================================================= -->

<div class="spaces-page-header">

    <div>

        <h2 class="h5 mb-1">
            Espacios / Croquis
        </h2>

        <p class="text-secondary mb-0">
            Estacionamiento Devioz · Planta única · 40 plazas.
        </p>

    </div>

    <span class="badge spaces-total-badge">
        <i class="bi bi-p-square me-1"></i>
        <?= h((string) $total) ?> plazas registradas
    </span>

</div>

<!-- =========================================================
     KPIs
========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-6 col-xl">

        <div class="spaces-kpi-card">

            <div>

                <span>
                    Total
                </span>

                <strong>
                    <?= h((string) $total) ?>
                </strong>

            </div>

            <i class="bi bi-p-square"></i>

        </div>

    </div>

    <div class="col-6 col-xl">

        <div class="spaces-kpi-card is-available">

            <div>

                <span>
                    Disponibles
                </span>

                <strong>
                    <?= h((string) $available) ?>
                </strong>

            </div>

            <i class="bi bi-check-circle"></i>

        </div>

    </div>

    <div class="col-6 col-xl">

        <div class="spaces-kpi-card is-reserved">

            <div>

                <span>
                    Reservadas
                </span>

                <strong>
                    <?= h((string) $reserved) ?>
                </strong>

            </div>

            <i class="bi bi-clock"></i>

        </div>

    </div>

    <div class="col-6 col-xl">

        <div class="spaces-kpi-card is-occupied">

            <div>

                <span>
                    Ocupadas
                </span>

                <strong>
                    <?= h((string) $occupied) ?>
                </strong>

            </div>

            <i class="bi bi-car-front-fill"></i>

        </div>

    </div>

<!-- =========================================================
     CROQUIS + DETALLE
========================================================= -->

<div class="spaces-layout">

    <!-- =====================================================
         CROQUIS
    ====================================================== -->

    <div class="spaces-map-card">

        <!-- Cabecera -->

        <div class="spaces-map-heading">

            <div>

                <h3>
                    Estacionamiento Devioz — Planta única
                </h3>

                <p>
                    Lima, Perú · 40 plazas
                </p>

            </div>

            <div class="spaces-map-summary">

                <span class="summary-chip">
                    Total <?= h((string) $total) ?>
                </span>

                <span class="summary-chip available">
                    Libres <?= h((string) $available) ?>
                </span>

                <span class="summary-chip reserved">
                    Reservadas <?= h((string) $reserved) ?>
                </span>

                <span class="summary-chip occupied">
                    Ocupadas <?= h((string) $occupied) ?>
                </span>

            </div>

        </div>

        <!-- Vía superior -->

        <div class="parking-road">

            <div class="parking-road-line top"></div>

            <div class="parking-road-title">
                AV. NICOLÁS AYLLÓN
            </div>

            <div class="parking-road-line bottom"></div>

        </div>

        <!-- Entrada -->

        <div class="parking-entry">

            <span>
                ENTRADA / SALIDA
            </span>

            <i class="bi bi-chevron-down"></i>

        </div>

        <!-- =================================================
             FILA 1
        ================================================== -->

        <div class="parking-row">

            <?php foreach ($row1 as $space): ?>

                <?php
                $status = (string) ($space['status'] ?? 'available');
                $spaceId = (int) ($space['id'] ?? 0);
                $vehiclePlate = (string) ($space['vehicle_plate'] ?? '');
                $driverName = (string) ($space['driver_name'] ?? '');
                $driverEmail = (string) ($space['driver_email'] ?? '');
                $driverPhone = (string) ($space['driver_phone'] ?? '');
                $vehicleBrand = (string) ($space['vehicle_brand'] ?? '');
                $vehicleModel = (string) ($space['vehicle_model'] ?? '');
                $vehicleColor = (string) ($space['vehicle_color'] ?? '');
                $startsOn = (string) ($space['starts_on'] ?? '');
                $endsOn = (string) ($space['ends_on'] ?? '');
                $paymentStatus = (string) ($space['payment_status'] ?? '');
                $amount = (float) ($space['amount'] ?? 0);
                $reservationId = (int) ($space['reservation_id'] ?? 0);
                ?>

                <button
                    type="button"
                    class="parking-slot status-<?= h($status) ?> js-admin-space"
                    data-space-id="<?= h((string) $spaceId) ?>"
                    data-space-code="<?= h((string) ($space['code'] ?? '')) ?>"
                    data-space-status="<?= h($status) ?>"

                    data-driver-name="<?= h($driverName) ?>"
                    data-driver-email="<?= h($driverEmail) ?>"
                    data-driver-phone="<?= h($driverPhone) ?>"

                    data-vehicle-plate="<?= h($vehiclePlate) ?>"
                    data-vehicle-brand="<?= h($vehicleBrand) ?>"
                    data-vehicle-model="<?= h($vehicleModel) ?>"
                    data-vehicle-color="<?= h($vehicleColor) ?>"

                    data-starts-on="<?= h($startsOn) ?>"
                    data-ends-on="<?= h($endsOn) ?>"

                    data-payment-status="<?= h($paymentStatus) ?>"
                    data-amount="<?= h(number_format($amount, 2, '.', '')) ?>"

                    data-reservation-id="<?= h((string) $reservationId) ?>"
                >

                    <span class="parking-slot-code">
                        <?= h((string) ($space['code'] ?? '')) ?>
                    </span>

                    <div class="parking-slot-visual">

                        <?php if ($status === 'occupied'): ?>

                            <?php if (($space['vehicle_type'] ?? 'car') === 'moto'): ?>
                                <svg class="vehicle-motorcycle-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <circle cx="5" cy="17" r="4"/>
                                    <circle cx="19" cy="17" r="4"/>
                                    <path d="M19 17 15.5 6H12M14 6h3M3 9h5l3 7H5l4-6h6M11 16h3l3-5"/>
                                    <path d="M9 9h4l2 3h-5z" fill="currentColor" stroke="none"/>
                                </svg>
                            <?php else: ?>
                                <i class="bi bi-car-front-fill" aria-hidden="true"></i>
                            <?php endif; ?>

                        <?php elseif ($status === 'reserved'): ?>

                            <span class="parking-reserved-circle">
                                R
                            </span>

                        <?php elseif ($status === 'maintenance'): ?>

                            <i class="bi bi-tools"></i>

                        <?php else: ?>

                            <i class="bi bi-p-square"></i>

                        <?php endif; ?>

                    </div>

                    <span class="parking-slot-status">
                        <?= h(status_label($status)) ?>
                    </span>

                </button>

            <?php endforeach; ?>

        </div>

        <!-- Carril -->

        <div class="parking-lane">

            <span></span>

            <i class="bi bi-chevron-down"></i>

            <span></span>

            <i class="bi bi-chevron-down"></i>

            <span></span>

        </div>

        <!-- =================================================
             FILA 2
        ================================================== -->

        <div class="parking-row">

            <?php foreach ($row2 as $space): ?>

                <?php
                $status = (string) ($space['status'] ?? 'available');
                $vehiclePlate = (string) ($space['vehicle_plate'] ?? '');
                $driverName = (string) ($space['driver_name'] ?? '');
                $driverEmail = (string) ($space['driver_email'] ?? '');
                $driverPhone = (string) ($space['driver_phone'] ?? '');
                $vehicleBrand = (string) ($space['vehicle_brand'] ?? '');
                $vehicleModel = (string) ($space['vehicle_model'] ?? '');
                $vehicleColor = (string) ($space['vehicle_color'] ?? '');
                $startsOn = (string) ($space['starts_on'] ?? '');
                $endsOn = (string) ($space['ends_on'] ?? '');
                $paymentStatus = (string) ($space['payment_status'] ?? '');
                $amount = (float) ($space['amount'] ?? 0);
                $reservationId = (int) ($space['reservation_id'] ?? 0);
                ?>

                <button
                    type="button"
                    class="parking-slot status-<?= h($status) ?> js-admin-space"

                    data-space-code="<?= h((string) ($space['code'] ?? '')) ?>"
                    data-space-status="<?= h($status) ?>"

                    data-driver-name="<?= h($driverName) ?>"
                    data-driver-email="<?= h($driverEmail) ?>"
                    data-driver-phone="<?= h($driverPhone) ?>"

                    data-vehicle-plate="<?= h($vehiclePlate) ?>"
                    data-vehicle-brand="<?= h($vehicleBrand) ?>"
                    data-vehicle-model="<?= h($vehicleModel) ?>"
                    data-vehicle-color="<?= h($vehicleColor) ?>"

                    data-starts-on="<?= h($startsOn) ?>"
                    data-ends-on="<?= h($endsOn) ?>"

                    data-payment-status="<?= h($paymentStatus) ?>"
                    data-amount="<?= h(number_format($amount, 2, '.', '')) ?>"

                    data-reservation-id="<?= h((string) $reservationId) ?>"
                >

                    <span class="parking-slot-code">
                        <?= h((string) ($space['code'] ?? '')) ?>
                    </span>

                    <div class="parking-slot-visual">

                        <?php if ($status === 'occupied'): ?>

                            <?php if (($space['vehicle_type'] ?? 'car') === 'moto'): ?>
                                <svg class="vehicle-motorcycle-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <circle cx="5" cy="17" r="4"/>
                                    <circle cx="19" cy="17" r="4"/>
                                    <path d="M19 17 15.5 6H12M14 6h3M3 9h5l3 7H5l4-6h6M11 16h3l3-5"/>
                                    <path d="M9 9h4l2 3h-5z" fill="currentColor" stroke="none"/>
                                </svg>
                            <?php else: ?>
                                <i class="bi bi-car-front-fill" aria-hidden="true"></i>
                            <?php endif; ?>

                        <?php elseif ($status === 'reserved'): ?>

                            <span class="parking-reserved-circle">
                                R
                            </span>

                        <?php elseif ($status === 'maintenance'): ?>

                            <i class="bi bi-tools"></i>

                        <?php else: ?>

                            <i class="bi bi-p-square"></i>

                        <?php endif; ?>

                    </div>

                    <span class="parking-slot-status">
                        <?= h(status_label($status)) ?>
                    </span>

                </button>

            <?php endforeach; ?>

        </div>

        <!-- Carril -->

        <div class="parking-lane reverse">

            <span></span>

            <i class="bi bi-chevron-up"></i>

            <span></span>

            <i class="bi bi-chevron-up"></i>

            <span></span>

        </div>

        <!-- =================================================
             FILA 3
        ================================================== -->

        <div class="parking-row parking-row-last">

            <?php foreach ($row3 as $space): ?>

                <?php
                $status = (string) ($space['status'] ?? 'available');
                $vehiclePlate = (string) ($space['vehicle_plate'] ?? '');
                $driverName = (string) ($space['driver_name'] ?? '');
                $driverEmail = (string) ($space['driver_email'] ?? '');
                $driverPhone = (string) ($space['driver_phone'] ?? '');
                $vehicleBrand = (string) ($space['vehicle_brand'] ?? '');
                $vehicleModel = (string) ($space['vehicle_model'] ?? '');
                $vehicleColor = (string) ($space['vehicle_color'] ?? '');
                $startsOn = (string) ($space['starts_on'] ?? '');
                $endsOn = (string) ($space['ends_on'] ?? '');
                $paymentStatus = (string) ($space['payment_status'] ?? '');
                $amount = (float) ($space['amount'] ?? 0);
                $reservationId = (int) ($space['reservation_id'] ?? 0);
                ?>

                <button
                    type="button"
                    class="parking-slot status-<?= h($status) ?> js-admin-space"

                    data-space-code="<?= h((string) ($space['code'] ?? '')) ?>"
                    data-space-status="<?= h($status) ?>"

                    data-driver-name="<?= h($driverName) ?>"
                    data-driver-email="<?= h($driverEmail) ?>"
                    data-driver-phone="<?= h($driverPhone) ?>"

                    data-vehicle-plate="<?= h($vehiclePlate) ?>"
                    data-vehicle-brand="<?= h($vehicleBrand) ?>"
                    data-vehicle-model="<?= h($vehicleModel) ?>"
                    data-vehicle-color="<?= h($vehicleColor) ?>"

                    data-starts-on="<?= h($startsOn) ?>"
                    data-ends-on="<?= h($endsOn) ?>"

                    data-payment-status="<?= h($paymentStatus) ?>"
                    data-amount="<?= h(number_format($amount, 2, '.', '')) ?>"

                    data-reservation-id="<?= h((string) $reservationId) ?>"
                >

                    <span class="parking-slot-code">
                        <?= h((string) ($space['code'] ?? '')) ?>
                    </span>

                    <div class="parking-slot-visual">

                        <?php if ($status === 'occupied'): ?>

                            <?php if (($space['vehicle_type'] ?? 'car') === 'moto'): ?>
                                <svg class="vehicle-motorcycle-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <circle cx="5" cy="17" r="4"/>
                                    <circle cx="19" cy="17" r="4"/>
                                    <path d="M19 17 15.5 6H12M14 6h3M3 9h5l3 7H5l4-6h6M11 16h3l3-5"/>
                                    <path d="M9 9h4l2 3h-5z" fill="currentColor" stroke="none"/>
                                </svg>
                            <?php else: ?>
                                <i class="bi bi-car-front-fill" aria-hidden="true"></i>
                            <?php endif; ?>

                        <?php elseif ($status === 'reserved'): ?>

                            <span class="parking-reserved-circle">
                                R
                            </span>

                        <?php elseif ($status === 'maintenance'): ?>

                            <i class="bi bi-tools"></i>

                        <?php else: ?>

                            <i class="bi bi-p-square"></i>

                        <?php endif; ?>

                    </div>

                    <span class="parking-slot-status">
                        <?= h(status_label($status)) ?>
                    </span>

                </button>

            <?php endforeach; ?>

        </div>

        <!-- Leyenda -->

        <div class="spaces-map-legend">

            <span>
                <i class="legend-dot available"></i>
                Libre
            </span>

            <span>
                <i class="legend-dot reserved"></i>
                Reservada
            </span>

            <span>
                <i class="legend-dot occupied"></i>
                Ocupada
            </span>

            <span>
                <i class="legend-dot maintenance"></i>
                Mantenimiento
            </span>

        </div>

    </div>
    
<!-- =====================================================
     DETALLE DE PLAZA
====================================================== -->

<aside class="space-detail-card">

    <!-- Cabecera -->

    <div class="space-detail-header">

        <div>

            <span class="space-detail-eyebrow">
                Detalle de plaza
            </span>

            <h3 id="detailSpaceCode">
                Selecciona una plaza
            </h3>

        </div>

        <span
            class="badge text-bg-secondary"
            id="detailSpaceStatus"
        >
            —
        </span>

    </div>


    <!-- =================================================
         ESTADO INICIAL
    ================================================== -->

    <div
        class="space-detail-empty"
        id="spaceDetailEmpty"
    >

        <div class="space-detail-empty-icon">

            <i class="bi bi-cursor"></i>

        </div>

        <strong>
            Selecciona una plaza
        </strong>

        <p>
            Haz clic sobre cualquier espacio del croquis
            para consultar su información.
        </p>

    </div>


    <!-- =================================================
         PLAZA DISPONIBLE
    ================================================== -->

    <div
        class="space-available-message d-none"
        id="spaceAvailableMessage"
    >

        <div class="space-available-icon">

            <i class="bi bi-check-circle"></i>

        </div>

        <div>

            <strong>
                Plaza disponible
            </strong>

            <p>
                Esta plaza se encuentra libre y disponible
                para una nueva reserva.
            </p>

        </div>

    </div>


    <!-- =================================================
         INFORMACIÓN DE RESERVA / OCUPACIÓN
    ================================================== -->

    <div
        class="space-detail-content d-none"
        id="spaceDetailContent"
    >

        <!-- Conductor -->

        <div class="space-detail-section">

            <span class="space-detail-label">
                Conductor
            </span>

            <strong id="detailDriverName">
                —
            </strong>

            <small id="detailDriverEmail">
                —
            </small>

            <small id="detailDriverPhone">
                —
            </small>

        </div>


        <!-- Vehículo -->

        <div class="space-detail-section">

            <span class="space-detail-label">
                Vehículo
            </span>

            <strong id="detailVehiclePlate">
                —
            </strong>

            <small id="detailVehicleModel">
                —
            </small>

            <small id="detailVehicleColor">
                —
            </small>

        </div>


        <!-- Vigencia -->

        <div class="space-detail-section">

            <span class="space-detail-label">
                Vigencia
            </span>

            <div class="space-detail-row">

                <span>
                    Inicio
                </span>

                <strong id="detailStartsOn">
                    —
                </strong>

            </div>

            <div class="space-detail-row">

                <span>
                    Vencimiento
                </span>

                <strong id="detailEndsOn">
                    —
                </strong>

            </div>

        </div>


        <!-- Pago -->

        <div class="space-detail-section">

            <div class="space-detail-row">

                <div>

                    <span class="space-detail-label">
                        Pago
                    </span>

                    <strong
                        id="detailPaymentStatus"
                        class="badge text-bg-secondary"
                    >
                        —
                    </strong>

                </div>

                <div class="text-end">

                    <span class="space-detail-label">
                        Monto
                    </span>

                    <strong id="detailAmount">
                        —
                    </strong>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         RESERVA
    ================================================== -->

    <div
        class="space-detail-footer d-none"
        id="spaceDetailFooter"
    >

        <span>
            Reserva
        </span>

        <strong id="detailReservationId">
            —
        </strong>

    </div>

</aside>
</div>

<?php

render_page_end();

?>
