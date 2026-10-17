const { test, expect } = require('@playwright/test');

const APP = '/devioz-parking-php';
const fs = require('node:fs');
const path = require('node:path');
const AxeBuilder = require('@axe-core/playwright').default;

test.beforeEach(async ({ page }) => {
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
  await expect(page).toHaveURL(/dashboard\.php/);
}

async function openWebcam(page) {
  await page.addInitScript(() => {
    Object.defineProperty(navigator.mediaDevices, 'getUserMedia', {
      configurable: true,
      value: async () => {
        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 240;
        const context = canvas.getContext('2d');
        let frame = 0;
        const draw = () => {
          context.fillStyle = frame++ % 2 ? '#2563eb' : '#1d4ed8';
          context.fillRect(0, 0, canvas.width, canvas.height);
          context.fillStyle = '#fff';
          context.fillText(`DEVIOZ test ${frame}`, 20, 50);
        };
        draw();
        const timer = window.setInterval(draw, 100);
        const stream = canvas.captureStream(10);
        const stop = stream.getVideoTracks()[0].stop.bind(stream.getVideoTracks()[0]);
        stream.getVideoTracks()[0].stop = () => {
          window.clearInterval(timer);
          stop();
        };
        return stream;
      },
    });
  });
  await login(page);
  await page.goto(`${APP}/frontend/admin/camaras.php`);
  await page.locator('[data-camera-action="webcam"]').click();
  await page.locator('[data-camera-action="webcam-connect"]').click();
  await expect(page.locator('[data-recording-action="start"]')).toBeEnabled();
}

async function saveRecording(page) {
  const start = page.locator('[data-recording-action="start"]');
  const stop = page.locator('[data-recording-action="stop"]');
  await start.click();
  await expect(page.locator('[data-recording-status]')).toContainText('Grabando');
  await page.waitForTimeout(1300);
  await stop.click();
  await expect(page.locator('[data-recording-status]')).toContainText('Grabación guardada', { timeout: 30000 });
  const item = page.locator('[data-recordings-list] [data-recording-item]').first();
  await expect(item).toBeVisible();
  return item.getAttribute('data-recording-item');
}

test('graba vídeo real del navegador, lo conserva al recargar y permite reproducirlo', async ({ page }, testInfo) => {
  test.setTimeout(60000);
  await openWebcam(page);
  const id = await saveRecording(page);
  expect(id).toMatch(/^[a-f0-9]{32}$/);
  await page.reload();
  await page.locator('[data-camera-action="webcam"]').click();
  const item = page.locator(`[data-recording-item="${id}"]`);
  await expect(item).toBeVisible();
  await item.getByRole('button', { name: /Reproducir/ }).click();
  const video = page.locator('video[data-recordings-video]');
  await expect(video).toBeVisible();
  await expect.poll(() => video.evaluate(el => el.readyState)).toBeGreaterThanOrEqual(2);
  await expect.poll(() => video.evaluate(el => el.videoWidth)).toBeGreaterThan(0);
  await expect(page.locator('[data-recording-action="stop"]')).toBeDisabled();
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => document.documentElement.setAttribute('data-theme', value), theme);
    const accessibility = await new AxeBuilder({ page }).include('[data-camera-module]').analyze();
    expect(accessibility.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`recordings-${theme}.png`), fullPage: true });
  }
  await page.locator('[data-recording-action="refresh"]').focus();
  await expect(page.locator('[data-recording-action="refresh"]')).toBeFocused();
});

