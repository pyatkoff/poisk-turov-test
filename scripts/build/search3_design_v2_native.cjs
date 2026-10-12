'use strict';
// Native donor extraction. No legacy presentation module is imported.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');

const ROOT = path.resolve(__dirname, '../..');
const SOURCE = path.join(ROOT, 'src/search3-design-v2');
const SHA = '00fe6734c08acf234756bb59e5b61dbfecf41941';
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const expected = Object.freeze({
  'anytour-fragment.html': 'cc4dde79750e45cbb9e236fd909e9050c44ab0d14c68ad9b5470b9d368c3e2fb',
  'product-desktop.css': 'ab55fb2b1c8857d3d62e3826325c0f3bd4629418a555e5f021c95ab8f2995848'
});

function reference() {
  const files = Object.fromEntries(Object.keys(expected).map(name => {
    const value = fs.readFileSync(path.join(SOURCE, 'reference', name), 'utf8');
    assert.equal(hash(value), expected[name], 'Exact final Sites v2 donor: ' + name);
    return [name, value];
  }));
  return files;
}

function extract() {
  const files = reference(), fragment = files['anytour-fragment.html'];
  const css = fragment.match(/^<style>([\s\S]*?)<\/style>/)?.[1];
  assert(css, 'Original donor stylesheet');
  const mock = fragment.match(/<script[^>]*>([\s\S]*?)<\/script>/g);
  assert.equal(mock?.length, 1, 'Exactly one donor mock owner is removed');
  let markup = fragment.replace(/^<style>[\s\S]*?<\/style>/, '')
    .replace(/<script[^>]*>[\s\S]*?<\/script>/g, '');
  // These controls exist only to inspect the donor, not in the product.
  markup = markup.replace(/<p class="st-caption">[\s\S]*?<\/p>/g, '')
    .replace(/<nav class="st-preview-nav"[\s\S]*?<\/nav>/g, '')
    .replace(/<nav class="st-browser-tabs"[\s\S]*?<\/nav>/g, '');
  // The single mobile product is the original template; frontend.js clones it
  // into the donor's own desktop mount and selects the responsive variant.
  markup = markup.replace('data-product>', 'data-product hidden>');
  assert(!markup.includes('<script'), 'No mock script in product document');
  return {markup, css, desktopCSS: files['product-desktop.css']};
}

const scriptGraph = Object.freeze([
    '../prototype-search/config.js', '../runtime-v3.js', '../lead-search-context.js', '../lead-form-guard-v1.js',
    './lead-runtime-v1.js', '../search3-canonical-profiles-v1.js',
    '../search3-local-db-provider-v1.js', '../prototype-search/data.js',
    '../prototype-search/calendar-exact-hotel-v1.js',
    '../prototype-search/source-receipt-v1.js',
    '../prototype-search/lead.js',
    './native-v2/lucide.min.js', './native-v2/bridge.js', './native-v2/tour.js', './native-v2/frontend.js',
    './native-v2/bootstrap.js'
]);
const styleGraph = Object.freeze(['./native-v2/donor.css', './native-v2/product-desktop.css', './native-v2/viewport.css']);
function documentHTML(markup) {
  return '<!doctype html>\n<html lang="ru"><head>\n' +
    '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">\n' +
    '<meta name="robots" content="noindex,nofollow,noarchive">\n' +
    '<meta name="referrer" content="same-origin">\n<title>AnyTour — поиск туров</title>\n' +
    '<link rel="stylesheet" href="./native-v2/donor.css">\n' +
    '<link rel="stylesheet" href="./native-v2/product-desktop.css">\n' +
    '<link rel="stylesheet" href="./native-v2/viewport.css">\n' +
    scriptGraph.map(src => '<script src="' + src + '"' +
      (src === '../prototype-search/config.js' ? ' data-local-tv-catalog="enabled"' : '') +
      ' defer></script>').join('\n') + '\n</head><body>\n' +
    '<main id="design-v2-native-root" aria-busy="true">' + markup + '</main>\n' +
    '<noscript>Для поиска туров включите JavaScript.</noscript>\n</body></html>\n';
}

