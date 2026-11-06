"""Offline dependency-delivery regression; no SSH, schema or supplier execution.

The real workflow copy step runs against local transport doubles. Only the
delivered TOP500 reader runs in PHP, with a synthetic in-memory SQLite database.
BOOTSTRAP_WORKFLOW can select the pre-fix workflow for defect reproduction;
PHP_TEST_COMMAND can supply a PHP executable and its extension options.
"""
from __future__ import annotations

import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import sys
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
REGISTRY = Path('app/integrations/anex-search-mapping-registry.php')
HELPER = Path('v2/data/top500-anex-priority-v1.php')
STEP = 'Copy data tooling and verify installed files'
LEGACY_STEP = 'Copy data tooling and initialize schema'
SCHEMA_STEP = 'Initialize changed schema and inspect database'
SCOPE_PATH = ROOT / 'scripts/deploy/anytour_data_bootstrap_scope.py'
spec = importlib.util.spec_from_file_location('bootstrap_scope', SCOPE_PATH)
scope = importlib.util.module_from_spec(spec)
spec.loader.exec_module(scope)


def copy_step(path: Path, name=None) -> str:
    """Read this literal YAML block, without executing or expanding other steps."""
    lines = path.read_text().splitlines()
    names = (name,) if name else (STEP, LEGACY_STEP)
    matches = [i for i, line in enumerate(lines) if line.strip() in ('- name: ' + item for item in names)]
    if len(matches) != 1:
        raise ValueError('expected one bootstrap copy step')
    start = matches[0]
    step_indent = len(lines[start]) - len(lines[start].lstrip())
    for i in range(start + 1, len(lines)):
        line = lines[i]
        if line.strip() and len(line) - len(line.lstrip()) <= step_indent:
            break
        if line.strip() == 'run: |':
            indent = len(line) - len(line.lstrip()) + 2
            block = []
            for body in lines[i + 1:]:
                if body.strip() and len(body) - len(body.lstrip()) < indent:
                    break
                block.append(body[indent:] if body.strip() else '')
            return '\n'.join(block) + '\n'
    raise ValueError('bootstrap shell block missing')


