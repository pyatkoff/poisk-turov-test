const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'src/search3/manifest.json'), 'utf8'));
assert.deepEqual(manifest.assets['search3-selected-flow-v2.js'], [], 'public selected slot is retained but empty');
assert.equal(fs.existsSync(path.join(root, 'src/search3/behavior/selected-flow-v2.js')), false);
assert.equal(fs.existsSync(path.join(root, 'src/search3/behavior/selected/flight-fallback.js')), false);

const bundle = fs.readFileSync(path.join(root, 'v2/bundle-manifest-v1.php'), 'utf8');
assert.match(bundle, /\$selected = \[[^\]]*'tour-controller-v4\.js'[^\]]*'flight-empty-recovery-v1\.js'[^\]]*'flight-price-sync-v1\.js'/s);
const recovery = fs.readFileSync(path.join(root, 'v2/flight-empty-recovery-v1.js'), 'utf8');
const price = fs.readFileSync(path.join(root, 'v2/flight-price-sync-v1.js'), 'utf8');
const summary = fs.readFileSync(path.join(root, 'src/search3/behavior/summary-cta.js'), 'utf8');
for (const marker of ['Проверить рейсы ещё раз', 'MutationObserver(queue)', "window.addEventListener('v2:tour-selected'"]) assert.ok(recovery.includes(marker), marker);
for (const marker of ['displayedVariantPrice', 'priceTradeoff', 'clarifyVariantChoices', "window.addEventListener('v2:flight-selected'"]) assert.ok(price.includes(marker), marker);
for (const marker of ['selectedState', 'correctTradeoffs', 'MutationObserver', 'load-flights', 'Продолжить к заявке', 'search3:lead-entry']) assert.ok(summary.includes(marker), marker);

const events = new Map();
const bodyClasses = new Set(['search3-candidate']);
const selectedClasses = new Set();
let focused = 0;
const labels = [
  { textContent: 'К минимальной цене', classList: { toggle() {} } },
  { textContent: 'К минимальной цене', classList: { toggle() {} } }
];
const variants = [72832, '90 049,6'].map((value, index) => ({
  querySelector(selector) { return selector === '.flight-choice>b' ? { textContent: `Стоимость тура: ${value} ₽` } : null; },
  querySelectorAll(selector) { return selector === '.flight-choice-tradeoffs span' ? [labels[index]] : []; }
}));
const button = { textContent: '' };
const action = { hidden: true, querySelector() { return button; } };
const flights = { querySelector() { return action; }, appendChild() {} };
const form = { scrollIntoView() {}, querySelector() { return { focus() { focused += 1; } }; } };
const selected = {
  classList: {
    add(name) { selectedClasses.add(name); },
    remove(...names) { names.forEach(name => selectedClasses.delete(name)); }
  },
  querySelector(selector) {
    if (selector === '.tour-flights') return flights;
    if (selector === '.lead-form') return form;
    return null;
  }
};
const document = {
  body: { classList: { toggle(name, active) { active ? bodyClasses.add(name) : bodyClasses.delete(name); } } },
  getElementById(id) { return id === 'selectedTour' ? selected : null; },
  querySelectorAll(selector) { return selector === '#selectedTour .flight-variant' ? variants : []; },
  addEventListener(name, handler) { events.set(name, handler); },
  createElement() { throw new Error('existing CTA must be reused'); }
};
const window = {
  addEventListener(name, handler) { events.set(name, handler); },
  dispatchEvent() {}
};
vm.runInNewContext(summary, {
  window, document, Intl, String, Number, setTimeout(handler) { handler(); },
  CustomEvent: function (type, options) { this.type = type; this.detail = options?.detail; }
});

events.get('v2:tour-selected')({ detail: { tour: { id: 'tour-1' } } });
assert.ok(bodyClasses.has('search3-selected-open'));
assert.equal(button.textContent, 'Продолжить к заявке');
events.get('v2:flight-selected')({ detail: {} });
assert.equal(labels[0].textContent, 'Самая низкая цена');
assert.equal(labels[1].textContent.replace(/\s/g, ' '), '+17 217,6 ₽ к минимальной');
events.get('v2:tour-returned')({ detail: {} });
assert.equal(bodyClasses.has('search3-selected-open'), false);
window.Search3SummaryCta.enterLead('flight');
assert.ok(selectedClasses.has('search3-lead-entry'));
assert.equal(focused, 1);
assert.equal(window.Search3SummaryCta.version, 13);

console.log('PASS: selected public adapter is retired; canonical recovery/price and native handoff remain');
