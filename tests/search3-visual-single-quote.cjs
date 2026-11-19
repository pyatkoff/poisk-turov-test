'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {setTimeout:delay} = require('node:timers/promises');
const {entry,sha,openReplay,liveEntry,measureEngines,emitFirstOpenFile} = require('./search3-visual-first-open.cjs');
const {makeDelivery,until,summary} = require('./search3-visual-first-tours.cjs');
const {profile,trip} = require('./search3-visual-live-fixture.cjs');

const prefix='/_preview/search3-anex-candidate/';
const searchApi=prefix+'api-andromeda-search3-preview.php';
const quoteApi=prefix+'api-andromeda-quote-preview.php';
const ref='offer_'+'d'.repeat(64);
const amount=119000, raised=125500, supplierDelay=180;
const card='#hotel-'+profile.id+' [data-card-entry][data-key^="andromeda%3A"]';
const second='#modal-footer [data-action="refresh-hotel"]:not(:disabled)';

function quoteFixture(transport,scenario) {
  const original=makeDelivery(transport,{offers:1,anexDelay:0,tvStatusDelay:0});
  const quotes=[];
  transport.state.samoFlightChoice=scenario==='flights';
  transport.state.samoFailure=scenario==='unavailable'?'supplier_auth':null;
  return {quotes, async reply(url,body,method,payload) {
    if(url.pathname!==quoteApi)return original.reply(url,body,method,payload);
    assert.equal(method,'POST');assert.equal(body.action,'quote');
    assert.equal(body.offer_context.offer_ref,ref);
    assert.equal(body.listing_price_ref,'listing_'+'e'.repeat(64));
    quotes.push({offerRef:body.offer_context.offer_ref,listingRef:body.listing_price_ref});
    await delay(supplierDelay);
    const dto=await transport.json(url.href,{body:payload});
    if(scenario==='equal')dto.data.final_price.amount=String(amount);
    return dto;
  }};
}

// Measure inside the page from the first trusted tariff click until the result
// can be seen. Driver polling never supplies a clock value for these frames.
function observeQuote(config) {
  const state=window.__singleQuote={clicks:[],result:null};
  function visible(el) {
    if(!el)return false;
    const box=el.getBoundingClientRect();
    const hit=document.elementFromPoint(box.left+box.width/2,box.top+box.height/2);
    return box.width>0&&box.height>0&&box.left>=0&&box.top>=-1&&box.right<=innerWidth+1&&box.bottom<=innerHeight+1&&
      getComputedStyle(el).visibility==='visible'&&(hit===el||el.contains(hit));
  }
  let previous=null;
  function frame() {
    const action=document.querySelector('[data-action="andromeda-application-preview"]');
    const sum=document.querySelector('#modal-footer .footer-total strong');
    const price=Number(sum?.textContent.replace(/\D/g,''));
    const flights=document.querySelector('[data-action="apply-andromeda-flights"]');
    const error=document.querySelector('#modal-body .error-text');
    let result=null;
    if(action&&!action.disabled&&price===config.final&&visible(action)&&visible(sum))result={kind:'verified',price,label:action.textContent.trim()};
    else if(flights&&document.querySelector('[name="andromeda-outbound"]')&&document.querySelector('[name="andromeda-return"]'))result={kind:'flights'};
    else if(error?.textContent.trim()==='Не удалось подтвердить этот тур. Выберите другой вариант.')result={kind:'unavailable'};
    if(result&&previous?.kind===result.kind){
      state.result=previous;
      return;
    }
    previous=result&&{...result,ms:performance.now()-state.start};
    requestAnimationFrame(frame);
  }
  document.addEventListener('click',e=>{
    const button=e.target.closest('button');
    if(!button)return;
    const action=button.dataset.action;
    if(action==='select-hotel-offer'&&button.dataset.key===config.key&&state.start===undefined){
      state.start=performance.now();
      requestAnimationFrame(frame);
    }
    if(state.start!==undefined&&['select-hotel-offer','refresh-hotel'].includes(action))
      state.clicks.push({action,trusted:e.isTrusted,at:performance.now()-state.start});
  },true);
}

