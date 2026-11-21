'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {setTimeout: delay} = require('node:timers/promises');
const {gzipSync} = require('node:zlib');
const {entry, sha, openReplay, liveEntry, measureEngines, emitFirstOpenFile} = require('./search3-visual-first-open.cjs');
const {privateTVFixture} = require('./search3-visual-messenger-browser.cjs');
const {profile, trip} = require('./search3-visual-live-fixture.cjs');

const scenarios = [
  {name:'anex-page', count:7, offers:250, anexDelay:20, tvStatusDelay:700, first:'anex'},
  {name:'tourvisor-first', count:5, offers:1, anexDelay:2050, tvStatusDelay:0, first:'tourvisor'},
];
const ref = 'anex_online:' + 'b'.repeat(64);
const offerKeys = {anex:encodeURIComponent('anex:' + ref),tourvisor:encodeURIComponent('tourvisor:visual-tv-101')};
const hotel = '#hotel-' + profile.id;
const cardCTA = hotel + ' [data-card-entry]';

function makeDelivery(transport, scenario) {
  const tv = privateTVFixture(transport, 'http://127.0.0.1');
  const calls = [];
  const amounts = {anex:scenario.offers === 1 ? 121000 : 119000,tourvisor:120000};
  async function reply(url, body, method, payload) {
    const action = url.searchParams.get('action') || body.action || 'read';
    const anex = url.pathname === '/_preview/search3-anex-candidate/api-anex-search3-preview.php';
    const call = {action,path:url.pathname,started:Date.now()};
    calls.push(call);
    if (action === 'tv_search' || (anex && action === 'search')) call.params = body.params;
    if (anex && action === 'search' && scenario.anexDelay) await delay(scenario.anexDelay);
    if (anex && action === 'tv_status' && scenario.tvStatusDelay) await delay(scenario.tvStatusDelay);
    assert.ok(!/quote|handoff|booking|payment|lead/i.test(action), 'unexpected mutation/quote ' + action);
    let response;
    if (action.startsWith('tv_')) {
      assert.ok(anex && ['tv_token','tv_search','tv_status','tv_results','tv_continue'].includes(action));
      response = await tv.handle(action,body,method);
    } else {
      response = await transport.json(url.href,{body:payload});
    }
    if (url.pathname.endsWith('/hotel-details-read-v1.php')) {
      const withLocalPhoto=item=>item?.id===profile.id ? {...item,images:[entry+'assets/photo-1.jpg']} : item;
      if (response.items) response.items=response.items.map(withLocalPhoto);
      if (response.item) response.item=withLocalPhoto(response.item);
    }
    if (anex && action === 'search') {
      const tours = response.data.hotels[0].tours;
      const original = tours[0];
      // A full, valid retained page. Stable unique group minima represent
      // distinct room/program variants of the same canonical hotel, date and party.
      if (scenario.offers > 1) response.data.hotels[0].tours = Array.from({length:scenario.offers}, (_,i) => ({
        ...original,room:i ? 'ANEX STANDARD · программа ' + String(i).padStart(3,'0') : original.room,
        offer_ref:i ? 'anex_online:' + sha('search3-first-tours:anex-variant:' + i) : ref,
        price:{amount:String(amounts.anex + i * 100),currency:'RUB'},
      }));
      const offers = response.data.hotels[0].tours;
      assert.equal(new Set(offers.map(o=>o.offer_ref)).size,scenario.offers);
      call.firstGroup={offerRef:offers[0].offer_ref,searchRef:offers[0].search_ref,hotelId:response.data.hotels[0].local_id,generation:response.data.generation};
      call.offers = offers.length;
      call.minimum = Math.min(...offers.map(o=>Number(o.price.amount)));
      // Date-independent digest allows exact comparison across runner midnights.
      call.inventorySHA256 = sha(JSON.stringify(offers.map(o=>[o.offer_ref,o.room,o.price.amount,o.price.currency])));
    }
    if (anex && action==='expand') {
      call.parent={offerRef:body.offer_ref,searchRef:body.search_ref,hotelId:body.local_hotel_id,generation:body.generation};
      const expanded=response.data.hotels;
      assert.equal(expanded.length,1);
      assert.equal(expanded[0].tours.length,1);
      const tour=expanded[0].tours[0];
      assert.equal(tour.kind,'concrete');
      call.concrete={key:encodeURIComponent('anex:'+tour.offer_ref),room:tour.room,amount:Number(tour.price.amount)};
    }
    call.sha256 = sha(JSON.stringify(response));
    call.completed = Date.now();
    return response;
  }
  return {calls,amounts,reply,tv};
}

