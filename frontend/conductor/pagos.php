<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

/*
|--------------------------------------------------------------------------
| Conductor autenticado
|--------------------------------------------------------------------------
*/

$user = require_driver();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Envío de voucher desde esta pantalla
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reservationId =
        (int) ($_POST['reservation_id'] ?? 0);

    run_post_action(
        function () use ($reservationId): void {

            PaymentController::submit(
                $reservationId,
                $_POST,
                $_FILES
            );

        },
        'Voucher enviado. El pago quedó pendiente de validación.',
        '/frontend/conductor/pagos.php'
    );
}

/*
|--------------------------------------------------------------------------
| Reservas del conductor
|--------------------------------------------------------------------------
*/

$reservations =
    Reservation::forUser($userId);

/*
|--------------------------------------------------------------------------
| Historial de pagos EXCLUSIVO del conductor autenticado
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "
    SELECT
        py.id,
        py.reservation_id,
        py.amount,
        py.method,
        py.provider,
        py.provider_tx_id,
        py.voucher_path,
        py.status,
        py.created_at,

        r.starts_on,
        r.ends_on,
        r.status AS reservation_status,

        s.code AS space_code,

        v.plate AS vehicle_plate

    FROM payments py

    INNER JOIN reservations r
        ON r.id = py.reservation_id

    INNER JOIN spaces s
        ON s.id = r.space_id

    LEFT JOIN vehicles v
        ON v.id = r.vehicle_id

    WHERE r.user_id = :user_id

    ORDER BY py.created_at DESC
    "
);

$stmt->execute([
    'user_id' => $userId,
]);

$payments =
    $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Estadísticas
|--------------------------------------------------------------------------
*/

$totalPayments = count($payments);

$pendingPayments = 0;
$paidPayments = 0;
$rejectedPayments = 0;

$totalPaid = 0.0;

foreach ($payments as $payment) {

    $status =
        (string) $payment['status'];

    if ($status === 'pending') {

        $pendingPayments++;

    } elseif ($status === 'paid') {

        $paidPayments++;

        $totalPaid +=
            (float) $payment['amount'];

    } elseif ($status === 'rejected') {

        $rejectedPayments++;
    }
}

/*
|--------------------------------------------------------------------------
| Reserva preseleccionada
|--------------------------------------------------------------------------
*/

