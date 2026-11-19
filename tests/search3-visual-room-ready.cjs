'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {setTimeout:delay}=require('node:timers/promises');
const {gzipSync}=require('node:zlib');
const {entry,sha,openReplay,liveEntry,measureEngines,emitFirstOpenFile}=require('./search3-visual-first-open.cjs');
const {makeDelivery,until,summary,offerKeys}=require('./search3-visual-first-tours.cjs');
const {profile,trip,tour}=require('./search3-visual-live-fixture.cjs');

const key=offerKeys.tourvisor;
const selector='[data-offer-key="'+key+'"]';
const choose=selector+' [data-action="select-hotel-offer"]';
const card='#hotel-'+profile.id+' [data-card-entry][data-key="'+key+'"]';
const warmCopy='Рядом с отелем находится песчаный пляж.';
const freshCopy='Отель обновил описание пляжа: к морю ведёт дорожка через сад.';
const reader='/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php';

function fixtureForRooms(transport,delayMs) {
  const delivery=makeDelivery(transport,{offers:1,anexDelay:0,tvStatusDelay:0});
  const reads=[],quotes=[];
  let armed=false;
  return {reads,quotes,arm(){assert.equal(reads.length,1,'one complete prewarm');assert.ok(reads[0].finished);armed=true;},
    async reply(url,body,method,payload) {
      const action=url.searchParams.get('action') || body.action;
      if (url.pathname===reader) {
        assert.deepEqual([...url.searchParams.entries()],[['oldLocalHotelIds[]',String(profile.id)]]);
        assert.equal(method,'GET');
        const revision=armed?2:1, recorded={revision,start:Date.now()};
        reads.push(recorded);
        if (armed && delayMs) await delay(delayMs);
        const result={ok:true,source:'anytour-local-tv-catalog-v1',catalog:'local-tv',requestedIds:[profile.id],missingIds:[],
          items:[{id:101,catalog:'local-tv',revision,description:armed?freshCopy:warmCopy}],
          links:[{oldLocalId:profile.id,tourvisorHotelId:101}]};
        recorded.sha256=sha(JSON.stringify(result));recorded.finished=Date.now();
        return result;
      }
      if (['tv_tour','tv_select','tv_flights'].includes(action)) {
        assert.equal(url.pathname,'/_preview/search3-anex-candidate/api-anex-search3-preview.php');
        const result=await delivery.tv.handle(action,body,method);
        quotes.push({action,ref:body.offer_ref});
        return result;
      }
      if (action==='handoff_token') {
        // Stop at the private local contact boundary once the chosen price has been verified.
        return {ok:false,error:'signer_unavailable'};
      }
      return delivery.reply(url,body,method,payload);
    }};
}

// Sample at paint time inside the actual modal. No scrolling, focus or synthetic
// click is performed by the observer. Only the real product positions the room.
function observeRoom(expected) {
  const state=window.__roomReady={timeOrigin:performance.timeOrigin,viewport:[innerWidth,innerHeight]};
  function snapshot() {
    const modal=document.querySelector('#modal[open]'),body=modal?.querySelector('#modal-body');
    const row=body?.querySelector(expected.row);
    const room=row?.closest('[data-room]'),name=room?.querySelector('h4'),meal=row?.querySelector('.room-offer-meal');
    const price=row?.querySelector('.hotel-room-price > strong'),action=row?.querySelector('[data-action="select-hotel-offer"]');
    if (!action||action.disabled||body.querySelector('.hotel-section-nav')?.dataset.hotelId!==String(expected.id) ||
        action.dataset.key!==expected.key||room.dataset.room!==expected.room||name?.textContent.trim()!==expected.room||
        meal?.textContent.trim()!=='Всё включено'||Number(price?.textContent.replace(/\D/g,''))!==expected.amount||
        !price.textContent.includes('₽')||action.textContent.trim()!=='Проверить цену') return null;
    const bounds=body.getBoundingClientRect();
    function geometry(el) {
      const r=el.getBoundingClientRect(), hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);
      return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,width:r.width,height:r.height,
        visible:r.width>0 && r.height>0 && r.top>=Math.max(0,bounds.top)-1 && r.bottom<=Math.min(innerHeight,bounds.bottom)+1 &&
          r.left>=0 && r.right<=innerWidth+1 && getComputedStyle(el).visibility==='visible' && (hit===el || el.contains(hit))};
    }
    const boxes={name:geometry(name),meal:geometry(meal),price:geometry(price),action:geometry(action)};
    return {ms:performance.now()-state.start,key:action.dataset.key,room:room.dataset.room,meal:meal.textContent.trim(),
      amount:expected.amount,scrollTop:body.scrollTop,focusKey:document.activeElement?.dataset.key||'',
      viewportTop:bounds.top,boxes,visible:Object.values(boxes).every(b=>b.visible)};
  }
  window.__roomSnapshot=snapshot;
  let last=null;
  function frame() {
    const current=snapshot();
    if (current) {
      state.dom??=current;
      if (current.visible && last?.visible && Math.abs(current.boxes.action.top-last.boxes.action.top)<1) {
        state.visible=last;
        return;
      }
    }
    last=current;
    if (!state.stop) requestAnimationFrame(frame);
  }
  document.addEventListener('click',event=>{
    if (event.target.closest(expected.card) && state.start===undefined) {
      state.start=performance.now();state.trustedEntry=event.isTrusted;requestAnimationFrame(frame);
    }
    const action=event.target.closest('[data-action="select-hotel-offer"]');
    if (action) state.checked={key:action.dataset.key,trusted:event.isTrusted};
  },true);
}

