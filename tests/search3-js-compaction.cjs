'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const { compact, compactBindings, compactBindingTokens, print, parsed } = require('../scripts/build/search3-js/compact.cjs');

(async () => {
  const cases = [
    `'use strict';\n/*! license */\nvar x = 1, y = 2; x\n++y;\noutput([x, y]);`,
    `function value(){ return\n output(42); } output(value()); // ASI matters\n`,
    `var regex = /a[\\/]b/g; var n = 18 / 3 / 2; output([regex.source, n]);`,
    `var name = 'hotel'; output({name, price: 5000, ['__proto__']: null});`,
    'output([String.raw`\\u0061`, `line${2 + 3}`, "/* CSS literal */"]);',
    `var x = 0; outer: for(var i=0;i<3;i++){ x++; if(i===1) break outer; } output(x);`,
    `output([Object.is(-0,0), 0x10, 'a\\\nb', 1n + 2n]);`,
    `var x = null; output(x?.price ?? 'missing');`,
    `var prototype = { inherited: 7 };
     var sample = { "public-key": 'He said "yes"', "plain": 10000,
       "__proto__": prototype, ["__proto__"]: 9 };
     output([Object.keys(sample), sample["public-key"], sample.plain,
       Object.getPrototypeOf(sample) === prototype, sample["__proto__"],
       10000000, 0.000001, 9007199254740991, Object.is(-0, 0)]);`
  ];
  function execute(code) {
    const values = [];
    vm.runInNewContext(code, { output: value => values.push(value) });
    return JSON.stringify(values, (_, value) => typeof value === 'bigint' ? String(value) + 'n' : value);
  }
  for (const original of cases) {
    const output = await compact(original);
    assert.equal(execute(await compactBindings(original)), execute(original));
    assert.equal(execute(await compactBindingTokens(original)), execute(original));
    assert.equal(execute(output), execute(original));
    assert.deepEqual(parsed(await print(original)), parsed(original));
    assert.equal(await compact(output), output, 'printing is deterministic and idempotent');
  }
  const localNames = [
    `(function(){ var veryLongLocalPrice = 72099; var feeValue = 17218; output({price:veryLongLocalPrice + feeValue}); })();`,
    `(function(){ var hotelName = 'hotel'; function readableFunction(argumentValue){return {hotelName, argumentValue};} output([readableFunction.name,readableFunction(2)]); })();`,
    `(function(){ var outerValue=3; function first(innerValue){ return function second(){return outerValue+innerValue;}; } output(first(4)()); })();`,
    `(function(){ var privateValue=42; output(eval('privateValue')); })();`,
    `(function(){ class NamedHotel { value(){return 4;} } output([NamedHotel.name,new NamedHotel().value()]); })();`,
    `(function(){ var reads=0; var supplier={get price(){reads++;return 17217.6;}}; supplier.price; output([72099 + supplier.price, reads]); })();`,
    `(function(){ function selectedPrice(price, ignored){return price;} output([selectedPrice.name,selectedPrice.length,selectedPrice(89317)]); })();`
  ];
  for (const original of localNames) {
    const output = await compact(original);
    assert.equal(execute(await compactBindings(original)), execute(original));
    assert.equal(execute(await compactBindingTokens(original)), execute(original));
    assert.equal(execute(output), execute(original));
    assert.ok(Buffer.byteLength(output) <= Buffer.byteLength(original));
  }
  assert.ok(!(await compact(localNames[0])).includes('veryLongLocalPrice'), 'local bindings actually shrink');
  const notedSource = `/*! retained notice */
// ordinary source explanation
/* @license retained license */
/* Copyright retained owner */
/* @preserve retained directive */
output("/* literal source explanation */");
//# sourceURL=search3-fixture.js
`;
  const notedOutput = await compact(notedSource);
  assert.equal(execute(notedOutput), execute(notedSource));
  assert.deepEqual(parsed(notedOutput).comments, parsed(notedSource).comments.filter(
    comment => !comment.value.includes('ordinary source explanation')));
  assert.deepEqual(parsed(await print(notedSource)), parsed(notedSource),
    'exact first-stage printing still retains every source comment');
  assert.deepEqual(parsed(await compactBindingTokens(notedSource)).comments, parsed(notedOutput).comments);
  const tokenCases = [
    `(function(){function returnValue(){try{return\n {price:42};}catch(error){;} } output(returnValue());})();`,
    `(function(){var countryCatalog={"2":"Turkey",'10':'Egypt'};output([Object.keys(countryCatalog),countryCatalog[2]]);})();`,
    `(function(){var publicName=3;var {publicName:privateValue,otherValue=4}={publicName,otherValue:5};output({publicName,otherValue,total:privateValue+otherValue});})();`,
    `(function(){var inferredFunction=function(){return 1};var inferredClass=class {};output([inferredFunction.name,inferredClass.name]);})();`,
    `(function(){var escaped\\u004eame=2;output(escapedName);})();`,
    `(function(){var beforeComment=1;output(beforeComment +/* comment */+ 2);})();`,
    `(function(){var selectedPrice=72099;var fuelPrice=17217.6;output([selectedPrice+fuelPrice,Math.round(selectedPrice+fuelPrice)]);})();`
  ];
  for (const original of tokenCases) {
    const output = await compactBindingTokens(original);
    assert.equal(execute(output), execute(original));
    assert.equal(await compactBindingTokens(original), output, 'token plans are deterministic');
  }
  assert.ok(!(await compactBindingTokens(localNames[0])).includes('veryLongLocalPrice'),
    'token compaction actually shortens local bindings');
  assert.ok((await compactBindingTokens(tokenCases[0])).includes('{;}'), 'empty statements remain');
  assert.ok((await compactBindingTokens(tokenCases[1])).includes('"2":"Turkey"'),
    'numeric string property keys retain their original spelling');
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
  console.log('PASS: checked printing and optimization preserve execution, closures, eval, public keys, names, arity and getter side effects');
})().catch(error => { console.error(error); process.exitCode = 1; });
