'use strict';

// A mixed 250-fare page delivered through the real private TV session adapter.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {trip, tour, flights, profile} = require('./search3-visual-live-fixture.cjs');
const {privateTVFixture} = require('./search3-visual-messenger-browser.cjs');
const {entry, openReplay, liveEntry, measureEngines, emitFirstOpenFile} = require('./search3-visual-first-open.cjs');
const {until} = require('./search3-visual-first-tours.cjs');

const room = 'Семейный номер с балконом';
const meal = 'Всё включено';
const basePrice = 169500;
const supplement = 1300;
const rooms = ['Стандартный номер', room, 'Семейный номер с видом на сад', 'Двухкомнатный люкс', 'Стандарт с балконом'];
const offers = Array.from({length:250}, (_, i) => ({
  ...tour, id:'family-fare-' + i, childs:1, placement:'2 взрослых + 1 ребёнок', operator:{name:'FUN&SUN'},
  roomType:rooms[i % rooms.length], meal:i % 2 ? {id:7,name:'AI'} : {id:3,name:'BB'},
  price:i === 231 ? basePrice : i % rooms.length === 1 ? (i % 2 ? 185000 : 138000) + i : 145000 + i,
}));
const familyAI = offers.filter(o => o.roomType === room && o.meal.id === 7);
const chosen = familyAI.reduce((a,b) => a.price < b.price ? a : b);
const key = encodeURIComponent('tourvisor:' + chosen.id);
const selected = '.room-offer-choice[data-offer-key="' + key + '"]';
const targetRoom = '.hotel-room-card[data-room="' + room + '"]';
const card = '#hotel-' + profile.id;
const price = value => Number(String(value).replace(/\D/g, ''));

function familyTransport(transport) {
  const csrf='a'.repeat(64), requests=[], freezes=[];
  const tv = privateTVFixture({json:async address => {
    const url=new URL(address), action=url.searchParams.get('action');
    if (action==='search_results') return [{id:101,provider:'tourvisor',tours:offers}];
    if (action==='tour' || action==='flights') {
      assert.equal(url.searchParams.get('tourId'), chosen.id, 'only the lowest qualifying room may be quoted');
      if (action==='tour') return {...chosen,hotel:{id:101,name:profile.name}};
      return flights.map((pair,i)=>({...pair,price:{value:basePrice+(i ? supplement : 0)}}));
    }
    return transport.json(address);
  }},'http://127.0.0.1');
  return {requests,freezes,async reply(url,body,method,payload) {
    const action=url.searchParams.get('action')||body.action||'read';
    requests.push(action);
    if (action.startsWith('tv_')) {
      if (action==='tv_search') {assert.equal(body.params.adults,2);assert.deepEqual(body.params.childs,[7]);}
      return tv.handle(action,body,method);
    }
    if (action==='handoff_token') return {ok:true,csrf};
    if (action==='handoff_freeze') {
      const {action:_,csrf:token,...pins}=body;assert.equal(token,csrf);
      const fact=tv.freeze(pins);freezes.push(fact.observation);return {ok:true,data:fact};
    }
    assert(!/handoff|lead|booking|payment|quote/.test(action),'unexpected operation '+action);
    const result=await transport.json(url.href,{body:payload});
    if ((url.pathname.endsWith('/api-andromeda-search3-preview.php') || url.pathname.endsWith('/api-anex-search3-preview.php')) && Array.isArray(result.data?.hotels)) {
      result.data.hotels=[];if ('received_offers' in result.data) result.data.received_offers=result.data.mapped_offers=0;
    }
    if (url.pathname.endsWith('/hotel-details-read-v1.php')) {
      for(const item of result.items||[])item.images=[];
      if (result.item)result.item.images=[];
    }
    return result;
  }};
}

function firstScreen() {
  const box=selector=>{
    const el=document.querySelector(selector);
    const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);
    return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,visible:r.height>0&&r.width>0&&
      r.top>=0&&r.left>=0&&r.bottom<=innerHeight+1&&r.right<=innerWidth+1&&(hit===el||el.contains(hit))};
  };
  const title=document.querySelector('#modal-title'), text=document.createRange();
  text.selectNodeContents(title);
  const ink=text.getBoundingClientRect(),back=document.querySelector('#modal-back').getBoundingClientRect();
  const close=document.querySelector('#modal [data-action="close-modal"]').getBoundingClientRect();
  return {title:title.textContent,titleFits:ink.width>0&&ink.left>=back.right-1&&ink.right<=close.left+1&&ink.top>=0&&ink.bottom<=innerHeight,
    scroll:document.querySelector('#modal-body').scrollTop,hotel:box('.application-review-heading strong'),
    stay:box('.application-review-stay'),party:box('.application-review-heading small'),
    base:box('.application-price-breakdown dd'),total:box('.application-price-breakdown [data-price-part="total"] dd'),
    telegram:box('[data-action="messenger-open"][data-platform="telegram"]'),max:box('[data-action="messenger-open"][data-platform="max"]')};
}

