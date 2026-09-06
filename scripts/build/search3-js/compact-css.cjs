'use strict';
const assert = require('node:assert/strict');
const { isDeepStrictEqual } = require('node:util');
const css = require('css-tree');
const { transform, Features } = require('lightningcss');

const strict = { onParseError(error) { throw error; } };
const parsed = code => css.toPlainObject(css.parse(code, strict));

function printCSS(code) {
  let retain = false;
  const tree = css.parse(code, { ...strict, positions: true, onComment(value) {
    // The parser retains /*! notes itself. Keep the entire asset if it contains
    // another protected comment whose exact position the printer cannot retain.
    if (!value.startsWith('!') && /@license|copyright|sourcemappingurl/i.test(value)) retain = true;
  } });
  if (retain) return code;
  css.walk(tree, node => {
    const key = node.type === 'Declaration' ? 'value' :
      node.type === 'Rule' || node.type === 'Atrule' ? 'prelude' : null;
    if (key && node[key] && node[key].loc) {
      const { start, end } = node[key].loc;
      // Keep selectors, conditions and declaration values as their exact source
      // slices, including calc spacing, escapes and custom-property token text.
      node[key] = { type: 'Raw', loc: null, value: code.slice(start.offset, end.offset) };
    }
  });
  const output = css.generate(tree) + '\n';
  assert.ok(isDeepStrictEqual(parsed(output), parsed(code)), 'Search3 CSS printing changed syntax');
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

function compactCSS(code) {
  // Keep protected notes in place. Ordinary private source notes were removed
  // by the source assembler before this build-only optimization.
  if (/\/\*[\s\S]*?(?:@license|copyright|sourcemappingurl)[\s\S]*?\*\//i.test(code)) return code;
  // Raw custom-property values can use comments as token boundaries. The CSS
  // optimizer removes these separators (red/**/blue becomes redblue), so keep
  // such styles exact instead of changing their token stream.
  let rawComments = false;
  css.walk(css.parse(code, strict), node => {
    if (node.type === 'Raw' && node.value.includes('/*')) rawComments = true;
  });
  if (rawComments) return code;
  const printed = printCSS(code);
  const result = transform({
    filename: 'search3.css', code: Buffer.from(printed), minify: true,
    exclude: Features.Nesting,
    targets: { safari: (16 << 16) | (5 << 8), chrome: 120 << 16, firefox: 117 << 16 }
  });
  assert.equal(result.warnings.length, 0, 'Search3 CSS optimizer emitted warnings');
  const output = result.code.toString() + '\n';
  parsed(output);
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

module.exports = { compactCSS, printCSS, parsed };
