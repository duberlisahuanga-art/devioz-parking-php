<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_driver();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    run_post_action(function (): void { ReservationController::store($_POST); }, 'Reserva creada correctamente.', '/frontend/conductor/reservas.php');
}

$data = ReservationController::createData();
$available = array_values(array_filter($data['spaces'], fn(array $s): bool => $s['status'] === 'available'));
$today = date('Y-m-d');
render_page_start('Reservar plaza', 'reservar');
?>
<div class="reservation-layout">
    <div class="reservation-panel reservation-panel--primary">
        <div class="panel-card h-100">
            <div class="panel-heading">
                <div>
                    <h2>1. Elige una plaza</h2>
                    <p>Consulta las plazas disponibles según su estado.</p>
                </div>
                <span class="badge text-bg-success"><?= count($available) ?> libres</span>
            </div>

            <div class="space-filter-bar" aria-label="Filtros de plazas">
                <button type="button" class="space-filter is-active" data-filter="all">Todas</button>
                <button type="button" class="space-filter" data-filter="available">Libres</button>
                <button type="button" class="space-filter" data-filter="reserved">Reservadas</button>
                <button type="button" class="space-filter" data-filter="occupied">Ocupadas</button>
            </div>

            <?php if (!$data['vehicles']): ?>
                <div class="alert alert-warning mt-3">Primero registra un vehículo en <a href="<?= h(app_url('/frontend/conductor/vehiculos.php')) ?>">Mis vehículos</a>.</div>
            <?php elseif (!$available): ?>
                <div class="alert alert-warning mt-3">En este momento no hay plazas disponibles.</div>
            <?php else: ?>
                <div class="space-grid js-space-grid" aria-live="polite">
                    <?php foreach ($data['spaces'] as $space): ?>
                        <?php
                        $spaceStatus = (string) ($space['status'] ?? 'available');
                        $statusLabel = status_label($spaceStatus);
                        $isSelectable = $spaceStatus === 'available';
                        ?>
                        <button
                            type="button"
                            class="space-card status-<?= h($spaceStatus) ?> <?= $isSelectable ? 'space-card--selectable' : 'space-card--locked' ?>"
                            data-space-id="<?= h((string) $space['id']) ?>"
                            data-space-code="<?= h($space['code']) ?>"
                            data-space-status="<?= h($spaceStatus) ?>"
                            data-space-floor="<?= h((string) ($space['floor'] ?? 1)) ?>"
                            data-space-type="<?= h((string) ($space['type'] ?? 'car')) ?>"
                            data-filter-match="<?= h($spaceStatus) ?>"
                            <?= $isSelectable ? '' : 'disabled' ?>
                            aria-label="Plaza <?= h($space['code']) ?>, estado <?= h($statusLabel) ?>"
                        >
                            <span class="space-card__code"><?= h($space['code']) ?></span>
                            <span class="space-card__meta">Piso <?= h((string) ($space['floor'] ?? 1)) ?></span>
                            <span class="space-card__meta"><?= h(ucfirst((string) ($space['type'] ?? 'car'))) ?></span>
                            <span class="space-card__status"><?= h($statusLabel) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="reservation-panel reservation-panel--secondary">
        <div class="panel-card h-100">
            <div class="panel-heading">
                <div>
                    <h2>2. Datos de la reserva</h2>
                    <p>Completa la información para confirmar tu mensualidad.</p>
                </div>
            </div>

            <?php if (!$data['vehicles'] || !$available): ?>
                <div class="alert alert-secondary mb-0">No puedes continuar hasta registrar un vehículo y disponer de una plaza disponible.</div>
            <?php else: ?>
                <form method="post" class="reservation-form" data-prevent-double-submit id="reservationForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="space_id" id="spaceId" value="">

                    <div class="reservation-field">
                        <label class="form-label" for="vehicleSelect">Vehículo</label>
                        <select class="form-select" id="vehicleSelect" name="vehicle_id" required>
                            <option value="">Selecciona un vehículo</option>
                            <?php foreach ($data['vehicles'] as $vehicle): ?>
                                <?php $vehicleLabel = trim((string) (($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''))); ?>
                                <option value="<?= h((string) $vehicle['id']) ?>"><?= h($vehicle['plate']) ?><?= $vehicleLabel !== '' ? ' · ' . h($vehicleLabel) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="reservation-field">
                        <label class="form-label" for="startsOn">Fecha de inicio</label>
                        <input
                            class="form-control"
                            id="startsOn"
                            type="date"
                            name="starts_on"
                            min="<?= h($today) ?>"
                            value="<?= h($today) ?>"
                            required
                        >
                    </div>

                    <div class="reservation-card reservation-card--plain">
                        <div class="reservation-card__header">
                            <span>Plan</span>
                            <span class="reservation-plan">Mensual</span>
                        </div>
                        <div class="reservation-card__price"><?= h(money($data['monthly_price'])) ?></div>
                    </div>

                    <div class="reservation-card reservation-card--plain">
                        <div class="reservation-card__header">
                            <span>Vigencia de la mensualidad</span>
                        </div>
                        <div class="reservation-date-grid">
                            <div>
                                <small>Inicio</small>
                                <strong id="startDateDisplay"><?= h(format_date($today)) ?></strong>
                            </div>
                            <div>
                                <small>Vencimiento</small>
                                <strong id="endDateDisplay">Selecciona una fecha</strong>
                            </div>
                        </div>
                    </div>

                    <div class="reservation-card reservation-card--summary">
                        <div class="reservation-card__header">
                            <span>Resumen</span>
                        </div>
                        <div class="reservation-summary-list">
                            <div>
                                <span>Plaza</span>
                                <strong id="summarySpace">Sin seleccionar</strong>
                            </div>
                            <div>
                                <span>Vehículo</span>
                                <strong id="summaryVehicle">Sin seleccionar</strong>
                            </div>
                            <div>
                                <span>Mensualidad</span>
                                <strong><?= h(money($data['monthly_price'])) ?></strong>
                            </div>
                        </div>
                        <div class="reservation-total-row">
                            <span>Total de la reserva</span>
                            <strong><?= h(money($data['monthly_price'])) ?></strong>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="confirmReservationBtn" disabled data-prevent-double-submit>
                        Confirmar reserva mensual
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_page_end(); ?>
