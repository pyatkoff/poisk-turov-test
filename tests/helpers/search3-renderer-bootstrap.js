'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const sources = ['v2/results-renderer-v5.js'];

// Use the same context for dependencies and renderer. No browser globals are
// invented here: each regression keeps ownership of its own fixtures.
function loadSearch3Renderer(sandbox) {
  if (sandbox !== undefined && (sandbox === null || typeof sandbox !== 'object')) {
    throw new TypeError('Renderer sandbox must be an object');
  }
  const context = sandbox === undefined ? null
    : (vm.isContext(sandbox) ? sandbox : vm.createContext(sandbox));
  for (const relativePath of sources) {
    const filename = path.join(root, relativePath);
    const source = fs.readFileSync(filename, 'utf8');
    if (context) vm.runInContext(source, context, { filename });
    else vm.runInThisContext(source, { filename });
  }
  // Preserve the existing no-argument/global-window callers.
  return (context || globalThis).window.V2Results;
}

module.exports = { loadSearch3Renderer };
