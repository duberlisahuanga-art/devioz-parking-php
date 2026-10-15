<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_admin();

/*
|--------------------------------------------------------------------------
| Procesar aprobación / rechazo
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['payment_id'] ?? 0);

    run_post_action(
        function () use ($action, $id): void {

            if ($id < 1) {
                throw new RuntimeException('Pago inválido.');
            }

            if ($action === 'confirm') {

                AdminController::confirmPayment($id);

            } elseif ($action === 'reject') {

                AdminController::rejectPayment($id);

            } else {

                throw new RuntimeException(
                    'Acción no válida.'
                );
            }
        },
        'Pago procesado correctamente.',
        '/frontend/admin/pagos.php'
    );
}


/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$userId = (int) ($_GET['user_id'] ?? 0);

$allowedStatuses = [
    '',
    'pending',
    'paid',
    'rejected',
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}


/*
|--------------------------------------------------------------------------
| Datos
|--------------------------------------------------------------------------
*/

$data = AdminController::payments([
    'search' => $search,
    'status' => $status,
    'user_id' => $userId,
]);

$payments = $data['payments'] ?? [];
$summary = $data['summary'] ?? [];
$drivers = $data['drivers'] ?? [];

$pendingCount = (int) ($summary['pending'] ?? 0);
$paidCount = (int) ($summary['paid'] ?? 0);
$rejectedCount = (int) ($summary['rejected'] ?? 0);
$paidTotal = (float) ($summary['paid_total'] ?? 0);


/*
|--------------------------------------------------------------------------
| Helper para pestañas
|--------------------------------------------------------------------------
*/

function payment_filter_url(
    string $statusValue,
    string $searchValue,
    int $userIdValue
): string {

    $params = [];

    if ($searchValue !== '') {
        $params['search'] = $searchValue;
    }

    if ($userIdValue > 0) {
        $params['user_id'] = $userIdValue;
    }

    if ($statusValue !== '') {
        $params['status'] = $statusValue;
    }

    $query = http_build_query($params);

    return app_url(
        '/frontend/admin/pagos.php'
        . ($query !== '' ? '?' . $query : '')
    );
}


/*
|--------------------------------------------------------------------------
| Página
|--------------------------------------------------------------------------
*/

$pageHasNext = count($payments) > 50;
$payments = array_slice($payments,0,50);
render_page_start(
    'Pagos',
    'pagos'
);

?>

<style>

.payments-kpi-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 20px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.payments-kpi-label {
    color: var(--muted);
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 6px;
}

.payments-kpi-value {
    color: var(--text);
    font-size: 1.75rem;
    line-height: 1;
    font-weight: 800;
}

.payments-kpi-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    background: var(--primary-light);
    color: var(--payments-accent, var(--primary));
}

.payments-filter-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
}

.payments-filter-header {
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
}

.payments-filter-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
}

.payments-filter-header p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: .88rem;
}

.payments-filter-body {
    background: var(--surface-soft);
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
}

.payments-tabs {
    display: flex;
    gap: 6px;
    padding: 14px 20px 0;
    overflow-x: auto;
    border-bottom: 1px solid var(--border);
}

.payments-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 13px;
    border-bottom: 3px solid transparent;
    color: var(--muted);
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.payments-tab:hover {
    color: var(--payments-accent, var(--primary));
}

.payments-tab.active {
    color: var(--payments-accent, var(--primary));
    border-bottom-color: var(--payments-accent, var(--primary));
}

.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: .75rem;
    font-weight: 700;
}

.payment-status-pending {
    color: #8a6300;
    background: #fff7d6;
}

.payment-status-paid {
    color: #087851;
    background: #eafaf2;
}

.payment-status-rejected {
    color: #b42318;
    background: #fdecec;
}

.payment-history-label {
    color: var(--muted);
    font-size: .8rem;
    font-weight: 600;
}

.payments-empty {
    padding: 55px 20px;
    text-align: center;
    color: var(--muted);
}

.payments-empty i {
    display: block;
    font-size: 2rem;
    margin-bottom: 10px;
}

@media (max-width: 767px) {

    .payments-kpi-value {
        font-size: 1.5rem;
    }

    .payments-filter-body {
        padding: 16px;
    }

}

