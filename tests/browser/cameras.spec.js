const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { isolateCameraTest } = require('./camera-test-scope');

const cameraPath = '/devioz-parking-php/frontend/admin/camaras.php';
const action = (page, name) => page.locator(`[data-camera-action="${name}"]`).filter({ visible: true });

test.beforeEach(async ({ page }, testInfo) => isolateCameraTest(page, testInfo));

async function login(page, user = 'admin') {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}

async function saveCamera(page, form) {
  // Wait for the new document before choosing a view; the mobile default differs.
  await Promise.all([
    page.waitForEvent('domcontentloaded'),
    form.getByRole('button', { name: 'Guardar cámara', exact: true }).click()
  ]);
}

async function prepareSyntheticCamera(page) {
  // Exercise real MediaStream/video/canvas behavior without requesting a physical device.
  await page.addInitScript(() => {
    const probe = { requests: [], streams: [], mode: 'success', pending: [] };
    const makeStream = () => {
      const canvas = document.createElement('canvas');
      canvas.width = 320;
      canvas.height = 180;
      const context = canvas.getContext('2d');
      let frame = 0;
      const paint = () => {
        context.fillStyle = frame++ % 2 ? '#2563eb' : '#1d4ed8';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#ffffff';
        context.font = '24px sans-serif';
        context.fillText(`Cámara de prueba ${frame}`, 12, 90);
      };
      paint();
      const stream = canvas.captureStream(12);
      probe.streams.push(stream);
      const timer = setInterval(() => {
        if (stream.getTracks().every(track => track.readyState === 'ended')) clearInterval(timer);
        else paint();
      }, 80);
      return stream;
    };
    const getUserMedia = constraints => {
      probe.requests.push(JSON.parse(JSON.stringify(constraints)));
      if (probe.mode === 'pending') {
        return new Promise((resolve, reject) => probe.pending.push({
          resolve: () => resolve(makeStream()),
          reject: name => reject(new DOMException('Synthetic camera failure', name))
        }));
      }
      if (probe.mode !== 'success') return Promise.reject(new DOMException('Synthetic camera failure', probe.mode));
      return Promise.resolve(makeStream());
    };
    if (!navigator.mediaDevices) Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: {} });
    Object.defineProperty(navigator.mediaDevices, 'getUserMedia', { configurable: true, value: getUserMedia });
    window.__cameraProbe = probe;
  });
}

async function connectCamera(page, expectedCount) {
  await action(page, 'webcam-connect').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Conectada');
  await expect.poll(() => page.evaluate(() => window.__cameraProbe.requests.length)).toBe(expectedCount);
  await expect.poll(() => page.locator('[data-camera-webcam-video]').evaluate(video => video.videoWidth > 0 && video.readyState >= 2)).toBe(true);
}

async function expectAllTracksStopped(page, expectedStreams) {
  await expect.poll(() => page.evaluate(() => ({
    streams: window.__cameraProbe.streams.length,
    stopped: window.__cameraProbe.streams.every(stream => stream.getTracks().every(track => track.readyState === 'ended'))
  }))).toEqual({ streams: expectedStreams, stopped: true });
}

