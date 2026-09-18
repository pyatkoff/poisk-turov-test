'use strict';
// This is only a browser-tier selector. Source/build/hash/isolation gates still run.
const css = require('css-tree');
const { createHash } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const SOURCE = 'src/search3/styles/results-layout.css';
const OUTPUT = 'v2/search3-results-filters-v1.css';
const IMPORT = 'docs/project/search3-production-import.json';
const CARD_ROOTS = new Set(['hotel-card', 'hotel-main', 'hotel-photo', 'hotel-thumbs',
  'hotel-body', 'hotel-title', 'hotel-place', 'hotel-rating', 'hotel-description-summary',
  'hotel-details', 'hotel-details-facts', 'hotel-offers-summary']);

function outsideCards(source) {
  const ast = css.parse(source, { onParseError(error) { throw error; } });
  function visit(node, inBody = false) {
    if (!node.children) return;
    node.children.forEach((child, item, list) => {
      if (child.type === 'Rule') {
        const selector = css.generate(child.prelude);
        if (!inBody && ['body.search3-candidate', '.search3-candidate #results'].includes(selector)) {
          visit(child.block, true);
          return;
        }
        // Only direct, declaration-only rules under the canonical body/media tree.
        // Never prune global, selected-tour, sibling, grouped-escape or nested rules.
        const scoped = inBody && child.prelude.type === 'SelectorList'
          && child.prelude.children.toArray().every(s => {
            const parts = s.children.toArray();
            return parts[0]?.type === 'NestingSelector'
              && parts[1]?.type === 'Combinator' && parts[1].name === ' '
              && parts[2]?.type === 'ClassSelector' && CARD_ROOTS.has(parts[2].name)
              && !parts.some(p => p.type === 'Combinator' && p.name !== ' ' && p.name !== '>');
          })
          && child.block.children.toArray().every(c => c.type === 'Declaration');
        if (scoped) list.remove(item);
      } else if (child.type === 'Atrule' && child.name === 'media' && child.block) {
        visit(child.block, inBody);
      }
    });
  }
  visit(ast);
  return css.generate(ast);
}

function classify(changed, readBefore, readAfter) {
  try {
    const expected = [SOURCE, OUTPUT, IMPORT];
    if (changed.length !== 3 || !expected.every(p => changed.includes(p))) {
      return { cardOnly: false, reason: 'mixed-or-unknown-paths' };
    }
    if (outsideCards(readBefore(SOURCE)) !== outsideCards(readAfter(SOURCE))) {
      return { cardOnly: false, reason: 'shared-or-structural-css' };
    }
    function importWithoutHash(read) {
      const data = JSON.parse(read(IMPORT));
      const asset = data.assets['search3-results-filters-v1.css'];
      if (asset.productionSha256 !== createHash('sha256').update(read(OUTPUT)).digest('hex')) {
        throw new Error('unmatched-generated-hash');
      }
      delete asset.productionSha256;
      return JSON.stringify(data);
    }
    if (importWithoutHash(readBefore) !== importWithoutHash(readAfter)) {
      return { cardOnly: false, reason: 'non-css-import-change' };
    }
    return { cardOnly: true, reason: 'card-scoped-css-only' };
  } catch (error) {
    return { cardOnly: false, reason: 'unproven-scope' };
  }
}

if (require.main === module) {
  let result;
  try {
    const [base, head] = process.argv.slice(2);
    if (![base, head].every(sha => /^[a-f0-9]{40}$/.test(sha || ''))) throw new Error('invalid-sha');
    const git = args => execFileSync('git', args, { encoding: 'utf8' });
    const changed = git(['diff', '--name-only', '-z', base, head]).split('\0').filter(Boolean);
    result = classify(changed, p => git(['show', `${base}:${p}`]), p => git(['show', `${head}:${p}`]));
  } catch (error) {
    result = { cardOnly: false, reason: 'unproven-git-diff' };
  }
  console.log(`card-only=${result.cardOnly}`);
  console.log(`reason=${result.reason}`);
}
module.exports = { classify, outsideCards, SOURCE, OUTPUT, IMPORT };
