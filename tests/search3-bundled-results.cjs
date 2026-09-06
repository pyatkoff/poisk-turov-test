// Exercise the actual shipped IIFE, including its build-time private parts.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const bundle = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
const start = bundle.indexOf('/* Candidate-owned result and responsive safety layer. */');
const end = bundle.indexOf('/* Candidate-only human-readable selected-tour presentation. */', start);
assert.ok(start >= 0 && end > start, 'the results IIFE retains its bundle position');
module.exports = bundle.slice(start, end);
