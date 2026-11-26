'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');

function runPerformanceSuite(root) {
  const evidence = fs.mkdtempSync(path.join(os.tmpdir(), 'search3-performance-'));
  const benchmarks = [
    ['first-open', 180000],
    ['first-tours', 240000],
    ['room-ready', 180000],
    ['single-quote', 180000, '1'],
  ];
  try {
    for (const [name, timeout, ...scenario] of benchmarks) {
      execFileSync('xvfb-run', [
        '-a', '--server-args=-screen 0 1366x1400x24', process.execPath,
        path.join(__dirname, `search3-visual-${name}.cjs`),
        root, path.join(evidence, name), ...scenario,
      ], {stdio:'inherit', timeout});
    }
  } finally {
    fs.rmSync(evidence, {recursive:true, force:true});
  }
}

module.exports = runPerformanceSuite;
if (require.main === module) {
  runPerformanceSuite(fs.realpathSync(process.argv[2]));
}
