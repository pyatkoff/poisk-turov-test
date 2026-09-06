'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { isDeepStrictEqual } = require('node:util');
const acorn = require('acorn');
const terser = require('terser');

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

async function compact(code) {
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

module.exports = { compact, parsed };
if (require.main === module) {
  (async () => {
    const input = JSON.parse(fs.readFileSync(0, 'utf8'));
    const output = {};
    for (const [name, code] of Object.entries(input)) output[name] = await compact(code);
    process.stdout.write(JSON.stringify(output));
  })().catch(error => {
    process.stderr.write(`Search3 JS compaction failed: ${error.message}\n`);
    process.exitCode = 1;
  });
}
