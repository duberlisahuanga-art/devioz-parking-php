const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
test('egresos: mensaje de campo, foco, datos conservados y corrección', async ({ page }, testInfo) => {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('admin@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
  await page.goto('/devioz-parking-php/frontend/admin/finanzas.php?month=2026-10');
  await page.getByRole('button', { name: 'Nuevo egreso', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Nuevo egreso', exact: true });
  await expect(dialog).toBeVisible();
  await dialog.getByLabel('Categoría', { exact: false }).selectOption('Mantenimiento');
  const concept = `Egreso de prueba ${testInfo.project.name}`;
  await dialog.getByLabel('Concepto', { exact: false }).fill(concept);
  await dialog.getByLabel('Monto (S/)', { exact: false }).fill('0');
  await dialog.getByLabel('Método de pago', { exact: false }).selectOption('Efectivo');
  await dialog.getByLabel('Descripción / observación', { exact: false }).fill('Conservar este texto');
  // Bypass browser constraints to prove that server validation protects the write.
  await page.locator('#financeExpenseForm').evaluate(form => { form.noValidate = true; });
  await dialog.getByRole('button', { name: 'Guardar egreso', exact: true }).click();
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('alert')).toContainText('Ingresa un monto entre');
  const amount = dialog.getByLabel('Monto (S/)', { exact: false });
  await expect(amount).toHaveAttribute('aria-invalid', 'true');
  await expect(amount).toBeFocused();
  await expect(dialog.getByLabel('Concepto', { exact: false })).toHaveValue(concept);
  await expect(dialog.getByLabel('Descripción / observación', { exact: false })).toHaveValue('Conservar este texto');
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => localStorage.setItem('devioz_theme', value), theme);
    // Reapply the stored theme without reloading the failed POST.
    await page.evaluate(value => { document.documentElement.dataset.theme = value; document.documentElement.dataset.bsTheme = value; }, theme);
    expect((await new AxeBuilder({ page }).include('#expenseModal').withRules(['label','button-name','color-contrast']).analyze()).violations).toEqual([]);
    await page.screenshot({ path: `test-results/expense-validation-${testInfo.project.name}-${theme}.png`, fullPage: true });
  }
  await amount.fill('25.50');
  await dialog.getByRole('button', { name: 'Guardar egreso', exact: true }).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('.finance-table tbody')).toContainText(concept);
  const row = page.locator('.finance-table tbody tr').filter({ hasText: concept });
  await row.getByRole('button', { name: 'Editar', exact: true }).click();
  const editing = page.getByRole('dialog', { name: 'Editar egreso', exact: true });
  await expect(editing.getByRole('alert')).toHaveCount(0);
  await expect(editing.getByLabel('Monto (S/)', { exact: false })).toHaveValue('25.50');
});
