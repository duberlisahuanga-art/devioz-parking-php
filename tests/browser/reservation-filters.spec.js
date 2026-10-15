const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('child_process');
const path = require('path');

test('filtros combinados, historial, páginas, acciones y refresco', async ({ page }, testInfo) => {
  test.setTimeout(110000);
  const base = testInfo.project.name === 'desktop' ? 1000 : 2000;
  const plateSearch = base === 1000 ? 'flt100' : 'flt200';
  const dates = JSON.parse(execFileSync(process.env.PHP_BIN || 'php',
    [path.join(__dirname, '../reservation-filters-browser-fixture.php'), String(base)], { encoding: 'utf8' }));
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('admin@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
  await page.goto('/devioz-parking-php/frontend/admin/reservas.php');
  const filters = page.locator('[data-reservation-filters]');
  const advanced = page.locator('[data-reservation-advanced]');
  await expect(advanced).not.toHaveAttribute('open');
  await filters.getByLabel('Buscar', { exact: true }).fill(plateSearch);
  await filters.getByLabel('Pago', { exact: true }).selectOption('paid');
  await page.getByText('Más filtros', { exact: true }).click();
  await filters.getByLabel('Tipo de vehículo', { exact: true }).selectOption('moto');
  await filters.getByLabel('Plaza', { exact: true }).selectOption(String(base));
  await filters.getByLabel('Fecha de inicio desde', { exact: true }).fill(dates.today);
  await filters.getByLabel('Fecha de inicio hasta', { exact: true }).fill(dates.today);
  await filters.getByRole('button', { name: 'Filtrar', exact: true }).click();
  await expect(page.locator('[data-reservation-count]')).toHaveText('1 coincidencia');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody tr')).toContainText('Moto');
  for (const theme of ['light', 'dark']) {
    await page.evaluate(theme => localStorage.setItem('devioz_theme', theme), theme);
    await page.reload();
    await expect(advanced).toHaveAttribute('open');
    const results = await new AxeBuilder({ page }).include('[data-reservation-filters]')
      .withRules(['label', 'color-contrast', 'aria-valid-attr']).analyze();
    expect(results.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/reservation-filters-${theme}-${testInfo.project.name}.png`, fullPage: true });
  }
  await filters.evaluate(element => { element.dataset.preserved = 'yes'; });
  // An unapplied edit must survive the timed refresh while results still use the URL filters.
  await filters.getByLabel('Pago', { exact: true }).selectOption('unpaid');
  const poll = await page.waitForResponse(response => response.request().method() === 'GET'
    && response.url().includes('/admin/reservas.php?') && !response.request().isNavigationRequest(), { timeout: 45000 });
  expect(new URL(poll.url()).searchParams.get('payment')).toBe('paid');
  await expect(page.locator('[data-live-status]')).toContainText('Datos actualizados');
  await expect(filters).toHaveAttribute('data-preserved', 'yes');
  await expect(filters.getByLabel('Pago', { exact: true })).toHaveValue('unpaid');
  await expect(advanced).toHaveAttribute('open');
  // POST actions retain the applied filters, regardless of unsubmitted edits.
  await page.getByRole('button', { name: 'Registrar ingreso', exact: true }).click();
  await expect(page).toHaveURL(/vehicle_type=moto/);
  await expect(filters.getByLabel('Pago', { exact: true })).toHaveValue('paid');
  await expect(page.locator('tbody tr')).toContainText('Vehículo dentro');
  await filters.getByLabel('Fecha de inicio desde', { exact: true }).fill('');
  await filters.getByLabel('Fecha de inicio hasta', { exact: true }).fill('');
  await filters.getByLabel('Estado', { exact: true }).selectOption('active');
  await filters.getByRole('button', { name: 'Filtrar', exact: true }).click();
  await page.getByRole('link', { name: 'Historial de mensualidades', exact: true }).click();
  await expect(filters.getByLabel('Estado', { exact: true })).toHaveValue('');
  await expect(filters.getByLabel('Tipo de vehículo', { exact: true })).toHaveValue('moto');
  await expect(page.locator('[data-reservation-count]')).toHaveText('57 coincidencias');
  await expect(page.locator('tbody tr')).toHaveCount(50);
  await page.getByRole('link', { name: 'Siguiente', exact: true }).click();
  await expect(page).toHaveURL(/page=2/);
  await expect(page.locator('tbody tr')).toHaveCount(7);
  await expect(page.locator('[data-reservation-count]')).toHaveText('57 coincidencias');
  await expect(filters.getByLabel('Buscar', { exact: true })).toHaveValue(plateSearch);
  await filters.getByLabel('Fecha de inicio desde', { exact: true }).fill(dates.today);
  await filters.getByLabel('Fecha de inicio hasta', { exact: true }).fill(dates.yesterday);
  await filters.getByRole('button', { name: 'Filtrar', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('no puede ser posterior');
  await expect(page.locator('[data-reservation-count]')).toHaveText('0 coincidencias');
  await page.getByRole('link', { name: 'Limpiar', exact: true }).click();
  await expect(filters.getByLabel('Buscar', { exact: true })).toHaveValue('');
  await expect(advanced).not.toHaveAttribute('open');
  expect(new URL(page.url()).searchParams.get('view')).toBe('history');
});
