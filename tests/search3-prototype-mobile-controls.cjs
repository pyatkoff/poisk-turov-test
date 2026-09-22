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

assert.match(html, /<fieldset class="quick-category">[\s\S]*?<legend>Категория отеля<\/legend>/,
  'The actual prototype search form must keep the quick-category legend');
assert.match(html, /class="quick-field" id="quick-meal"[\s\S]*?<span>Питание<\/span>/,
  'The actual prototype search form must keep the meal quick-field label');
assert.match(html, /class="quick-field" id="quick-budget"[\s\S]*?<span>Бюджет за всех<\/span>/,
  'The actual prototype search form must keep the budget quick-field label');
assert.match(html, /class="text-button more-filters" data-action="filters"[\s\S]*?Все фильтры/,
  'The actual prototype search form must keep the top all-filters action');

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
assert.match(normalized,
  /\.search-secondary label, \.search-secondary select, \.search-secondary \.more-filters \{ min-height:44px; \}/,
  'Legacy secondary controls keep their 44px minimum tap height');
assert.match(normalized,
  /\.search-secondary select \{ font-size:16px; \}/,
  'Legacy secondary selects retain the existing 16px mobile contract');
assert.match(normalized,
  /\.quick-category legend, \.quick-field > span:first-child, \.intro \.more-filters \{ font-size:14px; \}/,
  'Real mobile search-form labels and all-filters action need readable 14px text');
assert.match(normalized,
  /\.intro \.more-filters \{ min-height:44px; \}/,
  'Top all-filters action needs a 44px minimum tap height');
assert.match(normalized,
  /\.drawer-trigger, \.quick-chips \.chip, \.active-filter, \.hotel-links \.text-button, \.compare-btn, \.hotel-more \.text-button, \.offer-price \.primary, \.compare-tray > \.primary, \.calendar-heading \.text-button, \.calendar-foot \.text-button, \.destination-selection button, \.flex-dates button, \.filter-panel \.check-row, \.filter-panel \.star-options button, \.filter-top \.text-button \{ min-height:44px; \}/,
  'Frequent mobile filter, tour, calendar and destination actions need 44px minimum tap height');
assert.match(normalized,
  /\.applied-search > \.secondary, \.compact-search \.secondary \{ width:44px; min-width:44px; min-height:44px; \}/,
  'Mobile edit-search controls need a 44px minimum tap target');
assert.match(normalized,
  /\.filter-top \.mobile-close, \.favorite-button, \.card-photo-arrow, \.compare-tray \.icon-button, \.modal-header \.icon-button, #modal-back, \.counter button, \.favorite-item \.icon-button, \.compare-hotel-card \.compare-remove \{ width:44px; min-width:44px; height:44px; min-height:44px; \}/,
  'Mobile icon, counter and compare-remove actions need 44 by 44 tap targets');
assert.match(normalized,
  /\.hotel-image-wrap \{ aspect-ratio:3 \/ 2; \}/,
  'Mobile hotel cards need a calmer 3:2 photo stage');
assert.match(normalized,
  /\.hotel-detail-photos \{ grid-template-rows:108px 108px; \}/,
  'Mobile hotel details need a taller photo grid');

const forbiddenDesktopMedia = /@media\s*\(min-width/i;
assert.equal(forbiddenDesktopMedia.test(css), false, 'This focused layer must not alter desktop widths');

console.log(JSON.stringify({
  test: 'search3-prototype-mobile-controls',
  mobileMaxWidth: 760,
  actualSearchDom: true,
  searchLabelFontSizePx: 14,
  minTapHeightPx: 44,
  searchReadability: [
    'hotel-category-legend', 'meal-label', 'budget-label', 'all-filters-action'
  ],
  filterTapTargets: [
    'drawer', 'preset-chip', 'active-filter', 'drawer-close',
    'filter-checkbox-row', 'filter-star-option', 'filter-reset'
  ],
  tourTapTargets: [
    'edit-search', 'hotel-link', 'compare', 'expand-offers', 'choose-offer',
    'favorite', 'photo-arrow', 'compare-tray', 'modal-close', 'modal-back',
    'party-counter', 'favorite-remove', 'compare-remove'
  ],
  secondaryTapTargets: [
    'calendar-choose-dates', 'calendar-clear-date', 'destination-selection-action',
    'date-length-shortcut'
  ],
  photoStage: {
    cardAspectRatio: '3:2',
    detailRowsPx: [108, 108]
  },
  supplierRequests: 0,
  leadRequests: 0,
  status: 'passed'
}));
