/* Called from the existing served-entry test; inherits its offline request guard. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
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
  for (const count of [2, 3, 7, 21]) {
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
      if (count <= 7) {
        assert.equal(new Set(geometry.tiles.map(item => Math.round(item.top))).size, 1, 'up to a week fits in one wide desktop row');
        assert.ok(Math.abs(geometry.tiles.at(-1).right - geometry.list.right) < 2, 'available dates fill the row without empty columns');
      } else {
        assert.equal(new Set(geometry.tiles.map(item => Math.round(item.top))).size, 3, 'three weeks use three balanced desktop rows');
      }
    }
    if (width > 640) assert.ok(geometry.listScrollWidth <= geometry.listClientWidth + 1, 'desktop calendar needs no horizontal scroll');
    records.push({ count, ...geometry });
    if (width === 1440 && [3, 7, 21].includes(count)) await calendar.screenshot({ path: path.join(output, `calendar-readable-${width}-${count}.png`) });
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
  fs.writeFileSync(path.join(output, `calendar-readable-${width}.json`), JSON.stringify({ width, records, focus, fixture: true, supplier_requests: 0, leads: 0 }, null, 2) + '\n');
  await page.evaluate(() => window.V2CurrentPriceCalendar.clear());
};
