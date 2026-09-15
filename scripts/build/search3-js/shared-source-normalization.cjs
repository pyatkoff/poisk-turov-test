'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

const sha256 = value => crypto.createHash('sha256').update(value).digest('hex');

// Two canonical protected owners use syntax which the pinned printer rewrites even
// with compression disabled. Never apply these printer rewrites to source files; normalize
// only the derived Search3 representation. Exact source hashes and single-match
// replacements make every accepted rewrite explicit and fail closed on source drift.
// Relock this hash whenever a reviewed controller source change regenerates the shared map.
const rules = {
  'tour-controller-v4.js': {
    sourceSha256: '2047b9e402d5a1bd978e2af44da24154f2ae3c92c4c464b0d017f99d815e5d9a',
    from: 'catch(_){};}try{',
    to: 'catch(_){}}try{'
  },
  'catalogs-v2.js': {
    sourceSha256: 'e64de8426f95b6d199a8c4370a1228bd963f09f7e5b50acaac2e5bb78b55d961',
    from: "({'2':'3','3':'3.5','4':'4','5':'4.5'})",
    to: "({2:'3',3:'3.5',4:'4',5:'4.5'})"
  }
};

function normalizeSharedSource(name, source) {
  const rule = rules[name];
  if (!rule) return source;
  assert.equal(sha256(source), rule.sourceSha256, `${name} normalization source changed`);
  const first = source.indexOf(rule.from);
  assert.notEqual(first, -1, `${name} normalization input missing`);
  assert.equal(source.indexOf(rule.from, first + rule.from.length), -1,
    `${name} normalization input repeated`);
  return source.slice(0, first) + rule.to + source.slice(first + rule.from.length);
}

module.exports = { normalizeSharedSource, normalizationRules: rules };
