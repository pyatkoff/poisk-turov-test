const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const php = fs.readFileSync('v2/home-v1.php', 'utf8');
const match = php.match(/<script>\s*(\(function\(\)\{[\s\S]*?\}\)\(\);)\s*<\/script>/);
assert(match, 'homepage controller script remains discoverable');
const script = match[1].replace('<?=json_encode($homeForm[\'child_ages\'])?>', '[]');

class Target {
  constructor() { this.listeners = new Map(); this.attributes = new Map(); this.dataset = {}; }
  addEventListener(type, callback) { const list = this.listeners.get(type) || []; list.push(callback); this.listeners.set(type, list); }
  dispatch(type) { const event = { target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } }; for (const callback of this.listeners.get(type) || []) callback(event); return event; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  removeAttribute(name) { this.attributes.delete(name); }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
}
class OptionStub {
  constructor(text = '', value = '') { this.textContent = text; this.value = String(value); }
}
class SelectStub extends Target {
  constructor(name, value) { super(); this.name = name; this.value = String(value); this.disabled = false; this.required = false; this.options = [new OptionStub('Загружаем…', value)]; }
  set innerHTML(value) { if (value === '') this.options = []; }
  appendChild(option) { this.options.push(option); if (this.options.length === 1) this.value = option.value; }
  add(option) { this.appendChild(option); }
}
class BoxStub extends Target {
  constructor() { super(); this.hidden = false; }
  querySelectorAll() { return []; }
  replaceChildren() {}
  append() {}
}
async function run(failFirstDeparture = false) {
const departure = new SelectStub('from', '1');
const country = new SelectStub('country', '4'); country.disabled = true; country.required = true;
const childCount = new SelectStub('', '0');
const childBox = new BoxStub();
const submit = new Target(); submit.disabled = true;
const retry = new Target(); retry.hidden = true;
const catalogError = new Target(); catalogError.hidden = true;
const more = new Target(); more.href = '/poisk-turov/'; more.setAttribute('aria-disabled', 'true'); more.setAttribute('tabindex', '-1');
const values = { dateFrom: '2099-09-10', dateTo: '2099-09-20', daysFrom: '7', daysTill: '10', count_people: '2' };
const form = new Target();
form.action = '/poisk-turov/';
form.dataset = { countriesBusy: 'true' };
form.elements = { daysFrom: { value: '7', setCustomValidity() {} }, daysTill: { value: '10', setCustomValidity() {} } };
form.querySelector = selector => ({ '[data-home-children]': childCount, '[data-home-child-ages]': childBox, '.at-home-search__more': more, 'button[type="submit"]': submit, '[data-home-catalog-retry]': retry, '[data-home-catalog-error]': catalogError })[selector];
form.reportValidity = () => !country.required || (!country.disabled && country.value !== '');

class FormDataStub {
  constructor() { this.entries = [...(!departure.disabled ? [['from', departure.value]] : []), ...(!country.disabled ? [['country', country.value]] : []), ...Object.entries(values)]; }
  *[Symbol.iterator]() { yield* this.entries; }
}

const pendingCountries = [];
let departureCalls = 0;
const fetch = url => {
  const action = new URL(url).searchParams.get('action');
  if (action === 'departures') { departureCalls++; if (failFirstDeparture && departureCalls === 1) return Promise.reject(new Error('offline')); return Promise.resolve({ ok: true, json: async () => [{ id: 1, name: 'Москва' }, { id: 2, name: 'Казань' }] }); }
  return new Promise((resolve, reject) => pendingCountries.push(items => items instanceof Error ? reject(items) : resolve({ ok: true, json: async () => items })));
};
const document = {
  querySelector: selector => ({ '[data-home-departures]': departure, '[data-home-countries]': country, '[data-home-search]': form })[selector],
  createElement: tag => tag === 'option' ? new OptionStub() : new BoxStub(),
};
const context = { document, fetch, FormData: FormDataStub, Option: OptionStub, URL, URLSearchParams, location: { origin: 'https://anytoour.ru' }, console };
vm.runInNewContext(script, context, { filename: 'home-v1.php:inline' });

const tick = () => new Promise(resolve => setImmediate(resolve));
  await tick(); await tick();
  if (failFirstDeparture) {
    assert.equal(retry.hidden, false, 'failed departures exposes explicit retry');
    assert.equal(catalogError.hidden, false, 'failure is announced');
    assert.equal(more.dispatch('click').defaultPrevented, true, 'failure cannot hand off an empty country');
    values.dateFrom = '2099-10-01'; values.count_people = '3';
    retry.dispatch('click'); retry.dispatch('click');
    await tick(); await tick();
    assert.equal(departureCalls, 2, 'double click starts only one retry');
  }
  assert.equal(pendingCountries.length, 1, 'initial countries request remains pending');
  assert.equal(country.disabled, true, 'country stays disabled while its catalog loads');
  assert.equal(submit.disabled, true, 'primary handoff is unavailable while country is incomplete');
  assert.equal(more.getAttribute('aria-disabled'), 'true', 'advanced handoff exposes its unavailable state');
  assert.equal(form.dispatch('submit').defaultPrevented, true, 'keyboard submit cannot lose the country');
  assert.equal(more.dispatch('click').defaultPrevented, true, 'advanced handoff cannot lose the country');

  pendingCountries.shift()([{ id: 4, name: 'Турция' }]);
  await tick(); await tick();
  assert.equal(country.disabled, false, 'country becomes available after the catalog settles');
  assert.equal(submit.disabled, false, 'primary handoff becomes available with a country');
  assert.equal(more.getAttribute('aria-disabled'), 'false', 'advanced handoff becomes available with a country');
  assert.equal(form.dispatch('submit').defaultPrevented, false, 'ready primary handoff remains native');
  assert.equal(more.dispatch('click').defaultPrevented, false, 'ready advanced handoff remains native');
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('country'), '4', 'advanced handoff keeps the selected country');
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('from'), '1', 'recovered departure is included in handoff');
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('dateFrom'), values.dateFrom, 'retry preserves edited dates');
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('count_people'), values.count_people, 'retry preserves party');
  assert.equal(retry.hidden, true, 'successful recovery removes retry');

  departure.value = '1'; departure.dispatch('change'); await tick();
  departure.value = '2'; departure.dispatch('change'); await tick();
  assert.equal(pendingCountries.length, 2, 'rapid departure changes start two country requests');
  pendingCountries[1]([{ id: 9, name: 'Египет' }]); await tick(); await tick();
  assert.equal(country.value, '9', 'latest country response wins');
  assert.equal(submit.disabled, false, 'latest response releases both handoffs');
  pendingCountries[0](new Error('stale failure')); await tick(); await tick();
  assert.equal(country.value, '9', 'stale country response cannot overwrite the latest departure');
  assert.equal(retry.hidden, true, 'stale failure cannot expose retry for the current catalog');
  pendingCountries.length = 0;
  departure.dispatch('change'); await tick();
  pendingCountries.shift()(new Error('offline')); await tick(); await tick();
  assert.equal(retry.hidden, false, 'country failure exposes retry');
  const callsBeforeRetry = departureCalls;
  retry.dispatch('click'); retry.dispatch('click'); await tick();
  assert.equal(pendingCountries.length, 1, 'country retry is single-flight');
  assert.equal(departureCalls, callsBeforeRetry, 'country retry does not reload departures');
  pendingCountries.shift()([{ id: 9, name: 'Египет' }]); await tick(); await tick();
  assert.equal(country.value, '9');
  assert.equal(retry.hidden, true);
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('from'), '2');
  assert.equal(new URL(more.href, 'https://anytoour.ru').searchParams.get('dateFrom'), values.dateFrom);
}

(async () => {
  await run(); await run(true);
  console.log('HOME_SEARCH_LOADING_HANDOFF_OK');
})().catch(error => { console.error(error); process.exit(1); });