def fake_transport(tool: str, args: list[str]) -> int:
    """Local copies only; remote shell commands are parsed or recorded, never run."""
    repo = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_REPO']).resolve()
    remote = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_REMOTE']).resolve()
    log = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_LOG'])

    def record(kind, **fields):
        with log.open('a') as output:
            output.write(json.dumps(dict(kind=kind, **fields)) + '\n')

    def remote_path(value):
        for prefix in ('$HOME/www/anytoour.ru', '~/www/anytoour.ru'):
            if value == prefix or value.startswith(prefix + '/'):
                suffix = value[len(prefix):].lstrip('/')
                if '..' in Path(suffix).parts or '\\' in suffix:
                    raise ValueError('unsafe fake remote path')
                target = (remote / suffix).resolve()
                if not target.is_relative_to(remote):
                    raise ValueError('fake remote escape')
                return target
        raise ValueError('unexpected fake remote path')

    recursive = False
    while args and args[0].startswith('-'):
        option = args.pop(0)
        if option in ('-i', '-o') and args:
            args.pop(0)
        elif option == '-r':
            recursive = True
        else:
            raise ValueError('unexpected transport option')
    if tool == 'ssh':
        if len(args) != 2 or args[0] != 'fixture@offline.invalid':
            raise ValueError('unexpected SSH destination')
        command = args[1]
        if '\n' in command:
            if 'bootstrap-schema-v1.php' in command or 'db-doctor-v1.php' in command:
                # Preserve the old schema/doctor block as data; never execute it.
                record('schema_block_intercepted', command=command)
                return 0
            if 'sha256sum --check --strict -' not in command:
                raise ValueError('unexpected remote verification block')
            lines = sys.stdin.read().splitlines()
            expected = {}
            for line in lines:
                match = re.fullmatch(r'([0-9a-f]{64})  (.+)', line)
                if not match or match[2] in expected:
                    raise ValueError('invalid file readback manifest')
                expected[match[2]] = match[1]
            wanted = {str(REGISTRY), 'data/' + HELPER.name}
            if set(expected) != wanted or len(lines) != len(wanted):
                raise ValueError('unexpected readback file set')
            for path, checksum in expected.items():
                file = remote / path
                if not file.is_file() or hashlib.sha256(file.read_bytes()).hexdigest() != checksum:
                    record('file_readback_failed', path=path)
                    return 45
            record('file_readback_verified', hashes=expected)
            print('ANYTOUR_TOP500_FILE_READBACK_OK')
            return 0
        tokens = shlex.split(command)
        while tokens:
            split = tokens.index('&&') if '&&' in tokens else len(tokens)
            part, tokens = tokens[:split], tokens[split + 1:]
            if part[:2] == ['mkdir', '-p'] and len(part) == 3:
                remote_path(part[2]).mkdir(parents=True, exist_ok=True)
            elif part[:2] == ['test', '-s'] and len(part) == 3:
                if not remote_path(part[2]).is_file() or remote_path(part[2]).stat().st_size == 0:
                    return 1
            else:
                raise ValueError('remote command not allowed by offline double')
        return 0
    if tool != 'scp' or len(args) != 2 or not args[1].startswith('fixture@offline.invalid:'):
        raise ValueError('unexpected SCP command')
    source = (repo / args[0]).resolve()
    if not source.is_relative_to(repo):
        raise ValueError('fake source escape')
    destination = remote_path(args[1].split(':', 1)[1])
    if not destination.is_dir():
        raise ValueError('destination directory was not created')
    if source == repo / REGISTRY and os.environ.get('ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE') == '1':
        record('dependency_copy_failed')
        return 42
    if not source.exists():
        record('source_missing', source=str(source.relative_to(repo)))
        return 43
    if source.is_dir():
        if not recursive or source != repo / 'v2/data':
            raise ValueError('unexpected recursive copy')
        target_registry = remote / REGISTRY
        record('data_copy', dependency_ready=target_registry.is_file()
               and target_registry.read_bytes() == (repo / REGISTRY).read_bytes())
        shutil.copytree(source, destination, dirs_exist_ok=True)
    else:
        if source != repo / REGISTRY:
            raise ValueError('unexpected dependency file')
        shutil.copy2(source, destination / source.name)
        if os.environ.get('ANYTOUR_BOOTSTRAP_TEST_CORRUPTION') == '1':
            with (destination / source.name).open('ab') as file:
                file.write(b'\ncorrupted fixture\n')
        record('dependency_copied')
    return 0


PHP_READER = r'''
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY);
CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INTEGER, catalog_hotel_id INTEGER,
 match_class TEXT, approval_policy TEXT, enabled INTEGER, scope TEXT);
CREATE TABLE anex_hotel_decisions(anex_hotel_id INTEGER, decision_status TEXT, catalog_hotel_id INTEGER);
CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INTEGER, catalog_hotel_id INTEGER);
INSERT INTO catalog_hotels VALUES (8401),(900076);
INSERT INTO anex_hotel_search_mappings VALUES
 (8401,900076,'exact','owner_exact_and_strong_20260908',1,'preview');");
require $argv[1] . '/data/top500-anex-priority-v1.php';
$registry = v2_top500_registry($pdo);
$resolved = v2_top500_resolution($registry);
echo json_encode(['loaded' => $registry !== null, 'state' => $resolved['state'],
 'ids' => $resolved['tourvisor_ids']], JSON_THROW_ON_ERROR);
'''


