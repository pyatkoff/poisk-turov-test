'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'src/search3/styles/results-layout.css'), 'utf8');

// Exact offers should read like dense OTA rows, not nested cards. Keep this test
// on the canonical source owner so later visual work cannot silently restore the
// older padded/operator-heavy composition.
assert.ok(source.includes('& .tour-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(190px,24%);gap:14px;align-items:center;border:0;border-top:1px solid var(--at-line-soft,#e9edf6);border-radius:0;padding:12px 16px;background:#fff}'));
assert.ok(source.includes('& .tour-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 12px;margin-top:6px}'));
assert.ok(source.includes('& .tour-secondary-facts{display:flex;flex-wrap:wrap;gap:3px 10px;margin-top:6px;padding-top:0;border-top:0}'));
assert.ok(source.includes('& .tour-operator .hotel-operator{width:96px;grid-template-rows:22px auto;padding:3px 5px}'));
assert.ok(source.includes('& .tour-operator .hotel-operator-logo{height:22px}'));

// Mobile preserves exact facts and actions, but removes unnecessary vertical
// padding. The <=350 fallback stays available for Select + Compare.
assert.ok(source.includes('@media(max-width:760px){'));
assert.ok(source.includes('& .tour-row{grid-template-columns:1fr;padding:10px 12px;gap:10px}'));
assert.ok(source.includes('& .tour-meta>strong{font-size:16px}'));
assert.ok(source.includes('& .tour-action{grid-template-columns:1fr auto;gap:5px 12px;align-items:center;padding:8px 0 0;border-left:0;border-top:1px solid var(--at-line-soft,#e9edf6)}'));
assert.ok(source.includes('@media(max-width:350px){'));
assert.ok(source.includes('& .tour-action:has(.search3-shortlist-toggle){grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 8px}'));

// Do not achieve density by hiding the business-critical exact price or actions.
assert.ok(!source.includes('.tour-row .hotel-price{display:none'));
assert.ok(!source.includes('.tour-row .direct-tour{display:none'));
assert.ok(!source.includes('.tour-row .search3-shortlist-toggle{display:none'));

console.log('SEARCH3_OFFER_ROW_DENSITY_OK');
