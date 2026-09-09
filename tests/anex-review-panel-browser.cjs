'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

(async () => {
  const root = process.env.ANEX_REVIEW_TEST_ROOT;
  assert(root, 'isolated test root required');
  const base = 'http://127.0.0.1:8765';
  const route = '/_preview/search3-anex-candidate/anex-hotel-review.php';
  const browser = await chromium.launch({ headless: true });
  const proofs = [];
  try {
    const anonymous = await browser.newContext();
    const denied = await anonymous.request.get(base + route);
    assert.equal(denied.status(), 403);
    assert(!(await denied.text()).includes('Hotel One'));
    assert.equal((await anonymous.request.post(base + route, { form: { action: 'accept', id: '5', target: '101' } })).status(), 403);
    assert.equal((await anonymous.request.get(base + '/anex-hotel-review.php')).status(), 404);
    await anonymous.close();
    const context = await browser.newContext();
    const session = JSON.parse(fs.readFileSync(path.join(root, 'browser-session.json'), 'utf8')).session;
    await context.addCookies([{ name: 'ANEX_REVIEW_TEST', value: session, domain: '127.0.0.1', path: '/', httpOnly: true, sameSite: 'Strict' }]);
    let external = 0;
    await context.route('**/*', async request => {
      if (!request.request().url().startsWith(base + '/')) { external++; await request.abort(); }
      else await request.continue();
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    let response = await page.goto(base + route + '?id=5&status=all');
    assert.equal(response.status(), 200);
    assert.equal(response.headers()['x-robots-tag'], 'noindex, nofollow');
    assert(response.headers()['cache-control'].includes('no-store'));
    assert(response.headers()['content-security-policy'].includes("frame-ancestors 'none'"));
    for (const width of [1280, 820, 390, 320]) {
      await page.setViewportSize({ width, height: 1000 });
      const metrics = await page.evaluate(() => ({
        viewport: innerWidth,
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        compareColumns: getComputedStyle(document.querySelector('.compare')).gridTemplateColumns,
        disabled: document.querySelectorAll('button:disabled').length,
        rows: document.querySelectorAll('.list li').length
      }));
      assert.equal(metrics.overflow, false, JSON.stringify(metrics));
      assert.equal(metrics.rows, 25);
      await page.screenshot({ path: path.join(root, `panel-${width}.png`), fullPage: true });
      proofs.push({ width, ...metrics });
    }
    const readForm = async (action, target) => page.locator('form').filter({ has: page.locator(`input[name="action"][value="${action}"]`) })
      .filter({ has: page.locator(`input[name="target"][value="${target}"]`) }).first()
      .evaluate(form => Object.fromEntries(new FormData(form).entries()));
    const reject = await readForm('reject_pair', '101');
    assert.equal((await context.request.post(base + route, { form: { ...reject, csrf: 'invalid' }, maxRedirects: 0 })).status(), 403);
    const rejected = await context.request.post(base + route, { form: reject, maxRedirects: 0 });
    assert.equal(rejected.status(), 303);
    await page.goto(base + route + '?id=5');
    assert(await page.getByText('Эта пара отклонена.', { exact: false }).isVisible());
    assert(await page.getByText('Соответствие не принято', { exact: false }).isVisible());
    const alternative = await readForm('accept', '102');
    assert.equal((await context.request.post(base + route, { form: alternative, maxRedirects: 0 })).status(), 400, 'checkbox required');
    const accepted = await context.request.post(base + route, { form: { ...alternative, confirm: 'yes' }, maxRedirects: 0 });
    assert.equal(accepted.status(), 303);
    assert.equal((await context.request.post(base + route, { form: { ...alternative, confirm: 'yes' }, maxRedirects: 0 })).status(), 303, 'same request replays');
    const stale = { ...reject, request_id: 'f'.repeat(32) };
    assert.equal((await context.request.post(base + route, { form: stale, maxRedirects: 0 })).status(), 409);
    await page.goto(base + route + '?id=5');
    assert(await page.getByText('Принятое соответствие: AnyTour 102', { exact: false }).isVisible());
    assert(await page.getByText('owner:http-test', { exact: false }).first().isVisible());
    // A URL cannot forge a successful save banner.
    await page.goto(base + route + '?id=6&saved=1');
    assert.equal(await page.getByText('Решение сохранено.', { exact: false }).count(), 0);
    const later = await readForm('later', '');
    assert.equal((await context.request.post(base + route, { form: later, maxRedirects: 0 })).status(), 303);
    await page.goto(base + route + '?status=later&q=Hotel%206');
    assert.equal(await page.locator('.list li').count(), 1);
    assert(await page.locator('.list li').getByText('Нужна проверка', { exact: false }).isVisible());
    await page.goto(base + route + '?id=30');
    assert(await page.getByText('Нет сохранённых кандидатов.', { exact: false }).isVisible());
    await page.goto(base + route + '?q=NO_SUCH_HOTEL');
    assert(await page.getByText('По этим условиям отелей нет.', { exact: false }).isVisible());
    assert.equal(external, 0, 'no supplier/assets/analytics requests');
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(root, 'browser-report.json'), JSON.stringify({ synthetic: true, live_auth: false, supplierCalls: 0, widths: proofs, httpDecisions: ['reject_pair','accept_alternative','replay','stale','later'], passed: true }, null, 2));
    console.log('ANEX_REVIEW_BROWSER_OK widths=1280,820,390,320 overflow=false auth_csrf_replay_stale=passed supplier_calls=0 synthetic_identity=true');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
