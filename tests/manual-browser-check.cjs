/** Inspect the bundled manual in the local WordPress admin. Requires SOSC_ADMIN_PASSWORD. */
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const password = process.env.SOSC_ADMIN_PASSWORD;
if (!password) throw new Error('SOSC_ADMIN_PASSWORD is required');
const project = path.resolve(__dirname, '..');
const output = path.join(project, '.local', process.env.SOSC_MANUAL_OUTPUT || 'manual-current');
fs.mkdirSync(output, { recursive: true });
const profile = fs.mkdtempSync(path.join(project, '.local', 'manual-chrome-'));
const chrome = spawn(process.env.SOSC_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
  '--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run', '--ignore-certificate-errors',
  '--remote-debugging-port=0', '--remote-allow-origins=*', `--user-data-dir=${profile}`, 'about:blank',
], { stdio: 'ignore', windowsHide: true });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ws, seq = 0;
const pending = new Map();
function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++seq;
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
  return response.result.value;
}
async function navigate(url) {
  await send('Page.navigate', { url });
  await evaluate('(async()=>{while(document.readyState!=="complete")await new Promise(r=>setTimeout(r,50));await document.fonts.ready;return true})()');
  await evaluate('(async()=>{await Promise.all([...document.images].map(i=>i.complete?null:new Promise(r=>{i.onload=r;i.onerror=r})));return true})()');
  await sleep(250);
}
async function capture(name, width) {
  const height = await evaluate('Math.min(1600,Math.ceil(Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)))');
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width, height, scale: 1 } });
  fs.writeFileSync(path.join(output, `${name}.png`), Buffer.from(shot.data, 'base64'));
}
async function main() {
  let port;
  for (let i = 0; i < 100; i++) {
    try { port = Number(fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split(/\r?\n/)[0]); break; }
    catch { await sleep(100); }
  }
  if (!port) throw new Error('Chrome DevTools did not start');
  const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  ws = new WebSocket(pages.find(p => p.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
  ws.onmessage = event => {
    const data = JSON.parse(event.data);
    if (!data.id || !pending.has(data.id)) return;
    const item = pending.get(data.id); pending.delete(data.id);
    data.error ? item.reject(new Error(data.error.message)) : item.resolve(data.result);
  };
  await send('Page.enable'); await send('Runtime.enable');
  await navigate('https://localhost/wp-login.php');
  const loggedIn = await evaluate(`(()=>{const f=document.querySelector('#loginform');if(!f)return false;document.querySelector('#user_login').value='wp_local_admin';document.querySelector('#user_pass').value=${JSON.stringify(password)};f.submit();return true})()`);
  if (!loggedIn) throw new Error('Login form missing');
  await sleep(1000);
  const allTopics = [];
  for (const width of [320, 390, 1280]) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 1200, deviceScaleFactor: 1, mobile: width <= 390 });
    await navigate('https://localhost/wp-admin/admin.php?page=sosc_manual');
    const index = await evaluate(`(()=>({title:document.querySelector('.sosc-manual h1')?.textContent.trim(),cards:[...document.querySelectorAll('.sosc-manual-card')].map(x=>new URL(x.href).searchParams.get('topic')),groups:document.querySelectorAll('.sosc-manual-section').length,broken:[...document.querySelectorAll('.sosc-manual-card img')].filter(x=>!x.complete||!x.naturalWidth).length,scroll:document.documentElement.scrollWidth,css:[...document.styleSheets].some(x=>x.href&&x.href.includes('/manual/manual.css'))}))()`);
    if (!index.title || index.cards.length !== 29 || index.groups !== 5 || index.broken || index.scroll > width || !index.css) throw new Error(`Index layout ${width}: ${JSON.stringify(index)}`);
    if (!allTopics.length) allTopics.push(...index.cards);
    const cardLayout = await evaluate(`(()=>{const cards=[...document.querySelectorAll('.sosc-manual-card')];return cards.every(card=>{const r=card.getBoundingClientRect();const title=card.querySelector('strong')?.getBoundingClientRect();const more=card.querySelector('.sosc-manual-card-more')?.getBoundingClientRect();return r.width>180&&r.left>=-1&&r.right<=innerWidth+1&&title&&more&&title.bottom<more.top})})()`);
    if (!cardLayout) throw new Error(`Index card layout ${width}: overlap or clipping`);
    await capture(`manual-index-${width}`, width);
    for (const topic of ['info-list03', 'pickup', 'vertical-banner', 'h2-style', 'updates', 'backup']) {
      await navigate(`https://localhost/wp-admin/admin.php?page=sosc_manual&topic=${topic}`);
      const detail = await evaluate(`(()=>({title:document.querySelector('.sosc-manual h1')?.textContent.trim(),steps:document.querySelectorAll('.sosc-manual-steps li').length,visual:!!document.querySelector('.sosc-manual-visual img,.sosc-manual-visual svg,.sosc-manual-preview-banner,.sosc-manual-preview-heading'),broken:[...document.querySelectorAll('.sosc-manual-visual img')].filter(x=>!x.complete||!x.naturalWidth).length,scroll:document.documentElement.scrollWidth}))()`);
      if (!detail.title || detail.steps !== 3 || !detail.visual || detail.broken || detail.scroll > width) throw new Error(`Detail ${topic} ${width}: ${JSON.stringify(detail)}`);
      await capture(`manual-${topic}-${width}`, width);
    }
    console.log(`index and samples ${width}px: passed`);
    for (const topic of allTopics) {
      await navigate(`https://localhost/wp-admin/admin.php?page=sosc_manual&topic=${topic}`);
      const detail = await evaluate(`(()=>({heading:!!document.querySelector('.sosc-manual h1'),sections:document.querySelectorAll('.sosc-manual-body > section').length,visual:!!document.querySelector('.sosc-manual-visual img,.sosc-manual-visual svg,.sosc-manual-preview-banner,.sosc-manual-preview-heading'),broken:[...document.querySelectorAll('.sosc-manual-visual img')].filter(x=>!x.complete||!x.naturalWidth).length,runtime:!!document.querySelector('.sosc-manual-runtime dl'),scroll:document.documentElement.scrollWidth}))()`);
      if (!detail.heading || detail.sections < 3 || !detail.visual || detail.broken || detail.scroll > width || (['updates','backup'].includes(topic) && !detail.runtime)) throw new Error(`Topic ${topic} ${width}: ${JSON.stringify(detail)}`);
    }
    console.log(`all ${allTopics.length} chapters ${width}px: passed`);
  }
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 1200, deviceScaleFactor: 1, mobile: false });
  await navigate('https://localhost/wp-admin/admin.php?page=sosc_manual&topic=info-list01');
  const copy = await evaluate(`(async()=>{let copied='';Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async text=>{copied=text}}});const button=document.querySelector('.sosc-manual-copy');button.click();await new Promise(r=>setTimeout(r,50));return {copied,example:document.querySelector('.sosc-manual-body pre code')?.textContent,label:button.textContent}})()`);
  if (!copy.copied || copy.copied !== copy.example || copy.label !== 'コピーしました') throw new Error(`Copy action: ${JSON.stringify(copy)}`);
  await navigate('https://localhost/wp-admin/admin.php?page=sosc_manual&topic=missing-topic');
  const missing = await evaluate(`({heading:document.querySelector('.sosc-manual h1')?.textContent,returnLink:!!document.querySelector('.sosc-manual-breadcrumb a[href*="sosc_manual"]')})`);
  if (missing.heading !== 'ページが見つかりません' || !missing.returnLink) throw new Error(`Missing topic: ${JSON.stringify(missing)}`);
  console.log('copy action and missing topic: passed');
  await navigate('https://localhost/wp-admin/admin.php?page=sosc_menu&tab=other');
  const settings = [
    ['hide-h1', 'h1タグ非表示'], ['top-margin', 'コンテンツ上マージン非表示'],
    ['bottom-margin', 'コンテンツ下マージン非表示'], ['font', 'フォント指定'],
    ['contact-form', 'コンタクトフォーム修正'], ['pickup', 'Welcart店頭受取機能'],
    ['service-item', 'Welcartサービス商品機能'], ['packing-setup', 'Welcart箱詰め設定'],
  ];
  for (const [name, label] of settings) {
    const box = await evaluate(`(()=>{const row=[...document.querySelectorAll('#tab-pane4 tr')].find(x=>x.querySelector('td')?.textContent.trim()===${JSON.stringify(label)});if(!row)return null;const r=row.getBoundingClientRect();return {x:Math.max(0,Math.floor(r.x+scrollX)),y:Math.max(0,Math.floor(r.y+scrollY)),width:Math.ceil(r.width),height:Math.ceil(r.height)}})()`);
    if (!box || box.width < 400 || box.height < 20 || box.width > 1280 || box.height > 700) throw new Error(`Settings row not found: ${label}`);
    const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { ...box, scale: 1 } });
    fs.writeFileSync(path.join(output, `settings-${name}.png`), Buffer.from(shot.data, 'base64'));
  }
  console.log(`settings screenshots ${settings.length}: captured`);
  for (const width of [320, 390, 1280]) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 1200, deviceScaleFactor: 1, mobile: width <= 390 });
    await navigate('https://localhost/wp-admin/admin.php?page=sosc_menu&tab=backup');
    const backup = await evaluate(`(()=>{const panel=document.querySelector('#tab-pane6');return {visible:!!panel?.classList.contains('active'),noRestore:!!panel?.textContent.includes('このプラグインに復元機能はありません'),scroll:document.documentElement.scrollWidth}})()`);
    if (backup.visible && (!backup.noRestore || backup.scroll > width)) throw new Error(`Backup guidance layout ${width}: ${JSON.stringify(backup)}`);
    console.log(`backup guidance ${width}px: ${backup.visible ? 'passed' : 'tab disabled'}`);
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (ws) ws.close();
  chrome.kill();
  await sleep(500);
  const safeRoot = path.resolve(project, '.local') + path.sep;
  if (path.resolve(profile).startsWith(safeRoot) && path.basename(profile).startsWith('manual-chrome-')) {
    try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 }); }
    catch (error) { console.error(`Profile cleanup failed: ${error.message}`); process.exitCode ||= 1; }
  }
});
