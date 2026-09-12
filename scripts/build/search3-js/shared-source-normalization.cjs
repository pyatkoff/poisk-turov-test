'use strict';
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

const sha256 = value => crypto.createHash('sha256').update(value).digest('hex');

// Two canonical protected owners use syntax which the pinned printer rewrites even
// with compression disabled. Never apply these printer rewrites to source files; normalize
// only the derived Search3 representation. Exact source hashes and single-match
// replacements make every accepted rewrite explicit and fail closed on source drift.
const rules = {
  'tour-controller-v4.js': {
    sourceSha256: 'd4f7ad90c909ffdb3b8ab6993adaa574807bd8cc945adbb60be117f9646c81cf',
    from: 'catch(_){};}try{',
    to: 'catch(_){}}try{'
  },
  'catalogs-v2.js': {
    sourceSha256: '614ef26c94e573b15cfa74695263ff135d03cc7522a23ed4feb007dc4771949e',
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
