const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

async function login(page, user) {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}

test('landing, etiquetas y foco de login/registro', async ({ page }, testInfo) => {
  await page.goto('/devioz-parking-php/frontend/index.php');
  await expect(page.locator('.landing-hero h1')).toHaveCSS('opacity', '1');
  await page.screenshot({ path: `test-results/landing-${testInfo.project.name}.png` });
  await page.locator('.js-open-auth[data-auth-mode="login"]').filter({ visible: true }).first().click();
  const dialog = page.getByRole('dialog', { name: 'Acceso a Devioz Parking' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByLabel('Correo electrónico').filter({ visible: true })).toBeFocused();
  await dialog.getByRole('button', { name: 'Cerrar', exact: true }).focus();
  await page.keyboard.press('Shift+Tab');
  await expect(page.locator('#authModal :focus')).toHaveCount(1);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await page.locator('.js-open-auth[data-auth-mode="register"]').filter({ visible: true }).first().click();
  await expect(dialog.getByLabel('Nombre', { exact: true })).toBeFocused();
  await expect(dialog.getByLabel('Confirmar contraseña')).toBeVisible();
  const results = await new AxeBuilder({ page }).include('#authModal').withRules(['label','aria-dialog-name','aria-valid-attr','button-name']).analyze();
  expect(results.violations).toEqual([]);
});

test('landing disponible con sesión y cierre de sesión de cada rol', async ({ page }, testInfo) => {
  for (const user of ['driver', 'admin']) {
    if (user === 'admin') await page.context().clearCookies();
    await login(page, user);
    const response = await page.goto('/devioz-parking-php/');
    expect(response.status()).toBe(200);
    await expect(page).toHaveURL(/frontend\/index\.php$/);
    await expect(page.locator('.landing-hero h1')).toHaveCSS('opacity', '1');
    await expect(page.locator('.js-open-auth')).toHaveCount(0);
    await page.locator('.landing-hero').getByRole('link', { name: 'Ir a mi panel' }).click();
    await expect(page).toHaveURL(new RegExp(`/frontend/${user === 'admin' ? 'admin' : 'conductor'}/dashboard\\.php$`));
    if (testInfo.project.name === 'mobile') await page.locator('.topbar-menu-btn').click();
    await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
    await expect(page).toHaveURL(/frontend\/index\.php$/);
    await expect(page.locator('.landing-hero h1')).toHaveCSS('opacity', '1');
    await expect(page.getByRole('dialog', { name: 'Acceso a Devioz Parking' })).toBeHidden();
    await expect(page.locator('.landing-hero .js-open-auth[data-auth-mode="register"]')).toBeVisible();
    expect((await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1', { maxRedirects: 0 })).status()).toBe(302);
  }
});

test('borrar historial elimina solo leídas propias y exige CSRF', async ({ page, browser }, testInfo) => {
  const user = testInfo.project.name === 'desktop' ? 'driver' : 'other';
  const otherUser = user === 'driver' ? 'other' : 'driver';
  await login(page, user);
  const otherContext = await browser.newContext({ baseURL: 'http://127.0.0.1:8099' });
  try {
    const otherPage = await otherContext.newPage();
    await login(otherPage, otherUser);
    const endpoint = '/devioz-parking-php/frontend/notificaciones.php';
    const otherBefore = await (await otherPage.request.get(endpoint)).json();
    const initial = await (await page.request.get(endpoint)).json();
    expect(initial.read_count).toBe(60);
    expect(initial.unread_count).toBe(1);
    expect(initial.items).toHaveLength(50);
    const badCsrf = await page.request.post(endpoint, { form: { action: 'clear_read', csrf_token: 'invalid' } });
    expect(badCsrf.status()).toBe(400);
    expect((await (await page.request.get(endpoint + '?action=clear_read')).json()).read_count).toBe(60);
    await page.getByRole('button', { name: 'Notificaciones', exact: true }).click();
    const clear = page.getByRole('button', { name: 'Borrar historial', exact: true });
    await expect(clear).toBeEnabled();
    await clear.click();
    await expect(page.locator('[data-notification-feedback]')).toContainText('Se eliminaron 60');
    await expect(clear).toBeDisabled();
    await expect(page.locator('.notification-item')).toHaveCount(1);
    await expect(page.locator('.notification-item')).toContainText('Aviso pendiente');
    await expect(page.locator('[data-notification-dot]')).toBeVisible();
    const after = await (await page.request.get(endpoint)).json();
    expect(after.read_count).toBe(0);
    expect(after.unread_count).toBe(1);
    expect(await (await otherPage.request.get(endpoint)).json()).toEqual(otherBefore);
    await page.screenshot({ path: `test-results/notifications-${testInfo.project.name}.png` });
    await page.getByRole('button', { name: 'Cambiar a tema oscuro', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.getByRole('button', { name: 'Notificaciones', exact: true }).click();
    await expect(clear).toBeVisible();
    const panelBounds = await page.locator('[data-notification-panel]').boundingBox();
    expect(panelBounds.x).toBeGreaterThanOrEqual(0);
    expect(panelBounds.x + panelBounds.width).toBeLessThanOrEqual(page.viewportSize().width);
    await page.screenshot({ path: `test-results/notifications-dark-${testInfo.project.name}.png` });
    await page.locator('.notification-item').getByRole('button', { name: 'Marcar como leído', exact: true }).click();
    await expect(clear).toBeEnabled();
    await clear.click();
    await expect(page.locator('.notification-empty')).toHaveText('No tienes notificaciones.');
    await expect(clear).toBeDisabled();
    await expect(page.locator('[data-notification-dot]')).toBeHidden();
  } finally {
    await otherContext.close();
  }
});

test('documentos y rutas internas no son públicos', async ({ request }) => {
  for (const route of ['/.git/HEAD','/database/sql/schema.sql','/backend/uploads/comprobantes/']) {
    expect((await request.get('/devioz-parking-php'+route)).status()).toBe(403);
  }
  expect((await request.get('/devioz-parking-php/frontend/logout.php')).status()).toBe(405);
  const r=await request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1',{ maxRedirects: 0 });
  expect(r.status()).toBe(302);
});

test('conductor: propietario y permisos por recurso', async ({ page }) => {
  await login(page,'driver');
  const good=await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1');
  expect(good.status()).toBe(200);
  expect((await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=expense&id=1')).status()).toBe(404);
  expect((await page.request.get('/devioz-parking-php/frontend/admin/usuarios.php')).status()).toBe(403);
  await page.goto('/devioz-parking-php/frontend/logout.php');
  expect((await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1')).status()).toBe(200);
});

test('otro conductor no puede descargar el comprobante', async ({ page }) => {
  await login(page,'other');
  expect((await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1')).status()).toBe(404);
});

test('admin: búsquedas sin errores y cierre de sesión POST', async ({ page }, testInfo) => {
  await login(page,'admin');
  await page.screenshot({ path: `test-results/dashboard-${testInfo.project.name}.png`, fullPage: true });
  for (const path of ['usuarios','pagos','auditoria']) {
    const response=await page.goto(`/devioz-parking-php/frontend/admin/${path}.php?search=Prueba`);
    expect(response.status()).toBe(200);
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
  }
  if (testInfo.project.name === 'mobile') await page.locator('.topbar-menu-btn').click();
  await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();
  await expect(page).toHaveURL(/frontend\/index\.php$/);
  await expect(page.locator('.landing-hero h1')).toHaveCSS('opacity', '1');
  await expect(page.getByRole('dialog', { name: 'Acceso a Devioz Parking' })).toBeHidden();
  await expect(page.locator('.landing-hero .js-open-auth[data-auth-mode="register"]')).toBeVisible();
  expect((await page.request.get('/devioz-parking-php/frontend/comprobante.php?type=payment&id=1',{maxRedirects:0})).status()).toBe(302);
});

test('registro rechaza truncado de contraseña y permite crear una cuenta', async ({ page }, testInfo) => {
  await page.goto('/devioz-parking-php/frontend/index.php');
  await page.locator('.js-open-auth[data-auth-mode="register"]').filter({ visible: true }).first().click();
  const dialog=page.getByRole('dialog');
  const email=`registration-${testInfo.project.name}-${Date.now()}@browser.invalid`;
  await dialog.getByLabel('Nombre',{exact:true}).fill('Registro sintético');
  await dialog.getByLabel('Correo electrónico').filter({visible:true}).fill(email);
  await dialog.getByLabel('Contraseña',{exact:true}).filter({visible:true}).fill('a'.repeat(73));
  await dialog.getByLabel('Confirmar contraseña').fill('a'.repeat(73));
  await dialog.locator('button[type=submit]').filter({visible:true}).click();
  await expect(page.locator('body')).toContainText('La contraseña debe tener entre 8 y 72 bytes.');
  await dialog.getByLabel('Nombre',{exact:true}).fill('Registro sintético');
  await dialog.getByLabel('Correo electrónico').filter({visible:true}).fill(email);
  await dialog.getByLabel('Contraseña',{exact:true}).filter({visible:true}).fill('Testing123!');
  await dialog.getByLabel('Confirmar contraseña').fill('Testing123!');
  await dialog.locator('button[type=submit]').filter({visible:true}).click();
  await expect(page).toHaveURL(/conductor\/dashboard\.php/);
});

test('subida real de comprobante: reintento y rechazo por CSRF', async ({page},testInfo) => {
  await login(page,'driver');
  await page.goto('/devioz-parking-php/frontend/conductor/pagos.php');
  const token=await page.locator('input[name=csrf_token]').first().inputValue();
  const id=testInfo.project.name==='desktop'?2:3;
  const payload={csrf_token:token,reservation_id:String(id),method:'yape',provider_tx_id:'123',voucher:{name:'synthetic.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n%%EOF\n')}};
  const first=await page.request.post('/devioz-parking-php/frontend/conductor/pagos.php',{multipart:payload});
  expect(first.status()).toBe(200);
  expect(await first.text()).toContain('enviado');
  const second=await page.request.post('/devioz-parking-php/frontend/conductor/pagos.php',{multipart:payload});
  expect(await second.text()).toContain('La reserva no admite un nuevo pago.');
  const invalid=await page.request.post('/devioz-parking-php/frontend/conductor/pagos.php',{multipart:{...payload,csrf_token:'invalid-token'}});
  expect(await invalid.text()).toContain('Token CSRF invalido.');
});
