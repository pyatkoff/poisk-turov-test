'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const bundledIife = require('./search3-bundle-iife.cjs');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const ids = ['search3-mobile-final-review-v2'];
const owners = ids.map(literal => bundledIife(source, { literal }))
  .sort((left, right) => source.indexOf(left.trimEnd()) - source.indexOf(right.trimEnd()));

function capture(hasSelectedRoot, existing = false) {
  const inserted = [];
  const seen = new Map(hasSelectedRoot ? [['selectedTour', {}]] : []);
  if (existing) for (const id of ids) seen.set(id, {});
  const context = {document: {
    getElementById: id => seen.get(id),
    createElement: tag => ({tag}),
    head: {appendChild(style) { seen.set(style.id, style); inserted.push(style); }}
  }};
  for (let pass = 0; pass < 2; pass++) {
    for (const owner of owners) vm.runInNewContext(owner, context);
  }
  return inserted;
}

const styles = capture(true);
assert.deepEqual(styles.map(style => style.id), ids);
assert(styles.every(style => style.tag === 'style' && style.textContent.includes('@media')));
const [finalReview] = styles.map(style => style.textContent);
const linkedSelected = fs.readFileSync(path.join(__dirname, '../v2/search3-selected-flow-v2.css'), 'utf8');
assert.match(linkedSelected, /#selectedTour:not\(\.search3-final-review\).*\.tour-flights\{order:4!important/s);
assert.doesNotMatch(linkedSelected, /search3-mobile-convergence-style/);
assert.match(finalReview, /#selectedTour\.search3-final-review:not\(\.search3-lead-entry\)/);
assert.doesNotMatch(finalReview, /search3-review-heading|search3-booking-step/);
assert.match(finalReview, /\.search3-lead-shell>\.lead-form\{[^}]*display:none!important/);
assert.match(finalReview, /\.search3-booking-summary\{[^}]*display:block!important/);
assert.match(finalReview, /\.search3-summary-submit\{(?=[^}]*width:100%!important)(?=[^}]*min-height:48px!important)/);
assert.deepEqual(capture(false).map(style => style.id), ids);
assert.equal(capture(true, true).length, 0);
console.log('PASS: compiled private CSS preserves injection order, root guard and idempotence');
