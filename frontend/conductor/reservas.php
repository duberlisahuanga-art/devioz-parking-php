<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_driver();

/*
|--------------------------------------------------------------------------
| Procesamiento POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formAction = (string) ($_POST['form_action'] ?? '');
    if ($formAction === 'renew') {
        run_post_action(function (): void {
            ReservationController::renew((int) ($_POST['reservation_id'] ?? 0));
        }, 'Nueva mensualidad creada. Envía su pago para habilitar el ingreso.', '/frontend/conductor/reservas.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Pago mediante Yape
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'submit_payment') {

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);

        run_post_action(
            function () use ($reservationId): void {

                PaymentController::submit(
                    $reservationId,
                    $_POST,
                    $_FILES
                );
            },
            'Voucher enviado. El pago quedó pendiente de validación.',
            '/frontend/conductor/reservas.php'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Solicitud de cancelación
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'cancel_reservation') {

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);

        $reason = trim(
            (string) ($_POST['reason'] ?? '')
        );

        run_post_action(
            function () use ($reservationId, $reason): void {

                ReservationController::requestCancellation(
                    $reservationId,
                    $reason
                );
            },
            'Solicitud de cancelación enviada.',
            '/frontend/conductor/reservas.php'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Reservas del conductor
|--------------------------------------------------------------------------
*/

$reservations = ReservationController::index();

$pageHasNext = count($reservations) > 50;
$reservations = array_slice($reservations,0,50);
render_page_start(
    'Mis reservas',
    'reservas'
);
?>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Estado de las reservas">
    <a class="btn btn-outline-primary" href="?view=current" <?= ($_GET['view'] ?? '') !== 'history' ? 'aria-current="page"' : '' ?>>En curso</a>
    <a class="btn btn-outline-primary" href="?view=history" <?= ($_GET['view'] ?? '') === 'history' ? 'aria-current="page"' : '' ?>>Historial y renovación</a>
</nav>
<p class="small text-secondary">Las mensualidades finalizadas se conservan en el historial. Desde allí puedes renovar la misma plaza si está disponible; cada renovación requiere un nuevo pago aprobado.</p>

<!-- =========================================================
     ENCABEZADO
========================================================= -->

<div class="reservation-list-header">

    <div>

        <h2 class="h5 mb-1">
            Mis reservas
        </h2>

        <p class="text-secondary mb-0">
            Consulta la vigencia de tu plaza, pagos y solicitudes.
        </p>

    </div>

    <a
        class="btn btn-primary"
        href="<?= h(
            app_url(
                '/frontend/conductor/reservar.php'
            )
        ) ?>"
    >

        <i class="bi bi-plus-circle me-1"></i>

        Reservar plaza

    </a>

</div>

<!-- =========================================================
     SIN RESERVAS
========================================================= -->

<?php if (!$reservations): ?>

    <div class="panel-card text-center py-5 mt-4">

        <i
            class="bi bi-calendar-x display-5 text-secondary">
        </i>

        <h3 class="h5 mt-3">
            Aún no tienes reservas
        </h3>

        <p class="text-secondary">
            Cuando reserves una plaza aparecerá aquí.
        </p>

        <a
            class="btn btn-primary mt-2"
            href="<?= h(
                app_url(
                    '/frontend/conductor/reservar.php'
                )
            ) ?>"
        >

            <i class="bi bi-plus-circle me-1"></i>

            Reservar ahora

        </a>

    </div>

