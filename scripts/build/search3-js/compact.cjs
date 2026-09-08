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

// Some legacy sources contain syntax that Terser's printer normalizes even with
// compression disabled (empty statements and quoted numeric keys). For these
// files use only its scope-aware name plan, then edit the original name tokens.
// The original operators, literal spelling, punctuation and line breaks survive.
async function compactBindingTokens(code) {
  const tokens = [], comments = [];
  const tree = acorn.parse(code, { ecmaVersion: 2022, sourceType: 'script',
    onToken: tokens, onComment: comments });
  const reserved = new Set();
  function walk(value, visit) {
    if (!value || typeof value !== 'object') return;
    if (Array.isArray(value)) { value.forEach(item => walk(item, visit)); return; }
    visit(value);
    for (const child of Object.values(value)) walk(child, visit);
  }
  // Expanding shorthand would alter the original tokens. Keep those bindings,
  // including destructuring defaults, so public keys remain exactly as written.
  walk(tree, node => {
    if (node.type === 'Property' && node.shorthand && node.key.type === 'Identifier') {
      reserved.add(node.key.name);
    }
    const binding = node.type === 'VariableDeclarator' ? node.id : node.left;
    const value = node.type === 'VariableDeclarator' ? node.init : node.right;
    if (['VariableDeclarator', 'AssignmentExpression', 'AssignmentPattern'].includes(node.type) &&
        binding?.type === 'Identifier' && value && !value.id &&
        ['FunctionExpression', 'ArrowFunctionExpression', 'ClassExpression'].includes(value.type)) {
      reserved.add(binding.name); // Preserve anonymous function/class inferred .name.
    }
  });
  const result = await terser.minify(code, {
    compress: false,
    mangle: { toplevel: false, eval: false, properties: false, reserved: [...reserved] },
    keep_fnames: true, keep_classnames: true,
    format: { ast: true, code: false }
  });
  const byStart = new Map(tokens.map(token => [token.start, token]));
  const replacements = new Map(), seen = new Set();
  function names(node) {
    if (!node || typeof node !== 'object' || seen.has(node)) return;
    seen.add(node);
    if (node.TYPE && node.TYPE.startsWith('Symbol') && node.thedef?.mangled_name &&
        node.name !== node.thedef.mangled_name) {
      const token = byStart.get(node.start.pos);
      assert.ok(token && token.type.label === 'name' && token.value === node.name,
        'Shared JS binding plan did not identify an original name token');
      const name = node.thedef.mangled_name;
      assert.ok(!replacements.has(token.start) || replacements.get(token.start) === name,
        'Shared JS binding plan assigned conflicting names');
      replacements.set(token.start, name);
    }
    for (const [key, value] of Object.entries(node)) {
      if (['scope', 'thedef', 'parent_scope', 'variables', 'globals', 'enclosed'].includes(key)) continue;
      if (Array.isArray(value)) value.forEach(names); else names(value);
    }
  }
  names(result.ast);
  const retained = comments.filter(comment =>
    /^!|@(?:license|preserve|cc_on)|copyright|source(?:mapping)?url/i.test(comment.value));
  const units = [...tokens.filter(token => token.type.label !== 'eof'), ...retained]
    .sort((a, b) => a.start - b.start);
  let output = '', end = 0;
  for (const unit of units) {
    const gap = code.slice(end, unit.start);
    if (gap) output += /[\r\n\u2028\u2029]/.test(gap) ? '\n' : ' ';
    output += replacements.get(unit.start) || code.slice(unit.start, unit.end);
    end = unit.end;
  }
  output += '\n';
  // Compare every AST field with the original plus only the planned identifiers.
  // This is deliberately stricter than ignoring all identifier names in a shape.
  walk(tree, node => {
    if (node.type === 'Identifier' && replacements.has(node.start)) {
      node.name = replacements.get(node.start);
    }
  });
  assert.deepEqual(parsed(output), {
    tree: syntax(tree), comments: retained.map(({ type, value }) => ({ type, value }))
  }, 'Shared JS token compaction changed syntax beyond planned local bindings');
  return Buffer.byteLength(output) < Buffer.byteLength(code) ? output : code;
}

module.exports = { compact, compactBindings, compactBindingTokens, print, parsed };
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
