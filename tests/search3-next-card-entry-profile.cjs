'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { exactAssets, startServer } = require('./search3-next-compact-calendar.cjs');
const { compile } = require('../scripts/build/search3-js/visual-entry.cjs');
const { parse } = require('../scripts/build/search3-js/node_modules/acorn');

const sourceCommit = 'b137a93dc08baedfd8f68508fe816bd34f5721ff';
const releaseCommit = '9a160c61cb727f58bb9cef6222a19a0f50165d45';
const rawApp = '76008646a83926fff1a6737e366e974101bd618ada8c4565affc4634545e29bc';
const compiledApp = 'a2f5b906a953364e735988eb055eb5b370cca5f1c675913ae0d1a2e0b663c633';
const styles = '0db189a9fbd6fbc74d38b71c0f79f92c7ecad4a2847151d8d3f7fe0c5e101b1a';
const fixturePath = 'visual-search/fixtures/live-search-2026-09-23.json';
const viewport = { width: 430, height: 932 };
const budget = 220000;
const today = new Date().toISOString().slice(0, 10);
const future = days => new Date(Date.parse(today + 'T12:00Z') + days * 86400000).toISOString().slice(0, 10);
const query = new URLSearchParams({
  scenario: 'family', searched: '1', origin: 'Москва', country: '4',
  from: future(14), to: future(20), minNights: '7', maxNights: '7',
  adults: '3', ages: '0,12', stars: '4|5', meals: 'Всё включено', max: '240000',
});
const sha256 = value => createHash('sha256').update(value).digest('hex');
const phaseNames = new Set([
  'closeFilters', 'renderResults', 'resultInventory', 'sortResultItems',
  'renderResultCards', 'cardHTML', 'paintResultCards', 'paintGeneratedRoots',
  'hotelMatchFactsHTML', 'hotelHighlightsHTML', 'currentCardSelectionHTML',
  'minimumOfferSummary', 'hotelStarsHTML',
]);

function instrument(source) {
  const edits = [];
  const functions = new Set();
  let entryPlan = 0;
  let labelBlock = 0;
  let cardTemplate = 0;
  function insert(at, text) {
    edits.push([at, text]);
  }
  function walk(node, owner) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
      for (const value of node) walk(value, owner);
      return;
    }
    if (node.type === 'FunctionDeclaration') {
      owner = node.id?.name;
      if (phaseNames.has(owner)) {
        assert(!functions.has(owner), owner);
        functions.add(owner);
        const id = owner === 'cardHTML' ? ',h.id' : '';
        insert(node.body.start + 1, 'const probe=window.cardEntryProbe;const token=probe?.enter(' + JSON.stringify(owner) + id + ');try{');
        insert(node.body.end - 1, '}finally{if(token)probe.exit(token);}');
      }
      if (owner === 'cardHTML') {
        const first = node.body.body.find(value => value.declarations?.some(declaration => declaration.id.name === 'cardEntryLabel'));
        const last = node.body.body.find(value => value.declarations?.some(declaration => declaration.id.name === 'mealText'));
        assert(first && last && last.end > first.start);
        insert(first.start, 'const headline=probe?.enter("offerHeadline");');
        insert(last.end, 'if(headline)probe.exit(headline);');
        labelBlock++;
      }
    }
    if (owner === 'renderResultCards' && node.type === 'VariableDeclaration' && node.declarations.some(
      declaration => declaration.id.name === 'entries' && declaration.init?.callee?.property?.name === 'map',
    )) {
      insert(node.start, 'const entryList=probe?.enter("entryPlan");');
      insert(node.end, 'if(entryList)probe.exit(entryList);');
      entryPlan++;
    }
    if (owner === 'cardHTML' && node.type === 'ReturnStatement' && node.argument?.type === 'TemplateLiteral') {
      insert(node.start, 'const cardMarkup=probe?.enter("cardTemplate");try{');
      insert(node.end, '}finally{if(cardMarkup)probe.exit(cardMarkup);}');
      cardTemplate++;
    }
    for (const value of Object.values(node)) walk(value, owner);
  }
  walk(parse(source, { ecmaVersion: 'latest' }), null);
  assert.deepEqual(functions, phaseNames);
  assert.deepEqual([entryPlan, labelBlock, cardTemplate], [1, 1, 1]);
  for (const [at, text] of edits.sort((a, b) => b[0] - a[0])) {
    source = source.slice(0, at) + text + source.slice(at);
  }
  return source;
}

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




