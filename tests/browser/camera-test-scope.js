const { createHash } = require('node:crypto');

// Keep the real rate-limit policy, but give each independent case its own files.
async function isolateCameraTest(page, testInfo) {
  const caseId = createHash('sha256').update(testInfo.testId).digest('hex').slice(0, 12);
  await page.context().setExtraHTTPHeaders({ 'X-Devioz-Test-Scope': `${testInfo.project.name}-${caseId}` });
  await page.route('https://cdn.jsdelivr.net/**', async route => {
    const headers = { ...route.request().headers() };
    delete headers['x-devioz-test-scope'];
    await route.continue({ headers });
  });
}

module.exports = { isolateCameraTest };
