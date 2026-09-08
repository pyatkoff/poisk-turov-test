'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { compactBindings } = require('./compact.cjs');
const root = path.resolve(__dirname, '../../..');
const sha256 = value => crypto.createHash('sha256').update(value).digest('hex');

// Explicit reviewed files; controller/catalogs retain their original bytes because
// they do not pass the existing exact-print guard. Legacy endpoint stays unchanged.
const files = [
  'runtime-retry-policy.js', 'runtime-v3.js', 'analytics-v4.js',
  'results-renderer-v5.js', 'search-continue-v6.js', 'lead-search-context.js',
  'lead-ui-race-guard-v1.js', 'lead-form-guard-v1.js', 'flight-price-sync-v1.js',
  'unpriced-flight-price-reset-v1.js', 'catalog-local-routing-v1.js',
  'country-matrix-routing-v1.js', 'url-primary-catalog-sync-v1.js',
  'search-lifecycle-v6.js', 'passive-price-observer-v1.js'
];

(async () => {
  assert.ok(['--write', '--check'].includes(process.argv[2]), 'Use --write or --check');
  const entries = {};
  let before = 0, after = 0;
  for (const name of files) {
    const source = fs.readFileSync(path.join(root, 'v2', name), 'utf8');
    const code = await compactBindings(source); // A failed syntax proof aborts the build.
    entries[name] = { sourceSha256: sha256(source), codeSha256: sha256(code), code };
    before += Buffer.byteLength(source);
    after += Buffer.byteLength(code);
  }
  const output = JSON.stringify({ schema_version: 1, method: 'local-bindings-only', entries }) + '\n';
  const target = path.join(root, 'v2/search3-shared-runtime.json');
  if (process.argv[2] === '--write') fs.writeFileSync(target, output);
  else assert.equal(fs.readFileSync(target, 'utf8'), output, 'Stale Search3 shared runtime; run shared-runtime.cjs --write');
  console.log(`SEARCH3_SHARED_RUNTIME_OK before=${before} after=${after} saved=${before - after}`);
})().catch(error => { console.error(error.message); process.exitCode = 1; });
