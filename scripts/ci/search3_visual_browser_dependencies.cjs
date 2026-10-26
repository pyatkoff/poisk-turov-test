// A dry run supplies Playwright's complete libraries/tools/fonts list. It does
// not prove installation; only positive dpkg facts may skip the stock installer.
const fs = require('node:fs');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');

const MIRROR_FILE = '/etc/apt/apt-mirrors.txt';
const AZURE = 'http://azure.archive.ubuntu.com/ubuntu/';
const ARCHIVE = 'https://archive.ubuntu.com/ubuntu/';
const SECURITY = 'https://security.ubuntu.com/ubuntu/';

// Only the known image mirror list is eligible. Preserve all three existing
// repositories and the file's formatting; change their priority digits only.
function preferredMirrorList(input) {
  const text = String(input);
  const lines = text.replace(/\n$/, '').split('\n');
  if (lines.length !== 3) return null;
  const priorities = new Map(), parsed = [];
  for (const line of lines) {
    const match = line.match(/^(\S+)([ \t]+priority:)([123])([ \t]*)$/);
    if (!match || ![AZURE, ARCHIVE, SECURITY].includes(match[1]) || priorities.has(match[1])) return null;
    priorities.set(match[1], Number(match[3]));
    parsed.push(match);
  }
  if (new Set(priorities.values()).size !== 3 || priorities.get(SECURITY) !== 3) return null;
  const stock = priorities.get(AZURE) === 1 && priorities.get(ARCHIVE) === 2;
  const preferred = priorities.get(AZURE) === 2 && priorities.get(ARCHIVE) === 1;
  if (!stock && !preferred) return null;
  if (preferred) return { changed: false, content: text };
  const next = parsed.map(match => match[1] + match[2] + (match[1] === ARCHIVE ? 1 : match[1] === AZURE ? 2 : 3) + match[4]).join('\n');
  return { changed: true, content: next + (text.endsWith('\n') ? '\n' : '') };
}

function preferUbuntuMirror(io = fs, uid = process.getuid?.(), nonce = () => crypto.randomBytes(8).toString('hex')) {
  if (uid !== 0) return { changed: false, reason: 'not-root' };
  let originalFd, temporaryFd, temporaryPath, temporaryCreated = false, writeAttempted = false;
  const sameFile = (a, b) => b.isFile() && !b.isSymbolicLink() &&
    ['dev', 'ino', 'mode', 'uid', 'gid', 'size', 'mtimeMs'].every(key => a[key] === b[key]);
  try {
    const original = io.lstatSync(MIRROR_FILE);
    if (original.isSymbolicLink()) return { changed: false, reason: 'symlink' };
    if (!original.isFile()) return { changed: false, reason: 'not-file' };
    originalFd = io.openSync(MIRROR_FILE, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
    if (!sameFile(original, io.fstatSync(originalFd))) return { changed: false, reason: 'changed-during-read' };
    const transformation = preferredMirrorList(io.readFileSync(originalFd, 'utf8'));
    if (!transformation) return { changed: false, reason: 'unsupported-list' };
    if (!transformation.changed) return { changed: false, reason: 'already-preferred' };
    const token = nonce();
    if (!/^[0-9a-f]{16}$/.test(token)) throw new Error('Invalid temporary file token');
    temporaryPath = MIRROR_FILE + '.search3-' + process.pid + '-' + token;
    writeAttempted = true;
    temporaryFd = io.openSync(temporaryPath, 'wx', original.mode & 0o7777);
    temporaryCreated = true;
    io.fchownSync(temporaryFd, original.uid, original.gid);
    io.fchmodSync(temporaryFd, original.mode & 0o7777);
    io.writeFileSync(temporaryFd, transformation.content, { encoding: 'utf8' });
    io.fsyncSync(temporaryFd);
    io.closeSync(temporaryFd);
    temporaryFd = undefined;
    if (!sameFile(original, io.lstatSync(MIRROR_FILE))) throw new Error('Mirror list changed before replacement');
    io.renameSync(temporaryPath, MIRROR_FILE);
    temporaryCreated = false;
    return { changed: true, reason: 'preferred-existing-ubuntu-mirror' };
  } catch {
    if (writeAttempted) throw new Error('Could not atomically prepare the existing Ubuntu mirror list; installation must not continue');
    return { changed: false, reason: 'unavailable' };
  } finally {
    if (temporaryFd !== undefined) io.closeSync(temporaryFd);
    if (originalFd !== undefined) io.closeSync(originalFd);
    if (temporaryCreated) io.unlinkSync(temporaryPath);
  }
}

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
  if (process.argv.length === 3 && process.argv[2] === '--prefer-ubuntu-mirror') {
    console.log(JSON.stringify(preferUbuntuMirror()));
    process.exit(0);
  }
  if (process.argv.length !== 3) throw new Error('Expected the installed Playwright CLI path');
  const started = performance.now();
  const result = inspectDependencies(process.argv[2]);
  console.log(JSON.stringify({ ...result, preflight_ms: Math.round(performance.now() - started) }));
  if (process.env.GITHUB_OUTPUT)
    fs.appendFileSync(process.env.GITHUB_OUTPUT, `ready=${result.ready}\n`);
}

module.exports = { packagesFromDryRun, inspectDependencies, preferredMirrorList, preferUbuntuMirror, MIRROR_FILE };