test('cámaras: webcam por permiso, captura, pausa y liberación del dispositivo', async ({ page }, testInfo) => {
  test.setTimeout(60000);
  await prepareSyntheticCamera(page);
  await login(page);
  await page.goto(cameraPath);
  await expect.poll(() => page.evaluate(() => window.__cameraProbe.requests.length)).toBe(0);
  await action(page, 'webcam').focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('[data-camera-view="webcam"]')).toBeVisible();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');
  await expect(action(page, 'webcam-snapshot')).toBeDisabled();
  await expect(action(page, 'webcam-play')).toBeDisabled();
  await expect.poll(() => page.evaluate(() => window.__cameraProbe.requests.length)).toBe(0);

  await connectCamera(page, 1);
  expect(await page.evaluate(() => window.__cameraProbe.requests[0].audio)).toBe(false);
  await expect(action(page, 'webcam-connect')).toBeDisabled();
  await expect(action(page, 'webcam-disconnect')).toBeEnabled();
  await expect(action(page, 'webcam-snapshot')).toBeEnabled();
  await expect(action(page, 'webcam-fullscreen')).toBeEnabled();
  const downloadReady = page.waitForEvent('download');
  await action(page, 'webcam-snapshot').click();
  const download = await downloadReady;
  expect(download.suggestedFilename()).toMatch(/\.png$/i);
  const capture = await download.createReadStream();
  const chunks = [];
  for await (const chunk of capture) chunks.push(chunk);
  const image = Buffer.concat(chunks);
  expect(image.subarray(0, 8).toString('hex')).toBe('89504e470d0a1a0a');
  expect(image.length).toBeGreaterThan(100);

  await action(page, 'webcam-play').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Imagen pausada');
  await expect(action(page, 'webcam-play')).toHaveText(/Reproducir/);
  expect(await page.evaluate(() => window.__cameraProbe.streams[0].getVideoTracks()[0].readyState)).toBe('live');
  await action(page, 'webcam-play').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Conectada');
  await expect(action(page, 'webcam-play')).toHaveText(/Pausar imagen/);

  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => {
      localStorage.setItem('devioz_theme', value);
      document.documentElement.dataset.theme = value;
      document.documentElement.dataset.bsTheme = value;
    }, theme);
    expect((await new AxeBuilder({ page }).include('[data-camera-module]')
      .withRules(['label', 'button-name', 'color-contrast']).analyze()).violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/cameras-webcam-${testInfo.project.name}-${theme}.png`, fullPage: true });
  }

  await action(page, 'webcam-disconnect').click();
  await expectAllTracksStopped(page, 1);
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');
  expect(await page.locator('[data-camera-webcam-video]').evaluate(video => video.srcObject)).toBeNull();
  await connectCamera(page, 2);
  await action(page, 'grid').first().click();
  await expect(page.locator('[data-camera-view="grid"]')).toBeVisible();
  await expectAllTracksStopped(page, 2);
  await action(page, 'webcam').click();
  await connectCamera(page, 3);
  await page.evaluate(() => {
    const track = window.__cameraProbe.streams[2].getVideoTracks()[0];
    track.stop();
    // stop() is silent; a device removal also emits ended.
    track.dispatchEvent(new Event('ended'));
  });
  await expectAllTracksStopped(page, 3);
  await expect(page.locator('[data-camera-webcam-error]')).toBeVisible();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');
  await expect(action(page, 'webcam-connect')).toBeEnabled();
  expect(await page.locator('[data-camera-webcam-video]').evaluate(video => video.srcObject)).toBeNull();
  await connectCamera(page, 4);
  await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
  await expectAllTracksStopped(page, 4);
});

test('cámaras: errores de permiso y cancelación de una respuesta tardía', async ({ page }) => {
  await prepareSyntheticCamera(page);
  await login(page);
  await page.goto(cameraPath);
  await action(page, 'webcam').click();
  const webcamError = page.locator('[data-camera-webcam-error]');
  for (const [error, message] of [
    ['NotAllowedError', /permiso/i],
    ['NotFoundError', /no se encontró|no hay.*cámara|no.*encontr/i],
    ['NotReadableError', /otra aplicación|ocupada|no se pudo abrir/i]
  ]) {
    await page.evaluate(value => { window.__cameraProbe.mode = value; }, error);
    await action(page, 'webcam-connect').click();
    await expect(webcamError).toBeVisible();
    await expect(webcamError).toContainText(message);
    await expect(page.locator('[data-camera-webcam-status]')).toContainText('Sin conexión');
    await expect(action(page, 'webcam-connect')).toBeEnabled();
    await expect(action(page, 'webcam-snapshot')).toBeDisabled();
  }
  await page.evaluate(() => { window.__cameraProbe.mode = 'pending'; });
  await action(page, 'webcam-connect').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Solicitando permiso');
  await expect(webcamError).toBeHidden();
  await expect(action(page, 'webcam-connect')).toBeDisabled();
  await action(page, 'webcam-disconnect').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');
  await page.evaluate(() => window.__cameraProbe.pending.shift().resolve());
  await expectAllTracksStopped(page, 1);
  expect(await page.locator('[data-camera-webcam-video]').evaluate(video => video.srcObject)).toBeNull();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');

  await action(page, 'webcam-connect').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Solicitando permiso');
  await action(page, 'grid').first().click();
  await expect(page.locator('[data-camera-view="grid"]')).toBeVisible();
  await page.evaluate(() => window.__cameraProbe.pending.shift().resolve());
  await expectAllTracksStopped(page, 2);
  expect(await page.locator('[data-camera-webcam-video]').evaluate(video => video.srcObject)).toBeNull();
  await action(page, 'webcam').click();
  await expect(page.locator('[data-camera-webcam-status]')).toContainText('Desconectada');
  await expect(action(page, 'webcam-connect')).toBeEnabled();
});

test('cámaras: canales inactivos y sin fuente no ofrecen reproducción ni captura', async ({ page }, testInfo) => {
  await login(page);
  await page.goto(cameraPath);
  await action(page, 'manage').click();
  await action(page, 'create').click();
  const form = page.locator('[data-camera-form]');
  const name = `Canal de prueba ${testInfo.project.name}`;
  await form.getByLabel('Nombre', { exact: true }).fill(name);
  await form.locator('[name="channel_number"]').selectOption(testInfo.project.name === 'desktop' ? '1' : '2');
  await form.getByRole('combobox', { name: 'Estado', exact: true }).selectOption('inactive');
  await page.locator('[data-camera-tab="stream"]').click();
  const protocol = form.locator('[name="stream_protocol"]');
  await expect(protocol.locator('option[value="webrtc"]')).toHaveJSProperty('disabled', false);
  await protocol.selectOption('hls');
  await expect(protocol).toHaveValue('hls');
  await saveCamera(page, form);
  await action(page, 'grid').first().click();
  const card = page.locator('[data-camera-grid] .camera-channel').filter({ hasText: name });
  await expect(card).toContainText('Inactiva');
  await card.getByRole('button', { name: 'Ampliar', exact: true }).click();
  await expect(action(page, 'play')).toBeDisabled();
  await expect(action(page, 'snapshot')).toBeDisabled();
  await expect(page.locator('[data-camera-quality-info]')).toBeVisible();
  await expect(page.locator('[data-camera-quality]')).toHaveCount(0);

  await action(page, 'settings').click();
  await form.getByRole('combobox', { name: 'Estado', exact: true }).selectOption('active');
  await saveCamera(page, form);
  await action(page, 'grid').first().click();
  await card.getByRole('button', { name: 'Ampliar', exact: true }).click();
  await expect(page.locator('[data-camera-single]')).toContainText(/Stream no configurado|Fuente no configurada/);
  await expect(action(page, 'play')).toBeDisabled();
  await expect(action(page, 'snapshot')).toBeDisabled();

  // A configured WebRTC camera keeps its protocol when editing other fields.
  await action(page, 'settings').click();
  const legacyValues = await form.evaluate(node => Object.fromEntries(new FormData(node)));
  const legacyResponse = await page.request.post(cameraPath, { form: { ...legacyValues, stream_protocol: 'webrtc' } });
  expect(legacyResponse.ok()).toBe(true);
  await page.goto(cameraPath);
  await action(page, 'grid').first().click();
  await card.getByRole('button', { name: 'Ampliar', exact: true }).click();
  await action(page, 'settings').click();
  await expect(form.locator('[name="stream_protocol"]')).toHaveValue('webrtc');
  await form.getByLabel('Ubicación', { exact: true }).fill('Ubicación actualizada');
  await saveCamera(page, form);
  await action(page, 'grid').first().click();
  await expect(card).toContainText('Ubicación actualizada');
  const stored = await page.locator('[data-camera-data]').evaluate(node => JSON.parse(node.textContent));
  expect(stored.find(camera => camera.name === name).stream_protocol).toBe('webrtc');
});

test('cámaras: acceso administrativo y cambios protegidos por CSRF', async ({ page }) => {
  await login(page, 'driver');
  expect((await page.request.get(cameraPath)).status()).toBe(403);
  await page.context().clearCookies();
  await login(page);
  await page.goto(cameraPath);
  const rejected = await page.request.post(cameraPath, {
    form: { action: 'create', csrf_token: 'invalid', name: 'No debe guardarse', parking_id: '1', channel_number: '4' }
  });
  expect(await rejected.text()).toContain('Token CSRF');
  await page.reload();
  await expect(page.locator('[data-camera-grid]')).not.toContainText('No debe guardarse');
});
