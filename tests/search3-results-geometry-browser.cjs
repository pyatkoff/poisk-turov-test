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

const card = `
<article class="hotel-card" data-hotel-id="fixture">
  <div class="hotel-main">
    <div class="hotel-photo"><img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='675'%3E%3Crect width='1200' height='675' fill='%239ac7df'/%3E%3C/svg%3E"></div>
    <div class="hotel-body">
      <div class="search3-hotel-heading"><h3 class="hotel-title">LONG BEACH RESORT HOTEL WITH A LONG NAME</h3><span class="search3-hotel-category">5★</span></div>
      <p class="hotel-place">Турция · Анталья · Сиде</p>
      <div class="hotel-decision-line"><span>★ 4,7/5</span><span>До моря 350 м</span></div>
      <div class="hotel-bottom"><div class="hotel-best-offer"><small>За весь тур</small><strong class="hotel-price">от 148 500 ₽</strong><small class="hotel-price-context"><span>2 взрослых</span></small></div></div>
      <div class="search3-hotel-facts"><span><small>Вылет</small><b>12 сент. 2026</b></span><span><small>Ночей</small><b>9</b></span><span><small>Питание</small><b>Всё включено</b></span><span><small>Рейс</small><b>Чартер</b></span></div>
      <div class="search3-hotel-action"><button class="search3-show-tours" type="button" data-search3-show-label="Показать 16 туров">Показать 16 туров</button></div>
      <div class="hotel-actions"></div><div class="hotel-inline-detail"></div><button class="hotel-compare-toggle"></button><div class="result-decision-badges"></div>
    </div>
  </div>
  <div class="hotel-tours" hidden>
    <div class="hotel-choice-hint">Выберите тур</div>
    <article class="tour-row">
      <div class="tour-meta"><strong>12 сент. 2026</strong><div class="tour-facts"><span class="tour-fact"><small>Ночей</small><b>9</b></span><span class="tour-fact"><small>Питание</small><b>Всё включено</b></span><span class="tour-fact"><small>Рейс</small><b>Чартер</b></span></div></div>
      <div class="tour-action"><small>За весь тур</small><b>148 500 ₽</b><button class="direct-tour">Выбрать</button></div>
    </article>
  </div>
</article>`;

const drawer = `<div class="mrf-sheet"><div class="mrf-backdrop"></div><section class="mrf-panel"><div class="mrf-grab"></div><div class="mrf-head"><h3>Фильтры</h3><button class="mrf-close" type="button">×</button></div><div class="mrf-section"><strong>Категория отеля</strong><div class="mrf-options"><button class="mrf-choice is-active" type="button">5★</button></div></div><div class="mrf-section"><strong>Цена за тур, до</strong><div class="mrf-price"><input type="number"><span>₽</span></div></div><div class="mrf-actions"><button class="mrf-reset" type="button">Сбросить</button><button class="mrf-apply" type="button">Показать</button></div></section></div>`;
const html = `<!doctype html><meta charset="utf-8"><style>*,*:before,*:after{box-sizing:border-box}html,body{margin:0}.v2-shell{display:block!important;width:100%!important;max-width:none!important;padding:0!important}</style><body class="search3-candidate search3-results-active search3-has-results"><main class="v2-shell"><section id="resultsSearchSummary">Параметры поиска</section><section id="resultsTools"><strong>1 тур</strong></section><div class="results-layout"><aside class="results-filter-rail"></aside><section id="results">${card}</section></div></main>${drawer}</body>`;

