'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const html = fs.readFileSync(path.join(root, 'v2/prototype-search/index.html'), 'utf8');
const css = fs.readFileSync(path.join(root, 'v2/prototype-search/mobile-controls-v1.css'), 'utf8');

const baseLink = '<link rel="stylesheet" href="./styles.css">';
const mobileLink = '<link rel="stylesheet" href="./mobile-controls-v1.css">';
const baseIndex = html.indexOf(baseLink);
const mobileIndex = html.indexOf(mobileLink);
assert.ok(baseIndex >= 0, 'Prototype must keep its canonical styles.css link');
assert.ok(mobileIndex > baseIndex, 'Mobile control layer must load after the canonical stylesheet');
assert.equal(html.indexOf(mobileLink, mobileIndex + 1), -1, 'Mobile control layer must be linked once');

assert.equal(css.includes('!important'), false, 'Mobile layer must not rely on !important');
assert.match(css, /@media\s*\(max-width\s*:\s*760px\)\s*\{[\s\S]*\}\s*$/,
  'Mobile changes must stay scoped to <=760px');

const normalized = css.replace(/\s+/g, ' ');
assert.match(normalized,
  /\.sort-label select, \.offer-list-toolbar select, \.offer-controls select \{ font-size:16px; min-height:44px; \}/,
  'Hotel sort, offer sort and offer condition selects need 16px text and 44px minimum height');
assert.match(normalized,
  /\.offer-list-toolbar \{ font-size:16px; \}/,
  'Offer-list toolbar label must remain readable on mobile');

const forbiddenDesktopMedia = /@media\s*\(min-width/i;
assert.equal(forbiddenDesktopMedia.test(css), false, 'This focused layer must not alter desktop widths');

console.log(JSON.stringify({
  test: 'search3-prototype-mobile-controls',
  mobileMaxWidth: 760,
  selectFontSizePx: 16,
  minTapHeightPx: 44,
  supplierRequests: 0,
  leadRequests: 0,
  status: 'passed'
}));