$selected =
    (int) ($_GET['reservation_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

render_page_start(
    'Pagos Yape',
    'pagos'
);
?>

<!-- =========================================================
     ENCABEZADO
========================================================= -->

<div class="mb-4">

    <h2 class="h5 mb-1">
        Pagos Yape
    </h2>

    <p class="text-secondary mb-0">
        Realiza tus pagos y consulta el historial
        de comprobantes enviados.
    </p>

</div>

<!-- =========================================================
     RESUMEN DE PAGOS
========================================================= -->

<div class="row g-3 mb-4">

    <!-- Total -->

    <div class="col-6 col-xl-3">

        <div class="metric-card">

            <div class="metric-icon">

                <i class="bi bi-receipt"></i>

            </div>

            <div>

                <span>
                    Comprobantes
                </span>

                <strong>
                    <?= h((string) $totalPayments) ?>
                </strong>

            </div>

        </div>

    </div>

    <!-- Pendientes -->

    <div class="col-6 col-xl-3">

        <div class="metric-card">

            <div class="metric-icon">

                <i class="bi bi-clock-history"></i>

            </div>

            <div>

                <span>
                    Pendientes
                </span>

                <strong>
                    <?= h((string) $pendingPayments) ?>
                </strong>

            </div>

        </div>

    </div>

    <!-- Pagados -->

    <div class="col-6 col-xl-3">

        <div class="metric-card">

            <div class="metric-icon">

                <i class="bi bi-check-circle"></i>

            </div>

            <div>

                <span>
                    Aprobados
                </span>

                <strong>
                    <?= h((string) $paidPayments) ?>
                </strong>

            </div>

        </div>

    </div>

    <!-- Total pagado -->

    <div class="col-6 col-xl-3">

        <div class="metric-card">

            <div class="metric-icon">

                <i class="bi bi-wallet2"></i>

            </div>

            <div>

                <span>
                    Total pagado
                </span>

                <strong>
                    <?= h(
                        money(
                            $totalPaid
                        )
                    ) ?>
                </strong>

            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     CONTENIDO
========================================================= -->

<div class="row g-4">

    <!-- =====================================================
         HISTORIAL
    ====================================================== -->

    <div class="col-12">

        <div class="panel-card">

            <div class="panel-heading">

                <div>

                    <h2>
                        Historial de pagos
                    </h2>

                    <p>
                        Todos tus comprobantes enviados
                    </p>

                </div>

                <?php if ($rejectedPayments > 0): ?>

                    <span class="badge text-bg-danger">

                        <?= h(
                            (string) $rejectedPayments
                        ) ?>

                        rechazado<?= $rejectedPayments !== 1
                            ? 's'
                            : '' ?>

                    </span>

                <?php endif; ?>

            </div>

            <?php if (!$payments): ?>

                <!-- Sin pagos -->

                <div class="text-center py-5">

                    <i
                        class="bi bi-receipt
                               display-5
                               text-secondary"
                    ></i>

                    <h3 class="h6 mt-3">

                        Aún no tienes pagos registrados

                    </h3>

                    <p class="text-secondary small mb-0">

                        Los comprobantes que envíes
                        aparecerán en este historial.

                    </p>

                </div>

            <?php else: ?>

                <!-- Tabla -->

                <div class="table-responsive">

                    <table
                        class="table align-middle mb-0"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Fecha
                                </th>

                                <th>
                                    Reserva
                                </th>

                                <th>
                                    Plaza / Vehículo
                                </th>

                                <th>
                                    Monto
                                </th>

                                <th>
                                    Operación
                                </th>

                                <th>
                                    Estado
                                </th>

                                <th>
                                    Comprobante
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($payments as $p): ?>

                            <tr>

                                <!-- Fecha -->

                                <td>

                                    <?= h(
                                        format_datetime(
                                            $p['created_at']
                                        )
                                    ) ?>

                                </td>

                                <!-- Reserva -->

                                <td>

                                    <strong>

                                        #<?= h(
                                            (string)
                                            $p['reservation_id']
                                        ) ?>

                                    </strong>

                                    <div
                                        class="small text-secondary"
                                    >

                                        Mensual

                                    </div>

                                </td>

                                <!-- Plaza / vehículo -->

                                <td>

                                    <strong>

                                        <?= h(
                                            (string)
                                            $p['space_code']
                                        ) ?>

                                    </strong>

                                    <?php if (!empty($p['vehicle_plate'])): ?>

                                        <div
                                            class="small text-secondary"
                                        >

                                            <?= h(
                                                (string)
                                                $p['vehicle_plate']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>

                                <!-- Monto -->

                                <td>

                                    <strong>

                                        <?= h(
                                            money(
                                                $p['amount']
                                            )
                                        ) ?>

                                    </strong>

                                </td>

                                <!-- Operación -->

                                <td>

                                    <?= h(
                                        (string)
                                        $p['provider_tx_id']
                                    ) ?>

                                </td>

                                <!-- Estado -->

                                <td>

                                    <span
                                        class="badge <?= h(
                                            badge_class(
                                                (string)
                                                $p['status']
                                            )
                                        ) ?>"
                                    >

                                        <?= h(
                                            status_label(
                                                (string)
                                                $p['status']
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <!-- Voucher -->

                                <td>

                                    <?php if (!empty($p['voucher_path'])): ?>

                                        
<?php
$voucherUrl = app_url(
    '/'
    . ltrim(
        (string) $p['voucher_path'],
        '/'
    )
);

$voucherExtension = strtolower(
    pathinfo(
        (string) $p['voucher_path'],
        PATHINFO_EXTENSION
    )
);
?>

<button
    type="button"
    class="btn btn-sm btn-outline-primary js-view-voucher"
    data-bs-toggle="modal"
    data-bs-target="#voucherModal"
    data-voucher-url="<?= h($voucherUrl) ?>"
    data-voucher-type="<?= h($voucherExtension) ?>"
    data-reservation-id="<?= h((string) $p['reservation_id']) ?>"
    data-space-code="<?= h((string) $p['space_code']) ?>"
>
    <i class="bi bi-eye me-1"></i>
    Ver
</button>
                                    <?php else: ?>

                                        <span class="text-secondary small">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>
<!-- =========================================================
     MODAL VISOR DE COMPROBANTE
========================================================= -->

<div
    class="modal fade"
    id="voucherModal"
    tabindex="-1"
    aria-labelledby="voucherModalLabel"
    aria-hidden="true"
>

    <div
        class="modal-dialog
               modal-dialog-centered
               modal-dialog-scrollable
               voucher-modal-dialog"
    >

        <div class="modal-content voucher-modal-content">

            <!-- HEADER -->

            <div class="voucher-modal-header">

                <div>

                    <span class="voucher-modal-eyebrow">
                        Comprobante de pago
                    </span>

                    <h2
                        class="voucher-modal-title"
                        id="voucherModalLabel"
                    >
                        Reserva
                        <span id="voucherReservationId">
                            —
                        </span>
                    </h2>

                    <small id="voucherSpaceCode">
                        —
                    </small>

                </div>

                <button
                    type="button"
                    class="voucher-modal-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                >
                    <i class="bi bi-x-lg"></i>
                </button>

            </div>

            <!-- BODY -->

            <div class="voucher-modal-body">

                <!-- Imagen -->

                <div
                    id="voucherImageContainer"
                    class="voucher-viewer-container d-none"
                >

                    <img
                        id="voucherImage"
                        src=""
                        alt="Comprobante de pago"
                        class="voucher-preview-image"
                    >

                </div>

                <!-- PDF -->

                <div
                    id="voucherPdfContainer"
                    class="voucher-viewer-container d-none"
                >

                    <iframe
                        id="voucherPdf"
                        src=""
                        title="Comprobante PDF"
                        class="voucher-preview-pdf"
                    ></iframe>

                </div>

                <!-- Error -->

                <div
                    id="voucherUnsupported"
                    class="voucher-unsupported d-none"
                >

                    <i class="bi bi-file-earmark-x"></i>

                    <p class="mb-0">
                        No se pudo visualizar este comprobante.
                    </p>

                </div>

            </div>

            <!-- FOOTER -->

            <div class="voucher-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>
<?php

render_page_end();

?>