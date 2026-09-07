const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const events = new Map(), frames = [], timers = [], classes = new Set();
let observeResults, cards = true, emptyLocal = false;
let scrolled = 0, focused = 0;
const properties = new Map();
const style = { setProperty(k,v,priority) { assert.equal(priority,'important'); properties.set(k,v); }, removeProperty(k) { properties.delete(k); } };
const heading = { textContent: '' }, summary = { textContent: '' };
const tools = { style, parentElement: { getBoundingClientRect() { return { left: 0 }; } }, querySelector(s) { assert.notEqual(s, '.search3-results-meta', 'hidden duplicate counters have no runtime owner'); assert.notEqual(s, '.results-map-button', 'retired map control has no runtime owner'); return s === 'strong' ? heading : null; } };
const results = {
 querySelector() { return cards ? {} : null; },
 querySelectorAll() { return []; },
 contains() { return false; }
};
const route = { textContent: '' };
const searchSummary = { querySelector() { return route; } };
const focusTarget = { focus() { focused++; } };
const edit = { addEventListener(type, handler) { assert.equal(type, 'click'); this.click = handler; } };
const form = { elements: { from: { value: 'Москва' }, country: { value: 'Турция' }, region: { value: 'Сиде' } }, addEventListener() {}, scrollIntoView() { scrolled++; }, querySelector() { return focusTarget; } };
const document = { getElementById(id) { return { tourSearch: form, resultsTools: tools, resultSummary: summary, resultsSearchSummary: searchSummary, resultsSearchEdit: edit, results }[id] || null; }, querySelector(selector) { assert.notEqual(selector, '.search3-page-intro', 'static intro stays owned by search-form'); return selector === '.results-filter-rail[data-s3-empty-results="1"]' && emptyLocal ? {} : null; }, addEventListener() {}, body: { classList: { contains(n) { return n === 'search3-candidate' || classes.has(n); }, add(n) { classes.add(n); }, toggle(n,on) { on ? classes.add(n) : classes.delete(n); }, remove(...names) { names.forEach(n=>classes.delete(n)); } } } };
const window = { innerWidth: 1440, addEventListener(n,fn) { assert.ok(!events.has(n), 'one listener per results lifecycle event'); events.set(n,fn); }, setTimeout() { return 1; }, clearTimeout() {}, matchMedia() { return { addEventListener() {} }; } };
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname,'../v2/search3-results-filters-v1.js'),'utf8');
const bundledIife = require('./search3-bundle-iife.cjs');
vm.runInNewContext(bundledIife(bundle, { literal: '#resultsSearchRoute' }), {
 document, window,
 MutationObserver: function(fn) { observeResults = fn; this.observe = ()=>{}; },
 requestAnimationFrame(fn) { frames.push(fn); },
 setTimeout(fn) { timers.push(fn); }
});
const emit = (n,items) => events.get(n)({ detail: { items } });
const flush = () => { while(frames.length) frames.shift()(); };
assert.ok(classes.has('search3-has-results'), 'initial result state is synchronized');
assert.equal(route.textContent,'Москва → Турция, Сиде');
edit.click();
assert.ok(classes.has('search3-editing-search'));
assert.equal(scrolled, 1);
assert.equal(timers.length, 1);
timers.shift()();
assert.equal(focused, 1);
emit('v2:results-rendered',[{ tours: [{},{}] }]); observeResults(); observeResults();
assert.equal(frames.length,1,'result mutations share one state frame'); flush();
assert.equal(properties.size,0,'results presentation does not write inline geometry');
assert.equal(heading.textContent,'Найдено 2 тура');
assert.equal(summary.textContent,'1 отель · актуальные варианты');
assert.equal(route.textContent,'Москва → Турция, Сиде');
assert.ok(!events.has('resize'),'responsive geometry is owned by CSS');
// A render event is queued before reset. Its old item count must not be used later.
emit('v2:results-rendered',[{ tours:[{}] }]); cards = false; emit('v2:search-reset'); flush();
assert.ok(!classes.has('search3-has-results'));
cards = true; observeResults(); flush();
assert.ok(classes.has('search3-has-results'),'later result insertion still updates state');
window.innerWidth = 1440; cards = false; emptyLocal = true;
emit('v2:results-rendered', []); observeResults(); flush();
assert.ok(classes.has('search3-has-results'), 'local zero matches retain the results shell');
assert.equal(heading.textContent, 'Найдено 0 туров');
emptyLocal = false; emit('v2:search-reset'); observeResults(); flush();
assert.ok(!classes.has('search3-has-results'), 'a true search reset clears the empty-filter shell');
assert.equal(properties.size,0,'reset does not write inline geometry');
console.log('PASS: one results state frame and CSS-owned geometry');
