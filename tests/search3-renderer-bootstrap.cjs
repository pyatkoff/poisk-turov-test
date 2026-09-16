'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const { loadSearch3Renderer } = require('./helpers/search3-renderer-bootstrap');

// These small fixtures test the loader contract, not room/meal business rules.
// The existing meal-owner regression exercises the real runtime with this loader.
const root = path.resolve(__dirname, '..');
const normalizerPath = path.join(root, 'v2/search3-room-normalizer-v1.js');
const rendererPath = path.join(root, 'v2/results-renderer-v5.js');
const normalizer = "window.order = ['normalizer']; window.normalizer = { label: 'fixture' };";
const renderer = "if (!window.normalizer) throw new Error('missing dependency'); window.order.push('renderer'); window.V2Results = { owner: window.normalizer };";
const originalRead = fs.readFileSync;
const originalWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
const originalCwd = process.cwd();
const outsideRepo = fs.mkdtempSync(path.join(os.tmpdir(), 'search3-bootstrap-'));
let reads = [];
let missingDependency = null;

try {
  fs.readFileSync = function (filename, encoding) {
    assert.equal(encoding, 'utf8');
    assert.ok(path.isAbsolute(filename), 'runtime sources use absolute repository paths');
    reads.push(filename);
    if (filename === normalizerPath) {
      if (missingDependency) throw missingDependency;
      return normalizer;
    }
    assert.equal(filename, rendererPath, 'the loader reads only the two declared owners');
    return renderer;
  };
  process.chdir(outsideRepo);

  const first = { window: { marker: 'first' } };
  const firstResults = loadSearch3Renderer(first);
  assert.equal(firstResults, first.window.V2Results, 'returns the supplied sandbox renderer');
  assert.equal(firstResults.owner, first.window.normalizer, 'both scripts share one context');
  assert.deepEqual(Array.from(first.window.order), ['normalizer', 'renderer']);
  assert.deepEqual(reads, [normalizerPath, rendererPath], 'normalizer is loaded first exactly once');
  assert.equal(first.window.marker, 'first', 'existing fixture fields are preserved');

  reads = [];
  const second = vm.createContext({ window: { marker: 'second' } });
  const secondResults = loadSearch3Renderer(second);
  assert.equal(secondResults, second.window.V2Results, 'accepts an already contextified sandbox');
  assert.notEqual(firstResults, secondResults, 'separate sandboxes do not share renderer state');
  assert.notEqual(firstResults.owner, secondResults.owner, 'dependencies are isolated too');
  assert.equal(first.window.marker, 'first');
  assert.deepEqual(reads, [normalizerPath, rendererPath]);

  reads = [];
  Object.defineProperty(globalThis, 'window', { value: {}, configurable: true, writable: true });
  const globalResults = loadSearch3Renderer();
  assert.equal(globalResults, globalThis.window.V2Results, 'legacy no-argument callers still work');
  assert.deepEqual(globalThis.window.order, ['normalizer', 'renderer']);
  assert.deepEqual(reads, [normalizerPath, rendererPath]);
  assert.notEqual(globalResults, firstResults, 'global loading does not alter previous sandboxes');

  reads = [];
  missingDependency = new Error('fixture read failure');
  const failed = { window: {} };
  assert.throws(() => loadSearch3Renderer(failed), error => error === missingDependency,
    'dependency failures propagate instead of producing a false-green test');
  assert.deepEqual(reads, [normalizerPath], 'the renderer does not run after a dependency failure');
  assert.equal(failed.window.V2Results, undefined);
  missingDependency = null;

  for (const invalid of [null, 'sandbox', 1, false]) {
    assert.throws(() => loadSearch3Renderer(invalid), TypeError);
  }
  console.log('SEARCH3_RENDERER_BOOTSTRAP_OK order=1 sandbox=1 isolation=1 global_compat=1 cwd_independent=1 fail_closed=1');
} finally {
  fs.readFileSync = originalRead;
  process.chdir(originalCwd);
  fs.rmdirSync(outsideRepo);
  if (originalWindow) Object.defineProperty(globalThis, 'window', originalWindow);
  else delete globalThis.window;
}
