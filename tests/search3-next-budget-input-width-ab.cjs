'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { exactAssets, startServer } = require('./search3-next-compact-calendar.cjs');
const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');

const sourceCommit = 'b137a93dc08baedfd8f68508fe816bd34f5721ff';
const releaseCommit = '9a160c61cb727f58bb9cef6222a19a0f50165d45';
const compiledApp = 'a2f5b906a953364e735988eb055eb5b370cca5f1c675913ae0d1a2e0b663c633';
const styles = '0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const fixturePath = 'visual-search/fixtures/live-search-2026-09-23.json';
const screens = [{ width: 430, height: 932, dpr: 3, touch: true }, { width: 1280, height: 720, dpr: 1, touch: false }];
const budget = 220000;
const today = new Date().toISOString().slice(0, 10);
const future = days => new Date(Date.parse(today + 'T12:00Z') + days * 86400000).toISOString().slice(0, 10);
const query = new URLSearchParams({
  scenario: 'family', searched: '1', origin: 'Москва', country: '4',
  from: future(14), to: future(20), minNights: '7', maxNights: '7',
  adults: '3', ages: '0,12', stars: '4|5', meals: 'Всё включено', max: '240000',
});
const sha256 = value => createHash('sha256').update(value).digest('hex');
const filterPanelSha='4a06a979444416a51445f05d9e347219318dc4477a07f3ef3304e22b8b08184b';
const candidateSha='7e43346677128c37a2c9675df2fccc216e4e6dd9619c374cdc5e968234574690';
const candidateRawSha='c8fa157d6eafdda09aea39b0dbc48122f2399e585ae91d2a0b1e8f9051f7a557';
const patch=["!!getFilterDraft()&&getViewportWidth()<=1100&&expandedFilterSections.has('Туроператор')","!!getFilterDraft()&&expandedFilterSections.has('Туроператор')&&getViewportWidth()<=1100"];

function archivedInventory(raw){
 const names={'AI':'Всё включено','ALL':'Всё включено','ALL INCLUSIVE':'Всё включено','ВСЕ ВКЛЮЧЕНО':'Всё включено','ВСЁ ВКЛЮЧЕНО':'Всё включено'};
 const rows=[];
 for(const h of raw.hotels){
  if(![4,5].includes(h.stars))continue;
  const resort=h.location.replace(/, Турция$/,'');
  const rating=Number(String(h.rating||'').replace(',','.'));
  const offers=h.offers.map((o,variant)=>{
   const label=String(o.meal?.name||o.meal?.russianName||o.meal).trim();
   const meal=names[label.toLocaleUpperCase('ru-RU').replace(/\s+/g,' ')]||label;
   const offset=((Number(o.dates.match(/^\d+/)?.[0])||1)-1)%7;
   const total=Number(o.price.replace(/\D/g,''));
   return {key:o.key,hotelId:h.id,variant,provider:decodeURIComponent(o.key).split(':')[0],
    cached:true,raw:{selectionEnabled:false,listingPriceState:'snapshot'},offset,nights:7,meal,mealRaw:o.meal,
    room:o.room,operator:o.operator,flight:o.flight,total,flightChoiceId:null};
  });
  if(!offers.some(o=>o.meal==='Всё включено'&&o.total>0&&o.total<=240000))continue;
  rows.push({id:h.id,name:h.name,country:'4',resort,region:'',subRegion:'',stars:h.stars,
   rating:rating>0&&rating<=5?rating:null,photos:[],amenities:[],legacyIds:[],beach:null,family:false,spa:false,
   raw:{description:h.facts,arrival:resort},offers});
 }
 return rows;
}

