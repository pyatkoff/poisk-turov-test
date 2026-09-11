/* Called from the existing served-entry test; inherits its offline request guard. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
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
      const after = [...new FormData(form)];
      return { invalid, valid: { dates: [from.value, to.value], events: events.slice(), submits, othersUnchanged: JSON.stringify(after.filter(([key]) => !['dateFrom', 'dateTo'].includes(key))) === JSON.stringify(initial.filter(([key]) => !['dateFrom', 'dateTo'].includes(key))) } };
    } finally {
      lifecycle.submit = originalSubmit; form.removeEventListener('input', input); button.remove();
      [from.value, to.value] = before;
    }
  });
  assert.deepEqual(action.invalid, { unchanged: true, events: [], submits: 0 }, 'invalid clicked dates cannot mutate the form or submit');
  assert.deepEqual(action.valid, { dates: ['2096-02-29', '2096-02-29'], events: ['dateFrom', 'dateTo'], submits: 1, othersUnchanged: true }, 'valid leap day keeps the original single-submit and non-date field contract');
  return { cases: cases.length, ...integrity, action };
}
module.exports = async function calendarReadability(page, width, output) {
  const calendar = page.locator('#currentPriceCalendar');
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
      return {
        list: box(days), listScrollWidth: days.scrollWidth, listClientWidth: days.clientWidth,
        pageOverflow: document.documentElement.scrollWidth > innerWidth + 2,
        tiles: [...days.children].map(item => ({ ...box(item), date: item.dataset.calendarDate,
          price: item.querySelector('strong').textContent, background: getComputedStyle(item).backgroundColor,
          border: getComputedStyle(item).borderTopColor, priceSize: parseFloat(getComputedStyle(item.querySelector('strong')).fontSize),
          best: item.classList.contains('is-best') })),
      };
    });
    assert.equal(geometry.pageOverflow, false, 'calendar never widens the document');
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
  fs.writeFileSync(path.join(output, `calendar-readable-${width}.json`), JSON.stringify({ width, records, focus, dateIntegrity, fixture: true, supplier_requests: 0, leads: 0 }, null, 2) + '\n');
  await page.evaluate(() => window.V2CurrentPriceCalendar.clear());
};
