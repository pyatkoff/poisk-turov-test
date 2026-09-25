const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const root = path.resolve(__dirname, '../v2/visual-search');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'migration-manifest.json')));
assert.equal(manifest.visualVersion, 147);
assert.equal(manifest.liveConnected, false);
assert.equal(manifest.supplierRequests, false);
assert.equal(manifest.leadDelivery, false);
for (const [file, expected] of Object.entries(manifest.sourceFiles)) {
  assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(root, file))).digest('hex'), expected, file + ' differs from reviewed Site');
}
const html = fs.readFileSync(path.join(root, 'index.html'), 'utf8');
assert.match(html, /Живой поиск ещё не подключён/);
assert.match(html, /noindex, nofollow, noarchive/);
assert.doesNotMatch(html, /live-data\.js|value="live"|href="\.\/(?:review\.html|v17\/)/);
const scripts = [...html.matchAll(/<script src="([^"]+)"/g)].map(m => m[1]);
assert.deepEqual(scripts, ['./fixture-data.js', './local-db-parser.js', './recorded-data.js', './search-lifecycle-v1.js', './flight-picker-v18.js', './preview-lead.js', './app.js']);
for (const link of [...html.matchAll(/(?:src|href)="(\.\/[^"?#]+)"/g)].map(m => m[1])) assert(fs.existsSync(path.join(root, link)), link);
const bootstrap = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
assert.match(bootstrap, /\/\_preview\/search3-next-candidate\/visual-search\//);
assert.match(bootstrap, /http_response_code\(403\)/);
assert.match(bootstrap, /hash_file\('sha256'/);
console.log('PASS visual migration: 29 exact Site files, complete entry graph, offline-only scripts, noindex, isolated route and cache binding');