// All critical timestamps are sampled by the rendered page, independently of
// WebDriver polling. A price and its native action must survive two painted
// frames with unobstructed hit targets before they qualify.
function watchCards(expected) {
  const state = window.__firstTours = {viewport:[innerWidth,innerHeight],timeOrigin:performance.timeOrigin};
  const readBox = el => {
    const r = el.getBoundingClientRect();
    const hit = document.elementFromPoint(r.left + r.width/2,r.top + r.height/2);
    return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,width:r.width,height:r.height,
      visible:r.width>0 && r.height>0 && r.top>=0 && r.bottom<=innerHeight && r.left>=0 && r.right<=innerWidth &&
        getComputedStyle(el).visibility === 'visible' && (hit===el || el.contains(hit))};
  };
  function read() {
    const card = document.querySelector('#cards .hotel-card');
    const button = card?.querySelector('[data-card-entry]'), price = card?.querySelector('.starting-price strong');
    if (!button || !price || button.disabled || card.dataset.hotelId !== String(expected.id) ||
        card.querySelector('.hotel-card-name')?.textContent.trim() !== expected.name) return null;
    const key = button.dataset.key, provider = Object.keys(expected.keys).find(source=>expected.keys[source]===key);
    const amount = Number(price.textContent.replace(/\D/g,''));
    if (!provider || expected.amounts[provider]!==amount || !price.textContent.includes('₽')) return null;
    const nameBox=readBox(card.querySelector('.hotel-card-name')),priceBox=readBox(price),buttonBox=readBox(button);
    return {ms:performance.now()-state.start,key,provider,amount,label:button.textContent.trim(),scrollY,
      name:nameBox,price:priceBox,button:buttonBox,visible:priceBox.visible && buttonBox.visible};
  }
  let previous = null;
  function frame() {
    if (state.stopped) return;
    const current = read();
    if (current) {
      state.dom ??= current;
      if (!state.identity && (current.name.visible || current.price.visible)) {
        state.identity={ms:current.ms,key:current.key,provider:current.provider,name:current.name,price:current.price,scrollY:current.scrollY};
      }
      if (current.visible && previous?.visible && current.key===previous.key &&
          Math.abs(current.button.top-previous.button.top)<1 && Math.abs(current.price.top-previous.price.top)<1) {
        state.visible = previous;
        return;
      }
    }
    previous = current;
    if (state.start !== undefined && !state.stopped) requestAnimationFrame(frame);
  }
  window.__currentTourCard = read;
  document.addEventListener('click',event=>{
    if (event.target.closest('.search-submit') && state.start===undefined) {
      state.start=performance.now();state.trustedClick=event.isTrusted;state.initialScrollY=scrollY;
      requestAnimationFrame(frame);
    }
    if (event.target.closest('[data-card-entry]')) state.opened={key:event.target.closest('[data-card-entry]').dataset.key,trusted:event.isTrusted};
  },true);
}

async function until(driver, expression, milliseconds) {
  const stop=Date.now()+milliseconds;
  do {
    const value=await driver.evaluate(expression);
    if (value) return value;
    await delay(45);
  } while (Date.now()<stop);
  return null;
}

function summary(times) {
  if (!times.length) return {n:0,medianMs:null,minMs:null,maxMs:null};
  const sorted=times.slice().sort((a,b)=>a-b), middle=Math.floor(sorted.length/2);
  return {n:sorted.length,medianMs:sorted.length%2?sorted[middle]:(sorted[middle-1]+sorted[middle])/2,
    minMs:sorted[0],maxMs:sorted.at(-1)};
}

