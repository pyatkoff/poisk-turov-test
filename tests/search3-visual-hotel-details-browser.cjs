// Exercise the actual compiled lazy owner through the isolated offline PHP entry.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2'));
const base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence/hotel-details-lazy');fs.mkdirSync(evidence,{recursive:true});
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://fixture');if(!url.pathname.startsWith(base)){res.writeHead(403).end();return;}
 const local=path.resolve(root,url.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
 const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
 if(!fs.existsSync(file)){res.writeHead(404).end();return;}
 if(file.endsWith('.php')){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; $_GET["scenario"]="mixed"; include $argv[1];',file]));return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;const receipts=[];
 try{browser=await chromium.launch({headless:true});for(const width of [390,1280]){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],forbidden=[];let requests=0,release,pending;
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url());if(url.hostname!=='127.0.0.1'){if(route.request().resourceType()!=='image')forbidden.push(url.pathname);await route.abort();return;}
   if(url.pathname.endsWith('/hotel-details-v1.js')){requests++;assert.match(url.search,/^\?v=[0-9a-f]{12}$/,'PHP binds the actual cold asset hash');if(requests===1){await route.abort();return;}if(requests===2){pending=true;await new Promise(resolve=>{release=resolve;});}}
   await route.continue();
  });
  try{
   await page.goto(`http://127.0.0.1:${server.address().port}${base}visual-search/?scenario=mixed`);
   const trigger=page.locator('.hotel-card [data-action="hotel-details"]').first();await trigger.waitFor();assert.equal(requests,0,'form/results never download hotel-details renderer');
   await trigger.click();await page.locator('[data-action="retry-hotel-details"]').waitFor();assert.equal(requests,1);
   await page.locator('[data-action="retry-hotel-details"]').click();while(!pending)await page.waitForTimeout(20);assert.equal(requests,2);
   await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);release();await page.waitForFunction(()=>!!window.AnyTourHotelDetails?.create);
   assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late load does not reopen a closed modal');
   await trigger.click();await page.locator('#hotel-room-count').waitFor();assert.equal(requests,2,'warm open reuses the owner');
   const room=page.locator('.room-overview').first(),selected=await room.getAttribute('data-room');await room.locator(':scope > summary').click();
   assert.equal(await room.evaluate(el=>el.open),true);const choice=room.locator('[data-action="offer"]').first(),key=await choice.getAttribute('data-key');assert(key);
   await page.screenshot({path:path.join(evidence,`hotel-${width}.png`)});
   await choice.click();await page.locator('.tour-dialog #detail-total').waitFor();assert((await page.locator('.tour-dialog').innerText()).includes(selected),'selected offer keeps the exact room');
   await page.locator('[data-action="modal-back"]').click();await page.locator('#hotel-room-count').waitFor();assert.equal(await page.locator('.room-overview[open]').first().getAttribute('data-room'),selected,'nested Back keeps exact chosen room disclosure');
   assert.equal(await page.locator('.room-overview[open] [data-action="offer"]').first().getAttribute('data-key'),key);
   await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);await page.waitForTimeout(150);await page.goForward();await page.locator('#hotel-room-count').waitFor();assert.equal(await page.locator('.room-overview[open]').first().getAttribute('data-room'),selected,'Forward restores room disclosure');assert.equal(requests,2);
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);receipts.push({width,initial_downloads:0,failed_downloads:1,retry_downloads:1,warm_downloads:0,late_closed_modal_render:false,history_room_restored:true,nested_exact_offer_back:true,supplier_requests:0,lead_requests:0});
  }finally{release?.();await context.close();}
 }}finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS compiled cold hotel-details browser',JSON.stringify(receipts));
})().catch(error=>{console.error(error);process.exitCode=1;});
