/* Isolated RTSP -> go2rtc -> PHP -> real browser WebRTC test. No physical DVR. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const crypto = require('node:crypto');
const { spawn, spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BIN || 'C:\\xampp\\php\\php.exe';
const state = path.join(root, 'backend/storage/browser-fixture.json');
const configurationPath = path.join(root, `backend/storage/webrtc-test-${crypto.randomBytes(6).toString('hex')}.json`);
const runtime = path.join(root, 'tools/runtime/go2rtc-v1.9.14');
const originalState = fs.existsSync(state) ? fs.readFileSync(state) : null;
let bridge;
let created = false;
let detachedState = false;
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function port() {
  const server = net.createServer();
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
  const value = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return value;
}
function fixture(action) {
  const result = spawnSync(php, ['tools/browser-fixture.php', action], { cwd: root, stdio: 'inherit', windowsHide: true });
  if (result.status !== 0) throw new Error(`Falló fixture ${action}`);
}
async function main() {
  const manifest = JSON.parse(fs.readFileSync(path.join(runtime, 'verified.json'), 'utf8').replace(/^\uFEFF/, ''));
  const executable = path.join(runtime, 'go2rtc.exe');
  if (crypto.createHash('sha256').update(fs.readFileSync(executable)).digest('hex') !== manifest.sha256) throw new Error('Ejecutable no verificado');
  const [api, rtsp, media] = await Promise.all([port(), port(), port()]);
  const configuration = {
    api: { listen: `127.0.0.1:${api}`, username: 'fixture', password: crypto.randomBytes(32).toString('hex'), local_auth: true },
    rtsp: { listen: `127.0.0.1:${rtsp}` },
    webrtc: { listen: `127.0.0.1:${media}`, candidates: [`127.0.0.1:${media}`], ice_servers: [], filters: { loopback: true } },
    log: { level: 'error' }, streams: { fixture: [] }, devioz: { dvr_host: '127.0.0.1' }
  };
  for (let channel = 1; channel <= 4; channel++) for (const quality of ['sub', 'main']) {
    configuration.streams[`dvr_ch${channel}_${quality}`] = [`rtsp://127.0.0.1:${rtsp}/fixture`];
  }
  fs.writeFileSync(configurationPath, JSON.stringify(configuration));
  bridge = spawn(executable, ['-config', configurationPath], { cwd: root, stdio: 'ignore', windowsHide: true });
  let launchError;
  bridge.on('error', error => { launchError = error; });
  const authorization = 'Basic ' + Buffer.from(`${configuration.api.username}:${configuration.api.password}`).toString('base64');
  let ready = false;
  for (let attempt = 0; attempt < 50; attempt++) {
    if (launchError || bridge.exitCode !== null) throw new Error('No se inició el puente aislado');
    try { ready = (await fetch(`http://${configuration.api.listen}/api`, { headers: { authorization }, signal: AbortSignal.timeout(800) })).ok; } catch {}
    if (ready) break;
    await sleep(100);
  }
  if (!ready) throw new Error('Puente aislado no disponible');
  if (originalState) { fs.unlinkSync(state); detachedState = true; }
  fixture('create');
  created = true;
  const argumentsList = process.argv.slice(2);
  const regressions = argumentsList.includes('--regression');
  const selected = regressions ? ['cameras.spec.js', 'camera-recordings.spec.js', 'camera-library.spec.js'] : ['camera-webrtc.spec.js'];
  const test = spawn(process.execPath, [require.resolve('@playwright/test/cli'), 'test', ...selected, ...argumentsList.filter(value => value !== '--regression')], {
    cwd: root, stdio: 'inherit', windowsHide: true,
    env: { ...process.env, PHP_BIN: php, CAMERA_BRIDGE_CONFIG: configurationPath, CAMERA_ICE_CONFIG: configurationPath + '.ice.json', DEVIOZ_WEBRTC_TEST_CONFIG: configurationPath }
  });
  process.exitCode = await new Promise((resolve, reject) => { test.on('error', reject); test.on('exit', code => resolve(code ?? 1)); });
}
main().catch(error => { console.error(error.message); process.exitCode = 1; }).finally(async () => {
  if (bridge && bridge.exitCode === null) { bridge.kill(); await sleep(250); }
  try { if (created) fixture('clean'); }
  finally {
    if (detachedState) fs.writeFileSync(state, originalState);
    if (fs.existsSync(configurationPath)) fs.unlinkSync(configurationPath);
    if (fs.existsSync(configurationPath + '.ice.json')) fs.unlinkSync(configurationPath + '.ice.json');
  }
});