class BootstrapDeliveryTest(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory(prefix='anytour-bootstrap-offline-')
        self.addCleanup(temporary.cleanup)
        self.directory = Path(temporary.name)
        self.repo = self.directory / 'source'
        self.remote = self.directory / 'fake-host'
        self.log = self.directory / 'transport.jsonl'
        for path in (HELPER, REGISTRY):
            (self.repo / path).parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / path, self.repo / path)
        self.remote.mkdir()
        (self.remote / 'config.php').write_text('offline fixture, not a configuration\n')
        binaries = self.directory / 'bin'
        binaries.mkdir()
        for tool in ('ssh', 'scp'):
            shim = binaries / tool
            shim.write_text('#!/bin/bash\nexec ' + shlex.quote(sys.executable) + ' '
                            + shlex.quote(str(Path(__file__).resolve()))
                            + ' --fake-' + tool + ' "$@"\n')
            shim.chmod(0o700)
        for tool in ('sha256sum', 'sed'):
            binary = shutil.which(tool)
            self.assertIsNotNone(binary, tool + ' is required by the real copy step')
            (binaries / tool).symlink_to(binary)
        self.env = dict(os.environ, PATH=str(binaries), HOST_VALUE='offline.invalid', USER_VALUE='fixture',
                        RUNNER_TEMP=str(self.directory), GITHUB_SHA='1' * 40,
                        BOOTSTRAP_MODE='files-only', ANYTOUR_BOOTSTRAP_TEST_REPO=str(self.repo),
                        ANYTOUR_BOOTSTRAP_TEST_REMOTE=str(self.remote), ANYTOUR_BOOTSTRAP_TEST_LOG=str(self.log))
        self.env.pop('ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE', None)
        self.env.pop('ANYTOUR_BOOTSTRAP_TEST_CORRUPTION', None)

    def workflow(self):
        return Path(os.environ.get('BOOTSTRAP_WORKFLOW', str(ROOT / '.github/workflows/bootstrap-anytour-data.yml')))

    def deliver(self, *, fail_copy=False, corrupt=False, mode='files-only'):
        env = dict(self.env)
        if fail_copy:
            env['ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE'] = '1'
        if corrupt:
            env['ANYTOUR_BOOTSTRAP_TEST_CORRUPTION'] = '1'
        if mode is None:
            env.pop('BOOTSTRAP_MODE', None)
        else:
            env['BOOTSTRAP_MODE'] = mode
        return subprocess.run(['/bin/bash', '-c', copy_step(self.workflow())], cwd=self.repo,
                              env=env, text=True, capture_output=True, timeout=15)

    def events(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def read_delivered_identity(self):
        command = shlex.split(os.environ.get('PHP_TEST_COMMAND', 'php'))
        result = subprocess.run(command + ['-r', PHP_READER, str(self.remote)],
                                text=True, capture_output=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return json.loads(result.stdout)

    def test_dependency_first_delivery_loads_registry(self):
        result = self.deliver()
        self.assertEqual(result.returncode, 0, result.stderr)
        events = self.events()
        copied = next(event for event in events if event['kind'] == 'data_copy')
        self.assertTrue(copied['dependency_ready'], 'TOP500 consumers arrived without the MATCH dependency')
        self.assertEqual((self.remote / REGISTRY).read_bytes(), (ROOT / REGISTRY).read_bytes())
        self.assertEqual((self.remote / 'data' / HELPER.name).read_bytes(), (ROOT / HELPER).read_bytes())
        self.assertEqual(self.read_delivered_identity(),
                         {'loaded': True, 'state': 'partially_resolved', 'ids': [900076]})
        self.assertEqual(sum(event['kind'] == 'file_readback_verified' for event in events), 1)
        self.assertFalse(any(event['kind'] == 'schema_block_intercepted' for event in events))

    def test_missing_dependency_stays_unresolved(self):
        (self.remote / 'data').mkdir()
        shutil.copy2(self.repo / HELPER, self.remote / 'data' / HELPER.name)
        self.assertEqual(self.read_delivered_identity(),
                         {'loaded': False, 'state': 'registry_unavailable', 'ids': []})
        self.assertEqual(self.events(), [])

    def test_dependency_failure_stops_data_and_schema(self):
        for failure in ('copy_failed', 'missing_source'):
            with self.subTest(failure=failure):
                self.log.unlink(missing_ok=True)
                if failure == 'missing_source':
                    (self.repo / REGISTRY).unlink()
                result = self.deliver(fail_copy=failure == 'copy_failed')
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse((self.remote / 'data' / HELPER.name).exists())
                self.assertFalse(any(event['kind'] in ('data_copy', 'schema_block_intercepted')
                                     for event in self.events()))

    def test_files_only_never_runs_schema(self):
        result = self.deliver()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse(any(event['kind'] == 'schema_block_intercepted' for event in self.events()),
                         'files-only delivery attempted schema bootstrap or database diagnostics')
        self.assertIn('ANYTOUR_DATA_SCHEMA_SKIPPED', result.stdout)
        self.assertTrue(any(event['kind'] == 'file_readback_verified' for event in self.events()))

    def test_invalid_scope_stops_before_transport(self):
        for mode in (None, '', 'unknown'):
            with self.subTest(mode=mode):
                self.assertNotEqual(self.deliver(mode=mode).returncode, 0)
                self.assertEqual(self.events(), [])

    def test_corrupt_delivery_fails_readback(self):
        result = self.deliver(corrupt=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertTrue(any(event['kind'] == 'file_readback_failed' for event in self.events()))
        self.assertFalse(any(event['kind'] == 'schema_block_intercepted' for event in self.events()))

    def test_schema_mode_keeps_explicit_initializer(self):
        workflow = self.workflow().read_text()
        header = workflow.split('- name: ' + SCHEMA_STEP, 1)[1].split('run: |', 1)[0]
        self.assertIn("if: steps.scope.outputs.mode == 'schema'", header)
        self.assertLess(workflow.index('- name: Select data delivery scope'),
                        workflow.index('- name: Prepare SSH key'))
        delivered = self.deliver(mode='schema')
        self.assertEqual(delivered.returncode, 0, delivered.stderr)
        self.assertNotIn('ANYTOUR_DATA_SCHEMA_SKIPPED', delivered.stdout)
        result = subprocess.run(['/bin/bash', '-c', copy_step(self.workflow(), SCHEMA_STEP)],
                                cwd=self.repo, env=self.env, text=True, capture_output=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stderr)
        schema = [event for event in self.events() if event['kind'] == 'schema_block_intercepted']
        self.assertEqual(len(schema), 1)
        self.assertIn('php "$root/data/bootstrap-schema-v1.php"', schema[0]['command'])
        self.assertIn('php "$root/data/db-doctor-v1.php"', schema[0]['command'])


class BootstrapScopeTest(unittest.TestCase):
    def test_closed_scope_and_malformed_diff(self):
        self.assertEqual(scope.choose_scope('workflow_dispatch')['mode'], 'schema')
        self.assertEqual(scope.choose_scope('push', list(scope.FILES_ONLY_PATHS))['mode'], 'files-only')
        for path in ('v2/data/schema-v1.sql', 'v2/data/bootstrap-schema-v1.php', 'v2/data/db-v1.php',
                     'v2/data/db-doctor-v1.php', 'unknown file.php', 'scripts/deploy/new-helper.py'):
            with self.subTest(path=path):
                self.assertEqual(scope.choose_scope('push', [str(HELPER), path])['mode'], 'schema')
        self.assertEqual(scope.choose_scope('push', [str(HELPER)], [str(HELPER)])['mode'], 'schema')
        self.assertEqual(scope.parse_paths(b'unknown file.php\0'), ['unknown file.php'])
        for raw in (b'', b'path', b'path\0\0', b'path\0path\0', b'../path\0', b'\xff\0'):
            with self.subTest(raw=raw), self.assertRaises(scope.ScopeError):
                scope.parse_paths(raw)
        for event in ('pull_request', 'workflow_run'):
            with self.subTest(event=event), self.assertRaises(scope.ScopeError):
                scope.choose_scope(event, [str(HELPER)])

    def test_immutable_git_pins_and_mode_output(self):
        with tempfile.TemporaryDirectory(prefix='anytour-bootstrap-git-') as temporary:
            repo = Path(temporary)

            def git(*args):
                result = subprocess.run(['git', '-c', 'user.name=Offline fixture',
                                         '-c', 'user.email=offline@example.invalid', *args],
                                        cwd=repo, text=True, capture_output=True, timeout=15)
                self.assertEqual(result.returncode, 0, result.stderr)
                return result.stdout.strip()

            def commit():
                git('add', '-A')
                git('commit', '-qm', 'offline fixture')
                return git('rev-parse', 'HEAD')

            git('init', '-q')
            (repo / HELPER).parent.mkdir(parents=True)
            (repo / HELPER).write_text('initial helper\n')
            wrapper = repo / 'v2/data/top-hotels-v1.php'
            wrapper.write_text('initial wrapper\n')
            before = commit()
            (repo / HELPER).write_text('changed helper\n')
            after = commit()
            event = {'before': before, 'after': after}
            self.assertEqual(scope.inspect_scope('push', event, after, repo)['mode'], 'files-only')

            event_path, output_path = repo / 'event.json', repo / 'scope-output'
            event_path.write_text(json.dumps(event))
            env = dict(os.environ, GITHUB_EVENT_NAME='push', GITHUB_EVENT_PATH=str(event_path),
                       GITHUB_SHA=after, GITHUB_OUTPUT=str(output_path))
            result = subprocess.run([sys.executable, str(SCOPE_PATH)], cwd=repo,
                                    env=env, text=True, capture_output=True, timeout=15)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(output_path.read_text(), 'mode=files-only\n')

            tree = git('rev-parse', 'HEAD^{tree}')
            unrelated = git('commit-tree', tree, '-m', 'unrelated root')
            failures = [({'before': '0' * 40, 'after': after}, after),
                        ({'before': 'f' * 40, 'after': after}, after),
                        ({'before': unrelated, 'after': after}, after),
                        ({'before': after, 'after': after}, after),
                        ({'before': before, 'after': before}, after),
                        (event, before), (event, 'A' * 40)]
            for candidate, source in failures:
                with self.subTest(candidate=candidate, source=source), self.assertRaises(scope.ScopeError):
                    scope.inspect_scope('push', candidate, source, repo)
            event_path.write_text(json.dumps({'before': 'f' * 40, 'after': after}))
            output_path.unlink()
            result = subprocess.run([sys.executable, str(SCOPE_PATH)], cwd=repo,
                                    env=env, text=True, capture_output=True, timeout=15)
            self.assertNotEqual(result.returncode, 0)
            self.assertFalse(output_path.exists(), 'invalid evidence must never publish a scope')
            self.assertEqual(scope.inspect_scope('workflow_dispatch', {}, after, repo)['mode'], 'schema')

            # Renaming between two allowed source paths still includes a deletion.
            event_path.unlink()
            (repo / REGISTRY).parent.mkdir(parents=True)
            git('mv', str(HELPER), str(REGISTRY))
            renamed = commit()
            result = scope.inspect_scope('push', {'before': after, 'after': renamed}, renamed, repo)
            self.assertEqual((result['mode'], result['changed_paths'], result['deleted_paths']), ('schema', 2, 1))
            wrapper.unlink()
            deleted = commit()
            result = scope.inspect_scope('push', {'before': renamed, 'after': deleted}, deleted, repo)
            self.assertEqual((result['mode'], result['deleted_paths']), ('schema', 1))


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] in ('--fake-ssh', '--fake-scp'):
        try:
            sys.exit(fake_transport(sys.argv[1][7:], sys.argv[2:]))
        except (ValueError, OSError) as error:
            print('OFFLINE_TRANSPORT_REFUSED: ' + str(error), file=sys.stderr)
            sys.exit(44)
    unittest.main()
