'use strict';

const assert = require('node:assert/strict');
const {entry, openReplay, sha} = require('./search3-visual-first-open.cjs');
const {until} = require('./search3-visual-first-tours.cjs');
const {trip} = require('./search3-visual-live-fixture.cjs');
const {privateTVFixture} = require('./search3-visual-messenger-browser.cjs');

// The first eight hotels arrive together. The ninth is the independently
// delivered cheapest hotel, gated until the third card is being read.
function readingDelivery(transport) {
  const catalogue = Array.from({length:9}, (_,i)=>({
    id:2001+i,legacyId:7001+i,name:'Проверка позиции · отель '+(i+1),
    country:'4',countryName:'Турция',images:[entry+'assets/photo-1.jpg'],
  }));
  transport.state.hotelCatalogue=catalogue;
  const last=catalogue.at(-1);
  const tv=privateTVFixture({async json(url) {
    const body=await transport.json(url);
    return new URL(url).searchParams.get('action')==='search_results' ?
      body.map(h=>({...h,id:last.legacyId,tours:h.tours.map(t=>({...t,price:90000}))})) : body;
  }},'http://127.0.0.1');
  let release;
  const gate=new Promise(resolve=>{release=resolve;});
  const delivered=[];
  return {release,delivered,async reply(url,body,method,payload) {
    const action=url.searchParams.get('action')||body.action;
    if (action?.startsWith('tv_')) {
      if (action==='tv_results') await gate;
      return tv.handle(action,body,method);
    }
    const response=await transport.json(url.href,{body:payload});
    if (url.pathname.endsWith('/api-anex-search3-preview.php') && action==='search') {
      const h=response.data.hotels[0],offer=h.tours[0];
      response.data.hotels=catalogue.slice(0,-1).map((row,i)=>({...h,
        local_id:row.legacyId,name:row.name,catalog:{...h.catalog,hotel_id:row.legacyId},
        tours:[{...offer,offer_ref:'anex_online:'+sha('reading:'+row.legacyId),
          price:{amount:String(120000+1000*i),currency:'RUB'}}],
      }));
      delivered.push(...response.data.hotels.map(h=>h.local_id));
    }
    return response;
  }};
}

function inspectReadingCard() {
  const cards=[...document.querySelectorAll('#cards .hotel-card')];
  const card=document.getElementById('hotel-2003');
  if (!card) return null;
  const button=card.querySelector('[data-card-entry]');
  return {top:card.getBoundingClientRect().top,scrollY,key:button?.dataset.key,
    amount:Number(card.querySelector('.starting-price strong')?.textContent.replace(/\D/g,'')),
    ids:cards.map(c=>c.dataset.hotelId),focusInside:!!document.activeElement?.closest('#cards')};
}

async function measureReadingPosition(driver,root,html) {
  const replay=await openReplay(root,html,0,readingDelivery);
  const read='('+inspectReadingCard.toString()+')()';
  try {
    await driver.go(replay.origin+entry+'?'+new URLSearchParams({...trip,ages:'',sort:'price'}));
    assert.ok(await until(driver,'window.__firstOpen?.readyMs || null',10000));
    replay.allowClick();
    await driver.click('.search-submit');
    assert.ok(await until(driver,'document.querySelectorAll("#cards .hotel-card").length===8',10000),
      'the initial eight hotels must be searchable '+JSON.stringify(replay.blocked));
    // Position the reading card slightly under the compact search while keeping
    // focus outside the cards, as a reader scrolling with a finger or wheel does.
    await driver.evaluate('window.scrollBy({top:document.getElementById("hotel-2003").getBoundingClientRect().top-100,behavior:"instant"})');
    const settle='new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve(true))))';
    await driver.evaluate(settle);
    const before=await driver.evaluate(read);
    assert.ok(Math.abs(before.top-100)<3 && before.scrollY>0,JSON.stringify(before));
    assert.equal(before.focusInside,false);
    assert.deepEqual(before.ids,['2001','2002','2003','2004','2005','2006','2007','2008']);
    replay.delivery.release();
    assert.ok(await until(driver,'document.querySelector("#cards .hotel-card")?.dataset.hotelId==="2009" && document.querySelectorAll("#cards .hotel-card").length===9',12000),
      'the last, cheaper hotel must enter at the beginning '+JSON.stringify(replay.blocked));
    await driver.evaluate(settle);
    const after=await driver.evaluate(read);
    assert.equal(after.focusInside,false);
    assert.equal(after.ids[3],'2003');
    assert.ok(before.key && after.key===before.key && before.amount===122000 && after.amount===before.amount);
    assert.deepEqual(replay.blocked,[]);
    return {before,after,deltaPx:after.top-before.top};
  } finally {replay.delivery.release();await replay.close();}
}

module.exports={measureReadingPosition};
