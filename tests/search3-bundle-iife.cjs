// Extract the actual compiled owner without relying on optional source comments.
const assert = require('node:assert/strict');
const acorn = require('../scripts/build/search3-js/node_modules/acorn');
const trees = new Map();

function contains(node, predicate) {
  if (!node || typeof node !== 'object') return false;
  if (predicate(node)) return true;
  return Object.values(node).some(value => Array.isArray(value)
    ? value.some(child => contains(child, predicate))
    : contains(value, predicate));
}

module.exports = function bundledIife(source, marker) {
  assert.equal(Number(typeof marker.global === 'string') + Number(typeof marker.literal === 'string'), 1,
    'select one stable global assignment or exact runtime string');
  if (!trees.has(source)) trees.set(source, acorn.parse(source, { ecmaVersion: 2022, sourceType: 'script' }));
  const matches = trees.get(source).body.filter(statement => contains(statement, node => {
    if (marker.literal !== undefined) return node.type === 'Literal' && node.value === marker.literal;
    if (node.type !== 'AssignmentExpression' || node.operator !== '=') return false;
    const left = node.left;
    return left.type === 'MemberExpression' && left.object.type === 'Identifier' && left.object.name === 'window'
      && (left.computed ? left.property.type === 'Literal' && left.property.value === marker.global
        : left.property.type === 'Identifier' && left.property.name === marker.global);
  }));
  assert.equal(matches.length, 1, `exactly one compiled owner for ${JSON.stringify(marker)}`);
  const statement = matches[0];
  assert.equal(statement.type, 'ExpressionStatement', 'compiled owner is one top-level expression');
  let expression = statement.expression;
  while (expression.type === 'UnaryExpression') expression = expression.argument;
  assert.equal(expression.type, 'CallExpression', 'compiled owner remains an IIFE call');
  assert.equal(expression.callee.type, 'FunctionExpression', 'compiled owner retains its function scope');
  return source.slice(statement.start, statement.end) + '\n';
};