async function measure(driver, engine, size, root, html, evidence) {
  const groups=[];
  for (const scenario of scenarios) {
    const samples=[];
    for (let i=0;i<scenario.count;i++) {
      const replay=await openReplay(root,html,0,transport=>makeDelivery(transport,scenario));
      const {calls,amounts}=replay.delivery;
      try {
        await driver.go(replay.origin+entry+'?'+new URLSearchParams({...trip,ages:''}));
        assert.ok(await until(driver,'window.__firstOpen?.readyMs || null',10000),'hydrated form');
        assert.deepEqual(await driver.evaluate('window.__firstOpen.viewport'),size);
        const expected={id:profile.id,name:profile.name,keys:offerKeys,amounts};
        await driver.evaluate('('+watchCards.toString()+')('+JSON.stringify(expected)+')');
        assert.equal(await driver.evaluate('document.querySelectorAll("#cards .hotel-card").length'),0);
        replay.allowClick();
        await driver.click('.search-submit');
        const dom=await until(driver,'window.__firstTours.dom || null',10000);
        assert.ok(dom,'missing canonical priced card '+JSON.stringify({calls,blocked:replay.blocked}));
        assert.equal(dom.provider,scenario.first,'first priced card came from the independently ready source');
        const visible=await until(driver,'window.__firstTours.visible || null',3300);
        // Freeze the unassisted result before any explicit or driver-induced scroll.
        await driver.evaluate('window.__firstTours.stopped=true');
        if (!visible) await driver.evaluate('document.querySelector("'+hotel+' .hotel-price").scrollIntoView({block:"center",behavior:"instant"})');
        const reachable=await until(driver,'window.__currentTourCard()?.visible && window.__currentTourCard()',3000);
        assert.ok(reachable,'card must be reachable '+JSON.stringify({dom,visible}));
        if (i===0 && scenario===scenarios[0]) await driver.screenshot(path.join(evidence,engine+'.png'));
        if (visible) assert.equal(visible.key,reachable.key,'the measured entry remained actionable');
        await driver.click(cardCTA+'[data-key="'+reachable.key+'"]');
        // ANEX replaces its group minimum with the concrete tariff. Poll the
        // finished room screen even if the intermediate group was never painted.
        const group=reachable.provider==='anex';
        const row=group?'#modal .hotel-room-card[data-room="ANEX CONCRETE"] .room-offer-choice':'#modal .room-offer-choice[data-offer-key="'+reachable.key+'"]';
        const opened=await until(driver,`(()=>{
          if(!document.querySelector('.hotel-section-nav[data-hotel-id="${profile.id}"]'))return null;
          const row=document.querySelector(${JSON.stringify(row)});if(!row)return null;
          return {key:row.dataset.offerKey,room:row.closest('.hotel-room-card').dataset.room,
            amount:Number(row.querySelector('.hotel-room-price>strong').textContent.replace(/\\D/g,''))};
        })()`,8000);
        if (!opened) {
          const screen = await driver.evaluate(`({
            click:window.__firstTours.opened,
            title:document.querySelector('#modal-title')?.textContent,
            hotel:document.querySelector('.hotel-section-nav')?.dataset.hotelId,
            offers:Array.from(document.querySelectorAll('#modal [data-offer-key]'), e => e.dataset.offerKey).slice(0,4)
          })`);
          assert.fail('missing final lodging offer: '+JSON.stringify({screen,scenario:scenario.name,expand:calls.filter(c=>c.action==='expand'),blocked:replay.blocked}));
        }
        const expansions=calls.filter(c=>c.action==='expand');
        assert.equal(expansions.length,group?1:0);
        if(group){
          const parent=calls.find(c=>c.action==='search'&&c.firstGroup).firstGroup;
          assert.equal(encodeURIComponent('anex:'+parent.offerRef),reachable.key);
          assert.deepEqual(expansions[0].parent,parent,'expand targets the clicked group in the same hotel and search');
          assert.deepEqual(opened,expansions[0].concrete,'terminal room, amount and tariff key match the expansion response');
        }else{
          assert.equal(opened.key,reachable.key,'the Tourvisor tariff keeps its offer key');
          assert.equal(opened.amount,reachable.amount);
        }
        const result=await driver.evaluate('window.__firstTours');
        assert.equal(result.trustedClick,true);assert.equal(await driver.evaluate('window.__firstOpen.trustedSubmit'),true);
        assert.deepEqual(result.opened,{key:reachable.key,trusted:true});
        for (const action of ['tv_token','tv_search','search']) {
          const records=calls.filter(c=>c.action===action);
          assert.equal(records.length,1,action+' must be dispatched exactly once');
          if (action==='tv_search') {
            const p=records[0].params;
            assert.deepEqual([String(p.departureId),String(p.countryId),p.dateFrom,p.dateTo,p.nightsFrom,p.nightsTo,p.adults,p.childs],
              ['1','4',trip.from,trip.to,7,7,2,[]]);
          }
        }
        const slow = calls.find(c=>c.action===(scenario.first==='anex'?'tv_status':'search'));
        const elapsed = time=>time ? time - result.timeOrigin - result.start : null;
        const slowerDone=elapsed(slow?.completed);
        const independentAtDOM=slowerDone===null || dom.ms<slowerDone;
        assert.ok(independentAtDOM,'first source visibly represented in DOM before slower response');
        const scripts={};
        for (const key of ['visual-search/app.js','prototype-search/data.js']) {
          scripts[key]=sha(fs.readFileSync(path.join(root,key)));
          assert.equal(replay.scripts[key],scripts[key],'compiled bytes '+key);
        }
        assert.deepEqual(replay.blocked,[]);
        samples.push({dom,identity:result.identity||null,visible,assisted:!visible,opened:result.opened,openedRoom:opened,reachable,initialScrollY:result.initialScrollY,
          trustedSearch:true,scripts,independentAtDOM,independentAtVisible:visible?(slowerDone===null || visible.ms<slowerDone):null,
          calls:calls.map(({started,completed,...request})=>({...request,startMs:elapsed(started),endMs:elapsed(completed)}))});
      } finally {await replay.close();}
    }
    groups.push({scenario,dom:summary(samples.map(s=>s.dom.ms)),identity:summary(samples.flatMap(s=>s.identity?[s.identity.ms]:[])),
      visible:summary(samples.flatMap(s=>s.visible?[s.visible.ms]:[])),samples});
  }
  return groups;
}

