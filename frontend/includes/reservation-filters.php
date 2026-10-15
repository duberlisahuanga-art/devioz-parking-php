<?php

declare(strict_types=1);

if (!isset($filters, $data) || !function_exists('h')) {
    http_response_code(404);
    exit;
}
$advancedOpen = $filters['errors'] !== [];
foreach (['space_id', 'vehicle_type', 'validity', 'date_from', 'date_to', 'cancellation'] as $key) {
    $advancedOpen = $advancedOpen || $filters[$key] !== '';
}
$statusOptions = $filters['view'] === 'history'
    ? ['expired' => 'Finalizada', 'cancelled' => 'Cancelada']
    : ['confirmed' => 'Vehículo fuera', 'active' => 'Vehículo dentro'];
?>
<form method="get" class="panel-card mb-3" aria-label="Filtros de reservas" data-reservation-filters>
    <input type="hidden" name="view" value="<?= h($filters['view']) ?>">
    <?php if ($filters['errors']): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($filters['errors'] as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <label class="form-label" for="reservation-search">Buscar</label>
            <input type="search" class="form-control" id="reservation-search" name="search" maxlength="100" value="<?= h($filters['search']) ?>" placeholder="Conductor, correo, placa o #reserva">
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="reservation-status">Estado</label>
            <select class="form-select" id="reservation-status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="reservation-payment">Pago</label>
            <select class="form-select" id="reservation-payment" name="payment">
                <option value="">Todos</option>
                <?php foreach (['paid' => 'Pagado', 'pending' => 'Pendiente de aprobación', 'unpaid' => 'Sin pagar'] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $filters['payment'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <details class="mt-3" <?= $advancedOpen ? 'open' : '' ?> data-reservation-advanced>
        <summary class="fw-semibold">Más filtros</summary>
        <div class="row g-3 mt-1">
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label" for="reservation-space">Plaza</label>
                <select class="form-select" id="reservation-space" name="space_id">
                    <option value="">Todas</option>
                    <?php foreach ($data['spaces'] as $space): ?>
                        <option value="<?= h($space['id']) ?>" <?= (string) $filters['space_id'] === (string) $space['id'] ? 'selected' : '' ?>><?= h($space['code'] . ' · ' . $space['parking_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label" for="reservation-type">Tipo de vehículo</label>
                <select class="form-select" id="reservation-type" name="vehicle_type">
                    <option value="">Todos</option>
                    <option value="car" <?= $filters['vehicle_type'] === 'car' ? 'selected' : '' ?>>Auto</option>
                    <option value="moto" <?= $filters['vehicle_type'] === 'moto' ? 'selected' : '' ?>>Moto</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label" for="reservation-validity">Vigencia</label>
                <select class="form-select" id="reservation-validity" name="validity" aria-describedby="reservation-date-help">
                    <option value="">Todas</option>
                    <?php foreach (['future' => 'Por comenzar', 'current' => 'Vigentes', 'expired' => 'Vencidas'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $filters['validity'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label" for="reservation-cancellation">Cancelación</label>
                <select class="form-select" id="reservation-cancellation" name="cancellation">
                    <option value="">Todas</option>
                    <?php foreach (['pending' => 'Pendientes de revisión', 'approved' => 'Aprobadas', 'rejected' => 'Rechazadas', 'none' => 'Sin solicitud'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $filters['cancellation'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="reservation-date-from">Fecha de inicio desde</label>
                <input type="date" class="form-control" id="reservation-date-from" name="date_from" value="<?= h($filters['date_from']) ?>" aria-describedby="reservation-date-help">
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="reservation-date-to">Fecha de inicio hasta</label>
                <input type="date" class="form-control" id="reservation-date-to" name="date_to" value="<?= h($filters['date_to']) ?>" aria-describedby="reservation-date-help">
            </div>
            <p class="small text-secondary mb-0" id="reservation-date-help">Vigencia según las fechas del periodo contratado. El rango busca por fecha de inicio e incluye ambos días.</p>
        </div>
    </details>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="?view=<?= h($filters['view']) ?>">Limpiar</a>
    </div>
</form>