function inside(inner, outer, message) {
  assert.ok(inner.left >= outer.left - 1 && inner.right <= outer.right + 1
    && inner.top >= outer.top - 1 && inner.bottom <= outer.bottom + 1, message);
}

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined,
  });
  const output = process.env.SEARCH3_RESULTS_OUTPUT;
  if (output) fs.mkdirSync(output, { recursive: true });
  let states = 0;
  try {
    for (const width of [375, 760, 761, 999, 1000, 1440]) {
      for (const expanded of [false, true]) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        try {
          await page.setContent(html);
          await page.addStyleTag({ content: css });
          await page.evaluate(() => {
            const force = (selector, declarations) => {
              const node = document.querySelector(selector);
              for (const [name, value] of Object.entries(declarations)) node.style.setProperty(name, value, 'important');
            };
            force('.v2-shell', { display: 'block', width: '100%', 'max-width': 'none', padding: '0' });
          });
          if (expanded) await page.evaluate(() => {
            document.body.classList.add('search3-hotel-tours-open');
            document.querySelector('.hotel-card').classList.add('search3-tours-open');
            document.querySelector('.hotel-tours').hidden = false;
            document.querySelector('.mrf-sheet').classList.add('is-open');
          });
          await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
          if (expanded && width <= 999) await page.waitForTimeout(250);
          const state = await page.evaluate(() => {
            const pick = selector => {
              const node = document.querySelector(selector);
              const value = node.getBoundingClientRect();
              const box = { left: value.left, top: value.top, right: value.right, bottom: value.bottom, width: value.width, height: value.height };
              const style = getComputedStyle(node);
              return { box, display: style.display, visibility: style.visibility, pointerEvents: style.pointerEvents,
                opacity: Number(style.opacity), transform: style.transform, position: style.position };
            };
            return {
              overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
              host: pick('.results-layout'), card: pick('.hotel-card'), main: pick('.hotel-main'),
              photo: pick('.hotel-photo'), body: pick('.hotel-body'), facts: pick('.search3-hotel-facts'),
              action: pick('.search3-hotel-action'), disclosure: pick('.search3-show-tours'),
              tours: pick('.hotel-tours'), row: pick('.tour-row'), meta: pick('.tour-meta'),
              tourAction: pick('.tour-action'), direct: pick('.direct-tour'),
              sheet: pick('.mrf-sheet'), backdrop: pick('.mrf-backdrop'), panel: pick('.mrf-panel'),
              close: pick('.mrf-close'), drawerActions: pick('.mrf-actions'),
              retired: ['.hotel-actions', '.hotel-inline-detail', '.hotel-compare-toggle', '.result-decision-badges', '.hotel-choice-hint', '.hotel-decision-line'].map(selector => pick(selector).display),
            };
          });
          assert.ok(state.overflow <= 1, `${width}: page must not overflow horizontally`);
          // 761 is the exact drawer handoff witness; card acceptance retains its original widths.
          if (width !== 761) {
            assert.ok(Math.abs(state.card.box.width - state.host.box.width) <= 1,
              `${width}: card fills results host (${state.card.box.width}/${state.host.box.width})`);
            inside(state.main.box, state.card.box, `${width}: hotel content stays inside card`);
            inside(state.body.box, state.main.box, `${width}: hotel body stays inside main row`);
            inside(state.facts.box, state.body.box, `${width}: hotel facts stay inside body`);
            inside(state.action.box, state.body.box, `${width}: hotel action stays inside body`);
            inside(state.disclosure.box, state.card.box, `${width}: disclosure stays inside card`);
            assert.ok(state.disclosure.box.height >= 43.5, `${width}: disclosure keeps a 44px target`);
            assert.ok(state.retired.every(display => display === 'none'), `${width}: retired card chrome stays hidden`);
            assert.ok(state.photo.box.right <= state.body.box.left + 1 || state.photo.box.bottom <= state.body.box.top + 1,
              `${width}: photo and body do not overlap`);
          }
          if (width <= 999) {
            assert.equal(state.sheet.display, 'block', `${width}: compact drawer remains mounted`);
            assert.equal(state.sheet.visibility, expanded ? 'visible' : 'hidden', `${width}: drawer visibility follows open state`);
            assert.equal(state.sheet.pointerEvents, expanded ? 'auto' : 'none', `${width}: drawer pointer state follows open state`);
            if (expanded) {
              assert.ok(state.backdrop.opacity >= .99, `${width}: open drawer backdrop is opaque`);
              assert.ok(Math.abs(state.panel.box.right - width) <= 1, `${width}: open drawer stays right-aligned`);
              assert.ok(state.close.box.width >= 43.5 && state.close.box.height >= 43.5, `${width}: drawer close keeps a 44px target`);
              assert.equal(state.drawerActions.position, width <= 760 ? 'fixed' : 'sticky', `${width}: drawer actions retain breakpoint ownership`);
              assert.ok(Math.abs(state.panel.box.width - (width <= 760 ? width : 440)) <= 1,
                `${width}: drawer width retains phone/tablet geometry`);
            }
          } else {
            assert.equal(state.sheet.display, 'none', `${width}: compact drawer stays absent on desktop`);
          }
          if (width !== 761) {
            if (width >= 1000) assert.ok(Math.abs(state.main.box.height - 230) <= 1, `${width}: desktop card owner keeps 230px geometry`);
            if (expanded) {
              assert.notEqual(state.tours.display, 'none', `${width}: expanded packages are visible`);
              inside(state.row.box, state.tours.box, `${width}: package row stays inside package list`);
              inside(state.meta.box, state.row.box, `${width}: package facts stay inside row`);
              inside(state.tourAction.box, state.row.box, `${width}: package action stays inside row`);
              inside(state.direct.box, state.row.box, `${width}: package CTA stays inside row`);
              assert.ok(state.direct.box.height >= 35.5, `${width}: package CTA remains actionable`);
            } else {
              assert.equal(state.tours.display, 'none', `${width}: collapsed packages stay hidden`);
            }
          }
          if (output) await page.screenshot({ path: path.join(output, `${width}-${expanded ? 'expanded' : 'collapsed'}.png`), fullPage: true, animations: 'disabled' });
          states += 1;
        } finally {
          await page.close();
        }
      }
    }
  } finally {
    await browser.close();
  }
  console.log(`SEARCH3_RESULTS_GEOMETRY_OK states=${states}`);
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
