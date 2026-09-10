const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const { execFileSync } = require('node:child_process');
let legacyNames;
try {
  legacyNames = JSON.parse(execFileSync('php', ['-r',
    'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("css", "search3"));'
  ], { cwd: root, encoding: 'utf8' }));
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
  legacyNames = ['design-system-v2.css', 'site-header-v2.css', 'site-footer-v1.css', 'current-price-calendar-v1.css'];
}
const searchNames = [
  'search3-results-filters-v1.css',
  'search3-entry-v1.css',
  'search3-results-cards-v2.css',
  'search3-selected-flow-v2.css',
];
const css = legacyNames.concat(searchNames)
  .map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8'))
  .join('\n');
// Render the current shared owner, not a second hard-coded navigation fixture.
const header = execFileSync('php', ['-r',
  'require "v2/site-header-v2.php"; v2_render_site_header("8 (800) 100-61-50", "88001006150", "/poisk-turov/");'
], { cwd: root, encoding: 'utf8' });

const field = (label, control, name) => `<label class="field search3-${name}"><span>${label}</span>${control}</label>`;
const html = `<!doctype html><meta charset="utf-8"><style>*,*:before,*:after{box-sizing:border-box}html,body{margin:0}.v2-shell{width:100%;max-width:1120px;margin:auto;padding:12px}</style>
<body class="search3-candidate">${header}<main class="v2-shell"><form id="tourSearch" class="search-card">
  <div class="search-section-title"><span>Параметры поездки</span></div>
  <div class="main-fields">
    <fieldset class="search-group search-group--route"><legend>Направление</legend>
      ${field('Вылет из', '<select><option>Калининград</option></select>', 'from')}
      ${field('Страна', '<select><option>Турция</option></select>', 'country')}
    </fieldset>
    <fieldset class="search-group search-group--dates"><legend>Даты вылета</legend>
      ${field('С', '<input type="date" value="2026-09-12">', 'date-from')}
      ${field('По', '<input type="date" value="2026-09-19">', 'date-to')}
    </fieldset>
    <fieldset class="search-group search-group--nights"><legend>Продолжительность</legend>
      ${field('Ночей от', '<select><option>7</option></select>', 'nights-from')}
      ${field('Ночей до', '<select><option>10</option></select>', 'nights-to')}
    </fieldset>
    <fieldset class="search-group search-group--party"><legend>Туристы</legend>
      ${field('Взрослых', '<select><option>2</option></select>', 'adults')}
      ${field('Детей', '<select><option>Без детей</option></select>', 'children')}
    </fieldset>
  </div>
  <details class="extras"><summary>Фильтры отдыха <span>курорт, отель, питание и перелёт</span></summary></details>
  <button class="primary search-submit" type="submit"><span>Найти туры</span></button>
</form></main></body>`;