async function run() {
  const root=fs.realpathSync(process.argv[2]),evidence=path.resolve(process.argv[3]);
  fs.mkdirSync(evidence,{recursive:true});
  const {html,source,entrySHA256}=liveEntry(root);
  const report={source,entrySHA256,trip,hotel:{id:profile.id,name:profile.name},keys:offerKeys,
    method:'Trusted search click to first animation frame with exact retained canonical offer and ruble amount. Usable time requires two stable frames with price and native CTA entirely inside the viewport and hit-tested. A below-fold result has no usable time; any explicit recovery scroll happens after measurement. Each sample uses a new local origin, no-store HTTP and the normal compiled PHP/JS entry. Catalog and token have no added delay. No supplier or lead traffic.',
    engines:await measureEngines(root,html,(driver,engine,size)=>measure(driver,engine,size,root,html,evidence))};
  fs.writeFileSync(path.join(evidence,'first-tours.json.gz'),gzipSync(JSON.stringify(report)));
  for (const file of ['chromium-1280.png','firefox-rdm-430.png','first-tours.json.gz']) emitFirstOpenFile(evidence,file,'SEARCH3_FIRST_TOURS_FRAME');
  for (const [engine,result] of Object.entries(report.engines)) console.log('PASS first tours',engine,
    result.groups.map(g=>({scenario:g.scenario.name,dom:g.dom,visible:g.visible})));
}
if (require.main===module) run().catch(error=>{console.error(error);process.exitCode=1;});
module.exports={makeDelivery,until,summary,offerKeys};
