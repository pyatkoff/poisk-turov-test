'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { compact, parsed } = require('../scripts/build/search3-js/compact.cjs');

(async () => {
  const cases = [
    `'use strict';\n/*! license */\nvar x = 1, y = 2; x\n++y;\noutput([x, y]);`,
    `function value(){ return\n output(42); } output(value()); // ASI matters\n`,
    `var regex = /a[\\/]b/g; var n = 18 / 3 / 2; output([regex.source, n]);`,
    `var name = 'hotel'; output({name, price: 5000, ['__proto__']: null});`,
    'output([String.raw`\\u0061`, `line${2 + 3}`, "/* CSS literal */"]);',
    `var x = 0; outer: for(var i=0;i<3;i++){ x++; if(i===1) break outer; } output(x);`,
    `output([Object.is(-0,0), 0x10, 'a\\\nb', 1n + 2n]);`,
    `var x = null; output(x?.price ?? 'missing');`
  ];
  function execute(code) {
    const values = [];
    vm.runInNewContext(code, { output: value => values.push(value) });
    return JSON.stringify(values, (_, value) => typeof value === 'bigint' ? String(value) + 'n' : value);
  }
  for (const original of cases) {
    const output = await compact(original);
    assert.equal(execute(output), execute(original));
    assert.deepEqual(parsed(output), parsed(original));
    assert.equal(await compact(output), output, 'printing is deterministic and idempotent');
  }
  assert.notDeepEqual(parsed('({__proto__})'), parsed('({__proto__:__proto__})'),
    'prototype setter and shorthand property are not equivalent');
  assert.notDeepEqual(parsed('String.raw`\\u0061`'), parsed('String.raw`a`'),
    'tagged template raw text remains part of the syntax contract');
  assert.notDeepEqual(parsed('price + fee'), parsed('price - fee'));
  assert.notDeepEqual(parsed('send(value)'), parsed('send(other)'));
  await assert.rejects(compact('function broken('), SyntaxError);
  await assert.rejects(compact('function value(){return\n {price:42};}'), /changed syntax/,
    'dropping even an empty statement fails closed');
  await assert.rejects(compact('output(`line\n${2 + 3}`)'), /changed syntax/,
    'rewriting template raw text fails closed even for an untagged template');
  console.log('PASS: format-only JS preserves syntax, comments, ASI, literals, templates and execution');
})().catch(error => { console.error(error); process.exitCode = 1; });
