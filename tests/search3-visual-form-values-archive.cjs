'use strict';

const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

module.exports = function archiveFormValues(evidence, states) {
  const directory = path.resolve(evidence);
  const names = states.map(({label, width}) => `form-values-${label}-${width}.png`);
  assert(names.length > 0 && new Set(names).size === names.length);

  const archive = path.join(directory, 'form-values.tar.xz');
  const staging = fs.mkdtempSync(path.join(directory, '.form-values-'));
  const pending = path.join(staging, 'form-values.tar.xz');
  try {
    execFileSync('tar', ['-cJf', pending, '--', ...names], {cwd: directory});
    const entries = execFileSync('tar', ['-tf', pending], {encoding: 'utf8'}).trimEnd().split(/\r?\n/);
    assert.deepEqual(entries, names, 'all form screenshots retain their original names');
    execFileSync('tar', ['-xf', pending, '-C', staging]);
    for (const name of names) {
      const original = fs.readFileSync(path.join(directory, name));
      assert(original.equals(fs.readFileSync(path.join(staging, name))), name + ' must round-trip byte for byte');
    }
    fs.renameSync(pending, archive);
    try {
      for (const name of names) fs.unlinkSync(path.join(directory, name));
    } catch (error) {
      execFileSync('tar', ['-xf', archive, '-C', directory]);
      throw error;
    }
  } finally {
    fs.rmSync(staging, {recursive: true, force: true});
  }
};
