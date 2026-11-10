/** Reproducible local visual capture for the so-short-code fixture. Node 20: --experimental-websocket. */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');

const project = path.resolve(__dirname, '../..');
const mode = process.argv[2];
if (!['baseline', 'compare'].includes(mode)) throw new Error('Usage: node --experimental-websocket tests/visual/capture.cjs baseline|compare');
const baselineDate = process.env.SOSC_BASELINE_DATE || '20261020-v3';
if (!/^\d{8}(?:-v[1-9]\d*)?$/.test(baselineDate)) throw new Error('SOSC_BASELINE_DATE must be YYYYMMDD or YYYYMMDD-vN.');
const baseline = path.join(project, 'tests', 'visual-baseline', baselineDate);
const output = mode === 'baseline' ? baseline : path.join(project, '.local', 'visual-current');
if (mode === 'baseline' && fs.existsSync(baseline) && fs.readdirSync(baseline).length) throw new Error('Baseline already exists. Review and archive it before a separately authorized replacement.');
fs.mkdirSync(output, { recursive: true });
if (mode === 'compare') {
  fs.rmSync(path.join(output, 'comparison.json'), { force: true });
  if (!fs.existsSync(path.join(baseline, 'manifest.json'))) throw new Error('Baseline manifest is missing.');
}
const profileParent = path.join(project, '.local');
const profile = fs.mkdtempSync(path.join(profileParent, 'visual-chrome-'));
const chrome = process.env.SOSC_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const child = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-sandbox', '--ignore-certificate-errors', '--remote-debugging-port=0', '--remote-allow-origins=*', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore', windowsHide: true });
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const sha = data => crypto.createHash('sha256').update(data).digest('hex');
const pages = ['info', 'cards', 'welcart'];
const widths = [320, 390, 768, 1280];
const expected = {
  info: ['sosc_info_list01', 'sosc_info_list02', 'sosc_info_list03', 'sosc_info_list04', 'sosc_info_list05'],
  cards: ['sosc_blog_card01', 'sosc_tag_view01', 'sosc_target_content'],
  welcart: ['sosc_wc_item_list01', 'sosc_wc_item_list02', 'sosc_wc_item_list03', 'sosc_wc_item_card01', 'sosc_wc_item_card02', 'sosc_wc_item_card03', 'sosc_wc_documents'],
};
const manifest = { schema: 1, date: `${baselineDate.slice(0,4)}-${baselineDate.slice(4,6)}-${baselineDate.slice(6,8)}`, settleMs: 2000, site: 'local Docker osaru-wp', fixture: 'tests/visual-fixture.php', viewports: widths, files: {}, captures: [] };
let ws;
let seq = 0;
const pending = new Map();
function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++seq;
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params }));
  });
}
async function evalJS(expression) {
  const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (response.exceptionDetails) throw new Error(response.exceptionDetails.text);
  return response.result.value;
}
async function imageDiff(left, right) {
  const a = fs.readFileSync(left).toString('base64');
  const b = fs.readFileSync(right).toString('base64');
  return evalJS(`(async()=>{const load=s=>new Promise((ok,fail)=>{const i=new Image();i.onload=()=>ok(i);i.onerror=fail;i.src='data:image/png;base64,'+s});const a=await load(${JSON.stringify(a)}),b=await load(${JSON.stringify(b)});if(a.width!==b.width||a.height!==b.height)return {sameSize:false,baseline:[a.width,a.height],current:[b.width,b.height]};const c=document.createElement('canvas');c.width=a.width;c.height=a.height;const x=c.getContext('2d',{willReadFrequently:true});x.drawImage(a,0,0);const p=x.getImageData(0,0,c.width,c.height).data;x.clearRect(0,0,c.width,c.height);x.drawImage(b,0,0);const q=x.getImageData(0,0,c.width,c.height).data;let changed=0,minX=c.width,minY=c.height,maxX=-1,maxY=-1;for(let i=0;i<p.length;i+=4){if(p[i]!==q[i]||p[i+1]!==q[i+1]||p[i+2]!==q[i+2]||p[i+3]!==q[i+3]){changed++;const px=(i/4)%c.width,py=Math.floor(i/4/c.width);minX=Math.min(minX,px);minY=Math.min(minY,py);maxX=Math.max(maxX,px);maxY=Math.max(maxY,py)}}return {sameSize:true,changedPixels:changed,totalPixels:c.width*c.height,changedPercent:Number((100*changed/(c.width*c.height)).toFixed(4)),bounds:changed?[minX,minY,maxX,maxY]:null}})()`);
}
async function capture(name, clip, fullPage = false) {
  const result = await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: true, clip: { ...clip, scale: 1 } });
  const data = Buffer.from(result.data, 'base64');
  const file = name + '.png';
  fs.writeFileSync(path.join(output, file), data);
  const entry = { file, sha256: sha(data), width: Math.ceil(clip.width), height: Math.ceil(clip.height), fullPage };
  manifest.captures.push(entry);
  return entry;
}
async function main() {
  let port;
  for (let i = 0; i < 100; i++) {
    try { port = Number(fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split(/\r?\n/)[0]); break; } catch { await sleep(100); }
  }
  if (!port) throw new Error('Chrome DevTools did not start');
  const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { ws.addEventListener('open', resolve, { once: true }); ws.addEventListener('error', reject, { once: true }); });
  ws.addEventListener('message', event => {
    const msg = JSON.parse(event.data);
    if (!pending.has(msg.id)) return;
    const job = pending.get(msg.id);
    pending.delete(msg.id);
    msg.error ? job.reject(new Error(JSON.stringify(msg.error))) : job.resolve(msg.result);
  });
  manifest.browser = await send('Browser.getVersion');
  for (const file of ['src/so-short-code/css/bootstrap.scoped.min.css', 'src/so-short-code/css/short-code.css', 'src/so-short-code/css/sosc-common.css', 'src/so-short-code/view/panel/short_code_view.php', 'src/so-short-code/view/panel/short_code_wc_view.php', 'tests/visual-fixture.php']) {
    manifest.files[file] = sha(fs.readFileSync(path.join(project, file)));
  }
  await send('Page.enable');
  for (const width of widths) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 1200, deviceScaleFactor: 1, mobile: width <= 390 });
    for (const page of pages) {
      await send('Page.navigate', { url: `https://localhost/sosc-visual-20261020-${page}/` });
      await evalJS('(async()=>{while(document.readyState!=="complete")await new Promise(r=>setTimeout(r,50));await document.fonts.ready;const delay=ms=>new Promise(r=>setTimeout(r,ms));for(let y=0;y<document.documentElement.scrollHeight;y+=600){scrollTo(0,y);await delay(80)}scrollTo(0,0);await Promise.all([...document.images].map(i=>i.complete?null:Promise.race([new Promise(r=>{i.onload=r;i.onerror=r}),delay(5000)])));await delay(2000);if(scrollY!==0)throw new Error("Capture must start at page top");document.querySelector("#pagetop")?.style.setProperty("visibility","hidden","important");return true})()');
      const layout = await evalJS(`(()=>{const cases=[...document.querySelectorAll('.sosc-visual-case')];return {path:location.pathname,width:innerWidth,scrollWidth:document.documentElement.scrollWidth,height:Math.ceil(Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)),scopedCss:[...document.styleSheets].some(s=>s.href&&new URL(s.href).pathname.endsWith('/bootstrap.scoped.min.css')),brokenImages:[...document.querySelectorAll('.sosc-visual-case img')].filter(i=>!i.complete||i.naturalWidth===0).map(i=>i.src),cases:cases.map(s=>{const r=s.getBoundingClientRect(),roots=[...s.querySelectorAll('.sosc-bs-scope')];return {name:s.dataset.case,x:r.x,y:r.y+scrollY,width:r.width,height:r.height,roots:roots.length,content:roots.some(root=>root.textContent.trim().length>0)}})}})()`);
      if (layout.path !== `/sosc-visual-20261020-${page}/` || layout.width !== width || layout.scrollWidth > width || !layout.scopedCss || layout.brokenImages.length || JSON.stringify(layout.cases.map(item => item.name)) !== JSON.stringify(expected[page])) throw new Error(`Layout failed: ${page} ${width}: ${JSON.stringify(layout)}`);
      if (layout.height > 12000) throw new Error(`Unexpected page height: ${layout.height}`);
      const full = await capture(`${page}-${width}-page`, { x: 0, y: 0, width, height: layout.height }, true);
      full.viewport = width;
      full.page = page;
      full.scrollWidth = layout.scrollWidth;
      for (const item of layout.cases) {
        if (item.roots < 1 || !item.content || item.width <= 0 || item.height <= 0 || item.x < 0 || item.x + item.width > width + 1 || item.y < 0 || item.y + item.height > layout.height + 1) throw new Error(`Invalid shortcode case: ${item.name} ${JSON.stringify(item)}`);
        const x = Math.max(0, Math.floor(item.x));
        const y = Math.max(0, Math.floor(item.y));
        const right = Math.min(width, Math.ceil(item.x + item.width));
        const bottom = Math.min(layout.height, Math.ceil(item.y + item.height));
        const entry = await capture(`${page}-${width}-${item.name}`, { x, y, width: right - x, height: bottom - y });
        entry.viewport = width;
        entry.page = page;
        entry.case = item.name;
      }
      console.log(`${page} ${width}px: ${layout.cases.length} cases, scrollWidth ${layout.scrollWidth}px`);
    }
  }
  if (mode === 'baseline') {
    fs.writeFileSync(path.join(output, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
    console.log(`Baseline saved: ${output}`);
  } else {
    const reference = JSON.parse(fs.readFileSync(path.join(baseline, 'manifest.json'), 'utf8'));
    const currentNames = new Set(manifest.captures.map(entry => entry.file));
    const referenceNames = new Set(reference.captures.map(entry => entry.file));
    if (currentNames.size !== manifest.captures.length || referenceNames.size !== reference.captures.length) throw new Error('Duplicate capture names in manifest.');
    for (const entry of reference.captures) {
      const file = path.join(baseline, entry.file);
      if (!fs.existsSync(file) || sha(fs.readFileSync(file)) !== entry.sha256) throw new Error(`Baseline image/manifest mismatch: ${entry.file}`);
    }
    const findings = [];
    for (const file of referenceNames) if (!currentNames.has(file)) findings.push({ kind: 'missing-current', severity: 'review', file });
    for (const file of Object.keys(reference.files)) if (!(file in manifest.files)) findings.push({ kind: 'missing-source-check', severity: 'review', file });
    if (reference.browser.product !== manifest.browser.product) findings.push({ kind: 'browser-version', severity: 'review', baseline: reference.browser.product, current: manifest.browser.product });
    for (const [file, hash] of Object.entries(manifest.files)) {
      if (reference.files[file] !== hash) findings.push({ kind: 'source-change', severity: 'review', file });
    }
    for (const entry of manifest.captures) {
      const before = reference.captures.find(old => old.file === entry.file);
      if (!before) { findings.push({ kind: 'missing-baseline', severity: 'review', file: entry.file }); continue; }
      if (entry.sha256 === before.sha256) continue;
      const diff = await imageDiff(path.join(baseline, entry.file), path.join(output, entry.file));
      if (diff.sameSize && diff.changedPixels === 0) continue;
      findings.push({ kind: 'image-diff', severity: 'review', file: entry.file, ...diff });
    }
    fs.writeFileSync(path.join(output, 'comparison.json'), JSON.stringify({ baseline, current: output, findings }, null, 2) + '\n');
    const review = findings.filter(f => f.severity === 'review').length;
    console.log(`Comparison: ${review} review items; ${path.join(output, 'comparison.json')}`);
    if (review) process.exitCode = 2;
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (ws) ws.close();
  child.kill();
  await sleep(1000);
  const safeRoot = path.resolve(profileParent) + path.sep;
  if (path.resolve(profile).startsWith(safeRoot) && path.basename(profile).startsWith('visual-chrome-')) {
    try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 }); }
    catch (error) { console.error(`Chrome profile cleanup failed: ${profile}: ${error.message}`); process.exitCode ||= 1; }
  }
});
