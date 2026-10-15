const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');
const php = process.env.PHP_BIN || 'php';
const fixture = (mode, base) => execFileSync(php, [path.join(__dirname, '../lifecycle-browser-fixture.php'), mode, String(base)]);
async function login(page, user) {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}

test('vencimiento sin recarga, historial, croquis y renovación con nuevo pago', async ({ page, context, browser }, testInfo) => {
  test.setTimeout(100000);
  const base = testInfo.project.name === 'desktop' ? 100 : 200;
  fixture('seed', base);
  await login(page, 'admin');
  await page.goto('/devioz-parking-php/frontend/admin/reservas.php');
  const row = page.locator('tbody tr').filter({ hasText: `L${base}` });
  await expect(row).toContainText('Registrar ingreso');
  const map = await context.newPage();
  await map.goto('/devioz-parking-php/frontend/admin/espacios.php');
  const outside = map.locator(`[data-space-code="L${base}"]`);
  const inside = map.locator(`[data-space-code="L${base + 1}"]`);
  await expect(outside).toHaveAttribute('data-space-status', 'reserved');
  await inside.click();
  await expect(map.locator('#detailSpaceCode')).toHaveText(`L${base + 1}`);
  let navigations = 0;
  page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
  fixture('age', base);
  // Both tabs stay visible to their contexts; a background tab updates when it gains visibility.
  await page.bringToFront();
  await expect(row).toHaveCount(0, { timeout: 45000 });
  await expect(page.locator('tbody tr').filter({ hasText: `L${base + 1}` }))
    .toContainText('Mensualidad vencida · Pendiente de salida');
  expect(navigations).toBe(0);
  await map.bringToFront();
  await expect(outside).toHaveAttribute('data-space-status', 'available', { timeout: 45000 });
  await expect(inside).toHaveAttribute('data-space-status', 'occupied');
  await inside.click();
  await expect(map.locator('#detailVehiclePlate')).toHaveText(`MOT-${base}`);
  await page.getByRole('link', { name: 'Historial de mensualidades' }).click();
  await expect(page.locator('tbody tr').filter({ hasText: `L${base}` })).toContainText('Mensualidad finalizada');
  await map.screenshot({ path: `test-results/lifecycle-map-${testInfo.project.name}.png`, fullPage: true });
  const driverContext = await browser.newContext({ baseURL: 'http://127.0.0.1:8099' });
  try {
    const driver = await driverContext.newPage();
    await login(driver, 'driver');
    await driver.goto('/devioz-parking-php/frontend/conductor/reservas.php?view=history');
    const old = driver.locator('tbody tr').filter({ hasText: `L${base}` });
    await old.getByRole('button', { name: 'Renovar mensualidad' }).click();
    await expect(driver).toHaveURL(/\/conductor\/reservas\.php$/);
    await expect(driver.locator('tbody tr').filter({ hasText: `L${base}` })).toContainText('Sin pagar');
    await expect(driver.locator('tbody tr').filter({ hasText: `L${base}` })).toContainText('Pagar');
  } finally { await driverContext.close(); }
});
