'use strict';
// Geometry/readiness on the controller-admitted native LIVE site. The one pinned
// SOURCE transport supplies catalog fixtures only; all supplier/lead actions are
// prohibited. Full submit/receiving-generation coverage remains in SOURCE CI.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const { loadFixture } = require('./home-search-loading-handoff.cjs');
const native = loadFixture();
const { fixture, trip } = require(path.join(native.root, 'transport.cjs'));
const output = path.resolve(process.env.HOME_NATIVE_OUTPUT || 'standalone-content-artifacts/native-home');
fs.mkdirSync(output, { recursive: true });
const routes = new Set(['/', '/index.php', '/poisk-turov/', '/poisk-turov/index.php']);
const types = { '.js': 'application/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png',
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2', '.woff': 'font/woff', '.ico': 'image/x-icon' };
const catalogPaths = new Set(['/data/departures-v1.php', '/data/hotel-search-v1.php', '/api-v2.php',
  '/_preview/search3-local-candidate/data/search3-destination-read-v1.php',
  '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php',
  '/_preview/search3-local-candidate/data/hotel-details-read-v1.php',
  '/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php']);
const serverFailures = [];
const php = `
  $_SERVER['SCRIPT_NAME'] = $argv[2]; $_SERVER['HTTP_HOST'] = 'anytoour.ru';
  $_SERVER['DOCUMENT_ROOT'] = $argv[3];
  $_SERVER['REQUEST_URI'] = $argv[2] . ($argv[4] !== '' ? '?' . $argv[4] : '');
  parse_str($argv[4], $_GET);
  ob_start();
  register_shutdown_function(function () {
    echo json_encode(['status' => http_response_code() ?: 200, 'body' => ob_get_clean(),
      'error' => error_get_last()], JSON_THROW_ON_ERROR);
  });
  require $argv[1];
`;
const server = http.createServer((request, response) => {
  try {
    assert.equal(request.method, 'GET', 'the fixture server executes no data/API/lead request');
    const url = new URL(request.url, 'http://fixture');
    if (routes.has(url.pathname)) {
      const owner = url.pathname === '/' || url.pathname === '/index.php' ? 'index.php' : 'poisk-turov/index.php';
      const data = JSON.parse(execFileSync('php', ['-n', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
        '-d', 'display_errors=stderr', '-r', php, path.join(native.site, owner), url.pathname, native.site, url.search.slice(1)],
        { encoding: 'utf8', timeout: 30000, maxBuffer: 8 * 1024 * 1024 }));
      assert.equal(data.status, 200, 'actual guarded PHP route must be available'); assert.equal(data.error, null);
      response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }).end(data.body); return;
    }
    const target = path.resolve(native.site, '.' + decodeURIComponent(url.pathname));
    assert(target.startsWith(native.site + path.sep), 'static path outside admitted site');
    const name = path.relative(native.site, target).split(path.sep).join('/');
    assert(types[path.extname(target)] && native.receipt.site_files[name], 'unlisted executable/static dependency: ' + url.pathname);
    response.writeHead(200, { 'Content-Type': types[path.extname(target)] }).end(fs.readFileSync(target));
  } catch (error) {
    serverFailures.push(error.message); response.writeHead(500).end('Native fixture failure');
  }
});
const rgb = value => (value.match(/\d+/g) || []).slice(0, 3).map(Number);
const luminance = values => values.map(value => { value /= 255; return value <= .03928 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4; })
  .reduce((total, value, index) => total + value * [.2126, .7152, .0722][index], 0);
