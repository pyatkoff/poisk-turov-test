from pathlib import Path

source = Path('src/search3/behavior/results/shortlist.js')
text = source.read_text()
old = "panel.dataset.mobileOpen=mobileOpen?'true':'false';"
new = "panel.dataset.mobileOpen=(mobileOpen||(!saved.length&&!!message))?'true':'false';"
if old not in text:
    raise SystemExit('shortlist recovery visibility anchor missing')
source.write_text(text.replace(old, new, 1))

test = Path('tests/search3-shortlist-browser.cjs')
text = test.read_text()
anchor = "}\n\nasync function checkJourney(browser, width) {"
helper = """}

async function openComparison(page, width, options = {}) {
  const shortlist = page.locator('.search3-shortlist');
  const body = shortlist.locator('.search3-shortlist__body');
  const disclosure = shortlist.locator('.search3-shortlist-disclosure');
  if (width <= 600) {
    assert.equal(await disclosure.isVisible(), true, 'mobile comparison exposes one explicit disclosure');
    assert.ok((await disclosure.boundingBox()).height >= 44, 'mobile comparison disclosure retains a 44px target');
    if (options.assertCollapsed) {
      assert.equal(await disclosure.getAttribute('aria-expanded'), 'false', 'mobile comparison starts collapsed');
      assert.equal(await body.isVisible(), false, 'collapsed mobile comparison does not dominate the results flow');
    }
    if ((await disclosure.getAttribute('aria-expanded')) !== 'true') await disclosure.click();
    assert.equal(await disclosure.getAttribute('aria-expanded'), 'true', 'mobile comparison expands only on explicit request');
    assert.equal(await body.isVisible(), true, 'explicit mobile disclosure reveals comparison content');
    const geometry = await shortlist.locator('.search3-shortlist-item').evaluateAll(nodes => nodes.map(node => {
      const rect = node.getBoundingClientRect();
      const parent = node.parentElement.getBoundingClientRect();
      return { left: rect.left, right: rect.right, width: rect.width, parentLeft: parent.left, parentRight: parent.right, parentWidth: parent.width };
    }));
    geometry.forEach(item => {
      assert.ok(item.width >= item.parentWidth - 3, 'opened mobile comparison cards use the full comparison width');
      assert.ok(item.left >= item.parentLeft - 2 && item.right <= item.parentRight + 2, 'opened mobile cards are not clipped horizontally');
    });
  } else {
    assert.equal(await disclosure.isVisible(), false, 'desktop comparison stays expanded without a redundant mobile disclosure');
    assert.equal(await body.isVisible(), true, 'desktop comparison remains directly visible');
  }
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2), false, 'comparison creates no page-level horizontal overflow');
}

async function checkJourney(browser, width) {"""
if anchor not in text:
    raise SystemExit('browser helper anchor missing')
text = text.replace(anchor, helper, 1)
replacements = [
    ("    await addOffer(page, 'offer-third');\n    const shortlist = page.locator('.search3-shortlist');",
     "    await addOffer(page, 'offer-third');\n    await openComparison(page, width, { assertCollapsed: true });\n    const shortlist = page.locator('.search3-shortlist');"),
    ("    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'two exact snapshots survive reload');",
     "    await page.waitForFunction(() => document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');\n    await openComparison(page, width);\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'two exact snapshots survive reload');"),
    ("    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'fixture restores persisted snapshots for the remaining selection checks');",
     "    await page.waitForFunction(() => window.V2Results && window.Search3Shortlist && document.querySelector('#tourSearch')?.dataset.catalogSource === 'partial');\n    await openComparison(page, width);\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 2, 'fixture restores persisted snapshots for the remaining selection checks');"),
    ("    await render(page, 731);\n    assert.equal(await page.locator('.search3-shortlist-select:enabled').count(), 2, 'unique current-generation matches restore explicit selection authority');",
     "    await render(page, 731);\n    await openComparison(page, width);\n    assert.equal(await page.locator('.search3-shortlist-select:enabled').count(), 2, 'unique current-generation matches restore explicit selection authority');"),
    ("    await render(page, 731);\n    await page.locator('.search3-shortlist-clear').focus();",
     "    await render(page, 731);\n    await openComparison(page, width);\n    await page.locator('.search3-shortlist-clear').focus();"),
    ("    await addOffer(page, 'offer-standard', true);\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 1, `${mode}: in-memory fallback retains the explicit snapshot`);",
     "    await addOffer(page, 'offer-standard', true);\n    await openComparison(page, width);\n    assert.equal(await page.locator('.search3-shortlist-item').count(), 1, `${mode}: in-memory fallback retains the explicit snapshot`);")
]
for old, new in replacements:
    if old not in text:
        raise SystemExit('browser replacement anchor missing: ' + old[:80])
    text = text.replace(old, new, 1)
test.write_text(text)
