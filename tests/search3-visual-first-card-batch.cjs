'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function installCardMeasurement({mode, hotelCount, toursPerHotel}) {
  const measurement = {mode, parsedCardRoots:0, eventCount:0};
  window.firstCardMeasurement = measurement;
  const hasPlaceholder = () => {
    const cards = document.getElementById('cards');
    return cards?.childElementCount > 0 && !cards.querySelector('.hotel-card');
  };
  const select = Element.prototype.querySelector;
  if (mode === 'individual') {
    Element.prototype.querySelector = function(selector) {
      const match = select.call(this,selector);
      if (selector === '.hotel-card' && this.id === 'cards' && !match &&
          this.childElementCount > 0 && measurement.emittedAt !== undefined && !measurement.observedAt) {
        measurement.oldPaintBranch = true;
        return this.firstElementChild;
      }
      return match;
    };
  }

  const html = Object.getOwnPropertyDescriptor(Element.prototype,'innerHTML');
  Object.defineProperty(Element.prototype,'innerHTML',{
    ...html,
    set(markup) {
      if (this.tagName === 'TEMPLATE' && !measurement.observedAt &&
          measurement.emittedAt !== undefined && /class="(?:hotel-card|secondary load-more-cards)\b/.test(markup)) {
        measurement.parsedCardRoots++;
      }
      html.set.call(this,markup);
    },
  });

  let adapter;
  const expandedEvents = new WeakSet();
  Object.defineProperty(window,'AnyTourPrototypeData',{
    configurable:true,
    get:() => adapter,
    set(data) {
      const properties = Object.getOwnPropertyDescriptors(data);
      const searchOwner = properties.search.value;
      properties.search.value = function(search, emit, ...options) {
          return searchOwner.call(data,search,event => {
            if (event.type !== 'results' || !event.hotels?.length || expandedEvents.has(event)) return emit(event);
            measurement.eventCount++;
            const seed = event.hotels.find(h => h.offers?.length);
            if (!seed) return emit(event);
            const first = seed.offers[0];
            const scaled = Array.from({length:hotelCount},(_,i) => {
              const id = seed.id+i;
              const offers = Array.from({length:toursPerHotel},(_,j) => ({
                ...first, hotelId:id, total:first.total+i*13+j*17, key:'browser-first-'+id+'-'+j,
              }));
              return {...seed,id,name:seed.name+' '+(i+1),offers};
            });
            if (measurement.emittedAt === undefined) {
              measurement.placeholder = hasPlaceholder();
              measurement.emittedAt = performance.now();
            }
            const expanded = {...event,hotels:scaled};
            expandedEvents.add(expanded);
            return emit(expanded);
          },...options);
      };
      adapter = Object.create(Object.getPrototypeOf(data),properties);
    },
  });

  const observer = new MutationObserver(() => {
    if (measurement.emittedAt === undefined) return;
    const card = document.querySelector('#cards > .hotel-card');
    if (!card) return;
    measurement.observedAt = performance.now();
    measurement.keys = [...document.querySelectorAll('#cards > .hotel-card')].map(node => node.id);
    observer.disconnect();
    requestAnimationFrame(() => {
      const {width,height} = card.getBoundingClientRect();
      if (card.isConnected && width > 0 && height > 0 && getComputedStyle(card).visibility === 'visible') {
        requestAnimationFrame(() => {measurement.nextFrameAt = performance.now();});
      }
    });
  });
  observer.observe(document,{subtree:true,childList:true});
}

async function oneTrial({browser,origin,base,canonicalLogoFixture,width,height,mode}) {
  const context = await browser.newContext({viewport:{width,height},hasTouch:width===430,serviceWorkers:'block'});
  const page = await context.newPage();
  const exceptions = [];
  const blockedRequests = [];
  page.on('pageerror',error=>exceptions.push(error.message));
  try {
    await page.addInitScript(installCardMeasurement,{mode,hotelCount:120,toursPerHotel:24});
    await page.route('**/*',async route => {
      const request = route.request(), url = new URL(request.url());
      const logo = canonicalLogoFixture(request,origin);
      if (logo) return route.fulfill(logo);
      const staticRequest = url.origin === origin && url.pathname.startsWith(base) &&
        !url.pathname.includes('/data/') && !url.pathname.endsWith('.php') && request.method() === 'GET';
      if (staticRequest) return route.continue();
      if (request.resourceType()==='image') return route.abort();
      blockedRequests.push(url.pathname);
      return route.abort();
    });
    await page.goto(origin+base+'visual-search/?scenario=journey',{waitUntil:'domcontentloaded'});
    try {
      await page.locator('.search-submit:not(:disabled)').click({timeout:10000});
    } catch (error) {
      const state = await page.evaluate(()=>({
        scenario:window.AnyTourPrototypeData?.scenario,
        catalogMessage:document.querySelector('#catalog-error')?.textContent,
        formHidden:document.querySelector('#search-form')?.hidden,
      }));
      throw new Error('Offline journey button did not become ready. '+JSON.stringify({state,exceptions,blockedRequests}),{cause:error});
    }
    await page.waitForFunction(()=>Number.isFinite(window.firstCardMeasurement?.nextFrameAt),null,{timeout:10000});
    const observation = await page.evaluate(()=>{
      const m = window.firstCardMeasurement;
      return {placeholder:m.placeholder,branch:m.oldPaintBranch??false,parses:m.parsedCardRoots,
        keys:m.keys,observerMs:m.observedAt-m.emittedAt,frameMs:m.nextFrameAt-m.emittedAt};
    });
    assert.equal(observation.keys.length,24);
    assert.deepEqual(exceptions,[]);
    assert.deepEqual(blockedRequests,[]);
    return observation;
  } finally {
    await context.close();
  }
}

function median(values) {
  const sorted = values.slice().sort((a,b)=>a-b), at = Math.floor(sorted.length/2);
  return sorted.length%2 ? sorted[at] : (sorted[at-1]+sorted[at])/2;
}

module.exports = async function measureFirstCards(options) {
  const result = [];
  for (const [width,height] of [[430,932],[1280,720]]) {
    const pairs = [];
    for (let i=0;i<7;i++) {
      const pair = {};
      for (const mode of i % 2 ? ['batch','individual'] : ['individual','batch']) {
        pair[mode] = await oneTrial({...options,width,height,mode});
      }
      assert(pair.batch.placeholder && pair.individual.placeholder && pair.individual.branch);
      assert.equal(pair.batch.parses,0);
      assert(pair.individual.parses>0);
      assert.deepEqual(pair.batch.keys,pair.individual.keys);
      if (i>0) pairs.push(pair);
    }
    const summary = {width,height,frameSavingMs:median(pairs.map(p=>p.individual.frameMs-p.batch.frameMs)),
      observerSavingMs:median(pairs.map(p=>p.individual.observerMs-p.batch.observerMs))};
    result.push({...summary,pairs});
  }
  fs.writeFileSync(path.join(options.evidence,'first-card-batch.json'),JSON.stringify({
    engine:'Chromium',physicalDevice:false,clock:'response to second animation frame after card insertion',
    scenario:'120 fictional hotels with 24 tours each',result,
  },null,2)+'\n');
  return result.map(({pairs,...summary})=>summary);
};
