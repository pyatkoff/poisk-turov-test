const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const events = new Map(), frames = [], classes = new Set();
let observeResults, cards = true, geometryReads = 0;
const properties = new Map();
const style = { setProperty(k,v) { properties.set(k,v); }, removeProperty(k) { properties.delete(k); } };
const counters = { textContent: '' };
const meta = { querySelector() { return counters; }, remove() {} };
const heading = { textContent: '' }, summary = { textContent: '' };
const tools = { style, parentElement: { getBoundingClientRect() { return { left: 0 }; } }, querySelector(s) { return s === 'strong' ? heading : s === '.search3-results-meta' ? meta : null; } };
const results = { querySelector() { return cards ? {} : null; }, getBoundingClientRect() { geometryReads++; return { width: 800, left: 200 }; } };
const form = { elements: {}, addEventListener() {} };
const document = { getElementById(id) { return { tourSearch: form, resultsTools: tools, resultSummary: summary, results }[id] || null; }, querySelector() { return null; }, body: { classList: { toggle(n,on) { on ? classes.add(n) : classes.delete(n); }, remove(...names) { names.forEach(n=>classes.delete(n)); } } } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../src/search3/behavior/results-top.js'),'utf8'), {
 document, window: { innerWidth: 1440, addEventListener(n,fn) { events.set(n,fn); } },
 MutationObserver: function(fn) { observeResults = fn; this.observe = ()=>{}; },
 requestAnimationFrame(fn) { frames.push(fn); }
});
const emit = (n,items) => events.get(n)({ detail: { items } });
const flush = () => { while(frames.length) frames.shift()(); };
geometryReads = 0;
emit('v2:results-rendered',[{ tours: [{},{}] }]); observeResults(); emit('resize'); observeResults();
assert.equal(frames.length,1,'event + mutations + resize share one frame'); flush();
assert.equal(geometryReads,1,'only one geometry measurement for the burst');
assert.equal(heading.textContent,'Найдено 2 тура');
classes.add('search3-editing-search'); emit('resize'); emit('resize'); flush();
assert.ok(classes.has('search3-editing-search'),'resize preserves active editor');
// A render event is queued before reset. Its old item count must not be used later.
emit('v2:results-rendered',[{ tours:[{}] }]); cards = false; emit('v2:search-reset'); flush();
assert.ok(!properties.has('width'),'queued geometry uses reset DOM instead of stale items');
assert.ok(!classes.has('search3-has-results'));
cards = true; observeResults(); flush();
assert.equal(properties.get('width'),'800px','later result insertion still updates geometry');
console.log('PASS: one results frame, editor preserved, no stale post-reset geometry');
