'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const owners = [...source.matchAll(/\/\* Static mobile/g)].map(match => {
  const end = source.indexOf('/* donor:', match.index);
  assert.ok(end > match.index, 'style owner ends before the next behavior owner');
  return source.slice(match.index, end);
});
assert.equal(owners.length, 2);
const ids = ['search3-mobile-convergence-style', 'search3-mobile-final-review-v2'];

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
