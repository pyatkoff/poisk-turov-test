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

const field = (label, control, name, preference = false) => `<label class="field${preference ? ' search-preference' : ''} search3-${name}"><span>${label}</span>${control}</label>`;
const html = `<!doctype html><meta charset="utf-8"><style>*,*:before,*:after{box-sizing:border-box}html,body{margin:0}.v2-shell{width:100%;max-width:1120px;margin:auto;padding:12px}.v2-visually-hidden{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}</style>
<body class="search3-candidate"><main class="v2-shell"><section class="v2-product-hero v2-visually-hidden" aria-labelledby="v2-search-title"><h1 id="v2-search-title">Поиск туров</h1><p>Выберите направление и даты — сравните подходящие предложения.</p></section><form id="tourSearch" class="search-card">
  <div class="search-section-title"><span>Параметры поездки</span></div>
  <div class="main-fields">
    <fieldset class="search-group search-group--route"><legend>Направление</legend>
      ${field('Вылет из', '<select><option>Калининград</option></select>', 'from')}
      ${field('Страна', '<select><option>Турция</option></select>', 'country')}
    </fieldset>
    <fieldset class="search-group search-group--dates"><legend>Даты вылета</legend>
      ${field('Вылет с', '<input type="date" value="2026-09-12">', 'date-from')}
      ${field('Вылет до', '<input type="date" value="2026-09-19">', 'date-to')}
    </fieldset>
    <fieldset class="search-group search-group--nights"><legend>Продолжительность</legend>
      ${field('Ночей от', '<select><option>7</option></select>', 'nights-from')}
      ${field('Ночей до', '<select><option>10</option></select>', 'nights-to')}
    </fieldset>
    <fieldset class="search-group search-group--party"><legend>Туристы</legend>
      ${field('Взрослых', '<select><option>2</option></select>', 'adults')}
      ${field('Детей', '<select><option>Без детей</option></select>', 'children')}
    </fieldset>
    <div class="search-section-title search-section-title--preferences"><span>Отель и условия</span></div>
    ${field('Курорт / регион', '<select><option>Анталья</option></select>', 'region', true)}
    ${field('Район / субкурорт', '<select><option>Все районы</option></select>', 'subregion', true)}
    ${field('Конкретный отель', '<select><option>Любой отель</option></select>', 'hotel', true)}
    ${field('Категория отеля', '<select><option>4★ и выше</option></select>', 'stars', true)}
    ${field('Питание', '<select><option>Всё включено</option></select>', 'food', true)}
    ${field('Рейтинг отеля', '<select><option>от 4.0</option></select>', 'rating', true)}
    ${field('Цена от', '<input type="number" value="80000">', 'price-from', true)}
    ${field('Цена до', '<input type="number" value="180000">', 'price-to', true)}
  </div>
  <details class="extras"><summary>Ещё фильтры <span>аэропорт, туроператор, тип отеля, перелёт и услуги</span></summary></details>
  <button class="primary search-submit" type="submit"><span>Найти туры</span></button>
</form></main></body>`;

const measure = node => {
  const box = node.getBoundingClientRect();
  const style = getComputedStyle(node);
  return { top: box.top, bottom: box.bottom, width: box.width, height: box.height, fontSize: parseFloat(style.fontSize), display: style.display, position: style.position };
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
    for (const width of [375, 760, 761, 1024, 1025, 1199, 1200, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1300 } });
      try {
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const state = await page.evaluate(measureSource => {
          const measureNode = eval(`(${measureSource})`);
          return {
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            hero: measureNode(document.querySelector('.v2-product-hero')),
            form: measureNode(document.querySelector('#tourSearch')),
            mainColumns: getComputedStyle(document.querySelector('.main-fields')).gridTemplateColumns.split(' ').length,
            groupColumns: [...document.querySelectorAll('.search-group')].map(node => getComputedStyle(node).gridTemplateColumns.split(' ').length),
            groupTops: [...document.querySelectorAll('.search-group')].map(node => Math.round(node.getBoundingClientRect().top)),
            groupLegends: [...document.querySelectorAll('.search-group legend')].map(node => node.textContent.trim()),
            preferenceTops: [...document.querySelectorAll('.search-preference')].map(node => Math.round(node.getBoundingClientRect().top)),
            preferenceLabels: [...document.querySelectorAll('.search-preference>span')].map(node => node.textContent.trim()),
            labels: [...document.querySelectorAll('#tourSearch .field>span')].map(measureNode),
            controls: [...document.querySelectorAll('#tourSearch .field :is(input,select)')].map(measureNode),
            dateControls: [...document.querySelectorAll('.search-group--dates input')].map(measureNode),
            submit: measureNode(document.querySelector('.search-submit')),
            extras: measureNode(document.querySelector('.extras')),
          };
        }, measure.toString());
        assert.ok(state.overflow <= 1, `${width}: form must not overflow horizontally`);
        assert.equal(state.hero.position, 'absolute', `${width}: semantic Search3 hero stays out of visual flow`);
        assert.ok(state.hero.width <= 1.1 && state.hero.height <= 1.1, `${width}: redundant hero consumes no first-view space`);
        assert.ok(state.form.top <= 13, `${width}: search form starts at the shell top without a hero gap`);
        assert.ok(state.labels.every(item => item.fontSize >= 12), `${width}: labels remain readable`);
        assert.ok(state.controls.every(item => item.height >= 43.5 && item.fontSize >= 16), `${width}: native controls keep 44px/16px`);
        assert.ok(state.submit.height >= 43.5 && state.submit.fontSize >= 13, `${width}: submit remains actionable and readable`);
        assert.deepEqual(state.groupLegends, ['Направление', 'Даты вылета', 'Продолжительность', 'Туристы'], `${width}: trip basics keep the existing four canonical groups`);
        assert.deepEqual(state.preferenceLabels, ['Курорт / регион', 'Район / субкурорт', 'Конкретный отель', 'Категория отеля', 'Питание', 'Рейтинг отеля', 'Цена от', 'Цена до'], `${width}: hotel preference fields stay visible in canonical order`);
        if (width === 375) {
          assert.equal(state.mainColumns, 1, '375: full search uses one readable outer column');
          assert.deepEqual(state.groupColumns, [1, 2, 2, 2], '375: route stacks while coupled trip pairs stay compact');
          assert.equal(new Set(state.preferenceTops).size, 8, '375: preference controls stack without cramped pairs');
          assert.ok(state.submit.width >= state.form.width - 45, '375: primary action spans the mobile form');
        }
        if (width > 700) assert.ok(Math.abs(state.submit.top - state.extras.top) <= 1, `${width}: extra parameters and search share the footer row`);
        if (width >= 1200) {
          assert.equal(state.mainColumns, 4, 'wide desktop: canonical trip grid has four columns');
          assert.equal(new Set(state.groupTops).size, 1, 'wide desktop: trip basics stay in one row');
          const preferenceRows = new Map();
          for (const top of state.preferenceTops) preferenceRows.set(top, (preferenceRows.get(top) || 0) + 1);
          assert.deepEqual([...preferenceRows.values()].sort((a,b)=>a-b), [4, 4], 'wide desktop: hotel preferences use two balanced rows of four full-width controls');
          assert.ok(state.dateControls.every(item => item.width >= 125), 'wide desktop: date fields keep enough width for the complete native value');
          assert.ok(state.submit.width <= 281, 'wide desktop: primary action does not consume the entire form width');
        }
        if (output) await page.screenshot({ path: path.join(output, `entry-${width}.png`), fullPage: true, animations: 'disabled' });
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