test('protege la grabación al navegar y reintenta un guardado sin duplicarlo', async ({ page }) => {
  test.setTimeout(60000);
  await openWebcam(page);
  let simulatedFailure = false;
  let savedId = null;
  await page.route('**/frontend/admin/grabaciones.php**', async route => {
    const request = route.request();
    const body = request.postData() || '';
    const isSave = request.method() === 'POST' && (
      /[?&]action=save(?:&|$)/.test(request.url()) ||
      /(?:^|&)action=save(?:&|$)/.test(body) ||
      /name="action"\r?\n\r?\nsave\r?\n/.test(body)
    );
    if (!isSave || simulatedFailure) {
      await route.continue();
      return;
    }
    simulatedFailure = true;
    const actual = await route.fetch();
    expect(actual.ok()).toBeTruthy();
    const result = await actual.json();
    savedId = result.recording.id;
    await route.fulfill({
      status: 503,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'No se pudo confirmar el guardado. Reintenta.' }),
    });
  });
  await page.locator('[data-recording-action="start"]').click();
  await expect(page.locator('[data-recording-status]')).toContainText('Grabando');
  const priorUrl = page.url();
  await page.locator('[data-camera-action="grid"]').filter({ visible: true }).first().click();
  expect(page.url()).toBe(priorUrl);
  await expect(page.locator('[data-recording-status]')).toContainText('Grabando');
  await expect(page.locator('[role="alert"]:visible').first()).toContainText(/grabaci[oó]n|guardar/i);
  await page.waitForTimeout(1100);
  await page.locator('[data-recording-action="stop"]').click();
  await expect(page.locator('[data-recording-action="retry"]')).toBeVisible({ timeout: 30000 });
  expect(simulatedFailure).toBeTruthy();
  expect(savedId).toMatch(/^[a-f0-9]{32}$/);
  await expect(page.locator('[data-recording-local-preview]')).toBeVisible();
  const localDownload = page.waitForEvent('download');
  await page.locator('[data-recording-action="download-local"]').click();
  expect((await localDownload).suggestedFilename()).toContain(savedId);
  // Recover the same account in a fresh session without reloading the page holding the Blob.
  await page.context().clearCookies();
  const renewal = await page.context().newPage();
  await login(renewal);
  await renewal.close();
  await page.locator('[data-recording-action="retry"]').click();
  await expect(page.locator('[data-recording-status]')).toContainText('Grabación guardada', { timeout: 30000 });
  await expect(page.locator(`[data-recording-item="${savedId}"]`)).toHaveCount(1);
  await page.reload();
  await page.locator('[data-camera-action="webcam"]').click();
  await expect(page.locator(`[data-recording-item="${savedId}"]`)).toHaveCount(1);
});

