const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const { isolateCameraTest } = require('./camera-test-scope');
const configurationPath = process.env.DEVIOZ_WEBRTC_TEST_CONFIG;
test.skip(!configurationPath, 'Usar node tools/test-camera-webrtc.cjs para el puente RTSP aislado.');
test.describe.configure({ mode: 'serial' });
let publisher;
let publisherContext;
const cameraPath = '/devioz-parking-php/frontend/admin/camaras.php';
const endpoint = '/devioz-parking-php/frontend/admin/camera-stream.php';
const action = (page, name) => page.locator(`[data-camera-action="${name}"]`).filter({ visible: true }).first();

test.beforeAll(async ({ browser }) => {
  if (!configurationPath) return;
  const configuration = JSON.parse(fs.readFileSync(configurationPath, 'utf8'));
  if (!configuration.api.listen.startsWith('127.0.0.1:') || !configuration.streams.fixture) throw new Error('Sólo fixture aislado');
  publisherContext = await browser.newContext();
  publisher = await publisherContext.newPage();
  await publisher.goto('/devioz-parking-php/frontend/login.php');
  const offer = await publisher.evaluate(async () => {
    const canvas = document.createElement('canvas');
    canvas.width = 320; canvas.height = 180;
    document.body.append(canvas);
    const draw = canvas.getContext('2d');
    let frame = 0;
    const paint = () => { draw.fillStyle = frame++ % 2 ? '#2563eb' : '#1d4ed8'; draw.fillRect(0, 0, 320, 180); draw.fillStyle = 'white'; draw.font = '24px sans-serif'; draw.fillText(`RTSP prueba ${frame}`, 12, 90); };
    paint();
    window.publisherTimer = setInterval(paint, 80);
    const pc = window.publisherPC = new RTCPeerConnection({ iceServers: [] });
    const transceiver = pc.addTransceiver(canvas.captureStream(12).getVideoTracks()[0], { direction: 'sendonly' });
    const h264 = RTCRtpSender.getCapabilities('video').codecs.filter(codec => codec.mimeType.toLowerCase() === 'video/h264');
    if (!h264.length) throw new Error('H.264 no disponible en navegador de prueba');
    transceiver.setCodecPreferences(h264);
    await pc.setLocalDescription(await pc.createOffer());
    await new Promise(resolve => {
      if (pc.iceGatheringState === 'complete') return resolve();
      pc.addEventListener('icegatheringstatechange', () => { if (pc.iceGatheringState === 'complete') resolve(); });
      setTimeout(resolve, 2000);
    });
    return pc.localDescription.sdp;
  });
  const response = await fetch(`http://${configuration.api.listen}/api/webrtc?dst=fixture`, {
    method: 'POST', signal: AbortSignal.timeout(10000),
    headers: { 'Content-Type': 'application/sdp', Authorization: 'Basic ' + Buffer.from(`${configuration.api.username}:${configuration.api.password}`).toString('base64') }, body: offer
  });
  expect(response.status).toBe(201);
  const answer = await response.text();
  await publisher.evaluate(sdp => window.publisherPC.setRemoteDescription({ type: 'answer', sdp }), answer);
  await expect.poll(() => publisher.evaluate(() => window.publisherPC.connectionState), { timeout: 15000 }).toBe('connected');
});
test.afterAll(async () => { await publisherContext?.close(); });
test.beforeEach(async ({ page }, info) => isolateCameraTest(page, info));

async function login(page, user = 'admin') {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}
async function prepare(page) {
  await login(page);
  await page.goto(cameraPath);
  await page.getByText('Preparar los cuatro canales del DVR', { exact: true }).click();
  await page.getByRole('button', { name: 'Conectar canales al DVR', exact: true }).click();
  await expect(page.locator('[data-bridge-status]')).toContainText('Puente disponible');
}
async function frames(page, expected) {
  await expect.poll(() => page.locator('[data-camera-view]:visible video').evaluateAll(videos =>
    videos.filter(video => video.videoWidth === 320 && video.readyState >= 2 && video.currentTime > 0).length), { timeout: 25000 }).toBe(expected);
}

test('RTSP real: cuatro canales, calidad principal, pausa y controles móviles', async ({ page }, info) => {
  test.setTimeout(90000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await prepare(page);
  const mobile = info.project.name === 'mobile';
  await frames(page, mobile ? 1 : 4);
  await action(page, 'single').click();
  await frames(page, 1);
  await page.locator('[data-camera-live-quality]').selectOption('main');
  await frames(page, 1);
  await expect(page.locator('[data-camera-single-diagnostic]')).toContainText('Principal');
  await action(page, 'play').click();
  await expect.poll(() => page.locator('[data-camera-single] video').evaluate(video => video.paused)).toBe(true);
  await action(page, 'play').click();
  await expect.poll(() => page.locator('[data-camera-single] video').evaluate(video => video.paused)).toBe(false);
  await expect(action(page, 'snapshot')).toBeEnabled();
  await expect(action(page, 'mute')).toBeDisabled();
  await action(page, 'grid').click();
  await frames(page, 4);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await page.screenshot({ path: `test-results/webrtc-${info.project.name}.png`, fullPage: true });
  expect(errors).toEqual([]);
});

test('WebRTC: recuperación ante fallo temporal y liberación al cambiar de vista', async ({ page }) => {
  test.setTimeout(60000);
  await prepare(page);
  await action(page, 'single').click();
  await frames(page, 1);
  let failures = 0;
  await page.route('**/admin/camera-stream.php', route => {
    if (route.request().method() === 'POST' && failures++ === 0) return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Fallo temporal de prueba.' }) });
    return route.continue();
  });
  await action(page, 'reconnect').click();
  await frames(page, 1);
  expect(failures).toBeGreaterThanOrEqual(2);
  await page.evaluate(() => { window.previousReceiver = document.querySelector('[data-camera-single] video').srcObject; });
  await action(page, 'recordings').click();
  await expect(page.getByRole('tabpanel', { name: 'Grabaciones', exact: true })).toBeVisible();
  expect(await page.evaluate(() => window.previousReceiver.getTracks().every(track => track.readyState === 'ended'))).toBe(true);
  await page.getByRole('tab', { name: 'Cámaras en vivo', exact: true }).click();
  await frames(page, 1);
});

test('WebRTC: exige sesión, administrador y CSRF, sin revelar credenciales', async ({ page, browser }) => {
  await login(page);
  const unauthorized = await page.request.post(endpoint, { data: { camera_id: 1, quality: 'sub', csrf_token: 'invalid', sdp: 'invalid' } });
  expect(unauthorized.status()).toBe(403);
  expect(await unauthorized.text()).not.toContain('rtsp://');
  const anonymous = await browser.newContext();
  const denied = await anonymous.request.get(endpoint + '?action=status');
  expect(denied.status()).toBe(401);
  await anonymous.close();
  const driver = await browser.newContext();
  const driverPage = await driver.newPage();
  await login(driverPage, 'driver');
  expect((await driver.request.get(endpoint + '?action=status')).status()).toBe(403);
  await driver.close();
});
