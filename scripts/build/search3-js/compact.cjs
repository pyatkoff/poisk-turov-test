'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { isDeepStrictEqual } = require('node:util');
const acorn = require('acorn');
const terser = require('terser');
const { compactCSS } = require('./compact-css.cjs');

// Compare syntax, not source formatting. Keep operators, names, literal values,
// directive text, template raw text, property order and every statement.
function syntax(value) {
  if (Array.isArray(value)) return value.map(syntax);
  if (typeof value === 'bigint') return { bigintValue: String(value) };
  if (!value || typeof value !== 'object') return value;
  const result = {};
  for (const [key, child] of Object.entries(value)) {
    if (value.type && (key === 'start' || key === 'end')) continue;
    if (value.type === 'Literal' && key === 'raw') continue;
    // ES5 printing expands {name} to {name:name}. These have the same key/value.
    // __proto__ is deliberately excluded: its colon notation changes semantics.
    if (value.type === 'Property' && key === 'shorthand' && child &&
        value.key.type === 'Identifier' && value.value.type === 'Identifier' &&
        value.key.name === value.value.name && value.key.name !== '__proto__') {
      result[key] = false;
    } else result[key] = syntax(child);
  }
  return result;
}

function parsed(code) {
  const comments = [];
  const tree = acorn.parse(code, { ecmaVersion: 2022, sourceType: 'script', onComment: comments });
  return { tree: syntax(tree), comments: comments.map(({ type, value }) => ({ type, value })) };
}

async function print(code) {
  const before = parsed(code);
  const result = await terser.minify(code, {
    compress: false,
    mangle: false,
    format: { comments: 'all', quote_style: 3, wrap_iife: true,
      keep_quoted_props: true, keep_numbers: true }
  });
  const output = result.code ? result.code + '\n' : '';
  assert.ok(isDeepStrictEqual(parsed(output), before), 'Search3 JS printing changed syntax or comments');
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

async function compact(code) {
  const output = await print(code);
  // Optimize the preview build after exact printing. Retain public properties,
  // globals, function/class names and argument arity; no unsafe arithmetic or
  // assumptions that property reads are side-effect free.
  const renamed = await terser.minify(output, {
    compress: { passes: 2, sequences: false, unsafe: false, unsafe_math: false,
      pure_getters: false, keep_fargs: true },
    mangle: { toplevel: false, eval: false, properties: false },
    keep_fnames: true,
    keep_classnames: true,
    // Source notes stay in the readable modules and exact first-stage print.
    // Keep legal notices and tool directives in the served output.
    // Shorten literal spelling and optional parentheses without changing values or keys.
    format: { comments: /^!|@(?:license|preserve|cc_on)|copyright|source(?:mapping)?url/i,
      quote_style: 0, wrap_iife: false,
      keep_quoted_props: false, keep_numbers: false }
  });
  const compacted = renamed.code ? renamed.code + '\n' : '';
  parsed(compacted); // Reject invalid output before the builder writes any asset.
  return Buffer.byteLength(compacted) < Buffer.byteLength(code) ? compacted : code;
}

// Shared runtime uses binding renaming only: no statement, condition or arithmetic
// compression. Keep function/class names, arity, globals, properties and eval scopes.
async function compactBindings(code) {
  const exact = await print(code);
  const result = await terser.minify(exact, {
    compress: false,
    mangle: { toplevel: false, eval: false, properties: false },
    keep_fnames: true,
    keep_classnames: true,
    format: { comments: /^!|@(?:license|preserve|cc_on)|copyright|source(?:mapping)?url/i,
      quote_style: 3, wrap_iife: true, keep_quoted_props: true, keep_numbers: true }
  });
  const output = result.code ? result.code + '\n' : '';
  function shape(node) {
    if (Array.isArray(node)) return node.map(shape);
    if (!node || typeof node !== 'object') return node;
    return Object.fromEntries(Object.entries(node)
      .filter(([key]) => !(node.type === 'Identifier' && key === 'name'))
      .map(([key, value]) => [key, shape(value)]));
  }
  assert.deepEqual(shape(parsed(output).tree), shape(parsed(exact).tree),
    'Shared JS renaming changed statement, literal or arithmetic structure');
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

module.exports = { compact, compactBindings, print, parsed };
if (require.main === module) {
  (async () => {
    const input = JSON.parse(fs.readFileSync(0, 'utf8'));
    const output = {};
    for (const [name, code] of Object.entries(input)) {
      output[name] = name.endsWith('.css') ? compactCSS(code) : await compact(code);
    }
    process.stdout.write(JSON.stringify(output));
  })().catch(error => {
    process.stderr.write(`Search3 asset compaction failed: ${error.message}\n`);
    process.exitCode = 1;
  });
}
