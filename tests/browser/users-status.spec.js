const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
async function login(page, user) {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}
test('usuarios: permisos, CSRF, activar/desactivar y sesiones antiguas', async ({ page, browser }, testInfo) => {
  const driverContext = await browser.newContext({ baseURL: 'http://127.0.0.1:8099', extraHTTPHeaders: { 'X-Devioz-Test-Scope': testInfo.project.name } });
  const driver = await driverContext.newPage();
  const base = '/devioz-parking-php/frontend/admin/usuarios.php';
  try {
    await login(driver, 'driver');
    const forbidden = await driver.request.post(base, { form: { action: 'change_status', user_id: '1', status: 'inactive' } });
    expect(forbidden.status()).toBe(403);
    await login(page, 'admin');
    await page.goto(base + '?search=driver%40browser.invalid');
    let row = page.locator('tbody tr').filter({ hasText: 'driver@browser.invalid' });
    const csrfRejected = await page.request.post(base, { form: { action: 'change_status', user_id: '2', status: 'inactive', csrf_token: 'invalid' } });
    expect(await csrfRejected.text()).toContain('Token CSRF');
    await expect(row.locator('summary')).toHaveText('Desactivar usuario');
    await row.locator('summary').click();
    await row.getByRole('button', { name: 'Confirmar desactivación' }).click();
    await expect(page).toHaveURL(/search=driver/);
    row = page.locator('tbody tr').filter({ hasText: 'driver@browser.invalid' });
    await expect(row.locator('summary')).toHaveText('Activar usuario');
    const blocked = await driver.request.get('/devioz-parking-php/frontend/conductor/dashboard.php', { maxRedirects: 0 });
    expect(blocked.status()).toBe(302);
    await row.locator('summary').click();
    await row.getByRole('button', { name: 'Confirmar activación' }).click();
    await expect(row.locator('summary')).toHaveText('Desactivar usuario');
    expect((await driver.request.get('/devioz-parking-php/frontend/conductor/dashboard.php', { maxRedirects: 0 })).status()).toBe(302);
    await login(driver, 'driver');
    for (const theme of ['light', 'dark']) {
      await page.evaluate(value => localStorage.setItem('devioz_theme', value), theme);
      await page.reload();
      expect((await new AxeBuilder({ page }).include('.panel-card').withRules(['button-name','color-contrast']).analyze()).violations).toEqual([]);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await page.screenshot({ path: `test-results/users-status-${testInfo.project.name}-${theme}.png`, fullPage: true });
    }
    await page.goto(base + '?search=admin%40browser.invalid');
    await expect(page.locator('tbody tr').filter({ hasText: 'admin@browser.invalid' })).toContainText('Tu cuenta');
    await expect(page.locator('tbody summary')).toHaveCount(0);
  } finally { await driverContext.close(); }
});
