const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const crypto = require('node:crypto');
const { isolateCameraTest } = require('./camera-test-scope');
const fixturePath = process.env.DEVIOZ_WEBRTC_TEST_CONFIG;
test.skip(!fixturePath, 'Usar node tools/test-camera-webrtc.cjs --pwa para un entorno aislado.');
const cameras = '/devioz-parking-php/frontend/admin/camaras.php';
const endpoint = '/devioz-parking-php/frontend/admin/camera-stream.php';
test.beforeEach(async ({ page }, info) => isolateCameraTest(page, info));
async function login(page, user = 'admin') {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}
async function pwaReady(page) {
  await login(page);
  await page.goto(cameras);
  await page.evaluate(async () => { await navigator.serviceWorker.ready; });
  await expect.poll(() => page.evaluate(() => !!navigator.serviceWorker.controller)).toBe(true);
}

test('PWA: manifiesto, iconos, instalación y diseño administrativo', async ({ page }, info) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await pwaReady(page);
  const href = await page.locator('link[rel="manifest"]').getAttribute('href');
  const response = await page.request.get(href);
  expect(response.headers()['content-type']).toContain('application/manifest+json');
  const manifest = await response.json();
  expect(manifest.display).toBe('standalone');
  expect(new URL(manifest.start_url, response.url()).pathname).toBe(cameras);
  for (const icon of manifest.icons) {
    const source = new URL(icon.src, response.url()).href;
    const dimensions = await page.evaluate(src => new Promise((resolve, reject) => {
      const image = new Image(); image.onload = () => resolve(`${image.naturalWidth}x${image.naturalHeight}`); image.onerror = reject; image.src = src;
    }), source);
    expect(dimensions).toBe(icon.sizes);
  }
  // Exercise the installation button without installing an application in the test PC.
  await page.evaluate(() => {
    const prompt = new Event('beforeinstallprompt', { cancelable: true });
    prompt.prompt = async () => { window.installRequested = true; };
    prompt.userChoice = Promise.resolve({ outcome: 'accepted' });
    dispatchEvent(prompt);
  });
  const button = page.getByRole('button', { name: 'Instalar Devioz', exact: true });
  await expect(button).toBeVisible();
  await page.screenshot({ path: `test-results/pwa-${info.project.name}.png`, fullPage: true });
  await button.click();
  await expect(page.locator('[data-pwa-status]')).toContainText('Instalación solicitada');
  expect(await page.evaluate(() => window.installRequested)).toBe(true);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  expect(errors).toEqual([]);
});

test('PWA: sin red muestra aviso público y no almacena páginas ni vídeo privados', async ({ page, context }) => {
  await pwaReady(page);
  const cached = await page.evaluate(async () => {
    const entries = await Promise.all((await caches.keys()).map(async key => (await (await caches.open(key)).keys()).map(request => request.url)));
    return entries.flat();
  });
  expect(cached.length).toBeGreaterThan(0);
  expect(cached.every(url => url.endsWith('/frontend/admin/offline.html'))).toBe(true);
  await context.setOffline(true);
  await page.goto(cameras);
  await expect(page.getByRole('heading', { name: 'No hay conexión con el sistema' })).toBeVisible();
  await expect(page.getByText('Prueba admin', { exact: true })).toHaveCount(0);
  await context.setOffline(false);
  await page.getByRole('link', { name: 'Volver a intentar' }).click();
  await expect(page.locator('[data-camera-module]')).toBeVisible();
});

test('PWA: conductor no recibe instalación administrativa ni permisos de cámara', async ({ page }) => {
  await login(page, 'driver');
  await expect(page.locator('link[rel="manifest"]')).toHaveCount(0);
  expect((await page.request.get(cameras)).status()).toBe(403);
  expect((await page.request.get(endpoint + '?action=connectivity')).status()).toBe(403);
});

test('ICE: configuración por administrador, caducidad y secreto maestro privado', async ({ page, browser }) => {
  await login(page);
  const local = await page.request.get(endpoint + '?action=connectivity');
  expect(local.status()).toBe(200);
  expect(local.headers()['cache-control']).toContain('no-store');
  expect((await local.json()).iceServers).toEqual([]);
  const configurationPath = fixturePath + '.ice.json';
  if (!/webrtc-test-[a-f0-9]{12}\.json\.ice\.json$/.test(configurationPath)) throw new Error('Ruta de prueba inválida');
  const secret = 'synthetic-turn-secret-'.repeat(3);
  try {
    fs.writeFileSync(configurationPath, JSON.stringify({ turn_urls: ['turns:relay.example.com:5349?transport=tcp'], turn_shared_secret: secret, ttl_seconds: 600 }));
    const response = await page.request.get(endpoint + '?action=connectivity');
    expect(response.status()).toBe(200);
    const value = await response.json();
    const credential = value.iceServers[0];
    expect(credential.username).toMatch(/^\d+:devioz-1$/);
    expect(credential.credential).toBe(crypto.createHmac('sha1', secret).update(credential.username).digest('base64'));
    expect(value.expiresAt).toBeGreaterThan(Math.floor(Date.now() / 1000) + 550);
    expect(JSON.stringify(value)).not.toContain(secret);
    fs.writeFileSync(configurationPath, '{invalid');
    const invalid = await page.request.get(endpoint + '?action=connectivity');
    expect(invalid.status()).toBe(503);
    expect(await invalid.text()).not.toContain(secret);
  } finally { if (fs.existsSync(configurationPath)) fs.unlinkSync(configurationPath); }
  const anonymous = await browser.newContext();
  expect((await anonymous.request.get(endpoint + '?action=connectivity')).status()).toBe(401);
  await anonymous.close();
});
