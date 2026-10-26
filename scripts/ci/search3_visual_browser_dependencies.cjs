// A dry run supplies Playwright's complete libraries/tools/fonts list. It does
// not prove installation; only positive dpkg facts may skip the stock installer.
const fs = require('node:fs');
const { spawnSync } = require('node:child_process');

function packagesFromDryRun(output) {
  const token = '[a-z0-9][a-z0-9+.-]*';
  const command = new RegExp('^(?:sh -c|sudo -- sh -c|su root -c) "apt-get update&& apt-get install -y --no-install-recommends (' + token + '(?: ' + token + ')*)"$');
  const match = String(output).trim().match(command);
  if (!match) return null;
  const packages = match[1].split(' ');
  return new Set(packages).size === packages.length ? packages : null;
}

function inspectDependencies(cli, run = spawnSync, platform = process.platform) {
  const result = { ready: false, package_count: 0, reason: 'preflight-error' };
  const capture = (command, args) => run(command, args, {
    encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'pipe'],
  });
  const clean = r => r && r.status === 0 && !r.error && !r.signal && !String(r.stderr || '').trim();
  try {
    if (platform !== 'linux') return { ...result, reason: 'unsupported-platform' };
    const version = capture(cli, ['--version']);
    if (!clean(version) || String(version.stdout).trim() !== 'Version 1.55.0')
      return { ...result, reason: 'unrecognized-version' };
    const dryRun = capture(cli, ['install-deps', '--dry-run', 'chromium']);
    if (!clean(dryRun)) return { ...result, reason: 'dry-run-failed' };
    const packages = packagesFromDryRun(dryRun.stdout);
    if (!packages) return { ...result, reason: 'unrecognized-package-list' };
    result.package_count = packages.length;
    const query = capture('dpkg-query', ['--show', '--showformat=${Package}\t${Status}\n', '--', ...packages]);
    if (!clean(query)) return { ...result, reason: 'package-query-failed' };
    const rows = String(query.stdout).trim().split('\n').map(line => line.split('\t'));
    if (rows.length !== packages.length || rows.some(row => row.length !== 2))
      return { ...result, reason: 'incomplete-package-facts' };
    const facts = new Map(rows);
    if (facts.size !== packages.length || packages.some(pkg => !facts.has(pkg)))
      return { ...result, reason: 'incomplete-package-facts' };
    if (packages.some(pkg => facts.get(pkg) !== 'install ok installed'))
      return { ...result, reason: 'package-not-installed' };
    return { ...result, ready: true, reason: 'all-packages-installed' };
  } catch {
    return result;
  }
}

if (require.main === module) {
  if (process.argv.length !== 3) throw new Error('Expected the installed Playwright CLI path');
  const started = performance.now();
  const result = inspectDependencies(process.argv[2]);
  console.log(JSON.stringify({ ...result, preflight_ms: Math.round(performance.now() - started) }));
  if (process.env.GITHUB_OUTPUT)
    fs.appendFileSync(process.env.GITHUB_OUTPUT, `ready=${result.ready}\n`);
}

module.exports = { packagesFromDryRun, inspectDependencies };
