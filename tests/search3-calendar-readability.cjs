/* Called from the existing served-entry test; inherits its offline request guard. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
async function checkDisclosureState(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
  const items = [{ tours: [
    { date: '2099-09-01', price: 150000 },
    { date: '2099-09-02', price: 140000 },
    { date: '2099-09-03', price: 145000 },
  ] }];
  const emit = (name, values) => page.evaluate(({ name, values }) => {
    window.dispatchEvent(new CustomEvent(name, { detail: { items: values } }));
  }, { name, values });
  const expanded = () => calendar.locator('details').evaluate(node => node.open);
  await emit('v2:search-started', []);
  await emit('v2:search-complete', items);
  assert.equal(await expanded(), true, 'each Search3 width starts with an open calendar');
  const records = [];
  for (const open of [false, true]) {
    if (await expanded() !== open) await calendar.locator('summary').click();
    for (const count of [0, 1]) {
      const filtered = count ? [{ tours: items[0].tours.slice(0, count) }] : [];
      await emit('search3:local-results-filtered', filtered);
      assert.equal(await calendar.evaluate(node => node.hidden), true, 'insufficient dates hide the calendar');
      assert.equal(await calendar.innerHTML(), '', 'hidden calendar retains no old prices or actionable dates');
      await emit('search3:local-results-filtered', items);
      assert.equal(await calendar.evaluate(node => node.hidden), false);
      assert.equal(await expanded(), open, `user disclosure choice survives ${count} dates and local reset`);
      assert.equal(await calendar.locator('[data-calendar-date]').count(), 3);
      records.push({ open, transientDates: count, restoredDates: 3 });
    }
    await emit('v2:search-continued', items);
    assert.equal(await expanded(), open, 'progressive completion preserves the same disclosure choice');
    if ([375, 1440].includes(width)) await calendar.screenshot({ path: path.join(output, `calendar-disclosure-${width}-${open ? 'open' : 'closed'}.png`) });
  }
  await calendar.locator('summary').click();
  assert.equal(await expanded(), false);
  await emit('v2:search-started', []);
  await emit('v2:search-complete', items);
  assert.equal(await expanded(), true, 'new search restores the initial open contract');
  await emit('v2:search-reset', []);
  assert.equal(await calendar.evaluate(node => node.hidden), true);
  return { records, progressiveChoicePreserved: true, newSearchInitiallyOpen: true };
}
async function checkDateIntegrity(page, width, output) {
  const cases = [
    ['2028-02-29', '2028-02-29'], ['29.02.2028', '2028-02-29'],
    ['2000-02-29', '2000-02-29'], ['2400-02-29', '2400-02-29'],
    ['2026-04-30', '2026-04-30'], [' 05.10.2026 ', '2026-10-05'],
    ['2099-12-31', '2099-12-31'], ['2026-02-30', ''], ['31.04.2026', ''],
    ['2026-02-29', ''], ['1900-02-29', ''], ['2100-02-29', ''],
    ['2026-13-01', ''], ['2026-00-01', ''], ['2026-01-00', ''],
    ['2026-01-32', ''], ['0000-01-01', ''], ['05/10/2026', ''],
    ['2026-10-05T00:00:00Z', ''], ['<img src=x onerror=alert(1)>', ''], ['', ''], [null, ''],
  ];
  const parsed = await page.evaluate(values => values.map(value => window.V2CurrentPriceCalendar.dateValue(value)), cases.map(pair => pair[0]));
  assert.deepEqual(parsed, cases.map(pair => pair[1]), 'real date-only values including leap centuries; no rollover or ambiguous parsing');
  const integrity = await page.evaluate(() => {
    const api = window.V2CurrentPriceCalendar;
    const invalid = [{ date: '2096-02-30', price: 1 }, { date: '31.04.2096', price: 2 }, { date: '2096-13-01', price: 3 }];
    const items = [{ tours: [...invalid, { date: '2096-02-29', price: 130000 }, { date: '29.02.2096', price: 120000 }, { date: '2096-03-01', price: 125000 }, { date: '2096-03-02', price: 140000 }] }];
    const original = JSON.stringify(items);
    items[0].tours.forEach(Object.freeze); Object.freeze(items[0].tours); Object.freeze(items[0]); Object.freeze(items);
    const invalidOnly = api.render([{ tours: invalid }]), hiddenForInvalid = document.getElementById('currentPriceCalendar').hidden;
    const result = api.render(items), box = document.getElementById('currentPriceCalendar');
    box.querySelector('details').open = true;
    const tiles = [...box.querySelectorAll('[data-calendar-date]')].map(node => ({ date: node.dataset.calendarDate, label: node.querySelector('span').textContent, accessible: node.getAttribute('aria-label'), best: node.classList.contains('is-best') }));
    return { invalidOnly, hiddenForInvalid, result, tiles, unchanged: original === JSON.stringify(items) };
  });
  assert.deepEqual(integrity.invalidOnly, []);
  assert.equal(integrity.hiddenForInvalid, true, 'no invalid-only price comparison is shown');
  assert.deepEqual(integrity.result, [{ date: '2096-02-29', price: 120000 }, { date: '2096-03-01', price: 125000 }, { date: '2096-03-02', price: 140000 }], 'invalid cheap dates cannot win; valid aliases keep their actual daily minimum');
  assert.equal(integrity.unchanged, true, 'source offers, dates and prices are not rewritten');
  assert.deepEqual(integrity.tiles.filter(tile => tile.best).map(tile => tile.date), ['2096-02-29']);
  const formatter = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', weekday: 'short', timeZone: 'UTC' });
  for (const tile of integrity.tiles) {
    assert.equal(tile.label, formatter.format(new Date(tile.date + 'T12:00:00Z')).replace(/\.$/, ''), 'label and submitted date identify the same day');
    assert.ok(tile.accessible.includes(tile.date.split('-').reverse().join('.')), 'accessible name includes the complete date and year');
    assert.ok(tile.accessible.includes(tile.label), 'visible day label stays in the accessible name');
  }
  const calendar = page.locator('#currentPriceCalendar');
  if ([375, 1024, 1440].includes(width)) await calendar.screenshot({ path: path.join(output, `calendar-valid-dates-${width}.png`) });
  const action = await page.evaluate(() => {
    const form = document.getElementById('tourSearch'), from = form.elements.dateFrom, to = form.elements.dateTo;
    const initial = [...new FormData(form)], before = [from.value, to.value], events = [];
    const lifecycle = window.V2SearchLifecycle, originalSubmit = lifecycle.submit;
    let submits = 0;
    const input = event => events.push(event.target.name);
    const button = document.createElement('button'); button.type = 'button';
    document.getElementById('currentPriceCalendar').append(button);
    lifecycle.submit = () => { submits++; }; form.addEventListener('input', input);
    try {
      for (const date of ['2096-02-30', '31.04.2096', '2096-13-01']) { button.dataset.calendarDate = date; button.click(); }
      const invalid = { unchanged: JSON.stringify([...new FormData(form)]) === JSON.stringify(initial), events: events.slice(), submits };
      document.querySelector('[data-calendar-date="2096-02-29"]').click();
      const selected = { unchanged: JSON.stringify([...new FormData(form)]) === JSON.stringify(initial), events: events.slice(), submits };
      document.querySelector('[data-calendar-apply]').click();
      const after = [...new FormData(form)];
      const valid = { dates: [from.value, to.value], events: events.slice(), submits, othersUnchanged: JSON.stringify(after.filter(([key]) => !['dateFrom', 'dateTo'].includes(key))) === JSON.stringify(initial.filter(([key]) => !['dateFrom', 'dateTo'].includes(key))) };
      const candidate = document.body.classList.contains('search3-candidate');
      let legacy;
      try {
        document.body.classList.remove('search3-candidate');
        window.V2CurrentPriceCalendar.render([{ tours: [{ date: '2096-03-01', price: 120000 }, { date: '2096-03-02', price: 125000 }] }]);
        const previousSubmits = submits;
        document.querySelector('[data-calendar-date="2096-03-02"]').click();
        legacy = { dates: [from.value, to.value], submits: submits - previousSubmits, confirmation: !!document.querySelector('[data-calendar-apply]') };
      } finally {
        if (candidate) document.body.classList.add('search3-candidate');
      }
      return { invalid, selected, valid, legacy };
    } finally {
      lifecycle.submit = originalSubmit; form.removeEventListener('input', input); button.remove();
      [from.value, to.value] = before;
    }
  });
  assert.deepEqual(action.invalid, { unchanged: true, events: [], submits: 0 }, 'invalid clicked dates cannot mutate the form or submit');
  assert.deepEqual(action.selected, { unchanged: true, events: [], submits: 0 }, 'selecting a valid date leaves search conditions untouched until confirmation');
  assert.deepEqual(action.valid, { dates: ['2096-02-29', '2096-02-29'], events: ['dateFrom', 'dateTo'], submits: 1, othersUnchanged: true }, 'valid leap day keeps the original single-submit and non-date field contract');
  assert.deepEqual(action.legacy, { dates: ['2096-03-02', '2096-03-02'], submits: 1, confirmation: false }, 'legacy calendar keeps its immediate single-submit behavior');
  return { cases: cases.length, ...integrity, action };
}
async function checkRerenderFocus(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
  const items = [{ tours: Array.from({ length: 21 }, (_, i) => ({ date: `2099-09-${String(i + 1).padStart(2, '0')}`, price: 140000 + i * 1000 })) }];
  const updated = [{ tours: items[0].tours.map(tour => ({ ...tour, price: tour.price + 500 })) }];
  const emit = (name, values) => page.evaluate(({ name, values }) => {
    window.dispatchEvent(new CustomEvent(name, { detail: { items: values } }));
  }, { name, values });
  const formData = () => page.evaluate(() => [...new FormData(document.getElementById('tourSearch'))]);
  const initial = await formData();
  await emit('v2:search-started', []);
  await emit('v2:search-complete', items);
  const last = calendar.locator('[data-calendar-date="2099-09-21"]');
  await page.keyboard.press('Tab');
  await last.focus();
  // Native focus can start the page's existing smooth scroll. Measure only after
  // that movement settles, on BOTH sides of the rerender; retain strict deltas.
  const position = () => calendar.evaluate(node => new Promise((resolve, reject) => {
    const strip = node.querySelector('.current-price-calendar__days');
    let prior = null, stable = 0, frames = 0;
    const sample = () => {
      const current = { scrollLeft: strip.scrollLeft, pageY: scrollY };
      stable = prior && current.scrollLeft === prior.scrollLeft && current.pageY === prior.pageY ? stable + 1 : 0;
      prior = current;
      if (stable >= 8) return resolve({ ...current, frames });
      if (++frames >= 180) return reject(new Error('Calendar scroll did not settle before measurement'));
      requestAnimationFrame(sample);
    };
    requestAnimationFrame(sample);
  }));
  const before = await position();
  await emit('v2:search-continued', updated);
  assert.equal(await last.evaluate(node => node === document.activeElement), true, 'continued prices retain keyboard focus on the same date, not body');
  assert.equal(await last.locator('strong').innerText(), new Intl.NumberFormat('ru-RU').format(160500) + ' ₽', 'focus restoration does not prevent updated prices rendering');
  const after = await position();
  fs.writeFileSync(path.join(output, `calendar-rerender-${width}-position.json`), JSON.stringify({ width, before, after }, null, 2) + '\n');
  assert.ok(Math.abs(after.scrollLeft - before.scrollLeft) <= 1, 'late-date mobile strip scroll survives replacement');
  assert.ok(Math.abs(after.pageY - before.pageY) <= 2, `same-date rerender does not jump the page: ${before.pageY} -> ${after.pageY}`);
  if ([375, 1440].includes(width)) await calendar.screenshot({ path: path.join(output, `calendar-rerender-${width}-date-focus.png`) });

  await calendar.locator('summary').focus();
  await calendar.locator('summary').press('Enter');
  assert.equal(await calendar.locator('details').evaluate(node => node.open), false);
  await emit('v2:search-continued', items);
  assert.equal(await calendar.locator('summary').evaluate(node => node === document.activeElement), true, 'closed disclosure retains focus when results update');
  assert.equal(await calendar.locator('details').evaluate(node => node.open), false, 'focus restoration does not reopen a user-closed calendar');
  if ([375, 1440].includes(width)) await calendar.screenshot({ path: path.join(output, `calendar-rerender-${width}-summary-focus.png`) });
  await calendar.locator('summary').press('Space');
  await last.focus();
  await emit('search3:local-results-filtered', [{ tours: items[0].tours.slice(0, 2) }]);
  assert.equal(await calendar.locator('summary').evaluate(node => node === document.activeElement), true, 'removed focused date falls back to the existing calendar heading');
  assert.equal(await calendar.locator('details').evaluate(node => node.open), true);

  const outside = page.locator('#tourSearch [name="dateFrom"]');
  await outside.focus();
  await emit('search3:local-results-filtered', items);
  assert.equal(await outside.evaluate(node => node === document.activeElement), true, 'background calendar updates never steal outside focus');
  for (const count of [0, 1]) {
    await last.focus();
    const expected = await page.evaluate(() => {
      const form = document.getElementById('tourSearch');
      const target = [document.getElementById('resultsSearchEdit'), form.elements.dateFrom].find(node => node && node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden');
      return target.id || target.name;
    });
    await emit('search3:local-results-filtered', [{ tours: items[0].tours.slice(0, count) }]);
    assert.equal(await calendar.evaluate(node => node.hidden), true);
    assert.equal(await page.evaluate(() => document.activeElement.id || document.activeElement.name), expected, 'hidden calendar returns focused keyboard users to an existing visible search control');
    await emit('search3:local-results-filtered', items);
    assert.equal(await page.evaluate(() => document.activeElement.id || document.activeElement.name), expected, 'restoring calendar dates does not reclaim focus');
  }
  assert.deepEqual(await formData(), initial, 'calendar focus/scroll recovery never rewrites search conditions');
  await emit('v2:search-reset', []);
  return { sameDate: true, updatedPrice: 160500, closedSummary: true, removedDateFallback: true, hiddenCounts: [0, 1], outsideFocusPreserved: true, formDataUnchanged: true, before, after };
}
async function checkDateSelection(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
  const items = [{ tours: [
    { date: '2099-09-01', price: 150000 },
    { date: '2099-09-02', price: 140000 },
    { date: '2099-09-03', price: 145000 },
  ] }];
  const emit = (name, values) => page.evaluate(({ name, values }) => {
    window.dispatchEvent(new CustomEvent(name, { detail: { items: values } }));
  }, { name, values });
  const snapshot = () => page.evaluate(() => ({
    form: [...new FormData(document.getElementById('tourSearch'))],
    url: location.href, results: document.getElementById('results').innerHTML,
    submits: window.__dateSelectionSubmits,
  }));
  await page.evaluate(() => {
    window.__dateSelectionSubmits = 0;
    window.__dateSelectionOriginal = window.V2SearchLifecycle.submit;
    window.V2SearchLifecycle.submit = () => window.__dateSelectionSubmits++;
  });
  try {
    await emit('v2:search-started', []);
    await emit('v2:search-complete', items);
    const before = await snapshot();
    const second = calendar.locator('[data-calendar-date="2099-09-02"]');
    const third = calendar.locator('[data-calendar-date="2099-09-03"]');
    const apply = calendar.locator('[data-calendar-apply]');
    assert.equal(await apply.isVisible(), false, 'no apply action before a date is chosen');
    await second.click();
    assert.equal(await second.getAttribute('aria-pressed'), 'true');
    assert.equal(await apply.isVisible(), true);
    assert.ok((await apply.innerText()).includes('2 сент'), 'confirm action names the selected departure date');
    await third.focus();
    await third.press('Space');
    assert.equal(await second.getAttribute('aria-pressed'), 'false');
    assert.equal(await third.getAttribute('aria-pressed'), 'true');
    assert.equal(await calendar.locator('[aria-pressed="true"]').count(), 1);
    assert.deepEqual(await snapshot(), before, 'comparing dates never changes FormData, URL, results or submits');
    const colors = await third.evaluate(node => ({ selected: getComputedStyle(node).backgroundColor, best: getComputedStyle(node.parentElement.querySelector('.is-best')).backgroundColor }));
    assert.notEqual(colors.selected, colors.best, 'selected date is visually distinct from cheapest date');
    await apply.focus();
    await emit('v2:search-continued', [{ tours: items[0].tours.map(tour => ({ ...tour, price: tour.price + 1000 })) }]);
    assert.equal(await third.getAttribute('aria-pressed'), 'true', 'available selection survives progressive refresh');
    assert.equal(await apply.evaluate(node => node === document.activeElement), true, 'refresh retains focus on the confirm button');
    assert.ok((await calendar.locator('[role="status"]').innerText()).includes(new Intl.NumberFormat('ru-RU').format(146000)), 'selected price refreshes with actual results');
    assert.deepEqual(await snapshot(), before);
    if ([375, 768, 1440].includes(width)) await calendar.screenshot({ path: path.join(output, `calendar-selected-${width}.png`) });

    await emit('search3:local-results-filtered', [{ tours: items[0].tours.slice(0, 2) }]);
    assert.equal(await apply.isVisible(), false, 'removed date cannot be confirmed');
    assert.equal(await calendar.locator('summary').evaluate(node => node === document.activeElement), true, 'removing a focused apply action restores the calendar heading');
    await emit('search3:local-results-filtered', items);
    assert.equal(await apply.isVisible(), false, 'a removed choice does not silently revive');
    await second.click();
    await calendar.locator('summary').click();
    await calendar.locator('summary').press('Enter');
    assert.equal(await second.getAttribute('aria-pressed'), 'true', 'disclosure preserves an explicit choice');
    await apply.press('Enter');
    const after = await snapshot();
    assert.equal(after.submits, 1, 'one confirmation makes exactly one canonical submit');
    assert.equal(new Map(after.form).get('dateFrom'), '2099-09-02');
    assert.equal(new Map(after.form).get('dateTo'), '2099-09-02');
    assert.deepEqual(after.form.filter(([key]) => !['dateFrom', 'dateTo'].includes(key)), before.form.filter(([key]) => !['dateFrom', 'dateTo'].includes(key)), 'party, nights, hotel and every non-date condition survive');
    assert.equal(after.url, before.url, 'calendar does not own URL mutation');
    assert.equal(after.results, before.results, 'calendar does not own result clearing');
    await page.evaluate(() => {
      const button = document.querySelector('[data-calendar-apply]');
      if (button) button.click();
    });
    assert.equal((await snapshot()).submits, 1, 'rapid repeat confirmation cannot submit twice');

    await emit('v2:search-started', []);
    await emit('v2:search-complete', items);
    assert.equal(await apply.isVisible(), false, 'a new search invalidates the previous date choice');
    await second.click();
    await emit('search3:local-results-filtered', [{ tours: items[0].tours.slice(0, 1) }]);
    await emit('search3:local-results-filtered', items);
    assert.equal(await apply.isVisible(), false, 'one-date hiding clears the stale choice');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
    return { selectWithoutSearch: true, keyboard: true, updatedPrice: 146000, removedDateCleared: true, nonDateConditionsPreserved: true, singleSubmit: true, newSearchCleared: true };
  } finally {
    await page.evaluate(() => {
      window.V2SearchLifecycle.submit = window.__dateSelectionOriginal;
      delete window.__dateSelectionOriginal;
      delete window.__dateSelectionSubmits;
    });
    await emit('v2:search-reset', []);
  }
}
async function checkNavigation(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
  const tours = Array.from({ length: 21 }, (_, i) => ({ date: `2099-09-${String(i + 1).padStart(2, '0')}`, price: i === 20 ? 118900 : 140000 + i * 1000 }));
  const emit = (name, values) => page.evaluate(({ name, values }) => {
    window.dispatchEvent(new CustomEvent(name, { detail: { items: [{ tours: values }] } }));
  }, { name, values });
  await page.evaluate(() => {
    window.__calendarNavigation = { submit: window.V2SearchLifecycle.submit, api: window.V2Runtime.api, submits: 0, calls: 0 };
    window.V2SearchLifecycle.submit = () => { window.__calendarNavigation.submits++; };
    window.V2Runtime.api = () => { window.__calendarNavigation.calls++; throw new Error('Calendar navigation must not request supplier data'); };
  });
  const snapshot = () => page.evaluate(() => ({ form: [...new FormData(document.getElementById('tourSearch'))], url: location.href, results: document.getElementById('results').innerHTML, submits: window.__calendarNavigation.submits, calls: window.__calendarNavigation.calls }));
  const nav = calendar.locator('.current-price-calendar__navigation');
  const previous = nav.locator('[data-calendar-move="previous"]');
  const next = nav.locator('[data-calendar-move="next"]');
  const best = nav.locator('[data-calendar-best]');
  const scroll = () => calendar.locator('.current-price-calendar__days').evaluate(node => node.scrollLeft);
  const visibleDate = async date => {
    await page.waitForFunction(date => {
      const tile = document.querySelector(`[data-calendar-date="${date}"]`), strip = tile.parentElement;
      const a = tile.getBoundingClientRect(), b = strip.getBoundingClientRect();
      return a.left - 5 >= b.left - 1 && a.right + 5 <= b.right + 1;
    }, date);
  };
  try {
    await emit('v2:search-started', []);
    await emit('v2:search-complete', tours);
    const initial = await snapshot();
    if (width > 640) {
      assert.equal(await nav.isVisible(), false, 'desktop grid does not display redundant strip navigation');
      assert.equal(await calendar.locator('[data-calendar-date]').count(), 21);
      assert.deepEqual(await snapshot(), initial);
      return { desktopGrid: true, navigationHidden: true, dates: 21, submits: 0, calls: 0 };
    }
    await best.waitFor({ state: 'visible' });
    const targets = await nav.locator('button').evaluateAll(nodes => nodes.map(node => ({ width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height })));
    assert.ok(targets.every(rect => rect.width >= 44 && rect.height >= 44), 'all mobile navigation controls have full touch targets');
    assert.equal(await previous.getAttribute('aria-disabled'), 'true');
    assert.equal(await next.getAttribute('aria-disabled'), 'false');
    assert.match(await best.innerText(), /21 сент/);
    assert.ok((await best.getAttribute('aria-label')).includes(new Intl.NumberFormat('ru-RU').format(118900)), 'shortcut announces an actual found minimum');
    await next.click();
    await page.waitForFunction(() => document.querySelector('.current-price-calendar__days').scrollLeft > 10);
    const forward = await scroll();
    assert.equal(await next.evaluate(node => node === document.activeElement), true, 'paging retains button focus');
    await previous.click();
    await page.waitForFunction(() => document.querySelector('.current-price-calendar__days').scrollLeft <= 1);
    await calendar.screenshot({ path: path.join(output, `calendar-navigation-${width}-start.png`) });
    await best.click();
    await visibleDate('2099-09-21');
    assert.equal(await calendar.locator('[data-calendar-date="2099-09-21"]').evaluate(node => node === document.activeElement), true, 'minimum shortcut reveals and focuses the late date');
    assert.equal(await calendar.locator('[aria-pressed="true"]').count(), 0, 'navigation does not select a date');
    assert.equal(await calendar.locator('[data-calendar-apply]').isVisible(), false);
    assert.equal(await next.getAttribute('aria-disabled'), 'true');
    const end = await scroll();
    await next.focus();
    await next.press('Enter');
    assert.equal(await scroll(), end, 'end-of-strip navigation is inert');
    assert.equal(await next.evaluate(node => node === document.activeElement), true, 'an unavailable boundary keeps keyboard focus');
    assert.deepEqual(await snapshot(), initial, 'paging and minimum reveal leave form, URL, results and supplier calls untouched');
    await best.click();
    await calendar.locator('[data-calendar-date="2099-09-21"]').press('Enter');
    assert.equal(await calendar.locator('[data-calendar-date="2099-09-21"]').getAttribute('aria-pressed'), 'true');
    assert.equal(await calendar.locator('[data-calendar-apply]').isVisible(), true, 'selection still requires the existing separate confirmation');
    await calendar.screenshot({ path: path.join(output, `calendar-navigation-${width}-minimum.png`) });
    await previous.focus();
    const updated = tours.map((tour, i) => ({ ...tour, price: i === 4 ? 110000 : tour.price }));
    await emit('v2:search-continued', updated);
    assert.equal(await previous.evaluate(node => node === document.activeElement), true, 'progressive rerender retains navigation focus');
    assert.equal(await calendar.locator('[data-calendar-date="2099-09-21"]').getAttribute('aria-pressed'), 'true', 'a new lower price does not replace the user choice');
    assert.match(await best.innerText(), /5 сент/);
    await best.click();
    await visibleDate('2099-09-05');
    assert.equal(await calendar.locator('[data-calendar-date="2099-09-21"]').getAttribute('aria-pressed'), 'true', 'revealing another minimum keeps the selected date');
    await emit('search3:local-results-filtered', tours.slice(0, 3));
    assert.equal(await calendar.locator('[data-calendar-apply]').isVisible(), false, 'filtered-out date is no longer actionable');
    assert.match(await best.innerText(), /1 сент/);
    assert.equal(await calendar.locator('summary').evaluate(node => node === document.activeElement), true, 'removing the focused minimum restores the calendar heading');

    await emit('v2:search-started', []);
    await emit('v2:search-complete', tours.slice(0, 2));
    assert.equal(await nav.isVisible(), false, 'two fully visible mobile dates need no strip navigation');
    await emit('v2:search-started', []);
    await emit('v2:search-complete', tours.map(tour => ({ ...tour, price: 120000 })));
    assert.match(await best.innerText(), /1 сент/, 'equal minima use the earliest date deterministically');
    await calendar.locator('summary').click();
    await calendar.locator('summary').click();
    await best.waitFor({ state: 'visible' });
    const viewport = page.viewportSize();
    await next.focus();
    await page.setViewportSize({ width: 1024, height: viewport.height });
    await page.waitForFunction(() => document.querySelector('.current-price-calendar__navigation').hidden);
    assert.equal(await calendar.locator('summary').evaluate(node => node === document.activeElement), true, 'hiding navigation on desktop restores visible focus');
    await page.setViewportSize({ width: 320, height: 500 });
    await best.waitFor({ state: 'visible' });
    await emit('v2:search-continued', tours);
    await best.click();
    await visibleDate('2099-09-21');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'navigation fits a short 320px viewport');
    assert.equal(await best.evaluate(node => node.getBoundingClientRect().height), 44, 'minimum shortcut keeps the date on one line at 320px');
    assert.ok(await next.evaluate(node => parseFloat(getComputedStyle(node).fontSize) >= 24), 'direction icons remain readable at the narrowest viewport');
    if (width === 375) await calendar.screenshot({ path: path.join(output, 'calendar-navigation-320-minimum.png') });
    await page.setViewportSize(viewport);
    await best.waitFor({ state: 'visible' });
    const final = await snapshot();
    assert.deepEqual(final.form, initial.form);
    assert.equal(final.url, initial.url);
    assert.equal(final.submits, 0);
    assert.equal(final.calls, 0);
    return { targets, forward, minimumScroll: end, minimumDate: '2099-09-21', selectionPreserved: true, filteredMinimumRecomputed: true, rerenderFocus: true, resize: [1024, 320, width], submits: 0, calls: 0 };
  } finally {
    await page.evaluate(() => {
      window.V2SearchLifecycle.submit = window.__calendarNavigation.submit;
      window.V2Runtime.api = window.__calendarNavigation.api;
      delete window.__calendarNavigation;
    });
    await emit('v2:search-reset', []);
  }
}
module.exports = async function calendarReadability(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
  const disclosure = await checkDisclosureState(page, width, output);
  const rerenderFocus = await checkRerenderFocus(page, width, output);
  await page.mouse.move(0, 0);
  const records = [];
  const tours = Array.from({ length: 21 }, (_, index) => ({
    date: `2099-09-${String(index + 1).padStart(2, '0')}`,
    price: index === 1 ? 118900 : 132500 + index * 1000,
  }));
  const render = async count => {
    await page.evaluate(items => window.V2CurrentPriceCalendar.render(items), [{ tours: tours.slice(0, count) }]);
    await calendar.locator('details').evaluate(node => { node.open = true; });
  };
  for (const count of [2, 3, 6, 7, 8, 14, 21]) {
    await render(count);
    const geometry = await calendar.evaluate(node => {
      const box = item => { const r = item.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height }; };
      const days = node.querySelector('.current-price-calendar__days');
      const summary = node.querySelector('summary');
      return {
        heading: box(summary), headingBorder: getComputedStyle(summary).borderTopWidth,
        eyebrowDisplay: getComputedStyle(node.querySelector('.current-price-calendar__heading>span')).display,
        list: box(days), listScrollWidth: days.scrollWidth, listClientWidth: days.clientWidth,
        pageOverflow: document.documentElement.scrollWidth > innerWidth + 2,
        tiles: [...days.children].map(item => ({ ...box(item), date: item.dataset.calendarDate,
          price: item.querySelector('strong').textContent, background: getComputedStyle(item).backgroundColor,
          border: getComputedStyle(item).borderTopColor, priceSize: parseFloat(getComputedStyle(item.querySelector('strong')).fontSize),
          best: item.classList.contains('is-best') })),
      };
    });
    assert.equal(geometry.pageOverflow, false, 'calendar never widens the document');
    assert.ok(geometry.heading.height >= 44, 'calendar disclosure retains a full touch target');
    if (width <= 640) {
      assert.ok(geometry.heading.height <= 70, 'mobile calendar heading leaves room for results');
      assert.equal(geometry.headingBorder, '0px', 'mobile calendar avoids a nested frame');
      assert.equal(geometry.eyebrowDisplay, 'none', 'mobile keeps one calendar heading');
    } else {
      assert.notEqual(geometry.eyebrowDisplay, 'none', 'desktop calendar heading is unchanged');
    }
    assert.equal(geometry.tiles.length, count, 'every existing date stays available');
    assert.deepEqual(geometry.tiles.map(item => item.date), tours.slice(0, count).map(item => item.date));
    assert.deepEqual(geometry.tiles.map(item => item.price), tours.slice(0, count).map(item => new Intl.NumberFormat('ru-RU').format(item.price) + ' ₽'));
    const best = geometry.tiles.filter(item => item.best);
    assert.equal(best.length, 1, 'the existing minimum remains the only best day');
    assert.equal(best[0].date, '2099-09-02');
    assert.notEqual(best[0].background, geometry.tiles[0].background, 'best price is visible despite generic button styles');
    assert.notEqual(best[0].border, geometry.tiles[0].border, 'best-price border remains distinct');
    assert.ok(geometry.tiles.every(item => item.height >= 44), 'native day actions retain full targets');
    if (width >= 1180) {
      assert.ok(geometry.tiles.every(item => item.priceSize >= 18), 'desktop amounts remain readable');
      const firstRow = geometry.tiles.filter(item => Math.abs(item.top - geometry.tiles[0].top) < 1);
      assert.equal(firstRow.length, Math.min(count, 7), 'wide rows use available dates up to seven columns');
      if (count <= 7) {
        assert.equal(new Set(geometry.tiles.map(item => Math.round(item.top))).size, 1, 'up to a week fits in one wide desktop row');
        assert.ok(Math.abs(geometry.tiles.at(-1).right - geometry.list.right) < 2, 'available dates fill the row without empty columns');
      } else {
        assert.equal(new Set(geometry.tiles.map(item => Math.round(item.top))).size, Math.ceil(count / 7), 'full and partial weeks retain seven-column desktop rows');
      }
    }
    if (width > 640) assert.ok(geometry.listScrollWidth <= geometry.listClientWidth + 1, 'desktop calendar needs no horizontal scroll');
    records.push({ count, ...geometry });
    if ([375, 1024, 1199, 1440].includes(width) && [2, 3, 7, 21].includes(count)) await calendar.screenshot({ path: path.join(output, `calendar-readable-${width}-${count}.png`) });
  }
  const last = calendar.locator('[data-calendar-date]').last();
  await page.keyboard.press('Tab');
  await last.focus();
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
  const focus = await last.evaluate(node => {
    const item = node.getBoundingClientRect(), list = node.parentElement.getBoundingClientRect(), style = getComputedStyle(node);
    return { active: node === document.activeElement, width: parseFloat(style.outlineWidth), style: style.outlineStyle,
      left: item.left, right: item.right, top: item.top, bottom: item.bottom,
      listLeft: list.left, listRight: list.right, listTop: list.top, listBottom: list.bottom, scrollLeft: node.parentElement.scrollLeft };
  });
  assert.equal(focus.active, true);
  assert.ok(focus.width >= 3 && focus.style !== 'none', 'keyboard focus is separate from best-price styling');
  if (width <= 640) {
    assert.ok(focus.scrollLeft > 0, 'native focus reaches late dates within mobile scrolling');
    assert.ok(focus.left - 5 >= focus.listLeft - 1 && focus.right + 5 <= focus.listRight + 1 && focus.top - 5 >= focus.listTop - 1 && focus.bottom + 5 <= focus.listBottom + 1, 'focused late date and its whole outline remain inside the scrollport');
    await calendar.screenshot({ path: path.join(output, `calendar-readable-${width}-focus.png`) });
  }
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false);
  const dateIntegrity = await checkDateIntegrity(page, width, output);
  const selection = await checkDateSelection(page, width, output);
  const navigation = await checkNavigation(page, width, output);
  fs.writeFileSync(path.join(output, `calendar-readable-${width}.json`), JSON.stringify({ width, records, focus, dateIntegrity, disclosure, rerenderFocus, selection, navigation, fixture: true, supplier_requests: 0, leads: 0 }, null, 2) + '\n');
  await page.evaluate(() => window.V2CurrentPriceCalendar.clear());
};
