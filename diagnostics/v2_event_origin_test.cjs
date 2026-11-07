const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class TestCustomEvent extends Event {
  constructor(type, { detail } = {}) {
    super(type);
    this.detail = detail;
  }
}

const settle = () => new Promise(resolve => setImmediate(resolve));

function fixture({ autoSearch = false, deferFirstSearch = false, markedDefault = true } = {}) {
  const fields = {
    from: '1', country: '4', dateFrom: '2099-06-01', dateTo: '2099-06-03',
    daysFrom: '7', daysTill: '7', count_people: '2',
  };
  const form = Object.assign(new EventTarget(), {
    elements: Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, { value }])),
    querySelector() { return {}; },
    querySelectorAll() { return []; },
  });
  const nodes = {
    tourSearch: form, status: {}, results: {}, resultsTools: {},
    selectedTour: { querySelector() { return null; }, scrollIntoView() {} },
  };
  const document = Object.assign(new EventTarget(), {
    getElementById(id) { return nodes[id] || null; },
    createElement() { throw new Error('Unexpected resource request'); },
  });
  const timers = new Map();
  const goals = [];
  const searches = [];
  const flights = [];
  let timerId = 0;
  let searchCount = 0;
  let finishFirstSearch;
  const window = Object.assign(new EventTarget(), {
    location: { search: autoSearch ? '?from=1&country=4&date_from=2099-06-01' : '' },
    V2_CONFIG: { metrikaCounter: 98615635 },
    V2Runtime: {
      setSearchId() {},
      async api(action) {
        switch (action) {
          case 'search_start':
            searchCount++;
            if (deferFirstSearch && searchCount === 1) {
              return new Promise(resolve => { finishFirstSearch = resolve; });
            }
            return { searchId: searchCount };
          case 'tour':
            return { id: 'fixture-tour', price: 100000 };
          case 'flights':
            return { flights: [{ isDefault: markedDefault, price: 100000 }] };
          default:
            throw new Error('Unexpected API action: ' + action);
        }
      },
    },
    V2Catalogs: { renderChildAges() {}, async init() {} },
    V2Results: {},
    dataLayer: [],
    ym(_counter, method, name, params) {
      if (method === 'reachGoal') goals.push({ name, params: JSON.parse(JSON.stringify(params)) });
    },
  });
  window.addEventListener('v2:search-started', event => searches.push(event.detail));
  window.addEventListener('v2:flight-selected', event => flights.push(event.detail));
  const context = vm.createContext({
    window, document, console, URLSearchParams, CustomEvent: TestCustomEvent,
    performance: { now: () => 100 },
    FormData: class {
      constructor(form) { this.elements = form.elements; }
      get(name) { return this.elements[name]?.value ?? null; }
      getAll() { return []; }
    },
    setTimeout(handler, delay) {
      timers.set(++timerId, { handler, delay });
      return timerId;
    },
    clearTimeout(id) { timers.delete(id); },
  });
  for (const module of ['analytics-v4.js', 'tour-controller-v4.js', 'search-lifecycle-v6.js']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'v2', module), 'utf8'), context, { filename: module });
  }
  return {
    window, searches, flights, goals,
    async runBootSearch() {
      await settle();
      const boot = Array.from(timers.entries()).filter(([, timer]) => timer.delay === 0);
      assert.equal(boot.length, 1);
      timers.delete(boot[0][0]);
      boot[0][1].handler();
      await settle();
    },
    async submitForm() {
      form.dispatchEvent(new Event('submit', { cancelable: true }));
      await settle();
    },
    async finishSupersededSearch() {
      finishFirstSearch({ searchId: 1 });
      await settle();
    },
    async selectTour() {
      await window.V2TourController.selectTour('fixture-tour');
      await settle();
    },
    selectFirstFlight() {
      const event = new Event('change');
      Object.defineProperty(event, 'target', { value: {
        checked: true,
        matches(selector) { return selector === '#selectedTour input[name="v2flight"]'; },
        closest() { return { dataset: { flightIndex: '0' } }; },
      } });
      document.dispatchEvent(event);
    },
  };
}

function assertSearchGoals(f, searchIds) {
  const goals = f.goals.filter(goal => goal.name === 'V2_SEARCH_STARTED');
  assert.deepEqual(goals, searchIds.map(searchId => ({ name: 'V2_SEARCH_STARTED', params: { searchId } })));
  const dataLayer = JSON.parse(JSON.stringify(f.window.dataLayer));
  assert.deepEqual(dataLayer.filter(event => event.event === 'v2_search_started').map(event => event.v2),
    searchIds.map(searchId => ({ searchId })));
}

(async () => {
  const search = fixture({ autoSearch: true });
  await search.runBootSearch();
  await search.submitForm();
  await search.window.V2SearchLifecycle.submit('form_submit');
  assert.deepEqual(search.searches.map(event => event.origin), ['url_hydration', 'form_submit', 'unspecified']);
  assertSearchGoals(search, [1, 2, 3]);

  const stale = fixture({ autoSearch: true, deferFirstSearch: true });
  await stale.runBootSearch();
  await stale.submitForm();
  await stale.finishSupersededSearch();
  assert.deepEqual(stale.searches.map(event => event.origin), ['form_submit']);
  assertSearchGoals(stale, [2]);

  for (const markedDefault of [true, false]) {
    const flight = fixture({ markedDefault });
    await flight.selectTour();
    flight.selectFirstFlight();
    assert.deepEqual(flight.flights.map(event => event.origin), ['default_assignment', 'flight_control']);
    assert.deepEqual(flight.flights.map(event => event.index), [0, 0]);
    const goals = flight.goals.filter(goal => goal.name === 'V2_FLIGHT_SELECTED');
    assert.equal(goals.length, 2);
    assert.deepEqual(goals[0], goals[1]);
    assert.equal(goals[0].params.isDefault, markedDefault);
    assert.ok(!Object.hasOwn(goals[0].params, 'origin'));
    const dataLayer = JSON.parse(JSON.stringify(flight.window.dataLayer));
    assert.deepEqual(dataLayer.filter(event => event.event === 'v2_flight_selected').map(event => event.v2),
      goals.map(goal => goal.params));
  }

  console.log('V2_EVENT_ORIGIN_OK');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