function measure() {
  const cards = new Map([...document.querySelectorAll('#cards .hotel-card')].map(node => [node.id, node]));
  const stack = [];
  const state = window.cardEntryProbe = {
    rows: [],
    enter(name, id) {
      if (this.started === undefined || this.stopped !== undefined) return null;
      const start = performance.now();
      const parent = stack.at(-1);
      const entry = { name, id, path: parent ? parent.path + '/' + name : name, start, childTime: 0 };
      stack.push(entry);
      return entry;
    },
    exit(entry) {
      const elapsed = performance.now() - entry.start;
      if (stack.pop() !== entry) throw Error('unexpected profile tree');
      const parent = stack.at(-1);
      if (parent) parent.childTime += elapsed;
      this.rows.push({ path: entry.path, id: entry.id, start: entry.start - this.started, total: elapsed, self: elapsed - entry.childTime });
    },
  };
  let selected;
  function begin(event) {
    if (!event.isTrusted || !event.target.closest('#apply-filters')) return;
    selected = event;
    state.started = performance.now();
    document.removeEventListener('click', begin, true);
  }
  function finish(event) {
    if (event !== selected) return;
    state.dispatch = performance.now() - state.started;
    document.removeEventListener('click', finish);
    requestAnimationFrame(() => requestAnimationFrame(() => {
      state.frame = performance.now() - state.started;
      const visible = [...document.querySelectorAll('#cards .hotel-card')];
      const retainedIds = visible.filter(node => cards.get(node.id) === node).map(node => node.id);
      state.cards = { previous: cards.size, current: visible.length, retained: retainedIds.length, retainedIds };
    }));
  }
  document.addEventListener('click', begin, true);
  document.addEventListener('click', finish);
}

const waitCount = (page, count) => page.waitForFunction(
  n => new RegExp('^' + n + ' ').test(document.querySelector('#results-summary')?.textContent), count, { timeout: 9000 },
);
const waitFrame = page => page.evaluate(() => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done))));
const waitCalendar = page => page.waitForFunction(
  () => document.querySelector('#price-strip')?.getAttribute('aria-busy') === 'false', null, { timeout: 9000 },
);

async function sample(browser, server, route, rows, cpu) {
  const context = await browser.newContext({
    viewport, deviceScaleFactor: 3, hasTouch: true, isMobile: true, reducedMotion: 'reduce',
    serviceWorkers: 'block', proxy: { server: server.origin, bypass: '127.0.0.1' },
  });
  const page = await context.newPage();
  const external = [];
  const errors = [];
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.origin !== server.origin) external.push(url.pathname);
  });
  page.on('pageerror', error => errors.push(error.message));
  try {
    const client = await context.newCDPSession(page);
    await client.send('Emulation.setCPUThrottlingRate', { rate: cpu });
    await client.send('Performance.enable');
    const read = async () => Object.fromEntries((await client.send('Performance.getMetrics')).metrics.map(metric => [metric.name, metric.value]));
    await page.addInitScript(fixtureInBrowser, { rows, searchPath: route });
    assert.equal((await page.goto(server.origin + route + '?' + query, { waitUntil: 'domcontentloaded' })).status(), 200);
    await waitCount(page, 100);
    await waitCalendar(page);
    await page.evaluate(() => document.fonts.ready);
    await waitFrame(page);
    assert.deepEqual(await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio, navigator.maxTouchPoints > 0]), [430, 932, 3, true]);
    const input = page.locator('#max-price');
    await page.locator('.drawer-trigger').tap();
    await input.fill(String(budget));
    await input.blur();
    assert.equal(new URL(page.url()).searchParams.get('max'), '240000');
    await page.evaluate(measure);
    const before = await read();
    await page.locator('#apply-filters').tap();
    await page.waitForFunction(() => window.cardEntryProbe?.frame > 0, null, { timeout: 9000 });
    await waitCount(page, 94);
    await waitCalendar(page);
    const after = await read();
    const timings = await page.evaluate(() => {
      const state = window.cardEntryProbe;
      state.stopped = performance.now();
      return { dispatch: state.dispatch, frame: state.frame, rows: state.rows, cards: state.cards,
        resultCalls: window.longResults.resultCalls, packets: window.longResults.packets };
    });
    assert.equal(new URL(page.url()).searchParams.get('max'), String(budget));
    const { previous, current, retained } = timings.cards;
    assert.deepEqual([previous, current, retained], [24, 24, 21]);
    assert.equal(timings.resultCalls, 1);
    assert.equal(timings.rows.filter(row => row.path.endsWith('/renderResults')).length, 1);
    const markup = timings.rows.filter(row => row.path.endsWith('/entryPlan/cardHTML'));
    assert.equal(markup.length, 24);
    assert.equal(new Set(markup.map(row => row.id)).size, 24);
    assert.equal(markup.filter(row => timings.cards.retainedIds.includes('hotel-' + row.id)).length, 21);
    assert(timings.rows.some(row => row.path.endsWith('/paintResultCards/paintGeneratedRoots')));
    assert.deepEqual(external, []);
    assert.deepEqual(errors, []);
    assert.deepEqual(server.rejected, []);
    return { ...timings, layoutWindow: (after.LayoutDuration - before.LayoutDuration) * 1000,
      styleWindow: (after.RecalcStyleDuration - before.RecalcStyleDuration) * 1000 };
  } finally {
    await context.close();
  }
}

