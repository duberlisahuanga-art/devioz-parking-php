const { test, expect } = require('@playwright/test');
const { createHash } = require('node:crypto');
const AxeBuilder = require('@axe-core/playwright').default;
const APP = '/devioz-parking-php';
const NEW = `${APP}/frontend/admin/nuevo-administrador.php`;

test.beforeEach(async ({ page }, testInfo) => {
  const scope = createHash('sha256').update(testInfo.testId).digest('hex').slice(0, 12);
  await page.context().setExtraHTTPHeaders({ 'X-Devioz-Test-Scope': `${testInfo.project.name}-${scope}` });
  await page.route('https://cdn.jsdelivr.net/**', async route => {
    const headers = { ...route.request().headers() };
    delete headers['x-devioz-test-scope'];
    await route.continue({ headers });
  });
});

async function login(page, email = 'admin@browser.invalid', password = 'Testing123!') {
  await page.goto(`${APP}/frontend/login.php`);
  await page.getByLabel('Correo electrónico').fill(email);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php$/);
}

test('crea administrador con acceso a los mismos módulos y conserva al creador', async ({ page, browser }, testInfo) => {
  const email = `new-admin-${testInfo.project.name}@browser.invalid`;
  await login(page);
  const originalMenu = await page.locator('.sidebar-nav a').evaluateAll(items => items.map(item => item.getAttribute('href')));
  await page.goto(`${APP}/frontend/admin/usuarios.php`);
  await page.getByRole('link', { name: 'Nuevo administrador', exact: true }).click();
  await expect(page).toHaveURL(/admin\/nuevo-administrador\.php$/);
  await expect(page.getByText('La nueva cuenta tendrá acceso completo', { exact: false })).toBeVisible();
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => localStorage.setItem('devioz_theme', value), theme);
    await page.reload();
    expect((await new AxeBuilder({ page }).include('.panel-card')
      .withRules(['label', 'button-name', 'color-contrast', 'link-name']).analyze()).violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/new-administrator-${testInfo.project.name}-${theme}.png` });
  }
  await page.getByLabel('Nombre completo').fill('Administrador nuevo');
  await page.getByLabel('Correo electrónico').fill(email);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing456!');
  await page.getByLabel('Confirmar contraseña').fill('Different123!');
  await page.getByRole('button', { name: 'Crear administrador', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Las contraseñas no coinciden');
  await expect(page.getByLabel('Nombre completo')).toHaveValue('Administrador nuevo');
  await expect(page.getByLabel('Contraseña', { exact: true })).toHaveValue('');
  await expect(page.getByLabel('Confirmar contraseña')).toHaveValue('');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing456!');
  await page.getByLabel('Confirmar contraseña').fill('Testing456!');
  await page.getByRole('button', { name: 'Crear administrador', exact: true }).focus();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/usuarios\.php\?search=new-admin/);
  await expect(page.locator('tbody tr').filter({ hasText: email })).toContainText('Administrador');
  await expect(page.locator('.topbar-user')).toContainText('Prueba admin');

  const newContext = await browser.newContext({ baseURL: 'http://127.0.0.1:8099' });
  try {
    const newAdmin = await newContext.newPage();
    await login(newAdmin, email, 'Testing456!');
    expect(await newAdmin.locator('.sidebar-nav a').evaluateAll(items => items.map(item => item.getAttribute('href')))).toEqual(originalMenu);
    for (const route of [...originalMenu, `${APP}/frontend/configuracion.php`, NEW]) {
      expect((await newAdmin.request.get(route)).status()).toBe(200);
    }
    await expect(newAdmin.locator('.topbar-user')).toContainText('Administrador nuevo');
  } finally { await newContext.close(); }
});

test('formulario exige permisos y CSRF y explica correo duplicado', async ({ page }, testInfo) => {
  const data = { name: 'Intento', email: `blocked-${testInfo.project.name}@browser.invalid`,
    password: 'Testing456!', password_confirmation: 'Testing456!' };
  expect((await page.request.get(NEW, { maxRedirects: 0 })).status()).toBe(302);
  expect((await page.request.post(NEW, { form: data, maxRedirects: 0 })).status()).toBe(302);
  await login(page, 'driver@browser.invalid');
  expect((await page.request.get(NEW)).status()).toBe(403);
  expect((await page.request.post(NEW, { form: data })).status()).toBe(403);
  await page.context().clearCookies();
  await login(page);
  const rejected = await page.request.post(NEW, { form: { ...data, csrf_token: 'invalid' } });
  expect(rejected.status()).toBe(403);
  expect(await rejected.text()).not.toContain(data.password);
  await page.goto(NEW);
  await page.getByLabel('Nombre completo').fill(data.name);
  await page.getByLabel('Correo electrónico').fill('ADMIN@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill(data.password);
  await page.getByLabel('Confirmar contraseña').fill(data.password_confirmation);
  await page.getByRole('button', { name: 'Crear administrador', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('El correo ya está registrado');
  await expect(page.getByLabel('Contraseña', { exact: true })).toHaveValue('');
  await page.goto(`${APP}/frontend/admin/usuarios.php?search=${encodeURIComponent(data.email)}`);
  await expect(page.locator('tbody tr')).toHaveCount(0);
});

test('registro público continúa creando conductores aunque se envíe rol administrador', async ({ page }, testInfo) => {
  await page.goto(`${APP}/frontend/login.php`);
  const csrf = await page.locator('input[name="csrf_token"]').inputValue();
  const response = await page.request.post(`${APP}/backend/auth/register.php`, { maxRedirects: 0, form: {
    name: 'Registro público', email: `public-role-${testInfo.project.name}@browser.invalid`,
    password: 'Testing456!', password_confirmation: 'Testing456!', role: 'admin', csrf_token: csrf } });
  expect(response.headers().location).toBe(`${APP}/frontend/conductor/dashboard.php`);
  expect((await page.request.get(`${APP}/frontend/admin/dashboard.php`)).status()).toBe(403);
});
