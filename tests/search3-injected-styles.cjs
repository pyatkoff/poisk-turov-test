'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const bundledIife = require('./search3-bundle-iife.cjs');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const ids = ['search3-mobile-convergence-style', 'search3-mobile-final-review-v2'];
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
assert.deepEqual(capture(false).map(style => style.id), [ids[1]]);
assert.equal(capture(true, true).length, 0);
console.log('PASS: compiled private CSS preserves injection order, root guard and idempotence');
