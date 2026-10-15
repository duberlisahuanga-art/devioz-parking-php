const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('catálogo local, entrada manual, teclado y temas', async ({ page }, testInfo) => {
  const api = '/devioz-parking-php/frontend/api/vehicle-catalog.php';
  expect((await page.request.get(api)).status()).toBe(401);
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('driver@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
  await page.goto('/devioz-parking-php/frontend/conductor/vehiculos.php');
  const brand = page.getByLabel('Marca', { exact: true });
  const model = page.getByLabel('Modelo', { exact: true });
  const type = page.getByLabel('Tipo', { exact: true });
  await expect(page.locator('#vehicle-model-hint')).toContainText('Selecciona primero');
  for (const theme of ['light', 'dark']) {
    await page.evaluate(theme => {
      localStorage.setItem('devioz_theme', theme);
    }, theme);
    await page.reload();
    await brand.fill('Toy');
    await expect(page.getByRole('option', { name: 'TOYOTA', exact: true })).toBeVisible();
    const accessible = await new AxeBuilder({ page }).include('[data-vehicle-catalog]')
      .withRules(['color-contrast', 'label', 'aria-valid-attr', 'aria-required-attr', 'aria-required-children']).analyze();
    expect(accessible.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: `test-results/catalog-${theme}-${testInfo.project.name}.png`, fullPage: true });
    await brand.press('ArrowDown');
    await brand.press('Enter');
    await expect(brand).toHaveValue('TOYOTA');
    await expect(model).toBeFocused();
    await model.fill('Cor');
    await expect(page.getByRole('option', { name: 'Corolla', exact: true })).toBeVisible();
    await expect(page.getByRole('option', { name: 'Corolla Cross', exact: true })).toBeVisible();
    await page.getByRole('option', { name: 'Corolla', exact: true }).click();
    await expect(model).toHaveValue('Corolla');
    await brand.fill('Hon');
    await expect(model).toHaveValue('');
    await expect(page.getByRole('option', { name: 'HONDA', exact: true })).toBeVisible();
    await page.getByRole('option', { name: 'HONDA', exact: true }).click();
    await model.fill('Corolla');
    await expect(page.locator('#vehicle-model-status')).toContainText('Sin coincidencias');
    await expect(page.getByRole('option', { name: 'Corolla', exact: true })).toHaveCount(0);
    await type.selectOption('moto');
    await expect(brand).toHaveValue('');
    await expect(model).toHaveValue('');
    await expect(page.locator('#vehicle-catalog-hint')).toContainText('no incluye motocicletas');
    await brand.fill('Toyota');
    await page.waitForTimeout(350);
    await expect(page.locator('#vehicle-brand-list')).toBeHidden();
    await type.selectOption('car');
  }
  const invalid = await page.request.get(`${api}?q[]=toy`);
  expect(invalid.status()).toBe(422);
  expect((await page.request.get(`${api}?kind=models&make_id=-1`)).status()).toBe(422);
  expect((await page.request.post(api)).status()).toBe(405);
  expect((await (await page.request.get(`${api}?type=moto&q=toy`)).json()).items).toEqual([]);
  for (const vehicleType of ['car', 'moto']) {
    await type.selectOption(vehicleType);
    if (vehicleType === 'car') await page.getByLabel('Mi vehículo no aparece en el catálogo', { exact: true }).check();
    await brand.fill('Marca propia');
    await model.fill('Modelo propio');
    await page.getByLabel('Color', { exact: true }).fill('Blanco');
    const plates = { desktop: { car: 'CAT-101', moto: 'MOT-101' }, mobile: { car: 'CAT-102', moto: 'MOT-102' } };
    const plate = plates[testInfo.project.name][vehicleType];
    await page.getByLabel('Placa', { exact: true }).fill(plate);
    await page.getByRole('button', { name: 'Registrar vehículo', exact: true }).click();
    await expect(page.locator('.vehicle-card').filter({ hasText: plate })).toContainText('Marca propia Modelo propio');
  }
  // Also check the dropdown at tablet width and with enlarged content.
  await page.setViewportSize({ width: 768, height: 1024 });
  await type.selectOption('car');
  await brand.fill('Toy');
  await expect(page.getByRole('option', { name: 'TOYOTA', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.locator('body').evaluate(element => { element.style.zoom = '2'; });
  await expect(page.getByRole('option', { name: 'TOYOTA', exact: true })).toBeVisible();
  const box = await page.locator('#vehicle-brand-list').boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(768);
});

test('fallback sin catálogo y respuestas antiguas', async ({ page }) => {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('other@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await page.goto('/devioz-parking-php/frontend/conductor/vehiculos.php');
  await page.route('**/api/vehicle-catalog.php?**', async route => {
    await route.fulfill({ status: 503, contentType: 'application/json', body: '{"error":"Unavailable"}' });
  });
  await page.getByLabel('Marca', { exact: true }).fill('Sin catálogo');
  await expect(page.locator('#vehicle-brand-status')).toContainText('activa la opción de entrada manual');
  await page.getByLabel('Modelo', { exact: true }).fill('Modelo manual');
  await expect(page.getByLabel('Modelo', { exact: true })).toHaveValue('Modelo manual');
  await page.unroute('**/api/vehicle-catalog.php?**');
  await page.route('**/api/vehicle-catalog.php?**', async route => {
    const q = new URL(route.request().url()).searchParams.get('q');
    if (q === 'Toy') {
      await new Promise(resolve => setTimeout(resolve, 800));
      await route.fulfill({ contentType: 'application/json', body: '{"items":[{"id":1,"name":"TOYOTA"}]}' });
    } else await route.continue();
  });
  const brand = page.getByLabel('Marca', { exact: true });
  await brand.fill('Toy');
  await page.waitForTimeout(350);
  await brand.fill('Hon');
  await expect(page.getByRole('option', { name: 'HONDA', exact: true })).toBeVisible();
  await page.waitForTimeout(850);
  await expect(page.getByRole('option', { name: 'TOYOTA', exact: true })).toHaveCount(0);
  await brand.press('Escape');
  await expect(page.locator('#vehicle-brand-list')).toBeHidden();
});

test('prioridad Perú, rechazo en servidor y placas alfanuméricas', async ({ page }, testInfo) => {
  await page.goto('/devioz-parking-php/frontend/login.php');
  await page.getByLabel('Correo electrónico').fill('driver@browser.invalid');
  await page.getByLabel('Contraseña', { exact: true }).fill('Testing123!');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await expect(page).toHaveURL(/dashboard\.php/);
  const path = '/devioz-parking-php/frontend/conductor/vehiculos.php';
  await page.goto(path);
  const brand = page.getByLabel('Marca', { exact: true });
  await brand.focus();
  await expect(page.locator('#vehicle-brand-list [role=option]').first()).toHaveText('TOYOTA');
  await expect(page.locator('#vehicle-brand-list [role=option]').nth(1)).toHaveText('KIA');
  await expect(page.locator('#vehicle-brand-list [role=option]').nth(2)).toHaveText('HYUNDAI');
  await brand.press('Escape');
  for (const [model, error] of [['Kia', 'El modelo indicado es una marca.'], ['Sportage', 'El modelo no corresponde']]) {
    const csrf = await page.locator('[data-vehicle-catalog] [name=csrf_token]').inputValue();
    await page.request.post(path, { form: { csrf_token: csrf, action: 'create', plate: 'INV-123',
      brand: 'Toyota', model, color: 'Blanco', type: 'car', catalog_manual: '1' }, maxRedirects: 0 });
    await page.goto(path);
    await expect(page.locator('body')).toContainText(error);
    await expect(page.locator('.vehicle-card').filter({ hasText: 'INV-123' })).toHaveCount(0);
  }
  for (const plate of testInfo.project.name === 'desktop' ? ['F3V-802', 'NEW1234'] : ['F3V-803', 'NEW1235']) {
    await brand.fill('Toyota');
    await page.getByLabel('Modelo', { exact: true }).fill('Corolla');
    await page.getByLabel('Color', { exact: true }).fill('Blanco');
    await page.getByLabel('Placa', { exact: true }).fill(plate);
    await page.getByRole('button', { name: 'Registrar vehículo', exact: true }).click();
    await expect(page.locator('.vehicle-card').filter({ hasText: plate })).toContainText('TOYOTA Corolla');
  }
});