function fixtureInBrowser({rows,searchPath}){
 if(location.pathname!==searchPath)return;
 const shift=(day,count)=>new Date(Date.parse(day+'T12:00:00Z')+count*86400000).toISOString().slice(0,10);
 const stats={packets:0,active:null,resultCalls:0};
 window.longResults=stats;
 let adapter;
 Object.defineProperty(window,'AnyTourPrototypeData',{configurable:true,get:()=>adapter,set(data){
  const props=Object.getOwnPropertyDescriptors(data);
  const original=props.search?.value;
  if(typeof original!=='function'){adapter=data;return;}
  props.search={...props.search,value:function(query,emit,...rest){
   stats.resultCalls++;
   return Reflect.apply(original,this,[query,event=>{
    if(event?.type!=='results')return emit(event);
    const hotels=rows.map(hotel=>({...hotel,offers:hotel.offers.map(({offset,...offer})=>{
     const day=shift(query.from,offset);
     return {...offer,search:structuredClone(query),origin:query.origin,day,returnDay:shift(day,offer.nights),
      adults:query.adults,ages:query.ages.slice()};
    })}));
    stats.packets++;
    return emit({...event,hotels});
   },...rest]);
  }};
  adapter=Object.freeze(Object.create(Object.getPrototypeOf(data),props));
 }});

}




function measure({trigger,selector}){
 const timings=window.compactBudgetMeasurement={};
 let selected;
 const initial=new Map([...document.querySelectorAll('#cards .hotel-card')].map(node=>[node.id,node]));
 const removed=new Set();
 const collect=records=>{
  for(const mutation of records)for(const node of mutation.removedNodes)if(initial.get(node.id)===node)removed.add(node);
 };
 const observer=new MutationObserver(collect);
 function begin(e){
  if(!e.isTrusted||!e.target.closest(selector))return;
  selected=e;
  observer.observe(document.querySelector('#cards'),{childList:true});
  timings.started=performance.now();
  document.removeEventListener(trigger,begin,true);
 }
 function end(e){
  if(e!==selected)return;
  timings.dispatch=performance.now()-timings.started;
  document.removeEventListener(trigger,end);
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   timings.frame=performance.now()-timings.started;
   collect(observer.takeRecords());
   observer.disconnect();
   const retained=[...document.querySelectorAll('#cards .hotel-card')].filter(node=>initial.get(node.id)===node);
   timings.dom={retained:retained.length,removed:removed.size,moved:retained.filter(node=>removed.has(node)).length};
  }));
 }
 document.addEventListener(trigger,begin,true);
 document.addEventListener(trigger,end);
}

const waitFrame = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
const waitCount = (page, count) => page.waitForFunction(n => new RegExp('^' + n + ' ').test(document.querySelector('#results-summary')?.textContent), count, { timeout: 9000 });
const budgetParam = page => Number(new URL(page.url()).searchParams.get('max'));
const cardData = page => page.locator('#cards .hotel-card').evaluateAll(rows => rows.map(node => [
  node.id, node.querySelector('.starting-price strong')?.textContent, node.querySelector('[data-card-entry]')?.dataset.key,
]));

async function exerciseDrawer(page) {
  const input = page.locator('#max-price');
  const panel = page.locator('#filter-panel');
  const apply = page.locator('#apply-filters');
  const previous = page.url();
  await input.fill('abc');
  assert.equal(await input.getAttribute('aria-invalid'), 'true');
  assert.equal(await apply.isDisabled(), true);
  assert.equal(page.url(), previous);
  await input.fill(String(budget));
  assert.equal(await input.getAttribute('aria-invalid'), 'false');
  assert.equal(await apply.textContent(), 'Показать отели (94)');
  const operator = panel.locator('.filter-operator-group .filter-section-toggle');
  await operator.tap();
  await page.waitForFunction(() => document.querySelector('#filter-panel').classList.contains('filter-detail-open'));
  assert.equal(await panel.locator('.filter-top h3').textContent(), 'Туроператор');
  await page.locator('#filter-detail-back').tap();
  await page.waitForFunction(() => !document.querySelector('#filter-panel').classList.contains('filter-detail-open'));
  assert.equal(await input.inputValue(), String(budget));
  await panel.locator('.mobile-close').tap();
  await page.waitForFunction(() => !document.querySelector('#filter-panel').classList.contains('open'));
  await waitFrame(page);
  assert.equal(page.url(), previous);
  await page.locator('.drawer-trigger').tap();
  assert.equal(await input.inputValue(), '240000');
  assert.equal(await apply.textContent(), 'Показать отели (100)');
  return { valid: true, invalid: true, operatorDetail: true, canceled: true };
}

