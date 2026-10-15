const { defineConfig } = require('@playwright/test');
const php = process.env.PHP_BIN || 'php';
module.exports = defineConfig({
  testDir: './tests/browser', workers: 1, retries: 0,
  use: { baseURL: 'http://127.0.0.1:8099/devioz-parking-php', browserName: 'chromium' },
  webServer: { command: `"${php}" -S 127.0.0.1:8099 tests/router.php`, url: 'http://127.0.0.1:8099/devioz-parking-php/frontend/login.php', reuseExistingServer: false },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 }, extraHTTPHeaders: { 'X-Devioz-Test-Scope': 'desktop' } } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 }, extraHTTPHeaders: { 'X-Devioz-Test-Scope': 'mobile' } } },
  ],
});