/* Pair page surfaces and foregrounds in both themes. */
.payments-kpi-card,
.payments-filter-card {
    color: var(--text);
}

.payments-filter-header h2,
.payments-filter-body .form-label {
    color: var(--text);
}

.payments-tab:hover {
    background: var(--primary-light);
}

.payments-tab:focus-visible {
    outline: 2px solid var(--payments-accent, var(--primary));
    outline-offset: -3px;
}

html[data-theme=dark] .payments-kpi-card,
html[data-theme=dark] .payments-filter-card {
    --payments-accent: #93c5fd;
}

html[data-theme=dark] .payment-status-pending {
    color: #fcd34d;
    background: #302611;
}

html[data-theme=dark] .payment-status-paid {
    color: #6ee7b7;
    background: #092c24;
}

html[data-theme=dark] .payment-status-rejected {
    color: #fca5a5;
    background: #321820;
}
</style>


<!-- =========================================================
     INDICADORES
========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">

        <div class="payments-kpi-card">

            <div>

                <div class="payments-kpi-label">
                    Pendientes
                </div>

                <div class="payments-kpi-value">
                    <?= h((string) $pendingCount) ?>
                </div>

            </div>

            <div class="payments-kpi-icon">

                <i class="bi bi-hourglass-split"></i>

            </div>

        </div>

    </div>


    <div class="col-12 col-sm-6 col-xl-3">

        <div class="payments-kpi-card">

            <div>

                <div class="payments-kpi-label">
                    Aprobados
                </div>

                <div class="payments-kpi-value">
                    <?= h((string) $paidCount) ?>
                </div>

            </div>

            <div class="payments-kpi-icon">

                <i class="bi bi-check-circle"></i>

            </div>

        </div>

    </div>


    <div class="col-12 col-sm-6 col-xl-3">

        <div class="payments-kpi-card">

            <div>

                <div class="payments-kpi-label">
                    Rechazados
                </div>

                <div class="payments-kpi-value">
                    <?= h((string) $rejectedCount) ?>
                </div>

            </div>

            <div class="payments-kpi-icon">

                <i class="bi bi-x-circle"></i>

            </div>

        </div>

    </div>


    <div class="col-12 col-sm-6 col-xl-3">

        <div class="payments-kpi-card">

            <div>

                <div class="payments-kpi-label">
                    Total aprobado
                </div>

                <div class="payments-kpi-value">

                    <?= h(
                        money(
                            $paidTotal
                        )
                    ) ?>

                </div>

            </div>

            <div class="payments-kpi-icon">

                <i class="bi bi-cash-coin"></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     GESTIÓN DE PAGOS
========================================================= -->

