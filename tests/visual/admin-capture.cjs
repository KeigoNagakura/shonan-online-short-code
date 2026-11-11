/** Capture the local plugin admin pages using a process-only password. */
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const crypto = require('crypto');
const project = path.resolve(__dirname, '../..');
const mode = process.argv[2];
if (!['baseline','compare'].includes(mode)) throw new Error('Usage: node --experimental-websocket tests/visual/admin-capture.cjs baseline|compare');
const baselineDate = process.env.SOSC_BASELINE_DATE || '20261111-v1';
if (!/^\d{8}(?:-v[1-9]\d*)?$/.test(baselineDate)) throw new Error('SOSC_BASELINE_DATE must be YYYYMMDD or YYYYMMDD-vN.');
const password = process.env.SOSC_ADMIN_PASSWORD;
const username = process.env.SOSC_ADMIN_USER || 'wp_local_admin';
const baseline = path.join(project, 'tests', 'visual-baseline', baselineDate);
const output = mode === 'baseline' ? baseline : path.join(project, '.local', 'visual-admin');
if (mode === 'baseline' && fs.existsSync(baseline) && fs.readdirSync(baseline).some(file => file === 'admin-manifest.json' || /^admin-.*\.png$/.test(file))) throw new Error('Admin baseline already exists. Review before replacement.');
fs.mkdirSync(output, { recursive: true });
if (mode === 'compare') {
  fs.rmSync(path.join(output, 'admin-comparison.json'), { force: true });
  fs.rmSync(path.join(output, 'admin-measurements.json'), { force: true });
  if (!fs.existsSync(path.join(baseline,'admin-manifest.json'))) throw new Error('Admin baseline is missing.');
}
if (!password) throw new Error('SOSC_ADMIN_PASSWORD is required in the process environment.');
const profile = fs.mkdtempSync(path.join(project, '.local', 'visual-admin-chrome-'));
const chrome = process.env.SOSC_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const child = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--no-sandbox','--ignore-certificate-errors','--remote-debugging-port=0','--remote-allow-origins=*',`--user-data-dir=${profile}`,'about:blank'], { stdio:'ignore', windowsHide:true });
const sleep = ms => new Promise(r => setTimeout(r,ms));
const sha = data => crypto.createHash('sha256').update(data).digest('hex');
let ws, seq=0; const pending=new Map();
function send(method,params={}) { return new Promise((resolve,reject)=>{ const id=++seq; pending.set(id,{resolve,reject}); ws.send(JSON.stringify({id,method,params})); }); }
async function evaluate(expression) { const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true}); if(r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; }
async function ready() { await evaluate('(async()=>{while(document.readyState!=="complete")await new Promise(r=>setTimeout(r,50));await document.fonts.ready;return true})()'); }
async function imageDiff(left,right) {
  const a=fs.readFileSync(left).toString('base64'),b=fs.readFileSync(right).toString('base64');
  return evaluate(`(async()=>{const load=s=>new Promise((ok,bad)=>{const i=new Image();i.onload=()=>ok(i);i.onerror=bad;i.src='data:image/png;base64,'+s});const a=await load(${JSON.stringify(a)}),b=await load(${JSON.stringify(b)});if(a.width!==b.width||a.height!==b.height)return {sameSize:false,baseline:[a.width,a.height],current:[b.width,b.height]};const c=document.createElement('canvas');c.width=a.width;c.height=a.height;const x=c.getContext('2d',{willReadFrequently:true});x.drawImage(a,0,0);const p=x.getImageData(0,0,c.width,c.height).data;x.clearRect(0,0,c.width,c.height);x.drawImage(b,0,0);const q=x.getImageData(0,0,c.width,c.height).data;let changed=0;for(let i=0;i<p.length;i+=4)if(p[i]!==q[i]||p[i+1]!==q[i+1]||p[i+2]!==q[i+2]||p[i+3]!==q[i+3])changed++;return {sameSize:true,changedPixels:changed,totalPixels:c.width*c.height,changedPercent:Number((100*changed/(c.width*c.height)).toFixed(4))}})()`);
}
async function main() {
  let port; for(let i=0;i<100;i++){try{port=Number(fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split(/\r?\n/)[0]);break}catch{await sleep(100)}}
  if(!port)throw new Error('Chrome DevTools did not start');
  const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
  await new Promise((ok,bad)=>{ws.addEventListener('open',ok,{once:true});ws.addEventListener('error',bad,{once:true})});
  ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(!pending.has(m.id))return;const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(new Error(JSON.stringify(m.error))):p.resolve(m.result)});
  await send('Page.enable');
  await send('Page.navigate',{url:'https://localhost/wp-login.php'}); await ready();
  const login = `(()=>{const u=document.querySelector('#user_login'),p=document.querySelector('#user_pass'),f=document.querySelector('#loginform');if(!u||!p||!f)return false;u.value=${JSON.stringify(username)};p.value=${JSON.stringify(password)};f.submit();return true})()`;
  if(!await evaluate(login))throw new Error('Login form not found');
  await sleep(1000); await ready();
  const pages=['shortcodes','welcart','other'];
  const panelIds={shortcodes:'tab-pane1',welcart:'tab-pane2',other:'tab-pane4'};
  const result=[];
  const browser=await send('Browser.getVersion');
  for(const width of [320,390,1280]){
    await send('Emulation.setDeviceMetricsOverride',{width,height:1200,deviceScaleFactor:1,mobile:width<=390});
    for(const tab of pages){
      await send('Page.navigate',{url:`https://localhost/wp-admin/admin.php?page=sosc_menu&tab=${tab}`}); await ready();
      // WordPress core update notices are outside the plugin and change independently.
      await evaluate(`(()=>{document.querySelectorAll('#wpbody-content > .update-nag').forEach(x=>x.style.display='none');return true})()`);
      const layout=await evaluate(`(()=>{const root=document.querySelector('.wrap.sosc-bs-scope'),panel=document.querySelector('.sosc-tab-pane.active');return {url:location.pathname,width:innerWidth,scroll:document.documentElement.scrollWidth,height:Math.ceil(Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)),root:!!root,tab:panel?.id||null,content:!!panel?.textContent.trim(),scopedCss:[...document.styleSheets].some(s=>s.href&&new URL(s.href).pathname.endsWith('/bootstrap.scoped.min.css')),overflow:root?[...root.querySelectorAll('*')].filter(x=>{const r=x.getBoundingClientRect();return r.right>innerWidth+2}).slice(0,5).map(x=>({tag:x.tagName,cls:x.className})):[]}})()`);
      if(!layout.root||layout.tab!==panelIds[tab]||!layout.content||!layout.scopedCss||layout.url!=='/wp-admin/admin.php'||layout.width!==width||layout.scroll>width||layout.height>10000)throw new Error(`Admin authentication/layout failed: ${tab} ${width}: ${JSON.stringify(layout)}`);
      const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height:Math.min(layout.height,10000),scale:1}});
      const file=`admin-${tab}-${width}.png`; const data=Buffer.from(shot.data,'base64'); fs.writeFileSync(path.join(output,file),data);
      result.push({file,tab,sha256:sha(data),...layout}); console.log(`${tab} ${width}px: scrollWidth ${layout.scroll}px`);
    }
  }
  if(mode==='baseline')fs.writeFileSync(path.join(output,'admin-manifest.json'),JSON.stringify({browser:browser.product,captures:result},null,2)+'\n');
  else {
    const before=JSON.parse(fs.readFileSync(path.join(baseline,'admin-manifest.json'),'utf8'));
    const currentNames=new Set(result.map(entry=>entry.file)),baselineNames=new Set(before.captures.map(entry=>entry.file));
    if(currentNames.size!==result.length||baselineNames.size!==before.captures.length)throw new Error('Duplicate admin capture names in manifest.');
    for(const entry of before.captures){const file=path.join(baseline,entry.file);if(!fs.existsSync(file)||sha(fs.readFileSync(file))!==entry.sha256)throw new Error(`Admin baseline image/manifest mismatch: ${entry.file}`)}
    const findings=[];
    for(const file of baselineNames)if(!currentNames.has(file))findings.push({kind:'missing-current',severity:'review',file});
    if(before.browser!==browser.product)findings.push({kind:'browser-version',severity:'review',baseline:before.browser,current:browser.product});
    for(const entry of result){const old=before.captures.find(x=>x.file===entry.file);if(!old){findings.push({kind:'missing-baseline',severity:'review',file:entry.file});continue}if(entry.sha256===old.sha256)continue;const diff=await imageDiff(path.join(baseline,entry.file),path.join(output,entry.file));if(diff.sameSize&&diff.changedPixels===0)continue;findings.push({kind:'image-diff',severity:'review',file:entry.file,...diff})}
    fs.writeFileSync(path.join(output,'admin-comparison.json'),JSON.stringify({findings},null,2)+'\n');
    fs.writeFileSync(path.join(output,'admin-measurements.json'),JSON.stringify(result,null,2)+'\n');
    const review=findings.filter(x=>x.severity==='review').length;
    console.log(`Admin comparison: ${review} review items`);
    if(review)process.exitCode=2;
  }
}
main().catch(e=>{console.error(e);process.exitCode=1}).finally(async()=>{if(ws)ws.close();child.kill();await sleep(1000);const base=path.resolve(project,'.local')+path.sep;if(path.resolve(profile).startsWith(base)&&path.basename(profile).startsWith('visual-admin-chrome-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:10,retryDelay:200})}catch(error){console.error(`Chrome profile cleanup failed: ${profile}: ${error.message}`);process.exitCode ||= 1}});
