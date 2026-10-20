const { test, expect } = require('@playwright/test');
const { createHash } = require('node:crypto');
const AxeBuilder = require('@axe-core/playwright').default;
const APP = '/devioz-parking-php';
const pages = ['dashboard', 'vehiculos', 'reservar', 'reservas', 'pagos'];

test.beforeEach(async ({ page }, testInfo) => {
  const scope = createHash('sha256').update(testInfo.testId).digest('hex').slice(0, 12);
  await page.context().setExtraHTTPHeaders({ 'X-Devioz-Test-Scope': `${testInfo.project.name}-${scope}` });
  await page.route('https://cdn.jsdelivr.net/**', async route => {
    const headers = { ...route.request().headers() };
    delete headers['x-devioz-test-scope'];
    await route.continue({ headers });
  });
});

async function login(page, user = 'admin') {
  await page.goto(`${APP}/frontend/login.php`);
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php$/);
}

test('usuarios abre el panel real y mantiene contexto entre pantallas y pestañas', async ({ page }, testInfo) => {
  await login(page);
  await page.goto(`${APP}/frontend/admin/usuarios.php`);
  await expect(page.locator('tr').filter({ hasText: 'admin@browser.invalid' }).locator('[data-manage-driver]')).toHaveCount(0);
  const manage = page.locator('[data-manage-driver="2"]');
  await manage.focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/conductor\/dashboard\.php\?managed_driver_id=2$/);
  const banner = page.locator('[data-driver-management="2"]');
  await expect(banner).toContainText('Gestionando a: Prueba driver');
  await expect(banner).toContainText('Prueba admin (administrador)');
  await expect(page.locator('.topbar-user')).toContainText('Prueba admin');
  for (const name of pages) {
    const response = await page.goto(`${APP}/frontend/conductor/${name}.php?managed_driver_id=2`);
    expect(response.status()).toBe(200);
    await expect(page.locator('[data-driver-management="2"]')).toBeVisible();
    const links = await page.locator('.sidebar-nav a').evaluateAll(items => items.map(item => item.getAttribute('href')));
    expect(links.every(link => link.includes('managed_driver_id=2'))).toBe(true);
  }
  const otherTab = await page.context().newPage();
  try {
    await otherTab.goto(`${APP}/frontend/conductor/dashboard.php?managed_driver_id=3`);
    await expect(otherTab.locator('[data-driver-management="3"]')).toContainText('Prueba other');
    await page.reload();
    await expect(page.locator('[data-driver-management="2"]')).toContainText('Prueba driver');
  } finally { await otherTab.close(); }

  await page.goto(`${APP}/frontend/conductor/reservas.php?managed_driver_id=2`);
  await page.getByRole('link', { name: 'Historial y renovación', exact: true }).click();
  await expect(page).toHaveURL(/view=history&managed_driver_id=2$/);
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => localStorage.setItem('devioz_theme', value), theme);
    await page.reload();
    expect((await new AxeBuilder({ page }).include('.driver-management-banner')
      .withRules(['color-contrast', 'link-name']).analyze()).violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/driver-management-${testInfo.project.name}-${theme}.png` });
  }
  await page.getByRole('link', { name: 'Volver a administración', exact: true }).click();
  await expect(page).toHaveURL(/admin\/usuarios\.php$/);
  await expect(page.locator('[data-driver-management]')).toHaveCount(0);
});

test('administrador registra vehículo, reserva y comprobante Yape para el conductor', async ({ page }, testInfo) => {
  const mobile = testInfo.project.name === 'mobile';
  const id = mobile ? 3 : 2;
  const plate = mobile ? 'GMB-101' : 'GDT-101';
  const vehiclePath = `${APP}/frontend/conductor/vehiculos.php?managed_driver_id=${id}`;
  await login(page);
  await page.goto(vehiclePath);
  await page.getByLabel('Tipo', { exact: true }).selectOption('moto');
  await page.getByLabel('Marca', { exact: true }).fill('Marca gestión');
  await page.getByLabel('Modelo', { exact: true }).fill('Modelo gestión');
  await page.getByLabel('Placa', { exact: true }).fill(plate);
  await page.getByRole('button', { name: 'Registrar vehículo', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`managed_driver_id=${id}$`));
  await expect(page.locator('.vehicle-card').filter({ hasText: plate })).toBeVisible();
  const rejected = await page.request.post(vehiclePath, { maxRedirects: 0,
    form: { action: 'create', plate: 'BAD-101', type: 'moto', brand: 'Prueba', model: 'Prueba', csrf_token: 'invalid' } });
  expect(rejected.headers().location).toContain(`managed_driver_id=${id}`);
  await page.reload();
  await expect(page.locator('.vehicle-card').filter({ hasText: 'BAD-101' })).toHaveCount(0);
  await page.goto(`${APP}/frontend/conductor/reservar.php?managed_driver_id=${id}`);
  await page.locator(`[data-space-id="${mobile ? 5 : 4}"]`).click();
  const vehicle = await page.locator('#vehicleSelect option').filter({ hasText: plate }).getAttribute('value');
  await page.locator('#vehicleSelect').selectOption(vehicle);
  await page.locator('#reservationForm button[type="submit"]').click();
  await expect(page).toHaveURL(new RegExp(`conductor/reservas\\.php\\?managed_driver_id=${id}$`));
  const row = page.locator('tr').filter({ hasText: plate }).filter({ has: page.locator('[data-reservation-id]') });
  const reservation = await row.locator('[data-reservation-id]').first().getAttribute('data-reservation-id');
  expect(Number(reservation)).toBeGreaterThan(0);
  const csrf = await page.locator('input[name="csrf_token"]').first().inputValue();
  const payment = await page.request.post(`${APP}/frontend/conductor/pagos.php?managed_driver_id=${id}`, {
    maxRedirects: 0, multipart: { reservation_id: reservation, method: 'yape', provider_tx_id: '123', csrf_token: csrf,
      voucher: { name: 'constancia.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF\n') } } });
  expect(payment.headers().location).toContain(`managed_driver_id=${id}`);
  await page.goto(`${APP}/frontend/conductor/pagos.php?managed_driver_id=${id}`);
  const paymentRow = page.locator('tbody tr').filter({ hasText: plate });
  await expect(paymentRow).toContainText('Pendiente');
  const voucherUrl = await paymentRow.locator('[data-voucher-url]').getAttribute('data-voucher-url');
  const voucher = await page.request.get(voucherUrl);
  expect(voucher.status()).toBe(200);
  expect((await voucher.body()).subarray(0, 8).toString()).toBe('%PDF-1.4');
  expect((await page.request.get(`${APP}/frontend/admin/usuarios.php`)).status()).toBe(200);
  await expect(page.locator('.topbar-user')).toContainText('Prueba admin');
});

test('selección administrativa rechaza invitados, conductores y destinos inválidos', async ({ page }) => {
  expect((await page.request.get(`${APP}/frontend/conductor/dashboard.php?managed_driver_id=2`, { maxRedirects: 0 })).status()).toBe(302);
  await login(page, 'driver');
  for (const name of pages) {
    expect((await page.request.get(`${APP}/frontend/conductor/${name}.php?managed_driver_id=2`, { maxRedirects: 0 })).status()).toBe(403);
  }
  await page.context().clearCookies();
  await login(page);
  for (const target of ['1', '999999', 'invalid', '%5B2%5D']) {
    expect((await page.request.get(`${APP}/frontend/conductor/dashboard.php?managed_driver_id=${target}`)).status()).toBe(404);
  }
  expect((await page.request.get(`${APP}/frontend/conductor/dashboard.php`, { maxRedirects: 0 })).status()).toBe(403);
  expect((await page.request.get(`${APP}/frontend/admin/usuarios.php`)).status()).toBe(200);
});
