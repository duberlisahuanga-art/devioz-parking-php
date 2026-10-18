const { test, expect } = require('@playwright/test');
const { createHash } = require('node:crypto');
const AxeBuilder = require('@axe-core/playwright').default;

const APP = '/devioz-parking-php';

test.beforeEach(async ({ page }, testInfo) => {
  const scope = createHash('sha256').update(testInfo.testId).digest('hex').slice(0, 12);
  await page.context().setExtraHTTPHeaders({ 'X-Devioz-Test-Scope': `${testInfo.project.name}-${scope}` });
  await page.route('https://cdn.jsdelivr.net/**', async route => {
    const headers = { ...route.request().headers() };
    delete headers['x-devioz-test-scope'];
    await route.continue({ headers });
  });
});

for (const role of ['admin', 'driver']) {
  test(`${role}: identidad visible y cierre desde la portada`, async ({ page, browser }, testInfo) => {
    await page.goto(`${APP}/frontend/login.php`);
    await page.getByLabel('Correo electrónico').fill(`${role}@browser.invalid`);
    await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    const dashboard = `${APP}/frontend/${role === 'admin' ? 'admin' : 'conductor'}/dashboard.php`;
    await expect(page).toHaveURL(new RegExp(dashboard.replace('.', '\\.') + '$'));
    await page.goto(`${APP}/frontend/index.php`);

    if (testInfo.project.name === 'mobile') {
      await page.getByRole('button', { name: 'Abrir menú' }).click();
    }
    const session = page.locator('[data-landing-session]').filter({ visible: true });
    await expect(session).toContainText(`Sesión iniciada: Prueba ${role}`);
    await expect(page.locator('.js-open-auth')).toHaveCount(0);
    const logout = page.getByRole('button', { name: 'Cerrar sesión', exact: true });
    await expect(logout).toBeVisible();
    const panel = page.getByRole('navigation').getByRole('link', { name: 'Ir a mi panel', exact: true }).filter({ visible: true });
    await expect(panel).toHaveAttribute('href', dashboard);

    // Both color modes retain usable controls within the viewport.
    for (const theme of ['light', 'dark']) {
      await page.evaluate(value => document.documentElement.setAttribute('data-theme', value), theme);
      const bounds = await logout.boundingBox();
      expect(bounds.height).toBeGreaterThanOrEqual(44);
      expect(bounds.x).toBeGreaterThanOrEqual(0);
      expect(bounds.x + bounds.width).toBeLessThanOrEqual(page.viewportSize().width);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      const accessibility = await new AxeBuilder({ page })
        .include('[data-landing-session]')
        .include('.landing-mobile-auth form')
        .withRules(['color-contrast', 'button-name', 'aria-valid-attr'])
        .analyze();
      expect(accessibility.violations).toEqual([]);
      await page.screenshot({ path: `test-results/landing-session-${role}-${testInfo.project.name}-${theme}.png` });
    }

    const endpoint = `${APP}/backend/auth/logout.php`;
    expect((await page.request.get(endpoint, { maxRedirects: 0 })).status()).toBe(405);
    expect((await page.request.post(endpoint, { form: { csrf_token: 'invalid' }, maxRedirects: 0 })).status()).toBe(403);
    expect((await page.request.get(dashboard, { maxRedirects: 0 })).status()).toBe(200);

    // Replaying the old cookie after logout must not restore the session.
    const oldSession = await page.context().storageState();
    await logout.focus();
    await expect(logout).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp(`${APP}/frontend/index\\.php$`));
    await expect(page.locator('[data-landing-session]')).toHaveCount(0);
    if (testInfo.project.name === 'mobile') {
      await page.getByRole('button', { name: 'Abrir menú' }).click();
    }
    const navigation = page.getByRole('navigation');
    await expect(navigation.getByRole('button', { name: 'Iniciar sesión', exact: true }).filter({ visible: true })).toBeVisible();
    await expect(navigation.getByRole('button', { name: 'Crear cuenta', exact: true }).filter({ visible: true })).toBeVisible();
    expect((await page.request.get(dashboard, { maxRedirects: 0 })).status()).toBe(302);
    const replay = await browser.newContext({ storageState: oldSession });
    try {
      const response = await replay.request.get(`http://127.0.0.1:8099${dashboard}`, { maxRedirects: 0 });
      expect(response.status()).toBe(302);
    } finally {
      await replay.close();
    }
  });
}