const measure = node => {
  const box = node.getBoundingClientRect();
  const style = getComputedStyle(node);
  return { top: box.top, bottom: box.bottom, width: box.width, height: box.height, fontSize: parseFloat(style.fontSize), display: style.display };
};

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined,
  });
  const output = process.env.SEARCH3_RESULTS_OUTPUT;
  if (output) fs.mkdirSync(output, { recursive: true });
  let states = 0;
  try {
    for (const width of [375, 760, 761, 1024, 1025, 1100, 1101, 1199, 1200, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      try {
        await page.route('**/*', route => route.abort());
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const headerState = await page.evaluate(() => {
          const box = selector => {
            const r = document.querySelector(selector).getBoundingClientRect();
            return { left: r.left, right: r.right, top: r.top, height: r.height, width: r.width };
          };
          return {
            inner: box('.at-global-header__inner'), actions: box('.at-global-header__actions'),
            nav: box('.at-global-header__nav'), menu: box('.at-global-header__mobile > summary'),
            links: [...document.querySelectorAll('.at-global-header__nav a')].map(node => {
              const r = node.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, height: r.height };
            }),
          };
        });
        assert.equal(headerState.links.length, 6, 'shared header retains all navigation destinations');
        if (width > 1024) {
          assert.ok(headerState.links.every(link => link.height >= 44), `${width}: desktop navigation targets stay >=44px`);
          assert.ok(headerState.links.every(link => link.right <= headerState.actions.left - 8), `${width}: navigation cannot overlap phone/actions`);
          assert.ok(headerState.actions.right <= headerState.inner.right + 1, `${width}: actions remain inside the shell`);
          assert.equal(new Set(headerState.links.map(link => Math.round(link.top))).size, 1, `${width}: ordinary desktop navigation stays in one row`);
          assert.equal(headerState.menu.width, 0, `${width}: desktop does not introduce a second visible menu`);
        } else {
          assert.equal(headerState.nav.width, 0, `${width}: native mobile menu replaces the desktop links`);
          assert.ok(headerState.menu.width >= 44 && headerState.menu.height >= 44, `${width}: native menu target stays >=44px`);
          const summary = page.locator('.at-global-header__mobile > summary');
          await summary.focus();
          assert.ok(parseFloat(await summary.evaluate(node => getComputedStyle(node).outlineWidth)) >= 3, 'keyboard focus remains visible');
          await page.keyboard.press('Space');
          assert.equal(await page.locator('.at-global-header__mobile').evaluate(node => node.open), true, 'native menu opens with the keyboard');
          const panel = await page.locator('.at-global-header__mobile-panel').boundingBox();
          assert.ok(panel.x >= 0 && panel.x + panel.width <= width + 1, 'open menu stays inside the viewport');
          for (const link of await page.locator('.at-global-header__mobile-panel > a').all()) assert.ok((await link.boundingBox()).height >= 44, 'menu links keep full touch targets');
          if (output) await page.screenshot({ path: path.join(output, `header-menu-${width}.png`), fullPage: true, animations: 'disabled' });
          await page.keyboard.press('Space');
          assert.equal(await page.locator('.at-global-header__mobile').evaluate(node => node.open), false, 'same native menu closes without a handler');
        }
        const state = await page.evaluate(measureSource => {
          const measureNode = eval(`(${measureSource})`);
          return {
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            form: measureNode(document.querySelector('#tourSearch')),
            mainColumns: getComputedStyle(document.querySelector('.main-fields')).gridTemplateColumns.split(' ').length,
            groupColumns: [...document.querySelectorAll('.search-group')].map(node => getComputedStyle(node).gridTemplateColumns.split(' ').length),
            groupTops: [...document.querySelectorAll('.search-group')].map(node => Math.round(node.getBoundingClientRect().top)),
            labels: [...document.querySelectorAll('#tourSearch .field>span')].map(measureNode),
            controls: [...document.querySelectorAll('#tourSearch .field :is(input,select)')].map(measureNode),
            dateControls: [...document.querySelectorAll('.search-group--dates input')].map(measureNode),
            submit: measureNode(document.querySelector('.search-submit')),
            extras: measureNode(document.querySelector('.extras')),
          };
        }, measure.toString());
        assert.ok(state.overflow <= 1, `${width}: form must not overflow horizontally`);
        assert.ok(state.labels.every(item => item.fontSize >= 12), `${width}: labels remain readable`);
        assert.ok(state.controls.every(item => item.height >= 43.5 && item.fontSize >= 16), `${width}: native controls keep 44px/16px`);
        assert.ok(state.submit.height >= 43.5 && state.submit.fontSize >= 13, `${width}: submit remains actionable and readable`);
        if (width === 375) {
          assert.equal(state.mainColumns, 1, '375: primary groups use one readable column');
          assert.deepEqual(state.groupColumns, [1, 2, 2, 2], '375: route stacks while coupled date, night and tourist values stay paired');
          assert.ok(state.submit.width >= state.form.width - 45, '375: primary action spans the mobile form');
        }
        if (width > 700) assert.ok(Math.abs(state.submit.top - state.extras.top) <= 1, `${width}: extra parameters and search share the footer row`);
        if (width >= 1200) {
          assert.equal(state.mainColumns, 4, '1440: four primary groups share one compact row');
          assert.equal(new Set(state.groupTops).size, 1, '1440: all primary groups align in one row');
          assert.ok(state.dateControls.every(item => item.width >= 125), '1440: date fields keep enough width for the complete native value');
          assert.ok(state.submit.width <= 281, '1440: primary action does not consume the entire form width');
        }
        if (output) {
          fs.writeFileSync(path.join(output, `header-${width}.json`), JSON.stringify(headerState, null, 2) + '\n');
          await page.screenshot({ path: path.join(output, `entry-${width}.png`), fullPage: true, animations: 'disabled' });
        }
        states += 1;
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }
  console.log(`SEARCH3_ENTRY_GEOMETRY_OK states=${states}`);
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
