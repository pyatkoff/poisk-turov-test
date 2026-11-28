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
const appPhaseNames = new Set([
  'handleBudgetMealDestinationInput', 'editFilterBudget', 'filterEdited', 'updateDrawerPreview',
  'rememberUIRoute', 'uiRoute', 'syncFilterResetState', 'showFilterBudgetValidity',
  'budgetScale', 'readBudgetFields', 'countFacetOptionGroups', 'facetCountPlan',
  'renderResults', 'paintGeneratedRoots',
]);
const panelPhaseNames = new Set([
  'updateFacetCounts', 'applyFacetSearch', 'updateFilterStars', 'syncAvailableFilterGroups',
  'syncFilterSections', 'renderFilterNavigation', 'settleFilterRoots',
]);
const filterPanelSha = '4a06a979444416a51445f05d9e347219318dc4477a07f3ef3304e22b8b08184b';

function instrument(source, phaseNames) {
  const seen = new Set();
  const insertions = [];
  function visit(node) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
      for (const item of node) visit(item);
      return;
    }
    if (node.type === 'FunctionDeclaration' && phaseNames.has(node.id?.name)) {
      const name = node.id.name;
      assert(!seen.has(name), name);
      seen.add(name);
      insertions.push([node.body.start + 1, 'const probe=window.budgetInputProbe;const time=probe?.enter(' + JSON.stringify(name) + ');try{']);
      insertions.push([node.body.end - 1, '}finally{if(time)probe.exit(time);}']);
    }
    for (const value of Object.values(node)) visit(value);
  }
  visit(parse(source, { ecmaVersion: 'latest' }));
  assert.deepEqual(seen, phaseNames);
  for (const [at, code] of insertions.sort((a, b) => b[0] - a[0])) source = source.slice(0, at) + code + source.slice(at);
  return source;
}
function instrumentApp(source) {
  const owner = 'getViewportWidth:()=>innerWidth';
  assert.equal(source.split(owner).length, 2);
  return instrument(source, appPhaseNames).replace(owner,
    'getViewportWidth:()=>window.budgetInputProbe?window.budgetInputProbe.width():innerWidth');
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
  const state = window.budgetInputProbe = {
    rows: [],
    enter(name) {
      if (this.started === undefined || this.stopped !== undefined) return null;
      const start = performance.now();
      const parent = stack.at(-1);
      const entry = { name, path: parent ? parent.path + '/' + name : name, start, childTime: 0 };
      stack.push(entry);
      return entry;
    },
    exit(entry) {
      const elapsed = performance.now() - entry.start;
      if (stack.pop() !== entry) throw Error('unexpected profile tree');
      const parent = stack.at(-1);
      if (parent) parent.childTime += elapsed;
      this.rows.push({ path: entry.path, start: entry.start - this.started, total: elapsed, self: elapsed - entry.childTime });
    },
    width() {
      const entry = this.enter('innerWidth');
      try {
        return innerWidth;
      } finally {
        if (entry) this.exit(entry);
      }
    },
  };
  let selected;
  function begin(event) {
    if (!event.isTrusted || event.target.id !== 'max-price') return;
    selected = event;
    state.started = performance.now();
    document.removeEventListener('input', begin, true);
  }
  function finish(event) {
    if (event !== selected) return;
    state.dispatch = performance.now() - state.started;
    document.removeEventListener('input', finish);
    requestAnimationFrame(() => requestAnimationFrame(() => {
      state.frame = performance.now() - state.started;
      const visible = [...document.querySelectorAll('#cards .hotel-card')];
      state.cards = { previous: cards.size, current: visible.length, retained: visible.filter(node => cards.get(node.id) === node).length };
    }));
  }
  document.addEventListener('input', begin, true);
  document.addEventListener('input', finish);
}

const waitCount = (page, count) => page.waitForFunction(
  n => new RegExp('^' + n + ' ').test(document.querySelector('#results-summary')?.textContent), count, { timeout: 9000 },
);
const waitFrame = page => page.evaluate(() => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done))));
const waitCalendar = page => page.waitForFunction(
  () => document.querySelector('#price-strip')?.getAttribute('aria-busy') === 'false', null, { timeout: 9000 },
);

