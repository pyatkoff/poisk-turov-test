'use strict';
const assert = require('node:assert/strict');
const css = require('../scripts/build/search3-js/node_modules/css-tree');
const { compactCSS, printCSS, parsed, groupAdjacentMedia } = require('../scripts/build/search3-js/compact-css.cjs');

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
  const output = printCSS(source);
  assert.deepEqual(parsed(output), parsed(source));
  assert.deepEqual(fragments(output), fragments(source), 'selectors, conditions and values retain exact source bytes');
  assert.equal(printCSS(output), output);
}
for (const note of ['/* Copyright owner */', '/* @license MIT */', '/*# sourceMappingURL=source.css.map */']) {
  const source = `.a { color: red; }\n${note}\n`;
  assert.equal(compactCSS(source), source, 'preserve protected comments in place');
}
for (const value of ['red/**/blue', '1/**/px', 'var(--x)/**/var(--y)']) {
  const source = `.a { --tokens: ${value}; color: #AABBCC; }\n`;
  assert.equal(compactCSS(source), source, 'preserve raw CSS token boundaries');
}
assert.notDeepEqual(parsed('.a .b{color:red}'), parsed('.a.b{color:red}'));
assert.notDeepEqual(parsed('.a{color:red!important}'), parsed('.a{color:red}'));
assert.throws(() => compactCSS('.a{ color }'), /Colon is expected/);
const nested = compactCSS('@media (max-width: 999px) { .a { & .b { color: #AABBCC !important; margin: 0px 0px; } } }');
assert.match(nested, /\.a\{& \.b\{/);
assert.match(nested, /!important/);
assert.match(nested, /#abc/);
assert.equal(compactCSS(nested), nested);
// Selector nesting paths preserve specificity; recording conditions separately
// proves that moving a declaration-free root across @media changes neither.
function cascadeStream(code) {
  const result = [];
  function visit(node, selectors = [], conditions = []) {
    if (node.type === 'Rule') selectors = selectors.concat(css.generate(node.prelude));
    if (node.type === 'Atrule') conditions = conditions.concat(node.name + ':' + (node.prelude ? css.generate(node.prelude) : ''));
    if (node.type === 'Declaration') result.push([selectors, conditions, css.generate(node)]);
    if (node.children) node.children.forEach(child => visit(child, selectors, conditions));
    if (node.block) visit(node.block, selectors, conditions);
  }
  visit(css.parse(code));
  return result;
}
const grouped = '.a{& .x{color:red}}@media(width>900px){.a{& .y,& .z{color:blue}}}.a{& .x{color:green}}';
assert.equal(groupAdjacentMedia(grouped), '.a{& .x{color:red}@media(width>900px){& .y,& .z{color:blue}}& .x{color:green}}');
assert.deepEqual(cascadeStream(groupAdjacentMedia(grouped)), cascadeStream(grouped));
assert.equal(groupAdjacentMedia(groupAdjacentMedia(grouped)), groupAdjacentMedia(grouped));
for (const source of [
  '.a{color:red}@media(width>900px){.a{& .x{color:blue}}}',
  '.a,.b{& .x{color:red}}@media(width>900px){.a,.b{& .y{color:blue}}}',
  '.a{& .x{color:red}}@media(width>900px){.a{& .y{color:blue}}.b{color:red}}',
  '.a{& .x{color:red}}@supports(display:grid){.a{& .y{color:blue}}}',
  '.a{& .x{color:red}}.b{& .y{color:blue}}',
  '.a{& .x{color:red}}/*! keep position */.a{& .y{color:blue}}',
  '.a{& .x{color:red}}@media(width>900px){/*! keep media position */.a{& .y{color:blue}}}',
  '.a{& .x{color:red}}@media(width>900px){.a{& .y{color:blue}}/*! keep trailing position */}',
  '.a{& .x{color:red}}@media(width>900px){.a/*! keep root header */{& .y{color:blue}}}'
]) assert.equal(groupAdjacentMedia(source), source, 'unsafe or nonadjacent root structures stay exact');
assert.throws(() => groupAdjacentMedia('.a{.x{color:red}}'), /Identifier is expected/,
  'unsupported implicit nesting fails closed');
module.exports = { cascadeStream };
console.log('PASS: CSS printing preserves exact selectors/conditions/values, nesting, priority and protected notes');
