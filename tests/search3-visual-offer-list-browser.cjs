// Exercise the actual compiled lazy owner through the isolated offline PHP entry.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2'));
const base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence/offer-list-lazy');fs.mkdirSync(evidence,{recursive:true});
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
   if(url.pathname.endsWith('/offer-list-v1.js')){requests++;assert.match(url.search,/^\?v=[0-9a-f]{12}$/,'PHP binds the actual cold asset hash');if(requests===1){await route.abort();return;}if(requests===2){pending=true;await new Promise(resolve=>{release=resolve;});}}
   await route.continue();
  });
  try{
   await page.goto(`http://127.0.0.1:${server.address().port}${base}visual-search/?scenario=mixed`);
   const trigger=page.locator('.hotel-card [data-action="all-offers"]').first();await trigger.waitFor();assert.equal(requests,0,'form/results never download offer-list renderer');
   await trigger.click();await page.locator('[data-action="retry-offer-list"]').waitFor();assert.equal(requests,1);
   await page.locator('[data-action="retry-offer-list"]').click();while(!pending)await page.waitForTimeout(20);assert.equal(requests,2);
   await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);release();await page.waitForFunction(()=>!!window.AnyTourOfferList?.create);
   assert.equal(await page.locator('#modal').evaluate(el=>el.open),false,'late load does not reopen a closed modal');
   await trigger.click();await page.locator('#offer-count').waitFor();await page.locator('.offer-group-heading').first().click();await page.locator('.grouped-offer').first().waitFor();assert.equal(requests,2,'warm open reuses the already loaded owner');
   let paginationIdentity=null,more=page.locator('[data-action="group-more"]').first();
   for(let i=1;await more.count()===0&&i<await page.locator('.hotel-card [data-action="all-offers"]').count();i++){
    await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);await page.locator('.hotel-card [data-action="all-offers"]').nth(i).click();await page.locator('#offer-count').waitFor();more=page.locator('[data-action="group-more"]').first();
   }
   assert.equal(await more.count(),1,'mixed fixture exposes a bounded group page across loaded hotels');
   if(await more.count()){
    const key=await more.getAttribute('data-value'),group=page.locator(`[id="group-${key}"]`);if(await group.getAttribute('hidden')!==null)await page.locator(`[data-action="offer-group"][data-value="${key}"]`).click();
    const before=await group.locator('.grouped-offer').count();assert(before>=2,'pagination group has retained rows to reconcile');
    await group.evaluate(body=>{const rows=body.querySelectorAll('.grouped-offer');window.__o44CleanRow=rows[0];window.__o44DirtyRow=rows[1];rows[1].setAttribute('data-external-dirty','1');rows[1].querySelector('strong').textContent='DIRTY';});
    await group.locator('[data-action="group-more"]').click();await page.waitForFunction(({key,before})=>document.getElementById('group-'+key).querySelectorAll('.grouped-offer').length>before,{key,before});
    paginationIdentity=await group.evaluate((body,before)=>{const rows=body.querySelectorAll('.grouped-offer'),active=document.activeElement;return {before,after:rows.length,cleanRetained:rows[0]===window.__o44CleanRow,dirtyReplaced:rows[1]!==window.__o44DirtyRow,dirtyRestored:!rows[1].hasAttribute('data-external-dirty')&&!rows[1].textContent.includes('DIRTY'),focusedNew:active?.dataset.action==='offer'&&active.closest('.grouped-offer')===rows[before]};},before);
    assert(paginationIdentity.after>before&&paginationIdentity.after<=before+8,'group-more appends only the next bounded page');assert.equal(paginationIdentity.cleanRetained,true);assert.equal(paginationIdentity.dirtyReplaced,true);assert.equal(paginationIdentity.dirtyRestored,true);assert.equal(paginationIdentity.focusedNew,true);
   }
   if(!await page.locator('.offer-filter-disclosure').evaluate(el=>el.open))await page.locator('.offer-filter-disclosure>summary').click();
   const room=page.locator('#offer-room');const choices=await room.locator('option').count();assert(choices>=3,'mixed fixture has two actual room choices');const selected=await room.locator('option').nth(1).getAttribute('value');await room.selectOption(selected);
   assert.equal(await room.inputValue(),selected);assert(await page.locator('.grouped-offer').count()>0);
   await page.screenshot({path:path.join(evidence,`offers-${width}.png`)});
   await page.locator('[data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);await page.waitForTimeout(150);await page.goForward();await page.locator('#offer-room').waitFor();assert.equal(await room.inputValue(),selected,'Forward restores the same offer refinement');assert.equal(requests,2);
   assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);receipts.push({width,initial_downloads:0,failed_downloads:1,retry_downloads:1,warm_downloads:0,late_closed_modal_render:false,group_pagination:paginationIdentity,history_room_restored:true,supplier_requests:0,lead_requests:0});
  }finally{release?.();await context.close();}
 }}finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS compiled cold offer-list browser',JSON.stringify(receipts));
})().catch(error=>{console.error(error);process.exitCode=1;});
