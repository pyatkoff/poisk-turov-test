const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

(async function () {
  const source = fs.readFileSync(path.join(__dirname, '../v2/tour-controller-v4.js'), 'utf8');
  const documentEvents = new Map();
  const frames = [];
  const returned = [];
  let listedButtons = [];
  let tourShouldFail = false;

  function action(className) {
    return {
      className,
      closest(selector) {
        if (selector === '.direct-tour') return className === 'direct-tour' ? this : null;
        if (selector === '.retry-tour' || selector === '.flight-variant' || selector === '.load-flights') return null;
        if (selector === '.back-results,.lead-success-back') return /back-results|lead-success-back/.test(className) ? this : null;
        return null;
      },
      matches() { return false; }
    };
  }

  function focusableTour(id) {
    const node = action('direct-tour');
    node.dataset = { tid: String(id) };
    node.disabled = false;
    node.hidden = false;
    node.connected = true;
    node.attributes = new Map();
    node.focuses = 0;
    node.scrolls = 0;
    node.getAttribute = name => node.attributes.get(name) || null;
    node.getClientRects = () => node.connected ? [{}] : [];
    node.focus = () => { node.focuses += 1; };
    node.scrollIntoView = options => { node.scrolls += 1; node.scrollOptions = options; };
    return node;
  }

  const selectedAttributes = new Map([['aria-hidden', 'true']]);
  const selected = {
    hidden: true,
    innerHTML: '',
    contains(node) { return node && node !== original && node !== replacement; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    focuses: 0,
    focus(options) { this.focuses++; this.focusOptions = options; },
    setAttribute(name, value) { selectedAttributes.set(name, value); },
    removeAttribute(name) { selectedAttributes.delete(name); },
    scrollIntoView() {}
  };
  const resultsAttributes = new Map();
  let fallbackBlur = null;
  const results = {
    focuses: 0,
    scrolls: 0,
    hasAttribute(name) { return resultsAttributes.has(name); },
    setAttribute(name, value) { resultsAttributes.set(name, value); },
    removeAttribute(name) { resultsAttributes.delete(name); },
    addEventListener(name, handler) { if (name === 'blur') fallbackBlur = handler; },
    focus() { this.focuses += 1; },
    scrollIntoView(options) { this.scrolls += 1; this.scrollOptions = options; }
  };
  const original = focusableTour(17);
  original.textContent = 'Выбрать тур';
  const replacement = focusableTour(17);
  const document = {
    body: { classList: { contains(name) { return name === 'search3-candidate'; } } },
    cookie: '',
    contains(node) { return !!(node && node.connected); },
    getElementById(id) { return id === 'selectedTour' ? selected : id === 'results' ? results : null; },
    querySelector() { return null; },
    querySelectorAll(selector) { return selector === '.direct-tour' ? listedButtons : []; },
    addEventListener(name, handler) { documentEvents.set(name, handler); }
  };
  const window = {
    V2_CONFIG: {},
    V2Runtime: {
      state: {},
      api(actionName) {
        if (actionName === 'tour' && tourShouldFail) return Promise.reject(new Error('fixture failure'));
        if (actionName === 'tour') return Promise.resolve({
          id: 17,
          hotel: { name: 'Test' },
          price: 100,
          hotelDescription: 'Номер 25 м&#178; &amp; SPA <b>рядом</b> &#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;'
        });
        if (actionName === 'flights') return Promise.resolve([]);
        throw new Error('unexpected API action');
      }
    },
    addEventListener() {},
    dispatchEvent(event) { if (event.type === 'v2:tour-returned') returned.push(event.detail); }
  };
  vm.runInNewContext(source, {
    window,
    document,
    CustomEvent: function (type, init) { this.type = type; this.detail = init && init.detail; },
    requestAnimationFrame(handler) { frames.push(handler); },
    URLSearchParams,
    location: { search: '', href: 'https://example.test/poisk-turov/' },
    FormData: function () {},
    Intl,
    Number,
    String,
    Array,
    Object,
    Promise,
    console
  }, { filename: 'tour-controller-v4.js' });
  const click = documentEvents.get('click');
  assert.equal(typeof click, 'function');

  click({ target: original, preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {} });
  assert.equal(original.disabled, true, 'source action is disabled while the tour loads');
  assert.equal(original.textContent, 'Загружаем…', 'source action reports its loading state');
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(selected.hidden, false, 'tour selection reveals the selected root');
  assert.equal(selected.focuses, 1, 'opening moves keyboard focus into selected tour');
  assert.equal(selected.focusOptions.preventScroll, true, 'focus preserves the explicit scroll transition');
  assert.equal(selectedAttributes.has('aria-hidden'), false, 'tour selection clears stale aria-hidden');
  assert.equal(original.disabled, false, 'source action is restored after tour load');
  assert.equal(original.textContent, 'Выбрать тур', 'successful load restores the renderer\'s exact action label');
  assert.match(selected.innerHTML, /<div class="hotel-desc">Номер 25 м² &amp; SPA рядом &lt;script&gt;alert\(1\)&lt;\/script&gt;<\/div>/,
    'supplier entities become readable text while decoded markup remains escaped');
  assert.doesNotMatch(selected.innerHTML, /<script>/, 'decoded supplier text cannot inject markup');

  const back = action('back-results');
  click({ target: back, preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {} });
  assert.equal(selected.hidden, true, 'return hides selected tour');
  assert.equal(selectedAttributes.get('aria-hidden'), 'true', 'return hides selected tour from accessibility tree');
  assert.equal(returned.length, 1);
  assert.equal(returned[0].source, original, 'return event identifies initiating action');
  frames.shift()();
  assert.equal(original.focuses, 1, 'initiating action receives focus');
  assert.equal(original.scrolls, 1, 'initiating action is revealed');
  assert.equal(original.scrollOptions.block, 'center');

  original.connected = false;
  listedButtons = [replacement];
  selected.hidden = false;
  click({ target: action('lead-success-back'), preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {} });
  assert.equal(returned[1].source, replacement, 'rerendered action with the same tour id is recovered');
  frames.shift()();
  assert.equal(replacement.focuses, 1);

  replacement.connected = false;
  listedButtons = [];
  selected.hidden = false;
  click({ target: back, preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {} });
  assert.equal(returned[2].source, null, 'missing source is reported truthfully');
  frames.shift()();
  assert.equal(results.focuses, 1, 'results receive fallback focus');
  assert.equal(resultsAttributes.get('tabindex'), '-1', 'fallback focus adds a temporary target');
  assert.equal(results.scrollOptions.block, 'center');
  fallbackBlur();
  assert.equal(resultsAttributes.has('tabindex'), false, 'temporary fallback tabindex is removed on blur');

  const failed = focusableTour(18);
  failed.textContent = 'Повторить загрузку тура';
  tourShouldFail = true;
  click({ target: failed, preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {} });
  assert.equal(failed.disabled, true, 'failed request keeps the action disabled while pending');
  assert.equal(failed.textContent, 'Загружаем…');
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(failed.disabled, false, 'failed request re-enables its source action');
  assert.equal(failed.textContent, 'Повторить загрузку тура', 'failed request restores the exact retry label');
  assert.match(selected.innerHTML, /Не удалось загрузить выбранный тур: fixture failure/);

  console.log('PASS: current tour controller owns exact source and fallback return lifecycle');
})();
