const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const events = new Map(), frames = [], classes = new Set();
let observeResults, cards = true, emptyLocal = false;
const properties = new Map();
const style = { setProperty(k,v,priority) { assert.equal(priority,'important'); properties.set(k,v); }, removeProperty(k) { properties.delete(k); } };
const counters = { textContent: '' };
const meta = { querySelector() { return counters; }, remove() {} };
const heading = { textContent: '' }, summary = { textContent: '' };
const tools = { style, parentElement: { getBoundingClientRect() { return { left: 0 }; } }, querySelector(s) { return s === 'strong' ? heading : s === '.search3-results-meta' ? meta : null; } };
const results = { querySelector() { return cards ? {} : null; } };
const form = { elements: {}, addEventListener() {} };
const document = { getElementById(id) { return { tourSearch: form, resultsTools: tools, resultSummary: summary, results }[id] || null; }, querySelector(selector) { return selector === '.results-filter-rail[data-s3-empty-results="1"]' && emptyLocal ? {} : null; }, body: { classList: { toggle(n,on) { on ? classes.add(n) : classes.delete(n); }, remove(...names) { names.forEach(n=>classes.delete(n)); } } } };
const window = { innerWidth: 1440, addEventListener(n,fn) { events.set(n,fn); } };
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname,'../v2/search3-results-filters-v1.js'),'utf8');
const bundledIife = require('./search3-bundle-iife.cjs');
vm.runInNewContext(bundledIife(bundle, { literal: '#resultsSearchRoute' }), {
 document, window,
 MutationObserver: function(fn) { observeResults = fn; this.observe = ()=>{}; },
 requestAnimationFrame(fn) { frames.push(fn); }
});
const emit = (n,items) => events.get(n)({ detail: { items } });
const flush = () => { while(frames.length) frames.shift()(); };
emit('v2:results-rendered',[{ tours: [{},{}] }]); observeResults(); observeResults();
assert.equal(frames.length,1,'result mutations share one state frame'); flush();
assert.equal(properties.size,0,'results presentation does not write inline geometry');
assert.equal(heading.textContent,'Найдено 2 тура');
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
