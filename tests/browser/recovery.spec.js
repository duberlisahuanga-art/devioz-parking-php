const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');
const AxeBuilder = require('@axe-core/playwright').default;
const fixture = mode => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, '../recovery-browser-fixture.php'), mode], { encoding: 'utf8' }).trim();

test('recuperación: formularios, CSRF, enlace único y contraseña nueva', async ({ page }, testInfo) => {
  try {
    await page.goto('/devioz-parking-php/frontend/login.php');
    await page.getByRole('link', { name: 'Olvidé mi contraseña' }).click();
    const denied = await page.request.post('/devioz-parking-php/frontend/recuperar-password.php', { form: { email: 'driver@browser.invalid' } });
    expect(denied.status()).toBe(400);
    await page.getByLabel('Correo electrónico').fill('nobody@browser.invalid');
    await page.getByRole('button', { name: 'Enviar enlace' }).click();
    await expect(page.getByRole('status')).toContainText('Si el correo corresponde');
    const token = fixture('issue');
    expect(token).toMatch(/^[a-f0-9]{64}$/);
    await page.goto(`/devioz-parking-php/frontend/restablecer-password.php?token=${token}`);
    await expect(page).toHaveURL(/restablecer-password\.php$/);
    for (const theme of ['light', 'dark']) {
      await page.evaluate(value => localStorage.setItem('devioz_theme', value), theme);
      await page.reload();
      expect((await new AxeBuilder({ page }).include('main').withRules(['label','button-name','color-contrast']).analyze()).violations).toEqual([]);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await page.screenshot({ path: `test-results/recovery-${testInfo.project.name}-${theme}.png`, fullPage: true });
    }
    await page.getByLabel('Contraseña nueva', { exact: true }).fill('Recovered123!');
    await page.getByLabel('Confirmar contraseña').fill('Different123!');
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(page.getByRole('alert')).toContainText('no coinciden');
    await page.getByLabel('Contraseña nueva', { exact: true }).fill('Recovered123!');
    await page.getByLabel('Confirmar contraseña').fill('Recovered123!');
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(page.getByRole('status')).toContainText('Contraseña actualizada');
    await page.getByRole('link', { name: 'Volver a iniciar sesión' }).click();
    await page.getByLabel('Correo electrónico').fill('driver@browser.invalid');
    await page.getByLabel('Contraseña', { exact: true }).fill('Recovered123!');
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await expect(page).toHaveURL(/conductor\/dashboard\.php/);
  } finally { fixture('clean'); }
});
