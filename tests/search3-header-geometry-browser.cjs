'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const searchNames = JSON.parse(execFileSync('php', ['-r',
  'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("css", "search3"));'
], { cwd: root, encoding: 'utf8' }));
const searchScripts = JSON.parse(execFileSync('php', ['-r',
  'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("js", "search3"));'
], { cwd: root, encoding: 'utf8' }));
assert.deepEqual(searchNames, ['design-system-v2.css', 'site-header-v2.css', 'site-footer-v1.css'],
  'Search3 shared shell CSS closure drifted');
assert.equal(searchNames.filter(name => name === 'site-header-v2.css').length, 1,
  'canonical header CSS must load exactly once');
assert.ok(!searchNames.includes('header-current-site.css'), 'legacy header CSS leaked into Search3');
assert.ok(!searchScripts.includes('header-current-site.js'), 'legacy header runtime leaked into Search3');
const searchPresentation = fs.readFileSync(path.join(root, 'v2', 'search3-results-filters-v1.css'), 'utf8');
assert.ok(!searchPresentation.includes('.at-global-header'),
  'Search3 presentation must not restore a private header owner');
const css = searchNames.map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8')).join('\n')
  + '\n' + searchPresentation;
const logo = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='40'%3E%3Crect width='180' height='40' fill='%232743cb'/%3E%3C/svg%3E";
const labels = ['Поиск туров', 'Страны', 'Горящие туры', 'Раннее бронирование', 'Как купить', 'Контакты'];
const nav = labels.map(label => `<a href="#">${label}</a>`).join('');
const html = `<!doctype html><meta charset="utf-8"><style>*,*:before,*:after{box-sizing:border-box}html,body{margin:0}</style><body class="search3-candidate"><header class="at-global-header"><div class="at-global-header__inner"><a class="at-global-header__logo"><img src="${logo}" alt="AnyTour"></a><nav class="at-global-header__nav">${nav}</nav><div class="at-global-header__actions"><a class="at-global-header__phone">8 (800) 100-61-50</a><a class="at-global-header__cta">Найти тур</a></div><details class="at-global-header__mobile"><summary aria-label="Открыть меню"><span></span><span></span><span></span></summary><div class="at-global-header__mobile-panel"><a class="at-global-header__mobile-phone">8 (800) 100-61-50</a>${nav}</div></details></div></header></body>`;

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined });
  const output = process.env.SEARCH3_RESULTS_OUTPUT;
  if (output) fs.mkdirSync(output, { recursive: true });
  let states = 0;
  try {
    for (const width of [375, 520, 521, 768, 769, 999, 1000, 1024, 1025, 1100, 1101, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      try {
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.evaluate(() => document.fonts && document.fonts.ready);
        const before = await page.evaluate(() => {
          const box = selector => document.querySelector(selector).getBoundingClientRect();
          const visible = selector => {
            const node = document.querySelector(selector);
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
          };
          return {
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            headers: document.querySelectorAll('.at-global-header').length,
            legacy: document.querySelectorAll('.at-site-header,.at-mobile-menu').length,
            header: box('.at-global-header__inner'),
            logo: box('.at-global-header__logo'),
            navTargets: Array.from(document.querySelectorAll('.at-global-header__nav a'), node => node.getBoundingClientRect().height),
            phone: box('.at-global-header__phone'),
            nav: visible('.at-global-header__nav'),
            actions: visible('.at-global-header__actions'),
            mobile: visible('.at-global-header__mobile'),
            panel: visible('.at-global-header__mobile-panel'),
            menuButton: box('.at-global-header__mobile summary'),
            ctaMinHeight: getComputedStyle(document.querySelector('.at-global-header__cta')).minHeight,
          };
        });
        const compact = width <= 768;
        const tablet = width <= 1024;
        assert.ok(before.overflow <= 1, `${width}: header overflow ${before.overflow}`);
        assert.equal(before.headers, 1, `${width}: current header count`);
        assert.equal(before.legacy, 0, `${width}: legacy header markup leaked`);
        assert.ok(before.header.width > 0 && before.header.height > 0 && before.logo.width > 0, `${width}: header geometry missing`);
        assert.ok(before.logo.height >= 44, `${width}: logo target collapsed`);
        assert.ok(Math.abs(before.header.width - (tablet ? width : Math.min(1180, width - 40))) <= 1,
          `${width}: canonical shared header width drifted (${before.header.width})`);
        assert.equal(before.nav, !tablet, `${width}: canonical navigation breakpoint drifted`);
        assert.equal(before.actions, !compact, `${width}: canonical header actions breakpoint drifted`);
        if (!tablet) assert.ok(before.navTargets.every(height => height >= 44), `${width}: desktop navigation target collapsed`);
        if (!compact) assert.ok(before.phone.height >= 44, `${width}: support phone target collapsed`);
        assert.equal(before.mobile, tablet, `${width}: canonical mobile-menu breakpoint drifted`);
        assert.equal(before.panel, false, `${width}: closed native menu panel leaked`);
        assert.ok(parseFloat(before.ctaMinHeight) >= 44, `${width}: canonical header CTA target collapsed`);
        if (tablet) {
          assert.ok(before.menuButton.height >= 44, `${width}: native menu target collapsed`);
          await page.locator('.at-global-header__mobile > summary').click();
          const open = await page.evaluate(() => {
            const node = document.querySelector('.at-global-header__mobile-panel');
            const rect = node.getBoundingClientRect();
            return {
              visible: getComputedStyle(node).display !== 'none' && rect.width > 0 && rect.height > 0,
              left: rect.left,
              right: rect.right,
              viewport: document.documentElement.clientWidth,
            };
          });
          assert.equal(open.visible, true, `${width}: open native menu panel missing`);
          assert.ok(open.left >= -1 && open.right <= open.viewport + 1, `${width}: native menu panel escaped viewport`);
          await page.locator('.at-global-header__mobile > summary').click();
          assert.equal(await page.locator('.at-global-header__mobile').evaluate(node => node.open), false,
            `${width}: native menu did not close without header runtime`);
        }
        if (output) await page.screenshot({ path: path.join(output, `header-${width}.png`), fullPage: true, animations: 'disabled' });
        states += 1;
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
  console.log(`SEARCH3_HEADER_GEOMETRY_OK states=${states}`);
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
