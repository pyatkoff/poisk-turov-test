const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const { execFileSync } = require('node:child_process');
const legacyNames = JSON.parse(execFileSync('php', ['-r',
  'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("css", "search3"));'
], { cwd: root, encoding: 'utf8' }));
const searchNames = [
  'search3-results-filters-v1.css',
  'search3-entry-v1.css',
  'search3-results-cards-v2.css',
  'search3-selected-flow-v2.css',
];
const css = legacyNames.concat(searchNames)
  .map(name => fs.readFileSync(path.join(root, 'v2', name), 'utf8'))
  .join('\n');

const field = (label, control, name) => `<label class="field search3-${name}"><span>${label}</span>${control}</label>`;
const html = `<!doctype html><meta charset="utf-8"><style>*,*:before,*:after{box-sizing:border-box}html,body{margin:0}.v2-shell{width:100%;max-width:1120px;margin:auto;padding:12px}.main-fields{display:grid;gap:10px}</style>
<body class="search3-candidate"><main class="v2-shell"><form id="tourSearch" class="search-card search3-mobile-advanced-open">
  <div class="main-fields search3-primary-grid">
    ${field('Город вылета', '<select><option>Калининград</option></select>', 'from')}
    ${field('Страна', '<select><option>Турция</option></select>', 'country')}
    ${field('Курорт / регион', '<select><option>Любой</option></select>', 'region')}
    ${field('Туристы', '<div class="search3-composite__control"><select class="search3-direct-control" aria-label="Взрослых"><option>2</option></select><select class="search3-direct-control" aria-label="Детей"><option>1</option></select></div>', 'tourists')}
    ${field('Даты вылета', '<div class="search3-composite__control"><input class="search3-direct-control" type="date" value="2026-09-12"><span class="search3-composite__dash">—</span><input class="search3-direct-control" type="date" value="2026-09-15"></div>', 'dates')}
    ${field('Ночей', '<div class="search3-composite__control"><select class="search3-direct-control"><option>7</option></select><span class="search3-composite__dash">—</span><select class="search3-direct-control"><option>10</option></select></div>', 'nights')}
    <button class="search-submit" type="submit"><b>Найти туры</b></button>
  </div>
  <section class="search3-quality"><div class="search3-quality__grid">${field('Категория отеля', '<select><option>Любая</option></select>', 'stars')}</div></section>
  <div class="search3-quick"><label class="search3-quick__label"><input type="checkbox"><span>Прямой рейс</span></label></div>
</form></main></body>`;

const measure = node => {
  const box = node.getBoundingClientRect();
  const style = getComputedStyle(node);
  return { width: box.width, height: box.height, fontSize: parseFloat(style.fontSize), display: style.display };
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
    for (const width of [375, 760, 761]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      try {
        await page.setContent(html);
        await page.addStyleTag({ content: css });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const state = await page.evaluate(measureSource => {
          const measureNode = eval(`(${measureSource})`);
          return {
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            labels: [...document.querySelectorAll('#tourSearch .field>span')].map(measureNode),
            controls: [...document.querySelectorAll('#tourSearch .search3-primary-grid>.field>select:not(.search3-direct-control)')].map(measureNode),
            directControls: [...document.querySelectorAll('#tourSearch .search3-direct-control')].map(measureNode),
            composites: [...document.querySelectorAll('#tourSearch .search3-composite__control')].map(measureNode),
            submit: measureNode(document.querySelector('.search-submit')),
            quick: measureNode(document.querySelector('.search3-quick__label')),
          };
        }, measure.toString());
        assert.ok(state.overflow <= 1, `${width}: form must not overflow horizontally`);
        if (width <= 760) {
          assert.ok(state.labels.every(item => item.fontSize >= 12), `${width}: labels remain readable`);
          assert.ok(state.controls.every(item => item.height > 0 && item.fontSize >= 13), `${width}: native primary controls remain visible and readable`);
          assert.ok(state.directControls.every(item => item.height >= 43.5 && item.fontSize >= 16), `${width}: native date, night and tourist controls keep 44px/16px`);
          assert.ok(state.composites.every(item => item.height >= 43.5), `${width}: native composite controls keep a 44px target`);
          assert.ok(state.submit.height >= 43.5 && state.submit.fontSize >= 13, `${width}: submit remains actionable and readable`);
          assert.ok(state.quick.height > 0 && state.quick.fontSize >= 13, `${width}: native quick filter remains visible and readable`);
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
