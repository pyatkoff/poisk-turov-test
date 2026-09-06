const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = ['presentation-labels.js', 'results-presentation.js']
  .map(name => fs.readFileSync(path.join(__dirname, '../src/search3/behavior', name), 'utf8')).join('');

function harness() {
  const events = new Map(), mediaEvents = new Map(), timers = new Map();
  const classes = new Set(['search3-candidate']);
  let timerId = 0, toolbar = null, available = true;
  const stats = { timers: 0, mounts: 0, moves: 0 };
  function control(value) {
    const listeners = new Map();
    return {
      value, innerHTML: '<option value="price">Цена</option><option value="rating">Рейтинг</option>', listeners,
      addEventListener(type, callback) { assert.ok(!listeners.has(type), 'one listener per control'); listeners.set(type, callback); },
      dispatchEvent(event) { const callback = listeners.get(event.type); if (callback) callback(event); }
    };
  }
  const sort = control('price'), proxy = control('');
  const filterBar = { parentElement: null };
  const slot = { appendChild(node) { assert.equal(node, filterBar); node.parentElement = slot; stats.moves++; } };
  const body = { classList: {
    contains: name => classes.has(name),
    toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); },
    remove: name => classes.delete(name)
  } };
  const results = { querySelectorAll: () => [] };
  const tools = { insertAdjacentElement(where, node) { assert.equal(where, 'afterend'); toolbar = node; stats.mounts++; } };
  const document = {
    body,
    getElementById: id => ({ results, resultsTools: tools, sortResults: sort }[id] || null),
    querySelector: selector => selector === '.mrf-bar' ? (available ? filterBar : null) : selector === '.search3-mobile-toolbar' ? toolbar : null,
    createElement(tag) {
      assert.equal(tag, 'div');
      return { className: '', innerHTML: '', querySelector: selector => selector === 'select' ? proxy : selector === '.search3-mobile-filter-slot' ? slot : null };
    },
    addEventListener() {}
  };
  const window = {
    addEventListener(type, callback) { events.set(type, callback); },
    setTimeout(callback) { const id = timerId++; timers.set(id, callback); stats.timers++; return id; },
    clearTimeout(id) { timers.delete(id); },
    matchMedia: () => ({ addEventListener(type, callback) { mediaEvents.set(type, callback); } })
  };
  class Event { constructor(type, options) { this.type = type; this.bubbles = !!(options && options.bubbles); } }
  vm.runInNewContext(source, { window, document, Event }, { filename: 'results-presentation.js' });
  return {
    stats, timers, sort, proxy, filterBar, slot,
    results(items = [{ id: 'hotel' }]) { events.get('v2:results-rendered')({ detail: { items } }); },
    reset() { events.get('v2:search-reset')(); },
    compact(matches = true) { mediaEvents.get('change')({ matches }); },
    setBarAvailable(value) { available = value; },
    flush() { const pending = [...timers.values()]; timers.clear(); pending.forEach(callback => callback()); },
    change(control, value) { control.value = value; control.dispatchEvent(new Event('change', { bubbles: true })); }
  };
}

{
  const h = harness();
  for (let i = 0; i < 20; i++) { h.results([{ id: 'hotel-' + i }]); h.compact(); }
  assert.equal(h.timers.size, 1, 'progressive results and breakpoint bursts share one deferred mount, including timer id zero');
  h.sort.value = 'rating';
  h.flush();
  assert.equal(h.stats.mounts, 1);
  assert.equal(h.stats.moves, 1);
  assert.equal(h.proxy.value, 'rating', 'mount consumes the latest native sort state');
  assert.equal(h.filterBar.parentElement, h.slot, 'reuse the canonical filter bar');
  h.change(h.proxy, 'price');
  assert.equal(h.sort.value, 'price', 'proxy preserves native change handoff');
  h.change(h.sort, 'rating');
  assert.equal(h.proxy.value, 'rating', 'native sort keeps proxy synchronized');
  h.results(); h.compact(); h.flush();
  assert.equal(h.stats.mounts, 1, 'no duplicate toolbar or control listeners');
  assert.equal(h.stats.moves, 1, 'mounted filter bar is not moved again');
}
{
  const h = harness();
  h.results(); h.reset();
  assert.equal(h.timers.size, 0, 'reset cancels pending presentation work');
  h.compact();
  assert.equal(h.timers.size, 0, 'empty search does not queue a toolbar');
  h.flush(); assert.equal(h.stats.mounts, 0);
  h.results(); h.results([]);
  assert.equal(h.timers.size, 0, 'empty results cancel an obsolete mount');
  h.results(); h.flush();
  assert.equal(h.stats.mounts, 1, 'fresh results after cancellation still mount');
}
{
  const h = harness();
  h.setBarAvailable(false); h.results(); h.flush();
  assert.equal(h.stats.mounts, 0, 'missing canonical filter bar remains a safe no-op');
  h.setBarAvailable(true); h.compact(); h.flush();
  assert.equal(h.stats.mounts, 1, 'next eligible event recovers after late filter-bar initialization');
  h.compact(false);
  assert.equal(h.timers.size, 0, 'desktop breakpoint does not mount mobile controls');
}
console.log('PASS: one deferred mobile toolbar mount; reset/empty cancellation, late owner and sort handoff preserved');
