const { chromium } = require('playwright');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({ headless: true });
  fs.mkdirSync('test-results', { recursive: true });
  try {
    for (const [name, width, height] of [['desktop', 1440, 900], ['mobile', 390, 844]]) {
      const page = await browser.newPage({ viewport: { width, height } });
      await page.goto('http://localhost/devioz-parking-php/frontend/index.php');
      await page.waitForFunction(() => getComputedStyle(document.querySelector('.landing-hero h1')).opacity === '1');
      await page.screenshot({ path: `test-results/landing-${name}.png` });
      await page.close();
    }
    console.log('Capturas de landing desktop/mobile guardadas.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
