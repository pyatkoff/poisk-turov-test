'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const cp = require('node:child_process');
const root = require('node:path').resolve(__dirname, '../..');
const css = require(root + '/scripts/build/search3-js/node_modules/css-tree');
const manifest = JSON.parse(fs.readFileSync(root + '/src/search3/manifest.json'));
const files = Object.entries(manifest.assets).filter(([name]) => name.endsWith('.css')).flatMap(([, names]) => names);
function read(file, before) {
  const path = 'src/search3/' + file;
  return before ? cp.execFileSync('git', ['show', 'b8d7282a2a90117a0fd7d291d946e6b2315399ae:' + path], { cwd: root, encoding: 'utf8' }) : fs.readFileSync(root + '/' + path, 'utf8');
}
function mediaActive(text, width) {
  return text.split(',').some(part => [...part.matchAll(/(min|max)-width\s*:\s*([\d.]+)px/g)].every(([, side, n]) => side === 'min' ? width >= Number(n) : width <= Number(n)));
}
function normalized(sel) { return sel.replace(/\s+/g,' ').replace(/\s*([>,])\s*/g,'$1').trim(); }
function effective(before, width) {
  const map = new Map();
  function visit(node, parents = [], active = true) {
    if (node.type === 'Atrule' && node.name === 'media') active = active && mediaActive(css.generate(node.prelude), width);
    if (!active) return;
    if (node.type === 'Rule') {
      const children = node.prelude.children.toArray().map(css.generate);
      parents = parents.length ? parents.flatMap(parent => children.map(child => normalized(child.replace(/&/g,parent)))) : children.map(normalized);
    }
    if (node.type === 'Declaration') {
      for (const selector of parents) {
        if (!/\.(?:mrf-|search3-mobile-(?:toolbar|filter-slot))/.test(selector)) continue;
        const key = selector + '|' + node.property;
        const previous = map.get(key);
        if (!previous || node.important || !previous.important) map.set(key, { value: css.generate(node.value), important: node.important });
      }
    }
    if (node.children) node.children.forEach(child => visit(child, parents, active));
    if (node.block) visit(node.block, parents, active);
  }
  files.forEach(file => visit(css.parse(read(file,before))));
  return [...map.entries()].sort(([a],[b]) => a.localeCompare(b));
}
for (const width of [375,760,760.5,761,999,999.5,1000]) {
  const before = effective(true,width), after = effective(false,width);
  assert.deepEqual(after,before, 'Drawer selector/declaration priority changed at ' + width);
  console.log('PASS: drawer final selector/value/important map unchanged at ' + width + ' (' + before.length + ' declarations)');
}
