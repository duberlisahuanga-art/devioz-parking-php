'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('financeData');
    if (!source) return;
    const data = JSON.parse(source.textContent);
    const form = document.getElementById('financeExpenseForm');
    const category = form.elements.category;
    const other = form.elements.other_detail;
    const money = value => 'S/ ' + Number(value).toLocaleString('es-PE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const updateOther = () => {
        const show = category.value === 'Otros';
        document.getElementById('expenseOtherGroup').hidden = !show;
        other.required = show;
        other.disabled = !show;
    };
    const fill = (values, editing) => {
        form.reset();
        form.elements.action.value = editing ? 'update_expense' : 'create_expense';
        form.elements.expense_id.value = editing ? (values.id || values.expense_id || '') : '';
        for (const key of ['category', 'concept', 'amount', 'expense_datetime', 'payment_method', 'description', 'other_detail']) {
            if (values[key] !== undefined) form.elements[key].value = values[key] || '';
        }
        if (values.expense_datetime) form.elements.expense_datetime.value = values.expense_datetime.replace(' ', 'T').slice(0, 16);
        document.getElementById('expenseModalTitle').textContent = editing ? 'Editar egreso' : 'Nuevo egreso';
        document.getElementById('expenseSave').textContent = editing ? 'Guardar cambios' : 'Guardar egreso';
        document.getElementById('expenseOriginalUser').textContent = editing && values.created_by_name ? 'Registrado originalmente por: ' + values.created_by_name : '';
        document.getElementById('expenseCurrentVoucher').textContent = values.voucher_url ? 'Este egreso ya tiene un comprobante.' : '';
        form.elements.voucher.setCustomValidity('');
        updateOther();
    };
    category.addEventListener('change', updateOther);
    document.getElementById('financeNewExpense').addEventListener('click', () => fill({}, false));
    document.querySelectorAll('[data-finance-edit]').forEach(button => button.addEventListener('click', () => {
        const movement = data.movements[Number(button.dataset.financeEdit)];
        if (movement && movement.type === 'expense') fill(movement, true);
    }));
    const voucher = movement => {
        if (!movement || !movement.voucher_url) return;
        const target = document.getElementById('financeVoucherContent');
        target.replaceChildren();
        const pdf = /\.pdf$/i.test(movement.voucher_path || "");
        const element = document.createElement(pdf ? 'iframe' : 'img');
        element.src = movement.voucher_url;
        element.className = pdf ? 'finance-voucher-pdf' : 'finance-voucher-image';
        if (pdf) element.title = 'Comprobante PDF';
        else element.alt = 'Comprobante del movimiento';
        target.append(element);
        document.getElementById('financeVoucherOpen').href = movement.voucher_url;
    };
    document.querySelectorAll('[data-finance-voucher]').forEach(button => button.addEventListener('click', () => voucher(data.movements[Number(button.dataset.financeVoucher)])));
    document.querySelectorAll('[data-finance-view]').forEach(button => button.addEventListener('click', () => {
        const m = data.movements[Number(button.dataset.financeView)];
        if (!m) return;
        const fields = document.getElementById('financeDetailFields');
        fields.replaceChildren();
        const rows = m.type === 'income'
            ? [['Fecha de aprobación', m.datetime], ['Tipo', 'Ingreso'], ['Reserva', '#' + m.reservation_id], ['Conductor', m.user_name], ['Plaza', m.space_code], ['Vehículo', [m.vehicle_plate, m.vehicle_brand, m.vehicle_model].filter(Boolean).join(' · ')], ['Monto', money(m.amount)], ['Método', m.payment_method], ['Número de operación', m.provider_tx_id], ['Estado', 'Pagado']]
            : [['Referencia', 'EG-' + m.reference_id], ['Tipo', 'Egreso'], ['Categoría', m.category], ['Concepto', m.concept], ['Monto', money(m.amount)], ['Fecha y hora', m.datetime], ['Método', m.payment_method], ['Usuario que registró', m.user_name], ['Descripción', m.description], ...(m.category === 'Otros' ? [['Detalle Otros', m.other_detail]] : [])];
        for (const [label, value] of rows) {
            const term = document.createElement('dt');
            term.className = 'col-sm-4';
            term.textContent = label;
            const detail = document.createElement('dd');
            detail.className = 'col-sm-8 finance-detail-value';
            detail.textContent = value || '—';
            fields.append(term, detail);
        }
        const viewVoucher = document.getElementById('financeDetailVoucher');
        viewVoucher.hidden = !m.voucher_url;
        viewVoucher.onclick = () => voucher(m);
    }));
    document.getElementById('financeVoucherModal').addEventListener('hidden.bs.modal', () => document.getElementById('financeVoucherContent').replaceChildren());
    form.elements.voucher.addEventListener('change', event => {
        const file = event.target.files[0];
        event.target.setCustomValidity(file && file.size > 5 * 1024 * 1024 ? 'El comprobante no puede superar 5 MB.' : '');
        event.target.reportValidity();
    });
    updateOther();
    if (data.reopen) {
        fill(data.form, data.form.action === 'update_expense');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('expenseModal')).show();
    }
});