<div class="payments-filter-card">

    <!-- Encabezado -->

    <div class="payments-filter-header">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">

            <div>

                <h2>
                    Gestión de pagos
                </h2>

                <p>
                    Consulta, filtra y valida los pagos registrados por los conductores.
                </p>

            </div>

            <span class="badge text-bg-light border px-3 py-2">

                <?= h((string) count($payments)) ?>

                registros

            </span>

        </div>

    </div>


    <!-- Filtros -->

    <div class="payments-filter-body">

        <form
            method="get"
            class="row g-3 align-items-end"
        >

            <!-- Buscar -->

            <div class="col-12 col-lg-5">

                <label
                    for="paymentSearch"
                    class="form-label fw-semibold small"
                >
                    Buscar
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white">

                        <i class="bi bi-search"></i>

                    </span>

                    <input
                        type="search"
                        class="form-control"
                        id="paymentSearch"
                        name="search"
                        value="<?= h($search) ?>"
                        placeholder="Conductor, correo, reserva, placa u operación"
                    >

                </div>

            </div>


            <!-- Conductor -->

            <div class="col-12 col-md-6 col-lg-3">

                <label
                    for="paymentUser"
                    class="form-label fw-semibold small"
                >
                    Conductor
                </label>

                <select
                    class="form-select"
                    id="paymentUser"
                    name="user_id"
                >

                    <option value="0">
                        Todos los conductores
                    </option>

                    <?php foreach ($drivers as $driver): ?>

                        <?php
                        $driverId = (int) ($driver['id'] ?? 0);
                        ?>

                        <option
                            value="<?= h((string) $driverId) ?>"
                            <?= $userId === $driverId ? 'selected' : '' ?>
                        >

                            <?= h(
                                (string) ($driver['name'] ?? '')
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Estado -->

            <div class="col-12 col-md-6 col-lg-2">

                <label
                    for="paymentStatus"
                    class="form-label fw-semibold small"
                >
                    Estado
                </label>

                <select
                    class="form-select"
                    id="paymentStatus"
                    name="status"
                >

                    <option
                        value=""
                        <?= $status === '' ? 'selected' : '' ?>
                    >
                        Todos
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>
                    >
                        Pendiente
                    </option>

                    <option
                        value="paid"
                        <?= $status === 'paid' ? 'selected' : '' ?>
                    >
                        Aprobado
                    </option>

                    <option
                        value="rejected"
                        <?= $status === 'rejected' ? 'selected' : '' ?>
                    >
                        Rechazado
                    </option>

                </select>

            </div>


            <!-- Botones -->

            <div class="col-12 col-lg-2">

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-grow-1"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Filtrar

                    </button>

                    <a
                        href="<?= h(
                            app_url(
                                '/frontend/admin/pagos.php'
                            )
                        ) ?>"
                        class="btn btn-outline-secondary"
                        title="Limpiar filtros"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- =====================================================
         PESTAÑAS
    ====================================================== -->

    <div class="payments-tabs">

        <a
            href="<?= h(
                payment_filter_url(
                    '',
                    $search,
                    $userId
                )
            ) ?>"
            class="payments-tab <?= $status === '' ? 'active' : '' ?>"
        >

            Todos

            <span class="badge rounded-pill text-bg-light">
                <?= h((string) ($summary['total'] ?? 0)) ?>
            </span>

        </a>


        <a
            href="<?= h(
                payment_filter_url(
                    'pending',
                    $search,
                    $userId
                )
            ) ?>"
            class="payments-tab <?= $status === 'pending' ? 'active' : '' ?>"
        >

            Pendientes

            <span class="badge rounded-pill text-bg-warning">
                <?= h((string) $pendingCount) ?>
            </span>

        </a>


        <a
            href="<?= h(
                payment_filter_url(
                    'paid',
                    $search,
                    $userId
                )
            ) ?>"
            class="payments-tab <?= $status === 'paid' ? 'active' : '' ?>"
        >

            Aprobados

            <span class="badge rounded-pill text-bg-success">
                <?= h((string) $paidCount) ?>
            </span>

        </a>


        <a
            href="<?= h(
                payment_filter_url(
                    'rejected',
                    $search,
                    $userId
                )
            ) ?>"
            class="payments-tab <?= $status === 'rejected' ? 'active' : '' ?>"
        >

            Rechazados

            <span class="badge rounded-pill text-bg-danger">
                <?= h((string) $rejectedCount) ?>
            </span>

        </a>

    </div>


    <!-- =====================================================
         TABLA
    ====================================================== -->

    <div class="table-responsive">

        <table class="table align-middle mb-0">

            <thead>

                <tr>

                    <th>
                        Pago
                    </th>

                    <th>
                        Conductor
                    </th>

                    <th>
                        Reserva / Vehículo
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

                    <th>
                        Acción
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (!$payments): ?>

                <tr>

                    <td
                        colspan="8"
                        class="p-0"
                    >

                        <div class="payments-empty">

                            <i class="bi bi-search"></i>

                            <strong class="d-block mb-1">
                                No se encontraron pagos
                            </strong>

                            <span>
                                No existen registros que coincidan con los filtros seleccionados.
                            </span>

                        </div>

                    </td>

                </tr>

            <?php endif; ?>


            <?php foreach ($payments as $p): ?>

                <?php

                $paymentStatus = (string) ($p['status'] ?? '');

                $statusLabel = match ($paymentStatus) {
                    'paid' => 'Aprobado',
                    'rejected' => 'Rechazado',
                    default => 'Pendiente',
                };

                $statusClass = match ($paymentStatus) {
                    'paid' => 'payment-status-paid',
                    'rejected' => 'payment-status-rejected',
                    default => 'payment-status-pending',
                };

                $statusIcon = match ($paymentStatus) {
                    'paid' => 'bi-check-circle',
                    'rejected' => 'bi-x-circle',
                    default => 'bi-clock',
                };

                ?>


                <tr>

                    <!-- Pago -->

                    <td>

                        <strong>

                            #<?= h(
                                (string) $p['id']
                            ) ?>

                        </strong>

                        <div class="small text-secondary">

                            <?= h(
                                format_datetime(
                                    $p['created_at']
                                )
                            ) ?>

                        </div>

                    </td>


                    <!-- Conductor -->

                    <td>

                        <strong>

                            <?= h(
                                (string) $p['user_name']
                            ) ?>

                        </strong>

                        <div class="small text-secondary">

                            <?= h(
                                (string) $p['user_email']
                            ) ?>

                        </div>

                    </td>


                    <!-- Reserva / vehículo -->

                    <td>

                        <strong>

                            Reserva
                            #<?= h(
                                (string) $p['reservation_id']
                            ) ?>

                        </strong>

                        <div class="small text-secondary">

                            <?= h(
                                (string) $p['space_code']
                            ) ?>

                            ·

                            <?= h(
                                (string) $p['vehicle_plate']
                            ) ?>

                        </div>

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
                            (string) (
                                $p['provider_tx_id']
                                ?: '—'
                            )
                        ) ?>

                    </td>


                    <!-- Estado -->

                    <td>

                        <span class="payment-status <?= h($statusClass) ?>">

                            <i class="bi <?= h($statusIcon) ?>"></i>

                            <?= h($statusLabel) ?>

                        </span>

                    </td>


                    <!-- Comprobante -->

                    <td>

                        <?php if (!empty($p['voucher_path'])): ?>

                            <?php

                            $voucherUrl = VoucherService::url('payment', (int) $p['id']);

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

                                data-voucher-url="<?= h(
                                    $voucherUrl
                                ) ?>"

                                data-voucher-type="<?= h(
                                    $voucherExtension
                                ) ?>"

                                data-reservation-id="<?= h(
                                    (string) $p['reservation_id']
                                ) ?>"

                                data-space-code="<?= h(
                                    (string) $p['space_code']
                                ) ?>"
                            >

                                <i class="bi bi-eye me-1"></i>

                                Ver

                            </button>

                        <?php else: ?>

                            <span class="text-secondary">
                                —
                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- Acción -->

                    <td>

                        <?php if ($paymentStatus === 'pending'): ?>

                            <div class="d-flex gap-2 flex-wrap">


                                <!-- Aprobar -->

                                <form
                                    method="post"
                                    data-prevent-double-submit
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= h(
                                            (string) $p['id']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="confirm"
                                    >

                                    <button
                                        class="btn btn-sm btn-success"
                                        type="submit"
                                    >

                                        <i class="bi bi-check-circle me-1"></i>

                                        Aprobar

                                    </button>

                                </form>


                                <!-- Rechazar -->

                                <form
                                    method="post"
                                    data-prevent-double-submit
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="payment_id"
                                        value="<?= h(
                                            (string) $p['id']
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    >

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        type="submit"
                                    >

                                        <i class="bi bi-x-circle me-1"></i>

                                        Rechazar

                                    </button>

                                </form>

                            </div>

                        <?php else: ?>

                            <span class="payment-history-label">

                                <i class="bi bi-check2-circle me-1"></i>

                                Procesado

                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>


    <div class="px-4 py-3 border-top bg-light">

        <small class="text-secondary">

            <i class="bi bi-info-circle me-1"></i>

            Los pagos aprobados y rechazados permanecen visibles como historial.
            Solo los pagos pendientes permiten aprobar o rechazar.

        </small>

    </div>

</div>


<!-- =========================================================
     MODAL INTERNO DE COMPROBANTE
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

            <!-- Header -->

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


            <!-- Body -->

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


                <!-- No compatible -->

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


            <!-- Footer -->

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

render_pagination($pageHasNext); render_page_end();

?>