async function sample(browser, server, route, rows, cpu, instrumentedPanel) {
  const context = await browser.newContext({
    viewport, deviceScaleFactor: 3, hasTouch: true, isMobile: true, reducedMotion: 'reduce',
    serviceWorkers: 'block', proxy: { server: server.origin, bypass: '127.0.0.1' },
  });
  const page = await context.newPage();
  const external = [];
  const errors = [];
  let panelLoads = 0;
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.origin !== server.origin) external.push(url.pathname);
  });
  page.on('pageerror', error => errors.push(error.message));
  try {
    const pane = new URL(route.replace(/poisk-turov\/$/, 'visual-search/filter-panel-v1.js'), server.origin);
    await page.route(url => url.origin === pane.origin && url.pathname === pane.pathname, async route => {
      assert.equal(new URL(route.request().url()).searchParams.get('v'), filterPanelSha.slice(0, 12));
      panelLoads++;
      await route.fulfill({ contentType: 'application/javascript; charset=utf-8', body: instrumentedPanel });
    });

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
    await input.tap();
    await waitFrame(page);
    const href = page.url();
    assert.equal(await input.inputValue(), '240000');
    assert.equal(await page.locator('#apply-filters').textContent(), 'Показать отели (100)');
    await page.evaluate(measure);
    const before = await read();
    await input.fill(String(budget));
    await page.waitForFunction(() => window.budgetInputProbe?.frame > 0, null, { timeout: 9000 });
    const after = await read();
    const timings = await page.evaluate(() => {
      const state = window.budgetInputProbe;
      state.stopped = performance.now();
      return { dispatch: state.dispatch, frame: state.frame, rows: state.rows, cards: state.cards,
        focus: document.activeElement?.id, resultCalls: window.longResults.resultCalls, packets: window.longResults.packets };
    });
    assert.equal(page.url(), href);
    assert.equal(await input.inputValue(), String(budget));
    assert.equal(await input.getAttribute('aria-invalid'), 'false');
    assert.equal(await page.locator('#apply-filters').textContent(), 'Показать отели (94)');
    assert.match(await page.locator('#results-summary').textContent(), /^100 /);
    assert.equal(timings.focus, 'max-price');
    assert.deepEqual(timings.cards, { previous: 24, current: 24, retained: 24 });
    assert.equal(timings.resultCalls, 1);
    assert.equal(panelLoads, 1);
    assert.equal(timings.rows.filter(row => row.path.endsWith('/editFilterBudget')).length, 1);
    assert.equal(timings.rows.filter(row => row.path.endsWith('/updateFacetCounts')).length, 1);
    assert.equal(timings.rows.filter(row => row.path.endsWith('/renderResults')).length, 0);
    assert(timings.rows.some(row => row.path.endsWith('/syncFilterSections/innerWidth')));
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
  return {
    dispatch: median(samples.map(sample => sample.dispatch)),
    frame: median(samples.map(sample => sample.frame)),
    layoutWindow: median(samples.map(sample => sample.layoutWindow)),
    styleWindow: median(samples.map(sample => sample.styleWindow)),
    phases,
  };
}

async function profileBudgetInput(browser, nextHome, output) {
  const exact = exactAssets(nextHome);
  const root = path.resolve(__dirname, '..');
  assert.equal(exact.appSha, compiledApp);
  assert.equal(exact.sheetSha, styles);
  assert.deepEqual(execFileSync('git', ['diff', '--name-only', releaseCommit, 'HEAD'], { cwd: root, encoding: 'utf8' }).trim().split('\n').sort(),
    ['tests/search3-entry-owner-browser.cjs', 'tests/search3-next-budget-input-profile.cjs']);
  const source = execFileSync('git', ['show', sourceCommit + ':v2/visual-search/app.js'], { cwd: root, encoding: 'utf8' });
  assert.equal(sha256(source), rawApp);
  assert.equal(sha256(await compile(source, { privateFunctions: true })), compiledApp);
  const code = await compile(instrumentApp(source), { privateFunctions: true });
  const panel = fs.readFileSync(path.join(nextHome.payload, 'visual-search/filter-panel-v1.js'), 'utf8');
  assert.equal(sha256(panel), filterPanelSha);
  const instrumentedPanel = instrument(panel, panelPhaseNames);
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
      const warm = await sample(browser, server, paths.search, rows, cpu, instrumentedPanel);
      const samples = [];
      for (let i = 0; i < 4; i++) samples.push(await sample(browser, server, paths.search, rows, cpu, instrumentedPanel));
      const summary = summarize(samples);
      console.log('SEARCH3_BUDGET_INPUT_PROFILE ' + JSON.stringify({ cpu, summary }));
      groups.push({ cpu, warm, samples, summary });
    }
    fs.writeFileSync(path.join(output, 'canonical-next-budget-input-profile.json'), JSON.stringify({
      diagnosticSource: process.env.SEARCH3_SOURCE_SHA, sourceCommit, releaseCommit, compiledApp,
      instrumentedApp, panelSha: filterPanelSha, instrumentedPanelSha: sha256(instrumentedPanel), styles, fixture: sha256(saved), browser: browser.version(), viewport: { ...viewport, dpr: 3, touch: true }, groups,
    }, null, 2) + '\n');
  } finally {
    await server.close();
  }
}

module.exports = profileBudgetInput;