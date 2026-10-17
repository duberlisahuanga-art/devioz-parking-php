const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { isolateCameraTest } = require('./camera-test-scope');

const APP = '/devioz-parking-php';
const cameraPath = `${APP}/frontend/admin/camaras.php`;
const endpoint = `${APP}/frontend/admin/grabaciones.php`;
const recordingId = number => 'aaaa' + number.toString(16).padStart(28, '0');
const recordingAction = (page, name) => page.locator(`[data-recording-action="${name}"]`);
const day = (page, date) => page.locator(`[data-recording-date="${date}"]`);
const hour = (page, value) => page.locator(`[data-recording-hour="${value}"]`);
const items = page => page.locator('[data-recordings-list] [data-recording-item]');

test.beforeEach(async ({ page }, testInfo) => isolateCameraTest(page, testInfo));

async function login(page, user = 'admin') {
  await page.goto(`${APP}/frontend/login.php`);
  await page.getByLabel('Correo electrónico').fill(`${user}@browser.invalid`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
}

async function openLibrary(page) {
  await login(page);
  await page.goto(cameraPath);
  await page.locator('[data-camera-parking]').selectOption('2');
  await page.locator('[data-camera-action="recordings"]').click();
  await expect(page.locator('[data-camera-view="webcam"]')).toBeVisible();
  await expect(page.locator('[data-recordings-month]')).toHaveValue('2026-10');
  await expect(day(page, '2026-10-05')).toHaveAttribute('aria-pressed', 'true');
  await expect(items(page)).toHaveCount(2);
  await expect(page.locator('[data-camera-webcam-status]')).toHaveText('Desconectada');
}

async function chooseDay(page, date, count) {
  await day(page, date).click();
  await expect(day(page, date)).toHaveAttribute('aria-pressed', 'true');
  await expect(items(page)).toHaveCount(count);
  await expect(recordingAction(page, 'refresh')).toBeEnabled();
}

test('biblioteca: días, horas, paginación y selección conservada al recargar', async ({ page }) => {
  test.setTimeout(60000);
  await openLibrary(page);
  await expect(day(page, '2026-10-03').locator('.camera-library__day-count')).toHaveText('26');
  await expect(day(page, '2026-10-04').locator('.camera-library__day-count')).toHaveText('2');
  await expect(day(page, '2026-10-05').locator('.camera-library__day-count')).toHaveText('2');
  await chooseDay(page, '2026-10-03', 20);
  await expect(page.locator('[data-recordings-summary]')).toHaveText('Mostrando 1–20 de 26 vídeos del día.');
  await expect(hour(page, 8).locator('.camera-library__hour-count')).toHaveText('26');
  await expect(hour(page, 9).locator('.camera-library__hour-count')).toHaveText('1');
  await expect(page.locator('[data-recordings-hours] [data-recording-hour]')).toHaveCount(25);
  const firstIds = await items(page).evaluateAll(nodes => nodes.map(node => node.dataset.recordingItem));
  expect(firstIds).toEqual(Array.from({ length: 20 }, (_, index) => recordingId(index + 1)));

  await recordingAction(page, 'next-page').click();
  await expect(items(page)).toHaveCount(6);
  await expect(page.locator('[data-recordings-page]')).toHaveText('Página 2 de 2');
  await expect(page.locator('[data-recordings-summary]')).toHaveText('Mostrando 21–26 de 26 vídeos del día.');
  const secondIds = await items(page).evaluateAll(nodes => nodes.map(node => node.dataset.recordingItem));
  expect(secondIds).toEqual(Array.from({ length: 6 }, (_, index) => recordingId(index + 21)));
  expect(new Set([...firstIds, ...secondIds]).size).toBe(26);
  await expect(recordingAction(page, 'next-page')).toBeDisabled();
  await recordingAction(page, 'previous-page').click();
  await expect(items(page)).toHaveCount(20);
  await expect(recordingAction(page, 'next-page')).toBeFocused();
  await expect(page.locator('[data-recordings-page]')).toHaveText('Página 1 de 2');

  await hour(page, 9).click();
  await expect(items(page)).toHaveCount(1);
  await expect(items(page).first()).toHaveAttribute('data-recording-item', recordingId(26));
  await expect(hour(page, 9)).toHaveAttribute('aria-pressed', 'true');
  await expect(hour(page, 8).locator('.camera-library__hour-count')).toHaveText('26');
  await expect(page.locator('[data-recordings-summary]')).toContainText('en la hora 09:00');
  await expect(page.locator('[data-recordings-pagination]')).toBeHidden();
  await hour(page, 7).click();
  await expect(items(page)).toHaveCount(0);
  await expect(page.locator('[data-recordings-empty]')).toHaveText('Esta hora no tiene grabaciones guardadas.');
  await expect(page.locator('[data-recordings-empty]')).toBeVisible();

  await chooseDay(page, '2026-10-05', 2);
  await hour(page, 0).click();
  await expect(items(page)).toHaveCount(1);
  await expect(items(page).first()).toHaveAttribute('data-recording-item', recordingId(28));
  await expect(items(page).first().locator('strong')).toContainText('4/10/26');
  await expect(items(page).first().locator('p')).toContainText('5/10/26');
  await expect(page).toHaveURL(/recordings_date=2026-10-05/);
  expect(new URL(page.url()).searchParams.get('recordings_parking')).toBe('2');
  expect(new URL(page.url()).searchParams.get('recordings_hour')).toBe('0');
  await page.reload();
  await page.locator('[data-camera-action="webcam"]').click();
  await expect(page.locator('[data-camera-parking]')).toHaveValue('2');
  await expect(day(page, '2026-10-05')).toHaveAttribute('aria-pressed', 'true');
  await expect(hour(page, 0)).toHaveAttribute('aria-pressed', 'true');
  await expect(items(page)).toHaveCount(1);
  await expect(items(page).first()).toHaveAttribute('data-recording-item', recordingId(28));

  await recordingAction(page, 'previous-month').click();
  await expect(page.locator('[data-recordings-month]')).toHaveValue('2026-09');
  await expect(page.locator('[data-recordings-calendar-status]')).toHaveText('Este mes no tiene grabaciones guardadas.');
  await expect(items(page)).toHaveCount(0);
  await expect(page.locator('[data-recordings-empty]')).toBeVisible();
  await recordingAction(page, 'next-month').click();
  await expect(page.locator('[data-recordings-month]')).toHaveValue('2026-10');
  await expect(day(page, '2026-10-05')).toHaveAttribute('aria-pressed', 'true');
  await expect(items(page)).toHaveCount(2);
});

test('biblioteca: un fallo de consulta conserva el mes pedido para reintentar', async ({ page }) => {
  test.setTimeout(60000);
  await openLibrary(page);
  let attempts = 0;
  await page.route('**/frontend/admin/grabaciones.php**', async route => {
    const url = new URL(route.request().url());
    if (url.searchParams.get('action') === 'calendar' && url.searchParams.get('month') === '2026-09') {
      attempts++;
      if (attempts === 1) {
        await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Consulta temporalmente no disponible.' }) });
        return;
      }
    }
    await route.continue();
  });
  await recordingAction(page, 'previous-month').click();
  await expect(page.locator('[data-recordings-calendar-error]')).toContainText('Consulta temporalmente no disponible');
  await expect(page.locator('[data-recordings-month]')).toHaveValue('2026-09');
  await expect(page.locator('[data-recordings-days] [data-recording-date]')).toHaveCount(0);
  await expect(page.locator('[data-recordings-empty]')).toBeHidden();
  await recordingAction(page, 'refresh').click();
  await expect(page.locator('[data-recordings-month]')).toHaveValue('2026-09');
  await expect(page.locator('[data-recordings-calendar-status]')).toHaveText('Este mes no tiene grabaciones guardadas.');
  await expect(page.locator('[data-recordings-days] [data-recording-date]')).toHaveCount(30);
  expect(attempts).toBe(2);
});

test('biblioteca: valida consultas HTTP y limita el acceso a administradores', async ({ page }) => {
  test.setTimeout(60000);
  await login(page);
  const calendar = await page.request.get(`${endpoint}?action=calendar&parking_id=2&month=2026-10`);
  expect(calendar.status()).toBe(200);
  const data = await calendar.json();
  expect(data.timezone).toBe('America/Lima');
  expect(data.days).toEqual([{ date: '2026-10-03', count: 26 }, { date: '2026-10-04', count: 2 }, { date: '2026-10-05', count: 2 }]);
  for (const query of [
    'action=calendar&parking_id=2&month=2026-13',
    'action=calendar&parking_id=2&month[]=2026-10',
    'action=calendar&parking_id[]=2&month=2026-10',
    'action[]=calendar&parking_id=2&month=2026-10',
    'action=list&parking_id=2&date=2026-02-30',
    'action=list&parking_id=2&date[]=2026-10-03',
    'action=list&parking_id=2&date=2026-10-03&hour=24',
    'action=list&parking_id=2&date=2026-10-03&hour[]=9',
    'action=list&parking_id=2&date=2026-10-03&page=0',
    'action=list&parking_id=2&date=2026-10-03&page[]=1',
    'action=list&parking_id=2&hour=9',
  ]) {
    const response = await page.request.get(`${endpoint}?${query}`);
    expect(response.status(), query).toBe(400);
    expect((await response.json()).error, query).toEqual(expect.any(String));
  }
  await page.context().clearCookies();
  await login(page, 'driver');
  for (const query of ['action=calendar&parking_id=2&month=2026-10', 'action=list&parking_id=2&date=2026-10-03']) {
    const response = await page.request.get(`${endpoint}?${query}`);
    expect(response.status()).toBe(403);
    expect((await response.json()).error).toContain('administrador');
  }
  await page.context().clearCookies();
  for (const action of ['calendar', 'list']) {
    const response = await page.request.get(`${endpoint}?action=${action}&parking_id=2&date=2026-10-03`, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers().location).toContain('/frontend/login.php');
  }
});

test('biblioteca: calendario real, teclado, temas y accesibilidad sin desbordamiento', async ({ page }, testInfo) => {
  test.setTimeout(60000);
  await openLibrary(page);
  const days = page.locator('[data-recordings-days]');
  expect(await days.evaluate(node => [...node.children].slice(0, 3).every(child => child.classList.contains('camera-library__day-placeholder')))).toBe(true);
  expect(await days.evaluate(node => node.children[3].dataset.recordingDate)).toBe('2026-10-01');
  await expect(days.locator('[data-recording-date]')).toHaveCount(31);
  await day(page, '2026-10-03').focus();
  await expect(day(page, '2026-10-03')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(day(page, '2026-10-03')).toHaveAttribute('aria-pressed', 'true');
  await expect(items(page)).toHaveCount(20);
  await expect(day(page, '2026-10-03')).toBeFocused();
  await chooseDay(page, '2026-10-05', 2);
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => {
      localStorage.setItem('devioz_theme', value);
      document.documentElement.dataset.theme = value;
      document.documentElement.dataset.bsTheme = value;
    }, theme);
    const audit = await new AxeBuilder({ page }).include('[data-camera-module]').analyze();
    expect(audit.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`library-${theme}.png`), fullPage: true });
    await page.locator('.camera-library__calendar').screenshot({ path: testInfo.outputPath(`library-calendar-${theme}.png`) });
  }
  const month = page.locator('[data-recordings-month]');
  await month.fill('2024-02');
  await month.press('Tab');
  await expect(day(page, '2024-02-29')).toBeVisible();
  await expect(days.locator('[data-recording-date]')).toHaveCount(29);
  await expect(items(page)).toHaveCount(0);
  await month.fill('2026-02');
  await month.press('Tab');
  await expect(day(page, '2026-02-28')).toBeVisible();
  await expect(day(page, '2026-02-29')).toHaveCount(0);
  await expect(days.locator('[data-recording-date]')).toHaveCount(28);
  await expect(page.locator('[data-recordings-calendar-status]')).toHaveText('Este mes no tiene grabaciones guardadas.');
});