async function measure(driver,engine,size,root,html,evidence) {
  const groups=[];
  for (const [delayMs,count] of [[2000,5],[0,3]]) {
    const samples=[];
    for (let i=0;i<count;i++) {
      const replay=await openReplay(root,html,0,transport=>fixtureForRooms(transport,delayMs));
      const fx=replay.delivery;
      try {
        await driver.go(replay.origin+entry+'?'+new URLSearchParams({...trip,ages:''}));
        assert.ok(await until(driver,'window.__firstOpen?.readyMs || null',10000));
        assert.deepEqual(await driver.evaluate('window.__firstOpen.viewport'),size);
        assert.equal(await driver.evaluate('V2_CONFIG.localTvCatalogEnabled && V2_CONFIG.search3TVSessionEnabled'),true);
        replay.allowClick();await driver.click('.search-submit');
        const resource=JSON.stringify(reader+'?');
        assert.ok(await until(driver,'document.querySelector("#search-status").hidden && document.querySelector('+JSON.stringify(card)+') && performance.getEntriesByType("resource").some(r=>r.name.includes('+resource+') && r.responseEnd>0)',10000),
          'search must finish and the first LOCAL response reach the browser before entry');
        assert.equal(await driver.evaluate('document.querySelector('+JSON.stringify(card)+').textContent.trim()'),'Номера и цены');
        assert.deepEqual(fx.quotes,[]);
        const expected={id:profile.id,key,row:selector,card,room:tour.roomType,amount:tour.price};
        await driver.evaluate('('+observeRoom.toString()+')('+JSON.stringify(expected)+')');
        fx.arm();
        await driver.click(card);
        const dom=await until(driver,'window.__roomReady.dom || null',9000);
        assert.ok(dom,'no exact room after native click '+JSON.stringify({reads:fx.reads,blocked:replay.blocked}));
        const visible=await until(driver,'window.__roomReady.visible || null',2200);
        await driver.evaluate('window.__roomReady.stop=true');
        if (!visible) await driver.evaluate('document.querySelector('+JSON.stringify(selector)+').closest("[data-room]").scrollIntoView({block:"center",behavior:"instant"})');
        const available=await until(driver,'window.__roomSnapshot()?.boxes.action.visible && window.__roomSnapshot()',2500);
        assert.ok(available,'actual tariff action unreachable '+JSON.stringify(dom));
        await driver.evaluate('(()=>{window.__roomFocusNode=document.querySelector('+JSON.stringify(choose)+');window.__roomFocusNode.focus({preventScroll:true});return true})()');
        const before=await driver.evaluate('window.__roomSnapshot()');
        assert.equal(before.focusKey,key);
        if (i===0 && delayMs) await driver.screenshot(path.join(evidence,engine+'.png'));
        const fresh=JSON.stringify(freshCopy);
        assert.ok(await until(driver,'document.querySelector("#modal-body")?.textContent.includes('+fresh+')',8000),'updated published LOCAL description');
        await driver.evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve(true))))');
        const after=await driver.evaluate('window.__roomSnapshot()');
        assert.equal(await driver.evaluate('window.__roomFocusNode===document.activeElement'),true,'the exact actionable DOM node survives LOCAL refresh');
        assert.equal(after?.focusKey,key,'profile refresh retains keyboard focus on the identical offer');
        assert.equal(after.key,key);assert.equal(after.boxes.action.visible,true);
        const topDelta=after.boxes.action.top-before.boxes.action.top;
        assert.ok(Math.abs(topDelta)<=3,'focused room action moved after profile refresh: '+topDelta);
        assert.deepEqual(fx.reads.map(r=>r.revision),[1,2],'one warm and one explicit read of the same hotel');
        assert.deepEqual(fx.quotes,[],'opening an exact TV room never requests a quote');
        await driver.click(choose);
        const accepted=await until(driver,'document.querySelector("#prototype-lead-form") && document.querySelector("#modal-footer .footer-total > strong")?.textContent',8000);
        assert.equal(Number(String(accepted).replace(/\D/g,'')),tour.price,'verified price from the same TV offer');
        assert.deepEqual(fx.quotes.filter(c=>c.action==='tv_tour'),[{action:'tv_tour',ref:'tourvisor:'+sha('tourvisor:tour:'+tour.id)}]);
        const page=await driver.evaluate('window.__roomReady');
        assert.equal(page.trustedEntry,true);assert.deepEqual(page.checked,{key,trusted:true});
        const clickedAt=page.timeOrigin+page.start;
        const reads=fx.reads.map(r=>({revision:r.revision,sha256:r.sha256,startMs:r.start-clickedAt,finishMs:r.finished-clickedAt}));
        assert.ok(reads[0].finishMs<0 && reads[1].startMs>=-3,'only the second LOCAL request followed this native click');
        assert.ok(reads[1].finishMs-reads[1].startMs>=delayMs-2);
        const scripts={};
        for (const asset of ['visual-search/app.js','visual-search/hotel-details-v1.js','prototype-search/data.js']) {
          scripts[asset]=sha(fs.readFileSync(path.join(root,asset)));assert.equal(replay.scripts[asset],scripts[asset]);
        }
        assert.deepEqual(replay.blocked,[]);
        samples.push({dom,visible,assisted:!visible,reads,scripts,topDelta,before,after,checked:page.checked,
          trustedEntry:true,quoteRef:fx.quotes[0].ref,readyBeforeProfile:visible?visible.ms<reads[1].finishMs:null});
      } finally {await replay.close();}
    }
    groups.push({delayMs,count,dom:summary(samples.map(s=>s.dom.ms)),visible:summary(samples.flatMap(s=>s.visible?[s.visible.ms]:[])),samples});
  }
  return groups;
}

