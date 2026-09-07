'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const linkedReview = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.css'), 'utf8');
const linkedSelected = fs.readFileSync(path.join(__dirname, '../v2/search3-selected-flow-v2.css'), 'utf8');
const retiredOwner = fs.readFileSync(path.join(__dirname, '../src/search3/behavior/summary-cta-styles.js'), 'utf8');
const reviewOwner = fs.readFileSync(path.join(__dirname, '../src/search3/styles/review-layout.css'), 'utf8');

// Shared DS2 body:has(#selectedTour) #selectedTour rules carry two IDs.
// Moving the injector to a linked owner must preserve that cascade boundary.
const responsiveOwners = reviewOwner.match(/html body[^\n]+\[data-search3-final-layout="maket7"\][^\n]+/g);
assert.equal(responsiveOwners.length, 2);
assert.ok(responsiveOwners.every(selector => selector.includes('#selectedTour#selectedTour')),
  'review header/board owners must outrank the two-ID shared DS2 context');

assert.match(linkedSelected, /#selectedTour:not\(\.search3-final-review\).*\.tour-flights\{order:4!important/s);
assert.doesNotMatch(linkedSelected, /search3-mobile-convergence-style/);
assert.doesNotMatch(source, /search3-mobile-final-review-v2|createElement\(['"]style['"]\)/);
assert.equal(retiredOwner.replace(/\/\*[\s\S]*?\*\//g, '').trim(), '');
assert.match(linkedReview,
  /html body\.search3-candidate\.search3-selected-open #selectedTour#selectedTour\.search3-final-review:not\(\.search3-lead-entry\)\{[^}]*display:grid!important/);
assert.match(linkedReview,
  /:is\(\.selected-price,\.facts,\.facts-secondary-toggle,\.selected-choice-summary,\.hotel-desc,\.hotel-desc-toggle,\.room-details-host,\.selected-confidence\)\{display:none!important/);
assert.match(linkedReview, /@media \(width<=640px\).*grid-template-columns:108px minmax\(0,1fr\)!important/s);
assert.match(linkedReview, /\.search3-booking-summary\{[^}]*display:block!important[^}]*position:static!important/);
assert.match(linkedReview, /\.search3-summary-submit\{(?=[^}]*width:100%!important)(?=[^}]*min-height:48px!important)/);
console.log('PASS: retired style injectors are absent and linked review owners retain phase guards');