async function checkCta(page) {
  const button = page.locator('#search-form .search-submit');
  assert(await button.isVisible() && await button.isEnabled(), 'ready visible canonical CTA');
  const colors = await button.evaluate(element => { const style = getComputedStyle(element); return [style.color, style.backgroundColor]; });
  const [foreground, background] = colors.map(value => luminance(rgb(value)));
  assert((Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05) >= 4.5, 'CTA text contrast >= 4.5');
  await button.focus();
  assert(await button.evaluate(element => parseFloat(getComputedStyle(element).outlineWidth)) >= 2, 'visible keyboard focus');
  await button.blur();
}
async function geometry(page, home) {
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => window.scrollTo(0, 0));
  return page.evaluate(home => {
    const box = element => { const r = element.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, bottom: r.bottom }; };
    const visible = element => !!element && element.checkVisibility() && element.getClientRects().length > 0
      && getComputedStyle(element).visibility === 'visible';
    const controls = ['[data-action="departure"]', '[data-action="destination"]', '[data-action="dates"]',
      '[data-action="nights"]', '[data-action="guests"]', '#quick-stars', '#quick-meal', '.search-submit'];
    const rows = Object.fromEntries(controls.map(selector => {
      const nodes = [...document.querySelectorAll('#search-form ' + selector)], element = nodes[0], r = element && box(element);
      const hit = r && document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
      return [selector, { count: nodes.length, visible: visible(element), box: r, hit: !!element && (hit === element || element.contains(hit)) }];
    }));
    const header = document.querySelector('.at-global-header__inner'), logo = document.querySelector('.at-global-header__logo');
    const nav = [...document.querySelectorAll('.at-global-header__nav>a,.at-global-header__nav>button')].map(element => ({
      label: element.textContent.trim(), path: element.tagName === 'A' ? new URL(element.href).pathname :
        element.dataset.action === 'top' ? '/poisk-turov/' : null, current: element.getAttribute('aria-current') }));
    const budget = document.querySelector('#quick-budget'), details = budget.closest('details.home-search-more');
    const footer = document.querySelector('footer.ds2-site-footer');
    const footerInner = footer && footer.querySelector('.ds2-site-footer__inner');
    const footerActions = footer ? [...footer.querySelectorAll('.ds2-site-footer__socials a,.ds2-site-footer__apps>a')] : [];
    return { width: innerWidth, height: innerHeight, docWidth: document.documentElement.scrollWidth,
      headers: document.querySelectorAll('.at-global-header').length, footers: document.querySelectorAll('footer').length,
      oldHeaders: document.querySelectorAll('.at-site-header').length,
      header: header && box(header), logo: logo && box(logo), nav,
      logosLoaded: [...document.querySelectorAll('.at-global-header img,footer img')].every(image => image.complete && image.naturalWidth > 0),
      forms: document.querySelectorAll('form').length, heroForms: document.querySelectorAll('.home-hero #search-form').length,
      cards: document.querySelectorAll('#cards').length, controls: rows,
      budgetClosed: !!details && !details.open, budgetHidden: !visible(budget),
      moreVisible: !!details && visible(details.querySelector('summary')),
      footerStyled: !!footer && getComputedStyle(footer).backgroundColor !== 'rgba(0, 0, 0, 0)'
        && !!footerInner && getComputedStyle(footerInner).display === 'grid',
      footerTargets: footerActions.length === 5 && footerActions.every(element => element.getBoundingClientRect().height >= 43.5),
      modernFooter: !!document.querySelector('footer.footer .footer-brand'), home };
  }, home);
}
function checkGeometry(result, label, reference) {
  assert(result.docWidth <= result.width + 2, label + ': no horizontal overflow');
  assert.equal(result.headers, 1); assert.equal(result.oldHeaders, 0); assert.equal(result.footers, 1);
  assert.equal(result.forms, 1); assert.equal(result.cards, 1);
  assert(result.header?.height > 0 && result.logo?.width > 0 && result.logosLoaded, label + ': actual header geometry/artwork');
  assert.deepEqual(result.nav.map(row => row.path), ['/poisk-turov/', '/country/', '/hot/', '/rb/', '/how-to-buy/', '/contacts/']);
  for (const [selector, control] of Object.entries(result.controls)) {
    assert.equal(control.count, 1, label + ': one ' + selector);
    assert(control.visible && control.box.width > 0 && control.box.height > 0, label + ': visible control ' + selector);
  }
  const submit = result.controls['.search-submit'];
  assert(submit.box.height >= 44 && submit.hit, label + ': unobscured 44px CTA');
  if (result.home) {
    assert.equal(result.heroForms, 1);
    assert(result.budgetClosed && result.budgetHidden && result.moreVisible, label + ': budget inside closed additional parameters');
    assert(submit.box.y >= 0 && submit.box.bottom <= result.height + 1, label + ': CTA fits in the first viewport');
    assert(result.footerStyled && result.footerTargets, label + ': factual HOME footer remains styled with five touch targets');
  } else {
    assert(result.modernFooter, label + ': actual modern Search3 footer');
    assert(result.nav[0].current === 'page', label + ': Search3 current navigation');
    assert(Math.abs(result.header.height - reference.header.height) <= 1 && Math.abs(result.logo.width - reference.logo.width) <= 1,
      label + ': shared header geometry');
    assert.deepEqual(result.nav.map(({ label, path }) => ({ label, path })), reference.nav.map(({ label, path }) => ({ label, path })));
  }
}
(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const origin = 'http://127.0.0.1:' + server.address().port;
  const report = { scope: 'Pinned native LIVE artifact; ready form/geometry; fictional catalogs; no submit/supplier/lead operation',
    source: native.receipt.source_sha, artifact: native.receipt.home_artifact_id,
    parentArtifact: native.receipt.parent_artifact_id, supplierHTTP: 0, leadHTTP: 0, productionOperations: 0,
    installedIconAliases: 'Not in the native artifact; exact favicon.ico/png requests are blocked and not certified here', cases: [] };
  const save = () => fs.writeFileSync(path.join(output, 'native-home-content.json'), JSON.stringify(report, null, 2) + '\n');
  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    for (const viewport of [{ width: 375, height: 812 }, { width: 390, height: 740 }, { width: 430, height: 932 },
      { width: 1280, height: 720 }, { width: 1440, height: 1000 }]) {
      let reference;
      for (const entry of ['home', 'search']) {
        const transport = fixture(), errors = [], forbidden = [], blockedIcons = [];
        const context = await browser.newContext({ viewport, serviceWorkers: 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(20000);
        page.on('pageerror', error => errors.push(error.message));
        const row = { entry, viewport, errors, forbidden, blockedIcons, calls: transport.calls, status: 'running' };
        report.cases.push(row); save();
        try {
          await context.route('**/*', async route => {
            const request = route.request(), url = new URL(request.url());
            try {
              assert.equal(url.origin, origin, 'external HTTP forbidden');
              if (['/favicon.ico', '/favicon.png'].includes(url.pathname) && !native.receipt.site_files[url.pathname.slice(1)]) {
                assert.equal(request.method(), 'GET'); blockedIcons.push(url.pathname); await route.abort(); return;
              }
              if (request.method() === 'GET' && (routes.has(url.pathname) || types[path.extname(url.pathname)])) {
                await route.continue(); return;
              }
              assert(catalogPaths.has(url.pathname), 'supplier/lead/unlisted route forbidden: ' + url.pathname);
              const body = request.postData() ? JSON.parse(request.postData()) : {};
              const action = url.searchParams.get('action') || body.action;
              if (url.pathname === '/api-v2.php') assert.equal(action, 'meals', 'all public supplier actions are forbidden');
              assert(!/^(?:tv_|handoff_|search_start|search_status|search_results|search_continue)/.test(action || ''), 'supplier/lead action forbidden');
              const value = await transport.json(url.href, { body: request.postData() });
              await route.fulfill({ contentType: 'application/json', body: JSON.stringify(value) });
            } catch (error) { forbidden.push(error.message); await route.abort(); }
          });
          const query = new URLSearchParams({ ...trip, adults: '3', ages: '0,12', yclid: 'ci-home-only', utm_source: 'native-home-ci' });
          if (entry === 'home') query.set('searched', '1');
          const response = await page.goto(origin + (entry === 'home' ? '/' : '/poisk-turov/') + '?' + query,
            { waitUntil: 'domcontentloaded', timeout: 45000 });
          assert.equal(response?.status(), 200, 'canonical PHP document status');
          await page.waitForFunction(() => window.AnyTourPublicConfigV1?.valid === true
            && document.querySelector('#search-form')?.getAttribute('aria-busy') !== 'true'
            && document.querySelector('#search-form .search-submit')?.disabled === false,
          null, { timeout: 20000 });
          assert.equal(await page.evaluate(() => window.V2_CONFIG?.search3FormOnly === true), entry === 'home');
          assert.match(await page.locator('#guests-label').textContent(), /3 взр.*2 реб/);
          assert.equal(await page.locator('#guests-detail').textContent(), 'До 1 года и 12 лет');
          assert(!new URL(page.url()).searchParams.has('searched'), 'idle HOME/receiver must not retain an applied-search flag');
          assert.equal(new URL(page.url()).searchParams.get('yclid'), 'ci-home-only');
          await page.locator('#search-form [data-action="guests"]').click();
          await page.locator('#modal[open]').waitFor();
          const ages = await page.locator('#modal [data-action="child-age"]>span:first-of-type').allTextContents();
          assert.deepEqual(ages, ['До 1 года', '12 лет'], 'actual shared picker hydrates both ages');
          assert(await page.locator('[data-action="apply-guests"]').isEnabled());
          await page.locator('#modal [data-action="close-modal"]').click();
          await page.waitForFunction(() => !document.querySelector('#modal').open);
          row.geometry = await geometry(page, entry === 'home');
          checkGeometry(row.geometry, entry + '/' + viewport.width, reference);
          if (entry === 'home') reference = row.geometry;
          await checkCta(page);
          await page.screenshot({ path: path.join(output, `${entry}-${viewport.width}x${viewport.height}.png`), fullPage: true, animations: 'disabled' });
          assert.deepEqual(errors, [], 'no JavaScript errors'); assert.deepEqual(forbidden, [], 'all HTTP stayed inside the catalog-only fixture');
          row.status = 'passed'; save();
        } catch (error) {
          row.status = 'failed'; row.error = error.stack || error.message; save();
          await page.screenshot({ path: path.join(output, `failure-${entry}-${viewport.width}.png`), fullPage: true }).catch(() => {});
          throw error;
        } finally { await context.close(); }
      }
    }
    assert.deepEqual(serverFailures, []); loadFixture();
    report.status = 'passed'; save(); console.log('HOME_NATIVE_CONTENT_OK', JSON.stringify({ source: report.source, artifact: report.artifact, cases: report.cases.length }));
  } catch (error) { report.status = 'failed'; report.error = error.stack || error.message; report.serverFailures = serverFailures; save(); throw error; }
  finally { if (browser) await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