async function run() {
  const root=fs.realpathSync(process.argv[2]),evidence=path.resolve(process.argv[3]);
  fs.mkdirSync(evidence,{recursive:true});
  const {html,source,entrySHA256}=liveEntry(root);
  const report={source,entrySHA256,trip,hotel:profile.id,key,room:tour.roomType,amount:tour.price,
    method:'Normal compiled PHP and private TV search; one fresh no-store loopback origin per navigation. Warm LOCAL profile 1 completes before the native hotel card click. Only the explicit modal GET of profile 2 has the indicated delay. Readiness is the first of two stable paint frames where the matching room name, meal, RUB price and native verification control are all visible and hit-tested. Any explicit recovery scroll follows the measurement. No external supplier or lead traffic.',
    engines:await measureEngines(root,html,async(driver,engine,size)=>{
      const groups=await measure(driver,engine,size,root,html,evidence);
      const readingPosition=await require('./search3-visual-reading-position.cjs').measureReadingPosition(driver,root,html);
      console.log('SEARCH3_READING_POSITION',engine,JSON.stringify(readingPosition));
      return groups;
    })};
  fs.writeFileSync(path.join(evidence,'room-ready.json.gz'),gzipSync(JSON.stringify(report)));
  for (const file of ['chromium-1280.png','firefox-rdm-430.png','room-ready.json.gz']) emitFirstOpenFile(evidence,file,'SEARCH3_ROOM_READY_FRAME');
  for (const [engine,result] of Object.entries(report.engines)) console.log('PASS room ready',engine,
    result.groups.map(g=>({delayMs:g.delayMs,dom:g.dom,visible:g.visible})));
}
if (require.main===module) run().catch(error=>{console.error(error);process.exitCode=1;});
