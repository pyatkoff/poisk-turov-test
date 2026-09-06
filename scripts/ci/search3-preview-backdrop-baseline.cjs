'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
module.exports = async function compareBackdrop(browser) {
  const base = 'https://anytoour.ru/_preview/search3-site-candidate/';
  const names = new Set(['search3-entry-v1.js','search3-results-filters-v1.css','search3-results-filters-v1.js','search3-selected-flow-v2.js']);
  const rows = [];
  for (const version of ['previous-39d37643','published-5832457c']) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await ctx.route('**/*', route => {
      const req = route.request(), url = new URL(req.url()), name = path.posix.basename(url.pathname);
      // Frozen-state reproduction only: no supplier search, analytics, or lead call.
      if (req.method() !== 'GET' || /\/(?:api[^/]*|lead[^/]*|preview-lead-disabled)\.php/.test(url.pathname) || /mc\.yandex|metrika|web-consultant\/widget/.test(url.href)) return route.abort();
      if (version.startsWith('previous') && url.href.startsWith(base) && names.has(name)) {
        return route.fulfill({ status: 200, contentType: name.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(path.join(process.env.BASELINE_DIR, name)) });
      }
      return route.continue();
    });
    const page = await ctx.newPage();
    try {
      await page.goto(base + 'poisk-turov/', { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.search3Ready === '1');
      // Reproduce the observed state without inventing results: the legacy details
      // owner is retained but hidden by Search3, and its open flag is still true.
      await page.evaluate(() => {
        const details = document.querySelector('#tourSearch details.extras');
        if (!details || !details.hidden) throw new Error('LEGACY_OWNER_NOT_HIDDEN');
        details.open = true;
      });
      await page.setViewportSize({ width: 375, height: 1000 });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const state = await page.evaluate(() => {
        const details = document.querySelector('#tourSearch details.extras'), backdrop = document.querySelector('.search-filters-backdrop');
        return { hiddenOwner: details.hidden, openOwner: details.open, backdropVisible: !backdrop.hidden && getComputedStyle(backdrop).display !== 'none', zIndex: getComputedStyle(backdrop).zIndex };
      });
      assert.equal(state.hiddenOwner, true);
      assert.equal(state.backdropVisible, true, version + ' must reproduce the observed legacy backdrop');
      await page.screenshot({ path: path.join(process.env.EVIDENCE_DIR, version + '-legacy-backdrop.png'), animations: 'disabled' });
      await page.keyboard.press('Escape');
      await page.locator('.search-filters-backdrop').waitFor({ state: 'hidden' });
      rows.push({ version, ...state, nativeEscapeRecovers: true });
    } finally { await ctx.close(); }
  }
  assert.deepEqual({ ...rows[0], version: '' }, { ...rows[1], version: '' });
  return { scope: 'Controlled hidden-legacy-details/open-state reproduction with supplier network blocked, not a second live search', sameBeforeAndAfter: true, rows };
};
