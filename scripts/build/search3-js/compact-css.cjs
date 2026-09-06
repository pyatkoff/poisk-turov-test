'use strict';
const assert = require('node:assert/strict');
const { isDeepStrictEqual } = require('node:util');
const css = require('css-tree');

const strict = { onParseError(error) { throw error; } };
const parsed = code => css.toPlainObject(css.parse(code, strict));

function compactCSS(code) {
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

module.exports = { compactCSS, parsed };
