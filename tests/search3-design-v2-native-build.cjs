'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), os = require('node:os'), crypto = require('node:crypto');
const builder = require('../scripts/build/search3_design_v2_native.cjs');
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'native-design-v2-build-'));
try {
  fs.writeFileSync(path.join(tmp, '.htaccess'), '<FilesMatch "\\.php$">\nRequire all denied\n</FilesMatch>\n');
  fs.copyFileSync(path.resolve(__dirname, '../v2/preview-lead-disabled.php'), path.join(tmp, 'preview-lead-disabled.php'));
  for (const src of builder.scriptGraph.filter(src => !src.startsWith('./native-v2/'))) {
    const relative = path.normalize(path.join('visual-search', src));
    fs.mkdirSync(path.dirname(path.join(tmp, relative)), {recursive:true});
    fs.copyFileSync(path.resolve(__dirname, '../v2', relative), path.join(tmp, relative));
  }
  const guard = fs.readFileSync(path.join(tmp, 'preview-lead-disabled.php'));
  const manifest = builder.build(tmp, {isolated:true});
  assert.equal(manifest.isolatedOverlay, true);
  assert.equal(manifest.canonicalDependenciesRewritten, false, 'Trusted publication prepare owns route derivation');
  assert.equal(manifest.donorSource, '00fe6734c08acf234756bb59e5b61dbfecf41941');
  assert.deepEqual(fs.readFileSync(path.join(tmp, 'preview-lead-disabled.php')), guard);
  const entry = fs.readFileSync(path.join(tmp, 'visual-search/index.html'), 'utf8');
  assert(!entry.includes('./app.js') && !entry.includes('./styles.css'));
  for (const item of [...manifest.graph.scripts, ...manifest.graph.styles]) {
    const bytes = fs.readFileSync(path.join(tmp, item.path));
    assert.equal(hash(bytes), item.sha256); assert.equal(bytes.length, item.bytes);
  }
  assert.equal(hash(fs.readFileSync(path.join(tmp, 'visual-search/index.html'))), manifest.entry.html);
  assert.equal(hash(fs.readFileSync(path.join(tmp, 'visual-search/index.php'))), manifest.entry.php);
  const php = fs.readFileSync(path.join(tmp, 'visual-search/index.php'), 'utf8');
  assert(php.includes("$entry = '/_preview/search3-design-v2-candidate/visual-search/';"));
  assert(!php.includes('search3-next-candidate') && !php.includes('DOCUMENT_ROOT'));
  fs.writeFileSync(path.join(tmp, 'api-v2.php'), '<?php');
  assert.throws(() => builder.build(tmp, {isolated:true}), /production endpoint/);
  fs.unlinkSync(path.join(tmp, 'api-v2.php')); fs.unlinkSync(path.join(tmp, '.htaccess'));
  assert.throws(() => builder.build(tmp, {isolated:true}), /PHP boundary/);
  console.log('PASS native build: exact donor/dependency graph, fixed entry, isolated overlay retains lead guard and rejects production endpoints');
} finally { fs.rmSync(tmp, {recursive:true,force:true}); }
