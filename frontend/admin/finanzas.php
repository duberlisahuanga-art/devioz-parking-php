<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_admin();
$error = null;
try {
    $filters = FinanceController::filters($_GET);
} catch (InvalidArgumentException $e) {
    $error = public_error_message($e);
    $filters = FinanceController::filters([]);
}
$query = array_intersect_key($filters, array_flip(['month', 'type', 'category', 'search']));
$return = '/frontend/admin/finanzas.php?' . http_build_query($query);
$formData = [];
$reopen = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($error !== null) throw new InvalidArgumentException($error);
        verify_csrf();
        $action = $_POST['action'] ?? '';
        if ($action === 'create_expense') {
            FinanceController::createExpense($_POST, $_FILES);
        } elseif ($action === 'update_expense') {
            $id = filter_var($_POST['expense_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $id === null) throw new InvalidArgumentException('Identificador inválido.');
            FinanceController::updateExpense($id, $_POST, $_FILES);
        } else {
            throw new InvalidArgumentException('Acción inválida.');
        }
        flash('success', $action === 'create_expense' ? 'Egreso registrado.' : 'Egreso actualizado.');
        redirect_to($return);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'No se pudo guardar el egreso. Revisa la migración y la conexión.' : public_error_message($e);
        foreach (['category', 'concept', 'amount', 'expense_datetime', 'payment_method', 'description', 'other_detail', 'expense_id', 'action'] as $key) {
            $formData[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        }
        $reopen = true;
    }
}
try {
    $overview = FinanceController::overview($query);
} catch (PDOException $e) {
    error_log('Finanzas: ' . public_error_message($e));
    render_page_start('Finanzas', 'finanzas');
    echo '<div class="alert alert-danger">No se pudo cargar Finanzas. Verifica la conexión y ejecuta la migración database/finance_expenses.sql.</div>';
    render_page_end();
    exit;
}
if (($_GET['export'] ?? '') === 'csv' && $error === null) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="finanzas-' . $filters['month'] . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha y hora', 'Tipo', 'Categoría', 'Concepto', 'Descripción', 'Método', 'Usuario', 'Monto (PEN)', 'Referencia', 'Reserva'], ';');
    foreach ($overview['movements'] as $m) {
        $row = [$m['datetime'], $m['type'] === 'income' ? 'Ingreso' : 'Egreso', $m['category'], $m['concept'], $m['description'], $m['payment_method'], $m['user_name'] ?? '',
            number_format(($m['type'] === 'expense' ? -1 : 1) * (float) $m['amount'], 2, '.', ''), ($m['type'] === 'income' ? 'PAY-' : 'EG-') . $m['reference_id'], $m['reservation_id'] ?? ''];
        // Evitar fórmulas al abrir texto aportado por usuarios en Excel.
        foreach ($row as $i => $cell) {
            if ($i !== 7 && preg_match('/^[\s\x00-\x20]*[=+@-]/u', (string) $cell)) $row[$i] = "'" . $cell;
        }
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}
$movements = $overview['movements'];
foreach ($movements as &$movement) $movement['voucher_url'] = !empty($movement['voucher_path']) ? VoucherService::url($movement['type'] === 'income' ? 'payment' : 'expense', (int) $movement['reference_id']) : null;
unset($movement);
render_page_start('Finanzas', 'finanzas');
?>
<div class="finance-module">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <p class="mb-0 text-secondary">Ingresos, egresos, balance e historial financiero.</p>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-success" href="<?= h(app_url($return . '&export=csv')) ?>"><i class="bi bi-download me-1"></i>Exportar Excel (CSV)</a>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#expenseModal" id="financeNewExpense"><i class="bi bi-plus-lg me-1"></i>Nuevo egreso</button>
        </div>
    </div>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
    <div class="row g-3 mb-4">
        <?php foreach (['previous' => ['Saldo anterior', 'bi-clock-history'], 'income' => ['Ingresos del mes', 'bi-arrow-down-circle'], 'expenses' => ['Egresos del mes', 'bi-arrow-up-circle'], 'available' => ['Saldo disponible', 'bi-wallet2']] as $key => [$label, $icon]): ?>
        <div class="col-12 col-sm-6 col-xl-3"><div class="metric-card finance-metric"><div class="metric-icon"><i class="bi <?= h($icon) ?>"></i></div><div><span><?= h($label) ?></span><strong class="<?= $key === 'expenses' || $overview[$key] < 0 ? 'text-danger' : ($key === 'income' ? 'text-success' : '') ?>"><?= h(money($overview[$key])) ?></strong></div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-9">
            <div class="panel-card">
                <div class="panel-heading"><div><h2>Movimientos financieros</h2><p><?= h($filters['month']) ?> · <?= count($movements) ?> movimientos · <?= h($overview['income_count']) ?> pagos aprobados en el mes</p></div></div>
                <form method="get" class="row g-2 p-3 finance-filters">
                    <div class="col-sm-6 col-lg-3"><label for="financeMonth" class="form-label">Mes</label><input id="financeMonth" name="month" type="month" class="form-control" required value="<?= h($filters['month']) ?>"></div>
                    <div class="col-sm-6 col-lg-2"><label for="financeType" class="form-label">Tipo</label><select id="financeType" name="type" class="form-select"><?php foreach (['' => 'Todos', 'income' => 'Ingresos', 'expense' => 'Egresos'] as $value => $label): ?><option value="<?= h($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                    <div class="col-sm-6 col-lg-3"><label for="financeCategory" class="form-label">Categoría de egreso</label><select id="financeCategory" name="category" class="form-select"><option value="">Todas</option><?php foreach (Expense::CATEGORIES as $category): ?><option <?= $filters['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></div>
                    <div class="col-sm-6 col-lg-4"><label for="financeSearch" class="form-label">Buscar</label><input id="financeSearch" name="search" maxlength="150" class="form-control" value="<?= h($filters['search']) ?>" placeholder="Conductor, placa, reserva, concepto…"></div>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Aplicar filtros</button><a class="btn btn-outline-secondary" href="<?= h(app_url('/frontend/admin/finanzas.php?month=' . $filters['month'])) ?>">Limpiar</a></div>
                </form>
                <div class="table-responsive"><table class="table align-middle mb-0 finance-table">
                    <thead><tr><th>Fecha y hora</th><th>Tipo</th><th>Categoría / concepto</th><th>Descripción</th><th>Método</th><th>Comprobante</th><th>Usuario</th><th>Monto</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if (!$movements): ?><tr><td colspan="9" class="text-center text-secondary py-5">No hay movimientos para estos filtros.</td></tr><?php endif; ?>
                    <?php foreach ($movements as $index => $m): $income = $m['type'] === 'income'; ?>
                        <tr>
                            <td class="text-nowrap"><?= h(format_datetime($m['datetime'])) ?></td>
                            <td><span class="badge <?= $income ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $income ? 'Ingreso' : 'Egreso' ?></span></td>
                            <td><strong><?= h($m['category']) ?></strong><div><?= h($m['concept']) ?></div></td>
                            <td class="finance-description"><?= h($m['description']) ?></td><td><?= h($m['payment_method']) ?></td>
                            <td><?php if ($m['voucher_url']): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-finance-voucher="<?= $index ?>" data-bs-toggle="modal" data-bs-target="#financeVoucherModal">Ver comprobante</button><?php else: ?>—<?php endif; ?></td>
                            <td><?= h($m['user_name'] ?? '—') ?></td>
                            <td class="text-nowrap fw-semibold <?= $income ? 'text-success' : 'text-danger' ?>"><?= $income ? '+' : '-' ?> <?= h(money($m['amount'])) ?></td>
                            <td><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" data-finance-view="<?= $index ?>" data-bs-toggle="modal" data-bs-target="#financeDetailModal">Ver</button><?php if (!$income): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-finance-edit="<?= $index ?>" data-bs-toggle="modal" data-bs-target="#expenseModal">Editar</button><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
        <aside class="col-12 col-xl-3">
            <div class="panel-card p-3 mb-4"><h2 class="h5">Egresos por categoría</h2><p class="small text-secondary">Totales del mes seleccionado</p>
            <?php foreach ($overview['categories'] as $category => $total): ?><div class="d-flex justify-content-between gap-2 py-2 border-bottom"><span><?= h($category) ?></span><strong><?= h(money($total)) ?></strong></div><?php endforeach; ?></div>
            <div class="panel-card p-3"><h2 class="h5">Balance</h2>
                <?php foreach (['previous' => 'Saldo anterior', 'income' => '+ Ingresos', 'expenses' => '- Egresos', 'available' => 'Saldo disponible'] as $key => $label): ?><div class="d-flex justify-content-between gap-2 py-2 <?= $key === 'available' ? 'border-top fw-bold' : '' ?>"><span><?= h($label) ?></span><span><?= h(money($overview[$key])) ?></span></div><?php endforeach; ?>
                <p class="small text-secondary mt-3 mb-0">Los filtros de la tabla no cambian los totales del mes.</p>
            </div>
        </aside>
    </div>
</div>
<div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="post" enctype="multipart/form-data" action="<?= h(app_url($return)) ?>" id="financeExpenseForm">
        <div class="modal-header"><h2 class="modal-title fs-5" id="expenseModalTitle">Nuevo egreso</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="create_expense"><input type="hidden" name="expense_id" value="">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="expenseCategory">Categoría *</label><select id="expenseCategory" name="category" class="form-select" required><option value="">Seleccionar</option><?php foreach (Expense::CATEGORIES as $category): ?><option><?= h($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="expenseConcept">Concepto *</label><input id="expenseConcept" name="concept" class="form-control" maxlength="255" required></div>
                <div class="col-md-6"><label class="form-label" for="expenseAmount">Monto (S/) *</label><input id="expenseAmount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label" for="expenseDatetime">Fecha y hora *</label><input id="expenseDatetime" name="expense_datetime" type="datetime-local" class="form-control" required value="<?= h((new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d\TH:i')) ?>"></div>
                <div class="col-md-6"><label class="form-label" for="expenseMethod">Método de pago *</label><select id="expenseMethod" name="payment_method" class="form-select" required><option value="">Seleccionar</option><?php foreach (Expense::METHODS as $method): ?><option><?= h($method) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="expenseUser">Usuario que registra</label><input id="expenseUser" class="form-control" readonly value="<?= h($user['name']) ?>"><div id="expenseOriginalUser" class="form-text"></div></div>
                <div class="col-12"><label class="form-label" for="expenseDescription">Descripción / observación *</label><textarea id="expenseDescription" name="description" class="form-control" rows="3" maxlength="10000" required></textarea></div>
                <div class="col-12" id="expenseOtherGroup" hidden><label class="form-label" for="expenseOther">Detalle Otros *</label><textarea id="expenseOther" name="other_detail" class="form-control" rows="2" maxlength="1000"></textarea></div>
                <div class="col-12"><label class="form-label" for="expenseVoucher">Comprobante opcional</label><input id="expenseVoucher" name="voucher" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control"><div class="form-text">JPG, PNG, WEBP o PDF. Máximo 5 MB. Al editar se conserva el comprobante si no seleccionas otro.</div><div id="expenseCurrentVoucher" class="form-text"></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="expenseSave">Guardar egreso</button></div>
    </form>
</div></div></div>
<div class="modal fade" id="financeDetailModal" tabindex="-1" aria-labelledby="financeDetailTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="financeDetailTitle">Ver movimiento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><dl class="row" id="financeDetailFields"></dl><button type="button" class="btn btn-outline-primary" id="financeDetailVoucher" data-bs-toggle="modal" data-bs-target="#financeVoucherModal" hidden>Ver comprobante</button></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
<div class="modal fade" id="financeVoucherModal" tabindex="-1" aria-labelledby="financeVoucherTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="financeVoucherTitle">Comprobante</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" id="financeVoucherContent"></div><div class="modal-footer"><a id="financeVoucherOpen" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer">Abrir archivo</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
<script type="application/json" id="financeData"><?= json_encode(['movements' => $movements, 'form' => $formData, 'reopen' => $reopen], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= h(app_url('/frontend/assets/js/finance.js')) ?>" defer></script>
<?php render_page_end(); ?>