async function check(driver,engine,root,html,output,scenario,shot,expectedClicks) {
  const replay=await openReplay(root,html,0,t=>quoteFixture(t,scenario));
  try {
    await driver.go(replay.origin+entry+'?'+new URLSearchParams({...trip,ages:''}));
    assert.ok(await until(driver,'window.__firstOpen?.readyMs || null',10000));
    // The distribution keeps Andromeda optional. Opt in only on the loopback
    // origin, with the same endpoint names as the existing live-browser fixture.
    await driver.evaluate('Object.assign(V2_CONFIG,'+JSON.stringify({andromedaApi:searchApi,andromedaQuoteApi:quoteApi})+') && true');
    replay.allowClick();await driver.click('.search-submit:not(:disabled)');
    const key=await until(driver,'document.querySelector('+JSON.stringify(card)+')?.dataset.key && document.querySelector("#search-status").hidden && document.querySelector('+JSON.stringify(card)+').dataset.key',12000);
    assert.ok(key,'FUN&SUN hotel entry missing '+JSON.stringify(replay.blocked));
    await driver.click(card);
    const choice='[data-action="select-hotel-offer"][data-key="'+key+'"]',row='[data-offer-key="'+key+'"]';
    assert.ok(await until(driver,'document.querySelector('+JSON.stringify(choice)+')?.textContent.trim()==="Проверить цену"',6000));
    const original=await driver.evaluate('document.querySelector('+JSON.stringify(row)+').textContent');
    assert.match(original,/Всё включено/);assert.match(original.replace(/\s/g,''),/119000₽/);
    assert.equal(replay.delivery.quotes.length,0);
    await driver.evaluate('('+observeQuote.toString()+')('+JSON.stringify({key,final:scenario==='equal'?amount:raised})+')');
    await driver.click(choice);
    const next=await until(driver,'window.__singleQuote.result || (document.querySelector('+JSON.stringify(second)+')?.textContent.trim()==="Проверить этот тур" && "repeat")',8000);
    if(next==='repeat')await driver.click(second);
    const result=await until(driver,'window.__singleQuote.result',8000);
    assert.ok(result,'no result for '+scenario+' '+JSON.stringify(replay.blocked));
    assert.equal(result.kind,scenario==='flights'||scenario==='unavailable'?scenario:'verified');
    const measured=await driver.evaluate(`(() => {
      const state=window.__singleQuote;
      const resources=performance.getEntriesByType('resource').filter(r=>new URL(r.name).pathname===${JSON.stringify(quoteApi)});
      return {clicks:state.clicks, quoteStarts:resources.map(r=>r.startTime-state.start), result:state.result};
    })()`);
    assert.deepEqual(measured.clicks.map(x=>x.action),expectedClicks===1?['select-hotel-offer']:['select-hotel-offer','refresh-hotel']);
    assert.ok(measured.clicks.every(x=>x.trusted));assert.equal(measured.quoteStarts.length,1);
    assert.ok(measured.quoteStarts[0]>=measured.clicks.at(-1).at-2);
    assert.deepEqual(replay.delivery.quotes,[{offerRef:ref,listingRef:'listing_'+'e'.repeat(64)}]);
    if(scenario==='changed'&&expectedClicks===1)assert.match(result.label,/Принять/);
    if(shot)await driver.screenshot(path.join(output,engine+'-quote.png'));
    assert.deepEqual(replay.blocked,[]);
    const scripts={};
    for(const file of ['visual-search/app.js','prototype-search/data.js','visual-search/hotel-details-v1.js']){
      scripts[file]=sha(fs.readFileSync(path.join(root,file)));assert.equal(replay.scripts[file],scripts[file]);
    }
    return {scenario,key,scripts,...measured};
  } finally {await replay.close();}
}

async function main() {
  const root=fs.realpathSync(process.argv[2]),output=path.resolve(process.argv[3]),expectedClicks=Number(process.argv[4]);
  assert.ok([1,2].includes(expectedClicks));fs.mkdirSync(output,{recursive:true});
  const {source,html,entrySHA256}=liveEntry(root);
  const engines=await measureEngines(root,html,async(driver,engine)=>{
    const samples=[];
    for(const scenario of ['equal','equal','equal','equal','equal','changed','flights','unavailable'])
      samples.push(await check(driver,engine,root,html,output,scenario,samples.length===0,expectedClicks));
    const equal=samples.filter(s=>s.scenario==='equal');
    return {samples,clicks:expectedClicks,start:summary(equal.map(s=>s.quoteStarts[0])),visible:summary(equal.map(s=>s.result.ms))};
  });
  const report={source,entrySHA256,supplierDelay,expectedClicks,method:'Fresh loopback origins and compiled NEXT JS. Native trusted tariff click to first of two consecutive frames with a hit-testable verified amount and application action. Five equal-price samples per engine. Fictional supplier takes 180ms. The baseline confirmation is clicked by the driver as soon as observed, with no intentional human delay. Optional Andromeda endpoints point only to the loopback fixture.',engines};
  fs.writeFileSync(path.join(output,'single-quote.json'),JSON.stringify(report,null,2));
  for(const file of ['single-quote.json','chromium-1280-quote.png','firefox-rdm-430-quote.png'])emitFirstOpenFile(output,file,'SEARCH3_SINGLE_QUOTE_FRAME');
  for(const [name,e] of Object.entries(engines))console.log('PASS single quote',name,e.groups.clicks,e.groups.visible);
}
if(require.main===module)main().catch(error=>{console.error(error);process.exitCode=1;});
module.exports={check,observeQuote,quoteFixture};