test('protege archivos, permisos, cargas y rangos de reproducción', async ({ page, browser }) => {
  test.setTimeout(60000);
  await openWebcam(page);
  const id = await saveRecording(page);
  const api = new URL(`${APP}/frontend/admin/grabaciones.php`, page.url()).href;
  const csrf = await page.locator('[data-camera-module]').getAttribute('data-recordings-csrf');
  const parking = await page.locator('[data-camera-parking]').inputValue();
  const list = await (await page.request.get(api, { params: { action: 'list', parking_id: parking } })).json();
  const item = list.items.find(item => item.id === id);
  expect(list.csrf_token).toBe(csrf);
  const mediaUrl = new URL(item.play_url, page.url()).href;
  const whole = await page.request.get(mediaUrl);
  expect(whole.status()).toBe(200);
  const bytes = await whole.body();
  expect(bytes.length).toBe(item.byte_size);
  expect(whole.headers()['cache-control']).toContain('no-store');
  for (const range of ['bytes=0-15', 'bytes=-16']) {
    const response = await page.request.get(mediaUrl, { headers: { Range: range } });
    expect(response.status()).toBe(206);
    expect((await response.body()).length).toBe(16);
    expect(response.headers()['content-range']).toContain(`/${bytes.length}`);
  }
  for (const range of [`bytes=${bytes.length}-`, 'bytes=0-1,4-5', 'bytes=-0']) {
    expect((await page.request.get(mediaUrl, { headers: { Range: range } })).status()).toBe(416);
  }
  const head = await page.request.head(mediaUrl);
  expect(head.status()).toBe(200);
  expect((await head.body()).length).toBe(0);
  expect(Number(head.headers()['content-length'])).toBe(bytes.length);
  const download = await page.request.get(new URL(item.download_url, page.url()).href);
  expect(download.headers()['content-disposition']).toContain('attachment');
  expect(await download.body()).toEqual(bytes);
  const changedBytes = Buffer.from(bytes);
  changedBytes[changedBytes.length - 1] ^= 1;
  const overwrite = await page.request.post(api, { multipart: { action: 'save', csrf_token: csrf, id,
    duration_ms: String(item.duration_ms), recording: { name: `changed.${item.format}`, mimeType: `video/${item.format}`, buffer: changedBytes } } });
  expect(overwrite.status()).toBe(400);
  expect((await overwrite.json()).error).toContain('ya fue guardada');
  const downloadEvent = page.waitForEvent('download');
  await page.locator(`[data-recording-item="${id}"]`).getByRole('link', { name: 'Descargar', exact: true }).click();
  expect((await downloadEvent).suggestedFilename()).toMatch(/\.(webm|mp4)$/);

  const fixture = JSON.parse(fs.readFileSync(path.resolve('backend/storage/browser-fixture.json'), 'utf8'));
  const privateRelative = `backend/storage/${fixture.database}-recordings/${id}.${item.format}`;
  expect(fs.statSync(path.resolve(privateRelative)).size).toBe(bytes.length);
  for (const origin of [new URL(page.url()).origin, 'http://localhost']) {
    expect((await page.request.get(`${origin}${APP}/${privateRelative}`)).status()).toBe(403);
  }
  expect((await page.request.post(api, { form: { action: 'begin', csrf_token: 'invalid', parking_id: parking, format: 'webm' } })).status()).toBe(400);
  expect((await page.request.post(api, { form: { action: 'cancel', csrf_token: csrf, id } })).status()).toBe(400);
  expect((await page.request.get(api, { params: { action: 'play', id: '../config/local.php' } })).status()).toBe(404);

  const begin = await page.request.post(api, { form: { action: 'begin', csrf_token: csrf, parking_id: parking, format: 'webm' } });
  expect(begin.status()).toBe(201);
  const pending = (await begin.json()).recording;
  const malicious = await page.request.post(api, { multipart: { action: 'save', csrf_token: csrf, id: pending.id, duration_ms: '1000',
    recording: { name: 'video.webm', mimeType: 'video/webm', buffer: Buffer.from('<html><script>bad</script>' + 'x'.repeat(100) + '</html>') } } });
  expect(malicious.status()).toBe(400);

  const stranger = await browser.newContext({ baseURL: new URL(page.url()).origin });
  const driver = await browser.newContext({ baseURL: new URL(page.url()).origin });
  try {
    expect((await stranger.request.get(mediaUrl, { maxRedirects: 0 })).status()).toBe(302);
    const otherPage = await stranger.newPage();
    await login(otherPage, 'recorder');
    const otherToken = (await (await stranger.request.get(api, { params: { action: 'list', parking_id: parking } })).json()).csrf_token;
    expect((await stranger.request.post(api, { form: { action: 'cancel', csrf_token: otherToken, id: pending.id } })).status()).toBe(404);
    expect((await stranger.request.post(api, { multipart: { action: 'save', csrf_token: otherToken, id: pending.id, duration_ms: '1000',
      recording: { name: `video.${item.format}`, mimeType: `video/${item.format}`, buffer: bytes } } })).status()).toBe(404);
    await login(await driver.newPage(), 'driver');
    expect((await driver.request.get(mediaUrl)).status()).toBe(403);
    expect((await driver.request.get(api, { params: { action: 'list', parking_id: parking } })).status()).toBe(403);
  } finally { await stranger.close(); await driver.close(); }
  expect((await page.request.post(api, { form: { action: 'cancel', csrf_token: csrf, id: pending.id } })).status()).toBe(200);
});

test('permite descartar expresamente un vídeo que no llegó a guardarse', async ({ page }) => {
  test.setTimeout(60000);
  await openWebcam(page);
  await page.route('**/frontend/admin/grabaciones.php**', async route => {
    if (route.request().method() === 'POST' && /name="action"\r?\n\r?\nsave\r?\n/.test(route.request().postData() || '')) {
      await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Servidor no disponible para guardar.' }) });
    } else await route.continue();
  });
  await page.locator('[data-recording-action="start"]').click();
  await expect(page.locator('[data-recording-status]')).toContainText('Grabando');
  await expect(page.locator('[data-camera-action="webcam-disconnect"]')).toBeDisabled();
  await expect(page.locator('[data-camera-action="webcam-play"]')).toBeDisabled();
  await page.waitForTimeout(1100);
  await page.locator('[data-recording-action="stop"]').click();
  await expect(page.locator('[data-recording-action="retry"]')).toBeVisible();
  await page.locator('[data-recording-discard] summary').click();
  await page.locator('[data-recording-action="discard"]').click();
  await expect(page.locator('[data-recording-status]')).toHaveText('Sin grabación en curso');
  await expect(page.locator('[data-recording-action="start"]')).toBeEnabled();
  await expect(page.locator('[data-recording-local-preview]')).toBeHidden();
  await page.locator('[data-camera-action="grid"]').filter({ visible: true }).first().click();
  await expect(page.locator('[data-camera-view="grid"]')).toBeVisible();
});
