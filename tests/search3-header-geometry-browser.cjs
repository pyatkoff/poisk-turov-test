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
assert.ok(searchNames.includes('site-header-v2.css'), 'current header CSS missing');
assert.ok(!searchNames.includes('header-current-site.css'), 'legacy header CSS leaked into Search3');
const css = searchNames.map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8')).join('\n');
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
    for (const width of [375, 520, 521, 768, 769, 1024, 1025, 1100, 1101, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      try {
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.evaluate(() => document.fonts && document.fonts.ready);
        if (width <= 1024) await page.locator('.at-global-header__mobile').evaluate(node => { node.open = true; });
        const state = await page.evaluate(() => {
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
            nav: visible('.at-global-header__nav'),
            actions: visible('.at-global-header__actions'),
            mobile: visible('.at-global-header__mobile'),
            panel: visible('.at-global-header__mobile-panel'),
            menuButton: box('.at-global-header__mobile summary'),
            panelTargets: [...document.querySelectorAll('.at-global-header__mobile-panel a')]
              .map(node => node.getBoundingClientRect().height)
          };
        });
        assert.ok(state.overflow <= 1, `${width}: header overflow ${state.overflow}`);
        assert.equal(state.headers, 1, `${width}: current header count`);
        assert.equal(state.legacy, 0, `${width}: legacy header markup leaked`);
        assert.ok(state.header.width > 0 && state.header.height > 0 && state.logo.width > 0, `${width}: header geometry missing`);
        if (width <= 1024) {
          assert.equal(state.nav, false, `${width}: desktop nav visible`);
          assert.equal(state.actions, width > 768, `${width}: phone action breakpoint drifted`);
          assert.equal(state.mobile, true, `${width}: mobile menu missing`);
          assert.equal(state.panel, true, `${width}: open mobile panel missing`);
          assert.ok(state.menuButton.height >= 39.5, `${width}: mobile menu target collapsed`);
          assert.ok(state.panelTargets.every(height => height >= 43.5), `${width}: mobile panel target below 44px`);
        } else {
          assert.equal(state.nav, true, `${width}: desktop nav missing`);
          assert.equal(state.actions, true, `${width}: desktop actions missing`);
          assert.equal(state.mobile, false, `${width}: mobile menu visible`);
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