async function sample(browser, server, route, rows, screen, cpu, panelCode, guard=false) {
  const context = await browser.newContext({
    viewport: { width: screen.width, height: screen.height }, deviceScaleFactor: screen.dpr,
    hasTouch: screen.touch, isMobile: screen.touch, reducedMotion: 'reduce', serviceWorkers: 'block',
    proxy: { server: server.origin, bypass: '127.0.0.1' },
  });
  const page = await context.newPage();
  const external = [], errors = [];
  let loads = 0;
  page.on('error', error => errors.push(error.message));
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.origin !== server.origin) external.push(url.pathname);
  });
  try {
    const pane = new URL(route.replace(/poisk-turov\/$/, 'visual-search/filter-panel-v1.js'), server.origin);
    await page.route(url => url.origin === pane.origin && url.pathname === pane.pathname, async interception => {
      assert.equal(new URL(interception.request().url()).searchParams.get('v'), filterPanelSha.slice(0, 12));
      loads++;
      await interception.fulfill({ contentType: 'application/javascript; charset=utf-8', body: panelCode });
    });
    const client = await context.newCDPSession(page);
    await client.send('Emulation.setCPUThrottlingRate', { rate: cpu });
    await client.send('Performance.enable');
    const read = async () => Object.fromEntries((await client.send('Performance.getMetrics')).metrics.map(row => [row.name, row.value]));
    await page.addInitScript(fixtureInBrowser, { rows, searchPath: route });
    assert.equal((await page.goto(server.origin + route + '?' + query, { waitUntil: 'domcontentloaded' })).status(), 200);
    await waitCount(page, 100);
    await page.waitForFunction(() => document.querySelector('#price-strip')?.getAttribute('aria-busy') === 'false');
    await page.evaluate(() => document.fonts.ready);
    assert.deepEqual(await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio, navigator.maxTouchPoints > 0]),
      [screen.width, screen.height, screen.dpr, screen.touch]);
    const input = page.locator('#max-price');
    if (screen.touch) {
      await page.locator('.drawer-trigger').tap();
      await input.tap();
      assert.equal(await page.locator('#apply-filters').textContent(), 'Показать отели (100)');
    } else {
      await input.focus();
    }
    await waitFrame(page);
    const baseline = await cardData(page),urlBefore = page.url();
    const field = await input.elementHandle();
    assert.equal(await input.inputValue(), '240000');
    assert.equal(budgetParam(page), 240000);
    await page.evaluate(measure, { selector:'#max-price', trigger:'input' });
    const before = await read();
    await input.fill(String(budget));
    await page.waitForFunction(() => window.compactBudgetMeasurement?.frame > 0);
    await waitCount(page, screen.touch ? 100 : 94);
    const after = await read();
    const { dispatch, frame, dom } = await page.evaluate(() => window.compactBudgetMeasurement);
    assert.equal(await field.evaluate(node => node === document.activeElement && node === document.querySelector('#max-price')), true);
    assert.equal(await input.inputValue(), String(budget));
    assert.equal(await input.getAttribute('aria-invalid'), 'false');
    assert.equal(await page.evaluate(() => window.longResults.resultCalls), 1);
    const cards = await cardData(page);
    assert.equal(cards.length, 24);
    if (screen.touch) {
      assert.equal(page.url(), urlBefore);
      assert.equal(await page.locator('#apply-filters').textContent(), 'Показать отели (94)');
      assert.deepEqual(cards, baseline);
      assert.deepEqual(dom, { retained:24, removed:0, moved:0 });
    } else {
      assert.equal(budgetParam(page), budget);
      assert.deepEqual(dom, { retained:21, removed:3, moved:0 });
    }
    const contracts = guard && screen.touch ? await exerciseDrawer(page) : null;
    assert.equal(loads, 1);
    assert.deepEqual(external, []);
    assert.deepEqual(errors, []);
    assert.deepEqual(server.rejected, []);
    return { dispatch, frame, cards, dom, contracts, layout:(after.LayoutDuration-before.LayoutDuration)*1000,
      style:(after.RecalcStyleDuration-before.RecalcStyleDuration)*1000 };
  } finally {
    await context.close();
  }
}