function build(output, options = {}) {
  assert(output, 'Explicit isolated output directory is required');
  output = path.resolve(output);
  assert(!output.startsWith(path.join(ROOT, 'v2') + path.sep), 'Do not replace a runtime entry during source preparation');
  assert(output !== ROOT && output !== SOURCE, 'Output is not a source root');
  if (options.isolated) {
    for (const name of ['api.php', 'api-v2.php', 'lead-adapter.php', 'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php']) {
      assert(!fs.existsSync(path.join(output, name)), 'Native overlay rejects a production endpoint: ' + name);
    }
    assert(fs.existsSync(path.join(output, 'preview-lead-disabled.php')), 'Existing isolated lead guard required');
    assert(fs.existsSync(path.join(output, '.htaccess')), 'Existing isolated PHP boundary required');
  }
  const parts = extract(), target = path.join(output, 'visual-search');
  fs.mkdirSync(path.join(target, 'native-v2'), {recursive: true});
  // Canonical dependencies remain byte-identical here. The existing guarded
  // design-v2 artifact derivation owns pathname remapping before publication.
  if (!options.isolated) fs.cpSync(path.join(ROOT, 'v2'), output, {recursive: true});
  const files = {
    'donor.css': parts.css,
    'product-desktop.css': parts.desktopCSS,
    'viewport.css': fs.readFileSync(path.join(SOURCE, 'viewport.css'), 'utf8'),
    'frontend.js': fs.readFileSync(path.join(SOURCE, 'frontend.js'), 'utf8'),
    'tour.js': fs.readFileSync(path.join(SOURCE, 'tour.js'), 'utf8'),
    'bridge.js': fs.readFileSync(path.join(SOURCE, 'bridge.js'), 'utf8'),
    'bootstrap.js': fs.readFileSync(path.join(SOURCE, 'bootstrap.js'), 'utf8'),
    'lucide.min.js': fs.readFileSync(path.join(SOURCE, 'reference/lucide.min.js'), 'utf8'),
    'lucide-LICENSE': fs.readFileSync(path.join(SOURCE, 'reference/lucide-LICENSE'), 'utf8')
  };
  for (const [name, value] of Object.entries(files)) fs.writeFileSync(path.join(target, 'native-v2', name), value);
  fs.writeFileSync(path.join(target, 'index.html'), documentHTML(parts.markup));
  fs.copyFileSync(path.join(SOURCE, 'entry.php'), path.join(target, 'index.php'));
  const graphEntry = src => {
    const file = path.resolve(target, src), value = fs.readFileSync(file);
    assert(file.startsWith(output + path.sep), 'Dependency graph stays in payload');
    return {src, path: path.relative(output, file).split(path.sep).join('/'), sha256: hash(value), bytes: value.length};
  };
  const manifest = {schema: 2, donorSource: SHA, donor: expected,
    status: 'native-source-candidate-not-published', legacyPresentationLoaded: false,
    mockSimulatorLoaded: false, isolatedOverlay: options.isolated === true, canonicalDependenciesRewritten: false,
    entry: {html: hash(fs.readFileSync(path.join(target, 'index.html'))), php: hash(fs.readFileSync(path.join(target, 'index.php')))},
    graph: {scripts: scriptGraph.map(graphEntry), styles: styleGraph.map(graphEntry)},
    files: Object.fromEntries(Object.entries(files).map(([name, value]) => [name, hash(value)]))};
  fs.writeFileSync(path.join(target, 'native-v2/source-manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
  return manifest;
}

module.exports = {reference, extract, documentHTML, build, expected, scriptGraph, styleGraph};
if (require.main === module) {
  try { console.log(JSON.stringify(build(process.argv[2], {isolated: process.argv.includes('--isolated')}), null, 2)); }
  catch (error) { console.error(error.message); process.exitCode = 1; }
}
