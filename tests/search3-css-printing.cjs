'use strict';
const assert = require('node:assert/strict');
const css = require('../scripts/build/search3-js/node_modules/css-tree');
const { compactCSS, parsed } = require('../scripts/build/search3-js/compact-css.cjs');

function fragments(code) {
  const result = [];
  css.walk(css.parse(code, { positions: true }), node => {
    const key = node.type === 'Declaration' ? 'value' :
      node.type === 'Rule' || node.type === 'Atrule' ? 'prelude' : null;
    if (key && node[key] && node[key].loc) {
      const { start, end } = node[key].loc;
      result.push([node.type, code.slice(start.offset, end.offset)]);
    }
  });
  return result;
}

const cases = [
  '@media (max-width: 999px) {\n body.a {\n & .card, & .other { color: red !important; }\n }\n}',
  '.a { --tokens: red/**/blue; width: calc(100% - 2px); content: "/* literal */"; }',
  '.a { width: calc(1px/**/+/**/2px); color: red; color: color(display-p3 1 0 0); }',
  '.\\31\n  x { content: "a\\\n  b"; --spacing: first  second; }',
  '/*! license */\n.a { color: #AABBCC; margin: 0px  0px; }',
  '@supports selector(:is(.a, .b)) { .a:is(.x, .y) { display: grid; } }'
];
for (const source of cases) {
  const output = compactCSS(source);
  assert.deepEqual(parsed(output), parsed(source));
  assert.deepEqual(fragments(output), fragments(source), 'selectors, conditions and values retain exact source bytes');
  assert.equal(compactCSS(output), output);
}
for (const note of ['/* Copyright owner */', '/* @license MIT */', '/*# sourceMappingURL=source.css.map */']) {
  const source = `.a { color: red; }\n${note}\n`;
  assert.equal(compactCSS(source), source, 'preserve protected comments in place');
}
assert.notDeepEqual(parsed('.a .b{color:red}'), parsed('.a.b{color:red}'));
assert.notDeepEqual(parsed('.a{color:red!important}'), parsed('.a{color:red}'));
assert.throws(() => compactCSS('.a{ color }'), /Colon is expected/);
console.log('PASS: CSS printing preserves exact selectors/conditions/values, nesting, priority and protected notes');
