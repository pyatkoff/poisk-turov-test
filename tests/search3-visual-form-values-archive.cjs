'use strict';

const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

function archiveScreenshots(evidence, archiveName, names) {
  assert(names.length > 0 && new Set(names).size === names.length);
  assert(names.every(name => path.basename(name) === name && name.endsWith('.png')));
  const directory = path.resolve(evidence);
  const archive = path.join(directory, archiveName);
  const present = names.filter(name => fs.existsSync(path.join(directory, name)));
  const complete = present.length === names.length;
  assert(complete || fs.existsSync(archive), archiveName + ' requires a complete screenshot set');
  // A partial attempt never enters the acceptance directory or shadows the old archive.
  const staging = fs.mkdtempSync(path.join(path.dirname(directory), '.search3-frames-'));
  const pending = path.join(staging, archiveName);
  try {
    if (complete) execFileSync('tar', ['-cJf', pending, '--', ...names], {cwd: directory});
    const source = complete ? pending : archive;
    const entries = execFileSync('tar', ['-tf', source], {encoding: 'utf8'}).trimEnd().split(/\r?\n/);
    assert.deepEqual(entries, names, 'all screenshots retain their original names');
    execFileSync('tar', ['-xf', source, '-C', staging]);
    for (const name of present) {
      const original = fs.readFileSync(path.join(directory, name));
      assert(original.equals(fs.readFileSync(path.join(staging, name))), name + ' must round-trip byte for byte');
    }
    if (complete) fs.renameSync(pending, archive);
    const removed = [];
    try {
      for (const name of present) {
        fs.unlinkSync(path.join(directory, name));
        removed.push(name);
      }
    } catch (error) {
      for (const name of removed) fs.copyFileSync(path.join(staging, name), path.join(directory, name));
      throw error;
    }
  } finally {
    fs.rmSync(staging, {recursive:true, force:true});
  }
}

module.exports = function archiveFormValues(evidence, states) {
  archiveScreenshots(evidence, 'form-values.tar.xz', states.map(({label, width}) => `form-values-${label}-${width}.png`));
};

module.exports.calendarBudget = function archiveCalendarBudget(evidence) {
  const names = [];
  for (const width of [360, 390, 430, 768, 1280]) {
    names.push(`filter-keyboard-budget-${width}.png`);
    if ([390, 768, 1280].includes(width)) names.push(`calendar-budget-${width}.png`);
    if (width === 768 || width === 1280) {
      for (const state of ['error', 'manual', 'pending', 'visible']) names.push(`calendar-selected-${state}-${width}.png`);
    }
    for (const height of width === 1280 ? [720, 900] : [900]) {
      const size = `${width}x${height}`;
      names.push(`calendar-range-draft-${size}.png`);
      // The acceptance viewer consumes these two representative PNGs directly.
      if (size !== '768x900') names.push(`calendar-range-applied-${size}.png`);
      if (width === 430 || width === 1280) {
        if (size !== '1280x720') names.push(`calendar-viewport-fit-${size}.png`);
        names.push(`calendar-viewport-fit-reopened-${size}.png`);
      }
    }
  }
  archiveScreenshots(evidence, 'calendar-budget-forms.tar.xz', names);
};
