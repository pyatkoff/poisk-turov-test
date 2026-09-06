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

function groupAdjacentMedia(code) {
  const tree = css.parse(code, { ...strict, positions: true });
  const nodes = tree.children.toArray();
  function owner(node) {
    if (node?.type !== 'Rule' || node.prelude?.type !== 'SelectorList'
        || node.prelude.children.size !== 1) return null;
    if (code.slice(node.loc.start.offset, node.block.loc.start.offset).includes('/*')) return null;
    let nestedParent = false;
    css.walk(node.prelude, value => { if (value.type === 'NestingSelector') nestedParent = true; });
    const children = node.block.children.toArray();
    if (nestedParent || !children.length || !children.every(child => child.type === 'Rule'
        && child.prelude?.type === 'SelectorList' && child.prelude.children.toArray()
          .every(selector => selector.children.first?.type === 'NestingSelector'))) return null;
    return { root: node, key: css.generate(node.prelude) };
  }
  function entry(node) {
    if (node.type === 'Rule') return owner(node);
    if (node.type !== 'Atrule' || node.name !== 'media' || node.block?.children.size !== 1) return null;
    const result = owner(node.block.children.first);
    if (result && (code.slice(node.block.loc.start.offset + 1, result.root.loc.start.offset).trim()
        || code.slice(result.root.loc.end.offset, node.block.loc.end.offset - 1).trim())) return null;
    return result ? { ...result, media: node } : null;
  }
  const header = node => code.slice(node.loc.start.offset, node.block.loc.start.offset + 1);
  const children = node => code.slice(node.block.loc.start.offset + 1, node.block.loc.end.offset - 1);
  const edits = [];
  for (let index = 0; index < nodes.length;) {
    const first = entry(nodes[index]);
    if (!first) { index++; continue; }
    let end = index + 1;
    while (end < nodes.length && entry(nodes[end])?.key === first.key
        && !code.slice(nodes[end - 1].loc.end.offset, nodes[end].loc.start.offset).trim()) end++;
    if (end - index > 1) {
      let content = '';
      for (let cursor = index; cursor < end; cursor++) {
        const item = entry(nodes[cursor]);
        content += item.media ? header(item.media) + children(item.root) + '}' : children(item.root);
      }
      edits.push([nodes[index].loc.start.offset, nodes[end - 1].loc.end.offset,
        header(first.root) + content + '}']);
    }
    index = end;
  }
  // Keep every untouched selector, declaration and token slice verbatim.
  for (const [start, end, replacement] of edits.reverse()) code = code.slice(0, start) + replacement + code.slice(end);
  return code;
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
    filename: 'search3.css', code: Buffer.from(groupAdjacentMedia(printed)), minify: true,
    exclude: Features.Nesting,
    targets: { safari: (16 << 16) | (5 << 8), chrome: 120 << 16, firefox: 117 << 16 }
  });
  assert.equal(result.warnings.length, 0, 'Search3 CSS optimizer emitted warnings');
  const output = result.code.toString() + '\n';
  parsed(output);
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

module.exports = { compactCSS, printCSS, parsed, groupAdjacentMedia };