async function family250(driver,engine,size,root,html,output,summaryFirst=false) {
  const replay=await openReplay(root,html,0,familyTransport);
  const read=expression=>driver.evaluate(expression);
  const query=selector=>'document.querySelector('+JSON.stringify(selector)+')';
  const text=selector=>read(query(selector)+'?.textContent?.trim()');
  const wait=async(selector,why)=>assert.ok(await until(driver,'!!'+query(selector),10000),why||selector);
  const choose=async selector=>{await wait(selector);await driver.click(selector);};
  const totalSelector='#modal-footer .footer-total > strong';
  const ready='[data-action="messenger-open"][data-platform="telegram"]:not(:disabled)';
  try {
    await driver.go(replay.origin+entry+'?'+new URLSearchParams({...trip,adults:2,ages:'7'}));
    assert.ok(await until(driver,'window.__firstOpen?.readyMs || null',10000));
    replay.allowClick();
    await choose('.search-submit:not(:disabled)');
    await choose(card+' [data-card-entry]');
    await wait('#hotel-room-meal');
    assert.match(await text('#hotel-room-count'), /Туры:\s*250\b/);
    // Dispatch the standard select control's change action, using its actual options
    // and exactly the same form value as a browser selecting the visible label.
    const filter=await read(`(() => {
      const select=document.getElementById('hotel-room-meal');
      const option=[...select.options].find(o=>o.textContent.trim()===${JSON.stringify(meal)});
      if(!option||!select.getClientRects().length)return null;
      select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));return select.value;
    })()`);
    assert.equal(filter,meal);
    assert.match(await text('#hotel-room-count'),/Туры:\s*125\b/);
    if(!await read(query(targetRoom)+'.open'))await choose(targetRoom+' > summary');
    const visibleWinner=await read(`(() => {
      const r=document.querySelector(${JSON.stringify(targetRoom)});
      return [...r.querySelectorAll('.room-offer-choice')].filter(e=>e.querySelector('[data-action="select-hotel-offer"]').getClientRects().length)
        .map(e=>({key:e.dataset.offerKey,price:Number(e.querySelector('.hotel-room-price strong').textContent.replace(/\\D/g,'')),meal:e.querySelector('.room-offer-meal').textContent.trim()}));
    })()`);
    assert.ok(visibleWinner.length>0);assert.deepEqual(visibleWinner[0],{key,price:basePrice,meal});
    await choose(selected+' [data-action="select-hotel-offer"]');
    await wait(ready);assert.equal(price(await text(totalSelector)),basePrice);
    assert.match(await text('.application-review-heading'),/Семейный номер с балконом/);
    assert.match(await text('.application-review-heading'),/Всё включено/);
    assert.match(await text('.application-review-heading'),/7 лет/);
    await choose('#modal-back');
    await wait(selected+'[aria-current="true"]');
    assert.equal(await read(query('#hotel-room-meal')+'.value'),meal);
    await choose(selected+' [data-action="hotel-flights"]');
    await choose(selected+' [name="flight-pair"][value="1"]');
    await choose('#hotel-detail-offers');
    await wait(ready);
    const amounts=await read("Array.from(document.querySelectorAll('.application-price-breakdown dd'),e=>Number(e.textContent.replace(/\\D/g,'')))");
    assert.deepEqual(amounts,[basePrice,supplement,basePrice+supplement]);
    assert.equal(price(await text(totalSelector)),basePrice+supplement);
    const layout=await read('('+firstScreen.toString()+')()');
    assert.equal(layout.title,'Связь по выбранному туру');
    assert.ok(layout.titleFits,'contact title fits between navigation controls: '+JSON.stringify(layout));
    assert.equal(layout.scroll,0,'measure the initial application viewport');
    assert.ok(layout.hotel.visible&&layout.stay.visible&&layout.total.visible&&layout.telegram.visible&&layout.max.visible,JSON.stringify(layout));
    if(size[0]===430){
      if(summaryFirst)assert.ok(layout.total.bottom<=layout.telegram.top&&layout.stay.bottom<=layout.telegram.top&&layout.party.visible,JSON.stringify(layout));
      else assert.ok(layout.telegram.bottom<layout.hotel.top,JSON.stringify(layout));
    }else assert.ok(layout.hotel.right<=layout.telegram.left,JSON.stringify(layout));
    if(output)await driver.screenshot(path.join(output,engine+'-family-total.png'));
    await choose('#modal-back');await wait(selected+'[aria-current="true"]');
    assert.equal(await read(query('#hotel-room-meal')+'.value'),meal);
    assert.equal(await read(query(selected+' [name="flight-pair"]:checked')+'?.value'),'1');
    await choose('#hotel-detail-offers');await wait(ready);
    assert.equal(price(await text(totalSelector)),basePrice+supplement);
    const fx=replay.delivery,last=fx.freezes.at(-1);
    assert.equal(last.stay.room,room);assert.deepEqual(last.trip.childAges,[7]);
    assert.equal(last.flights.state,'selected');assert.equal(Number(last.price.amount),basePrice+supplement);
    assert.equal(fx.requests.filter(a=>a==='tv_tour').length,1);assert.equal(fx.requests.filter(a=>a==='tv_flights').length,1);
    assert.deepEqual(replay.blocked,[]);
    return {engine,size,offers:offers.length,matchingFamilyOffers:familyAI.length,room,meal,chosen:chosen.id,
      base:basePrice,supplement,total:basePrice+supplement,returnFlight:1,submitted:false,layout};
  } finally {await replay.close();}
}

async function main() {
  const root=fs.realpathSync(process.argv[2]),output=path.resolve(process.argv[3]);fs.mkdirSync(output,{recursive:true});
  const {source,html}=liveEntry(root);
  const engines=await measureEngines(root,html,(driver,engine,size)=>family250(driver,engine,size,root,html,output,process.argv[4]==='summary-first'));
  fs.writeFileSync(path.join(output,'family-250.json'),JSON.stringify({source,engines},null,2));
  for(const file of ['family-250.json','chromium-1280-family-total.png','firefox-rdm-430-family-total.png'])emitFirstOpenFile(output,file,'SEARCH3_FAMILY_250_FRAME');
  console.log('PASS family 250',JSON.stringify(engines));
}
if(require.main===module)main().catch(error=>{console.error(error);process.exitCode=1;});
module.exports={family250,familyTransport,offers,chosen,familyAI};
