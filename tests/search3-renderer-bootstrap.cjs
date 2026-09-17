'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const { loadSearch3Renderer } = require('./helpers/search3-renderer-bootstrap');

// Loader contract only: Search3 owns the renderer directly and must not inject
// the retired room normalizer into tests behind the runtime manifest's back.
const root = path.resolve(__dirname, '..');
const rendererPath = path.join(root, 'v2/results-renderer-v5.js');
const renderer = "window.order = ['renderer']; window.V2Results = { owner: 'renderer-only' };";
const originalRead = fs.readFileSync;
const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
const originalCwd = process.cwd();
const outsideRepo = fs.mkdtempSync(path.join(os.tmpdir(), 'search3-bootstrap-'));
let reads = [];
let rendererFailure = null;

try {
  fs.readFileSync = function (filename, encoding) {
    assert.equal(encoding, 'utf8');
    assert.ok(path.isAbsolute(filename), 'runtime source uses an absolute repository path');
    reads.push(filename);
    assert.equal(filename, rendererPath, 'the loader reads only the canonical Search3 renderer');
    if (rendererFailure) throw rendererFailure;
    return renderer;
  };
  process.chdir(outsideRepo);

  const first = { window: { marker: 'first' } };
  const firstResults = loadSearch3Renderer(first);
  assert.equal(firstResults, first.window.V2Results, 'returns the supplied sandbox renderer');
  assert.equal(firstResults.owner, 'renderer-only');
  assert.deepEqual(Array.from(first.window.order), ['renderer']);
  assert.deepEqual(reads, [rendererPath], 'no hidden room-normalizer dependency is injected');
  assert.equal(first.window.marker, 'first', 'existing fixture fields are preserved');

  reads = [];
  const second = vm.createContext({ window: { marker: 'second' } });
  const secondResults = loadSearch3Renderer(second);
  assert.equal(secondResults, second.window.V2Results, 'accepts an already contextified sandbox');
  assert.notEqual(firstResults, secondResults, 'separate sandboxes do not share renderer state');
  assert.deepEqual(Array.from(second.window.order), ['renderer']);
  assert.deepEqual(reads, [rendererPath]);

  reads = [];
  Object.defineProperty(globalThis, 'window', { value: {}, configurable: true, writable: true });
  const globalResults = loadSearch3Renderer();
  assert.equal(globalResults, globalThis.window.V2Results, 'legacy no-argument callers still work');
  assert.deepEqual(globalThis.window.order, ['renderer']);
  assert.deepEqual(reads, [rendererPath]);
  assert.notEqual(globalResults, firstResults, 'global loading does not alter previous sandboxes');

  reads = [];
  rendererFailure = new Error('fixture renderer read failure');
  const failed = { window: {} };
  assert.throws(() => loadSearch3Renderer(failed), error => error === rendererFailure,
    'renderer read failures propagate instead of producing a false-green test');
  assert.deepEqual(reads, [rendererPath]);
  assert.equal(failed.window.V2Results, undefined);
  rendererFailure = null;

  for (const invalid of [null, 'sandbox', 1, false]) {
    assert.throws(() => loadSearch3Renderer(invalid), TypeError);
  }
  console.log('SEARCH3_RENDERER_BOOTSTRAP_OK renderer_only=1 sandbox=1 isolation=1 global_compat=1 cwd_independent=1 fail_closed=1 no_hidden_normalizer=1');
} finally {
  fs.readFileSync = originalRead;
  process.chdir(originalCwd);
  fs.rmdirSync(outsideRepo);
  if (originalWindow) Object.defineProperty(globalThis, 'window', originalWindow);
  else delete globalThis.window;
}
