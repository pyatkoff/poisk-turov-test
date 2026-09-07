/* The note must still render when the summary is absent or the lead form is replaced. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const iife = require('./search3-bundle-iife.cjs');
const bundle = fs.readFileSync(process.argv[2] || path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const events = new Map(), timers = [];
let hasRoot = true, hasForm = true, form = makeForm();
function makeForm() {
  const title = { textContent: '' }, subtitle = { textContent: '' }, classes = new Set();
  let note = null, inserts = 0;
  return {
    querySelector(s) {
      if (s === '.section-heading') return { querySelector: s => s === 'strong' ? title : subtitle };
      if (s === '.search3-lead-protection') return note;
      if (s === 'textarea[name="comment"]') return { closest: () => ({ classList: { add: cls => classes.add(cls) } }) };
      throw Error('unexpected form selector: ' + s);
    },
    appendChild(n) { note = n; inserts++; },
    snapshot() { return { title: title.textContent, subtitle: subtitle.textContent, note, classes: [...classes], inserts }; }
  };
}
const root = { classList: { remove() {}, contains() { return false; } }, children: [], querySelector: s => s === '.lead-form' && hasForm ? form : null };
const window = { addEventListener(name, fn) { if (!events.has(name)) events.set(name, []); events.get(name).push(fn); } };
const source = [...new Set([iife(bundle, { global: 'Search3SummaryCta' }), iife(bundle, { literal: 'search3-lead-protection' })])].join('\n');
vm.runInNewContext(source, {
  window, document: { getElementById() { return hasRoot ? root : null; }, addEventListener() {}, createElement(tag) { assert.equal(tag, 'p'); return {}; } },
  setTimeout(fn) { timers.push(fn); }, clearTimeout() {}
});
const emit = name => { for (const fn of events.get(name) || []) fn({}); };
const flush = () => { while (timers.length) timers.shift()(); };
emit('v2:tour-selected');
const pending = timers.length;
flush();
assert.deepEqual(form.snapshot(), {
  title: 'Оставьте заявку',
  subtitle: 'Мы отправим выбранный тур менеджеру. Он свяжется с вами в ближайшее время.',
  note: { className: 'search3-lead-protection', innerHTML: '<span aria-hidden="true">♢</span> Проверьте контактные данные перед отправкой заявки.' },
  classes: ['search3-lead-comment'], inserts: 1
});
emit('v2:booking-review'); flush();
assert.equal(form.snapshot().inserts, 1, 'repeated review retains the existing note');
form = makeForm(); emit('v2:booking-review'); flush();
assert.equal(form.snapshot().inserts, 1, 'replacement lead form receives its own note');
hasForm = false; emit('v2:tour-selected'); flush();
hasRoot = false; emit('v2:booking-review'); flush();
assert.equal(form.snapshot().inserts, 1, 'missing root/form is harmless');
hasRoot = hasForm = true; form = makeForm();
for (const event of ['v2:lead-started', 'v2:lead-success', 'v2:lead-error', 'search3:lead-entry']) { emit(event); flush(); }
assert.equal(form.snapshot().inserts, 0, 'lifecycle events retain their original scope');
console.log(JSON.stringify({ pending }));
assert.equal(pending, 1, 'CTA and note share the existing task on tour selection');
console.log('PASS: lead note copy, idempotence, replacement and missing-form behavior are preserved');
