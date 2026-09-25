const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const root = path.resolve(__dirname, '../v2/visual-search');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'migration-manifest.json')));
assert.equal(manifest.visualVersion, 147);
assert.equal(manifest.liveConnected, true);
assert.equal(manifest.supplierRequests, 'explicit-user-actions-only');
assert.equal(manifest.leadDelivery, false);
for (const [file, expected] of Object.entries(manifest.sourceFiles)) {
  assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(root, file))).digest('hex'), (manifest.adaptedFiles[file]||expected), file + ' differs from reviewed Site');
}
const html = fs.readFileSync(path.join(root, 'index.html'), 'utf8');
assert.match(html, /Живой поиск ещё не подключён/);
assert.match(html, /noindex, nofollow, noarchive/);
assert.doesNotMatch(html, /live-data\.js|href="\.\/(?:review\.html|v17\/)/);
const scripts = [...html.matchAll(/<script src="([^"]+)"/g)].map(m => m[1]);
assert.deepEqual(scripts, ['./fixture-data.js', './local-db-parser.js', './recorded-data.js', './search-lifecycle-v1.js', './flight-picker-v18.js', './preview-lead.js', './app.js']);
for (const link of [...html.matchAll(/(?:src|href)="(\.\/[^"?#]+)"/g)].map(m => m[1])) assert(fs.existsSync(path.join(root, link)), link);
const bootstrap = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
assert.match(bootstrap, /\/\_preview\/search3-next-candidate\/visual-search\//);
assert.match(bootstrap, /http_response_code\(403\)/);
assert.match(bootstrap, /hash_file\('sha256'/);
assert.deepEqual(Object.keys(manifest.adaptedFiles),['app.js']);
assert.match(bootstrap,/prototype-search\/data\.js/);
assert.match(bootstrap,/prototype-search\/calendar-exact-hotel-v1\.js/);
console.log('PASS visual migration: preserved Site assets, explicit integration delta, separate live/offline entry graphs, noindex and cache binding');
