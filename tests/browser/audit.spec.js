const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('auditoría: filtros, detalle, teclado y temas', async ({ page }, testInfo) => {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('admin@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
  await page.goto('/devioz-parking-php/frontend/admin/auditoria.php');
  await page.getByLabel('Módulo', { exact: true }).fill('Autenticacion');
  await page.getByLabel('Acción', { exact: true }).fill('Login');
  await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
  await expect(page.locator('tbody tr').first()).toContainText('Login');
  const summary = page.locator('summary').first();
  await summary.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('details').first()).toHaveAttribute('open', '');
  await expect(page.locator('details').first()).toContainText('Autenticacion');
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => { localStorage.setItem('devioz_theme', value); }, theme);
    await page.reload();
    const results = await new AxeBuilder({ page }).include('.panel-card')
      .withRules(['label','button-name','color-contrast']).analyze();
    expect(results.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/audit-${testInfo.project.name}-${theme}.png`, fullPage: true });
  }
  await page.getByRole('link', { name: 'Limpiar filtros' }).click();
  await expect(page.getByLabel('Módulo', { exact: true })).toHaveValue('');
  const response = await page.goto('/devioz-parking-php/frontend/admin/auditoria.php?from=2026-02-30');
  expect(response.status()).toBe(400);
  await expect(page.getByRole('alert')).toContainText('Fecha de auditoría inválida');
});