<?php else: ?>

    <!-- =====================================================
         TABLA DE RESERVAS
    ====================================================== -->

    <div class="reservation-list-table-wrap mt-4">

        <div class="table-responsive">

            <table
                class="table reservation-table align-middle mb-0"
            >

                <thead>

                    <tr>

                        <th>
                            Plaza / vehículo
                        </th>

                        <th>
                            Vigencia
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Registrada
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Pago
                        </th>

                        <th>
                            Monto
                        </th>

                        <th>
                            Acciones
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reservations as $r): ?>

                    <?php

                    $reservationId =
                        (int) $r['id'];

                    $reservationStatus =
                        (string) $r['status'];

                    $paymentStatus =
                        (string) $r['payment_status'];

                    $cancellationStatus =
                        (string) (
                            $r['cancellation_status']
                            ?? 'none'
                        );

                    $statusText =
                        status_label(
                            $reservationStatus
                        );
                    if ($reservationStatus === 'expired') $statusText = 'Mensualidad finalizada';
                    if ($reservationStatus === 'active' && $r['ends_on'] < ReservationController::today()) {
                        $statusText = 'Mensualidad vencida · Pendiente de salida';
                    }

                    $cancellationText = '';

                    if ($cancellationStatus === 'pending') {

                        $cancellationText =
                            'Cancelación pendiente';

                    } elseif (
                        $cancellationStatus === 'approved'
                    ) {

                        $cancellationText =
                            'Cancelación aprobada';

                    } elseif (
                        $cancellationStatus === 'rejected'
                    ) {

                        $cancellationText =
                            'Solicitud rechazada';
                    }

                    $canPay =
                        $paymentStatus === 'unpaid'
                        && in_array(
                            $reservationStatus,
                            [
                                'confirmed',
                                'active',
                            ],
                            true
                        );

                    ?>

                    <tr>

                        <!-- PLAZA / VEHÍCULO -->

                        <td>

                            <div class="reservation-cell-main">

                                <strong>
                                    <?= h(
                                        $r['space_code']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= h(
                                        $r['vehicle_plate']
                                    ) ?>
                                </span>

                                <small>
                                    Estacionamiento Devioz
                                </small>

                            </div>

                        </td>

                        <!-- VIGENCIA -->

                        <td>

                            <div class="reservation-date-cell">

                                <strong>

                                    <?= h(
                                        format_date(
                                            $r['starts_on']
                                        )
                                    ) ?>

                                </strong>

                                <span>

                                    hasta

                                    <?= h(
                                        format_date(
                                            $r['ends_on']
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </td>

                        <!-- PLAN -->

                        <td>

                            <span class="badge text-bg-primary">
                                Mensual
                            </span>

                        </td>

                        <!-- REGISTRADA -->

                        <td>

                            <?= h(
                                format_datetime(
                                    $r['created_at']
                                )
                            ) ?>

                        </td>

                        <!-- ESTADO -->

                        <td>

                            <div
                                class="d-flex flex-column gap-1 align-items-start"
                            >

                                <span
                                    class="badge <?= h(
                                        badge_class(
                                            $reservationStatus
                                        )
                                    ) ?>"
                                >

                                    <?= h($statusText) ?>

                                </span>

                                <?php if ($cancellationText !== ''): ?>

                                    <span
                                        class="badge
                                        <?= $cancellationStatus === 'pending'
                                            ? 'text-bg-warning'
                                            : (
                                                $cancellationStatus === 'approved'
                                                    ? 'text-bg-secondary'
                                                    : 'text-bg-danger'
                                            ) ?>"
                                    >

                                        <?= h(
                                            $cancellationText
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>

                        <!-- PAGO -->

                        <td>

                            <span
                                class="badge <?= h(
                                    badge_class(
                                        $paymentStatus
                                    )
                                ) ?>"
                            >

                                <?= h(
                                    status_label(
                                        $paymentStatus
                                    )
                                ) ?>

                            </span>

                        </td>

                        <!-- MONTO -->

                        <td>

                            <strong>

                                <?= h(
                                    money(
                                        $r['amount']
                                    )
                                ) ?>

                            </strong>

                        </td>

                        <!-- ACCIONES -->

                        <td>

                            <div class="reservation-actions">

                                <?php if ($canPay): ?>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary js-open-payment-modal"
                                        data-bs-toggle="modal"
                                        data-bs-target="#paymentModal"

                                        data-reservation-id="<?= h(
                                            (string) $reservationId
                                        ) ?>"

                                        data-space-code="<?= h(
                                            (string) $r['space_code']
                                        ) ?>"

                                        data-vehicle-plate="<?= h(
                                            (string) $r['vehicle_plate']
                                        ) ?>"

                                        data-amount="<?= h(
                                            number_format(
                                                (float) $r['amount'],
                                                2,
                                                '.',
                                                ''
                                            )
                                        ) ?>"
                                    >

                                        <i class="bi bi-wallet2 me-1"></i>

                                        Pagar

                                    </button>

                                <?php endif; ?>

                                <?php if (
                                    $reservationStatus === 'confirmed'
                                    && $cancellationStatus !== 'pending'
                                    && $cancellationStatus !== 'approved'
                                ): ?>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#cancel-<?= h(
                                            (string) $reservationId
                                        ) ?>"
                                    >

                                        <i
                                            class="bi bi-exclamation-triangle me-1">
                                        </i>

                                        Solicitar cancelación

                                    </button>

                                <?php endif; ?>

                                <?php if (
                                    $cancellationStatus === 'pending'
                                ): ?>

                                    <span
                                        class="reservation-action-message warning"
                                    >

                                        <i
                                            class="bi bi-clock-history">
                                        </i>

                                        Esperando revisión del administrador

                                    </span>

                                <?php elseif ($reservationStatus === 'expired'): ?>
                                    <?php if (!empty($r['renewal_id'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="?view=<?= in_array($r['renewal_status'], ['expired', 'cancelled'], true) ? 'history' : 'current' ?>">Ver renovación #<?= h($r['renewal_id']) ?></a>
                                    <?php else: ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_action" value="renew">
                                        <input type="hidden" name="reservation_id" value="<?= h($reservationId) ?>">
                                        <button class="btn btn-sm btn-primary" type="submit">Renovar mensualidad</button>
                                    </form>
                                    <span class="small text-secondary">Nueva mensualidad desde hoy, con la misma plaza y vehículo si está disponible.</span>
                                    <?php endif; ?>
                                <?php elseif (
                                    $cancellationStatus === 'approved'
                                    || $reservationStatus === 'cancelled'
                                    || $reservationStatus === 'expired'
                                ): ?>

                                    <span
                                        class="text-muted small d-block"
                                    >

                                        Sin acciones disponibles

                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                    <!-- =================================================
                         FORMULARIO DE CANCELACIÓN
                    ================================================== -->

                    <?php if (
                        $reservationStatus === 'confirmed'
                        && $cancellationStatus !== 'pending'
                        && $cancellationStatus !== 'approved'
                    ): ?>

                        <tr class="collapse-row">

                            <td
                                colspan="8"
                                class="p-0"
                            >

                                <div
                                    class="collapse reservation-cancel-collapse"
                                    id="cancel-<?= h(
                                        (string) $reservationId
                                    ) ?>"
                                >

                                    <form
                                        method="post"
                                        class="reservation-cancel-form"
                                        data-prevent-double-submit
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="form_action"
                                            value="cancel_reservation"
                                        >

                                        <input
                                            type="hidden"
                                            name="reservation_id"
                                            value="<?= h(
                                                (string) $reservationId
                                            ) ?>"
                                        >

                                        <label
                                            class="form-label"
                                        >

                                            Motivo de cancelación

                                        </label>

                                        <textarea
                                            class="form-control mb-2"
                                            name="reason"
                                            minlength="5"
                                            required
                                            placeholder="Indica por qué deseas cancelar esta mensualidad"
                                        ></textarea>

                                        <button
                                            class="btn btn-danger btn-sm"
                                            type="submit"
                                        >

                                            <i class="bi bi-send me-1"></i>

                                            Enviar solicitud

                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     MODAL DE PAGO
========================================================= -->

<div
    class="modal fade"
    id="paymentModal"
    tabindex="-1"
    aria-labelledby="paymentModalLabel"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered modal-dialog-scrollable payment-modal-dialog"
    >

        <div class="modal-content payment-modal-content">

            <!-- HEADER -->

            <div class="payment-modal-header">

                <div>

                    <span class="payment-modal-eyebrow">

                        Pago seguro de demostración

                    </span>

                    <h2
                        class="payment-modal-title"
                        id="paymentModalLabel"
                    >

                        Pagar

                        <span id="paymentModalAmount">
                            S/ 0.00
                        </span>

                    </h2>

                </div>

                <button
                    type="button"
                    class="payment-modal-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                >

                    <i class="bi bi-x-lg"></i>

                </button>

            </div>

            <!-- BODY -->

            <div class="payment-modal-body">

                <!-- DATOS DE RESERVA -->

                <div class="payment-reservation-info">

                    <div>

                        <span>
                            Plaza
                        </span>

                        <strong id="paymentSpaceCode">
                            —
                        </strong>

                    </div>

                    <div>

                        <span>
                            Vehículo
                        </span>

                        <strong id="paymentVehiclePlate">
                            —
                        </strong>

                    </div>

                </div>

                <!-- FORMULARIO DE PAGO -->

                <form
                    id="paymentVoucherForm"
                    method="post"
                    enctype="multipart/form-data"
                    data-prevent-double-submit
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="form_action"
                        value="submit_payment"
                    >

                    <input
                        type="hidden"
                        name="method"
                        value="yape"
                    >

                    <input
                        type="hidden"
                        name="reservation_id"
                        id="paymentReservationId"
                        value=""
                    >

                    <!-- YAPE -->

                    <div class="payment-yape-card">

                        <div class="payment-method-header">

                            <div>

                                <strong>
                                    Yape - método principal
                                </strong>

                                <span>
                                    Pago de mensualidad
                                </span>

                            </div>

                            <span class="payment-method-active">
                                Activo
                            </span>

                        </div>

                        <!-- QR -->

                        <div class="payment-qr-wrap">

                            <img
                                src="<?= h(app_url('/frontend/assets/img/payments/yape_qr_oficial.png')) ?>"
                                alt="QR oficial de Yape para pagos"
                                class="payment-qr-image"
                            >

                        </div>

                        <p class="payment-qr-help">

                            Escanea el QR y realiza el pago por

                            <strong id="paymentQRAmount">
                                S/ 0.00
                            </strong>.

                        </p>

                    </div>

                    <!-- CÓDIGO DE SEGURIDAD -->

                    <div class="payment-field">

                        <label
                            for="paymentOperation"
                            class="form-label"
                        >

                            Código de seguridad

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="paymentOperation"
                            name="provider_tx_id"
                            inputmode="numeric"
                            maxlength="3"
                            pattern="[0-9]{3}"
                            title="El código de seguridad debe contener exactamente 3 dígitos numéricos."
                            placeholder="Ej. 123"
                            autocomplete="off"
                            required
                        >

                    </div>

                    <!-- VOUCHER -->

                    <div class="payment-field">

                        <label
                            for="paymentVoucher"
                            class="form-label"
                        >

                            Voucher de pago

                        </label>

                        <input
                            type="file"
                            class="form-control"
                            id="paymentVoucher"
                            name="voucher"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            required
                        >

                        <small class="payment-field-help">

                            JPG, PNG, WEBP o PDF · máximo 5 MB.

                        </small>

                    </div>

                    <!-- MÉTODOS -->

                    <div class="payment-methods">

                        <div class="payment-method-option active">

                            <div>

                                <i class="bi bi-phone"></i>

                                <span>
                                    Yape
                                </span>

                            </div>

                            <strong>
                                Seleccionado
                            </strong>

                        </div>






                    </div>

                    <!-- NOTA -->

                    <div class="payment-modal-note">

                        <i class="bi bi-info-circle"></i>

                        <span>

                            El pago quedará pendiente hasta que
                            el administrador verifique el voucher.

                        </span>

                    </div>

                </form>

            </div>

            <!-- FOOTER -->

            <div class="payment-modal-footer">

                <button
                    type="button"
                    class="btn payment-cancel-btn"
                    data-bs-dismiss="modal"
                >

                    Cancelar

                </button>

                <button
                    type="submit"
                    form="paymentVoucherForm"
                    class="btn payment-submit-btn"
                    id="paymentSubmitButton"
                >

                    <i class="bi bi-send me-1"></i>

                    Enviar voucher

                </button>

            </div>

        </div>

    </div>

</div>

<?php

render_pagination($pageHasNext); render_page_end();

?>