function summarize(samples) {
  const median = values => {
    const sorted = [...values].sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
  };
  const paths = new Set(samples.flatMap(sample => sample.rows.map(row => row.path)));
  const phases = Object.fromEntries([...paths].sort().map(phase => {
    const calls = samples.map(sample => sample.rows.filter(row => row.path === phase));
    return [phase, { calls: calls.map(rows => rows.length),
      total: median(calls.map(rows => rows.reduce((sum, row) => sum + row.total, 0))),
      self: median(calls.map(rows => rows.reduce((sum, row) => sum + row.self, 0))) }];
  }));
  const completed = samples.map(sample => {
    const retained = new Set(sample.cards.retainedIds);
    const calls = sample.rows.filter(row => row.path.endsWith('/entryPlan/cardHTML'));
    return {
      all: calls.reduce((total, row) => total + row.total, 0),
      retained: calls.filter(row => retained.has('hotel-' + row.id)).reduce((total, row) => total + row.total, 0),
      fresh: calls.filter(row => !retained.has('hotel-' + row.id)).reduce((total, row) => total + row.total, 0),
    };
  });
  const markup = Object.fromEntries(['all', 'retained', 'fresh'].map(key => [key, median(completed.map(sample => sample[key]))]));
  return {
    markup,
    dispatch: median(samples.map(sample => sample.dispatch)),
    frame: median(samples.map(sample => sample.frame)),
    layoutWindow: median(samples.map(sample => sample.layoutWindow)),
    styleWindow: median(samples.map(sample => sample.styleWindow)),
    phases,
  };
}

async function profileCardEntries(browser, nextHome, output) {
  const exact = exactAssets(nextHome);
  const root = path.resolve(__dirname, '..');
  assert.equal(exact.appSha, compiledApp);
  assert.equal(exact.sheetSha, styles);
  assert.deepEqual(execFileSync('git', ['diff', '--name-only', releaseCommit, 'HEAD'], { cwd: root, encoding: 'utf8' }).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs', 'tests/search3-next-card-entry-profile.cjs']);
  const source = execFileSync('git', ['show', sourceCommit + ':v2/visual-search/app.js'], { cwd: root, encoding: 'utf8' });
  assert.equal(sha256(source), rawApp);
  assert.equal(sha256(await compile(source, { privateFunctions: true })), compiledApp);
  const code = await compile(instrument(source), { privateFunctions: true });
  const instrumentedApp = sha256(code);
  const appUrl = exact.appUrl.replace(compiledApp.slice(0, 12), instrumentedApp.slice(0, 12));
  assert.notEqual(appUrl, exact.appUrl);
  const replace = html => {
    assert(html.includes(exact.appUrl));
    return html.replace(exact.appUrl, appUrl);
  };
  const instrumented = { ...exact, code: Buffer.from(code), appSha: instrumentedApp, appUrl, home: replace(exact.home), search: replace(exact.search) };
  const saved = fs.readFileSync(path.join(nextHome.payload, fixturePath));
  const inventory = archivedInventory(JSON.parse(saved));
  assert.equal(inventory.length, 215);
  const rows = inventory.slice(0, 100);
  const paths = { home: nextHome.prefix + '/', search: nextHome.prefix + '/poisk-turov/' };
  const server = await startServer(nextHome, instrumented, paths);
  const groups = [];
  try {
    for (const cpu of [1, 4]) {
      const warm = await sample(browser, server, paths.search, rows, cpu);
      const samples = [];
      for (let i = 0; i < 4; i++) samples.push(await sample(browser, server, paths.search, rows, cpu));
      const summary = summarize(samples);
      console.log('SEARCH3_CARD_ENTRY_PROFILE ' + JSON.stringify({ cpu, summary }));
      groups.push({ cpu, warm, samples, summary });
    }
    fs.writeFileSync(path.join(output, 'canonical-next-card-entry-profile.json'), JSON.stringify({
      diagnosticSource: process.env.SEARCH3_SOURCE_SHA, sourceCommit, releaseCommit, compiledApp,
      instrumentedApp, styles, fixture: sha256(saved), browser: browser.version(), viewport: { ...viewport, dpr: 3, touch: true }, groups,
    }, null, 2) + '\n');
  } finally {
    await server.close();
  }
}

module.exports = profileCardEntries;