function median(items){
 const sorted=items.slice().sort((a,b)=>a-b),mid=Math.floor(sorted.length/2);
 return sorted.length%2?sorted[mid]:(sorted[mid-1]+sorted[mid])/2;
}
function summarize(pairs){
 return Object.fromEntries(['dispatch','frame','layout','style'].map(key=>{
  const a=pairs.map(p=>p.before[key]),b=pairs.map(p=>p.after[key]),delta=b.map((value,i)=>value-a[i]);
  return [key,{before:median(a),after:median(b),paired:median(delta),faster:delta.filter(x=>x<0).length,
   lower:Math.min(...delta),upper:Math.max(...delta)}];
 }));
}

module.exports = async function compareBudgetDraft(browser, nextHome, output) {
  const exact = exactAssets(nextHome),root = path.resolve(__dirname,'..');
  assert.equal(exact.appSha,compiledApp);
  assert.equal(exact.sheetSha,styles);
  assert.deepEqual(execFileSync('git',['diff','--name-only',releaseCommit,'HEAD'],{cwd:root,encoding:'utf8'}).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs','tests/search3-next-budget-input-width-ab.cjs']);
  const installed = fs.readFileSync(path.join(nextHome.payload,'visual-search/filter-panel-v1.js'),'utf8');
  assert.equal(sha256(installed),filterPanelSha);
  const raw = execFileSync('git',['show',sourceCommit+':v2/visual-search/filter-panel-v1.js'],{cwd:root,encoding:'utf8'});
  assert.equal(sha256(await compile(raw)),filterPanelSha);
  assert.equal(raw.split(patch[0]).length,2);
  const fixed = raw.replace(...patch);
  assert.equal(sha256(fixed),candidateRawSha);
  const candidate = await compile(fixed);
  assert.equal(sha256(candidate),candidateSha);
  const stored = fs.readFileSync(path.join(nextHome.payload,fixturePath));
  const inventory = archivedInventory(JSON.parse(stored));
  assert.equal(inventory.length,215);
  const rows = inventory.slice(0,100),paths={home:nextHome.prefix+'/',search:nextHome.prefix+'/poisk-turov/'};
  const server = await startServer(nextHome,exact,paths),versions={before:installed,after:candidate},groups=[];
  try {
    for (const screen of screens) for (const cpu of [1,4]) {
      async function visit(side,guard){return sample(browser,server,paths.search,rows,screen,cpu,versions[side],guard);}
      const warm={before:await visit('before',cpu===1),after:await visit('after',cpu===1)};
      assert.deepEqual(warm.before.cards,warm.after.cards);
      assert.deepEqual(warm.before.contracts,warm.after.contracts);
      const pairs=[];
      for(let i=0;i<6;i++){
        const order=i%2?['before','after']:['after','before'],pair={order};
        for(const side of order)pair[side]=await visit(side,false);
        assert.deepEqual(pair.before.cards,pair.after.cards);
        assert.deepEqual(pair.before.dom,pair.after.dom);
        pairs.push(pair);
      }
      const summary=summarize(pairs);
      console.log('SEARCH3_BUDGET_INPUT_WIDTH_AB '+JSON.stringify({screen,cpu,summary}));
      groups.push({screen,cpu,warm,pairs,summary});
    }
    fs.writeFileSync(path.join(output,'canonical-next-budget-input-width-ab.json'),JSON.stringify({
      diagnosticSource:process.env.SEARCH3_SOURCE_SHA,sourceCommit,releaseCommit,compiledApp,
      before:filterPanelSha,after:candidateSha,styles,browser:browser.version(),fixture:sha256(stored),groups,
    },null,2)+'\n');
  } finally {
    await server.close();
  }
};