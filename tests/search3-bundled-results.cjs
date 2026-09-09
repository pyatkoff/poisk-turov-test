// Exercise the actual shipped IIFE, including its build-time private parts.
const fs = require('node:fs');
const path = require('node:path');
const bundledIife = require('./search3-bundle-iife.cjs');
const bundle = fs.readFileSync(path.join(__dirname, '../v2/search3-results-filters-v1.js'), 'utf8');
module.exports = bundledIife(bundle, { global: 'Search3CandidateResultsV1' });
