// Actual compiled presentation: terminal tours have no flight/application actions.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{execFileSync}=require('node:child_process'),{chromium}=require('playwright');
const root=path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT||path.join(__dirname,'../v2')),base='/_preview/search3-next-candidate/',evidence=path.resolve('visual-live-evidence/terminal-offers');fs.mkdirSync(evidence,{recursive:true});
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://fixture');if(!url.pathname.startsWith(base)){res.writeHead(403).end();return;}
 const local=path.resolve(root,url.pathname.slice(base.length)||'index.php');if(!local.startsWith(root+'/')){res.writeHead(403).end();return;}
 const file=fs.existsSync(local)&&fs.statSync(local).isDirectory()?path.join(local,'index.php'):local;
 if(!fs.existsSync(file)){res.writeHead(404).end();return;}
 if(file.endsWith('.php')){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(execFileSync('php',['-r','$_SERVER["SCRIPT_NAME"]="/_preview/search3-next-candidate/visual-search/index.php"; $_GET["scenario"]=$argv[2]; include $argv[1];',file,url.searchParams.get('scenario')||'mixed']));return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;const receipts=[];
 try{browser=await chromium.launch({headless:true});for(const width of [390,1280])for(const scenario of ['expired','unavailable','flight-error']){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[],external=[];
  page.on('pageerror',e=>errors.push(e.message));await page.route('**/*',async route=>{if(new URL(route.request().url()).hostname!=='127.0.0.1'){if(route.request().resourceType()!=='image')external.push(route.request().url());await route.abort();return;}await route.continue();});
  try{
   await page.goto(`http://127.0.0.1:${server.address().port}${base}visual-search/?scenario=${scenario}`);
   await page.locator('.hotel-card [data-action="offer"]').first().click();await page.locator('[data-action="start-tour-flights"]').click();
   if(scenario==='flight-error'){
    await page.locator('[data-action="retry-flights"]').waitFor();assert.equal(await page.locator('[data-action="confirm-tour"]').count(),1,'available tour keeps application after flight failure');
    await page.screenshot({path:path.join(evidence,`${scenario}-${width}.png`)});
    await page.locator('[data-action="confirm-tour"]').click();await page.locator('#prototype-lead-form').waitFor();assert.match(await page.locator('#modal-body').textContent(),/Рейс уточнит менеджер/);
   }else{
    await page.locator('.error-text[role="alert"]').waitFor();
    for(const action of ['start-tour-flights','offer-flights','retry-flights','choose-flight','confirm-tour','start-lead'])assert.equal(await page.locator(`#modal [data-action="${action}"]`).count(),0,`${scenario} removes ${action}`);
    assert.equal(await page.locator('#prototype-lead-form').count(),0);assert(!(await page.locator('#modal-body').textContent()).includes('Рейс уточнит менеджер'),'terminal tour removes the optional-flight invitation');
    await page.screenshot({path:path.join(evidence,`${scenario}-${width}.png`)});await page.locator('#modal-footer [data-action="close-modal"]').click();await page.waitForFunction(()=>!document.querySelector('#modal').open);assert(await page.locator('.hotel-card').first().isVisible(),'terminal tour returns to results');
   }
   assert.deepEqual(errors,[]);assert.deepEqual(external,[]);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no horizontal overflow');receipts.push({width,scenario,terminal_flights_removed:scenario!=='flight-error',available_application_retained:scenario==='flight-error',supplier_requests:0,lead_requests:0});
  }finally{await context.close();}
 }}finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
 fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(receipts,null,2)+'\n');console.log('PASS compiled terminal offer browser',JSON.stringify(receipts));
})().catch(error=>{console.error(error);process.exitCode=1;});
